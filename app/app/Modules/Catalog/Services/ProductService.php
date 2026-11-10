<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Data\ProductWithStock;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Clients\InventoryClient;
use App\Modules\Inventory\Exceptions\InventoryUnavailableException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductService
{
    private const EDITABLE = ['name', 'description', 'price', 'category'];

    public function __construct(
        private readonly InventoryClient $inventory,
        private readonly ProductCache $cache,
    ) {
    }

    public function list(?string $category, int $perPage, int $page): LengthAwarePaginator
    {
        $result = $this->cache->page($category, $perPage, $page, function () use ($category, $perPage, $page) {
            $query = $this->query()->when($category, fn ($q) => $q->where('category', $category));

            return [
                'total' => (clone $query)->count(),
                'rows' => $query->orderBy('id')->forPage($page, $perPage)->get()->map->getAttributes()->all(),
            ];
        });

        $products = Product::hydrate($result['rows']);
        $stock = $this->stockFor($products->pluck('id')->all());

        return new LengthAwarePaginator(
            $products->map(fn (Product $p) => new ProductWithStock($p, $stock[$p->id] ?? null)),
            $result['total'],
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    public function details(int $id): ProductWithStock
    {
        $attributes = $this->cache->product($id, fn () => $this->query()->find($id)?->getAttributes());

        if ($attributes === null) {
            throw (new ModelNotFoundException())->setModel(Product::class, [$id]);
        }

        $product = Product::hydrate([$attributes])->first();
        $stock = $this->stockFor([$id]);

        return new ProductWithStock($product, $stock[$id] ?? null);
    }

    public function create(array $data, ?UploadedFile $image): ProductWithStock
    {
        $imagePath = $image?->store('products', 'public');

        try {
            $product = DB::transaction(function () use ($data, $imagePath) {
                $product = Product::create([
                    ...array_intersect_key($data, array_flip(self::EDITABLE)),
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

        $this->cache->invalidateLists();

        return new ProductWithStock($product, $data['stock']);
    }

    public function update(Product $product, array $data, ?UploadedFile $image): ProductWithStock
    {
        $newImagePath = $image?->store('products', 'public');
        $oldImagePath = $product->image_path;

        try {
            DB::transaction(function () use ($product, $data, $newImagePath) {
                $product->fill(array_intersect_key($data, array_flip(self::EDITABLE)));
                if ($newImagePath) {
                    $product->image_path = $newImagePath;
                }
                $product->save();

                if (array_key_exists('stock', $data)) {
                    $this->inventory->setStock($product->id, $data['stock']);
                }
            });
        } catch (Throwable $e) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }
            throw $e;
        }

        if ($newImagePath && $oldImagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }

        $this->cache->forgetProduct($product->id);
        $this->cache->invalidateLists();

        $stock = array_key_exists('stock', $data) ? $data['stock'] : ($this->stockFor([$product->id])[$product->id] ?? null);

        return new ProductWithStock($product, $stock);
    }

    public function cacheStatus(): string
    {
        return $this->cache->lastStatus();
    }

    private function query(): Builder
    {
        return $this->cache->enabled() ? Product::query()->useWritePdo() : Product::query();
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
