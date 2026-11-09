<?php

namespace App\Modules\Ordering\Http\Requests;

use App\Modules\Ordering\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['shipped', 'delivered', 'cancelled'])],
        ];
    }

    public function status(): OrderStatus
    {
        return OrderStatus::from($this->validated('status'));
    }
}
