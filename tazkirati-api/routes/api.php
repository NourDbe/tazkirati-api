<?php

use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\Organizer\EventCancellationController;
use Illuminate\Support\Facades\Route;

// Public event endpoints
Route::prefix('v1')->group(function () {
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{event}', [EventController::class, 'show']);
    Route::get(
        '/events/{event}/remaining-tickets',
        [EventController::class, 'remainingTickets']
    );

    // Authenticated customer endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/bookings', [BookingController::class, 'store']);

        Route::post(
            '/bookings/{booking}/confirm',
            [BookingController::class, 'confirm']
        );

        Route::post(
            '/bookings/{booking}/cancel',
            [BookingController::class, 'cancel']
        );

        // Organizer endpoint
        Route::post(
            '/organizer/events/{event}/cancel',
            [EventCancellationController::class, 'store']
        );
    });
});
