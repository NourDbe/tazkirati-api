<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Services\NotificationDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBulkAnnouncementJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $eventId,
        public string $message,
        public string $channel = 'email'
    ) {
        $this->onQueue('announcements');
    }

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(NotificationDeliveryService $delivery): void
    {
        if (!in_array($this->channel, ['email', 'sms'], true)) {
            throw new \InvalidArgumentException('Unsupported notification channel.');
        }

        $event = Event::find($this->eventId);

        if (!$event) {
            return;
        }

        Booking::query()
            ->where('status', BookingStatus::Paid->value)
            ->whereHas('items.ticketType', function ($query) {
                $query->where('event_id', $this->eventId);
            })
            ->with('user')
            ->chunkById(500, function ($bookings) use ($delivery, $event) {
                foreach ($bookings as $booking) {
                    $user = $booking->user;

                    if (!$user) {
                        continue;
                    }

                    $recipient = $this->channel === 'email'
                        ? $user->email
                        : $user->phone;

                    if (!$recipient) {
                        continue;
                    }

                    $delivery->send(
                        channel: $this->channel,
                        recipient: $recipient,
                        purpose: 'event_announcement',
                        idempotencyKey: "event:{$event->id}:booking:{$booking->id}:announcement:{$this->channel}",
                        context: [
                            'event_id' => $event->id,
                            'booking_id' => $booking->id,
                            'user_id' => $user->id,
                            'message' => $this->message,
                        ]
                    );
                }
            });
    }
}
