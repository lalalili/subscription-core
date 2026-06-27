<?php

namespace Lalalili\SubscriptionCore\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Lalalili\SubscriptionCore\Enums\BillingCycle;
use Lalalili\SubscriptionCore\Enums\PaymentStatus;
use Lalalili\SubscriptionCore\Enums\SubscriptionStatus;
use Lalalili\SubscriptionCore\Models\Subscription;
use Lalalili\SubscriptionCore\Models\SubscriptionOrder;
use Lalalili\SubscriptionCore\Models\SubscriptionPlan;
use Lalalili\SubscriptionCore\Support\OrderNumberGenerator;
use RuntimeException;

class SubscriptionService
{
    public function __construct(private readonly ?OrderNumberGenerator $orderNumberGenerator = null) {}

    public function subscribe(
        Model $owner,
        SubscriptionPlan $plan,
        BillingCycle $cycle,
        bool $recurring = false,
        ?string $periodType = null,
        ?int $execTimes = null,
    ): SubscriptionOrder {
        return DB::transaction(function () use ($owner, $plan, $cycle, $recurring, $periodType, $execTimes): SubscriptionOrder {
            $startsAt = now();
            $expiresAt = $cycle === BillingCycle::Yearly
                ? $startsAt->copy()->addYear()
                : $startsAt->copy()->addMonth();
            $price = $this->priceFor($plan, $cycle);
            $ownerForeignKey = config('subscription.owner.foreign_key', 'merchant_id');

            /** @var class-string<Subscription> $subscriptionModel */
            $subscriptionModel = config('subscription.models.subscription', Subscription::class);
            /** @var class-string<SubscriptionOrder> $orderModel */
            $orderModel = config('subscription.models.order', SubscriptionOrder::class);

            $subscription = $subscriptionModel::create([
                $ownerForeignKey => $owner->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'billing_cycle' => $cycle,
                'price' => $price,
                'status' => SubscriptionStatus::Pending,
                'is_recurring' => $recurring,
                'recurring_period_type' => $recurring ? $periodType : null,
                'recurring_exec_times' => $recurring ? $execTimes : null,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
            ]);

            return $orderModel::create([
                'number' => $this->orderNumberGenerator()->generate(),
                $ownerForeignKey => $owner->getKey(),
                'merchant_subscription_id' => $subscription->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'billing_cycle' => $cycle,
                'period_sequence' => $recurring ? 1 : null,
                'amount' => $price,
                'payment_status' => PaymentStatus::PENDING,
            ]);
        });
    }

    /**
     * 記錄綠界定期定額單期扣款並推進訂閱。
     *
     * 所有期數通知都帶同一個首期訂單號（$firstOrderNumber），以 $totalSuccessTimes 區分第幾期。
     * 第 1 期沿用首期訂單啟用訂閱；第 2 期起建立新的 SubscriptionOrder 並延展 expires_at。
     * 以 period_sequence 做冪等鍵，避免 notify／return 雙觸發或重送造成重複續期。
     */
    public function recordRecurringCharge(
        string $firstOrderNumber,
        int $totalSuccessTimes,
        int $amount,
        \DateTimeInterface $chargedAt,
        ?string $gwsr = null,
    ): void {
        DB::transaction(function () use ($firstOrderNumber, $totalSuccessTimes, $amount, $chargedAt, $gwsr): void {
            $ownerForeignKey = config('subscription.owner.foreign_key', 'merchant_id');

            /** @var class-string<SubscriptionOrder> $orderModel */
            $orderModel = config('subscription.models.order', SubscriptionOrder::class);

            $firstOrder = $orderModel::query()
                ->with('subscription')
                ->where('number', $firstOrderNumber)
                ->lockForUpdate()
                ->first();

            if (! $firstOrder instanceof SubscriptionOrder) {
                return;
            }

            $subscription = $firstOrder->subscription;
            if (! $subscription instanceof Subscription) {
                return;
            }

            $sequence = max(1, $totalSuccessTimes);

            $alreadyRecorded = $orderModel::query()
                ->where('merchant_subscription_id', $subscription->getKey())
                ->where('period_sequence', $sequence)
                ->where('payment_status', PaymentStatus::COMPLETE)
                ->exists();

            if ($alreadyRecorded) {
                return;
            }

            if ($sequence <= 1) {
                try {
                    $this->activateSubscription($firstOrderNumber, $chargedAt);
                } catch (ModelNotFoundException) {
                    // 首期訂單非 PENDING（已啟用）：notify+return 雙觸發下的冪等 no-op。
                    return;
                }
            } else {
                $cycle = $this->resolveBillingCycle($subscription->billing_cycle);

                $orderModel::create([
                    'number' => $this->orderNumberGenerator()->generate(),
                    $ownerForeignKey => $firstOrder->getAttribute($ownerForeignKey),
                    'merchant_subscription_id' => $subscription->getKey(),
                    'subscription_plan_id' => $subscription->subscription_plan_id,
                    'billing_cycle' => $cycle,
                    'period_sequence' => $sequence,
                    'amount' => $amount,
                    'payment_status' => PaymentStatus::COMPLETE,
                    'payment_status_message' => '定期定額自動扣款成功',
                    'payment_time' => $chargedAt,
                ]);

                $base = $subscription->expires_at instanceof \DateTimeInterface && $subscription->expires_at->isFuture()
                    ? $subscription->expires_at->copy()
                    : now();

                $subscription->update([
                    'status' => SubscriptionStatus::Active,
                    'expires_at' => $cycle === BillingCycle::Yearly ? $base->addYear() : $base->addMonth(),
                ]);

                $subscription->loadMissing(['owner', 'plan']);
                $this->syncOwnerAfterActivation($subscription);
            }

            $subscription->refresh()->update([
                'total_success_times' => $sequence,
                'gwsr' => $gwsr ?? $subscription->gwsr,
                // 扣款成功即重置連續失敗計數（綠界「連續」失敗 6 次才終止）。
                'failed_charge_count' => 0,
            ]);
        });
    }

