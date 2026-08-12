<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


class AddSmppClusterSupport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add host column to smpp_logs if not exists
        if (Schema::hasTable('smpp_logs') && !Schema::hasColumn('smpp_logs', 'host')) {
            Schema::table('smpp_logs', function (Blueprint $table) {
                $table->string('host', 100)->nullable()->after('direction')->index();
            });
        }

        // Create smpp_host_statistics table for tracking host performance
        if (!Schema::hasTable('smpp_host_statistics')) {
            Schema::create('smpp_host_statistics', function (Blueprint $table) {
                $table->id();
                $table->string('host', 100)->unique();
                $table->integer('messages_sent')->default(0);
                $table->integer('messages_failed')->default(0);
                $table->float('response_time_avg')->default(0);
                $table->float('response_time_min')->nullable();
                $table->float('response_time_max')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('failed_attempts')->default(0);
                $table->timestamp('last_used')->nullable();
                $table->timestamp('last_failed')->nullable();
                $table->text('last_error')->nullable();
                $table->text('hourly_stats')->nullable(); // Store hourly statistics as JSON string (text for MySQL < 5.7 compatibility)
                $table->timestamps();
                
                $table->index('is_active');
                $table->index(['host', 'is_active']);
            });
        }

        // Create smpp_cluster_config table
        if (!Schema::hasTable('smpp_cluster_config')) {
            Schema::create('smpp_cluster_config', function (Blueprint $table) {
                $table->id();
                $table->string('key', 50)->unique();
                $table->text('value');
                $table->text('description')->nullable();
                $table->timestamps();
            });

            // Insert default configuration
            DB::table('smpp_cluster_config')->insert([
                [
                    'key' => 'load_balancing_mode',
                    'value' => 'round-robin',
                    'description' => 'Load balancing strategy: round-robin, random, least-used, failover',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'key' => 'max_connection_attempts',
                    'value' => '3',
                    'description' => 'Maximum connection attempts per host',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'key' => 'host_cooldown_minutes',
                    'value' => '5',
                    'description' => 'Minutes to wait before retrying failed host',
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'key' => 'health_check_interval',
                    'value' => '60',
                    'description' => 'Seconds between health checks',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
        }

        // Add cluster tracking to sms_queue
        if (Schema::hasTable('sms_queue') && !Schema::hasColumn('sms_queue', 'sent_via_host')) {
            Schema::table('sms_queue', function (Blueprint $table) {
                $table->string('sent_via_host', 100)->nullable()->after('message_id');
                $table->float('response_time_ms')->nullable()->after('sent_via_host');
                
                $table->index('sent_via_host');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove added columns
        if (Schema::hasTable('smpp_logs')) {
            Schema::table('smpp_logs', function (Blueprint $table) {
                $table->dropColumn('host');
            });
        }

        if (Schema::hasTable('sms_queue')) {
            Schema::table('sms_queue', function (Blueprint $table) {
                $table->dropColumn(['sent_via_host', 'response_time_ms']);
            });
        }

        // Drop new tables
        Schema::dropIfExists('smpp_host_statistics');
        Schema::dropIfExists('smpp_cluster_config');
    }
}
