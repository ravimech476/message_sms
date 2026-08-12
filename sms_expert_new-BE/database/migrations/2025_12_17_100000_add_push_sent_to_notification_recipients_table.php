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
        Schema::table('notification_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_recipients', 'push_sent')) {
                $table->boolean('push_sent')->default(false)->after('web_delivered');
            }
            if (!Schema::hasColumn('notification_recipients', 'push_sent_at')) {
                $table->timestamp('push_sent_at')->nullable()->after('email_sent_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('notification_recipients', 'push_sent')) {
                $table->dropColumn('push_sent');
            }
            if (Schema::hasColumn('notification_recipients', 'push_sent_at')) {
                $table->dropColumn('push_sent_at');
            }
        });
    }
};
