<?php

namespace App\Modules\Inventory;

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\InventoryUnavailableException;
use App\Modules\Inventory\Exceptions\ReservationConflictException;
use Grpc\ChannelCredentials;
use Grpc\UnaryCall;
use Proto\Inventory\V1\GetStockRequest;
use Proto\Inventory\V1\InventoryServiceClient;
use Proto\Inventory\V1\ReleaseStockRequest;
use Proto\Inventory\V1\ReservationItem;
use Proto\Inventory\V1\ReservationStatus;
use Proto\Inventory\V1\ReserveStockRequest;
use Proto\Inventory\V1\SetStockRequest;
use RuntimeException;

class InventoryClient
{
    private InventoryServiceClient $client;
    private int $timeoutMicros;

    public function __construct()
    {
        $this->client = new InventoryServiceClient(
            config('inventory.target'),
            ['credentials' => ChannelCredentials::createInsecure()]
        );
        $this->timeoutMicros = config('inventory.timeout_ms') * 1000;
    }

    /** @return array<int, int>  product id => available quantity */
    public function getStock(array $productIds): array
    {
        $request = (new GetStockRequest())->setProductIds($productIds);
        $response = $this->call($this->client->GetStock($request, [], $this->options()));

        $stock = [];
        foreach ($response->getItems() as $item) {
            $stock[$item->getProductId()] = $item->getAvailableQuantity();
        }
        return $stock;
    }

    public function setStock(int $productId, int $quantity): void
    {
        $request = (new SetStockRequest())->setProductId($productId)->setQuantity($quantity);
        $this->call($this->client->SetStock($request, [], $this->options()));
    }

    /** @param  array<int, int>  $items  product id => quantity */
    public function reserve(int $orderId, array $items): void
    {
        $reservationItems = [];
        foreach ($items as $productId => $quantity) {
            $reservationItems[] = (new ReservationItem())->setProductId($productId)->setQuantity($quantity);
        }

        $request = (new ReserveStockRequest())->setOrderId($orderId)->setItems($reservationItems);
        $this->call($this->client->ReserveStock($request, [], $this->options()));
    }

    /** @return bool  true = released (now or before), false = order was never reserved */
    public function release(int $orderId): bool
    {
        $request = (new ReleaseStockRequest())->setOrderId($orderId);
        $response = $this->call($this->client->ReleaseStock($request, [], $this->options()));

        return $response->getStatus() === ReservationStatus::RESERVATION_STATUS_RELEASED;
    }

    // ---------- helpers ----------

    private function options(): array
    {
        return ['timeout' => $this->timeoutMicros];
    }

    /** Waits for the answer and turns every gRPC error into the right exception. */
    private function call(UnaryCall $call): mixed
    {
        [$response, $status] = $call->wait();

        return match ($status->code) {
            \Grpc\STATUS_OK => $response,
            \Grpc\STATUS_FAILED_PRECONDITION => throw new InsufficientStockException($status->details),
            \Grpc\STATUS_ALREADY_EXISTS => throw new ReservationConflictException($status->details),
            \Grpc\STATUS_UNAVAILABLE, \Grpc\STATUS_DEADLINE_EXCEEDED
                => throw new InventoryUnavailableException("inventory service unavailable: {$status->details}"),
            default => throw new RuntimeException("inventory call failed: [{$status->code}] {$status->details}"),
        };
    }
}
