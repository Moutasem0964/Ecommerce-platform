<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Clients\InventoryClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoadTestProductsSeeder extends Seeder
{
    private const CHUNK = 1000;

    private const CATEGORIES = [
        'kitchen', 'electronics', 'books', 'toys', 'garden', 'sports',
        'beauty', 'office', 'pets', 'automotive', 'music', 'clothing',
    ];

    private const ADJECTIVES = ['Classic', 'Smart', 'Eco', 'Pro', 'Mini', 'Ultra', 'Soft', 'Compact', 'Deluxe', 'Basic'];

    private const NOUNS = ['Mug', 'Lamp', 'Backpack', 'Speaker', 'Notebook', 'Bottle', 'Chair', 'Headphones', 'Shirt', 'Ball'];

    public function __construct(private readonly InventoryClient $inventory)
    {
    }

    public function run(int $count): array
    {
        $existing = Product::count();
        $nextId = (Product::max('id') ?? 0) + 1;
        $now = now()->toDateTimeString();
        $created = [];

        for ($n = $existing; $n < $count; $n += self::CHUNK) {
            $rows = [];
            for ($i = $n; $i < min($n + self::CHUNK, $count); $i++) {
                $id = $nextId++;
                $rows[] = [
                    'id' => $id,
                    'name' => self::ADJECTIVES[array_rand(self::ADJECTIVES)].' '.self::NOUNS[array_rand(self::NOUNS)].' '.$id,
                    'description' => null,
                    'price' => number_format(mt_rand(100, 99999) / 100, 2, '.', ''),
                    'category' => self::CATEGORIES[array_rand(self::CATEGORIES)],
                    'image_path' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $created[] = $id;
            }
            DB::table('products')->insert($rows);
        }

        foreach ($created as $i => $id) {
            $this->inventory->setStock($id, mt_rand(1, 100) <= 10 ? 0 : mt_rand(1, 500));
            if (($i + 1) % 2000 === 0) {
                $this->command?->info('Stock set for '.($i + 1).' products');
            }
        }

        $this->command?->info('Products: '.Product::count());

        return Product::query()
            ->get(['id', 'name', 'price'])
            ->mapWithKeys(fn (Product $p) => [$p->id => ['name' => $p->name, 'price' => $p->price]])
            ->all();
    }
}
