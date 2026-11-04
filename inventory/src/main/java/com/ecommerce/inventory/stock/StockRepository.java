package com.ecommerce.inventory.stock;

import org.springframework.jdbc.core.simple.JdbcClient;
import org.springframework.stereotype.Repository;

import java.util.Collection;
import java.util.Map;
import java.util.stream.Collectors;

@Repository
class StockRepository {

    private final JdbcClient jdbc;

    StockRepository(JdbcClient jdbc) {
        this.jdbc = jdbc;
    }

    void upsert(long productId, int quantity) {
        jdbc.sql("""
                INSERT INTO stock (product_id, available_quantity) VALUES (:id, :qty)
                ON CONFLICT (product_id) DO UPDATE
                    SET available_quantity = EXCLUDED.available_quantity, updated_at = now()
                """)
                .param("id", productId)
                .param("qty", quantity)
                .update();
    }
    Map<Long, Integer> findAvailable(Collection<Long> productIds) {
        //noinspection JpaQueryApiInspection
        return jdbc.sql("SELECT product_id, available_quantity FROM stock WHERE product_id IN (:ids)")
                .param("ids", productIds)
                .query((rs, rowNum)
                        -> Map.entry(rs.getLong("product_id"), rs.getInt("available_quantity")))
                .list()
                .stream()
                .collect(Collectors.toMap(Map.Entry::getKey, Map.Entry::getValue));
    }
}