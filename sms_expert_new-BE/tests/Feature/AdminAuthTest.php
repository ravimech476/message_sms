<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_login_page_loads()
    {
        $response = $this->get('/admin');
        
        $response->assertStatus(200);
        $response->assertViewIs('admin.auth.login');
        $response->assertSee('Admin Access');
        $response->assertSee('SMS Expert');
    }

    /** @test */
    public function admin_can_login_with_valid_credentials()
    {
        // Create an admin user
        $admin = User::factory()->create([
            'uname' => 'admin',
            'pword' => 'password123',
            'login_type' => 'admin',
            'bit_disabled' => 0,
            'contactname' => 'Admin User',
            'bigid' => 'ADM001'
        ]);

        $response = $this->post('/admin/login', [
            'userName' => 'admin',
            'password' => 'password123'
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin);
    }

    /** @test */
    public function non_admin_user_cannot_login_to_admin()
    {
        // Create a regular customer user
        $customer = User::factory()->create([
            'uname' => 'customer',
            'pword' => 'password123',
            'login_type' => 'customer',
            'bit_disabled' => 0
        ]);

        $response = $this->post('/admin/login', [
            'userName' => 'customer',
            'password' => 'password123'
        ]);

        $response->assertRedirect('/admin');
        $response->assertSessionHasErrors(['userName']);
        $this->assertGuest();
    }

    /** @test */
    public function disabled_admin_cannot_login()
    {
        $admin = User::factory()->create([
            'uname' => 'admin',
            'pword' => 'password123',
            'login_type' => 'admin',
            'bit_disabled' => 1, // Disabled account
            'contactname' => 'Disabled Admin'
        ]);

        $response = $this->post('/admin/login', [
            'userName' => 'admin',
            'password' => 'password123'
        ]);

        $response->assertRedirect('/admin');
        $response->assertSessionHasErrors(['userName']);
        $this->assertGuest();
    }

    /** @test */
    public function admin_can_logout()
    {
        $admin = User::factory()->create([
            'uname' => 'admin',
            'pword' => 'password123',
            'login_type' => 'admin',
            'bit_disabled' => 0,
            'contactname' => 'Admin User',
            'bigid' => 'ADM001'
        ]);

        // Login first
        $this->actingAs($admin);
        Session::put('user_info', [
            'contactname' => $admin->contactname,
            'bigid' => $admin->bigid,
            'username' => $admin->uname,
            'login_type' => $admin->login_type,
        ]);

        // Test logout
        $response = $this->post('/admin/logout');
        
        $response->assertRedirect('/admin');
        $this->assertGuest();
        $this->assertEmpty(Session::get('user_info'));
    }

    /** @test */
    public function authenticated_admin_is_redirected_from_login_page()
    {
        $admin = User::factory()->create([
            'uname' => 'admin',
            'pword' => 'password123',
            'login_type' => 'admin',
            'contactname' => 'Admin User',
            'bigid' => 'ADM001'
        ]);

        $this->actingAs($admin);

        $response = $this->get('/admin');
        $response->assertRedirect('/admin/dashboard');
    }

    /** @test */
    public function invalid_credentials_show_error()
    {
        $response = $this->post('/admin/login', [
            'userName' => 'nonexistent',
            'password' => 'wrongpassword'
        ]);

        $response->assertRedirect('/admin');
        $response->assertSessionHasErrors(['userName']);
    }
}
