<?php

namespace Database\Factories;

use App\Enums\TicketType as TicketCategory;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement([
                'Regular',
                'VIP',
                'Early Bird',
            ]),
            'category' => fake()->randomElement([
                TicketCategory::Regular,
                TicketCategory::VIP,
            ]),
            'price' => fake()->randomFloat(2, 10, 500),
            'total_quantity' => 100,
            'reserved_quantity' => 0,
            'sold_quantity' => 0,
            'max_per_booking' => 5,
        ];
    }
}
