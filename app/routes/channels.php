<?php

use App\Modules\Ordering\Models\Order;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('orders.{orderId}', function ($user, int $orderId) {
    $order = Order::query()->useWritePdo()->find($orderId);

    return $order && ($user->isAdmin() || $order->user_id === $user->id);
}, ['guards' => ['api']]);

Broadcast::channel('admin.orders', fn ($user) => $user->isAdmin(), ['guards' => ['api']]);
