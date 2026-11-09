<?php

namespace App\Modules\Ordering\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Ordering\Models\Order;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->user_id === $user->id;
    }
}
