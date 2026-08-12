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
        // Add new permission columns for customer settings sub-sections
        Schema::table('admin_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_permissions', 'can_manage_general_customer_settings')) {
                $table->boolean('can_manage_general_customer_settings')->default(false)->after('can_manage_customer_settings');
            }
            if (!Schema::hasColumn('admin_permissions', 'can_manage_global_maintenance')) {
                $table->boolean('can_manage_global_maintenance')->default(false)->after('can_manage_general_customer_settings');
            }
            if (!Schema::hasColumn('admin_permissions', 'can_manage_customer_maintenance')) {
                $table->boolean('can_manage_customer_maintenance')->default(false)->after('can_manage_global_maintenance');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('admin_permissions', 'can_manage_general_customer_settings')) {
                $table->dropColumn('can_manage_general_customer_settings');
            }
            if (Schema::hasColumn('admin_permissions', 'can_manage_global_maintenance')) {
                $table->dropColumn('can_manage_global_maintenance');
            }
            if (Schema::hasColumn('admin_permissions', 'can_manage_customer_maintenance')) {
                $table->dropColumn('can_manage_customer_maintenance');
            }
        });
    }
};
