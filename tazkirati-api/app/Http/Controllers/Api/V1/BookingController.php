<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\BookingServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\ConfirmBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function __construct(
        private BookingServiceInterface $bookingService
    ) {}

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->createReservation(
            $request->user(),
            $request->validated()['items']
        );

        $booking->load('items.ticketType');

        return (new BookingResource($booking))
            ->additional([
                'message' => 'تم إنشاء الحجز المؤقت بنجاح. لديك 10 دقائق لإتمام الدفع.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function confirm(
        ConfirmBookingRequest $request,
        Booking $booking
    ): JsonResponse {
        $paidBooking = $this->bookingService->confirmPayment($booking);

        $paidBooking->load([
            'items.ticketType',
            'items.tickets',
        ]);

        return (new BookingResource($paidBooking))
            ->additional([
                'message' => 'تم تأكيد الدفع والحجز بنجاح.',
            ])
            ->response();
    }

    public function cancel(
        CancelBookingRequest $request,
        Booking $booking
    ): JsonResponse {
        $cancelledBooking = $this->bookingService
            ->cancelReservation($booking);

        $cancelledBooking->load('items.ticketType');

        return (new BookingResource($cancelledBooking))
            ->additional([
                'message' => 'تم إلغاء الحجز المؤقت وتحرير التذاكر.',
            ])
            ->response();
    }
}
