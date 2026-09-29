<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateAdminUserCommand extends Command
{
    protected $signature = 'pek:create-admin {email=admin@pek.com} {password?}';

    protected $description = 'Créer ou réinitialiser le mot de passe d\'un utilisateur administrateur Filament';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password') ?: $this->secret('Entrez le mot de passe administrateur :') ?: 'Admin@2026!';

        // Assurer que le rôle super_admin existe
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::firstOrNew(['email' => $email]);
        $user->first_name = $user->first_name ?: 'Admin';
        $user->last_name = $user->last_name ?: 'PEK';
        $user->password = Hash::make($password);
        $user->role = 'admin';
        $user->email_verified_at = now();
        $user->save();

        if (! $user->hasRole('super_admin')) {
            $user->assignRole($role);
        }

        $this->info('✅ Administrateur configuré avec succès !');
        $this->line("Email    : <comment>{$user->email}</comment>");
        $this->line("Password : <comment>{$password}</comment>");
        $this->line('Role     : <comment>admin / super_admin</comment>');

        return self::SUCCESS;
    }
}
