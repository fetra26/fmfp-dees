<?php

namespace App\Policies;

use App\Models\Paiement;
use App\Models\User;

class PaiementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->peutVoirPaiement();
    }

    public function view(User $user, Paiement $paiement): bool
    {
        return $user->peutVoirPaiement();
    }

    // Saisie réservée au DAF (règle métier critique)
    public function create(User $user): bool
    {
        return $user->peutEditerPaiement();
    }

    public function update(User $user, Paiement $paiement): bool
    {
        return $user->peutEditerPaiement();
    }

    public function delete(User $user, Paiement $paiement): bool
    {
        return $user->peutEditerPaiement();
    }

    public function restore(User $user, Paiement $paiement): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Paiement $paiement): bool
    {
        return $user->isSuperAdmin();
    }
}
