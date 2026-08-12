<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserCostAndUpdateUsersTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_cost')) {
            Schema::create('user_cost', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bigid');
                $table->unsignedBigInteger('country_id');
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('modified_by')->nullable();

                // $table->foreign('bigid')->references('bigid')->on('users')->onDelete('cascade');
                // $table->foreign('country_id')->references('id')->on('country')->onDelete('cascade');

                $table->index('bigid');
                $table->index('country_id');
            });
        }

        // The legacy users table may not have Laravel's remember_token column.
        // The app's autologin token flow and admin seeding both rely on it, so add it if missing.
        if (!Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->rememberToken();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'common_sms_rate')) {
                if (Schema::hasColumn('users', 'remember_token')) {
                    $table->decimal('common_sms_rate', 8, 4)->nullable()->after('remember_token');
                } else {
                    $table->decimal('common_sms_rate', 8, 4)->nullable();
                }
            }

            if (!Schema::hasColumn('users', 'use_common_rate')) {
                $table->tinyInteger('use_common_rate')->default(0)->after('common_sms_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'common_sms_rate')) {
                $table->dropColumn('common_sms_rate');
            }

            if (Schema::hasColumn('users', 'use_common_rate')) {
                $table->dropColumn('use_common_rate');
            }
        });

        Schema::dropIfExists('user_cost');
    }
}
