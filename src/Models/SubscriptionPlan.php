<?php

namespace Lalalili\SubscriptionCore\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lalalili\SubscriptionCore\Database\Factories\SubscriptionPlanFactory;

/**
 * @property int|string $id
 * @property int|float|string $monthly_price
 * @property int|float|string $yearly_price
 * @property array<string, mixed>|null $features
 */
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('subscription.tables.plans', 'subscription_plans');
    }

    public function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        $subscriptionModel = $this->subscriptionModelClass('subscription.models.subscription', Subscription::class);

        /** @var HasMany<Subscription, $this> $relation */
        $relation = $this->hasMany($subscriptionModel, 'subscription_plan_id');

        return $relation;
    }

    public function hasFeature(string $key): bool
    {
        $features = $this->features ?? [];
        $featureFlags = $features['features'] ?? [];
        $value = is_array($featureFlags) && array_key_exists($key, $featureFlags)
            ? $featureFlags[$key]
            : data_get($features, 'features.'.$key);

        if ($value === null) {
            $value = array_key_exists($key, $features) ? $features[$key] : data_get($features, $key);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function limit(string $key, ?int $default = null): ?int
    {
        $features = $this->features ?? [];
        $limits = $features['limits'] ?? [];
        $value = is_array($limits) && array_key_exists($key, $limits)
            ? $limits[$key]
            : data_get($features, 'limits.'.$key);

        if ($value === null) {
            $legacyColumn = config('subscription.features.legacy_limits.'.$key);
            $value = is_string($legacyColumn) ? $this->getAttribute($legacyColumn) : null;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    protected static function newFactory(): SubscriptionPlanFactory
    {
        return SubscriptionPlanFactory::new();
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
