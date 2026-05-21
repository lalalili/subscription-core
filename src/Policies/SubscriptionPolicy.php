<?php

namespace Lalalili\SubscriptionCore\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Lalalili\SubscriptionCore\Models\Subscription;

class SubscriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MerchantSubscription') || $authUser->can('ViewAny:Subscription');
    }

    public function view(AuthUser $authUser, Subscription $subscription): bool
    {
        return $authUser->can('View:MerchantSubscription') || $authUser->can('View:Subscription');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MerchantSubscription') || $authUser->can('Create:Subscription');
    }

    public function update(AuthUser $authUser, Subscription $subscription): bool
    {
        return $authUser->can('Update:MerchantSubscription') || $authUser->can('Update:Subscription');
    }

    public function delete(AuthUser $authUser, Subscription $subscription): bool
    {
        return $authUser->can('Delete:MerchantSubscription') || $authUser->can('Delete:Subscription');
    }
}
