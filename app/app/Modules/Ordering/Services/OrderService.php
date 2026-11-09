<?php

namespace App\Modules\Ordering\Services;

use App\Modules\Catalog\Contracts\CatalogApi;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Clients\InventoryClient;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\InventoryUnavailableException;
use App\Modules\Ordering\Data\StaleOrderResult;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderStatusChanged;
use App\Modules\Ordering\Exceptions\InvalidStatusTransitionException;
use App\Modules\Ordering\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly InventoryClient $inventory,
        private readonly CatalogApi $catalog,
    ) {
    }

    public function listFor(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Order::query()
            ->with('items')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->latest('id')
            ->paginate($perPage);
    }

    public function details(Order $order): Order
    {
        return $order->loadMissing('items');
    }

    public function place(User $user, array $items): Order
    {
        $products = $this->catalog->productsFor(array_column($items, 'product_id'));

        $missing = [];
        foreach ($items as $i => $item) {
            if (! isset($products[$item['product_id']])) {
                $missing["items.$i.product_id"] = ['The selected product does not exist.'];
            }
        }
        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        $order = DB::transaction(function () use ($user, $items, $products) {
            $total = '0.00';
            $lines = [];
            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                $total = bcadd($total, bcmul($product['price'], (string) $item['quantity'], 2), 2);
                $lines[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $product['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $product['price'],
                ];
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'total' => $total,
            ]);
            $order->items()->createMany($lines);

            return $order;
        });

        try {
            $this->inventory->reserve($order->id, array_column($items, 'quantity', 'product_id'));
        } catch (InsufficientStockException $e) {
            $order->update(['status' => OrderStatus::Rejected]);
            throw $e;
        }

        try {
            if (config('orders.fail_after_reserve')) {
                throw new RuntimeException('Simulated failure after reserving stock (ORDERS_FAIL_AFTER_RESERVE).');
            }
            $order->update(['status' => OrderStatus::Confirmed]);
        } catch (Throwable $e) {
            $this->inventory->release($order->id);
            $order->update(['status' => OrderStatus::Failed]);
            Log::warning('Order failed after reserve; stock released', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            throw $e;
        }

        $this->announce($order);

        return $this->details($order);
    }

    public function changeStatus(Order $order, OrderStatus $to): Order
    {
        if (! $order->status->canTransitionTo($to)) {
            throw new InvalidStatusTransitionException(
                "Cannot change order {$order->id} from {$order->status->value} to {$to->value}."
            );
        }

        if ($to === OrderStatus::Cancelled) {
            $this->inventory->release($order->id);
        }

        $order->update(['status' => $to]);
        $this->announce($order);

        return $this->details($order);
    }

    public function releaseStale(int $olderThanMinutes, int $limit = 100): array
    {
        $orders = Order::where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subMinutes($olderThanMinutes))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($orders as $order) {
            try {
                $released = $this->inventory->release($order->id);
            } catch (InventoryUnavailableException) {
                $results[] = new StaleOrderResult($order->id, StaleOrderResult::INVENTORY_UNAVAILABLE);
                continue;
            }

            $order->update(['status' => OrderStatus::Failed]);
            $this->announce($order);

            $results[] = new StaleOrderResult(
                $order->id,
                $released ? StaleOrderResult::RELEASED : StaleOrderResult::NOTHING_RESERVED,
            );
        }

        return $results;
    }

    private function announce(Order $order): void
    {
        OrderStatusChanged::dispatch($order->id, $order->user_id, $order->status->value);
    }
}
