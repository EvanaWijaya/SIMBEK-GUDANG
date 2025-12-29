<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Material;

class MaterialPolicy
{
    /**
     * Determine if the user can view any materials.
     */
    public function viewAny(User $user): bool
    {
        // Admin & Owner bisa lihat list
        return in_array($user->role, ['admin_inventory', 'owner_inventory']);
    }

    /**
     * Determine if the user can view the material.
     */
    public function view(User $user, Material $material): bool
    {
        // Admin & Owner bisa lihat detail
        return in_array($user->role, ['admin_inventory', 'owner_inventory']);
    }

    /**
     * Determine if the user can create materials.
     */
    public function create(User $user): bool
    {
        // Hanya Admin yang bisa create
        return $user->role === 'admin_inventory';
    }

    /**
     * Determine if the user can update the material.
     */
    public function update(User $user, Material $material): bool
    {
        // Hanya Admin yang bisa update
        return $user->role === 'admin_inventory';
    }

    /**
     * Determine if the user can delete the material.
     */
    public function delete(User $user, Material $material): bool
    {
        // Hanya Admin yang bisa delete
        // Dan material tidak boleh dipakai di formula yang aktif
        $isUsedInFormula = $material->formulaDetails()->whereHas('formula', function($query) {
            $query->where('is_active', true);
        })->exists();

        return $user->role === 'admin_inventory' && !$isUsedInFormula;
    }
}