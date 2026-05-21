<?php

namespace Lalalili\SubscriptionCore\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasLabel, HasColor
{
    case Pending = 'pending';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending   => '待付款',
            self::Active    => '生效中',
            self::PastDue   => '逾期未付',
            self::Cancelled => '已取消',
            self::Expired   => '已到期',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending   => 'gray',
            self::Active    => 'success',
            self::PastDue   => 'warning',
            self::Cancelled => 'danger',
            self::Expired   => 'gray',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
