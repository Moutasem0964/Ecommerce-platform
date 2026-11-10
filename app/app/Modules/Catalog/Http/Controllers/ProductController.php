<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Data\ProductWithStock;
use App\Modules\Catalog\Http\Requests\ListProductsRequest;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Shared\Http\Controller;
use App\Modules\Shared\Http\Responses\PaginatedResponse;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products)
    {
    }

    public function index(ListProductsRequest $request): JsonResponse
    {
        $page = $this->products->list(
            $request->validated('category'),
            (int) $request->validated('per_page', 15),
            (int) $request->validated('page', 1),
        );

        return PaginatedResponse::make($page, ProductResource::class)
            ->header('X-Cache', $this->products->cacheStatus());
    }

    public function show(int $product): JsonResponse
    {
        return $this->productResponse($this->products->details($product))
            ->header('X-Cache', $this->products->cacheStatus());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $created = $this->products->create($request->safe()->except('image'), $request->file('image'));

        return $this->productResponse($created, 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $updated = $this->products->update($product, $request->safe()->except('image'), $request->file('image'));

        return $this->productResponse($updated);
    }

    private function productResponse(ProductWithStock $product, int $status = 200): JsonResponse
    {
        return response()->json(['product' => (new ProductResource($product))->resolve()], $status);
    }
}
