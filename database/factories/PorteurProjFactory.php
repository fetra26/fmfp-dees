<?php

namespace Database\Factories;

use App\Models\Porteur;
use App\Models\PorteurProj;
use App\Models\Projet;
use Illuminate\Database\Eloquent\Factories\Factory;

class PorteurProjFactory extends Factory
{
    protected $model = PorteurProj::class;

    public function definition(): array
    {
        return [
            'projet_id'     => ProjetFactory::new(),
            'porteur_id'    => PorteurFactory::new(),
            'montant_total' => 10_000_000,
        ];
    }
}
