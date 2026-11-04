CREATE TABLE stock (
    product_id         BIGINT      PRIMARY KEY,
    available_quantity INTEGER     NOT NULL CHECK (available_quantity >= 0),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);