<?php

namespace Tests\Feature\Api;

use App\Contracts\BookingServiceInterface;
use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TazkiratiApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Cache::flush();
    }

    private function createPublishedEvent(): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::Published,
            'starts_at' => now()->addDays(5),
        ]);
    }

    private function createBooking(User $user): array
    {
        $event = $this->createPublishedEvent();

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'total_quantity' => 100,
            'reserved_quantity' => 0,
            'sold_quantity' => 0,
            'price' => 25,
            'max_per_booking' => 5,
        ]);

        $booking = app(BookingServiceInterface::class)
            ->createReservation($user, [
                [
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => 2,
                ],
            ]);

        return [$booking, $ticketType, $event];
    }

    public function test_guest_can_view_upcoming_events(): void
    {
        $event = $this->createPublishedEvent();

        $response = $this->getJson('/api/v1/events');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $event->id);
    }

    public function test_guest_cannot_create_booking(): void
    {
        $response = $this->postJson('/api/v1/bookings', [
            'items' => [
                [
                    'ticket_type_id' => 1,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_booking(): void
    {
        $user = User::factory()->create();
        $event = $this->createPublishedEvent();

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'total_quantity' => 100,
            'reserved_quantity' => 0,
            'sold_quantity' => 0,
            'price' => 25,
            'max_per_booking' => 5,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/bookings', [
            'items' => [
                [
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', BookingStatus::Pending->value);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'status' => BookingStatus::Pending->value,
        ]);
    }

    public function test_user_cannot_confirm_another_users_booking(): void
    {
        $owner = User::factory()->create();
        $anotherUser = User::factory()->create();

        [$booking] = $this->createBooking($owner);

        Sanctum::actingAs($anotherUser);

        $response = $this->postJson(
            "/api/v1/bookings/{$booking->id}/confirm"
        );

        $response->assertForbidden();
    }

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        $owner = User::factory()->create();
        $anotherUser = User::factory()->create();

        [$booking] = $this->createBooking($owner);

        Sanctum::actingAs($anotherUser);

        $response = $this->postJson(
            "/api/v1/bookings/{$booking->id}/cancel"
        );

        $response->assertForbidden();
    }

    public function test_organizer_cannot_cancel_another_organizers_event(): void
    {
        $firstOrganizerUser = User::factory()->create();
        $secondOrganizerUser = User::factory()->create();

        $firstOrganizer = Organizer::factory()->create([
            'user_id' => $firstOrganizerUser->id,
        ]);

        $event = Event::factory()->create([
            'organizer_id' => $firstOrganizer->id,
            'status' => EventStatus::Published,
            'starts_at' => now()->addDays(5),
        ]);

        Organizer::factory()->create([
            'user_id' => $secondOrganizerUser->id,
        ]);

        Sanctum::actingAs($secondOrganizerUser);

        $response = $this->postJson(
            "/api/v1/organizer/events/{$event->id}/cancel"
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => EventStatus::Published->value,
        ]);
    }

public function test_validation_errors_use_unified_json_response(): void
{
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/bookings', [
        'items' => [],
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'بيانات الطلب غير صالحة.')
        ->assertJsonStructure([
            'success',
            'message',
            'errors',
        ]);
}

public function test_unauthenticated_api_response_uses_unified_format(): void
{
    $response = $this->postJson('/api/v1/bookings', [
        'items' => [],
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'يجب تسجيل الدخول أولاً.')
        ->assertJsonStructure([
            'success',
            'message',
            'errors',
        ]);
}

public function test_missing_api_route_returns_unified_404_response(): void
{
    $response = $this->getJson('/api/v1/route-that-does-not-exist');

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonStructure([
            'success',
            'message',
            'errors',
        ]);
}

public function test_wrong_api_http_method_returns_unified_405_response(): void
{
    $response = $this->putJson('/api/v1/events');

    $response->assertStatus(405)
        ->assertJsonPath('success', false)
        ->assertJsonStructure([
            'success',
            'message',
            'errors',
        ]);
}

}
