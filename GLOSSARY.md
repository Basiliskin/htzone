# Glossary

Use these terms exactly, in code, docs and conversation.

| Term | Meaning |
|---|---|
| **Catalog** | The full set of Products loaded from `assets/catalog_no_prices.csv` (1000 rows). Read-only at runtime. |
| **Product** | One Catalog item: id (`P0001`), name, brand (may be empty), category, subcategory, description, attributes, image URL, Price. |
| **Price** | Generated amount in agorot (minor units) plus currency ILS, shown as `₪1,299`. Deterministic per Product (D4). Modelled as `Money`. |
| **Enrichment** | Extra searchable data written ahead of time by Claude per Product: **Tags** in Hebrew and English. Stored in `data/enrichment.jsonl`. |
| **Tag** | A short word or phrase attached to a Product to make it findable: a synonym, the other language's spelling (אייפון↔iPhone), a plural or prefixed form, a use-case, or who it suits. |
| **Alias** | A fixed query-side mapping between spellings (אייפון → iPhone, דייסון → Dyson), applied before matching. |
| **Query** | The text a Visitor types, after trimming and limits (at least 2 and at most 100 characters). |
| **Normalization** | Turning each Query word into its variants: as typed, with attached Hebrew prefixes stripped (ו ה ב ל מ ש כ), with plural suffixes stripped (ים, ות; English -s/-es), and with niqqud removed. |
| **Result** | A Product returned for a Query, with its rank. At most 24 per Query. |
| **Relevance** | How well the Results' order fits the Query. Checked by the example-query table in `docs/requirements.md`. |
| **Typo fallback** | A second, close-spelling (trigram) search used only when the main search returns no Results. Shown as "האם התכוונת…" ("did you mean…"). |
| **Notice** | An optional message returned with Results: `missing_model` (D14) or `did_you_mean` (D15). |
| **Visitor** | An anonymous person using the site. There are no accounts. |
