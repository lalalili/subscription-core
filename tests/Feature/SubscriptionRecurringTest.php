<?php

use Lalalili\SubscriptionCore\Enums\BillingCycle;
use Lalalili\SubscriptionCore\Enums\PaymentStatus;
use Lalalili\SubscriptionCore\Enums\SubscriptionStatus;
use Lalalili\SubscriptionCore\Models\Subscription;
use Lalalili\SubscriptionCore\Models\SubscriptionOrder;
use Lalalili\SubscriptionCore\Models\SubscriptionPlan;
use Lalalili\SubscriptionCore\Services\SubscriptionService;
use Lalalili\SubscriptionCore\Tests\Fixtures\SubscriptionOwner;

function recurringPlan(): SubscriptionPlan
{
    return SubscriptionPlan::factory()->create(['monthly_price' => 300, 'yearly_price' => 3000]);
}

it('subscribe 標記 recurring 並建立首期 PENDING 訂單（period_sequence=1）', function (): void {
    $owner = SubscriptionOwner::create();
    $plan = recurringPlan();

    $order = app(SubscriptionService::class)->subscribe($owner, $plan, BillingCycle::Monthly, true, 'M', 999);

    expect($order->payment_status)->toBe(PaymentStatus::PENDING)
        ->and($order->period_sequence)->toBe(1);

    $subscription = $order->subscription;
    expect($subscription->is_recurring)->toBeTrue()
        ->and($subscription->recurring_period_type)->toBe('M')
        ->and($subscription->recurring_exec_times)->toBe(999)
        ->and($subscription->status)->toBe(SubscriptionStatus::Pending);
});

it('recordRecurringCharge 首期（totalSuccessTimes=1）啟用訂閱與首單', function (): void {
    $owner = SubscriptionOwner::create();
    $plan = recurringPlan();
    $service = app(SubscriptionService::class);
    $order = $service->subscribe($owner, $plan, BillingCycle::Monthly, true, 'M', 999);

    $service->recordRecurringCharge($order->number, 1, 300, now(), '111222');

    $order->refresh();
    $subscription = $order->subscription->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::COMPLETE)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->total_success_times)->toBe(1)
        ->and($subscription->gwsr)->toBe('111222');
});

it('recordRecurringCharge 第二期建立新訂單並延展 expires_at', function (): void {
    $owner = SubscriptionOwner::create();
    $plan = recurringPlan();
    $service = app(SubscriptionService::class);
    $order = $service->subscribe($owner, $plan, BillingCycle::Monthly, true, 'M', 999);
    $service->recordRecurringCharge($order->number, 1, 300, now(), '111222');

    $subscription = $order->subscription->refresh();
    $expiresBefore = $subscription->expires_at->copy();

    $service->recordRecurringCharge($order->number, 2, 300, now(), '333444');

    $subscription->refresh();
    $secondOrder = SubscriptionOrder::query()
        ->where('merchant_subscription_id', $subscription->getKey())
        ->where('period_sequence', 2)
        ->first();

    expect($secondOrder)->not->toBeNull()
        ->and($secondOrder->payment_status)->toBe(PaymentStatus::COMPLETE)
        ->and($secondOrder->amount)->toBe(300)
        ->and($subscription->total_success_times)->toBe(2)
        ->and($subscription->gwsr)->toBe('333444')
        ->and($subscription->expires_at->greaterThan($expiresBefore))->toBeTrue();
});

it('recordRecurringCharge 對同一期重送具冪等性', function (): void {
    $owner = SubscriptionOwner::create();
    $plan = recurringPlan();
    $service = app(SubscriptionService::class);
    $order = $service->subscribe($owner, $plan, BillingCycle::Monthly, true, 'M', 999);
    $service->recordRecurringCharge($order->number, 1, 300, now());

    $service->recordRecurringCharge($order->number, 2, 300, now());
    $service->recordRecurringCharge($order->number, 2, 300, now());

    $count = SubscriptionOrder::query()
        ->where('merchant_subscription_id', $order->subscription->getKey())
        ->where('period_sequence', 2)
        ->count();

    expect($count)->toBe(1);
});

it('expireSubscriptions 不作廢 recurring 訂閱（即使已過期）', function (): void {
    $owner = SubscriptionOwner::create();
    $plan = recurringPlan();

    $recurring = Subscription::factory()->create([
        'merchant_id' => $owner->getKey(),
        'subscription_plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'is_recurring' => true,
        'expires_at' => now()->subDay(),
    ]);

    $nonRecurring = Subscription::factory()->create([
        'merchant_id' => $owner->getKey(),
        'subscription_plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'is_recurring' => false,
        'expires_at' => now()->subDay(),
    ]);

    $count = app(SubscriptionService::class)->expireSubscriptions();

    expect($count)->toBe(1)
        ->and($recurring->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($nonRecurring->refresh()->status)->toBe(SubscriptionStatus::Expired);
});

it('recordRecurringFailure 單次失敗 → 轉逾期未付、累計失敗次數、未終止', function (): void {
    $owner = SubscriptionOwner::create();
    $plan = recurringPlan();
    $service = app(SubscriptionService::class);
    $order = $service->subscribe($owner, $plan, BillingCycle::Monthly, true, 'M', 999);
    $service->recordRecurringCharge($order->number, 1, 300, now());

    $outcome = $service->recordRecurringFailure($order->number);

    expect($outcome)->not->toBeNull()
        ->and($outcome['terminated'])->toBeFalse()
        ->and($outcome['subscription']->status)->toBe(SubscriptionStatus::PastDue)
        ->and($outcome['subscription']->failed_charge_count)->toBe(1);
});

it('recordRecurringFailure 達門檻 → 終止訂閱（Cancelled）', function (): void {
    config()->set('subscription.recurring.failure_termination_threshold', 3);

    $owner = SubscriptionOwner::create();
    $plan = recurringPlan();
    $service = app(SubscriptionService::class);
    $order = $service->subscribe($owner, $plan, BillingCycle::Monthly, true, 'M', 999);
    $service->recordRecurringCharge($order->number, 1, 300, now());

    $service->recordRecurringFailure($order->number);
    $service->recordRecurringFailure($order->number);
    $outcome = $service->recordRecurringFailure($order->number);

    expect($outcome['terminated'])->toBeTrue()
        ->and($outcome['subscription']->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($outcome['subscription']->failed_charge_count)->toBe(3);
});

it('扣款成功重置連續失敗次數', function (): void {
    $owner = SubscriptionOwner::create();
    $plan = recurringPlan();
    $service = app(SubscriptionService::class);
    $order = $service->subscribe($owner, $plan, BillingCycle::Monthly, true, 'M', 999);
    $service->recordRecurringCharge($order->number, 1, 300, now());

    $service->recordRecurringFailure($order->number);
    expect($order->subscription->refresh()->failed_charge_count)->toBe(1);

    $service->recordRecurringCharge($order->number, 2, 300, now());

    expect($order->subscription->refresh()->failed_charge_count)->toBe(0)
        ->and($order->subscription->refresh()->status)->toBe(SubscriptionStatus::Active);
});

it('markPastDue 將訂閱轉為逾期未付', function (): void {
    $owner = SubscriptionOwner::create();
    $plan = recurringPlan();
    $subscription = Subscription::factory()->create([
        'merchant_id' => $owner->getKey(),
        'subscription_plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    app(SubscriptionService::class)->markPastDue($subscription);

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::PastDue);
});
