<?php

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Product;

final class ProductWithStock
{
    public function __construct(
        public readonly Product $product,
        public readonly ?int $stock,
    ) {
    }
}
