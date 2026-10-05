<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Met à jour les comptes tests / administrateurs pour s'assurer que leur champ role est bien 'admin'
        DB::table('users')
            ->where('email', 'contact@ejabbing.com')
            ->orWhere(function ($query) {
                $query->whereRaw("LOWER(TRIM(first_name)) = 'admin'")
                    ->whereRaw("LOWER(TRIM(last_name)) = 'admin'");
            })
            ->update([
                'role' => 'admin',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
