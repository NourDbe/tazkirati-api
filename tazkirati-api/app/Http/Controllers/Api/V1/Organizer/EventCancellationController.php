<?php

namespace App\Http\Controllers\Api\V1\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelEventRequest;
use App\Models\Event;
use App\Services\EventCancellationService;
use Illuminate\Http\JsonResponse;

class EventCancellationController extends Controller
{
    public function __construct(
        private EventCancellationService $cancellationService
    ) {}

    public function store(
        CancelEventRequest $request,
        Event $event
    ): JsonResponse {
        $cancelledEvent = $this->cancellationService->cancel($event);

        return response()->json([
            'message' => 'تم إلغاء الفعالية بنجاح.',
            'data' => $cancelledEvent,
        ]);
    }
}
