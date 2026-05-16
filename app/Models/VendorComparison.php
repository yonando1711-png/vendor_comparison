<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorComparison extends Model
{
    protected $fillable = [
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
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'odoo_synced_at',
    ];

    protected $casts = [
        'vendors'                => 'array',
        'vendor_prices'          => 'array',
        'supervisor_approved_at' => 'datetime',
        'manager_approved_at'    => 'datetime',
        'rejected_at'            => 'datetime',
        'odoo_synced_at'         => 'datetime',
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

    public function logs()
    {
        return $this->hasMany(ComparisonLog::class)->orderBy('created_at');
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
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_supervisor' => 'Pending Supervisor',
            'pending_manager'    => 'Pending Manager',
            'approved'           => 'Approved',
            'rejected'           => 'Rejected',
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
            default              => 'bg-secondary',
        };
    }
}
