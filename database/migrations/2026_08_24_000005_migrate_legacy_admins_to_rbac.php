<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'super_admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')
            ->where('role', 'admin')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($roleId) {
                DB::table('model_has_roles')->insertOrIgnore(
                    $users->map(fn ($user) => [
                        'role_id' => $roleId,
                        'model_type' => User::class,
                        'model_id' => $user->id,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        // Intentionally non-destructive: an RBAC assignment may have been granted independently.
    }
};
