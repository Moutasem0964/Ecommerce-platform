<?php

namespace App\Modules\Ordering\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
        ];
    }
}
