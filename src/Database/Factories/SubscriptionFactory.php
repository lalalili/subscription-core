<?php

namespace Lalalili\SubscriptionCore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lalalili\SubscriptionCore\Enums\BillingCycle;
use Lalalili\SubscriptionCore\Enums\SubscriptionStatus;
use Lalalili\SubscriptionCore\Models\Subscription;
use Lalalili\SubscriptionCore\Models\SubscriptionPlan;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $billingCycle = fake()->randomElement(BillingCycle::cases());
        $plan = SubscriptionPlan::factory()->create();
        $price = $billingCycle === BillingCycle::Monthly ? $plan->monthly_price : $plan->yearly_price;
        $startsAt = now();
        $expiresAt = $billingCycle === BillingCycle::Monthly ? $startsAt->copy()->addMonth() : $startsAt->copy()->addYear();

        return [
            config('subscription.owner.foreign_key', 'merchant_id') => 1,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'price' => $price,
            'status' => SubscriptionStatus::Active,
            'is_internal' => false,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'cancelled_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Pending,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Active,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'billing_cycle' => BillingCycle::Internal,
            'price' => 0,
            'status' => SubscriptionStatus::Active,
            'is_internal' => true,
            'expires_at' => null,
        ]);
    }
}
