<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparisonLog extends Model
{
    protected $fillable = ['comparison_id', 'user_id', 'action', 'notes'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comparison()
    {
        return $this->belongsTo(VendorComparison::class);
    }

    /**
     * Icon class for each action type.
     */
    public function actionIcon(): string
    {
        return match ($this->action) {
            'submitted'       => 'bi-send text-primary',
            'approved_supervisor' => 'bi-check-circle text-success',
            'approved_manager'    => 'bi-check-circle-fill text-success',
            'rejected'        => 'bi-x-circle-fill text-danger',
            'edited'          => 'bi-pencil text-warning',
            'odoo_posted'             => 'bi-cloud-check text-info',
            'acknowledged_controller' => 'bi-eye-fill text-teal',
            default                   => 'bi-info-circle text-secondary',
        };
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'submitted'           => 'Submitted',
            'approved_supervisor' => 'Approved by Supervisor',
            'approved_manager'    => 'Approved by Manager',
            'rejected'            => 'Rejected',
            'edited'              => 'Edited',
            'odoo_posted'             => 'Posted to Odoo',
            'acknowledged_controller' => 'Acknowledged by Controller',
            default                   => ucfirst($this->action),
        };
    }
}
