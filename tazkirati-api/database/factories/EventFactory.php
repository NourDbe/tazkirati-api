<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Organizer;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+6 months');

        return [
            'organizer_id' => Organizer::factory(),
            'venue_id' => Venue::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'event_type' => fake()->randomElement([
                'concert',
                'conference',
                'theater',
                'workshop',
                'exhibition',
            ]),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+3 hours'),
            'status' => EventStatus::Published,
        ];
    }
}
