<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(
        private EventCacheService $eventCache
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return EventResource::collection(
            $this->eventCache->getUpcomingEvents()
        )->additional([
            'message' => 'تم جلب الفعاليات بنجاح.',
        ]);
    }

    public function show(Event $event): EventResource
    {
        abort_unless(
            $event->status === EventStatus::Published,
            404,
            'الفعالية غير موجودة أو غير متاحة.'
        );

        $event->load([
            'venue.media',
            'organizer.media',
            'ticketTypes',
            'media',
        ]);

        return (new EventResource($event))->additional([
            'message' => 'تم جلب تفاصيل الفعالية بنجاح.',
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
            'message' => 'تم جلب عدد التذاكر المتبقية بنجاح.',
            'data' => [
                'event_id' => $event->id,
                'remaining_tickets' => $this->eventCache
                    ->getRemainingTickets($event->id),
            ],
        ]);
    }
}
