<?php

namespace App\Policies;

use App\Models\User;

class ProductVlPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('view_any_product_vl')
            || $user->can('view_any_product')
            || $user->can('view_product')
            || $user->can('update_product');
    }

    public function view(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('view_product_vl')
            || $user->can('view_product')
            || $user->can('update_product');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('create_product_vl')
            || $user->can('update_product')
            || $user->can('create_product');
    }

    public function update(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('update_product_vl')
            || $user->can('update_product');
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('delete_product_vl')
            || $user->can('delete_product');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('delete_any_product_vl')
            || $user->can('delete_any_product');
    }

    public function forceDelete(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('force_delete_product_vl')
            || $user->can('force_delete_product');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('force_delete_any_product_vl')
            || $user->can('force_delete_any_product');
    }

    public function restore(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('restore_product_vl')
            || $user->can('restore_product');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('restore_any_product_vl')
            || $user->can('restore_any_product');
    }

    public function replicate(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('replicate_product_vl')
            || $user->can('replicate_product');
    }

    public function reorder(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('reorder_product_vl')
            || $user->can('reorder_product');
    }
}
