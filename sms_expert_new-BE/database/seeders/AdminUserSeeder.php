<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\AdminPermission;
use Illuminate\Support\Facades\DB;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Generate unique bigid in MD5 hash format
        do {
            $bigid = md5(uniqid(mt_rand(), true));
            $exists = DB::table('users')->where('bigid', $bigid)->exists();
        } while ($exists);

        // Create Super Admin
        $superAdmin = User::create([
            'bigid' => $bigid,
            'busname' => 'Super Admin',
            'contactname' => 'Super Admin',
            'contactemail' => 'admin@smsexpert.com',
            'uname' => 'superadmin',
            'pword' => md5('Admin@123'),
            'phone' => '',
            'login_type' => 'admin',
            'role' => 'super_admin',
            'bit_disabled' => 0,
        ]);

        // Create permissions with all access
        $permission = new AdminPermission(['user_id' => $superAdmin->id]);
        foreach (AdminPermission::getPermissionFields() as $field) {
            $permission->$field = true;
        }
        $permission->save();

        $this->command->info('Super Admin created successfully!');
        $this->command->info('Email: admin@smsexpert.com');
        $this->command->info('Username: superadmin');
        $this->command->info('Password: Admin@123');
        $this->command->info('BigID: ' . $bigid);
    }
}
