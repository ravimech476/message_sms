<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smsg_log', function (Blueprint $table) {
            if (!Schema::hasColumn('smsg_log', 'sms_type')) {
                $table->enum('sms_type', ['sms', 'whatsapp'])
                      ->default('sms')
                      ->after('text')
                      ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('smsg_log', function (Blueprint $table) {
            if (Schema::hasColumn('smsg_log', 'sms_type')) {
                $table->dropColumn('sms_type');
            }
        });
    }
};
