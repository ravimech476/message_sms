<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create {username} {password} {--name=} {--bigid=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user for the SMS Expert system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $username = $this->argument('username');
        $password = $this->argument('password');
        $name = $this->option('name') ?: 'Admin User';
        $bigid = $this->option('bigid') ?: strtoupper('ADM' . Str::random(6));

        // Check if username already exists
        if (User::where('uname', $username)->exists()) {
            $this->error("User with username '{$username}' already exists!");
            return Command::FAILURE;
        }

        try {
            // Create the admin user
            $adminUser = User::create([
                'uname' => $username,
                'pword' => $password,
                'login_type' => 'admin',
                'bit_disabled' => 0,
                'contactname' => $name,
                'bigid' => $bigid,
                'ip_address_restriction' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create user options record
            DB::table('useroption')->insert([
                'userref' => $adminUser->bigid,
                'profileupdate_lockout' => '0',
                'clientcommfail' => 'n',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $this->info('✅ Admin user created successfully!');
            $this->line('');
            $this->line("👤 Username: {$username}");
            $this->line("🔑 Password: {$password}");
            $this->line("📛 Name: {$name}");
            $this->line("🆔 BigID: {$bigid}");
            $this->line('');
            $this->line("🌐 Admin Login URL: http://localhost:8000/admin");
            $this->line('');
            $this->warn('⚠️  Please store these credentials securely!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to create admin user: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
