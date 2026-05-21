<?php

namespace Lalalili\SubscriptionCore\Services;

use Illuminate\Database\Eloquent\Model;
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
    public function __construct(private readonly ?OrderNumberGenerator $orderNumberGenerator = null)
    {
    }

    public function subscribe(Model $owner, SubscriptionPlan $plan, BillingCycle $cycle): SubscriptionOrder
    {
        return DB::transaction(function () use ($owner, $plan, $cycle): SubscriptionOrder {
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
                $ownerForeignKey       => $owner->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'billing_cycle'        => $cycle,
                'price'                => $price,
                'status'               => SubscriptionStatus::Pending,
                'starts_at'            => $startsAt,
                'expires_at'           => $expiresAt,
            ]);

            return $orderModel::create([
                'number'                   => $this->orderNumberGenerator()->generate(),
                $ownerForeignKey           => $owner->getKey(),
                'merchant_subscription_id' => $subscription->getKey(),
                'subscription_plan_id'     => $plan->getKey(),
                'billing_cycle'            => $cycle,
                'amount'                   => $price,
                'payment_status'           => PaymentStatus::PENDING,
            ]);
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
                'payment_status'         => PaymentStatus::COMPLETE,
                'payment_status_message' => '訂單成立已付款',
                'payment_time'           => $paymentTime,
            ]);

            $subscription = $order->subscription;
            if (! $subscription instanceof Subscription) {
                throw new RuntimeException('Subscription not found for order.');
            }

            $cycle = $this->resolveBillingCycle($subscription->billing_cycle);
            $subscription->update([
                'status'     => SubscriptionStatus::Active,
                'starts_at'  => now(),
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
                'subscription_plan_id'                                  => $plan->getKey(),
                'billing_cycle'                                         => $cycle,
                'price'                                                 => $this->priceFor($plan, $cycle),
                'status'                                                => SubscriptionStatus::Active,
                'starts_at'                                             => $startsAt,
                'expires_at'                                            => $expiresAt,
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
                    'status'       => SubscriptionStatus::Cancelled->value,
                    'cancelled_at' => now(),
                ]);

            $subscription = $subscriptionModel::create([
                $ownerForeignKey       => $owner->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'billing_cycle'        => BillingCycle::Internal,
                'price'                => 0,
                'status'               => SubscriptionStatus::Active,
                'is_internal'          => true,
                'starts_at'            => now(),
                'expires_at'           => null,
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
            'status'       => SubscriptionStatus::Cancelled,
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
