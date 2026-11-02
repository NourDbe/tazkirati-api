<?php

namespace App\Services;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NotificationDeliveryService
{
    public function send(
        string $channel,
        string $recipient,
        string $purpose,
        string $idempotencyKey,
        array $context = []
    ): void {
        $log = NotificationLog::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'booking_id' => $context['booking_id'] ?? null,
                'event_id' => $context['event_id'] ?? null,
                'user_id' => $context['user_id'] ?? null,
                'channel' => $channel,
                'purpose' => $purpose,
                'recipient' => $recipient,
                'status' => 'pending',
            ]
        );

        // إذا أُرسل الإشعار سابقاً، لا نرسله مرة أخرى.
        if ($log->status === 'sent') {
            return;
        }

        $failureEnabled = (bool) config(
            "services.tazkirati_notifications.{$channel}_fail",
            false
        );

        if ($failureEnabled) {
            $log->update([
                'status' => 'failed',
                'error' => "Simulated {$channel} provider failure.",
            ]);

            throw new RuntimeException(
                "Simulated {$channel} provider failure."
            );
        }

        // محاكاة الإرسال حالياً، ويمكن استبدالها بمزوّد حقيقي لاحقاً.
        Log::info("Tazkirati {$channel} notification sent", [
            'recipient' => $recipient,
            'purpose' => $purpose,
            'context' => $context,
        ]);

        $log->update([
            'status' => 'sent',
            'error' => null,
            'sent_at' => now(),
        ]);
    }
}
