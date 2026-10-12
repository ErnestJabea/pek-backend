<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mfa_type')) {
                $table->string('mfa_type')->default('none')->after('password');
            }
            if (!Schema::hasColumn('users', 'mfa_secret')) {
                $table->text('mfa_secret')->nullable()->after('mfa_type');
            }
            if (!Schema::hasColumn('users', 'mfa_confirmed_at')) {
                $table->timestamp('mfa_confirmed_at')->nullable()->after('mfa_secret');
            }
            if (!Schema::hasColumn('users', 'mfa_prompt_dismissed')) {
                $table->boolean('mfa_prompt_dismissed')->default(false)->after('mfa_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_type', 'mfa_secret', 'mfa_confirmed_at', 'mfa_prompt_dismissed']);
        });
    }
};
