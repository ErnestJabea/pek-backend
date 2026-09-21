<?php

namespace App\Policies;

use App\Models\OnboardingSession;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OnboardingSessionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('view_any_onboarding_session');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OnboardingSession $onboardingSession): bool
    {
        return $user->hasRole('super_admin') || $user->can('view_onboarding_session');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('create_onboarding_session');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OnboardingSession $onboardingSession): bool
    {
        return $user->hasRole('super_admin') || $user->can('update_onboarding_session');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OnboardingSession $onboardingSession): bool
    {
        return $user->hasRole('super_admin') || $user->can('delete_onboarding_session');
    }
}
