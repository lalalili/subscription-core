<?php

namespace Lalalili\SubscriptionCore\Enums;

use Filament\Support\Contracts\HasLabel;

enum BillingCycle: string implements HasLabel
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Internal = 'internal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly  => '月繳',
            self::Yearly   => '年繳',
            self::Internal => '內部',
        };
    }
}
