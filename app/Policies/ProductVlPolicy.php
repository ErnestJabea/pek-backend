<?php
namespace App\Policies;
class ProductVlPolicy
{
    public function viewAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('view_any_product_vl');
    }
    public function view(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('view_product_vl');
    }
    public function create(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('create_product_vl');
    }
    public function update(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('update_product_vl');
    }
    public function delete(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('delete_product_vl');
    }
    public function deleteAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('delete_any_product_vl');
    }
    public function forceDelete(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('force_delete_product_vl');
    }
    public function forceDeleteAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('force_delete_any_product_vl');
    }
    public function restore(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('restore_product_vl');
    }
    public function restoreAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('restore_any_product_vl');
    }
    public function replicate(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('replicate_product_vl');
    }
    public function reorder(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('reorder_product_vl');
    }
}
