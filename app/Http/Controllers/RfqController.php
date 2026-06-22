<?php

namespace App\Http\Controllers;

use App\Models\VendorComparison;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RfqController extends Controller
{
    public function __construct(private OdooService $odoo) {}

    /**
     * List all RFQ purchase orders.
     */
    public function index()
    {
        try {
            $rfqs = $this->odoo->getRfqs();
            $odooError = null;
        } catch (\Throwable $e) {
            $rfqs = [];
            $odooError = $e->getMessage();
        }

        // Map po_id → comparison so the view can show CLVP status badges.
        // When multiple comparisons exist for the same RFQ (e.g. after rejection + resubmission),
        // prefer the active/latest one by ordering descending and keying by po_id (first wins).
        $existingComparisons = VendorComparison::whereIn('po_id', array_column($rfqs, 'id'))
            ->orderByDesc('id')
            ->get(['id', 'po_id', 'status'])
            ->keyBy('po_id');

        // Cache staleness info
        $cachedAt = Cache::get('odoo_rfqs_cached_at');

        return view('rfq.index', compact('rfqs', 'odooError', 'existingComparisons', 'cachedAt'));
    }

    /**
     * List low-value RFQs (amount_total <= 250,000) — read-only view for all roles.
     */
    public function rfqList()
    {
        try {
            $rfqs = $this->odoo->getRfqs();
            $odooError = null;
        } catch (\Throwable $e) {
            $rfqs = [];
            $odooError = $e->getMessage();
        }

        // Only keep general purchase type POs with amount_total <= 250,000
        $rfqs = array_values(array_filter(
            $rfqs,
            fn($r) => ($r['amount_total'] ?? 0) <= 250000
                && ($r['purchase_type'] ?? '') === 'general'
        ));

        // Fetch order lines for all matching RFQs so the popup can display products
        $linesByRfq = [];
        if (!empty($rfqs)) {
            $allLineIds = array_merge(...array_map(fn($r) => $r['order_line'], $rfqs));
            try {
                if (!empty($allLineIds)) {
                    $allLines   = $this->odoo->getOrderLines($allLineIds);
                    $linesById  = collect($allLines)->keyBy('id');
                    foreach ($rfqs as $rfq) {
                        $linesByRfq[$rfq['id']] = array_values(
                            array_filter(
                                array_map(fn($lid) => $linesById[$lid] ?? null, $rfq['order_line'])
                            )
                        );
                    }
                }
            } catch (\Throwable) {
                $linesByRfq = [];
            }
        }

        $cachedAt = Cache::get('odoo_rfqs_cached_at');

        return view('rfq.list', compact('rfqs', 'odooError', 'cachedAt', 'linesByRfq'));
    }

    /**
     * Flush Odoo caches and redirect back to the RFQ list.
     */
    public function refresh()
    {
        $this->odoo->flushCache();
        return redirect()->route('rfq.index')->with('success', 'Data refreshed from Odoo.');
    }

    /**
     * Show the vendor comparison form for a single RFQ.
     */
    public function show(int $id)
    {
        try {
            $rfq = $this->odoo->getRfq($id);

            if (!$rfq) {
                abort(404, 'RFQ not found.');
            }

            $productIds = array_values(array_filter(array_map(
                fn($line) => is_array($line['product_id']) ? $line['product_id'][0] : null,
                $rfq['lines']
            )));

            $history = $this->odoo->getProductVendorHistory($productIds);
            $vendors = $this->odoo->getVendors();
        } catch (\Throwable $e) {
            return view('rfq.show', [
                'rfq'                => null,
                'history'            => [],
                'vendors'            => [],
                'existingComparison' => null,
                'comparison'         => null,
                'error'              => $e->getMessage(),
            ]);
        }

        // Check if a comparison exists for this RFQ
        $existingComparison = VendorComparison::where('po_id', $id)
            ->latest()
            ->first();

        return view('rfq.show', [
            'rfq'                => $rfq,
            'history'            => $history,
            'vendors'            => $vendors,
            'existingComparison' => $existingComparison,
            'comparison'         => null,
            'error'              => null,
        ]);
    }
}
