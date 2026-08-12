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
        Schema::table('smsg_log', function (Blueprint $table) {
            $table->enum('migration_flag', ['old', 'new'])
                  ->after('requested_routetag')
                  ->nullable(); // no default value
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smsg_log', function (Blueprint $table) {
            $table->dropColumn('migration_flag');
        });
    }
};
