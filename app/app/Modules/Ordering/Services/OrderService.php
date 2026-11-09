<?php

namespace App\Modules\Ordering\Services;

use App\Modules\Catalog\Contracts\CatalogApi;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\InventoryUnavailableException;
use App\Modules\Inventory\InventoryClient;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderStatusChanged;
use App\Modules\Ordering\Exceptions\InvalidStatusTransitionException;
use App\Modules\Ordering\Models\Order;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;


class OrderService
{
    public function __construct(
        private readonly InventoryClient $inventory,
        private readonly CatalogApi $catalog,
    ) {
    }

    /** @param  array<int, array{product_id: int, quantity: int}>  $items */
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
                $price = $products[$item['product_id']]['price'];
                $total = bcadd($total, bcmul($price, (string) $item['quantity'], 2), 2);
                $lines[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $products[$item['product_id']]['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $price,
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

        $quantities = [];
        foreach ($items as $item) {
            $quantities[$item['product_id']] = $item['quantity'];
        }

        try {
            $this->inventory->reserve($order->id, $quantities);
        } catch (InsufficientStockException $e) {
            $order->update(['status' => OrderStatus::Rejected]);
            throw $e;
        } catch (InventoryUnavailableException $e) {
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

        OrderStatusChanged::dispatch($order->id, $order->user_id, $order->status->value);

        return $order;
    }

    private const ALLOWED_TRANSITIONS = [
        'confirmed' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
    ];

    public function changeStatus(Order $order, OrderStatus $to): Order
    {
        $allowed = self::ALLOWED_TRANSITIONS[$order->status->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw new InvalidStatusTransitionException(
                "Cannot change order {$order->id} from {$order->status->value} to {$to->value}."
            );
        }

        if ($to === OrderStatus::Cancelled) {
            $this->inventory->release($order->id);
        }

        $order->update(['status' => $to]);
        OrderStatusChanged::dispatch($order->id, $order->user_id, $order->status->value);

        return $order;
    }
}
