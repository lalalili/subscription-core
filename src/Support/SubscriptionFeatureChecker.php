<?php

namespace Lalalili\SubscriptionCore\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Lalalili\SubscriptionCore\Models\Subscription;
use Lalalili\SubscriptionCore\Models\SubscriptionPlan;

class SubscriptionFeatureChecker
{
    public function activeSubscriptionFor(?Model $owner): ?Subscription
    {
        if (! $owner) {
            return null;
        }

        if (method_exists($owner, 'activeSubscription')) {
            $subscription = $owner->activeSubscription()->with('plan')->first();

            return $subscription instanceof Subscription ? $subscription : null;
        }

        /** @var class-string<Subscription> $subscriptionModel */
        $subscriptionModel = config('subscription.models.subscription', Subscription::class);

        $subscription = $subscriptionModel::query()
            ->with('plan')
            ->where(config('subscription.owner.foreign_key', 'merchant_id'), $owner->getKey())
            ->where('status', \Lalalili\SubscriptionCore\Enums\SubscriptionStatus::Active)
            ->where(fn (Builder $query): Builder => $query
                ->where('is_internal', true)
                ->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();

        return $subscription instanceof Subscription ? $subscription : null;
    }

    public function hasFeature(?Model $owner, string $key): bool
    {
        $plan = $this->activeSubscriptionFor($owner)?->plan;

        return $plan instanceof SubscriptionPlan && $plan->hasFeature($key);
    }

    public function limit(?Model $owner, string $key, ?int $default = null): ?int
    {
        $plan = $this->activeSubscriptionFor($owner)?->plan;

        if (! $plan instanceof SubscriptionPlan) {
            return $default;
        }

        return $plan->limit($key, $default);
    }

    public function hasUnlimitedLimit(?Model $owner, string $key): bool
    {
        $subscription = $this->activeSubscriptionFor($owner);

        if (! $subscription instanceof Subscription) {
            return false;
        }

        $plan = $subscription->plan;
        $unlimitedFeatureKey = 'unlimited.'.$key;

        if ($plan instanceof SubscriptionPlan && $plan->hasFeature($unlimitedFeatureKey)) {
            return true;
        }

        return (bool) $subscription->is_internal
            && in_array($key, config('subscription.internal.unlimited_limits', []), true);
    }
}
