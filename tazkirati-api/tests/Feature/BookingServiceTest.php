<?php

namespace Tests\Feature;

use App\Contracts\BookingServiceInterface;
use App\Enums\BookingStatus;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingServiceInterface $bookingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingService = app(BookingServiceInterface::class);
    }

    public function test_user_can_create_temporary_reservation(): void
    {
        $user = User::factory()->create();

        $ticketType = TicketType::factory()->create([
            'total_quantity' => 100,
            'reserved_quantity' => 0,
            'sold_quantity' => 0,
            'price' => 25,
            'max_per_booking' => 5,
        ]);

        $booking = $this->bookingService->createReservation(
            $user,
            [
                [
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => 2,
                ],
            ]
        );

        $this->assertEquals(BookingStatus::Pending, $booking->status);
        $this->assertEquals(50, (float) $booking->total_amount);
        $this->assertNotNull($booking->expires_at);

        $this->assertEquals(
            2,
            $ticketType->fresh()->reserved_quantity
        );

        $this->assertEquals(0, $ticketType->fresh()->sold_quantity);
    }

    public function test_reservation_is_rejected_when_stock_is_insufficient(): void
    {
        $this->expectException(ValidationException::class);

        $user = User::factory()->create();

        $ticketType = TicketType::factory()->create([
            'total_quantity' => 2,
            'reserved_quantity' => 0,
            'sold_quantity' => 0,
            'max_per_booking' => 5,
        ]);

        $this->bookingService->createReservation($user, [
            [
                'ticket_type_id' => $ticketType->id,
                'quantity' => 3,
            ],
        ]);
    }

    public function test_payment_confirms_booking_and_creates_tickets(): void
    {
        $user = User::factory()->create();

        $ticketType = TicketType::factory()->create([
            'total_quantity' => 100,
            'reserved_quantity' => 0,
            'sold_quantity' => 0,
        ]);

        $booking = $this->bookingService->createReservation($user, [
            [
                'ticket_type_id' => $ticketType->id,
                'quantity' => 2,
            ],
        ]);

        $paidBooking = $this->bookingService->confirmPayment($booking);

        $this->assertEquals(BookingStatus::Paid, $paidBooking->status);
        $this->assertNotNull($paidBooking->paid_at);

        $this->assertEquals(0, $ticketType->fresh()->reserved_quantity);
        $this->assertEquals(2, $ticketType->fresh()->sold_quantity);

        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_cancelling_reservation_releases_reserved_stock(): void
    {
        $user = User::factory()->create();

        $ticketType = TicketType::factory()->create([
            'total_quantity' => 100,
            'reserved_quantity' => 0,
            'sold_quantity' => 0,
        ]);

        $booking = $this->bookingService->createReservation($user, [
            [
                'ticket_type_id' => $ticketType->id,
                'quantity' => 3,
            ],
        ]);

        $cancelledBooking = $this->bookingService->cancelReservation($booking);

        $this->assertEquals(
            BookingStatus::Expired,
            $cancelledBooking->status
        );

        $this->assertEquals(
            0,
            $ticketType->fresh()->reserved_quantity
        );
    }

    public function test_paid_booking_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();

        $ticketType = TicketType::factory()->create([
            'total_quantity' => 100,
            'reserved_quantity' => 0,
            'sold_quantity' => 0,
        ]);

        $booking = $this->bookingService->createReservation($user, [
            [
                'ticket_type_id' => $ticketType->id,
                'quantity' => 2,
            ],
        ]);

        $paidBooking = $this->bookingService->confirmPayment($booking);

        $result = $this->bookingService->cancelReservation($paidBooking);

        $this->assertEquals(BookingStatus::Paid, $result->status);
        $this->assertEquals(2, $ticketType->fresh()->sold_quantity);
        $this->assertEquals(0, $ticketType->fresh()->reserved_quantity);
        $this->assertDatabaseCount('tickets', 2);
    }
}
