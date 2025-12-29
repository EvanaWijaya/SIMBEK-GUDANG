<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'alamat',
        'kota',
        'provinsi',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relationships
     */

    // User melakukan produksi
    public function productions()
    {
        return $this->hasMany(Production::class);
    }

    // User melakukan penjualan
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    // User melakukan disposal
    public function disposals()
    {
        return $this->hasMany(StockDisposal::class);
    }

    // Activity log user
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Scopes
     */

    // Scope untuk admin inventory
    public function scopeAdminInventory($query)
    {
        return $query->where('role', 'admin_inventory');
    }

    // Scope untuk owner inventory
    public function scopeOwnerInventory($query)
    {
        return $query->where('role', 'owner_inventory');
    }

    /**
     * Accessors
     */

    // Check apakah user adalah admin
    public function isAdmin(): bool
    {
        return $this->role === 'admin_inventory';
    }

    // Check apakah user adalah owner
    public function isOwner(): bool
    {
        return $this->role === 'owner_inventory';
    }

    // Get full address
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([$this->alamat, $this->kota, $this->provinsi]);
        return implode(', ', $parts);
    }
}