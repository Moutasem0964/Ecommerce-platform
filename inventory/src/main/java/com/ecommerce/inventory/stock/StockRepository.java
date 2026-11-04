package com.ecommerce.inventory.stock;

import org.springframework.jdbc.core.simple.JdbcClient;
import org.springframework.stereotype.Repository;

import java.util.Collection;
import java.util.List;
import java.util.Map;
import java.util.Optional;
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

    boolean tryDecrement(long productId, int quantity) {
        return jdbc.sql("""
                UPDATE stock
                   SET available_quantity = available_quantity - :qty, updated_at = now()
                 WHERE product_id = :id AND available_quantity >= :qty
                """)
                .param("id", productId)
                .param("qty", quantity)
                .update() == 1;
    }

    boolean insertReservation(long orderId) {
        return jdbc.sql("""
                INSERT INTO reservations (order_id, status) VALUES (:id, 'RESERVED')
                ON CONFLICT (order_id) DO NOTHING
                """)
                .param("id", orderId)
                .update() == 1;
    }

    Optional<String> lockReservationStatus(long orderId) {
        return jdbc.sql("SELECT status FROM reservations WHERE order_id = :id FOR UPDATE")
                .param("id", orderId)
                .query(String.class)
                .optional();
    }

    void insertItems(long orderId, List<StockItem> items) {
        for (StockItem item : items) {
            jdbc.sql("INSERT INTO reservation_items (order_id, product_id, quantity) VALUES (:order, :product, :qty)")
                    .param("order", orderId)
                    .param("product", item.productId())
                    .param("qty", item.quantity())
                    .update();
        }
    }

    List<StockItem> findItems(long orderId) {
        return jdbc.sql("SELECT product_id, quantity FROM reservation_items WHERE order_id = :id ORDER BY product_id")
                .param("id", orderId)
                .query((rs, rowNum)
                        -> new StockItem(rs.getLong("product_id"), rs.getInt("quantity")))
                .list();
    }

    void increment(long productId, int quantity) {
        jdbc.sql("""
                UPDATE stock
                   SET available_quantity = available_quantity + :qty, updated_at = now()
                 WHERE product_id = :id
                """)
                .param("id", productId)
                .param("qty", quantity)
                .update();
    }

    void markReleased(long orderId) {
        jdbc.sql("UPDATE reservations SET status = 'RELEASED', updated_at = now() WHERE order_id = :id")
                .param("id", orderId)
                .update();
    }
}