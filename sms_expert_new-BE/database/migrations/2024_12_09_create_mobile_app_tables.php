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
        // Create contacts table if not exists
        if (!Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->id();
                $table->string('user_bigid', 50)->index();
                $table->string('name', 255);
                $table->string('phone', 20)->index();
                $table->string('email', 255)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('group_id')->nullable()->index();
                $table->timestamps();
                
                // Unique constraint
                $table->unique(['user_bigid', 'phone']);
            });
        }

        // Create contact groups table if not exists
        if (!Schema::hasTable('contact_groups')) {
            Schema::create('contact_groups', function (Blueprint $table) {
                $table->id();
                $table->string('user_bigid', 50)->index();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->timestamps();
                
                // Unique constraint
                $table->unique(['user_bigid', 'name']);
            });
        }

        // Create user FCM tokens table for push notifications
        // Note: No foreign key constraint due to users table structure
        if (!Schema::hasTable('user_fcm_tokens')) {
            Schema::create('user_fcm_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('user_bigid', 50)->nullable()->index(); // Also store bigid for reference
                $table->string('device_id', 255)->default('default');
                $table->string('fcm_token', 500);
                $table->string('device_name', 255)->nullable();
                $table->string('device_type', 50)->nullable(); // ios, android
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                
                // Unique constraint
                $table->unique(['user_id', 'device_id']);
            });
        }

        // Add mobile app related columns to users_session_log if not exists
        if (Schema::hasTable('users_session_log')) {
            if (!Schema::hasColumn('users_session_log', 'device_name')) {
                Schema::table('users_session_log', function (Blueprint $table) {
                    $table->string('device_name', 255)->nullable()->after('ip_address');
                });
            }
            
            if (!Schema::hasColumn('users_session_log', 'device_id')) {
                Schema::table('users_session_log', function (Blueprint $table) {
                    $table->string('device_id', 255)->nullable()->after('device_name');
                });
            }
            
            if (!Schema::hasColumn('users_session_log', 'logout_date')) {
                Schema::table('users_session_log', function (Blueprint $table) {
                    $table->string('logout_date', 14)->nullable()->after('login_date');
                });
            }
        }

        // Add source column to smsg_log if not exists
        // if (Schema::hasTable('smsg_log')) {
        //     if (!Schema::hasColumn('smsg_log', 'source')) {
        //         Schema::table('smsg_log', function (Blueprint $table) {
        //             $table->string('source', 50)->nullable()->default('web')->after('ip_address');
        //         });
        //     }
        // }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_fcm_tokens');
        Schema::dropIfExists('contact_groups');
        Schema::dropIfExists('contacts');
        
        if (Schema::hasTable('users_session_log')) {
            if (Schema::hasColumn('users_session_log', 'device_name')) {
                Schema::table('users_session_log', function (Blueprint $table) {
                    $table->dropColumn('device_name');
                });
            }
            if (Schema::hasColumn('users_session_log', 'device_id')) {
                Schema::table('users_session_log', function (Blueprint $table) {
                    $table->dropColumn('device_id');
                });
            }
            if (Schema::hasColumn('users_session_log', 'logout_date')) {
                Schema::table('users_session_log', function (Blueprint $table) {
                    $table->dropColumn('logout_date');
                });
            }
        }
        
        if (Schema::hasTable('smsg_log') && Schema::hasColumn('smsg_log', 'source')) {
            Schema::table('smsg_log', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
