<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;

class OdooService
{
    private Client $http;
    private string $url;
    private string $db;
    private string $username;
    private string $apiKey;
    private ?int $uid = null;

    public function __construct()
    {
        $this->url      = rtrim(config('odoo.url'), '/');
        $this->db       = config('odoo.db');
        $this->username = config('odoo.username');
        $this->apiKey   = config('odoo.api_key');

        $this->http = new Client([
            'base_uri' => $this->url,
            'timeout'  => 30,
            'headers'  => ['Content-Type' => 'application/json'],
        ]);
    }

    /**
     * Authenticate and return the user ID, cached for 1 hour.
     */
    public function getUid(): int
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $this->uid = Cache::remember('odoo_uid', 3600, function () {
            $resp = $this->jsonRpc('common', 'authenticate', [
                $this->db,
                $this->username,
                $this->apiKey,
                [],
            ]);

            if (!$resp || !is_int($resp)) {
                throw new \RuntimeException('Odoo authentication failed. Check credentials.');
            }

            return $resp;
        });

        return $this->uid;
    }

    /**
     * Generic JSON-RPC call to the object service (execute_kw).
     */
    public function call(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        return $this->jsonRpc('object', 'execute_kw', [
            $this->db,
            $this->getUid(),
            $this->apiKey,
            $model,
            $method,
            $args,
            $kwargs,
        ]);
    }

    /**
     * Low-level JSON-RPC call.
     */
    private function jsonRpc(string $service, string $method, array $args): mixed
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'id'      => random_int(1, 99999),
            'params'  => [
                'service' => $service,
                'method'  => $method,
                'args'    => $args,
            ],
        ]);

        try {
            $response = $this->http->post('/jsonrpc', ['body' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['error'])) {
                throw new \RuntimeException('Odoo error: ' . ($data['error']['data']['message'] ?? 'Unknown error'));
            }

            return $data['result'] ?? null;
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Odoo API request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    // ─────────────────────────────────────────────
    // Domain methods
    // ─────────────────────────────────────────────

    /**
     * Get all RFQ purchase orders (state = draft or sent).
     * Cached for 5 minutes.
     */
    public function getRfqs(): array
    {
        return Cache::remember('odoo_rfqs', 300, function () {
            $orders = $this->call(
                'purchase.order',
                'search_read',
                [[['state', 'in', ['draft', 'sent']]]],
                [
                    'fields' => [
                        'id',
                        'name',
                        'state',
                        'partner_id',
                        'order_line',
                        'date_order',
                        'amount_total',
                        'origin',
                        'currency_id',
                        'user_id'
                    ],
                    'order'  => 'id desc',
                ]
            );

            // Store when cache was last populated
            Cache::put('odoo_rfqs_cached_at', now(), 300);

            return $orders ?? [];
        });
    }

    /**
     * Get a single RFQ with its order lines populated.
     * Cached for 10 minutes per RFQ ID.
     */
    public function getRfq(int $id): ?array
    {
        return Cache::remember("odoo_rfq_{$id}", 600, function () use ($id) {
            return $this->fetchRfq($id);
        });
    }

    private function fetchRfq(int $id): ?array
    {
        $orders = $this->call(
            'purchase.order',
            'read',
            [[$id]],
            [
                'fields' => [
                    'id',
                    'name',
                    'state',
                    'partner_id',
                    'order_line',
                    'date_order',
                    'amount_total',
                    'origin',
                    'currency_id',
                    'user_id',
                    'notes'
                ],
            ]
        );

        if (empty($orders)) {
            return null;
        }

        $order = $orders[0];

        // Fetch order lines
        if (!empty($order['order_line'])) {
            $order['lines'] = $this->getOrderLines($order['order_line']);
        } else {
            $order['lines'] = [];
        }

        return $order;
    }

    /**
     * Read order lines by IDs.
     */
    public function getOrderLines(array $lineIds): array
    {
        $lines = $this->call(
            'purchase.order.line',
            'read',
            [$lineIds],
            [
                'fields' => [
                    'id',
                    'product_id',
                    'product_qty',
                    'price_unit',
                    'price_subtotal',
                    'name',
                    'product_uom'
                ],
            ]
        );

        if (empty($lines)) {
            return [];
        }

        // Fetch default_code (internal reference) for each product
        $productIds = array_filter(array_map(
            fn($l) => is_array($l['product_id']) ? $l['product_id'][0] : null,
            $lines
        ));

        if (!empty($productIds)) {
            $products = $this->call(
                'product.product',
                'read',
                [array_values($productIds)],
                ['fields' => ['id', 'default_code']]
            );
            $codeMap = [];
            foreach ($products ?? [] as $p) {
                $codeMap[$p['id']] = $p['default_code'] ?: '';
            }
            foreach ($lines as &$line) {
                $pid = is_array($line['product_id']) ? $line['product_id'][0] : null;
                $line['product_code'] = $pid ? ($codeMap[$pid] ?? '') : '';
            }
            unset($line);
        }

        return $lines;
    }

    /**
     * For an array of product IDs, return the latest confirmed purchase per vendor.
     * Cached for 30 minutes — purchase history changes infrequently.
     *
     * Returns: [product_id => [vendor_id => [vendor_name, price_unit, product_qty, uom, po_name, date, order_id]]]
     */
    public function getProductVendorHistory(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $cacheKey = 'odoo_vendor_history_' . md5(implode(',', $productIds));

        return Cache::remember($cacheKey, 1800, function () use ($productIds) {
            return $this->fetchProductVendorHistory($productIds);
        });
    }

    private function fetchProductVendorHistory(array $productIds): array
    {
        // Get all confirmed PO lines for these products (most recent first, capped at 1000)
        $lines = $this->call(
            'purchase.order.line',
            'search_read',
            [[['product_id', 'in', $productIds], ['state', 'in', ['purchase', 'done']]]],
            [
                'fields' => [
                    'id',
                    'product_id',
                    'price_unit',
                    'product_qty',
                    'order_id',
                    'product_uom'
                ],
                'order'  => 'id desc',
                'limit'  => 1000,
            ]
        );

        if (empty($lines)) {
            return [];
        }

        // Collect unique parent PO IDs
        $orderIds = array_values(array_unique(
            array_map(fn($l) => $l['order_id'][0], $lines)
        ));

        // Batch-read parent purchase orders for vendor + date
        $orders = $this->call(
            'purchase.order',
            'read',
            [$orderIds],
            ['fields' => ['id', 'partner_id', 'date_order', 'name']]
        );

        $ordersMap = [];
        foreach (($orders ?? []) as $o) {
            $ordersMap[$o['id']] = $o;
        }

        // Group by product_id → vendor_id, keep only the most recent (highest id = most recent)
        $history = [];
        foreach ($lines as $line) {
            $productId = $line['product_id'][0];
            $orderId   = $line['order_id'][0];
            $order     = $ordersMap[$orderId] ?? null;

            if (!$order) {
                continue;
            }

            $vendorId   = $order['partner_id'][0];
            $vendorName = $order['partner_id'][1];
            $date       = $order['date_order'];

            // Since we ordered by id desc, the first encounter per vendor is the most recent
            if (!isset($history[$productId][$vendorId])) {
                $history[$productId][$vendorId] = [
                    'vendor_id'   => $vendorId,
                    'vendor_name' => $vendorName,
                    'price_unit'  => $line['price_unit'],
                    'product_qty' => $line['product_qty'],
                    'uom'         => is_array($line['product_uom']) ? $line['product_uom'][1] : '',
                    'po_name'     => $order['name'],
                    'date'        => $date,
                    'order_id'    => $orderId,
                ];
            }
        }

        return $history;
    }

    /**
     * Get all active suppliers from Odoo with full contact details for auto-fill.
     * Cached for 30 minutes.
     *
     * Returns: [['id', 'name', 'street', 'phone', 'mobile', 'email'], ...]
     */
    public function getVendors(): array
    {
        return Cache::remember('odoo_vendors', 1800, function () {
            $vendors = $this->call(
                'res.partner',
                'search_read',
                [[['supplier_rank', '>', 0], ['active', '=', true]]],
                [
                    'fields' => ['id', 'name', 'street', 'street2', 'city', 'phone', 'mobile', 'email'],
                    'order'  => 'name asc',
                    'limit'  => 500,
                ]
            );

            return $vendors ?? [];
        });
    }

    /**
     * Flush all Odoo data caches (call after submitting a comparison or manual refresh).
     */
    public function flushCache(): void
    {
        Cache::forget('odoo_rfqs');
        Cache::forget('odoo_vendors');
        // Per-RFQ and per-product caches use dynamic keys; flush by tag not available
        // in file/database drivers — they will expire naturally on their TTL.
    }

    /**
     * Upload a PDF as an ir.attachment linked to a purchase.order record.
     *
     * @param int    $poId        Odoo purchase.order record ID
     * @param string $pdfBase64   Base64-encoded PDF content
     * @param string $filename    Filename shown in Odoo (e.g. "CLVP-2026-POO-01322.pdf")
     * @return int   Newly created ir.attachment ID
     */
    public function attachPdfToPO(int $poId, string $pdfBase64, string $filename): int
    {
        $attachmentId = $this->call(
            'ir.attachment',
            'create',
            [[
                'name'      => $filename,
                'type'      => 'binary',
                'datas'     => $pdfBase64,
                'res_model' => 'purchase.order',
                'res_id'    => $poId,
                'mimetype'  => 'application/pdf',
            ]]
        );

        if (!is_int($attachmentId)) {
            throw new \RuntimeException('Odoo ir.attachment creation returned unexpected result.');
        }

        return $attachmentId;
    }

    /**
     * Post an internal log note to the chatter of a purchase.order record.
     *
     * @param int    $poId      Odoo purchase.order record ID
     * @param string $htmlBody  HTML message body
     */
    public function postChatterNote(int $poId, string $htmlBody): void
    {
        $this->call(
            'purchase.order',
            'message_post',
            [[$poId]],
            [
                'body'           => $htmlBody,
                'message_type'   => 'comment',
                'subtype_xmlid'  => 'mail.mt_note',
            ]
        );
    }
}
