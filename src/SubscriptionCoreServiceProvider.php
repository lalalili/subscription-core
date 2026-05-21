<?php

namespace Lalalili\SubscriptionCore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lalalili\SubscriptionCore\Console\Commands\ExpireSubscriptionsCommand;
use Lalalili\SubscriptionCore\Models\Subscription;
use Lalalili\SubscriptionCore\Models\SubscriptionPlan;
use Lalalili\SubscriptionCore\Policies\SubscriptionPlanPolicy;
use Lalalili\SubscriptionCore\Policies\SubscriptionPolicy;
use Lalalili\SubscriptionCore\Support\SubscriptionFeatureChecker;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SubscriptionCoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('subscription-core')
            ->hasConfigFile('subscription')
            ->hasMigrations([
                '2026_02_14_011813_create_subscription_plans_table',
                '2026_02_14_011815_create_merchant_subscriptions_table',
                '2026_02_14_011826_create_subscription_orders_table',
                '2026_05_08_111746_add_internal_fields_to_merchant_subscriptions_table',
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(SubscriptionFeatureChecker::class);
    }

    public function packageBooted(): void
    {
        Gate::policy(SubscriptionPlan::class, SubscriptionPlanPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);

        Gate::define('subscription.feature', function (mixed $user, ?Model $owner, string $featureKey): bool {
            return app(SubscriptionFeatureChecker::class)->hasFeature($owner, $featureKey);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireSubscriptionsCommand::class,
            ]);
        }
    }
}
