<?php

namespace App\Jobs;

use App\Contracts\BookingServiceInterface;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireBookingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $bookingId
    ) {}

    public function handle(BookingServiceInterface $bookingService): void
    {
        $booking = Booking::find($this->bookingId);

        if (!$booking) {
            return;
        }

        // لا نحرر المخزون إلا إذا انتهت مهلة حجز ما زال Pending.
        if (
            $booking->status !== BookingStatus::Pending ||
            !$booking->expires_at ||
            $booking->expires_at->isFuture()
        ) {
            return;
        }

        $bookingService->cancelReservation($booking);
    }
}
