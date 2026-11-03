<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

class EventCacheService
{
    private const UPCOMING_EVENTS_VERSION_KEY = 'events:upcoming:version';

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

    public function getUpcomingEvents(int $perPage = 10): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        $version = Cache::get(
            self::UPCOMING_EVENTS_VERSION_KEY,
            1
        );

        $cacheKey = "events:upcoming:v{$version}:per_page:{$perPage}:page:{$page}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(10),
            fn () => Event::query()
                ->where('status', 'published')
                ->where('starts_at', '>', now())
                ->with([
                    'venue',
                    'organizer',
                    'ticketTypes',
                    'media',
                ])
                ->orderBy('starts_at')
                ->paginate($perPage)
        );
    }

    public function forgetEvent(int $eventId): void
    {
        Cache::forget("event:{$eventId}:remaining_tickets");

        $this->forgetUpcomingEvents();
    }

    public function forgetUpcomingEvents(): void
    {
        Cache::add(self::UPCOMING_EVENTS_VERSION_KEY, 1);

        Cache::increment(self::UPCOMING_EVENTS_VERSION_KEY);
    }
}
