<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'type_piece')) {
                $table->string('type_piece')->nullable()->after('employer');
            }
            if (! Schema::hasColumn('users', 'num_piece')) {
                $table->string('num_piece')->nullable()->after('type_piece');
            }
            if (! Schema::hasColumn('users', 'doc_piece_identite')) {
                $table->text('doc_piece_identite')->nullable()->after('num_piece');
            }
            if (! Schema::hasColumn('users', 'doc_piece_verso')) {
                $table->text('doc_piece_verso')->nullable()->after('doc_piece_identite');
            }
            if (! Schema::hasColumn('users', 'last_onboarding_reminder_at')) {
                $table->timestamp('last_onboarding_reminder_at')->nullable()->after('doc_piece_verso');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'type_piece',
                'num_piece',
                'doc_piece_identite',
                'doc_piece_verso',
                'last_onboarding_reminder_at',
            ]);
        });
    }
};
