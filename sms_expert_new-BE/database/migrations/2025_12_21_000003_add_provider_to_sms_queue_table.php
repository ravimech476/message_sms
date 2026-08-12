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
            if (!Schema::hasColumn('sms_queue', 'provider')) {
                $table->string('provider', 50)->nullable()->default('nexmo')->after('status')
                    ->comment('SMS provider: nexmo, sinch');
            }
        });

        // Also add provider column to smpp_message_mapping if it doesn't exist
        if (Schema::hasTable('smpp_message_mapping')) {
            Schema::table('smpp_message_mapping', function (Blueprint $table) {
                if (!Schema::hasColumn('smpp_message_mapping', 'provider')) {
                    $table->string('provider', 50)->nullable()->default('nexmo')->after('mobile_number')
                        ->comment('SMS provider: nexmo, sinch');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sms_queue') && Schema::hasColumn('sms_queue', 'provider')) {
            Schema::table('sms_queue', function (Blueprint $table) {
                $table->dropColumn('provider');
            });
        }

        if (Schema::hasTable('smpp_message_mapping') && Schema::hasColumn('smpp_message_mapping', 'provider')) {
            Schema::table('smpp_message_mapping', function (Blueprint $table) {
                $table->dropColumn('provider');
            });
        }
    }
};
