<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Inventory\Exceptions\InventoryUnavailableException;
use App\Services\Inventory\InventoryClient;
use App\Notifications\OrderStatusChanged;
use Illuminate\Console\Command;


class ReleaseStaleOrders extends Command
{
    protected $signature = 'orders:release-stale {--minutes=1 : Only orders pending longer than this}';

    protected $description = 'Release stock held by orders stuck in pending and mark them failed';

    public function handle(InventoryClient $inventory): int
    {
        $cutoff = now()->subMinutes((int) $this->option('minutes'));

        $orders = Order::where('status', OrderStatus::Pending)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($orders as $order) {
            try {
                $released = $inventory->release($order->id);
            } catch (InventoryUnavailableException) {
                $this->warn("Order {$order->id}: inventory unavailable, will retry next run");
                continue;
            }

            $order->update(['status' => OrderStatus::Failed]);
            $order->user->notify(new OrderStatusChanged($order));
            $this->info("Order {$order->id}: ".($released ? 'stock released' : 'nothing was reserved').', marked failed');
        }

        $this->info("Done: {$orders->count()} stale order(s) checked.");

        return self::SUCCESS;
    }
}
