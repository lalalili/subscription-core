<?php

namespace Lalalili\SubscriptionCore\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: int implements HasLabel, HasColor
{
    case PENDING = 1;
    case COMPLETE = 2;
    case REFUND = 3;
    case CANCEL = 9;

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING  => '待付款',
            self::COMPLETE => '完成',
            self::REFUND   => '退款',
            self::CANCEL   => '取消',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING  => 'gray',
            self::COMPLETE => 'success',
            self::REFUND   => 'danger',
            self::CANCEL   => 'warning',
        };
    }
}
