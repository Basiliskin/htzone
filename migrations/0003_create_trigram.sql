-- Trigram index, used only for the typo fallback ("did you mean").
CREATE VIRTUAL TABLE products_trigram USING fts5(
    name, brand, tags,
    tokenize = "trigram"
);
