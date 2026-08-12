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
        // Add can_manage_customer_settings column if it doesn't exist
        if (!Schema::hasColumn('admin_permissions', 'can_manage_customer_settings')) {
            Schema::table('admin_permissions', function (Blueprint $table) {
                $table->boolean('can_manage_customer_settings')->default(false)->after('can_view_process_monitor');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('admin_permissions', 'can_manage_customer_settings')) {
            Schema::table('admin_permissions', function (Blueprint $table) {
                $table->dropColumn('can_manage_customer_settings');
            });
        }
    }
};
