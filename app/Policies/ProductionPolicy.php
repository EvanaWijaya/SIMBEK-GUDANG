<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Production;

class ProductionPolicy
{
    /**
     * Determine if the user can view any productions.
     */
    public function viewAny(User $user): bool
    {
        // Admin & Owner bisa lihat list
        return in_array($user->role, ['admin_inventory', 'owner_inventory']);
    }

    /**
     * Determine if the user can view the production.
     */
    public function view(User $user, Production $production): bool
    {
        // Admin & Owner bisa lihat detail
        return in_array($user->role, ['admin_inventory', 'owner_inventory']);
    }

    /**
     * Determine if the user can create productions.
     */
    public function create(User $user): bool
    {
        // Hanya Admin yang bisa create produksi
        return $user->role === 'admin_inventory';
    }

    /**
     * Determine if the user can update the production.
     */
    public function update(User $user, Production $production): bool
    {
        // Hanya Admin yang bisa update
        // Dan hanya bisa update kalau status masih pending
        return $user->role === 'admin_inventory' && $production->status === 'pending';
    }

    /**
     * Determine if the user can delete the production.
     */
    public function delete(User $user, Production $production): bool
    {
        // Hanya Admin yang bisa delete
        // Dan hanya bisa delete kalau status pending atau batal
        return $user->role === 'admin_inventory' 
            && in_array($production->status, ['pending', 'batal']);
    }

    /**
     * Determine if the user can cancel the production.
     */
    public function cancel(User $user, Production $production): bool
    {
        // Hanya Admin yang bisa cancel
        // Dan hanya bisa cancel kalau status pending
        return $user->role === 'admin_inventory' && $production->status === 'pending';
    }
}