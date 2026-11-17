<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceCorrection;
use App\Models\InvoiceItem;
use App\Models\ShippingLabel;
use App\Models\Vendor;
use App\Services\Excel\ExcelExportService;
use App\Services\Invoice\InvoiceProcessorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    use AuthorizesRequests;

    // Page 4: All Invoices (Admin sees all, User sees only own)
    public function index(Request $request)
    {
        $query = Invoice::with(['vendor', 'customer', 'items', 'shippingMatch.shippingLabel', 'document'])->latest();

        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('po_number', 'like', "%{$search}%")
                  ->orWhere('order_number', 'like', "%{$search}%")
                  ->orWhere('shipping_id', 'like', "%{$search}%")
                  ->orWhere('buyer_name', 'like', "%{$search}%")
                  ->orWhere('buyer_state', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('shippingMatch', function ($sq) use ($search) {
                      $sq->where('awb_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('processing_status')) {
            $query->where('processing_status', $request->processing_status);
        }

        if ($request->filled('matching_status')) {
            $query->where('matching_status', $request->matching_status);
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        $invoices = $query->paginate(15)->withQueryString();
        $vendors = Vendor::orderBy('name')->get();

        return view('invoices.index', compact('invoices', 'vendors'));
    }

    // Page 6: Matched Invoices (Scoped)
    public function matched(Request $request)
    {
        $query = Invoice::with(['vendor', 'customer', 'items', 'shippingDocuments', 'matchingRecords'])
            ->where('matching_status', 'matched')
            ->latest();

        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('po_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->paginate(15)->withQueryString();
        $totalMatched = (clone $query)->count();
        $matchedValue = (clone $query)->sum('total_amount');

        return view('invoices.matched', compact('invoices', 'totalMatched', 'matchedValue'));
    }

    // Page 7: Unmatched Invoices (Scoped)
    public function unmatched(Request $request)
    {
        $query = Invoice::with(['vendor', 'customer', 'items', 'matchingRecords'])
            ->whereIn('matching_status', ['unmatched', 'mismatch'])
            ->latest();

        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('po_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->paginate(15)->withQueryString();
        $totalUnmatched = (clone $query)->count();
        $unmatchedValue = (clone $query)->sum('total_amount');

        return view('invoices.unmatched', compact('invoices', 'totalUnmatched', 'unmatchedValue'));
    }

    // Page 8: Data Review (Scoped)
    public function review(Request $request)
    {
        $query = Invoice::with(['vendor', 'customer', 'items', 'documents'])
            ->whereIn('processing_status', ['extracted', 'review_needed'])
            ->latest();

        if (!auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $pendingReviews = $query->paginate(12)->withQueryString();
        $needsReviewCount = (clone $query)->count();

        return view('review.index', compact('pendingReviews', 'needsReviewCount'));
    }

    // Quick Validate action from Data Review (Policy protected)
    public function quickValidate(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice->update([
            'processing_status' => 'validated',
        ]);

        ActivityLog::record(
            'invoice_quick_validated',
            "Invoice #{$invoice->invoice_number} approved and marked validated in Data Review.",
            $invoice
        );

        return back()->with('success', "Invoice #{$invoice->invoice_number} verified and validated.");
    }

    // Show Invoice (Policy protected)
    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'vendor',
            'customer',
            'items',
            'document',
            'documents',
            'extractionResult',
            'shippingDocuments',
            'shippingMatch.shippingLabel',
            'corrections.editor',
            'corrections.invoiceItem',
        ]);

        $validation = $invoice->validation_issues;
        $originalData = $invoice->original_extracted_data;

        return view('invoices.show', compact('invoice', 'validation', 'originalData'));
    }

    // Edit Invoice (Policy protected)
    public function edit(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice->load([
            'vendor',
            'customer',
            'items',
            'document',
            'extractionResult',
            'shippingMatch.shippingLabel',
            'corrections.editor',
        ]);

        $vendors = Vendor::orderBy('name')->get();
        $customers = Customer::orderBy('name')->get();
        $validation = $invoice->validation_issues;
        $originalData = $invoice->original_extracted_data;

        return view('invoices.edit', compact('invoice', 'vendors', 'customers', 'validation', 'originalData'));
    }

    // Update Invoice (Policy protected)
    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        DB::transaction(function () use ($request, $invoice) {
            $fieldsToTrack = [
                'invoice_number' => $request->invoice_number,
                'invoice_date' => $request->invoice_date ? \Carbon\Carbon::parse($request->invoice_date)->format('Y-m-d') : null,
                'due_date' => $request->due_date ? \Carbon\Carbon::parse($request->due_date)->format('Y-m-d') : null,
                'po_number' => $request->po_number,
                'order_number' => $request->order_number,
                'shipping_id' => $request->shipping_id,
                'buyer_name' => $request->buyer_name ?? $request->customer_name,
                'buyer_address' => $request->buyer_address,
                'buyer_state' => $request->buyer_state,
                'place_of_supply' => $request->place_of_supply,
                'currency' => $request->currency,
                'subtotal' => $request->taxable_value !== null ? (float) $request->taxable_value : (float) $request->subtotal,
                'cgst_amount' => (float) ($request->cgst_amount ?? 0),
                'sgst_amount' => (float) ($request->sgst_amount ?? 0),
                'igst_amount' => (float) ($request->igst_amount ?? 0),
                'tax_amount' => (float) ($request->tax_amount ?? (($request->cgst_amount ?? 0) + ($request->sgst_amount ?? 0) + ($request->igst_amount ?? 0))),
                'discount_amount' => (float) ($request->discount_amount ?? 0),
                'shipping_amount' => (float) ($request->shipping_amount ?? 0),
                'total_amount' => (float) $request->total_amount,
                'payment_status' => $request->payment_status ?? $invoice->payment_status,
            ];

            foreach ($fieldsToTrack as $fieldName => $newVal) {
                $oldVal = $invoice->{$fieldName};
                if ($oldVal instanceof \Carbon\Carbon || $oldVal instanceof \DateTimeInterface) {
                    $oldVal = $oldVal->format('Y-m-d');
                } elseif (is_numeric($oldVal) && is_numeric($newVal)) {
                    $oldVal = (float) $oldVal;
                    $newVal = (float) $newVal;
                }

                $normOld = (string) ($oldVal ?? '');
                $normNew = (string) ($newVal ?? '');

                if (is_float($oldVal) && is_float($newVal)) {
                    $hasChanged = round(abs($oldVal - $newVal), 2) > 0;
                } else {
                    $hasChanged = (trim($normOld) !== trim($normNew));
                }

                if ($hasChanged) {
                    InvoiceCorrection::create([
                        'invoice_id' => $invoice->id,
                        'field_name' => $fieldName,
                        'original_value' => $oldVal !== null ? (string) $oldVal : null,
                        'updated_value' => $newVal !== null ? (string) $newVal : null,
                        'updated_by' => auth()->id(),
                    ]);
                }
            }

            $vendorId = $invoice->vendor_id;
            if ($request->filled('vendor_name')) {
                $vendor = Vendor::firstOrCreate(
                    ['name' => trim($request->vendor_name)],
                    ['tax_number' => $request->vendor_tax_number]
                );
                $vendorId = $vendor->id;
            }

            $customerId = $invoice->customer_id;
            if ($request->filled('buyer_name') || $request->filled('customer_name')) {
                $cName = trim($request->buyer_name ?: $request->customer_name);
                $customer = Customer::firstOrCreate(
                    ['name' => $cName],
                    ['address' => $request->buyer_address, 'state' => $request->buyer_state]
                );
                $customerId = $customer->id;
            }

            $subtotalVal = $request->taxable_value !== null ? $request->taxable_value : $request->subtotal;
            $cgstVal = (float) ($request->cgst_amount ?? 0);
            $sgstVal = (float) ($request->sgst_amount ?? 0);
            $igstVal = (float) ($request->igst_amount ?? 0);
            $taxAmountVal = $request->tax_amount !== null ? (float) $request->tax_amount : ($cgstVal + $sgstVal + $igstVal);

            $invoice->update([
                'invoice_number' => $request->invoice_number,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'po_number' => $request->po_number,
                'order_number' => $request->order_number,
                'shipping_id' => $request->shipping_id,
                'buyer_name' => $request->buyer_name ?? $request->customer_name,
                'buyer_address' => $request->buyer_address,
                'buyer_state' => $request->buyer_state,
                'place_of_supply' => $request->place_of_supply,
                'currency' => $request->currency,
                'vendor_id' => $vendorId,
                'customer_id' => $customerId,
                'subtotal' => $subtotalVal ?? 0,
                'cgst_amount' => $cgstVal,
                'sgst_amount' => $sgstVal,
                'igst_amount' => $igstVal,
                'tax_amount' => $taxAmountVal,
                'discount_amount' => $request->discount_amount ?? 0,
                'shipping_amount' => $request->shipping_amount ?? 0,
                'total_amount' => $request->total_amount,
                'payment_status' => $request->payment_status ?? $invoice->payment_status,
                'processing_status' => $request->processing_status ?? 'validated',
                'is_manually_edited' => true,
            ]);

            if ($request->has('items') && is_array($request->items)) {
                $oldItems = $invoice->items()->get()->keyBy('id');
                $invoice->items()->delete();

                foreach ($request->items as $itemData) {
                    $desc = trim($itemData['description'] ?? $itemData['item_name'] ?? '');
                    if (empty($desc)) {
                        continue;
                    }

                    $qty = (float) ($itemData['quantity'] ?? 1);
                    $rate = (float) ($itemData['item_rate'] ?? $itemData['unit_price'] ?? 0);
                    $taxable = (float) ($itemData['taxable_value'] ?? ($qty * $rate));
                    $cgst = (float) ($itemData['cgst'] ?? 0);
                    $sgst = (float) ($itemData['sgst'] ?? 0);
                    $igst = (float) ($itemData['igst'] ?? 0);
                    $total = (float) ($itemData['total_price'] ?? ($taxable + $cgst + $sgst + $igst));

                    $createdItem = InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'item_code' => $itemData['item_code'] ?? null,
                        'description' => $desc,
                        'quantity' => $qty,
                        'unit' => $itemData['unit'] ?? 'unit',
                        'unit_price' => $rate,
                        'item_rate' => $rate,
                        'discount' => (float) ($itemData['discount'] ?? 0),
                        'taxable_value' => $taxable,
                        'tax_rate' => (float) ($itemData['tax_rate'] ?? 0),
                        'tax_amount' => (float) ($itemData['tax_amount'] ?? ($cgst + $sgst + $igst)),
                        'cgst' => $cgst,
                        'sgst' => $sgst,
                        'igst' => $igst,
                        'total_price' => $total,
                        'is_extra_charge' => !empty($itemData['is_extra_charge']),
                    ]);

                    $itemId = $itemData['id'] ?? null;
                    if ($itemId && isset($oldItems[$itemId])) {
                        $prevItem = $oldItems[$itemId];
                        if ($prevItem->description !== $createdItem->description || round(abs((float)$prevItem->total_price - (float)$createdItem->total_price), 2) > 0) {
                            InvoiceCorrection::create([
                                'invoice_id' => $invoice->id,
                                'invoice_item_id' => $createdItem->id,
                                'field_name' => "item[{$createdItem->description}]",
                                'original_value' => "Rate: {$prevItem->item_rate}, Total: {$prevItem->total_price}",
                                'updated_value' => "Rate: {$createdItem->item_rate}, Total: {$createdItem->total_price}",
                                'updated_by' => auth()->id(),
                            ]);
                        }
                    } elseif (!$itemId && $oldItems->isNotEmpty()) {
                        InvoiceCorrection::create([
                            'invoice_id' => $invoice->id,
                            'invoice_item_id' => $createdItem->id,
                            'field_name' => 'item_added',
                            'original_value' => null,
                            'updated_value' => "Added row: {$createdItem->description} (Total: {$createdItem->total_price})",
                            'updated_by' => auth()->id(),
                        ]);
                    }
                }
            }

            if ($request->filled('shipping_id')) {
                $newAwb = trim($request->shipping_id);
                if ($invoice->shippingMatch) {
                    $invoice->shippingMatch->update(['awb_number' => $newAwb]);
                }
            }

            ActivityLog::record(
                'invoice_updated',
                "Invoice #{$invoice->invoice_number} manually corrected and saved.",
                $invoice
            );
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice changes saved and validated successfully.');
    }

    // Destroy Invoice (Policy protected)
    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        $number = $invoice->invoice_number;
        $invoice->delete();

        ActivityLog::record('invoice_deleted', "Invoice {$number} moved to trash.", $invoice);

        return redirect()->route('invoices.index')
            ->with('success', "Invoice {$number} moved to trash.");
    }

    // Export single invoice (Policy protected)
    public function exportExcel(Invoice $invoice, ExcelExportService $excelService)
    {
        $this->authorize('export', $invoice);

        return $excelService->downloadSingleInvoice($invoice);
    }
}
