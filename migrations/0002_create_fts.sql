-- Full-text index for search. Column order = bm25 weight order (name, brand, tags, category, description).
-- A regular FTS5 table filled by the importer (rowid = products.rowid). External-content mode would
-- read columns by name from `products`, but `category` here is category + subcategory combined.
CREATE VIRTUAL TABLE products_fts USING fts5(
    name, brand, tags, category, description,
    tokenize = "unicode61 remove_diacritics 2",
    prefix = '2 3'
);
