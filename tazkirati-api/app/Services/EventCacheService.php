<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Support\Facades\Cache;

class EventCacheService
{
    private const UPCOMING_EVENTS_KEY = 'events:upcoming';

    public function getRemainingTickets(int $eventId): int
    {
        return Cache::remember(
            "event:{$eventId}:remaining_tickets",
            now()->addMinutes(5),
            function () use ($eventId) {
                return (int) TicketType::query()
                    ->where('event_id', $eventId)
                    ->selectRaw(
                        'COALESCE(SUM(total_quantity - reserved_quantity - sold_quantity), 0) as remaining'
                    )
                    ->value('remaining');
            }
        );
    }

    public function getUpcomingEvents()
    {
        return Cache::remember(
            self::UPCOMING_EVENTS_KEY,
            now()->addMinutes(10),
            fn () => Event::query()
                ->where('status', 'published')
                ->where('starts_at', '>', now())
                ->with(['venue', 'organizer'])
                ->orderBy('starts_at')
                ->limit(20)
                ->get()
        );
    }

    public function forgetEvent(int $eventId): void
    {
        Cache::forget("event:{$eventId}:remaining_tickets");
        Cache::forget(self::UPCOMING_EVENTS_KEY);
    }

    public function forgetUpcomingEvents(): void
    {
        Cache::forget(self::UPCOMING_EVENTS_KEY);
    }
}
