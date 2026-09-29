<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_departments', function (Blueprint $table) {
            $table->id(); $table->string('name', 120)->unique();
            $table->text('description')->nullable(); $table->json('permissions');
            $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('admin_department_id')->nullable()->constrained('admin_departments')->restrictOnDelete();
        });
        Schema::create('admin_access_events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('event'); $table->json('changes'); $table->timestamp('created_at');
        });
        $readPayments = ['access_admin_panel', 'view_any_subscription', 'view_subscription', 'update_subscription', 'view_payment_proof'];
        $defaults = [
            'Conformité / KYC' => array_merge($readPayments, ['view_any_onboarding_session', 'view_onboarding_session', 'update_onboarding_session', 'review_subscription_compliance']),
            'Comptabilité / Trésorerie' => array_merge($readPayments, ['review_payment_proof', 'confirm_bank_payment', 'view_any_bank_detail', 'view_bank_detail']),
            'Gestion des fonds' => ['access_admin_panel', 'view_any_product', 'view_product', 'create_product', 'update_product', 'view_any_product_vl', 'view_product_vl', 'create_product_vl', 'update_product_vl', 'view_any_subscription', 'view_subscription'],
            'Support client' => ['access_admin_panel', 'view_any_user', 'view_user', 'view_any_subscription', 'view_subscription'],
        ];
        foreach (['bank_detail', 'currency', 'product_vl'] as $resource) {
            foreach (['view_any', 'view', 'create', 'update', 'delete', 'delete_any', 'restore', 'restore_any', 'force_delete', 'force_delete_any', 'replicate', 'reorder'] as $action) {
                \Spatie\Permission\Models\Permission::findOrCreate($action.'_'.$resource, 'web');
            }
        }
        foreach ($defaults as $name => $permissions) {
            foreach ($permissions as $permission) \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
            DB::table('admin_departments')->insert(['name' => $name, 'description' => 'Base modifiable. Les rôles individuels restent nécessaires.',
                'permissions' => json_encode($permissions), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('admin_department_id'));
        Schema::dropIfExists('admin_access_events'); Schema::dropIfExists('admin_departments');
    }
};
