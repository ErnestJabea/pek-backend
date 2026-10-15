<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'fomethecarine@gmail.com')
            ->update([
                'last_name' => 'FOMETHE KENFACK',
                'first_name' => 'Carine',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('email', 'fomethecarine@gmail.com')
            ->update([
                'last_name' => 'Kenfack',
                'first_name' => 'Carine',
            ]);
    }
};
