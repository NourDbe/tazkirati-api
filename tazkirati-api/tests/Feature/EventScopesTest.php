<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Scopes\PublishedEventScope;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_scope_hides_unpublished_events(): void
    {
        $publishedEvent = Event::factory()->create([
            'status' => EventStatus::Published,
        ]);

        $draftEvent = Event::factory()->create([
            'status' => EventStatus::Draft,
        ]);

        $this->assertNotNull(Event::find($publishedEvent->id));

        $this->assertNull(Event::find($draftEvent->id));
    }

    public function test_global_scope_can_be_bypassed_when_needed(): void
    {
        $draftEvent = Event::factory()->create([
            'status' => EventStatus::Draft,
        ]);

        $event = Event::withoutGlobalScope(PublishedEventScope::class)
            ->find($draftEvent->id);

        $this->assertNotNull($event);
        $this->assertEquals($draftEvent->id, $event->id);
    }

    public function test_local_scopes_can_be_combined(): void
    {
        $venue = Venue::factory()->create([
            'city' => 'Latakia',
        ]);

        $matchingEvent = Event::factory()->create([
            'venue_id' => $venue->id,
            'status' => EventStatus::Published,
            'event_type' => 'concert',
            'starts_at' => now()->addDays(5),
        ]);

        $otherCityVenue = Venue::factory()->create([
            'city' => 'Damascus',
        ]);

        Event::factory()->create([
            'venue_id' => $otherCityVenue->id,
            'status' => EventStatus::Published,
            'event_type' => 'concert',
            'starts_at' => now()->addDays(5),
        ]);

        Event::factory()->create([
            'venue_id' => $venue->id,
            'status' => EventStatus::Published,
            'event_type' => 'theatre',
            'starts_at' => now()->addDays(5),
        ]);

        Event::factory()->create([
            'venue_id' => $venue->id,
            'status' => EventStatus::Published,
            'event_type' => 'concert',
            'starts_at' => now()->subDays(2),
        ]);

        $events = Event::query()
            ->inCity('Latakia')
            ->upcoming()
            ->ofType('concert')
            ->get();

        $this->assertCount(1, $events);
        $this->assertEquals($matchingEvent->id, $events->first()->id);
    }
}