    public function markPastDue(Subscription $subscription): void
    {
        $subscription->update(['status' => SubscriptionStatus::PastDue]);
    }

    /**
     * 記錄一次定期定額扣款失敗：累計連續失敗次數並轉為逾期未付；
     * 達設定門檻（對應綠界連續失敗 6 次自動終止）時，將訂閱標記為已取消並回收 owner 權限。
     *
     * @return array{subscription: Subscription, terminated: bool}|null 找不到訂閱時回 null
     */
    public function recordRecurringFailure(string $firstOrderNumber): ?array
    {
        return DB::transaction(function () use ($firstOrderNumber): ?array {
            /** @var class-string<SubscriptionOrder> $orderModel */
            $orderModel = config('subscription.models.order', SubscriptionOrder::class);

            $firstOrder = $orderModel::query()
                ->with('subscription')
                ->where('number', $firstOrderNumber)
                ->lockForUpdate()
                ->first();

            if (! $firstOrder instanceof SubscriptionOrder) {
                return null;
            }

            $subscription = $firstOrder->subscription;
            if (! $subscription instanceof Subscription) {
                return null;
            }

            $count = (int) $subscription->failed_charge_count + 1;
            $threshold = (int) config('subscription.recurring.failure_termination_threshold', 6);
            $terminated = $count >= $threshold;

            $updates = [
                'failed_charge_count' => $count,
                'status' => $terminated ? SubscriptionStatus::Cancelled : SubscriptionStatus::PastDue,
            ];

            if ($terminated) {
                $updates['cancelled_at'] = now();
            }

            $subscription->update($updates);

            if ($terminated) {
                $subscription->loadMissing('owner');
                $this->syncOwnerAfterExpiration($subscription);
            }

            return ['subscription' => $subscription->refresh(), 'terminated' => $terminated];
        });
    }

