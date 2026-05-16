<?php

namespace App\Http\Controllers;

use App\Services\OdooService;
use Illuminate\Http\Request;

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
        } catch (\Throwable $e) {
            return view('rfq.index', [
                'rfqs'  => [],
                'error' => $e->getMessage(),
            ]);
        }

        return view('rfq.index', ['rfqs' => $rfqs, 'error' => null]);
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
     * Show the vendor comparison for a single RFQ.
     */
    public function show(int $id)
    {
        try {
            $rfq = $this->odoo->getRfq($id);

            if (!$rfq) {
                abort(404, 'RFQ not found.');
            }

            // Collect product IDs from the order lines (skip lines with no product)
            $productIds = array_values(array_filter(array_map(
                fn($line) => is_array($line['product_id']) ? $line['product_id'][0] : null,
                $rfq['lines']
            )));

            // Get latest purchase history per vendor for each product
            $history = $this->odoo->getProductVendorHistory($productIds);

            // All active suppliers for the vendor selection form
            $vendors = $this->odoo->getVendors();
        } catch (\Throwable $e) {
            return view('rfq.show', [
                'rfq'     => null,
                'history' => [],
                'vendors' => [],
                'error'   => $e->getMessage(),
            ]);
        }

        return view('rfq.show', [
            'rfq'     => $rfq,
            'history' => $history,
            'vendors' => $vendors,
            'error'   => null,
        ]);
    }
}
