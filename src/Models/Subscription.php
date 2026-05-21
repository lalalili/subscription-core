<?php

namespace Lalalili\SubscriptionCore\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Lalalili\SubscriptionCore\Database\Factories\SubscriptionFactory;
use Lalalili\SubscriptionCore\Enums\BillingCycle;
use Lalalili\SubscriptionCore\Enums\SubscriptionStatus;

/**
 * @property int|string $id
 * @property int|string|null $subscription_plan_id
 * @property BillingCycle|string $billing_cycle
 * @property int|float|string $price
 * @property SubscriptionStatus|string $status
 * @property bool $is_internal
 * @property Carbon|null $expires_at
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('subscription.tables.subscriptions', 'merchant_subscriptions');
    }

    public function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'status' => SubscriptionStatus::class,
            'is_internal' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return HasMany<SubscriptionOrder, $this>
     */
    public function orders(): HasMany
    {
        $orderModel = $this->subscriptionModelClass('subscription.models.order', SubscriptionOrder::class);

        /** @var HasMany<SubscriptionOrder, $this> $relation */
        $relation = $this->hasMany($orderModel, 'merchant_subscription_id');

        return $relation;
    }

    public function isActive(): bool
    {
        $status = $this->status instanceof SubscriptionStatus
            ? $this->status
            : SubscriptionStatus::tryFrom((string) $this->status);

        if ($status !== SubscriptionStatus::Active) {
            return false;
        }

        if ($this->is_internal) {
            return true;
        }

        if ($this->expires_at === null) {
            return false;
        }

        return Carbon::parse((string) $this->expires_at)->isFuture();
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
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
