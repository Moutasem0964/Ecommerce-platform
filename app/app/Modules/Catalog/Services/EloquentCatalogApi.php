<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Contracts\CatalogApi;
use App\Modules\Catalog\Models\Product;

class EloquentCatalogApi implements CatalogApi
{
    public function productsFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return Product::query()
            ->useWritePdo()
            ->whereIn('id', array_unique($productIds))
            ->get(['id', 'name', 'price'])
            ->mapWithKeys(fn (Product $p) => [$p->id => ['name' => $p->name, 'price' => $p->price]])
            ->all();
    }
}
