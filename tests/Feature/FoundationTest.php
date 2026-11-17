<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('AI Invoice to Excel');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');
        $response->assertSee('Total Invoices');
        $response->assertSee('Total PDFs Uploaded');
    }

    public function test_authenticated_user_can_view_upload_invoices(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->get('/uploads/invoices');
        $response->assertStatus(200);
        $response->assertSee('Upload Invoice Documents');
    }

    public function test_authenticated_user_can_view_upload_shipping_labels(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->get('/uploads/shipping');
        $response->assertStatus(200);
        $response->assertSee('Upload Shipping Documents', false);
    }

    public function test_authenticated_user_can_view_all_invoices(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->get('/invoices');
        $response->assertStatus(200);
        $response->assertSee('All Invoices');
    }

    public function test_authenticated_user_can_view_matched_invoices(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->get('/invoices/matched');
        $response->assertStatus(200);
        $response->assertSee('Matched Invoices');
    }

    public function test_authenticated_user_can_view_unmatched_invoices(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->get('/invoices/unmatched');
        $response->assertStatus(200);
        $response->assertSee('Unmatched Invoices');
    }

    public function test_authenticated_user_can_view_data_review(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->get('/invoices/review');
        $response->assertStatus(200);
        $response->assertSee('Data Review Queue');
    }

    public function test_authenticated_user_can_view_processing_queue(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->get('/processing');
        $response->assertStatus(200);
        $response->assertSee('Processing Queue');
    }

    public function test_authenticated_user_can_view_excel_exports(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->get('/exports');
        $response->assertStatus(200);
        $response->assertSee('Excel Exports');
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();

        $response = $this->actingAs($user)->get('/profile');
        $response->assertStatus(200);
        $response->assertSee('My Profile');
    }

    public function test_invoice_export_excel_can_be_generated(): void
    {
        $user = User::where('email', 'admin@invoiceexcel.test')->first();
        $invoice = Invoice::first() ?? Invoice::create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-FOUNDATION-TEST',
            'total_amount' => 500.00,
            'currency' => 'INR',
        ]);

        $this->assertNotNull($invoice);
        $response = $this->actingAs($user)->get("/invoices/{$invoice->id}/export");
        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), '.xlsx'));
    }
}
