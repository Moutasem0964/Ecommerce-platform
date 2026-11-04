package com.ecommerce.inventory.grpc;

import org.springframework.stereotype.Service;

import com.ecommerce.inventory.grpc.v1.InventoryServiceGrpc;
import com.ecommerce.inventory.grpc.v1.ProductStock;
import com.ecommerce.inventory.grpc.v1.SetStockRequest;
import com.ecommerce.inventory.grpc.v1.SetStockResponse;
import com.ecommerce.inventory.stock.StockService;

import io.grpc.Status;
import io.grpc.stub.StreamObserver;
import java.util.Map;

import com.ecommerce.inventory.grpc.v1.GetStockRequest;
import com.ecommerce.inventory.grpc.v1.GetStockResponse;
import com.ecommerce.inventory.grpc.v1.ReservationStatus;
import com.ecommerce.inventory.grpc.v1.ReserveStockRequest;
import com.ecommerce.inventory.grpc.v1.ReserveStockResponse;
import com.ecommerce.inventory.stock.InsufficientStockException;
import com.ecommerce.inventory.stock.ReservationConflictException;
import com.ecommerce.inventory.stock.StockItem;

@Service
public class InventoryGrpcService extends InventoryServiceGrpc.InventoryServiceImplBase {

    private final StockService stockService;

    public InventoryGrpcService(StockService stockService) {
        this.stockService = stockService;
    }

    @Override
    public void setStock(SetStockRequest request, StreamObserver<SetStockResponse> observer) {
        try {
            int quantity = stockService.setStock(request.getProductId(), request.getQuantity());

            SetStockResponse response = SetStockResponse.newBuilder()
                    .setStock(ProductStock.newBuilder()
                            .setProductId(request.getProductId())
                            .setAvailableQuantity(quantity))
                    .build();

            observer.onNext(response);
            observer.onCompleted();
        } catch (IllegalArgumentException e) {
            observer.onError(Status.INVALID_ARGUMENT.withDescription(e.getMessage()).asRuntimeException());
        }
    }

    @Override
    public void getStock(GetStockRequest request, StreamObserver<GetStockResponse> observer) {
        try {
            Map<Long, Integer> available = stockService.getAvailable(request.getProductIdsList());

            GetStockResponse.Builder response = GetStockResponse.newBuilder();
            available.forEach((productId, quantity) ->
                    response.addItems(ProductStock.newBuilder()
                            .setProductId(productId)
                            .setAvailableQuantity(quantity)));

            observer.onNext(response.build());
            observer.onCompleted();
        } catch (IllegalArgumentException e) {
            observer.onError(Status.INVALID_ARGUMENT.withDescription(e.getMessage()).asRuntimeException());
        }
    }

    @Override
    public void reserveStock(ReserveStockRequest request, StreamObserver<ReserveStockResponse> observer) {
        try {
            var items = request.getItemsList().stream()
                    .map(item -> new StockItem(item.getProductId(), item.getQuantity()))
                    .toList();

            stockService.reserve(request.getOrderId(), items);

            observer.onNext(ReserveStockResponse.newBuilder()
                    .setOrderId(request.getOrderId())
                    .setStatus(ReservationStatus.RESERVATION_STATUS_RESERVED)
                    .build());
            observer.onCompleted();
        } catch (IllegalArgumentException e) {
            observer.onError(Status.INVALID_ARGUMENT.withDescription(e.getMessage()).asRuntimeException());
        } catch (InsufficientStockException e) {
            observer.onError(Status.FAILED_PRECONDITION.withDescription(e.getMessage()).asRuntimeException());
        } catch (ReservationConflictException e) {
            observer.onError(Status.ALREADY_EXISTS.withDescription(e.getMessage()).asRuntimeException());
        }
    }
}