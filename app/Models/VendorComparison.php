<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorComparison extends Model
{
    protected $fillable = [
        'comparison_code',
        'po_id',
        'po_name',
        'po_vendor',
        'category',
        'vendors',
        'vendor_prices',
        'selected_vendor',
        'notes',
        'status',
        'created_by',
        'supervisor_id',
        'supervisor_approved_at',
        'supervisor_notes',
        'procurement_id',
        'procurement_approved_at',
        'procurement_notes',
        'requires_procurement',
        'manager_id',
        'manager_approved_at',
        'manager_notes',
        'bypassed_by',
        'bypassed_at',
        'bypass_reason',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'odoo_synced_at',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
        'controller_id',
        'controller_acknowledged_at',
        'controller_notes',
    ];

    protected $casts = [
        'vendors'                => 'array',
        'vendor_prices'          => 'array',
        'supervisor_approved_at'  => 'datetime',
        'procurement_approved_at' => 'datetime',
        'manager_approved_at'     => 'datetime',
        'bypassed_at'             => 'datetime',
        'requires_procurement'    => 'boolean',
        'rejected_at'            => 'datetime',
        'odoo_synced_at'         => 'datetime',
        'cancelled_at'                  => 'datetime',
        'controller_acknowledged_at'    => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function procurement()
    {
        return $this->belongsTo(User::class, 'procurement_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function controller()
    {
        return $this->belongsTo(User::class, 'controller_id');
    }

    public function bypassedBy()
    {
        return $this->belongsTo(User::class, 'bypassed_by');
    }

    public function logs()
    {
        return $this->hasMany(ComparisonLog::class, 'comparison_id')->orderBy('created_at');
    }

    // ── Editable check ─────────────────────────────────────

    public function isEditableBy(\App\Models\User $user): bool
    {
        return ($this->isPendingSupervisor() || $this->isPendingProcurement())
            && $user->isCreator()
            && $this->created_by === $user->id;
    }

    // ── Status helpers ─────────────────────────────────────

    public function isPendingSupervisor(): bool
    {
        return $this->status === 'pending_supervisor';
    }
    public function isPendingProcurement(): bool
    {
        return $this->status === 'pending_procurement';
    }
    public function isPendingManager(): bool
    {
        return $this->status === 'pending_manager';
    }

    /**
     * Flow: pending_supervisor is the SECOND step (after optional procurement).
     * Staff submits → pending_procurement (if required) OR pending_supervisor
     */
    public function isInActiveApproval(): bool
    {
        return in_array($this->status, ['pending_procurement', 'pending_supervisor', 'pending_manager']);
    }
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
    public function isCancellableBy(\App\Models\User $user): bool
    {
        // Procurement can cancel when supervisor hasn't acted yet (they are step 1)
        if ($user->isProcurement() && $this->isPendingSupervisor()) {
            return true;
        }
        // Supervisor can cancel when manager hasn't acted yet
        if ($user->isSupervisor() && $this->isPendingManager()) {
            return true;
        }
        // Manager can cancel when fully approved
        if ($user->isManager() && $this->isApproved()) {
            return true;
        }
        return false;
    }

    public function canBypassApprove(\App\Models\User $user): bool
    {
        return $user->isManager()
            && ($this->isPendingProcurement() || $this->isPendingSupervisor());
    }

    public function isBypassed(): bool
    {
        return $this->bypassed_by !== null;
    }

    /**
     * Returns true if any vendor_price row's product_name contains "karoseri" (case-insensitive).
     */
    public function isKaroseri(): bool
    {
        foreach ($this->vendor_prices ?? [] as $row) {
            if (isset($row['product_name']) && stripos($row['product_name'], 'karoseri') !== false) {
                return true;
            }
        }
        return false;
    }

    public function isAcknowledgedByController(): bool
    {
        return $this->controller_acknowledged_at !== null;
    }

    /**
     * Normalize a product line description for comparison.
     * Lowercases, trims, and collapses internal whitespace so minor
     * formatting differences don't create false "new item" detections.
     */
    public static function normalizeDescription(string $desc): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($desc)));
    }

    /**
     * Evaluate whether automatic conditions trigger Procurement review.
     * Note: The staff can also manually flag via the toggle at submission.
     * Rules (ANY one is sufficient):
     *   1. product never purchased before (no history entry for this product+description)
     *   2. qty >= 25
     *   3. line total (best price * qty) >= 5,000,000
     *
     * History is keyed by "product_id::normalized_description" so that generic-code
     * products (e.g. [GA], [ATK]) sharing the same product_id are differentiated
     * by what was actually ordered (Approach A: normalize + exact match).
     *
     * @param array $vendorPrices  stored vendor_prices rows
     * @param array $history       "product_id::desc" => vendor history from Odoo
     * @param array $rfqLines      RFQ lines from Odoo
     */
    public static function checkRequiresProcurement(
        array $vendorPrices,
        array $history,
        array $rfqLines,
        string $selectedVendor = '',
        array $vendors = []
    ): bool {
        // Find the column index of the selected vendor
        $selectedIdx = null;
        if ($selectedVendor !== '' && !empty($vendors)) {
            foreach ($vendors as $i => $v) {
                if (($v['name'] ?? '') === $selectedVendor) {
                    $selectedIdx = $i;
                    break;
                }
            }
        }

        foreach ($vendorPrices as $row) {
            $qty    = (float) ($row['qty'] ?? 0);
            $allPrices = array_filter((array) ($row['prices'] ?? []), fn($p) => (float) $p > 0);

            // Use selected vendor price if known, else min across all
            if ($selectedIdx !== null && isset($row['prices'][$selectedIdx])) {
                $unitPrice = (float) $row['prices'][$selectedIdx];
            } else {
                $unitPrice = !empty($allPrices) ? min(array_map('floatval', $allPrices)) : 0;
            }

            $lineTotal = $unitPrice * $qty;

            if ($qty >= 25) {
                return true;
            }

            if ($unitPrice > 0 && $lineTotal >= 5_000_000) {
                return true;
            }
        }

        foreach ($rfqLines as $line) {
            if (!is_array($line['product_id'])) {
                continue;
            }
            $productId  = $line['product_id'][0];
            $historyKey = $productId . '::' . self::normalizeDescription($line['name'] ?? '');
            if (empty($history[$historyKey])) {
                return true;
            }
        }

        return false;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_supervisor'  => 'Pending Supervisor',
            'pending_procurement' => 'Pending Procurement',
            'pending_manager'     => 'Pending Manager',
            'approved'            => 'Approved',
            'rejected'            => 'Rejected',
            'cancelled'           => 'Cancelled',
            default              => 'Draft',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending_supervisor'  => 'bg-warning text-dark',
            'pending_procurement' => 'text-white',
            'pending_manager'     => 'bg-info text-dark',
            'approved'            => 'bg-success',
            'rejected'            => 'bg-danger',
            'cancelled'           => 'bg-secondary',
            default              => 'bg-secondary',
        };
    }
}
