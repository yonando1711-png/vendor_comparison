<?php

namespace App\Http\Controllers;

use App\Models\VendorComparison;
use App\Services\OdooService;
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

        // Creators only see their own; supervisors and managers see all
        if ($user->isCreator()) {
            $query->where('created_by', $user->id);
        }

        $comparisons = $query->get();

        return view('comparisons.index', compact('comparisons'));
    }

    /**
     * Submit a new vendor comparison for an RFQ.
     * POST /comparisons
     */
    public function store(Request $request)
    {
        $this->authorize('create-comparison');

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

        // Prevent duplicate submissions
        if (VendorComparison::where('po_id', $request->po_id)->exists()) {
            return back()->with('error', 'A comparison for this RFQ has already been submitted.');
        }

        VendorComparison::create([
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

        return redirect()->route('comparisons.index')
            ->with('success', "Comparison for {$request->po_name} submitted for Supervisor approval.");
    }

    /**
     * Approve a comparison (supervisor → pending_manager, manager → approved).
     * POST /comparisons/{comparison}/approve
     */
    public function approve(Request $request, VendorComparison $comparison)
    {
        $user = Auth::user();

        $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($user->isSupervisor() && $comparison->isPendingSupervisor()) {
            $comparison->update([
                'status'                  => 'pending_manager',
                'supervisor_id'           => $user->id,
                'supervisor_approved_at'  => now(),
                'supervisor_notes'        => $request->notes,
            ]);

            return back()->with('success', "Comparison approved. Now pending Manager approval.");
        }

        if ($user->isManager() && $comparison->isPendingManager()) {
            $comparison->update([
                'status'                => 'approved',
                'manager_id'            => $user->id,
                'manager_approved_at'   => now(),
                'manager_notes'         => $request->notes,
            ]);

            return back()->with('success', "Comparison fully approved!");
        }

        return back()->with('error', 'You are not authorized to approve this comparison at its current stage.');
    }

    /**
     * Reject a comparison.
     * POST /comparisons/{comparison}/reject
     */
    public function reject(Request $request, VendorComparison $comparison)
    {
        $user = Auth::user();

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

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

        return back()->with('success', 'Comparison has been rejected.');
    }

    /**
     * Show a single comparison with the RFQ data pulled from Odoo.
     */
    public function show(VendorComparison $comparison)
    {
        try {
            $rfq = $this->odoo->getRfq($comparison->po_id);

            $productIds = array_map(fn($l) => $l['product_id'][0], $rfq['lines'] ?? []);
            $history    = $this->odoo->getProductVendorHistory($productIds);
        } catch (\Throwable $e) {
            $rfq     = null;
            $history = [];
        }

        $comparison->load(['creator', 'supervisor', 'manager', 'rejectedBy']);

        return view('comparisons.show', compact('comparison', 'rfq', 'history'));
    }
}
