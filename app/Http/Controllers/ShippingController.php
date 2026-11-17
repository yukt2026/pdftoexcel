<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShippingRequest;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\ShippingDocument;
use App\Models\ShippingLabel;
use App\Repositories\ShippingLabelRepository;
use App\Services\Shipping\ShippingDocumentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ShippingLabelRepository $labelRepository
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->search ?: $request->tracking,
            'status' => $request->status,
            'courier' => $request->courier ?: $request->carrier,
            'format' => $request->format,
            'is_corrected' => $request->boolean('corrected'),
        ];

        if (auth()->user()->isAdmin()) {
            $labels = $this->labelRepository->getAllLabels($filters, 15);
        } else {
            $labels = $this->labelRepository->getUserLabels(auth()->id(), $filters, 15);
        }

        // Also fetch legacy shipping documents for backward compatibility
        $docsQuery = ShippingDocument::with(['user', 'invoice'])->latest();
        if (!auth()->user()->isAdmin()) {
            $docsQuery->where('user_id', auth()->id());
        }
        $documents = $docsQuery->paginate(15);

        $invoicesQuery = Invoice::orderBy('invoice_number');
        if (!auth()->user()->isAdmin()) {
            $invoicesQuery->where('user_id', auth()->id());
        }
        $invoices = $invoicesQuery->get();

        return view('shipping.index', compact('labels', 'documents', 'invoices'));
    }

    public function create()
    {
        $invoicesQuery = Invoice::orderBy('invoice_number');
        if (!auth()->user()->isAdmin()) {
            $invoicesQuery->where('user_id', auth()->id());
        }
        $invoices = $invoicesQuery->get();

        return view('shipping.create', compact('invoices'));
    }

    public function store(StoreShippingRequest $request, ShippingDocumentService $shippingService)
    {
        $data = $request->validated();
        if (!empty($data['invoice_id']) && !auth()->user()->isAdmin()) {
            $inv = Invoice::findOrFail($data['invoice_id']);
            if ($inv->user_id !== auth()->id()) {
                abort(403, 'Unauthorized. Cannot link to an invoice you do not own.');
            }
        }

        $shippingDoc = $shippingService->createShippingDocument($data, auth()->id());

        return redirect()->route('shipping.index')
            ->with('success', "Shipping document {$shippingDoc->tracking_number} registered successfully.");
    }

    public function show(ShippingDocument $shipping)
    {
        $this->authorize('view', $shipping);

        $shipping->load(['user', 'invoice.items', 'matchingRecords']);
        return view('shipping.show', compact('shipping'));
    }

    /**
     * Show extracted Shipping Label with inspection and manual correction form.
     */
    public function showLabel(ShippingLabel $label)
    {
        $this->authorize('view', $label);

        $label->load(['user', 'document', 'invoice']);
        return view('shipping.label-show', compact('label'));
    }

    /**
     * Allow manual correction of extracted shipping ID / AWB number.
     * Preserves original tracking number for full audit trail.
     */
    public function correctAwb(Request $request, ShippingLabel $label)
    {
        $this->authorize('update', $label);

        $validated = $request->validate([
            'awb_number' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $oldAwb = $label->awb_number;
        $updatedLabel = $this->labelRepository->updateAwb($label, $validated['awb_number'], $validated['notes'] ?? null);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "AWB number updated from '{$oldAwb}' to '{$updatedLabel->awb_number}'.",
                'label' => $updatedLabel,
            ]);
        }

        return back()->with('success', "Shipping ID / AWB number successfully updated from '{$oldAwb}' to '{$updatedLabel->awb_number}'.");
    }

    /**
     * Delete an extracted shipping label.
     */
    public function destroyLabel(ShippingLabel $label)
    {
        $this->authorize('delete', $label);

        $awb = $label->awb_number;
        $this->labelRepository->delete($label);

        ActivityLog::record('shipping_label_deleted', "Deleted shipping label {$awb}");

        return redirect()->route('shipping.index')
            ->with('success', "Shipping label {$awb} deleted successfully.");
    }

    public function destroy(ShippingDocument $shipping)
    {
        $this->authorize('delete', $shipping);

        $tracking = $shipping->tracking_number;
        $shipping->delete();

        ActivityLog::record('shipping_doc_deleted', "Deleted shipping document {$tracking}");

        return redirect()->route('shipping.index')
            ->with('success', "Shipping record {$tracking} removed.");
    }
}
