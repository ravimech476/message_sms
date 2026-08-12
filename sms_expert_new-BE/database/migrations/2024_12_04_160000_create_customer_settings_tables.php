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
        // Add new permission column to admin_permissions table if it doesn't exist
        if (Schema::hasTable('admin_permissions') && !Schema::hasColumn('admin_permissions', 'can_manage_customer_settings')) {
            Schema::table('admin_permissions', function (Blueprint $table) {
                if (Schema::hasColumn('admin_permissions', 'can_view_process_monitor')) {
                    $table->boolean('can_manage_customer_settings')->default(false)->after('can_view_process_monitor');
                } else {
                    $table->boolean('can_manage_customer_settings')->default(false);
                }
            });
        }

        // Create customer_settings table if it doesn't exist
        if (!Schema::hasTable('customer_settings')) {
            Schema::create('customer_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key', 100)->unique();
                $table->text('setting_value')->nullable();
                $table->string('setting_type', 50)->default('string'); // string, integer, boolean, json
                $table->string('description', 255)->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });

            // Insert default settings only when creating the table
            \DB::table('customer_settings')->insert([
                [
                    'setting_key' => 'default_price_margin_percentage',
                    'setting_value' => '10',
                    'setting_type' => 'decimal',
                    'description' => 'Default price margin percentage for new customers',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'global_maintenance_mode',
                    'setting_value' => '0',
                    'setting_type' => 'boolean',
                    'description' => 'Enable maintenance mode for all customers',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'maintenance_message',
                    'setting_value' => 'The site is currently under maintenance. Please try again later.',
                    'setting_type' => 'string',
                    'description' => 'Message to display during maintenance',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // Create customer_maintenance table for tracking maintenance mode per customer
        if (!Schema::hasTable('customer_maintenance')) {
            Schema::create('customer_maintenance', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable(); // null means all customers
                $table->string('user_bigid', 100)->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->string('maintenance_message', 500)->nullable();
                $table->timestamp('start_time')->nullable();
                $table->timestamp('end_time')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('user_bigid');
                $table->index('is_enabled');
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

        Schema::dropIfExists('customer_maintenance');
        Schema::dropIfExists('customer_settings');
    }
};
