<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users.migrated_at records the exact moment a customer flipped from the old
 * system to the new one. The dashboard's "still on old API" banner uses this
 * to ignore smsg_log rows sent BEFORE migration — without it, every freshly
 * migrated customer is incorrectly flagged because their pre-migration sends
 * are stamped migration_flag='old' (legitimately, since they really were on
 * the old API back then).
 *
 * Backfill: any customer already at migration_flag='new' gets migrated_at = now()
 * so historical old rows stop counting against them from this moment forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'migrated_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dateTime('migrated_at')->nullable()->after('migration_flag');
            });
        }

        DB::table('users')
            ->where('migration_flag', 'new')
            ->whereNull('migrated_at')
            ->update(['migrated_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'migrated_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('migrated_at');
            });
        }
    }
};
