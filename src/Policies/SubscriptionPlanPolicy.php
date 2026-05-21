<?php

namespace Lalalili\SubscriptionCore\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Lalalili\SubscriptionCore\Models\SubscriptionPlan;

class SubscriptionPlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SubscriptionPlan');
    }

    public function view(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('View:SubscriptionPlan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SubscriptionPlan');
    }

    public function update(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('Update:SubscriptionPlan');
    }

    public function delete(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('Delete:SubscriptionPlan');
    }

    public function restore(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('Restore:SubscriptionPlan');
    }

    public function forceDelete(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('ForceDelete:SubscriptionPlan');
    }
}