    public function activateSubscription(string $orderNumber, \DateTimeInterface $paymentTime): void
    {
        DB::transaction(function () use ($orderNumber, $paymentTime): void {
            /** @var class-string<SubscriptionOrder> $orderModel */
            $orderModel = config('subscription.models.order', SubscriptionOrder::class);

            $order = $orderModel::query()
                ->with(['subscription.plan', 'subscription.owner'])
                ->where('number', $orderNumber)
                ->where('payment_status', PaymentStatus::PENDING)
                ->lockForUpdate()
                ->firstOrFail();

            $order->update([
                'payment_status' => PaymentStatus::COMPLETE,
                'payment_status_message' => '訂單成立已付款',
                'payment_time' => $paymentTime,
            ]);

            $subscription = $order->subscription;
            if (! $subscription instanceof Subscription) {
                throw new RuntimeException('Subscription not found for order.');
            }

            $cycle = $this->resolveBillingCycle($subscription->billing_cycle);
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'starts_at' => now(),
                'expires_at' => $cycle === BillingCycle::Yearly ? now()->addYear() : now()->addMonth(),
            ]);

            $this->syncOwnerAfterActivation($subscription);
        });
    }

    public function grantSubscription(Model $owner, SubscriptionPlan $plan, BillingCycle $cycle): Subscription
    {
        return DB::transaction(function () use ($owner, $plan, $cycle): Subscription {
            $startsAt = now();
            $expiresAt = $cycle === BillingCycle::Yearly
                ? $startsAt->copy()->addYear()
                : $startsAt->copy()->addMonth();

            /** @var class-string<Subscription> $subscriptionModel */
            $subscriptionModel = config('subscription.models.subscription', Subscription::class);

            $subscription = $subscriptionModel::create([
                config('subscription.owner.foreign_key', 'merchant_id') => $owner->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'billing_cycle' => $cycle,
                'price' => $this->priceFor($plan, $cycle),
                'status' => SubscriptionStatus::Active,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
            ]);

            $subscription->setRelation('owner', $owner);
            $subscription->setRelation('plan', $plan);

            $this->syncOwnerAfterActivation($subscription);

            return $subscription;
        });
    }

    public function grantInternalSubscription(Model $owner, SubscriptionPlan $plan): Subscription
    {
        return DB::transaction(function () use ($owner, $plan): Subscription {
            $ownerForeignKey = config('subscription.owner.foreign_key', 'merchant_id');

            /** @var class-string<Subscription> $subscriptionModel */
            $subscriptionModel = config('subscription.models.subscription', Subscription::class);

            $subscriptionModel::query()
                ->where($ownerForeignKey, $owner->getKey())
                ->where('status', SubscriptionStatus::Active)
                ->update([
                    'status' => SubscriptionStatus::Cancelled->value,
                    'cancelled_at' => now(),
                ]);

            $subscription = $subscriptionModel::create([
                $ownerForeignKey => $owner->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'billing_cycle' => BillingCycle::Internal,
                'price' => 0,
                'status' => SubscriptionStatus::Active,
                'is_internal' => true,
                'starts_at' => now(),
                'expires_at' => null,
            ]);

            $subscription->setRelation('owner', $owner);
            $subscription->setRelation('plan', $plan);

            $this->syncOwnerAfterActivation($subscription);

            return $subscription;
        });
    }

    public function cancelSubscription(Subscription $subscription): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function expireSubscriptions(): int
    {
        $count = 0;

        /** @var class-string<Subscription> $subscriptionModel */
        $subscriptionModel = config('subscription.models.subscription', Subscription::class);

        $subscriptionModel::query()
            ->with(['owner', 'plan'])
            ->where('status', SubscriptionStatus::Active)
            ->where('is_internal', false)
            // 定期定額由綠界依週期自動扣款：續期、逾期(PastDue)、終止皆由 PeriodReturnURL webhook 推進，
            // 不可在續期通知到達前因 expires_at 過期而被排程提前作廢（避免權限閃斷）。
            ->where('is_recurring', false)
            ->where('expires_at', '<', now())
            ->each(function (Subscription $subscription) use (&$count): void {
                $subscription->update(['status' => SubscriptionStatus::Expired]);
                $this->syncOwnerAfterExpiration($subscription);
                $count++;
            });

        return $count;
    }

    private function priceFor(SubscriptionPlan $plan, BillingCycle $cycle): int
    {
        return (int) ($cycle === BillingCycle::Yearly ? $plan->yearly_price : $plan->monthly_price);
    }

    private function orderNumberGenerator(): OrderNumberGenerator
    {
        return $this->orderNumberGenerator ?? app(OrderNumberGenerator::class);
    }

    private function resolveBillingCycle(BillingCycle|string $billingCycle): BillingCycle
    {
        if ($billingCycle instanceof BillingCycle) {
            return $billingCycle;
        }

        return BillingCycle::from($billingCycle);
    }

    private function syncOwnerAfterActivation(Subscription $subscription): void
    {
        $owner = $subscription->owner;
        $plan = $subscription->plan;

        if (! $owner instanceof Model || ! $plan instanceof SubscriptionPlan) {
            return;
        }

        $updates = $this->ownerLimitUpdates($plan);
        $statusColumn = config('subscription.owner.status_column');

        if (is_string($statusColumn) && $statusColumn !== '') {
            $currentStatus = $owner->getAttribute($statusColumn);
            $currentStatusValue = $currentStatus instanceof \BackedEnum ? $currentStatus->value : $currentStatus;

            if ($currentStatusValue == config('subscription.owner.pending_status')) {
                $updates[$statusColumn] = config('subscription.owner.active_status');
            }
        }

        if ($updates !== []) {
            $owner->update($updates);
        }
    }

    private function syncOwnerAfterExpiration(Subscription $subscription): void
    {
        $owner = $subscription->owner;

        if (! $owner instanceof Model) {
            return;
        }

        $updates = [];
        foreach (config('subscription.owner.synced_limits', []) as $column => $featureKey) {
            if (is_string($column) && is_string($featureKey)) {
                $updates[$column] = 0;
            }
        }

        if ($updates !== []) {
            $owner->update($updates);
        }
    }

    /**
     * @return array<string, int>
     */
    private function ownerLimitUpdates(SubscriptionPlan $plan): array
    {
        $updates = [];

        foreach (config('subscription.owner.synced_limits', []) as $column => $featureKey) {
            if (is_string($column) && is_string($featureKey)) {
                $limit = $plan->limit($featureKey);
                if ($limit !== null) {
                    $updates[$column] = $limit;
                }
            }
        }

        return $updates;
    }
}
