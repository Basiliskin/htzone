-- Catalog rows as imported (one row per CSV line). Prices in agorot, never floats.
CREATE TABLE products (
    id           TEXT    PRIMARY KEY CHECK (id GLOB 'P[0-9][0-9][0-9][0-9]'),
    name         TEXT    NOT NULL CHECK (name <> ''),
    brand        TEXT    CHECK (brand IS NULL OR brand <> ''),       -- NULL when empty in the CSV
    category     TEXT    NOT NULL,
    subcategory  TEXT    NOT NULL,
    description  TEXT    NOT NULL,
    attributes   TEXT    NOT NULL,                                    -- original "k: v | k: v"
    image_url    TEXT    NOT NULL,
    price_minor  INTEGER NOT NULL CHECK (price_minor >= 0),
    currency     TEXT    NOT NULL DEFAULT 'ILS',
    tags         TEXT    NOT NULL DEFAULT ''                          -- enrichment tags joined with " | "
) STRICT;
