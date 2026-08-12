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
        // Add notification permission to admin_permissions table
        if (!Schema::hasColumn('admin_permissions', 'can_manage_notifications')) {
            Schema::table('admin_permissions', function (Blueprint $table) {
                $table->boolean('can_manage_notifications')->default(false)->after('can_manage_customer_maintenance');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('admin_permissions', 'can_manage_notifications')) {
            Schema::table('admin_permissions', function (Blueprint $table) {
                $table->dropColumn('can_manage_notifications');
            });
        }
    }
};
