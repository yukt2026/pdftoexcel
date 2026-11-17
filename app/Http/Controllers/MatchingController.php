<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceShippingMatch;
use App\Models\ShippingLabel;
use App\Services\Matching\InvoiceMatchingService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class MatchingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected InvoiceMatchingService $matchingService
    ) {}

    /**
     * Matching Dashboard & Results Page.
     * Displays: Matched invoices, Needs Review, Unmatched invoices, and Unmatched shipping labels.
     */
    public function index(Request $request)
    {
        $userId = auth()->user()->isAdmin() ? null : auth()->id();
        $activeTab = $request->query('tab', 'matched');

        // Core Summary Metrics
        $matchedCount = InvoiceShippingMatch::matched()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->count();

        $needsReviewCount = InvoiceShippingMatch::needsReview()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->count();

        $duplicateCount = InvoiceShippingMatch::duplicate()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->count();

        $unmatchedInvoicesCount = Invoice::where('matching_status', 'unmatched')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->count();

        $unmatchedLabelsCount = ShippingLabel::whereIn('status', [ShippingLabel::STATUS_EXTRACTED, ShippingLabel::STATUS_UNMATCHED])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->count();

        // 1. Matched Invoices & Shipping Labels
        $matchedQuery = InvoiceShippingMatch::matched()
            ->with(['invoice', 'shippingLabel', 'user'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest();

        // 2. Needs Review Matches
        $needsReviewQuery = InvoiceShippingMatch::needsReview()
            ->with(['invoice', 'shippingLabel', 'user'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest();

        // 3. Unmatched Invoices
        $unmatchedInvoicesQuery = Invoice::where('matching_status', 'unmatched')
            ->with(['user', 'vendor'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest();

        // 4. Unmatched Shipping Labels
        $unmatchedLabelsQuery = ShippingLabel::whereIn('status', [ShippingLabel::STATUS_EXTRACTED, ShippingLabel::STATUS_UNMATCHED])
            ->with(['user', 'document'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest();

        // 5. Duplicate Matches
        $duplicateQuery = InvoiceShippingMatch::duplicate()
            ->with(['invoice', 'shippingLabel', 'user'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest();

        // Search filter if provided
        if ($request->filled('search')) {
            $term = $request->search;
            $matchedQuery->where(function ($q) use ($term) {
                $q->where('order_number', 'like', "%{$term}%")
                  ->orWhere('awb_number', 'like', "%{$term}%")
                  ->orWhere('invoice_number', 'like', "%{$term}%");
            });
            $needsReviewQuery->where(function ($q) use ($term) {
                $q->where('order_number', 'like', "%{$term}%")
                  ->orWhere('awb_number', 'like', "%{$term}%");
            });
            $unmatchedInvoicesQuery->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', "%{$term}%")
                  ->orWhere('order_number', 'like', "%{$term}%")
                  ->orWhere('buyer_name', 'like', "%{$term}%");
            });
            $unmatchedLabelsQuery->where(function ($q) use ($term) {
                $q->where('awb_number', 'like', "%{$term}%")
                  ->orWhere('order_number', 'like', "%{$term}%")
                  ->orWhere('buyer_name', 'like', "%{$term}%");
            });
        }

        $matchedMatches = $matchedQuery->paginate(15, ['*'], 'matched_page')->withQueryString();
        $needsReviewMatches = $needsReviewQuery->paginate(15, ['*'], 'review_page')->withQueryString();
        $unmatchedInvoices = $unmatchedInvoicesQuery->paginate(15, ['*'], 'unmatched_inv_page')->withQueryString();
        $unmatchedLabels = $unmatchedLabelsQuery->paginate(15, ['*'], 'unmatched_lbl_page')->withQueryString();
        $duplicateMatches = $duplicateQuery->paginate(15, ['*'], 'dup_page')->withQueryString();

        // Available candidate shipping labels for manual matching modal
        $candidateLabels = ShippingLabel::whereIn('status', [ShippingLabel::STATUS_EXTRACTED, ShippingLabel::STATUS_UNMATCHED])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('awb_number')
            ->get();

        return view('matching.index', compact(
            'activeTab',
            'matchedCount',
            'needsReviewCount',
            'duplicateCount',
            'unmatchedInvoicesCount',
            'unmatchedLabelsCount',
            'matchedMatches',
            'needsReviewMatches',
            'unmatchedInvoices',
            'unmatchedLabels',
            'duplicateMatches',
            'candidateLabels'
        ));
    }

    /**
     * Trigger batch intelligent matching across all unlinked documents.
     */
    public function runMatch(Request $request)
    {
        $userId = auth()->user()->isAdmin() ? null : auth()->id();

        try {
            $summary = $this->matchingService->runBatchMatching($userId);

            $msg = "Reconciliation complete: {$summary['matched']} matched, {$summary['needs_review']} needs review, {$summary['duplicates']} duplicate(s) prevented.";
            return redirect()->route('matching.index')
                ->with('success', $msg);
        } catch (Exception $e) {
            return back()->with('warning', "Matching execution error: " . $e->getMessage());
        }
    }

    /**
     * Manually match an invoice with a shipping label.
     */
    public function manualMatch(Request $request)
    {
        $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'shipping_label_id' => ['required', 'exists:shipping_labels,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);
        $label = ShippingLabel::findOrFail($request->shipping_label_id);

        if (!auth()->user()->isAdmin()) {
            if ($invoice->user_id !== auth()->id() || $label->user_id !== auth()->id()) {
                abort(403, 'Unauthorized. Cannot match records belonging to other users.');
            }
        }

        try {
            $match = $this->matchingService->manualMatch($invoice, $label, $request->notes, auth()->id());
            return redirect()->route('matching.index', ['tab' => 'matched'])
                ->with('success', "Invoice #{$invoice->invoice_number} successfully linked to Shipping Label {$label->awb_number}.");
        } catch (Exception $e) {
            return back()->with('warning', $e->getMessage());
        }
    }

    /**
     * Manually correct an existing match pair.
     */
    public function correctMatch(Request $request, InvoiceShippingMatch $match)
    {
        if (!auth()->user()->isAdmin() && $match->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'shipping_label_id' => ['required', 'exists:shipping_labels,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $newLabel = ShippingLabel::findOrFail($request->shipping_label_id);

        if (!auth()->user()->isAdmin() && $newLabel->user_id !== auth()->id()) {
            abort(403, 'Unauthorized. Cannot reassign to a label belonging to another user.');
        }

        try {
            $this->matchingService->correctMatch($match, $newLabel, $request->notes, auth()->id());
            return back()->with('success', "Match pair corrected to Shipping Label {$newLabel->awb_number}.");
        } catch (Exception $e) {
            return back()->with('warning', $e->getMessage());
        }
    }

    /**
     * Unmatch an active match pair.
     */
    public function unmatch(InvoiceShippingMatch $match)
    {
        if (!auth()->user()->isAdmin() && $match->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $this->matchingService->unmatch($match);
        return back()->with('success', "Match unlinked successfully.");
    }

    /**
     * Approve a 'Needs Review' match pair into 'Matched'.
     */
    public function approveReview(InvoiceShippingMatch $match)
    {
        if (!auth()->user()->isAdmin() && $match->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        $match->update([
            'status' => InvoiceShippingMatch::STATUS_MATCHED,
            'notes' => 'Discrepancy reviewed and manually approved by user.',
            'matched_by_user_id' => auth()->id(),
        ]);

        $match->invoice?->update([
            'matching_status' => 'matched',
            'shipping_id' => $match->shippingLabel?->shipping_id ?: $match->awb_number,
        ]);
        $match->shippingLabel?->update(['status' => ShippingLabel::STATUS_MATCHED]);

        ActivityLog::record(
            'match_review_approved',
            "Approved reviewed match between Invoice #{$match->invoice_number} and AWB {$match->awb_number}.",
            $match
        );

        return back()->with('success', "Match approved and verified.");
    }
}
