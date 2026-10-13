<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Services\ClientCategoryService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nature_client')->default('Personne Physique')->after('categorie_client');
        });

        // Initialiser la nature_client des utilisateurs existants selon leur categorie_client
        $users = DB::table('users')->select('id', 'categorie_client')->get();
        foreach ($users as $user) {
            $nature = ClientCategoryService::getTypeForCategory($user->categorie_client);
            DB::table('users')->where('id', $user->id)->update([
                'nature_client' => $nature,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nature_client');
        });
    }
};
