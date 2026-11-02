<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => [
                'required',
                'integer',
                'distinct',
                'exists:ticket_types,id',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'يجب إضافة تذكرة واحدة على الأقل.',
            'items.array' => 'صيغة التذاكر غير صحيحة.',
            'items.min' => 'يجب إضافة تذكرة واحدة على الأقل.',
            'items.*.ticket_type_id.required' => 'نوع التذكرة مطلوب.',
            'items.*.ticket_type_id.exists' => 'نوع التذكرة المحدد غير موجود.',
            'items.*.ticket_type_id.distinct' => 'لا يمكن تكرار نوع التذكرة نفسه.',
            'items.*.quantity.required' => 'كمية التذاكر مطلوبة.',
            'items.*.quantity.integer' => 'كمية التذاكر يجب أن تكون رقماً صحيحاً.',
            'items.*.quantity.min' => 'يجب حجز تذكرة واحدة على الأقل.',
        ];
    }
}
