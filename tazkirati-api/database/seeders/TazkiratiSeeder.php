<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Enums\TicketType as TicketCategory;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TazkiratiSeeder extends Seeder
{
    public function run(): void
    {
        // Create organizers and their users
        $organizers = collect();

        for ($i = 1; $i <= 3; $i++) {
            $user = User::factory()->create([
                'name' => "Organizer {$i}",
                'email' => "organizer{$i}@tazkirati.test",
                'password' => Hash::make('password'),
            ]);

            $organizers->push(
                Organizer::factory()->create([
                    'user_id' => $user->id,
                ])
            );
        }

        // Create venues
        $venues = Venue::factory()->count(5)->create();

        // Create events and ticket types
        $events = collect();

        foreach (range(1, 10) as $i) {
            $event = Event::factory()->create([
                'organizer_id' => $organizers->random()->id,
                'venue_id' => $venues->random()->id,
                'title' => "Tazkirati Event {$i}",
                'status' => EventStatus::Published,
            ]);

            TicketType::factory()->create([
                'event_id' => $event->id,
                'name' => 'Regular',
                'category' => TicketCategory::Regular,
                'price' => 25,
                'total_quantity' => 5000,
            ]);

            TicketType::factory()->create([
                'event_id' => $event->id,
                'name' => 'VIP',
                'category' => TicketCategory::VIP,
                'price' => 75,
                'total_quantity' => 1000,
            ]);

            $events->push($event);
        }

        // Create a dedicated event for bulk announcement testing
        $bulkEvent = $events->first();

        $bulkTicketType = $bulkEvent->ticketTypes()
            ->where('category', TicketCategory::Regular->value)
            ->firstOrFail();

        // Create 2000 paid bookings in chunks
        foreach (range(1, 2000) as $i) {
            $customer = User::factory()->create();

            $booking = Booking::create([
                'user_id' => $customer->id,
                'status' => BookingStatus::Paid,
                'total_amount' => 25,
                'expires_at' => null,
                'paid_at' => now(),
                'refunded_at' => null,
            ]);

            $bookingItem = BookingItem::create([
                'booking_id' => $booking->id,
                'ticket_type_id' => $bulkTicketType->id,
                'quantity' => 1,
                'unit_price' => 25,
                'subtotal' => 25,
            ]);

            Ticket::create([
                'booking_item_id' => $bookingItem->id,
                'code' => (string) Str::uuid(),
                'file_path' => null,
            ]);

            // Release memory periodically
            if ($i % 100 === 0) {
                unset($customer, $booking, $bookingItem);
            }
        }

        // Update inventory after creating paid tickets
        $bulkTicketType->update([
            'sold_quantity' => 2000,
        ]);
    }
}
