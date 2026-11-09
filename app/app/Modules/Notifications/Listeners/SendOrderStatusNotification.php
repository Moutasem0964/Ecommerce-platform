<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Notifications\OrderStatusNotification;
use App\Modules\Ordering\Events\OrderStatusChanged;

class SendOrderStatusNotification
{
    public function handle(OrderStatusChanged $event): void
    {
        User::find($event->userId)?->notify(new OrderStatusNotification($event->orderId, $event->status));
    }
}
