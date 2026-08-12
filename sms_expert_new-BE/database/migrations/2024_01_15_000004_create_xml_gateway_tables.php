<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * XML Gateway Customer Tables - OLD SYSTEM Compatible
 *
 * Stores customer-specific features for XML to SMS Gateway.
 * Based on hardcoded conditions from OLD SYSTEM incoming_itagg_xml.php
 *
 * OLD SYSTEM Features migrated:
 * - arunestates: Default route 7002, skip confirmations
 * - mark: Default route 7002
 * - Hardys and Hansons emails: Skip confirmations
 * - Shortcode restrictions (82958, 82466)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Customer-specific XML Gateway features
        Schema::create('xml_gateway_customers', function (Blueprint $table) {
            $table->id();
            $table->string('user_bigid', 32)->nullable()->index()->comment('Customer bigid from users table');
            $table->string('username', 50)->nullable()->index()->comment('Username for matching');
            $table->string('default_route', 20)->nullable()->comment('Default route when not specified in XML');
            $table->boolean('skip_confirmation')->default(false)->comment('Skip all confirmation emails');
            $table->longText('skip_confirmation_emails')->nullable()->comment('JSON array of emails to skip confirmations');
            $table->text('notes')->nullable()->comment('Admin notes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_bigid', 'is_active']);
            $table->index(['username', 'is_active']);
        });

        // Shortcode/Sender ID restrictions
        Schema::create('xml_gateway_shortcode_restrictions', function (Blueprint $table) {
            $table->id();
            $table->string('shortcode', 20)->index()->comment('Shortcode or Sender ID');
            $table->string('allowed_bigid', 32)->comment('Only this bigid can use this shortcode');
            $table->string('customer_name', 100)->nullable()->comment('Human readable customer name');
            $table->text('notes')->nullable()->comment('Admin notes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shortcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xml_gateway_shortcode_restrictions');
        Schema::dropIfExists('xml_gateway_customers');
    }
};
