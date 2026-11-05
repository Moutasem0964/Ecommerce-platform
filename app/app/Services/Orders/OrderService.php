<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use App\Services\Inventory\Exceptions\InsufficientStockException;
use App\Services\Inventory\Exceptions\InventoryUnavailableException;
use App\Services\Inventory\InventoryClient;
use App\Services\Orders\Exceptions\InvalidStatusTransitionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;


class OrderService
{
    public function __construct(private readonly InventoryClient $inventory)
    {
    }

    /** @param  array<int, array{product_id: int, quantity: int}>  $items */
    public function place(User $user, array $items): Order
    {
        $order = DB::transaction(function () use ($user, $items) {
            $products = Product::whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');

            $total = '0.00';
            $lines = [];
            foreach ($items as $item) {
                $price = $products[$item['product_id']]->price;
                $total = bcadd($total, bcmul($price, (string) $item['quantity'], 2), 2);
                $lines[] = [
                    'product_id' => $item['product_id'],
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

        $user->notify(new OrderStatusChanged($order));

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
        $order->user->notify(new OrderStatusChanged($order));

        return $order;
    }
}
