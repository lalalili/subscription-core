<?php

namespace Lalalili\SubscriptionCore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lalalili\SubscriptionCore\Enums\PaymentStatus;
use Lalalili\SubscriptionCore\Models\Subscription;
use Lalalili\SubscriptionCore\Models\SubscriptionOrder;
use Lalalili\SubscriptionCore\Support\OrderNumberGenerator;

/**
 * @extends Factory<SubscriptionOrder>
 */
class SubscriptionOrderFactory extends Factory
{
    protected $model = SubscriptionOrder::class;

    public function definition(): array
    {
        $subscription = Subscription::factory()->create();

        return [
            'number'                                                => app(OrderNumberGenerator::class)->generate(),
            config('subscription.owner.foreign_key', 'merchant_id') => $subscription->getAttribute(config('subscription.owner.foreign_key', 'merchant_id')),
            'merchant_subscription_id'                              => $subscription->id,
            'subscription_plan_id'                                  => $subscription->subscription_plan_id,
            'billing_cycle'                                         => $subscription->billing_cycle,
            'amount'                                                => $subscription->price,
            'payment_status'                                        => PaymentStatus::PENDING,
            'payment_status_message'                                => null,
            'payment_time'                                          => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_status'         => PaymentStatus::COMPLETE,
            'payment_status_message' => '訂單成立已付款',
            'payment_time'           => now(),
        ]);
    }
}
