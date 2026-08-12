<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMPP Proxy Users Table - OLD SYSTEM Compatible
 *
 * Stores SMPP proxy user configurations.
 * Based on Craig Rutherford's SMPP front end authentication from smssend.inc
 *
 * Craig provides an SMPP front end and authenticates with his own credentials,
 * then passes smppusr param to represent the actual user within his system.
 * We then translate this into the itagg username.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('smpp_proxy_users', function (Blueprint $table) {
            $table->id();
            $table->string('proxy_username', 50)->index()->comment('Proxy user username (e.g., 6c3d9bb6)');
            $table->string('proxy_password', 50)->comment('Proxy user password');
            $table->string('proxy_name', 100)->nullable()->comment('Human readable name (e.g., Craig Rutherford)');
            $table->text('allowed_ips')->nullable()->comment('JSON array of allowed IP addresses');
            $table->string('notify_email', 255)->nullable()->comment('Email to notify on errors');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['proxy_username']);
        });

        // Add is_SMPP column to users table if not exists
        if (!Schema::hasColumn('users', 'is_SMPP')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_SMPP')->default(false)->after('is_active')
                    ->comment('User can be authenticated via SMPP proxy');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smpp_proxy_users');

        if (Schema::hasColumn('users', 'is_SMPP')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_SMPP');
            });
        }
    }
};
