<?php

use Lalalili\SubscriptionCore\Models\Subscription;
use Lalalili\SubscriptionCore\Models\SubscriptionOrder;
use Lalalili\SubscriptionCore\Models\SubscriptionPlan;

return [
    'models' => [
        'plan' => SubscriptionPlan::class,
        'subscription' => Subscription::class,
        'order' => SubscriptionOrder::class,
    ],

    'tables' => [
        'plans' => 'subscription_plans',
        'subscriptions' => 'merchant_subscriptions',
        'orders' => 'subscription_orders',
    ],

    'owner' => [
        'model' => 'App\\Models\\Merchant',
        'foreign_key' => 'merchant_id',
        'table' => 'merchants',
        'display_column' => 'name',
        'status_column' => 'status',
        'pending_status' => 'pending',
        'active_status' => 'active',
        'synced_limits' => [
            'product_limit' => 'recommendation.products',
        ],
    ],

    'payment_logs' => [
        'model' => 'App\\Models\\PaymentLog',
    ],

    'features' => [
        'legacy_limits' => [
            'recommendation.products' => 'product_limit',
            'recommendation.monthly_api_calls' => 'monthly_api_limit',
        ],
    ],

    'internal' => [
        'unlimited_limits' => [
            'recommendation.monthly_api_calls',
        ],
    ],

    'recurring' => [
        // 連續扣款失敗達此次數即視為終止（對齊綠界定期定額連續失敗 6 次自動終止合約）。
        'failure_termination_threshold' => 6,
    ],
];
