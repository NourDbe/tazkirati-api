<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $this->user() !== null
            && $booking instanceof Booking
            && $booking->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [];
    }
}
