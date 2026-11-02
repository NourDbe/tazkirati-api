<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\NotificationDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBookingEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $bookingId
    ) {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(NotificationDeliveryService $delivery): void
    {
        $booking = Booking::with('user')->find($this->bookingId);

        if (!$booking || !$booking->user) {
            return;
        }

        $delivery->send(
            channel: 'email',
            recipient: $booking->user->email,
            purpose: 'booking_confirmation',
            idempotencyKey: "booking:{$booking->id}:confirmation:email",
            context: [
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
            ]
        );
    }
}
