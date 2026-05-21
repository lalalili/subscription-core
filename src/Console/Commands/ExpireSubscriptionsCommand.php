<?php

namespace Lalalili\SubscriptionCore\Console\Commands;

use Illuminate\Console\Command;
use Lalalili\SubscriptionCore\Services\SubscriptionService;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire active subscriptions whose expiry date has passed.';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $count = $subscriptionService->expireSubscriptions();

        $this->info("Expired {$count} subscriptions.");

        return self::SUCCESS;
    }
}
