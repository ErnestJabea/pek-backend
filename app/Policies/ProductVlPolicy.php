<?php

namespace App\Policies;

use App\Models\User;

class ProductVlPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('view_any_product_vl');
    }

    public function view(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('view_product_vl');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('create_product_vl');
    }

    public function update(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('update_product_vl');
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('delete_product_vl');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('delete_any_product_vl');
    }

    public function forceDelete(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('force_delete_product_vl');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('force_delete_any_product_vl');
    }

    public function restore(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('restore_product_vl');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('restore_any_product_vl');
    }

    public function replicate(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('replicate_product_vl');
    }

    public function reorder(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('reorder_product_vl');
    }
}
