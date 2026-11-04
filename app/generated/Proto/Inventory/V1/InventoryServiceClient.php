<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Proto\Inventory\V1;

/**
 * Owns stock quantities and order reservations.
 *
 * Errors are returned as gRPC status codes, never as fields in a response:
 *   INVALID_ARGUMENT    - bad input (empty list, quantity <= 0, duplicate product ids)
 *   FAILED_PRECONDITION - not enough stock for at least one item (message lists them)
 *   ALREADY_EXISTS      - order_id was already used for a DIFFERENT set of items
 *   UNAVAILABLE / DEADLINE_EXCEEDED - service down or too slow (raised by gRPC itself)
 *
 */
class InventoryServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Proto\Inventory\V1\GetStockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Proto\Inventory\V1\GetStockResponse>
     */
    public function GetStock(\Proto\Inventory\V1\GetStockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.v1.InventoryService/GetStock',
        $argument,
        ['\Proto\Inventory\V1\GetStockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Admin: set the absolute available quantity of one product.
     * Calling it twice with the same values gives the same result (idempotent).
     *
     * @param \Proto\Inventory\V1\SetStockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Proto\Inventory\V1\SetStockResponse>
     */
    public function SetStock(\Proto\Inventory\V1\SetStockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.v1.InventoryService/SetStock',
        $argument,
        ['\Proto\Inventory\V1\SetStockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Reserve every item of an order, all-or-nothing, atomically.
     * order_id is the idempotency key: repeating the same request is safe and
     * returns RESERVATION_STATUS_RESERVED without reserving twice.
     *
     * @param \Proto\Inventory\V1\ReserveStockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Proto\Inventory\V1\ReserveStockResponse>
     */
    public function ReserveStock(\Proto\Inventory\V1\ReserveStockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.v1.InventoryService/ReserveStock',
        $argument,
        ['\Proto\Inventory\V1\ReserveStockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Give back everything reserved for an order (saga compensation).
     * Idempotent: releasing twice, or releasing an unknown order, is not an error.
     *
     * @param \Proto\Inventory\V1\ReleaseStockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Proto\Inventory\V1\ReleaseStockResponse>
     */
    public function ReleaseStock(\Proto\Inventory\V1\ReleaseStockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.v1.InventoryService/ReleaseStock',
        $argument,
        ['\Proto\Inventory\V1\ReleaseStockResponse', 'decode'],
        $metadata, $options);
    }

}
