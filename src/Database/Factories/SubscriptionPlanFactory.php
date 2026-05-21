<?php

namespace Lalalili\SubscriptionCore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lalalili\SubscriptionCore\Models\SubscriptionPlan;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name'              => fake()->words(2, true),
            'slug'              => fake()->unique()->slug(),
            'description'       => fake()->sentence(),
            'monthly_price'     => fake()->numberBetween(1000, 50000),
            'yearly_price'      => fake()->numberBetween(10000, 500000),
            'product_limit'     => fake()->randomElement([5000, 20000, 100000]),
            'monthly_api_limit' => fake()->randomElement([100000, 500000, 2000000]),
            'features'          => null,
            'sort_order'        => 0,
            'is_active'         => true,
        ];
    }

    public function starter(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name'              => 'Starter',
            'slug'              => 'starter',
            'description'       => '適合小型團隊的基礎方案',
            'monthly_price'     => 2000,
            'yearly_price'      => 20000,
            'product_limit'     => 5000,
            'monthly_api_limit' => 100000,
            'features'          => [
                'limits' => [
                    'recommendation.products'          => 5000,
                    'recommendation.monthly_api_calls' => 100000,
                ],
            ],
            'sort_order' => 1,
        ]);
    }

    public function growth(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name'              => 'Growth',
            'slug'              => 'growth',
            'description'       => '適合成長中企業的進階方案',
            'monthly_price'     => 5000,
            'yearly_price'      => 50000,
            'product_limit'     => 20000,
            'monthly_api_limit' => 500000,
            'features'          => [
                'features' => [
                    'survey.advanced_fields' => true,
                ],
                'limits' => [
                    'recommendation.products'          => 20000,
                    'recommendation.monthly_api_calls' => 500000,
                ],
            ],
            'sort_order' => 2,
        ]);
    }

    public function scale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name'              => 'Scale',
            'slug'              => 'scale',
            'description'       => '適合大型企業的企業級方案',
            'monthly_price'     => 12000,
            'yearly_price'      => 120000,
            'product_limit'     => 100000,
            'monthly_api_limit' => 2000000,
            'features'          => [
                'features' => [
                    'survey.advanced_fields' => true,
                ],
                'limits' => [
                    'recommendation.products'          => 100000,
                    'recommendation.monthly_api_calls' => 2000000,
                ],
            ],
            'sort_order' => 3,
        ]);
    }
}
