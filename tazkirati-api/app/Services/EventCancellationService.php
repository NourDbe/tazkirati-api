<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Events\EventCancelled;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Scopes\PublishedEventScope;

class EventCancellationService
{
    public function cancel(Event $event): Event
    {
        $cancelledEvent = DB::transaction(function () use ($event) {
            $event = Event::withoutGlobalScope(PublishedEventScope::class)
            ->whereKey($event->id)
            ->lockForUpdate()
            ->firstOrFail();

            if ($event->status === EventStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'event' => 'الفعالية ملغاة مسبقاً.',
                ]);
            }

            $event->update([
                'status' => EventStatus::Cancelled,
            ]);

            return $event->fresh();
        });

        EventCancelled::dispatch($cancelledEvent);

        return $cancelledEvent;
    }
}
