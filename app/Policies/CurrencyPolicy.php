<?php
namespace App\Policies;
class CurrencyPolicy
{
    public function viewAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('view_any_currency');
    }
    public function view(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('view_currency');
    }
    public function create(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('create_currency');
    }
    public function update(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('update_currency');
    }
    public function delete(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('delete_currency');
    }
    public function deleteAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('delete_any_currency');
    }
    public function forceDelete(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('force_delete_currency');
    }
    public function forceDeleteAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('force_delete_any_currency');
    }
    public function restore(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('restore_currency');
    }
    public function restoreAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('restore_any_currency');
    }
    public function replicate(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('replicate_currency');
    }
    public function reorder(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('reorder_currency');
    }
}
