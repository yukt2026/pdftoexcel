<?php

namespace Tests\Feature;

use App\Jobs\MatchingJob;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceShippingMatch;
use App\Models\ShippingLabel;
use App\Models\User;
use App\Services\Matching\InvoiceMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceShippingMatchingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
    }

    /**
     * Test Example 1: Flipkart exact order ID match (Priority 1).
     * Invoice Order ID: OD439142473682225100
     * Shipping Label Order ID: OD439142473682225100
     * Shipping ID: FMPC6756481216
     */
    public function test_flipkart_exact_order_id_matching(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'NUBFYP0270000004',
            'order_number' => 'OD439142473682225100',
            'buyer_name' => 'Sangeeta Soni',
            'buyer_address' => '12 husenipur maharajganj, Maharajganj bhadohi, Gird Baragaon - 221314, IN-UP',
            'buyer_state' => 'Uttar Pradesh',
            'total_amount' => 353.00,
            'matching_status' => 'unmatched',
        ]);

        $label = ShippingLabel::create([
            'user_id' => $this->user->id,
            'awb_number' => 'FMPC6756481216',
            'order_number' => 'OD439142473682225100',
            'buyer_name' => 'Sangeeta Soni',
            'shipping_address' => '12 husenipur maharajganj, Maharajganj bhadohi, Gird Baragaon - 221314, IN-UP',
            'state' => 'Uttar Pradesh',
            'pincode' => '221314',
            'courier_name' => 'E-Kart Logistics',
            'marketplace_name' => 'Flipkart',
            'status' => ShippingLabel::STATUS_EXTRACTED,
        ]);

        $service = app(InvoiceMatchingService::class);
        $result = $service->runBatchMatching($this->user->id);

        $this->assertEquals(1, $result['matched']);
        $this->assertEquals(0, $result['needs_review']);
        $this->assertEquals(0, $result['duplicates']);

        // Verify Match Record
        $this->assertDatabaseHas('invoice_shipping_matches', [
            'invoice_id' => $invoice->id,
            'shipping_label_id' => $label->id,
            'order_number' => 'OD439142473682225100',
            'awb_number' => 'FMPC6756481216',
            'status' => InvoiceShippingMatch::STATUS_MATCHED,
            'matching_method' => InvoiceShippingMatch::METHOD_EXACT_ORDER,
            'confidence_score' => 100.00,
        ]);

        // Verify Invoice and Label statuses updated
        $invoice->refresh();
        $label->refresh();
        $this->assertEquals('matched', $invoice->matching_status);
        $this->assertEquals(ShippingLabel::STATUS_MATCHED, $label->status);
        $this->assertEquals($invoice->id, $label->invoice_id);
    }

    /**
     * Test Example 2: DREESTI PO number & Suffix Stripped matching (Priority 2 & 4).
     * Invoice PO Number: 351974314617931328
     * Shipping Label Order Number: 351974314617931328_1
     * Shipping ID: 1490843814423655
     */
    public function test_dreesti_po_number_and_suffix_stripped_matching(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'gpzdk27301',
            'po_number' => '351974314617931328',
            'buyer_name' => 'Deep Singh',
            'buyer_address' => '262 hallo majra near water tank, Chandigarh 160002',
            'buyer_state' => 'Chandigarh',
            'total_amount' => 322.03,
            'matching_status' => 'unmatched',
        ]);

        $label = ShippingLabel::create([
            'user_id' => $this->user->id,
            'awb_number' => '1490843814423655',
            'order_number' => '351974314617931328_1', // Suffix _1
            'buyer_name' => 'deep Singh',
            'shipping_address' => '262 hallo majra near water tank, Chandigarh 160002',
            'state' => 'Chandigarh',
            'pincode' => '160002',
            'courier_name' => 'Delhivery',
            'marketplace_name' => 'Meesho',
            'status' => ShippingLabel::STATUS_EXTRACTED,
        ]);

        $service = app(InvoiceMatchingService::class);
        $result = $service->runBatchMatching($this->user->id);

        $this->assertEquals(1, $result['matched']);
        $this->assertEquals(0, $result['needs_review']);

        // Verify Match Record in MySQL
        $this->assertDatabaseHas('invoice_shipping_matches', [
            'invoice_id' => $invoice->id,
            'shipping_label_id' => $label->id,
            'awb_number' => '1490843814423655',
            'status' => InvoiceShippingMatch::STATUS_MATCHED,
            'matching_method' => InvoiceShippingMatch::METHOD_SUFFIX_STRIPPED,
        ]);

        $label->refresh();
        $this->assertEquals($invoice->id, $label->invoice_id);
        $this->assertEquals(ShippingLabel::STATUS_MATCHED, $label->status);
    }

    /**
     * Rule Test: Never match solely on buyer name.
     */
    public function test_never_matches_solely_on_buyer_name(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-NO-ORDER',
            'order_number' => null,
            'po_number' => null,
            'buyer_name' => 'Rahul Sharma',
            'buyer_address' => 'Delhi, India',
            'total_amount' => 100.00,
            'matching_status' => 'unmatched',
        ]);

        $label = ShippingLabel::create([
            'user_id' => $this->user->id,
            'awb_number' => 'AWB99887766',
            'order_number' => 'COMPLETELY-DIFFERENT-ORDER-99',
            'buyer_name' => 'Rahul Sharma',
            'shipping_address' => 'Delhi, India',
            'courier_name' => 'Delhivery',
            'status' => ShippingLabel::STATUS_EXTRACTED,
        ]);

        $service = app(InvoiceMatchingService::class);
        $result = $service->runBatchMatching($this->user->id);

        // Must NOT match solely based on matching buyer name
        $this->assertEquals(0, $result['matched']);
        $this->assertDatabaseMissing('invoice_shipping_matches', [
            'invoice_id' => $invoice->id,
            'shipping_label_id' => $label->id,
            'status' => InvoiceShippingMatch::STATUS_MATCHED,
        ]);
    }

    /**
     * Rule Test: Prevent duplicate AWB assignment.
     */
    public function test_prevents_duplicate_awb_assignment(): void
    {
        $label = ShippingLabel::create([
            'user_id' => $this->user->id,
            'awb_number' => 'FMPC6756481216',
            'order_number' => 'OD439142473682225100',
            'buyer_name' => 'Sangeeta Soni',
            'courier_name' => 'E-Kart Logistics',
            'status' => ShippingLabel::STATUS_EXTRACTED,
        ]);

        $invoice1 = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-FIRST',
            'order_number' => 'OD439142473682225100',
            'buyer_name' => 'Sangeeta Soni',
            'matching_status' => 'unmatched',
        ]);

        $invoice2 = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-SECOND-CONFLICT',
            'order_number' => 'OD439142473682225100',
            'buyer_name' => 'Sangeeta Soni',
            'matching_status' => 'unmatched',
        ]);

        $service = app(InvoiceMatchingService::class);
        $result = $service->runBatchMatching($this->user->id);

        // One gets matched, the conflicting duplicate attempt is flagged as duplicate
        $this->assertEquals(1, $result['matched']);
        $this->assertEquals(1, $result['duplicates']);

        $matches = InvoiceShippingMatch::all();
        $this->assertCount(2, $matches);
        $this->assertEquals(1, $matches->where('status', InvoiceShippingMatch::STATUS_MATCHED)->count());
        $this->assertEquals(1, $matches->where('status', InvoiceShippingMatch::STATUS_DUPLICATE)->count());
    }

    /**
     * Secondary Verification Test: Flags 'Needs Review' when buyer name is contradictory.
     */
    public function test_flags_needs_review_when_buyer_name_severely_conflicts(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-REVIEW-01',
            'order_number' => 'ORDER-100200',
            'buyer_name' => 'Ramesh Verma',
            'buyer_address' => 'Ludhiana, Punjab',
            'total_amount' => 450.00,
            'matching_status' => 'unmatched',
        ]);

        $label = ShippingLabel::create([
            'user_id' => $this->user->id,
            'awb_number' => 'AWB-REV-99',
            'order_number' => 'ORDER-100200',
            'buyer_name' => 'Pooja Hegde', // Completely different person
            'shipping_address' => 'Mumbai, Maharashtra',
            'courier_name' => 'Delhivery',
            'status' => ShippingLabel::STATUS_EXTRACTED,
        ]);

        $service = app(InvoiceMatchingService::class);
        $result = $service->runBatchMatching($this->user->id);

        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['needs_review']);

        $this->assertDatabaseHas('invoice_shipping_matches', [
            'invoice_id' => $invoice->id,
            'shipping_label_id' => $label->id,
            'status' => InvoiceShippingMatch::STATUS_NEEDS_REVIEW,
        ]);
    }

    /**
     * Test manual matching and correction.
     */
    public function test_allows_manual_matching_and_correction(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-MANUAL-01',
            'order_number' => 'OD-MANUAL-11',
            'total_amount' => 600.00,
        ]);

        $label1 = ShippingLabel::create([
            'user_id' => $this->user->id,
            'awb_number' => 'AWB-MANUAL-11',
            'courier_name' => 'E-Kart Logistics',
            'status' => ShippingLabel::STATUS_UNMATCHED,
        ]);

        $label2 = ShippingLabel::create([
            'user_id' => $this->user->id,
            'awb_number' => 'AWB-MANUAL-22',
            'courier_name' => 'Delhivery',
            'status' => ShippingLabel::STATUS_UNMATCHED,
        ]);

        $service = app(InvoiceMatchingService::class);

        // 1. Manual Match
        $match = $service->manualMatch($invoice, $label1, 'Verified via warehouse scan', $this->user->id);

        $this->assertEquals(InvoiceShippingMatch::STATUS_MATCHED, $match->status);
        $this->assertEquals(InvoiceShippingMatch::METHOD_MANUAL, $match->matching_method);
        $this->assertTrue($match->is_manually_matched);
        $this->assertEquals('matched', $invoice->fresh()->matching_status);
        $this->assertEquals(ShippingLabel::STATUS_MATCHED, $label1->fresh()->status);

        // 2. Manual Correction (Reassign to label 2)
        $corrected = $service->correctMatch($match, $label2, 'Reassigned to correct courier label', $this->user->id);

        $this->assertEquals($label2->id, $corrected->shipping_label_id);
        $this->assertEquals('AWB-MANUAL-22', $corrected->awb_number);
        $this->assertEquals(InvoiceShippingMatch::METHOD_MANUAL_CORRECTION, $corrected->matching_method);

        // Previous label is now freed
        $this->assertEquals(ShippingLabel::STATUS_UNMATCHED, $label1->fresh()->status);
        $this->assertNull($label1->fresh()->invoice_id);

        // New label is now matched
        $this->assertEquals(ShippingLabel::STATUS_MATCHED, $label2->fresh()->status);
        $this->assertEquals($invoice->id, $label2->fresh()->invoice_id);

        // 3. Unmatch
        $unmatched = $service->unmatch($corrected);
        $this->assertTrue($unmatched);
        $this->assertEquals('unmatched', $invoice->fresh()->matching_status);
        $this->assertEquals(ShippingLabel::STATUS_UNMATCHED, $label2->fresh()->status);
    }

    /**
     * Test MatchingJob queue dispatch.
     */
    public function test_matching_job_dispatches_correctly(): void
    {
        Queue::fake();

        MatchingJob::dispatch($this->user->id);

        Queue::assertPushed(MatchingJob::class, function ($job) {
            return $job->userId === $this->user->id;
        });
    }

    /**
     * Test Web controller endpoints: index, runMatch, manual, approve.
     */
    public function test_matching_controller_web_endpoints(): void
    {
        $invoice = Invoice::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-WEB-01',
            'order_number' => 'OD-WEB-99',
            'total_amount' => 200.00,
            'matching_status' => 'unmatched',
        ]);

        $label = ShippingLabel::create([
            'user_id' => $this->user->id,
            'awb_number' => 'AWB-WEB-99',
            'order_number' => 'OD-WEB-99',
            'courier_name' => 'E-Kart Logistics',
            'status' => ShippingLabel::STATUS_EXTRACTED,
        ]);

        // 1. Visit index page
        $res = $this->actingAs($this->user)->get(route('matching.index'));
        $res->assertOk();
        $res->assertViewHas('matchedCount');
        $res->assertViewHas('needsReviewCount');
        $res->assertViewHas('unmatchedInvoicesCount');
        $res->assertViewHas('unmatchedLabelsCount');

        // 2. Run Match endpoint
        $resRun = $this->actingAs($this->user)->post(route('matching.run'));
        $resRun->assertRedirect(route('matching.index'));
        $resRun->assertSessionHas('success');

        $this->assertDatabaseHas('invoice_shipping_matches', [
            'invoice_id' => $invoice->id,
            'shipping_label_id' => $label->id,
            'status' => InvoiceShippingMatch::STATUS_MATCHED,
        ]);
    }
}
