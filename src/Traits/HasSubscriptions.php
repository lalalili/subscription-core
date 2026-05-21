<?php

namespace Lalalili\SubscriptionCore\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Lalalili\SubscriptionCore\Enums\SubscriptionStatus;
use Lalalili\SubscriptionCore\Models\Subscription;
use Lalalili\SubscriptionCore\Models\SubscriptionOrder;

trait HasSubscriptions
{
    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        $subscriptionModel = $this->subscriptionModelClass('subscription.models.subscription', Subscription::class);

        /** @var HasMany<Subscription, $this> $relation */
        $relation = $this->hasMany($subscriptionModel, config('subscription.owner.foreign_key', 'merchant_id'));

        return $relation;
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        $subscriptionModel = $this->subscriptionModelClass('subscription.models.subscription', Subscription::class);

        /** @var HasOne<Subscription, $this> $relation */
        $relation = $this->hasOne($subscriptionModel, config('subscription.owner.foreign_key', 'merchant_id'))
            ->where('status', SubscriptionStatus::Active)
            ->where(fn (Builder $query): Builder => $query
                ->where('is_internal', true)
                ->orWhere('expires_at', '>', now()))
            ->latestOfMany();

        return $relation;
    }

    /**
     * @return HasMany<SubscriptionOrder, $this>
     */
    public function subscriptionOrders(): HasMany
    {
        $orderModel = $this->subscriptionModelClass('subscription.models.order', SubscriptionOrder::class);

        /** @var HasMany<SubscriptionOrder, $this> $relation */
        $relation = $this->hasMany($orderModel, config('subscription.owner.foreign_key', 'merchant_id'));

        return $relation;
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $default
     * @return class-string<TModel>
     */
    protected function subscriptionModelClass(string $key, string $default): string
    {
        $model = config($key);

        return is_string($model) && is_subclass_of($model, $default)
            ? $model
            : $default;
    }
}
