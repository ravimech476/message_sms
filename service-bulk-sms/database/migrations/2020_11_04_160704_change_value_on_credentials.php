<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeValueOnCredentials extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropColumn('value');
        });

        Schema::table('credentials', function (Blueprint $table) {
            $table->binary('value')->after('key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropColumn('value');
        });

        Schema::table('credentials', function (Blueprint $table) {
            $table->string('value');
        });
    }
}
