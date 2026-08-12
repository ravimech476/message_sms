<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            // Add system status permission after can_export_data
            if (!Schema::hasColumn('admin_permissions', 'can_view_system_status')) {
                $table->boolean('can_view_system_status')->default(false)->after('can_export_data');
            }
        });
        
        // Set default to true for existing admin users (so current admins can see it)
        DB::table('admin_permissions')->update(['can_view_system_status' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('admin_permissions', 'can_view_system_status')) {
                $table->dropColumn('can_view_system_status');
            }
        });
    }
};
