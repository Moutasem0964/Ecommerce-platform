<?php

namespace App\Modules\Ordering\Console;

use App\Modules\Ordering\Data\StaleOrderResult;
use App\Modules\Ordering\Services\OrderService;
use Illuminate\Console\Command;

class ReleaseStaleOrders extends Command
{
    protected $signature = 'orders:release-stale {--minutes=1 : Only orders pending longer than this}';

    protected $description = 'Release stock held by orders stuck in pending and mark them failed';

    public function handle(OrderService $orders): int
    {
        $results = $orders->releaseStale((int) $this->option('minutes'));

        foreach ($results as $result) {
            match ($result->outcome) {
                StaleOrderResult::INVENTORY_UNAVAILABLE => $this->warn("Order {$result->orderId}: inventory unavailable, will retry next run"),
                StaleOrderResult::RELEASED => $this->info("Order {$result->orderId}: stock released, marked failed"),
                StaleOrderResult::NOTHING_RESERVED => $this->info("Order {$result->orderId}: nothing was reserved, marked failed"),
            };
        }

        $this->info('Done: '.count($results).' stale order(s) checked.');

        return self::SUCCESS;
    }
}
