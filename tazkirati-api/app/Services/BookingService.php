<?php

namespace App\Services;

use App\Contracts\BookingServiceInterface;
use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Models\Booking;
use App\Models\Ticket;
use App\Models\TicketType as TicketTypeModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Jobs\ExpireBookingJob;
use App\Events\BookingConfirmed;

class BookingService implements BookingServiceInterface
{
    /**
     * إنشاء حجز مؤقت لمدة 10 دقائق.
     */
    public function createReservation(User $user, array $items): Booking
    {
        $booking = DB::transaction(function () use ($user, $items)  {

            $booking = Booking::create([
                'user_id' => $user->id,
                'status' => BookingStatus::Pending,
                'total_amount' => 0,
                'expires_at' => now()->addMinutes(10),
            ]);

            $totalAmount = 0;

            foreach ($items as $item) {
                $quantity = (int) $item['quantity'];

                if ($quantity < 1) {
                    throw ValidationException::withMessages([
                        'quantity' => 'يجب أن تكون الكمية أكبر من صفر.',
                    ]);
                }

                $ticketType = TicketTypeModel::with('event')
                    ->findOrFail($item['ticket_type_id']);

                if (
                    $ticketType->event->status !== EventStatus::Published ||
                    $ticketType->event->starts_at->isPast()
                ) {
                    throw ValidationException::withMessages([
                        'event' => 'الفعالية غير متاحة للحجز.',
                    ]);
                }

                if ($quantity > $ticketType->max_per_booking) {
                    throw ValidationException::withMessages([
                        'quantity' => 'تجاوزت الحد الأقصى للتذاكر في الحجز الواحد.',
                    ]);
                }

                // حجز الكمية بشكل ذري لمنع تجاوز المخزون.
                $reserved = TicketTypeModel::query()
                    ->whereKey($ticketType->id)
                    ->whereRaw(
                        '(total_quantity - reserved_quantity - sold_quantity) >= ?',
                        [$quantity]
                    )
                    ->increment('reserved_quantity', $quantity);

                if ($reserved === 0) {
                    throw ValidationException::withMessages([
                        'tickets' => 'الكمية المطلوبة من التذاكر غير متاحة.',
                    ]);
                }

                $unitPrice = (float) $ticketType->price;
                $subtotal = round($unitPrice * $quantity, 2);

                $booking->items()->create([
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $totalAmount += $subtotal;
            }

            $booking->update([
                'total_amount' => round($totalAmount, 2),
            ]);

            return $booking->load('items');
        });

        ExpireBookingJob::dispatch($booking->id)
            ->delay($booking->expires_at);

        return $booking;
            }

    /**
     * تأكيد الدفع وتحويل الكمية المحجوزة إلى مباعة.
     */
    public function confirmPayment(Booking $booking): Booking
{
    $paidBooking = DB::transaction(function () use ($booking) {

        $booking = Booking::whereKey($booking->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $booking->status !== BookingStatus::Pending ||
            !$booking->expires_at ||
            $booking->expires_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'booking' => 'الحجز غير صالح للدفع أو انتهت صلاحيته.',
            ]);
        }

        $items = $booking->items()->with('ticketType')->get();

        foreach ($items as $item) {
            $ticketType = $item->ticketType;

            if ($ticketType->reserved_quantity < $item->quantity) {
                throw ValidationException::withMessages([
                    'tickets' => 'الكمية المحجوزة غير كافية.',
                ]);
            }

            $ticketType->decrement('reserved_quantity', $item->quantity);
            $ticketType->increment('sold_quantity', $item->quantity);

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

        return $booking->load('items.tickets');
    });

    BookingConfirmed::dispatch($paidBooking);

    return $paidBooking;
}

    /**
     * إلغاء الحجز المؤقت وتحرير الكميات.
     */
    public function cancelReservation(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {

            $booking = Booking::whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            // لا نحرر مخزون حجز مدفوع أو منتهٍ سابقاً.
            if ($booking->status !== BookingStatus::Pending) {
                return $booking;
            }

            $items = $booking->items()->with('ticketType')->get();

            foreach ($items as $item) {
                $item->ticketType->decrement(
                    'reserved_quantity',
                    $item->quantity
                );
            }

            $booking->update([
                'status' => BookingStatus::Expired,
            ]);

            return $booking;
        });
    }
}
