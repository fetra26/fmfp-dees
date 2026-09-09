<?php

namespace App\Models;

/**
 * Alias de compatibilité vers le modèle Porteur.
 * La table s'appelle désormais 'porteur' (anciennement 'entreprise').
 */
class Entreprise extends Porteur
{
    // Hérite de tout Porteur — même table, mêmes relations
}
