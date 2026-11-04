CREATE TABLE reservations (
                              order_id   BIGINT      PRIMARY KEY,
                              status     VARCHAR(16) NOT NULL CHECK (status IN ('RESERVED', 'RELEASED')),
                              created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                              updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE reservation_items (
                                   order_id   BIGINT  NOT NULL REFERENCES reservations (order_id),
                                   product_id BIGINT  NOT NULL,
                                   quantity   INTEGER NOT NULL CHECK (quantity > 0),
                                   PRIMARY KEY (order_id, product_id)
);