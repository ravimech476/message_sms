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
        if (!Schema::hasTable('sms_queue')) {
            return;
        }

        Schema::table('sms_queue', function (Blueprint $table) {
            if (!Schema::hasColumn('sms_queue', 'sent_via_host')) {
                if (Schema::hasColumn('sms_queue', 'response_data')) {
                    $table->string('sent_via_host', 100)->nullable()->after('response_data')->comment('SMPP host used for sending');
                } else {
                    $table->string('sent_via_host', 100)->nullable()->comment('SMPP host used for sending');
                }
            }
        });

        Schema::table('sms_queue', function (Blueprint $table) {
            if (!Schema::hasColumn('sms_queue', 'response_time_ms')) {
                if (Schema::hasColumn('sms_queue', 'sent_via_host')) {
                    $table->integer('response_time_ms')->nullable()->after('sent_via_host')->comment('Response time in milliseconds');
                } else {
                    $table->integer('response_time_ms')->nullable()->comment('Response time in milliseconds');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_queue', function (Blueprint $table) {
            if (Schema::hasColumn('sms_queue', 'sent_via_host')) {
                $table->dropColumn('sent_via_host');
            }
            
            if (Schema::hasColumn('sms_queue', 'response_time_ms')) {
                $table->dropColumn('response_time_ms');
            }
        });
    }
};
