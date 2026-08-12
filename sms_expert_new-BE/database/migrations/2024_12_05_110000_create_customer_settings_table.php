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
        // Only create table if it doesn't exist
        if (!Schema::hasTable('customer_settings')) {
            Schema::create('customer_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key')->unique();
                $table->text('setting_value')->nullable();
                $table->string('setting_type')->default('string'); // string, integer, decimal, boolean, json
                $table->string('description')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                
                $table->index('setting_key');
            });
        }

        // Insert default values only if they don't exist
        $defaults = [
            [
                'setting_key' => 'default_price_margin_percentage',
                'setting_value' => '10',
                'setting_type' => 'decimal',
                'description' => 'Default price margin percentage for new customers',
            ],
            [
                'setting_key' => 'global_maintenance_mode',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Enable maintenance mode for all customers',
            ],
            [
                'setting_key' => 'maintenance_message',
                'setting_value' => 'The site is currently under maintenance. Please try again later.',
                'setting_type' => 'string',
                'description' => 'Message to display during maintenance',
            ],
        ];

        foreach ($defaults as $default) {
            $exists = DB::table('customer_settings')
                ->where('setting_key', $default['setting_key'])
                ->exists();
            
            if (!$exists) {
                DB::table('customer_settings')->insert(array_merge($default, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_settings');
    }
};
