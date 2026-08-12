<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCronLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cron_logs', function (Blueprint $table) {
            $table->id();
            $table->string('cron_name', 100)->index();
            $table->integer('processed_count')->default(0);
            $table->integer('updated_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamp('run_date');
            $table->text('error_message')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();
            
            // Add index for quick lookups
            $table->index(['cron_name', 'run_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cron_logs');
    }
}
