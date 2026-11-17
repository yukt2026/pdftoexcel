<?php

namespace App\Services\Matching;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceShippingMatch;
use App\Models\ShippingLabel;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceMatchingService
{
    /**
     * Run intelligent batch matching between all available invoices and shipping labels.
     * Wrapped in database transaction to prevent race conditions or inconsistent matches.
     *
     * @param int|null $userId
     * @return array{
     *   matched: int,
     *   needs_review: int,
     *   duplicates: int,
     *   unmatched_invoices: int,
     *   unmatched_labels: int,
     *   matches: Collection<InvoiceShippingMatch>
     * }
     */
    public function runBatchMatching(?int $userId = null): array
    {
        return DB::transaction(function () use ($userId) {
            // 1. Fetch Invoices requiring matching (or not yet matched with active match)
            $invoicesQuery = Invoice::query();
            if ($userId !== null) {
                $invoicesQuery->where('user_id', $userId);
            }
            $invoices = $invoicesQuery->where(function ($q) {
                $q->whereNull('matching_status')
                  ->orWhereIn('matching_status', ['unmatched', 'mismatch', 'review_needed', 'pending']);
            })->get();

            // 2. Fetch assigned Shipping Label IDs
            $assignedLabelIds = InvoiceShippingMatch::where('status', InvoiceShippingMatch::STATUS_MATCHED)
                ->pluck('shipping_label_id')
                ->filter()
                ->toArray();

            // 3. Fetch available Shipping Labels not yet successfully assigned
            $labelsQuery = ShippingLabel::query();
            if ($userId !== null) {
                $labelsQuery->where('user_id', $userId);
            }
            $availableLabels = $labelsQuery->where(function ($q) use ($assignedLabelIds) {
                $q->whereNotIn('id', $assignedLabelIds);
            })->get();

            $matchedCount = 0;
            $needsReviewCount = 0;
            $duplicateCount = 0;
            $createdMatches = collect();

            // 3. Evaluate each invoice against available labels
            foreach ($invoices as $invoice) {
                // Find candidate label following the 5-level matching priority
                $evaluation = $this->findBestLabelForInvoice($invoice, $availableLabels, $assignedLabelIds);

                if ($evaluation) {
                    $label = $evaluation['label'];
                    $method = $evaluation['method'];
                    $confidence = $evaluation['confidence'];
                    $status = $evaluation['status'];
                    $secondary = $evaluation['secondary'];
                    $discrepancies = $evaluation['discrepancies'];

                    $match = InvoiceShippingMatch::create([
                        'user_id' => $invoice->user_id,
                        'invoice_id' => $invoice->id,
                        'shipping_label_id' => $label->id,
                        'order_number' => $invoice->order_number ?: $label->order_number,
                        'awb_number' => $label->awb_number,
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $status,
                        'matching_method' => $method,
                        'confidence_score' => $confidence,
                        'secondary_verification' => $secondary,
                        'discrepancy_details' => !empty($discrepancies) ? $discrepancies : null,
                        'is_manually_matched' => false,
                        'matched_at' => now(),
                    ]);

                    if ($status === InvoiceShippingMatch::STATUS_MATCHED) {
                        $assignedLabelIds[] = $label->id;
                        $label->update([
                            'invoice_id' => $invoice->id,
                            'status' => ShippingLabel::STATUS_MATCHED,
                        ]);
                        $invoice->update([
                            'matching_status' => 'matched',
                            'shipping_id' => $label->shipping_id ?: $label->awb_number,
                        ]);
                        $matchedCount++;
                    } elseif ($status === InvoiceShippingMatch::STATUS_NEEDS_REVIEW) {
                        $label->update(['status' => ShippingLabel::STATUS_DISCREPANCY]);
                        $invoice->update(['matching_status' => 'review_needed']);
                        $needsReviewCount++;
                    }

                    $createdMatches->push($match);
                } else {
                    // Check if invoice matches an already assigned label (Duplicate AWB Assignment attempt)
                    $alreadyAssignedEvaluation = $this->findBestLabelForInvoice($invoice, $availableLabels, []);
                    if ($alreadyAssignedEvaluation && in_array($alreadyAssignedEvaluation['label']->id, $assignedLabelIds)) {
                        $label = $alreadyAssignedEvaluation['label'];
                        $match = InvoiceShippingMatch::create([
                            'user_id' => $invoice->user_id,
                            'invoice_id' => $invoice->id,
                            'shipping_label_id' => $label->id,
                            'order_number' => $invoice->order_number ?: $label->order_number,
                            'awb_number' => $label->awb_number,
                            'invoice_number' => $invoice->invoice_number,
                            'status' => InvoiceShippingMatch::STATUS_DUPLICATE,
                            'matching_method' => $alreadyAssignedEvaluation['method'],
                            'confidence_score' => $alreadyAssignedEvaluation['confidence'],
                            'discrepancy_details' => ["Duplicate assignment prevented: AWB {$label->awb_number} has already been assigned to another invoice."],
                            'is_manually_matched' => false,
                            'matched_at' => now(),
                        ]);
                        $invoice->update(['matching_status' => 'mismatch']);
                        $duplicateCount++;
                        $createdMatches->push($match);
                    } else {
                        $invoice->update(['matching_status' => 'unmatched']);
                    }
                }
            }

            // Count remaining unmatched
            $unmatchedInvoicesCount = Invoice::query()
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->where('matching_status', 'unmatched')
                ->count();

            $unmatchedLabelsCount = ShippingLabel::query()
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->whereIn('status', [ShippingLabel::STATUS_EXTRACTED, ShippingLabel::STATUS_UNMATCHED])
                ->count();

            ActivityLog::record(
                'batch_matching_completed',
                "Executed intelligent matching: {$matchedCount} matched, {$needsReviewCount} needs review, {$duplicateCount} duplicates.",
                null,
                [
                    'matched' => $matchedCount,
                    'needs_review' => $needsReviewCount,
                    'duplicates' => $duplicateCount,
                ]
            );

            return [
                'matched' => $matchedCount,
                'needs_review' => $needsReviewCount,
                'duplicates' => $duplicateCount,
                'unmatched_invoices' => $unmatchedInvoicesCount,
                'unmatched_labels' => $unmatchedLabelsCount,
                'matches' => $createdMatches,
            ];
        });
    }

    /**
     * Find best matching shipping label for an invoice using the strict 5-tier matching priority.
     *
     * PRIORITY:
     * 1. Exact Order Number match
     * 2. Exact Purchase Order Number match
     * 3. Normalized Order Number match
     * 4. Valid suffix removal (_1, _2)
     * 5. Buyer Name and Address as Secondary Verification (NEVER matched solely on buyer name)
     */
    public function findBestLabelForInvoice(Invoice $invoice, Collection $labels, array $excludedLabelIds = []): ?array
    {
        $invOrder = trim($invoice->order_number ?? '');
        $invPo = trim($invoice->po_number ?? '');

        // Rule: Never match solely on buyer name when no order/po numbers exist
        if (empty($invOrder) && empty($invPo)) {
            return null;
        }

        $invOrderNorm = $this->normalizeIdentifier($invOrder);
        $invPoNorm = $this->normalizeIdentifier($invPo);
        $invOrderStripped = $this->stripSuffix($invOrder);
        $invPoStripped = $this->stripSuffix($invPo);

        foreach ($labels as $label) {
            if (in_array($label->id, $excludedLabelIds)) {
                continue;
            }

            $lblOrder = trim($label->order_number ?? '');
            if (empty($lblOrder)) {
                continue;
            }

            $lblOrderNorm = $this->normalizeIdentifier($lblOrder);
            $lblOrderStripped = $this->stripSuffix($lblOrder);

            $matchedMethod = null;
            $confidence = 0.0;

            // 1. Exact Order Number match
            if (!empty($invOrder) && strcasecmp($invOrder, $lblOrder) === 0) {
                $matchedMethod = InvoiceShippingMatch::METHOD_EXACT_ORDER;
                $confidence = 100.0;
            }
            // 2. Exact Purchase Order Number match
            elseif (!empty($invPo) && strcasecmp($invPo, $lblOrder) === 0) {
                $matchedMethod = InvoiceShippingMatch::METHOD_EXACT_PO;
                $confidence = 98.0;
            }
            // 3. Normalized Order Number match
            elseif (!empty($invOrderNorm) && !empty($lblOrderNorm) && ($invOrderNorm === $lblOrderNorm || $invPoNorm === $lblOrderNorm)) {
                $matchedMethod = InvoiceShippingMatch::METHOD_NORMALIZED_ORDER;
                $confidence = 95.0;
            }
            // 4. Suffix Stripped Order Number match (e.g. 351974314617931328_1 -> 351974314617931328)
            elseif (
                (!empty($invOrderStripped) && $invOrderStripped === $lblOrderStripped) ||
                (!empty($invPoStripped) && $invPoStripped === $lblOrderStripped) ||
                (!empty($invPo) && $invPo === $lblOrderStripped) ||
                (!empty($invOrder) && $invOrder === $lblOrderStripped)
            ) {
                $matchedMethod = InvoiceShippingMatch::METHOD_SUFFIX_STRIPPED;
                $confidence = 95.0;
            }

            // If a primary key matched, perform Priority 5: Secondary Verification
            if ($matchedMethod !== null) {
                $secondary = $this->performSecondaryVerification($invoice, $label);
                $discrepancies = [];
                $status = InvoiceShippingMatch::STATUS_MATCHED;

                // Secondary Verification Rule Check:
                // If buyer names exist on both and are completely conflicting, flag for review
                if ($secondary['has_buyer_conflict']) {
                    $status = InvoiceShippingMatch::STATUS_NEEDS_REVIEW;
                    $confidence = max(65.0, $confidence - 25.0);
                    $discrepancies[] = "Order identifier matched, but buyer names differ: '{$invoice->buyer_name}' vs '{$label->buyer_name}'.";
                }

                if ($secondary['has_pincode_conflict']) {
                    $status = InvoiceShippingMatch::STATUS_NEEDS_REVIEW;
                    $confidence = max(60.0, $confidence - 20.0);
                    $discrepancies[] = "Destination postal codes differ between invoice and shipping label.";
                }

                return [
                    'label' => $label,
                    'method' => $matchedMethod,
                    'confidence' => $confidence,
                    'status' => $status,
                    'secondary' => $secondary,
                    'discrepancies' => $discrepancies,
                ];
            }
        }

        return null;
    }

    /**
     * Priority 5: Secondary verification of Buyer Name and Shipping Address.
     * Evaluates whether recipient metadata corroborates the match.
     */
    public function performSecondaryVerification(Invoice $invoice, ShippingLabel $label): array
    {
        $invBuyer = trim(strtolower($invoice->buyer_name ?? ''));
        $lblBuyer = trim(strtolower($label->buyer_name ?? ''));

        $invAddress = trim(strtolower($invoice->buyer_address ?? ''));
        $lblAddress = trim(strtolower($label->shipping_address ?? ''));

        $buyerNameMatch = false;
        $hasBuyerConflict = false;

        if (!empty($invBuyer) && !empty($lblBuyer)) {
            if ($invBuyer === $lblBuyer || str_contains($invBuyer, $lblBuyer) || str_contains($lblBuyer, $invBuyer)) {
                $buyerNameMatch = true;
            } else {
                similar_text($invBuyer, $lblBuyer, $percent);
                if ($percent >= 60.0) {
                    $buyerNameMatch = true;
                } else {
                    $hasBuyerConflict = true;
                }
            }
        }

        $addressMatch = false;
        if (!empty($invAddress) && !empty($lblAddress)) {
            similar_text($invAddress, $lblAddress, $addrPercent);
            if ($addrPercent >= 40.0) {
                $addressMatch = true;
            }
        }

        $pincodeMatch = false;
        $hasPincodeConflict = false;
        if (!empty($label->pincode)) {
            if (str_contains($invAddress, $label->pincode)) {
                $pincodeMatch = true;
            } elseif (!empty($invAddress) && preg_match('/\b[1-9][0-9]{5}\b/', $invAddress, $pinM)) {
                if ($pinM[0] !== $label->pincode) {
                    $hasPincodeConflict = true;
                }
            }
        }

        return [
            'buyer_name_match' => $buyerNameMatch,
            'address_match' => $addressMatch,
            'pincode_match' => $pincodeMatch,
            'has_buyer_conflict' => $hasBuyerConflict,
            'has_pincode_conflict' => $hasPincodeConflict,
        ];
    }

    /**
     * Manually match an Invoice and a Shipping Label.
     * Enforces duplicate prevention and records manual audit notes.
     */
    public function manualMatch(Invoice $invoice, ShippingLabel $label, ?string $notes = null, ?int $userId = null): InvoiceShippingMatch
    {
        return DB::transaction(function () use ($invoice, $label, $notes, $userId) {
            // Check if label is already matched to another invoice
            $existing = InvoiceShippingMatch::where('shipping_label_id', $label->id)
                ->where('status', InvoiceShippingMatch::STATUS_MATCHED)
                ->where('invoice_id', '!=', $invoice->id)
                ->first();

            if ($existing) {
                throw new Exception("Cannot match: Shipping Label {$label->awb_number} is already matched to Invoice #{$existing->invoice?->invoice_number}.");
            }

            // Remove any previous match for this invoice
            InvoiceShippingMatch::where('invoice_id', $invoice->id)->delete();

            $secondary = $this->performSecondaryVerification($invoice, $label);

            $match = InvoiceShippingMatch::create([
                'user_id' => $invoice->user_id,
                'invoice_id' => $invoice->id,
                'shipping_label_id' => $label->id,
                'order_number' => $invoice->order_number ?: $label->order_number,
                'awb_number' => $label->awb_number,
                'invoice_number' => $invoice->invoice_number,
                'status' => InvoiceShippingMatch::STATUS_MATCHED,
                'matching_method' => InvoiceShippingMatch::METHOD_MANUAL,
                'confidence_score' => 100.00,
                'secondary_verification' => $secondary,
                'is_manually_matched' => true,
                'matched_by_user_id' => $userId ?? auth()->id(),
                'matched_at' => now(),
                'notes' => $notes ?: 'Manually linked by user.',
            ]);

            $label->update([
                'invoice_id' => $invoice->id,
                'status' => ShippingLabel::STATUS_MATCHED,
            ]);

            $invoice->update([
                'matching_status' => 'matched',
                'shipping_id' => $label->shipping_id ?: $label->awb_number,
            ]);

            ActivityLog::record(
                'manual_invoice_shipping_matched',
                "Manually matched Invoice #{$invoice->invoice_number} with Shipping Label {$label->awb_number}.",
                $match,
                ['invoice_id' => $invoice->id, 'shipping_label_id' => $label->id]
            );

            return $match;
        });
    }

    /**
     * Manually correct or reassign an existing match to a different shipping label.
     */
    public function correctMatch(InvoiceShippingMatch $match, ShippingLabel $newLabel, ?string $notes = null, ?int $userId = null): InvoiceShippingMatch
    {
        return DB::transaction(function () use ($match, $newLabel, $notes, $userId) {
            // Free the previous label
            if ($match->shippingLabel) {
                $match->shippingLabel->update([
                    'invoice_id' => null,
                    'status' => ShippingLabel::STATUS_UNMATCHED,
                ]);
            }

            $invoice = $match->invoice;
            $secondary = $this->performSecondaryVerification($invoice, $newLabel);

            $match->update([
                'shipping_label_id' => $newLabel->id,
                'awb_number' => $newLabel->awb_number,
                'status' => InvoiceShippingMatch::STATUS_MATCHED,
                'matching_method' => InvoiceShippingMatch::METHOD_MANUAL_CORRECTION,
                'confidence_score' => 100.00,
                'secondary_verification' => $secondary,
                'is_manually_matched' => true,
                'matched_by_user_id' => $userId ?? auth()->id(),
                'notes' => $notes ?: 'Reassigned to new shipping label.',
            ]);

            $newLabel->update([
                'invoice_id' => $invoice->id,
                'status' => ShippingLabel::STATUS_MATCHED,
            ]);

            $invoice->update([
                'matching_status' => 'matched',
                'shipping_id' => $newLabel->shipping_id ?: $newLabel->awb_number,
            ]);

            ActivityLog::record(
                'match_corrected',
                "Match #{$match->id} corrected to Shipping Label {$newLabel->awb_number}.",
                $match
            );

            return $match->fresh();
        });
    }

    /**
     * Unmatch an active match pair.
     */
    public function unmatch(InvoiceShippingMatch $match): bool
    {
        return DB::transaction(function () use ($match) {
            if ($match->shippingLabel) {
                $match->shippingLabel->update([
                    'invoice_id' => null,
                    'status' => ShippingLabel::STATUS_UNMATCHED,
                ]);
            }

            if ($match->invoice) {
                $match->invoice->update([
                    'matching_status' => 'unmatched',
                    'shipping_id' => null,
                ]);
            }

            $match->delete();

            ActivityLog::record('match_unlinked', "Unlinked match record #{$match->id}");
            return true;
        });
    }

    /**
     * Normalize an identifier by removing non-alphanumeric characters and converting to lowercase.
     */
    public function normalizeIdentifier(?string $id): string
    {
        if (empty($id)) {
            return '';
        }
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($id)));
    }

    /**
     * Strip common e-commerce order line suffixes such as _1, _2, -1, -01.
     * e.g. '351974314617931328_1' -> '351974314617931328'
     */
    public function stripSuffix(?string $id): string
    {
        if (empty($id)) {
            return '';
        }
        $id = trim($id);
        return preg_replace('/(?:_[0-9]+|-[0-9]+|-0[0-9]+)$/', '', $id);
    }
}
