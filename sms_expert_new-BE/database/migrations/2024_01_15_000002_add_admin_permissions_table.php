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
        // Add new columns to existing users table for admin users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                if (Schema::hasColumn('users', 'login_type')) {
                    $table->string('role', 50)->nullable()->after('login_type');
                } else {
                    $table->string('role', 50)->nullable();
                }
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 100)->nullable();
            }
            if (!Schema::hasColumn('users', 'created_by')) {
                $table->integer('created_by')->nullable();
            }
        });

        // Create admin_permissions table without foreign key (only if it doesn't exist)
        if (!Schema::hasTable('admin_permissions')) {
            Schema::create('admin_permissions', function (Blueprint $table) {
                $table->id();
                $table->integer('user_id')->unsigned();
                $table->index('user_id');
                
                // Menu permissions
                $table->boolean('can_view_dashboard')->default(true);
                $table->boolean('can_view_customers')->default(false);
                $table->boolean('can_view_customer_emails')->default(false);
                $table->boolean('can_view_virtual_numbers')->default(false);
                $table->boolean('can_view_reports')->default(false);
                $table->boolean('can_view_settings')->default(false);
                $table->boolean('can_manage_admin_users')->default(false);
                
                // Customer tab permissions
                $table->boolean('can_view_customer_profile')->default(false);
                $table->boolean('can_edit_customer_profile')->default(false);
                $table->boolean('can_view_customer_keywords')->default(false);
                $table->boolean('can_edit_customer_keywords')->default(false);
                $table->boolean('can_view_customer_virtual_numbers')->default(false);
                $table->boolean('can_edit_customer_virtual_numbers')->default(false);
                $table->boolean('can_view_customer_notes')->default(false);
                $table->boolean('can_edit_customer_notes')->default(false);
                $table->boolean('can_view_customer_wallet')->default(false);
                $table->boolean('can_edit_customer_wallet')->default(false);
                $table->boolean('can_view_customer_logs')->default(false);
                $table->boolean('can_view_customer_reports')->default(false);
                
                // Action permissions
                $table->boolean('can_create_customers')->default(false);
                $table->boolean('can_delete_customers')->default(false);
                $table->boolean('can_export_data')->default(false);
                
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_permissions');
        
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
            if (Schema::hasColumn('users', 'last_login_ip')) {
                $table->dropColumn('last_login_ip');
            }
            if (Schema::hasColumn('users', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
