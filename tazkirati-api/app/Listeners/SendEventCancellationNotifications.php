<?php

namespace App\Listeners;

use App\Events\EventCancelled;
use App\Jobs\SendBulkAnnouncementJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEventCancellationNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public bool $afterCommit = true;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(EventCancelled $event): void
    {
        $message = "تم إلغاء فعالية: {$event->event->title}";

        SendBulkAnnouncementJob::dispatch(
            $event->event->id,
            $message,
            'email'
        );

        SendBulkAnnouncementJob::dispatch(
            $event->event->id,
            $message,
            'sms'
        );
    }
}
