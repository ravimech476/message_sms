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
        Schema::create('server_settings', function (Blueprint $table) {
            $table->id();
            $table->string('server_type')->unique()->comment('old_server or new_server');
            $table->string('host')->nullable();
            $table->integer('port')->default(22);
            $table->string('username')->nullable();
            $table->text('password')->nullable()->comment('Encrypted password');
            $table->string('campaign_file_path')->nullable()->comment('Path to campaign files directory');
            $table->string('connection_type')->default('sftp')->comment('sftp, ftp, or local');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable()->comment('success or failed');
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_settings');
    }
};
