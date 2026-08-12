<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration fixes notification_recipients where web_delivered was incorrectly set to true
     * for notifications that should only go to mobile (delivery_method = 'mobile' or 'email')
     */
    public function up(): void
    {
        // Fix notification_recipients where the notification's delivery_method is 'mobile' only
        // These should have web_delivered = false
        DB::statement("
            UPDATE notification_recipients nr
            INNER JOIN admin_notifications an ON nr.notification_id = an.id
            SET nr.web_delivered = 0
            WHERE an.delivery_method = 'mobile'
        ");

        // Fix notification_recipients where the notification's delivery_method is 'email' only
        // These should also have web_delivered = false
        DB::statement("
            UPDATE notification_recipients nr
            INNER JOIN admin_notifications an ON nr.notification_id = an.id
            SET nr.web_delivered = 0
            WHERE an.delivery_method = 'email'
        ");

        // Log the fix
        \Log::info('Fixed web_delivered flag for mobile-only and email-only notifications');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably reverse this migration
    }
};
