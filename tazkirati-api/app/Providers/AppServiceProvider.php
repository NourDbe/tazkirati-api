<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\BookingServiceInterface;
use App\Services\BookingService;
use App\Events\BookingConfirmed;
use App\Events\EventCancelled;
use App\Listeners\SendBookingConfirmationNotifications;
use App\Listeners\SendEventCancellationNotifications;
use Illuminate\Support\Facades\Event;
use App\Services\EventCacheService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
        BookingServiceInterface::class,
        BookingService::class
    );
    $this->app->singleton(EventCacheService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
{
    Event::listen(
        BookingConfirmed::class,
        SendBookingConfirmationNotifications::class
    );

    Event::listen(
        EventCancelled::class,
        SendEventCancellationNotifications::class
    );
}
}
