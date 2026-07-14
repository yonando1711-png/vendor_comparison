<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'role',
        'email',
        'password',
    ];

    public function isCreator(): bool
    {
        return $this->role === 'creator';
    }
    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }
    public function isProcurement(): bool
    {
        return $this->role === 'procurement';
    }
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }
    public function isController(): bool
    {
        return $this->role === 'controller';
    }

    public function roleBadge(): string
    {
        return match ($this->role) {
            'supervisor'   => 'Purchasing Supervisor',
            'procurement'  => 'Procurement',
            'manager'      => 'Purchasing Manager',
            'admin'        => 'Administrator',
            'viewer'       => 'Viewer',
            'controller'   => 'Controller',
            default        => 'Purchasing Staff',
        };
    }

    public function comparisonsCreated()
    {
        return $this->hasMany(VendorComparison::class, 'created_by');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
