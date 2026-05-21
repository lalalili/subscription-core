<?php

namespace Lalalili\SubscriptionCore\Support;

use Illuminate\Support\Str;
use Lalalili\SubscriptionCore\Models\SubscriptionOrder;

class OrderNumberGenerator
{
    public function generate(): string
    {
        /** @var class-string<SubscriptionOrder> $orderModel */
        $orderModel = config('subscription.models.order', SubscriptionOrder::class);

        do {
            $number = strtoupper(Str::random(10));
        } while ($orderModel::query()->where('number', $number)->exists());

        return $number;
    }
}
