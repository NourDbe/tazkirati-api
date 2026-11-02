<?php

namespace App\Listeners;

use App\Events\EventCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendEventCancellationNotifications implements ShouldQueue
{
    public bool $afterCommit = true;
    public string $queue = 'notifications';

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(EventCancelled $event): void
    {
        $eventModel = $event->event;

        $bookingItems = \App\Models\BookingItem::query()
            ->whereHas('ticketType', function ($query) use ($eventModel) {
                $query->where('event_id', $eventModel->id);
            })
            ->whereHas('booking', function ($query) {
                $query->where(
                    'status',
                    \App\Enums\BookingStatus::Paid->value
                );
            })
            ->with('booking.user')
            ->chunkById(500, function ($items) use ($eventModel) {
                foreach ($items as $item) {
                    Log::info('Event cancellation notification queued', [
                        'event_id' => $eventModel->id,
                        'booking_id' => $item->booking_id,
                        'user_email' => $item->booking->user->email,
                    ]);
                }
            });
    }
}
