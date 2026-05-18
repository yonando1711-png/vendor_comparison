<?php

namespace App\Http\Controllers;

use App\Models\ComparisonLog;
use App\Models\VendorComparison;
use App\Services\OdooService;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $user = Auth::user();

        $query = VendorComparison::with(['creator', 'supervisor', 'manager'])
            ->orderByDesc('created_at');

        if ($user->isCreator()) {
            $query->where('created_by', $user->id);
        }

        $comparisons = $query->get();

        $stats = [
            'pending_supervisor' => $comparisons->where('status', 'pending_supervisor')->count(),
            'pending_manager'    => $comparisons->where('status', 'pending_manager')->count(),
            'approved'           => $comparisons->where('status', 'approved')->count(),
            'rejected'           => $comparisons->where('status', 'rejected')->count(),
        ];

        return view('comparisons.index', compact('comparisons', 'stats'));
    }

    /**
     * Submit a new vendor comparison.
     */
    public function store(Request $request)
    {
        if (! Auth::user()->isCreator()) {
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
            ->whereNotIn('status', ['rejected'])->exists()
        ) {
            return back()->with('error', 'A comparison for this RFQ is already active. Please view it in Approvals.');
        }

        $comparison = VendorComparison::create([
            'po_id'           => $request->po_id,
            'po_name'         => $request->po_name,
            'po_vendor'       => $request->po_vendor,
            'category'        => $request->category,
            'vendors'         => $request->vendors,
            'vendor_prices'   => $request->vendor_prices,
            'selected_vendor' => $request->selected_vendor,
            'notes'           => $request->notes,
            'status'          => 'pending_supervisor',
            'created_by'      => Auth::id(),
        ]);

        // Audit log
        ComparisonLog::create([
            'comparison_id' => $comparison->id,
            'user_id'       => Auth::id(),
            'action'        => 'submitted',
            'notes'         => $request->notes,
        ]);

        // Clear localStorage draft key in session
        session()->flash('clear_draft_key', "clvp_draft_{$request->po_id}");

        return redirect()->route('comparisons.index')
            ->with('success', "Comparison for {$request->po_name} submitted for Supervisor approval.");
    }

    /**
     * Edit a comparison (only while pending_supervisor).
     */
    public function edit(VendorComparison $comparison)
    {
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
        $user = Auth::user();

        $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

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
                'status'             => 'approved',
                'manager_id'         => $user->id,
                'manager_approved_at' => now(),
                'manager_notes'      => $request->notes,
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
        $user = Auth::user();

        $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);

        if (!$user->isSupervisor() && !$user->isManager()) {
            return back()->with('error', 'Only supervisors or managers can reject comparisons.');
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
        } catch (\Throwable $e) {
            $rfq = null;
            $history = [];
        }

        $comparison->load(['creator', 'supervisor', 'manager', 'rejectedBy', 'logs.user']);

        return view('comparisons.show', compact('comparison', 'rfq', 'history'));
    }

    /**
     * Export CLVP as PDF.
     */
    public function pdf(VendorComparison $comparison)
    {
        $comparison->load(['creator', 'supervisor', 'manager']);

        $pdf = Pdf::loadView('comparisons.pdf', compact('comparison'))
            ->setPaper('a4', 'landscape')
            ->setOptions(['dpi' => 120, 'defaultFont' => 'Arial', 'isHtml5ParserEnabled' => true]);

        $filename = 'CLVP-' . str_replace('/', '-', $comparison->po_name) . '.pdf';

        return $pdf->download($filename);
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
