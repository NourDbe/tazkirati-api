<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Services\NotificationDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_notification_is_logged_as_sent(): void
    {
        config(['services.tazkirati_notifications.email_fail' => false]);

        Log::shouldReceive('info')->once();

        app(NotificationDeliveryService::class)->send(
            'email',
            'customer@example.com',
            'booking_confirmation',
            'test-booking-email-1'
        );

        $this->assertDatabaseHas('notification_logs', [
            'idempotency_key' => 'test-booking-email-1',
            'status' => 'sent',
        ]);
    }

    public function test_sent_notification_is_not_sent_twice(): void
    {
        config(['services.tazkirati_notifications.email_fail' => false]);

        Log::shouldReceive('info')->once();

        $service = app(NotificationDeliveryService::class);

        $service->send(
            'email',
            'customer@example.com',
            'booking_confirmation',
            'test-booking-email-2'
        );

        $service->send(
            'email',
            'customer@example.com',
            'booking_confirmation',
            'test-booking-email-2'
        );

        $this->assertDatabaseCount('notification_logs', 1);
    }

    public function test_failed_email_provider_is_recorded_and_exception_is_thrown(): void
    {
        config(['services.tazkirati_notifications.email_fail' => true]);

        try {
            app(NotificationDeliveryService::class)->send(
                'email',
                'customer@example.com',
                'booking_confirmation',
                'test-booking-email-3'
            );

            $this->fail('Expected provider failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'email provider failure',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('notification_logs', [
            'idempotency_key' => 'test-booking-email-3',
            'status' => 'failed',
        ]);
    }

    public function test_failed_notification_can_be_retried(): void
    {
        $service = app(NotificationDeliveryService::class);

        config(['services.tazkirati_notifications.email_fail' => true]);

        try {
            $service->send(
                'email',
                'customer@example.com',
                'booking_confirmation',
                'test-booking-email-4'
            );
        } catch (RuntimeException) {
            // Expected simulated failure.
        }

        config(['services.tazkirati_notifications.email_fail' => false]);

        Log::shouldReceive('info')->once();

        $service->send(
            'email',
            'customer@example.com',
            'booking_confirmation',
            'test-booking-email-4'
        );

        $this->assertDatabaseHas('notification_logs', [
            'idempotency_key' => 'test-booking-email-4',
            'status' => 'sent',
        ]);
    }
}
