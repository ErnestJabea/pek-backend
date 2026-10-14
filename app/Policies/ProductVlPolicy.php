<?php
namespace App\Policies;
class ProductVlPolicy
{
    public function viewAny(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('view_any_product_vl');
    }
    public function view(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('view_product_vl');
    }
    public function create(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('create_product_vl');
    }
    public function update(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('update_product_vl');
    }
    public function delete(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('delete_product_vl');
    }
    public function deleteAny(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('delete_any_product_vl');
    }
    public function forceDelete(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('force_delete_product_vl');
    }
    public function forceDeleteAny(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('force_delete_any_product_vl');
    }
    public function restore(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('restore_product_vl');
    }
    public function restoreAny(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('restore_any_product_vl');
    }
    public function replicate(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('replicate_product_vl');
    }
    public function reorder(\App\Models\User $user): bool
    {
        return $user->hasRole('super_admin') || $user->can('reorder_product_vl');
    }
}
