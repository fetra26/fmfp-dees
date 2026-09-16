<?php

namespace Database\Factories;

use App\Models\Porteur;
use Illuminate\Database\Eloquent\Factories\Factory;

class PorteurFactory extends Factory
{
    protected $model = Porteur::class;

    public function definition(): array
    {
        return [
            'raison_sociale' => fake()->unique()->company(),
        ];
    }
}
