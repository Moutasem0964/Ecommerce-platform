<?php

namespace App\Modules\Ordering\Console;

use App\Modules\Inventory\Exceptions\InventoryUnavailableException;
use App\Modules\Inventory\InventoryClient;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderStatusChanged;
use App\Modules\Ordering\Models\Order;
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
            OrderStatusChanged::dispatch($order->id, $order->user_id, $order->status->value);
            $this->info("Order {$order->id}: ".($released ? 'stock released' : 'nothing was reserved').', marked failed');
        }

        $this->info("Done: {$orders->count()} stale order(s) checked.");

        return self::SUCCESS;
    }
}
