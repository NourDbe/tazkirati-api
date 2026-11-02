<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\BookingServiceInterface;
use App\Services\BookingService;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
