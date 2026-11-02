<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Jobs\SendBookingEmailJob;
use App\Jobs\SendBookingSmsJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingConfirmationNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public bool $afterCommit = true;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(BookingConfirmed $event): void
    {
        SendBookingEmailJob::dispatch($event->booking->id);
        SendBookingSmsJob::dispatch($event->booking->id);
    }
}
