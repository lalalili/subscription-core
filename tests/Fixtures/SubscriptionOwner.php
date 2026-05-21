<?php

namespace Lalalili\SubscriptionCore\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lalalili\SubscriptionCore\Traits\HasSubscriptions;

class SubscriptionOwner extends Model
{
    use HasSubscriptions;
}
