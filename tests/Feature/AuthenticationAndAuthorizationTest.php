<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // 1. Login with valid credentials
    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@invoiceexcel.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    // 2. Cannot login with invalid credentials
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@invoiceexcel.test',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // 3. Deactivated user cannot login
    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::where('email', 'user@invoiceexcel.test')->first();
        $user->update(['status' => 'inactive']);

        $response = $this->post('/login', [
            'email' => 'user@invoiceexcel.test',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // 4. User can logout
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::where('email', 'user@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    // 5. Forgot password request
    public function test_forgot_password_link_can_be_requested(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'user@invoiceexcel.test',
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@invoiceexcel.test',
        ]);
    }

    // 6. Reset password with valid token
    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::where('email', 'user@invoiceexcel.test')->first();
        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'user@invoiceexcel.test',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
    }

    // 7. Profile update and Change Password
    public function test_user_can_update_profile_and_change_password(): void
    {
        $user = User::where('email', 'user@invoiceexcel.test')->first();

        // Update profile
        $response = $this->actingAs($user)->put('/profile', [
            'name' => 'Updated Operations Name',
            'email' => 'user@invoiceexcel.test',
            'phone' => '+1 555-9988',
        ]);
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertEquals('Updated Operations Name', $user->name);

        // Change password
        $passwordResponse = $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'brand-new-pass123',
            'password_confirmation' => 'brand-new-pass123',
        ]);
        $passwordResponse->assertSessionHas('success');
        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-pass123', $user->password));
    }

    // 8. Admin can access admin routes
    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::where('email', 'admin@invoiceexcel.test')->first();

        $this->actingAs($admin)->get('/users')->assertStatus(200);
        $this->actingAs($admin)->get('/settings')->assertStatus(200);
        $this->actingAs($admin)->get('/reports')->assertStatus(200);
    }

    // 9. Standard user is strictly forbidden from admin routes (HTTP 403)
    public function test_standard_user_is_forbidden_from_admin_routes(): void
    {
        $user = User::where('email', 'user@invoiceexcel.test')->first();

        $this->actingAs($user)->get('/users')->assertStatus(403);
        $this->actingAs($user)->get('/settings')->assertStatus(403);
        $this->actingAs($user)->get('/reports')->assertStatus(403);
    }

    // 10. Standard user cannot view another user's invoice (HTTP 403)
    public function test_standard_user_cannot_view_other_users_invoice(): void
    {
        $admin = User::where('email', 'admin@invoiceexcel.test')->first();
        $user = User::where('email', 'user@invoiceexcel.test')->first();

        // Admin's invoice
        $adminInvoice = Invoice::where('user_id', $admin->id)->first() ?? Invoice::create([
            'user_id' => $admin->id,
            'invoice_number' => 'INV-ADMIN-TEST',
            'total_amount' => 100,
            'currency' => 'INR',
        ]);
        $this->assertNotNull($adminInvoice);

        // Standard user tries to view admin's invoice directly
        $response = $this->actingAs($user)->get("/invoices/{$adminInvoice->id}");
        $response->assertStatus(403);
    }

    // 11. Standard user cannot edit another user's invoice (HTTP 403)
    public function test_standard_user_cannot_edit_other_users_invoice(): void
    {
        $admin = User::where('email', 'admin@invoiceexcel.test')->first();
        $user = User::where('email', 'user@invoiceexcel.test')->first();

        $adminInvoice = Invoice::where('user_id', $admin->id)->first() ?? Invoice::create([
            'user_id' => $admin->id,
            'invoice_number' => 'INV-ADMIN-TEST',
            'total_amount' => 100,
            'currency' => 'INR',
        ]);

        // Standard user tries to access edit form
        $this->actingAs($user)->get("/invoices/{$adminInvoice->id}/edit")->assertStatus(403);

        // Standard user tries to submit update
        $response = $this->actingAs($user)->put("/invoices/{$adminInvoice->id}", [
            'invoice_number' => 'HACKED-999',
            'currency' => 'INR',
            'subtotal' => 100,
            'total_amount' => 100,
            'payment_status' => 'paid',
        ]);
        $response->assertStatus(403);
    }

    // 12. Standard user can view and edit their own invoice
    public function test_standard_user_can_view_and_edit_own_invoice(): void
    {
        $user = User::where('email', 'user@invoiceexcel.test')->first();
        $userInvoice = Invoice::where('user_id', $user->id)->first() ?? Invoice::create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-USER-TEST',
            'total_amount' => 200,
            'currency' => 'INR',
        ]);
        $this->assertNotNull($userInvoice);

        $this->actingAs($user)->get("/invoices/{$userInvoice->id}")->assertStatus(200);
        $this->actingAs($user)->get("/invoices/{$userInvoice->id}/edit")->assertStatus(200);
    }

    // 13. Admin can manage users (create, update, delete)
    public function test_admin_can_manage_users(): void
    {
        $admin = User::where('email', 'admin@invoiceexcel.test')->first();

        // Admin creates new user
        $createResponse = $this->actingAs($admin)->post('/users', [
            'name' => 'New Auditor',
            'email' => 'auditor@invoiceexcel.test',
            'password' => 'auditorpass123',
            'role' => User::ROLE_USER,
            'phone' => '+1 555-4422',
        ]);
        $createResponse->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['email' => 'auditor@invoiceexcel.test']);

        $auditor = User::where('email', 'auditor@invoiceexcel.test')->first();

        // Admin updates user
        $updateResponse = $this->actingAs($admin)->put("/users/{$auditor->id}", [
            'name' => 'Senior Auditor',
            'email' => 'auditor@invoiceexcel.test',
            'role' => User::ROLE_USER,
            'status' => 'inactive',
        ]);
        $updateResponse->assertSessionHas('success');
        $auditor->refresh();
        $this->assertEquals('inactive', $auditor->status);

        // Admin deletes user
        $deleteResponse = $this->actingAs($admin)->delete("/users/{$auditor->id}");
        $deleteResponse->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $auditor->id]);
    }

    // 14. Admin cannot delete or demote themselves
    public function test_admin_cannot_delete_or_demote_themselves(): void
    {
        $admin = User::where('email', 'admin@invoiceexcel.test')->first();

        // Try self-demotion
        $demoteResponse = $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => 'System Administrator',
            'email' => 'admin@invoiceexcel.test',
            'role' => User::ROLE_USER,
            'status' => 'active',
        ]);
        $demoteResponse->assertSessionHasErrors('role');

        // Try self-deletion
        $deleteResponse = $this->actingAs($admin)->delete("/users/{$admin->id}");
        $deleteResponse->assertSessionHasErrors('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
