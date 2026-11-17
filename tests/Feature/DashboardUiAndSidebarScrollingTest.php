<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUiAndSidebarScrollingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@invoiceexcel.test')->first();
        $this->user = User::where('email', 'user@invoiceexcel.test')->first();
    }

    /**
     * Test Problem 1 & 2: Sidebar and Main Content Independent Scrolling Architecture.
     */
    public function test_sidebar_and_main_content_scrolling_css_architecture(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        // 1. Sidebar Structure
        $response->assertSee('id="sidebar-wrapper"', false);
        $response->assertSee('class="sidebar-header"', false);
        $response->assertSee('class="sidebar-menu"', false);
        $response->assertSee('class="sidebar-footer"', false);

        // 2. Main Content & Independent Scrolling Structure
        $response->assertSee('id="page-content-wrapper"', false);
        $response->assertSee('class="top-navbar"', false);
        $response->assertSee('class="main-content"', false);

        // 3. Verify CSS Flexbox rules for independent scrolling
        $response->assertSee('--sidebar-width: 270px;', false);
        $response->assertSee('--primary-navy: #172554;', false);
        $response->assertSee('--primary-teal: #0F766E;', false);
        $response->assertSee('--secondary-teal: #134E4A;', false);
        $response->assertSee('--main-bg: #F8FAFC;', false);
        $response->assertSee('--card-bg: #FFFFFF;', false);
        $response->assertSee('--text-primary: #1E293B;', false);
        $response->assertSee('--text-secondary: #64748B;', false);
        $response->assertSee('--border-color: #E2E8F0;', false);

        // Sidebar height & flex rules
        $response->assertSee('height: 100vh;', false);
        $response->assertSee('height: 100dvh;', false);
        $response->assertSee('flex: 1 1 0%;', false);
        $response->assertSee('overflow-y: auto;', false);
        $response->assertSee('overflow-x: hidden;', false);
        $response->assertSee('flex-shrink: 0;', false);

        // Subtle scrollbar styles
        $response->assertSee('.sidebar-menu::-webkit-scrollbar', false);
        $response->assertSee('#page-content-wrapper::-webkit-scrollbar', false);
    }

    /**
     * Test Problem 3: Complete Sidebar Navigation across all groups.
     */
    public function test_complete_sidebar_navigation_groups_and_routes(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        // Group 1: OVERVIEW
        $response->assertSee('Overview');
        $response->assertSee(route('dashboard'));
        $response->assertSee('Dashboard');

        // Group 2: DOCUMENT INGESTION
        $response->assertSee('Document Ingestion');
        $response->assertSee(route('uploads.invoices'));
        $response->assertSee('Upload Invoices');
        $response->assertSee(route('uploads.shipping'));
        $response->assertSee('Upload Shipping Labels');
        $response->assertSee(route('batches.index'));
        $response->assertSee('Upload Batches');
        $response->assertSee(route('processing.index'));
        $response->assertSee('Processing Queue');

        // Group 3: INVOICE OPERATIONS
        $response->assertSee('Invoice Operations');
        $response->assertSee(route('invoices.index'));
        $response->assertSee('All Invoices');
        $response->assertSee(route('invoices.review'));
        $response->assertSee('Data Review');

        // Group 4: RECONCILIATION
        $response->assertSee('Reconciliation');
        $response->assertSee(route('invoices.matched'));
        $response->assertSee('Matched Invoices');
        $response->assertSee(route('invoices.unmatched'));
        $response->assertSee('Unmatched Invoices');
        $response->assertSee(route('matching.index'));
        $response->assertSee('2-Way & 3-Way Match', false);
        $response->assertSee(route('shipping.index'));
        $response->assertSee('Shipping Bills');

        // Group 5: EXPORTS
        $response->assertSee('Exports');
        $response->assertSee(route('exports.index'));
        $response->assertSee('Excel Exports');
        $response->assertSee(route('history.index', ['tab' => 'exports']));
        $response->assertSee('Export History');
        $response->assertSee(route('history.index'));
        $response->assertSee('Processing History');

        // Group 6: MANAGEMENT
        $response->assertSee('Management');
        $response->assertSee(route('reports.index'));
        $response->assertSee('Reports');
        $response->assertSee(route('users.index'));
        $response->assertSee('User Management');
        $response->assertSee(route('settings.index'));
        $response->assertSee('Settings');
        $response->assertSee(route('profile.index'));
        $response->assertSee('My Profile');
    }

    /**
     * Test Problem 4 & 5: Dashboard Metric Cards, Quick Operations, and Recent Uploads.
     */
    public function test_dashboard_components_and_visual_elements(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        // Quick Operations toolbar
        $response->assertSee('Quick Operations');
        $response->assertSee('Upload new batches, trigger background processing');
        $response->assertSee('Upload Invoice PDF');
        $response->assertSee('Upload Shipping Label PDF');
        $response->assertSee('Process Documents');
        $response->assertSee('Export Excel');

        // 8 Metric Widgets
        $response->assertSee('Total Invoices');
        $response->assertSee('Total PDFs Uploaded');
        $response->assertSee('Processed Invoices');
        $response->assertSee('Pending Invoices');
        $response->assertSee('Failed Invoices');
        $response->assertSee('Matched Shipping Labels');
        $response->assertSee('Unmatched Invoices');
        $response->assertSee('Total Excel Exports');

        // Recent Uploads Table
        $response->assertSee('Recent Uploads');
        $response->assertSee('File Name');
        $response->assertSee('Document Type');
        $response->assertSee('Upload Date');
        $response->assertSee('Total Pages');
        $response->assertSee('Processing Status');

        // Total Invoice Volume & System Pipeline
        $response->assertSee('Total Invoice Volume');
        $response->assertSee('Average AI Confidence:');
        $response->assertSee('System Pipeline');
        $response->assertSee('1. Upload:');
        $response->assertSee('2. AI Parse:');
        $response->assertSee('3. Reconcile:');
        $response->assertSee('4. Export:');
    }

    /**
     * Test Mobile & Tablet responsiveness mechanisms.
     */
    public function test_mobile_drawer_and_toggle_attributes(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        // Hamburger button present with onclick="toggleSidebar()"
        $response->assertSee('onclick="toggleSidebar()"', false);

        // Sidebar backdrop present
        $response->assertSee('id="sidebar-backdrop"', false);

        // Nav links have closeSidebarOnMobile()
        $response->assertSee('onclick="closeSidebarOnMobile()"', false);
    }
}
