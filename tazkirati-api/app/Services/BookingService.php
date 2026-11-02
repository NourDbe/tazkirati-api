<?php

namespace App\Services;

use App\Contracts\BookingServiceInterface;
use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Events\BookingConfirmed;
use App\Jobs\ExpireBookingJob;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService implements BookingServiceInterface
{
    public function __construct(
        private EventCacheService $eventCache
    ) {}

    public function createReservation(User $user, array $items): Booking
    {
        $booking = DB::transaction(function () use ($user, $items) {
            $booking = Booking::create([
                'user_id' => $user->id,
                'status' => BookingStatus::Pending,
                'total_amount' => 0,
                'expires_at' => now()->addMinutes(10),
            ]);

            $totalAmount = 0;

            foreach ($items as $item) {
                $ticketType = TicketType::query()
                    ->with('event')
                    ->findOrFail($item['ticket_type_id']);

                $quantity = (int) $item['quantity'];

                if (
                    $ticketType->event->status !== EventStatus::Published ||
                    $ticketType->event->starts_at <= now()
                ) {
                    throw ValidationException::withMessages([
                        'event' => 'الفعالية غير متاحة للحجز.',
                    ]);
                }

                if ($quantity > $ticketType->max_per_booking) {
                    throw ValidationException::withMessages([
                        'quantity' => 'تجاوزت الحد الأقصى للتذاكر المسموح بها في الحجز.',
                    ]);
                }

                $updated = TicketType::query()
                    ->whereKey($ticketType->id)
                    ->whereRaw(
                        '(total_quantity - reserved_quantity - sold_quantity) >= ?',
                        [$quantity]
                    )
                    ->increment('reserved_quantity', $quantity);

                if ($updated === 0) {
                    throw ValidationException::withMessages([
                        'ticket_type_id' => 'عدد التذاكر المتاحة غير كافٍ.',
                    ]);
                }

                $unitPrice = $ticketType->price;
                $subtotal = $unitPrice * $quantity;

                BookingItem::create([
                    'booking_id' => $booking->id,
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $totalAmount += $subtotal;
            }

            $booking->update([
                'total_amount' => $totalAmount,
            ]);

            return $booking->load('items.ticketType');
        });

        // إبطال كاش المخزون للفعاليات المتأثرة.
        $this->forgetBookingEventsCache($booking);

        // جدولة انتهاء الحجز بعد 10 دقائق من إنشائه.
        ExpireBookingJob::dispatch($booking->id)
            ->delay($booking->expires_at);

        return $booking;
    }

    public function confirmPayment(Booking $booking): Booking
    {
        $paidBooking = DB::transaction(function () use ($booking) {
            $booking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($booking->status !== BookingStatus::Pending) {
                throw ValidationException::withMessages([
                    'booking' => 'هذا الحجز غير قابل لتأكيد الدفع.',
                ]);
            }

            if (
                $booking->expires_at === null ||
                $booking->expires_at->isPast()
            ) {
                throw ValidationException::withMessages([
                    'booking' => 'انتهت صلاحية الحجز.',
                ]);
            }

            $booking->load('items.ticketType');

            foreach ($booking->items as $item) {
                $ticketType = TicketType::query()
                    ->findOrFail($item->ticket_type_id);

                $ticketType->decrement(
                    'reserved_quantity',
                    $item->quantity
                );

                $ticketType->increment(
                    'sold_quantity',
                    $item->quantity
                );

                for ($i = 0; $i < $item->quantity; $i++) {
                    Ticket::create([
                        'booking_item_id' => $item->id,
                        'code' => (string) Str::uuid(),
                    ]);
                }
            }

            $booking->update([
                'status' => BookingStatus::Paid,
                'paid_at' => now(),
            ]);

            return $booking->fresh('items.ticketType');
        });

        // إبطال الكاش بعد نجاح الدفع.
        $this->forgetBookingEventsCache($paidBooking);

        // إطلاق الحدث بعد إتمام المعاملة.
        BookingConfirmed::dispatch($paidBooking);

        return $paidBooking;
    }

    public function cancelReservation(Booking $booking): Booking
    {
        $cancelledBooking = DB::transaction(function () use ($booking) {
            $booking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($booking->status !== BookingStatus::Pending) {
                throw ValidationException::withMessages([
                    'booking' => 'لا يمكن إلغاء هذا الحجز.',
                ]);
            }

            $booking->load('items.ticketType');

            foreach ($booking->items as $item) {
                TicketType::query()
                    ->whereKey($item->ticket_type_id)
                    ->decrement('reserved_quantity', $item->quantity);
            }

            $booking->update([
                'status' => BookingStatus::Expired,
            ]);

            return $booking->fresh('items.ticketType');
        });

        // إبطال الكاش بعد تحرير التذاكر.
        $this->forgetBookingEventsCache($cancelledBooking);

        return $cancelledBooking;
    }

    /**
     * إبطال كاش جميع الفعاليات المرتبطة بعناصر الحجز.
     */
    private function forgetBookingEventsCache(Booking $booking): void
    {
        foreach ($booking->items as $item) {
            $this->eventCache->forgetEvent(
                $item->ticketType->event_id
            );
        }
    }
}
