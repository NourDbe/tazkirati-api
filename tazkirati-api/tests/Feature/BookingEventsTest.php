<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Events\BookingConfirmed;
use App\Events\EventCancelled;
use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Services\BookingService;
use App\Services\EventCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class BookingEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_confirmed_event_is_dispatched_after_payment(): void
    {
        EventFacade::fake([BookingConfirmed::class]);

        $user = User::factory()->create();

        $ticketType = TicketType::factory()->create([
            'total_quantity' => 100,
            'reserved_quantity' => 0,
            'sold_quantity' => 0,
        ]);

        $service = app(BookingService::class);

        $booking = $service->createReservation($user, [
            [
                'ticket_type_id' => $ticketType->id,
                'quantity' => 1,
            ],
        ]);

        $service->confirmPayment($booking);

        EventFacade::assertDispatched(
            BookingConfirmed::class,
            fn ($event) => $event->booking->id === $booking->id
        );
    }

    public function test_event_cancelled_event_is_dispatched(): void
    {
        EventFacade::fake([EventCancelled::class]);

        $event = Event::factory()->create([
            'status' => EventStatus::Published,
        ]);

        $cancelledEvent = app(EventCancellationService::class)
            ->cancel($event);

        $this->assertEquals(
            EventStatus::Cancelled,
            $cancelledEvent->status
        );

        EventFacade::assertDispatched(
            EventCancelled::class,
            fn ($event) => $event->event->id === $cancelledEvent->id
        );
    }
}
