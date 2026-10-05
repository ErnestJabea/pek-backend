<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $tables = ['onboarding_events', 'admin_departments', 'bank_details', 'currencies'];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $cols = DB::select("SHOW FULL COLUMNS FROM `{$table}` WHERE Field = 'id'");
            if (! empty($cols) && ! str_contains(strtolower($cols[0]->Extra ?? ''), 'auto_increment')) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT");
                $max = DB::table($table)->max('id') ?? 0;
                $next = $max + 1;
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");
            }
        }
    }

    public function down(): void
    {
        // No need to reverse auto_increment attribute
    }
};
