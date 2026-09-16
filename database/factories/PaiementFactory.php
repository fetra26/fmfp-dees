<?php

namespace Database\Factories;

use App\Models\Paiement;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaiementFactory extends Factory
{
    protected $model = Paiement::class;

    public function definition(): array
    {
        return [
            'porteur_proj_id' => PorteurProjFactory::new(),
            'ligne'           => 'J1',
            'date_paiement'   => now()->toDateString(),
            // Montants en Ariary entiers : la colonne est un bigint unsigned,
            // sans décimales ni valeurs négatives possibles.
            'montant'         => 1_000_000,
            'is_annule'       => false,
        ];
    }

    public function annule(): static
    {
        return $this->state(fn () => ['is_annule' => true]);
    }

    public function tranche(string $ligne, int $montant): static
    {
        return $this->state(fn () => ['ligne' => $ligne, 'montant' => $montant]);
    }
}
