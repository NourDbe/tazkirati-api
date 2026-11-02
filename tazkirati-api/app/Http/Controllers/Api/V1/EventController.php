<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventCacheService;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    public function __construct(
        private EventCacheService $eventCache
    ) {}

    public function index(): JsonResponse
    {
        $events = $this->eventCache->getUpcomingEvents();

        return response()->json([
            'message' => 'تم جلب الفعاليات بنجاح.',
            'data' => $events,
        ]);
    }

    public function show(Event $event): JsonResponse
    {
        abort_unless(
            $event->status === EventStatus::Published,
            404,
            'الفعالية غير موجودة أو غير متاحة.'
        );

        $event->load([
            'venue',
            'organizer',
            'ticketTypes',
            'media',
        ]);

        return response()->json([
            'message' => 'تم جلب تفاصيل الفعالية بنجاح.',
            'data' => $event,
        ]);
    }

    public function remainingTickets(Event $event): JsonResponse
    {
        abort_unless(
            $event->status === EventStatus::Published,
            404,
            'الفعالية غير موجودة أو غير متاحة.'
        );

        return response()->json([
            'event_id' => $event->id,
            'remaining_tickets' => $this->eventCache
                ->getRemainingTickets($event->id),
        ]);
    }
}
