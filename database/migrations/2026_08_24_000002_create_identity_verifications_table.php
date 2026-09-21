<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('onboarding_session_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->default('idenfy');
            $table->uuid('client_reference')->unique();
            $table->string('provider_reference', 100)->nullable()->unique();
            $table->char('identity_data_hash', 64);
            $table->string('status', 30)->default('initiated');
            $table->boolean('is_final')->default(false);
            $table->boolean('document_validated')->nullable();
            $table->boolean('face_matched')->nullable();
            $table->boolean('reviewed_by_human')->default(false);
            $table->string('last_event_type', 80)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['onboarding_session_id', 'created_at']);
            $table->index(['status', 'is_final']);
        });

        Schema::create('identity_verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('identity_verification_id')->constrained()->cascadeOnDelete();
            $table->char('delivery_hash', 64)->unique();
            $table->string('event_type', 80)->nullable();
            $table->string('result_status', 30);
            $table->boolean('is_final')->default(false);
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['identity_verification_id', 'received_at'], 'identity_events_verification_received_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verification_events');
        Schema::dropIfExists('identity_verifications');
    }
};
