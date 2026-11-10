<?php

namespace App\Modules\Ordering\Database\Seeders;

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoadTestOrdersSeeder extends Seeder
{
    private const CHUNK = 1000;

    private const ITEM_CHUNK = 1000;

    private const DAYS_OF_HISTORY = 730;

    public function run(array $userIds, array $products, int $count): void
    {
        $productIds = array_keys($products);
        $productCount = count($productIds);
        $heavyUsers = array_slice($userIds, 0, max(1, intdiv(count($userIds), 10)));

        $nextOrderId = (Order::max('id') ?? 0) + 1;
        $nextItemId = (DB::table('order_items')->max('id') ?? 0) + 1;
        $start = now()->subDays(self::DAYS_OF_HISTORY)->getTimestamp();
        $span = self::DAYS_OF_HISTORY * 86400;

        for ($done = 0; $done < $count; $done += self::CHUNK) {
            $orders = [];
            $items = [];

            for ($i = $done; $i < min($done + self::CHUNK, $count); $i++) {
                $orderId = $nextOrderId++;
                $createdAt = date('Y-m-d H:i:s', $start + intdiv($i * $span, $count) + mt_rand(0, 3600));
                $userId = mt_rand(1, 100) <= 50
                    ? $heavyUsers[array_rand($heavyUsers)]
                    : $userIds[array_rand($userIds)];

                $picked = [];
                $lines = mt_rand(1, 5);
                while (count($picked) < $lines) {
                    $picked[$productIds[mt_rand(0, $productCount - 1)]] = true;
                }

                $total = '0.00';
                foreach (array_keys($picked) as $productId) {
                    $quantity = mt_rand(1, 3);
                    $price = $products[$productId]['price'];
                    $total = bcadd($total, bcmul($price, (string) $quantity, 2), 2);
                    $items[] = [
                        'id' => $nextItemId++,
                        'order_id' => $orderId,
                        'product_id' => $productId,
                        'product_name' => $products[$productId]['name'],
                        'quantity' => $quantity,
                        'unit_price' => $price,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }

                $orders[] = [
                    'id' => $orderId,
                    'user_id' => $userId,
                    'status' => $this->randomStatus()->value,
                    'total' => $total,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }

            DB::transaction(function () use ($orders, $items) {
                DB::table('orders')->insert($orders);
                foreach (array_chunk($items, self::ITEM_CHUNK) as $chunk) {
                    DB::table('order_items')->insert($chunk);
                }
            });

            if (($done + self::CHUNK) % 20000 === 0) {
                $this->command?->info('Orders: '.min($done + self::CHUNK, $count));
            }
        }

        $this->command?->info('Orders: '.Order::count().', items: '.DB::table('order_items')->count());
    }

    private function randomStatus(): OrderStatus
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 60 => OrderStatus::Delivered,
            $roll <= 72 => OrderStatus::Shipped,
            $roll <= 82 => OrderStatus::Confirmed,
            $roll <= 90 => OrderStatus::Cancelled,
            $roll <= 95 => OrderStatus::Rejected,
            default => OrderStatus::Failed,
        };
    }
}
