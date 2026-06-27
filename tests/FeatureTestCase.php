<?php

namespace Lalalili\SubscriptionCore\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Lalalili\SubscriptionCore\SubscriptionCoreServiceProvider;
use Lalalili\SubscriptionCore\Tests\Fixtures\SubscriptionOwner;
use Orchestra\Testbench\TestCase as Orchestra;

class FeatureTestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SubscriptionCoreServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('subscription.owner.model', SubscriptionOwner::class);
        $app['config']->set('subscription.owner.foreign_key', 'merchant_id');
        $app['config']->set('subscription.owner.table', 'subscription_owners');
        $app['config']->set('subscription.owner.status_column', null);
        $app['config']->set('subscription.owner.synced_limits', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('subscription_owners', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
