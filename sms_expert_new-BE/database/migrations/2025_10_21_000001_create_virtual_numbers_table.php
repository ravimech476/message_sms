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
        Schema::create('virtual_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->unique(); // Phone number
            $table->string('country', 10)->nullable();
            $table->string('type', 50)->nullable(); // mobile-lvn, landline, etc
            $table->longText('features')->nullable(); // SMS, VOICE, MMS
            $table->text('mo_http_url')->nullable(); // Webhook URL
            $table->longText('messages_webhooks')->nullable(); // Inbound/status webhooks
            $table->longText('voice_webhooks')->nullable(); // Voice webhooks
            $table->longText('limitations')->nullable(); // Any limitations
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            $table->index('msisdn');
            $table->index('country');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_numbers');
    }
};
