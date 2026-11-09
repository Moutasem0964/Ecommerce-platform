<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Exceptions\InventoryUnavailableException;
use App\Modules\Inventory\InventoryClient;
use App\Modules\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductController extends Controller
{
    public function __construct(private readonly InventoryClient $inventory)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->validate([
            'category' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = Product::query()
            ->when($query['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->orderBy('id')
            ->paginate($query['per_page'] ?? 15);

        $stock = $this->stockFor($page->pluck('id')->all());

        return response()->json([
            'data' => $page->getCollection()->map(fn (Product $p) => $this->present($p, $stock[$p->id] ?? null))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $stock = $this->stockFor([$product->id]);

        return response()->json(['product' => $this->present($product, $stock[$product->id] ?? null)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'category' => ['required', 'string', 'max:50'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('products', 'public')
            : null;

        try {

            $product = DB::transaction(function () use ($data, $imagePath) {
                $product = Product::create([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'price' => $data['price'],
                    'category' => $data['category'],
                    'image_path' => $imagePath,
                ]);

                $this->inventory->setStock($product->id, $data['stock']);

                return $product;
            });
        } catch (Throwable $e) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            throw $e; 
        }

        return response()->json(['product' => $this->present($product, $data['stock'])], 201);
    }

    /**
     * Stock for many products in one gRPC call.
     * If inventory is down, the catalog still works: stock is just unknown (null).
     *
     * @return array<int, int>|array{}
     */
    private function stockFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        try {
            return $this->inventory->getStock($productIds);
        } catch (InventoryUnavailableException) {
            return [];
        }
    }

    private function present(Product $product, ?int $stock): array
    {
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
