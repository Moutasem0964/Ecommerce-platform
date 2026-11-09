<?php

namespace App\Modules\Ordering\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'total' => $this->total,
            'items' => OrderItemResource::collection($this->items)->resolve($request),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
