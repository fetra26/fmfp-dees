<?php

namespace Database\Factories;

use App\Models\Projet;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjetFactory extends Factory
{
    protected $model = Projet::class;

    public function definition(): array
    {
        return [
            'reference' => 'PROJ-' . fake()->unique()->numberBetween(1000, 9999),
            'intitule'  => 'Formation ' . fake()->words(3, true),
        ];
    }
}
