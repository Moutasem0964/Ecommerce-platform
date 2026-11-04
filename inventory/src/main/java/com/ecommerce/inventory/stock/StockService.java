package com.ecommerce.inventory.stock;

import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
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
}