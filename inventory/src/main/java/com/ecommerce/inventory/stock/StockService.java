package com.ecommerce.inventory.stock;

import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.HashSet;
import java.util.Set;
@Service
public class StockService {

    private final StockRepository repository;

    public StockService(StockRepository repository) {
        this.repository = repository;
    }

    @Transactional
    public int setStock(long productId, int quantity) {
        if (productId <= 0) {
            throw new IllegalArgumentException("product_id must be positive");
        }
        if (quantity < 0) {
            throw new IllegalArgumentException("quantity must be >= 0");
        }
        repository.upsert(productId, quantity);
        return quantity;
    }

    @Transactional(readOnly = true)
    public Map<Long, Integer> getAvailable(List<Long> productIds) {
        if (productIds.isEmpty()) {
            throw new IllegalArgumentException("product_ids must not be empty");
        }
        if (productIds.size() > 500) {
            throw new IllegalArgumentException("at most 500 product_ids per call");
        }
        for (long id : productIds) {
            if (id <= 0) {
                throw new IllegalArgumentException("product ids must be positive, got " + id);
            }
        }

        List<Long> distinctIds = productIds.stream().distinct().toList();
        Map<Long, Integer> found = repository.findAvailable(distinctIds);

        Map<Long, Integer> result = new LinkedHashMap<>();
        for (Long id : distinctIds) {
            result.put(id, found.getOrDefault(id, 0));
        }
        return result;
    }

    @Transactional
    public void reserve(long orderId, List<StockItem> items) {
        if (orderId <= 0) {
            throw new IllegalArgumentException("order_id must be positive");
        }
        List<StockItem> sorted = validateAndSort(items);

        if (!repository.insertReservation(orderId)) {
            String status = repository.lockReservationStatus(orderId).orElseThrow();
            if (status.equals("RELEASED")) {
                throw new ReservationConflictException("order " + orderId + " was already released");
            }
            if (!repository.findItems(orderId).equals(sorted)) {
                throw new ReservationConflictException("order " + orderId + " is already reserved with different items");
            }
            return;
        }

        List<StockItem> shortages = new ArrayList<>();
        for (StockItem item : sorted) {
            if (!repository.tryDecrement(item.productId(), item.quantity())) {
                shortages.add(item);
            }
        }

        if (!shortages.isEmpty()) {
            throw new InsufficientStockException(describeShortages(shortages));
        }

        repository.insertItems(orderId, sorted);
    }

    private List<StockItem> validateAndSort(List<StockItem> items) {
        if (items.isEmpty()) {
            throw new IllegalArgumentException("items must not be empty");
        }
        if (items.size() > 500) {
            throw new IllegalArgumentException("at most 500 items per order");
        }
        Set<Long> seen = new HashSet<>();
        for (StockItem item : items) {
            if (item.productId() <= 0) {
                throw new IllegalArgumentException("product ids must be positive, got " + item.productId());
            }
            if (item.quantity() <= 0) {
                throw new IllegalArgumentException("quantity must be > 0 for product " + item.productId());
            }
            if (!seen.add(item.productId())) {
                throw new IllegalArgumentException("duplicate product " + item.productId() + " in items");
            }
        }
        return items.stream().sorted(Comparator.comparingLong(StockItem::productId)).toList();
    }

    private String describeShortages(List<StockItem> shortages) {
        Map<Long, Integer> available = repository.findAvailable(
                shortages.stream().map(StockItem::productId).toList());

        StringBuilder message = new StringBuilder("insufficient stock:");
        for (StockItem item : shortages) {
            message.append(" product ").append(item.productId())
                    .append(" requested ").append(item.quantity())
                    .append(" available ").append(available.getOrDefault(item.productId(), 0))
                    .append(';');
        }
        return message.toString();
    }

    @Transactional
    public boolean release(long orderId) {
        if (orderId <= 0) {
            throw new IllegalArgumentException("order_id must be positive");
        }

        String status = repository.lockReservationStatus(orderId).orElse(null);
        if (status == null) {
            return false;
        }
        if (status.equals("RELEASED")) {
            return true;
        }

        for (StockItem item : repository.findItems(orderId)) {
            repository.increment(item.productId(), item.quantity());
        }
        repository.markReleased(orderId);
        return true;
    }
}