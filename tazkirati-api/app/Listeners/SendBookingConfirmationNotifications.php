<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendBookingConfirmationNotifications implements ShouldQueue
{
    public bool $afterCommit = true;
    public string $queue = 'notifications';

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking->loadMissing([
            'user',
            'items.ticketType.event.organizer.user',
        ]);

        Log::info('Booking confirmation notification queued', [
            'booking_id' => $booking->id,
            'customer_email' => $booking->user->email,
        ]);

        // سنربط Email وSMS Jobs في مرحلة الإشعارات.
    }
}
