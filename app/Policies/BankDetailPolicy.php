<?php
namespace App\Policies;
class BankDetailPolicy
{
    public function viewAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('view_any_bank_detail');
    }
    public function view(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('view_bank_detail');
    }
    public function create(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('create_bank_detail');
    }
    public function update(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('update_bank_detail');
    }
    public function delete(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('delete_bank_detail');
    }
    public function deleteAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('delete_any_bank_detail');
    }
    public function forceDelete(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('force_delete_bank_detail');
    }
    public function forceDeleteAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('force_delete_any_bank_detail');
    }
    public function restore(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('restore_bank_detail');
    }
    public function restoreAny(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('restore_any_bank_detail');
    }
    public function replicate(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('replicate_bank_detail');
    }
    public function reorder(\App\Models\User $user): bool
    {
        // Preserve historical behavior until the account is explicitly assigned.
        return ! $user->admin_department_id || $user->can('reorder_bank_detail');
    }
}
