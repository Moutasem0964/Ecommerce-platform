<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $page = Order::query()
            ->with('items.product')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->latest('id')
            ->paginate(15);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Order $o) => $this->present($o))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return response()->json(['order' => $this->present($order)]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['shipped', 'delivered', 'cancelled'])],
        ]);

        $order = $this->orders->changeStatus($order, OrderStatus::from($data['status']));

        return response()->json(['order' => $this->present($order)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $order = $this->orders->place($request->user(), $data['items']);

        return response()->json(['order' => $this->present($order)], 201);
    }

    private function present(Order $order): array
    {
        $order->loadMissing('items.product');

        return [
            'id' => $order->id,
            'status' => $order->status->value,
            'total' => $order->total,
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])->values(),
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }
}
