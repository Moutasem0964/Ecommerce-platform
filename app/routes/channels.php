<?php

use App\Modules\Ordering\Models\Order;
use Illuminate\Support\Facades\Broadcast;

// الزبون صاحب الطلب أو المسؤول فقط
Broadcast::channel('orders.{orderId}', function ($user, int $orderId) {
    $order = Order::find($orderId);

    return $order && ($user->isAdmin() || $order->user_id === $user->id);
}, ['guards' => ['api']]);

// المسؤول فقط
Broadcast::channel('admin.orders', fn ($user) => $user->isAdmin(), ['guards' => ['api']]);
