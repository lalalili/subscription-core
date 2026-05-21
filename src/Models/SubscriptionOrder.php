<?php

namespace Lalalili\SubscriptionCore\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lalalili\SubscriptionCore\Database\Factories\SubscriptionOrderFactory;
use Lalalili\SubscriptionCore\Enums\BillingCycle;
use Lalalili\SubscriptionCore\Enums\PaymentStatus;

class SubscriptionOrder extends Model
{
    /** @use HasFactory<SubscriptionOrderFactory> */
    use HasFactory;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('subscription.tables.orders', 'subscription_orders');
    }

    public function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'payment_status' => PaymentStatus::class,
            'payment_time' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function owner(): BelongsTo
    {
        $ownerModel = $this->ownerModelClass();

        /** @var BelongsTo<Model, $this> $relation */
        $relation = $this->belongsTo($ownerModel, config('subscription.owner.foreign_key', 'merchant_id'));

        return $relation;
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        $subscriptionModel = $this->subscriptionModelClass('subscription.models.subscription', Subscription::class);

        /** @var BelongsTo<Subscription, $this> $relation */
        $relation = $this->belongsTo($subscriptionModel, 'merchant_subscription_id');

        return $relation;
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        $planModel = $this->subscriptionModelClass('subscription.models.plan', SubscriptionPlan::class);

        /** @var BelongsTo<SubscriptionPlan, $this> $relation */
        $relation = $this->belongsTo($planModel, 'subscription_plan_id');

        return $relation;
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function paymentLogs(): HasMany
    {
        $paymentLogModel = config('subscription.payment_logs.model', 'App\\Models\\PaymentLog');
        if (! is_string($paymentLogModel) || ! is_subclass_of($paymentLogModel, Model::class)) {
            $paymentLogModel = Model::class;
        }

        /** @var HasMany<Model, $this> $relation */
        $relation = $this->hasMany($paymentLogModel, 'order_number', 'number');

        return $relation;
    }

    protected static function newFactory(): SubscriptionOrderFactory
    {
        return SubscriptionOrderFactory::new();
    }

    /**
     * @return class-string<Model>
     */
    protected function ownerModelClass(): string
    {
        $ownerModel = config('subscription.owner.model');

        return is_string($ownerModel) && is_subclass_of($ownerModel, Model::class)
            ? $ownerModel
            : Model::class;
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
