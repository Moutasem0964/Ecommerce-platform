<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Data\ProductWithStock;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Clients\InventoryClient;
use App\Modules\Inventory\Exceptions\InventoryUnavailableException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductService
{
    public function __construct(private readonly InventoryClient $inventory)
    {
    }

    public function list(?string $category, int $perPage): LengthAwarePaginator
    {
        $page = Product::query()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('id')
            ->paginate($perPage);

        $stock = $this->stockFor($page->pluck('id')->all());

        return $page->through(fn (Product $p) => new ProductWithStock($p, $stock[$p->id] ?? null));
    }

    public function details(Product $product): ProductWithStock
    {
        $stock = $this->stockFor([$product->id]);

        return new ProductWithStock($product, $stock[$product->id] ?? null);
    }

    public function create(array $data, ?UploadedFile $image): ProductWithStock
    {
        $imagePath = $image?->store('products', 'public');

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

        return new ProductWithStock($product, $data['stock']);
    }

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
}
