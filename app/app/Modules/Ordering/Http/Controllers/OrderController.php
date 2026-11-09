<?php

namespace App\Modules\Ordering\Http\Controllers;

use App\Modules\Ordering\Http\Requests\PlaceOrderRequest;
use App\Modules\Ordering\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Ordering\Http\Resources\OrderResource;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Shared\Http\Controller;
use App\Modules\Shared\Http\Responses\PaginatedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return PaginatedResponse::make($this->orders->listFor($request->user()), OrderResource::class);
    }

    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return $this->orderResponse($this->orders->details($order));
    }

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        return $this->orderResponse($this->orders->place($request->user(), $request->validated('items')), 201);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        return $this->orderResponse($this->orders->changeStatus($order, $request->status()));
    }

    private function orderResponse(Order $order, int $status = 200): JsonResponse
    {
        return response()->json(['order' => (new OrderResource($order))->resolve()], $status);
    }
}
