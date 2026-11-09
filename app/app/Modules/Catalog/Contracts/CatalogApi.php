<?php

namespace App\Modules\Catalog\Contracts;

interface CatalogApi
{
    public function productsFor(array $productIds): array;
}
