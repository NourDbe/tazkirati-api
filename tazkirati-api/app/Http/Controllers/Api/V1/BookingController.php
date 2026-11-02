<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\BookingServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\ConfirmBookingRequest;
use App\Http\Requests\StoreBookingRequest;
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

        return response()->json([
            'message' => 'تم إنشاء الحجز المؤقت بنجاح. لديك 10 دقائق لإتمام الدفع.',
            'data' => $booking->load('items.ticketType'),
        ], 201);
    }

    public function confirm(
        ConfirmBookingRequest $request,
        Booking $booking
    ): JsonResponse {
        $paidBooking = $this->bookingService->confirmPayment($booking);

        return response()->json([
            'message' => 'تم تأكيد الدفع والحجز بنجاح.',
            'data' => $paidBooking->load('items.ticketType', 'items.tickets'),
        ]);
    }

    public function cancel(
        CancelBookingRequest $request,
        Booking $booking
    ): JsonResponse {
        $cancelledBooking = $this->bookingService
            ->cancelReservation($booking);

        return response()->json([
            'message' => 'تم إلغاء الحجز المؤقت وتحرير التذاكر.',
            'data' => $cancelledBooking,
        ]);
    }
}
