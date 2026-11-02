<?php

namespace App\Contracts;

use App\Models\Booking;
use App\Models\User;

interface BookingServiceInterface
{
    public function createReservation(
        User $user,
        array $items
    ): Booking;

    public function confirmPayment(
        Booking $booking
    ): Booking;

    public function cancelReservation(
        Booking $booking
    ): Booking;
}
