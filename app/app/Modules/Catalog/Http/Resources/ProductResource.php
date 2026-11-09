<?php

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->resource->product;
        $stock = $this->resource->stock;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'category' => $product->category,
            'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
            'stock' => $stock,
            'in_stock' => $stock === null ? null : $stock > 0,
        ];
    }
}
