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
        Schema::table('admin_permissions', function (Blueprint $table) {
            // Add can_manage_contracts permission
            $table->boolean('can_manage_contracts')->default(false)->after('can_manage_admin_users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            $table->dropColumn('can_manage_contracts');
        });
    }
};
