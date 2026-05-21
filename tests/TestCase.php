<?php

namespace Lalalili\SubscriptionCore\Tests;

use Lalalili\SubscriptionCore\SubscriptionCoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SubscriptionCoreServiceProvider::class,
        ];
    }
}
