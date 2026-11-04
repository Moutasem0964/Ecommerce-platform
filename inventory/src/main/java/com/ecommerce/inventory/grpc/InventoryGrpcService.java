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
}