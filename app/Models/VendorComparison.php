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
        'vendors'                    => 'array',
        'vendor_prices'              => 'array',
        'supervisor_approved_at'     => 'datetime',
        'manager_approved_at'        => 'datetime',
        'bypassed_at'                => 'datetime',
        'rejected_at'                => 'datetime',
        'odoo_synced_at'             => 'datetime',
        'cancelled_at'               => 'datetime',
        'controller_acknowledged_at' => 'datetime',
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
        return $this->isPendingSupervisor()
            && $user->isCreator()
            && $this->created_by === $user->id;
    }

    // ── Status helpers ─────────────────────────────────────

    public function isPendingSupervisor(): bool
    {
        return $this->status === 'pending_supervisor';
    }

    public function isPendingManager(): bool
    {
        return $this->status === 'pending_manager';
    }

    /**
     * Flow: Staff → Supervisor → Manager
     */
    public function isInActiveApproval(): bool
    {
        return in_array($this->status, ['pending_supervisor', 'pending_manager']);
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
            && $this->isPendingSupervisor();
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

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_supervisor' => 'Pending Supervisor',
            'pending_manager'    => 'Pending Manager',
            'approved'           => 'Approved',
            'rejected'           => 'Rejected',
            'cancelled'          => 'Cancelled',
            default              => 'Draft',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending_supervisor' => 'bg-warning text-dark',
            'pending_manager'    => 'bg-info text-dark',
            'approved'           => 'bg-success',
            'rejected'           => 'bg-danger',
            'cancelled'          => 'bg-secondary',
            default              => 'bg-secondary',
        };
    }
}
