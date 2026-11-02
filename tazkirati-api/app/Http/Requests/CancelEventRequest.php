<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class CancelEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        $organizer = $this->user()?->organizer;

        return $event instanceof Event
            && $organizer !== null
            && $event->organizer_id === $organizer->id;
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
