<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\ListProductsRequest;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
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
        $page = $this->products->list($request->validated('category'), (int) $request->validated('per_page', 15));

        return PaginatedResponse::make($page, ProductResource::class);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(['product' => (new ProductResource($this->products->details($product)))->resolve()]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $created = $this->products->create($request->safe()->except('image'), $request->file('image'));

        return response()->json(['product' => (new ProductResource($created))->resolve()], 201);
    }
}
