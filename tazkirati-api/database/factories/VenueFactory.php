<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class VenueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Hall',
            'city' => fake()->randomElement([
                'Damascus',
                'Latakia',
                'Aleppo',
                'Homs',
                'Tartus',
            ]),
            'address' => fake()->address(),
            'capacity' => fake()->numberBetween(100, 10000),
        ];
    }
}
