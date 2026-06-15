# Subscription Core

Reusable subscription domain package for Laravel applications.

`lalalili/subscription-core` owns subscription plans, merchant subscriptions, subscription orders, feature checks, and subscription expiration.

## Features

- Subscription plan, subscription, and subscription order models.
- Monthly, yearly, and internal billing cycle support.
- Pending, active, cancelled, and expired subscription lifecycle.
- Owner feature checks through `SubscriptionFeatureChecker`.
- `subscription.feature` gate.
- Owner limit synchronization for host models.
- `subscription:expire` console command.

## Installation

```bash
composer require lalalili/subscription-core
php artisan vendor:publish --tag=subscription-core-config
php artisan vendor:publish --tag=subscription-core-migrations
php artisan migrate
```

For GitHub installs before a Packagist release:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/lalalili/subscription-core.git"}
    ]
}
```

## Configuration

`config/subscription.php` controls:

- model classes
- table names
- owner model, foreign key, table, and display column
- owner status synchronization
- legacy feature-to-column mapping
- unlimited internal feature keys

The default owner is `App\Models\Merchant` through `merchant_id`.

## Usage

Subscribe an owner and create a payable order:

```php
use Lalalili\SubscriptionCore\Enums\BillingCycle;
use Lalalili\SubscriptionCore\Services\SubscriptionService;

$order = app(SubscriptionService::class)->subscribe(
    owner: $merchant,
    plan: $plan,
    cycle: BillingCycle::Monthly,
);
```

Activate after payment:

```php
app(SubscriptionService::class)->activateSubscription($order->number, now());
```

Grant an internal subscription:

```php
app(SubscriptionService::class)->grantInternalSubscription($merchant, $plan);
```

Check a feature:

```php
Gate::allows('subscription.feature', [$merchant, 'recommendation.products']);
```

Expire subscriptions:

```bash
php artisan subscription:expire
```

## Boundaries

- This package owns subscription domain logic, not admin UI.
- Filament resources live in `lalalili/subscription-filament`.
- Host applications own payment integration, owner model implementation, and plan seeding.

## Tests

From the package directory:

```bash
./vendor/bin/pest
```
