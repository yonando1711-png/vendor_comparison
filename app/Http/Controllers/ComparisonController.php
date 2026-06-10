<?php

namespace App\Http\Controllers;

use App\Models\ComparisonLog;
use App\Models\VendorComparison;
use App\Services\OdooService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComparisonController extends Controller
{
    public function __construct(private OdooService $odoo) {}

    /**
     * List all comparisons — dashboard view.
     */
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = VendorComparison::with(['creator', 'supervisor', 'manager'])
            ->orderByDesc('created_at');

        if ($user->isCreator()) {
            $query->where('created_by', $user->id);
        }

        $comparisons = $query->get();

        $stats = [
            'pending_supervisor'  => $comparisons->where('status', 'pending_supervisor')->count(),
            'pending_procurement' => $comparisons->where('status', 'pending_procurement')->count(),
            'pending_manager'     => $comparisons->where('status', 'pending_manager')->count(),
            'approved'            => $comparisons->where('status', 'approved')->count(),
            'rejected'            => $comparisons->where('status', 'rejected')->count(),
            'cancelled'           => $comparisons->where('status', 'cancelled')->count(),
        ];

        return view('comparisons.index', compact('comparisons', 'stats'));
    }

    /**
     * Submit a new vendor comparison.
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        if (! $authUser->isCreator()) {
            abort(403, 'Only staff/creators may submit a comparison.');
        }

        $request->validate([
            'po_id'                    => ['required', 'integer'],
            'po_name'                  => ['required', 'string'],
            'po_vendor'                => ['nullable', 'string'],
            'category'                 => ['nullable', 'string'],
            'vendors'                  => ['required', 'array', 'min:3', 'max:10'],
            'vendors.*.name'           => ['required', 'string'],
            'vendor_prices'            => ['nullable', 'array'],
            'selected_vendor'          => ['required', 'string', 'max:255'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
        ]);

        if (VendorComparison::where('po_id', $request->po_id)
            ->whereNotIn('status', ['rejected', 'cancelled'])->exists()
        ) {
            return back()->with('error', 'A comparison for this RFQ is already active. Please view it in Approvals.');
        }

        // Generate comparison code: YYYY/CP/NNNNN
        $year     = now()->year;
        $lastCode = VendorComparison::where('comparison_code', 'like', "{$year}/CP/%")
            ->orderByDesc('comparison_code')
            ->value('comparison_code');
        $seq      = $lastCode ? ((int) substr($lastCode, -5)) + 1 : 1;
        $comparisonCode = $year . '/CP/' . str_pad($seq, 5, '0', STR_PAD_LEFT);

        // Determine if Procurement review is required:
        // either staff manually flagged it OR automatic rules trigger it
        $manualFlag = (bool) ($request->requires_procurement ?? false);

        $autoFlag = false;
        try {
            $rfqLines = $this->odoo->getRfq($request->po_id)['lines'] ?? [];
            $productIds = array_values(array_filter(array_map(
                fn($l) => is_array($l['product_id']) ? $l['product_id'][0] : null,
                $rfqLines
            )));
            $history = $this->odoo->getProductVendorHistory($productIds);
            $autoFlag = VendorComparison::checkRequiresProcurement(
                $request->vendor_prices ?? [],
                $history,
                $rfqLines,
                $request->selected_vendor ?? '',
                $request->vendors ?? []
            );
        } catch (\Throwable) {
            // If Odoo is unreachable, fall back to manual flag only
        }

        $requiresProcurement = $manualFlag || $autoFlag;
        $initialStatus = $requiresProcurement ? 'pending_procurement' : 'pending_supervisor';

        try {
            $comparison = VendorComparison::create([
                'comparison_code'      => $comparisonCode,
                'po_id'                => $request->po_id,
                'po_name'              => $request->po_name,
                'po_vendor'            => $request->po_vendor,
                'category'             => $request->category,
                'vendors'              => $request->vendors,
                'vendor_prices'        => $request->vendor_prices,
                'selected_vendor'      => $request->selected_vendor,
                'notes'                => $request->notes,
                'status'               => $initialStatus,
                'requires_procurement' => $requiresProcurement,
                'created_by'           => Auth::id(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', 'A comparison for this RFQ is already active. Please view it in Approvals.');
        }

        // Audit log
        ComparisonLog::create([
            'comparison_id' => $comparison->id,
            'user_id'       => Auth::id(),
            'action'        => 'submitted',
            'notes'         => $request->notes,
        ]);

        // Clear localStorage draft key in session
        session()->flash('clear_draft_key', "clvp_draft_{$request->po_id}");

        $msg = $requiresProcurement
            ? "Comparison for {$request->po_name} submitted. Requires Procurement review first."
            : "Comparison for {$request->po_name} submitted for Supervisor approval.";

        return redirect()->route('comparisons.index')->with('success', $msg);
    }

    /**
     * Edit a comparison (only while pending_supervisor).
     */
    public function edit(VendorComparison $comparison)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$comparison->isEditableBy($user)) {
            abort(403, 'This comparison cannot be edited at its current stage.');
        }

        try {
            $rfq     = $this->odoo->getRfq($comparison->po_id);
            $vendors = $this->odoo->getVendors();
            $productIds = array_values(array_filter(array_map(
                fn($l) => is_array($l['product_id']) ? $l['product_id'][0] : null,
                $rfq['lines'] ?? []
            )));
            $history = $this->odoo->getProductVendorHistory($productIds);
        } catch (\Throwable $e) {
            $rfq = null;
            $vendors = [];
            $history = [];
        }

        return view('rfq.show', [
            'rfq'        => $rfq,
            'history'    => $history,
            'vendors'    => $vendors,
            'error'      => null,
            'comparison' => $comparison, // pre-fill signal
        ]);
    }

    /**
     * Update a comparison (only while pending_supervisor).
     */
    public function update(Request $request, VendorComparison $comparison)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$comparison->isEditableBy($user)) {
            abort(403, 'This comparison cannot be edited at its current stage.');
        }

        $request->validate([
            'po_id'                    => ['required', 'integer'],
            'po_name'                  => ['required', 'string'],
            'po_vendor'                => ['nullable', 'string'],
            'category'                 => ['nullable', 'string'],
            'vendors'                  => ['required', 'array', 'min:3', 'max:10'],
            'vendors.*.name'           => ['required', 'string'],
            'vendor_prices'            => ['nullable', 'array'],
            'selected_vendor'          => ['required', 'string', 'max:255'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
        ]);

        $comparison->update([
            'category'        => $request->category,
            'vendors'         => $request->vendors,
            'vendor_prices'   => $request->vendor_prices,
            'selected_vendor' => $request->selected_vendor,
            'notes'           => $request->notes,
        ]);

        ComparisonLog::create([
            'comparison_id' => $comparison->id,
            'user_id'       => Auth::id(),
            'action'        => 'edited',
            'notes'         => 'Comparison data updated before supervisor review.',
        ]);

        session()->flash('clear_draft_key', "clvp_draft_{$comparison->po_id}");

        return redirect()->route('comparisons.show', $comparison)
            ->with('success', 'Comparison updated successfully.');
    }

    /**
     * Approve (supervisor → pending_manager, manager → approved).
     */
    public function approve(Request $request, VendorComparison $comparison)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        // Flow: Staff → Procurement (if required) → Supervisor → Manager

        if ($user->isProcurement() && $comparison->isPendingProcurement()) {
            $comparison->update([
                'status'                  => 'pending_supervisor',
                'procurement_id'          => $user->id,
                'procurement_approved_at' => now(),
                'procurement_notes'       => $request->notes,
            ]);

            ComparisonLog::create([
                'comparison_id' => $comparison->id,
                'user_id'       => $user->id,
                'action'        => 'approved_procurement',
                'notes'         => $request->notes,
            ]);

            return back()->with('success', 'Procurement approved. Now pending Supervisor approval.');
        }

        if ($user->isSupervisor() && $comparison->isPendingSupervisor()) {
            $comparison->update([
                'status'                 => 'pending_manager',
                'supervisor_id'          => $user->id,
                'supervisor_approved_at' => now(),
                'supervisor_notes'       => $request->notes,
            ]);

            ComparisonLog::create([
                'comparison_id' => $comparison->id,
                'user_id'       => $user->id,
                'action'        => 'approved_supervisor',
                'notes'         => $request->notes,
            ]);

            return back()->with('success', 'Comparison approved. Now pending Manager approval.');
        }

        if ($user->isManager() && $comparison->isPendingManager()) {
            $comparison->update([
                'status'              => 'approved',
                'manager_id'          => $user->id,
                'manager_approved_at' => now(),
                'manager_notes'       => $request->notes,
            ]);

            ComparisonLog::create([
                'comparison_id' => $comparison->id,
                'user_id'       => $user->id,
                'action'        => 'approved_manager',
                'notes'         => $request->notes,
            ]);

            return back()->with('success', 'Comparison fully approved!');
        }

        return back()->with('error', 'You are not authorized to approve this comparison at its current stage.');
    }

    /**
     * Reject a comparison.
     */
    public function reject(Request $request, VendorComparison $comparison)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);

        if (!$user->isSupervisor() && !$user->isManager() && !$user->isProcurement()) {
            return back()->with('error', 'Only supervisors, procurement, or managers can reject comparisons.');
        }

        if ($user->isProcurement() && !$comparison->isPendingProcurement()) {
            return back()->with('error', 'Procurement can only reject comparisons pending their review.');
        }

        if ($user->isSupervisor() && !$comparison->isPendingSupervisor()) {
            return back()->with('error', 'Supervisor can only reject comparisons pending their review.');
        }

        if ($comparison->isApproved() || $comparison->isRejected()) {
            return back()->with('error', 'This comparison cannot be rejected in its current state.');
        }

        $comparison->update([
            'status'           => 'rejected',
            'rejected_by'      => $user->id,
            'rejected_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        ComparisonLog::create([
            'comparison_id' => $comparison->id,
            'user_id'       => $user->id,
            'action'        => 'rejected',
            'notes'         => $request->rejection_reason,
        ]);

        return back()->with('success', 'Comparison has been rejected.');
    }

    /**
     * Cancel an approved comparison (supervisor or manager only).
     */
    public function cancel(Request $request, VendorComparison $comparison)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $request->validate(['cancel_reason' => ['required', 'string', 'max:2000']]);

        if (!$comparison->isCancellableBy($user)) {
            return back()->with('error', 'Only supervisors or managers can cancel an approved comparison.');
        }

        $comparison->update([
            'status'        => 'cancelled',
            'cancelled_by'  => $user->id,
            'cancelled_at'  => now(),
            'cancel_reason' => $request->cancel_reason,
        ]);

        ComparisonLog::create([
            'comparison_id' => $comparison->id,
            'user_id'       => $user->id,
            'action'        => 'cancelled',
            'notes'         => $request->cancel_reason,
        ]);

        return back()->with('success', 'Comparison has been cancelled.');
    }

    /**
     * Show a single comparison.
     */
    public function show(VendorComparison $comparison)
    {
        try {
            $rfq = $this->odoo->getRfq($comparison->po_id);

            $productIds = array_values(array_filter(array_map(
                fn($l) => is_array($l['product_id']) ? $l['product_id'][0] : null,
                $rfq['lines'] ?? []
            )));
            $history = $this->odoo->getProductVendorHistory($productIds);

            $comparison->vendor_prices = $this->enrichVendorPrices(
                $comparison->vendor_prices ?? [],
                $rfq
            );
        } catch (\Throwable $e) {
            $rfq = null;
            $history = [];
        }

        $comparison->load(['creator', 'supervisor', 'procurement', 'manager', 'rejectedBy', 'cancelledBy', 'logs.user']);

        return view('comparisons.show', compact('comparison', 'rfq', 'history'));
    }

    /**
     * Export CLVP as PDF.
     */
    public function pdf(VendorComparison $comparison)
    {
        $comparison->load(['creator', 'supervisor', 'manager']);

        $rfq = null;
        try {
            $rfq = $this->odoo->getRfq($comparison->po_id);
        } catch (\Throwable) {
            // Odoo unreachable — product names fall back to stored data
        }

        $pdf = Pdf::loadView('comparisons.pdf', compact('comparison', 'rfq'))
            ->setPaper('a4', 'landscape')
            ->setOptions(['dpi' => 120, 'defaultFont' => 'Arial', 'isHtml5ParserEnabled' => true]);

        $filename = 'CLVP-' . str_replace('/', '-', $comparison->po_name) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Build a product_id → enriched data map from RFQ lines and apply it to vendor_prices.
     */
    private function enrichVendorPrices(array $vendorPrices, ?array $rfq): array
    {
        if (empty($rfq['lines'])) {
            return $vendorPrices;
        }

        // Build an ordered list of enriched data indexed by line position,
        // matching the same order/skipping logic used when the form was submitted.
        // Using line index (not product_id) so two lines with the same product
        // (e.g. same ATK product with different descriptions) stay separate.
        $lineData = [];
        $lineIdx  = 0;
        foreach ($rfq['lines'] as $line) {
            if (!is_array($line['product_id'])) {
                continue;
            }
            $cleanName          = $line['product_clean_name'] ?? $line['product_id'][1];
            $code               = $line['product_code'] ?? '';
            $desc               = ($line['name'] !== $cleanName) ? $line['name'] : '';
            $lineData[$lineIdx] = [
                'product_name'        => $cleanName,
                'product_code'        => $code,
                'product_description' => $desc,
            ];
            $lineIdx++;
        }

        foreach ($vendorPrices as $i => &$row) {
            if (isset($lineData[$i])) {
                $row['product_name']        = $lineData[$i]['product_name'];
                $row['product_code']        = $lineData[$i]['product_code'];
                $row['product_description'] = $lineData[$i]['product_description'];
            }
        }
        unset($row);

        return $vendorPrices;
    }

    /**
     * Attach the CLVP PDF to the Odoo RFQ and post a chatter log note.
     * Only available when the comparison is approved.
     */
    public function odooPost(VendorComparison $comparison)
    {
        if (!$comparison->isApproved()) {
            return back()->with('error', 'Only approved comparisons can be posted to Odoo.');
        }

        $comparison->load(['creator', 'supervisor', 'manager']);

        try {
            // Generate PDF as base64 string
            $pdf = Pdf::loadView('comparisons.pdf', compact('comparison'))
                ->setPaper('a4', 'landscape')
                ->setOptions(['dpi' => 120, 'defaultFont' => 'Arial', 'isHtml5ParserEnabled' => true]);

            $pdfContent = $pdf->output();
            $pdfBase64  = base64_encode($pdfContent);
            $filename   = 'CLVP-' . str_replace('/', '-', $comparison->po_name) . '.pdf';

            // Attach PDF to Odoo RFQ
            $this->odoo->attachPdfToPO((int) $comparison->po_id, $pdfBase64, $filename);

            // Build chatter note HTML
            $vendors = collect($comparison->vendors ?? []);
            $vendorList = $vendors->map(fn($v) => htmlspecialchars($v['name'] ?? '—'))->implode(', ');
            $recommended = htmlspecialchars($comparison->selected_vendor ?? '—');
            $approvedBy  = htmlspecialchars($comparison->manager->name ?? '—');
            $approvedOn  = $comparison->manager_approved_at?->format('d M Y H:i') ?? '—';
            $createdBy   = htmlspecialchars($comparison->creator->name ?? '—');

            $customNote = trim(request('note', ''));

            $comparisonUrl = url(route('comparisons.show', $comparison, false));

            $body = <<<HTML
<p><strong>✅ CLVP (Comparison Local Vendor Price) — Approved</strong></p>
<ul>
  <li><strong>Recommended Vendor:</strong> {$recommended}</li>
  <li><strong>Vendors Compared:</strong> {$vendorList}</li>
  <li><strong>Approved by Manager:</strong> {$approvedBy} on {$approvedOn}</li>
  <li><strong>Submitted by:</strong> {$createdBy}</li>
</ul>
<p>📎 <em>The CLVP document has been attached as a PDF file.</em></p>
<p>🔗 <a href="{$comparisonUrl}">View full CLVP on the Vendor Comparison portal</a></p>
HTML;

            if ($customNote !== '') {
                $safeNote = nl2br(htmlspecialchars($customNote));
                $body .= "<hr><p><strong>Note from " . htmlspecialchars(Auth::user()->name) . ":</strong><br>{$safeNote}</p>";
            }

            $this->odoo->postChatterNote((int) $comparison->po_id, $body);

            // Mark as synced
            $comparison->update(['odoo_synced_at' => now()]);

            // Log the action
            ComparisonLog::create([
                'comparison_id' => $comparison->id,
                'user_id'       => Auth::id(),
                'action'        => 'odoo_posted',
                'notes'         => 'PDF attached and log note posted to Odoo PO ' . $comparison->po_name,
            ]);

            return back()->with('success', 'CLVP PDF attached and log note posted to Odoo successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to post to Odoo: ' . $e->getMessage());
        }
    }
}
