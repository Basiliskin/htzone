# Data — sources, generation, enrichment, validation

## 1. Source profile (`assets/catalog_no_prices.csv`)

| Fact | Value / consequence |
|---|---|
| Rows / ids | 1000, `P0001`–`P1000`, sequential, unique |
| Encoding | UTF-8 **with BOM**: the importer and the check script strip it from the first header |
| Columns | `id, name, brand, category, subcategory, description, attributes, image_url`, with **no price** (→ §3) |
| Categories | 13 categories, 80 `category > subcategory` pairs |
| Brand | always Latin script, even when the name spells it in Hebrew (Dyson / "דייסון"); **52 empty** → brand line hidden, nothing generated |
| Attributes | always `key: value` pairs |
| Images | all `placehold.co` text placeholders (README limitation) |
| Language | names are Hebrew with embedded Latin (brands, models, units) |
| Gaps vs the brief | **no iPhone 16 and no "16" model at all** (D14): Apple phones P0401/P0407/P0413 exist, so the missing-model notice applies |
| Hidden characters | P0014's description contains two **Persian** letters (ی U+06CC, د U+062F) inside "סו-ווید": they look Hebrew but never match a Hebrew query. Its tags carry the correct "סו ויד" |

## 2. Data problems in the CSV (reported, not edited)

The CSV is the client's file and stays unchanged. Tags carry the correct spelling so search still works, and the list goes to the client. All 31 were found by the tagging agents in `issues`; ids can be listed with
`python3 -c "import json;[print(r['id'],i) for r in map(json.loads,open('data/enrichment.jsonl')) for i in r['issues']]"`.

- **Typos in names**: P0001 מטהג→מטגן · P0010 טוסטר לחיטה→לחם · P0212 מצרר · P0606 סדין חזה גומי→עם גומי · P0620 פרוכת→וילון · P0643 ויקטג׳→וינטג׳ · P0676 מקלוע→קלוע · P0805 ליטוי→יצוק · P0936 מיטת→מיטה אורתופדית · P0184 ניידת→נייד
- **Name doesn't match the product**: P0221 "מטהר אדים" is a steam cleaner · P0293 "שעון שבת לדוד שמש" is a Wi-Fi boiler switch · P0533 "Hi-Fi" is a portable BT speaker · P0598 "embroidery and knitting" kit has no knitting items · P0697 "set" is one brush · P0740 "sonic" brush is oscillating-rotating · P0816 "magnetic" ring has no magnets · P0981 "engraver" is a laser distance meter · P0983 "screwdriver" is a drill press
- **Description / attribute errors**: P0014 Persian letters · P0121 stationary vs portable · P0190 Lindt "Belgian" · P0365 מתי מוות · P0431 days vs 6 h battery · P0441, P0607, P0729 value in the wrong attribute · P0590 משבור · P0651 מיקרוסועד · P0781 נשים→נושם · P0827 הבמצע

## 3. Generated prices (D4, D24)

- **Source:** `data/price-ranges.json`, a `[min, max]` range in whole ₪ for each of the 80 subcategories (Israeli retail ballparks, drafted by Claude, reviewed by the developer).
- **Algorithm** (`PriceGenerator`, S1): `n = min + crc32("htzone-v1:" + id) mod (max − min + 1)`, then rounded to a store ending (`…9` under ₪1,000, `…99` above), clamped to the range, stored in **agorot**. It's deterministic: the same id always gets the same price, on every machine and every build. There's no random state.
- **Disclosed** in the README. **Weakness:** within a subcategory the price ignores the product itself. For example, the "pro" phone P0413 gets ₪2,599, cheaper than the base P0401 at ₪5,599. Per-product pricing was rejected (D24 c) as unjustified effort for synthetic data.

## 4. Enrichment (D2, D23, D25)

- **Output:** `data/enrichment.jsonl`, one line per product, `{"id","tags_he","tags_en","issues"}`. Tags are loaded into the FTS `tags` column (weight 6). `issues` isn't loaded; it's kept for §2.
- **How it was made:** written by **10 parallel Claude Code helper agents** with 100 products each, in two batches of 50. Each agent read its real CSV rows and followed the same committed spec, `data/ENRICHMENT-GUIDE.md` (priorities, counts, don'ts, worked examples), then validated its own part with the check script until it passed. The parts were merged in id order. There is no AI at runtime and no API key (D2, D11).
- **Process lesson:** the agents shared one scratch folder, and one agent's helper script overwrote another's. The output files were separate, so nothing was lost (the full check proves every id is present), but parallel agents should get separate folders.

## 5. Validation (`bin/check-enrichment`)

It fails (exit 1) on: invalid JSON or keys other than exactly `id, tags_he, tags_en, issues` · unknown, duplicate, missing or out-of-order ids · `tags_he` outside 8–20, `tags_en` outside 4–12 · a tag shorter than 2 or longer than 40 characters, or with surrounding spaces · Latin letters in `tags_he`, Hebrew in `tags_en`, uppercase in `tags_en` · duplicate tags · banned filler (מוצר, איכותי, מבצע, best, sale, product, quality) · malformed `issues`. Exit 2 means a usage or IO error. The checker itself was tested with a planted bad file (10 kinds of error caught) and a good file.

Final real output:
```
✓ enrichment.jsonl: 1000 products valid (all 1000 catalog ids covered) · avg tags he 10.7 / en 5.1 · data issues reported: 31
```

## 6. Human review: what was wrong and what changed

| Check | Finding | Change |
|---|---|---|
| Agent self-reports | Tags implying **health claims** the row doesn't make: "קורונה" (oximeter P0780), "אנטי אייג׳ינג" (LED mask P0730), "כאבי מחזור" (heating pad P0769), "דיכאון חורף" (sunrise alarm P0797) | Removed: a false health claim is worse than a missed search |
| Agent self-reports | Series names not in the CSV ("elitebook", "yoga", "blue yeti", "tapo"…) | Agents removed most; "lenovo tab" (P0312) removed in review. Kept only lines that are the whole product family (roomba, galaxy tab, echo show) |
| Example-query coverage | P0684 travel vacuum bags tagged "בלי שואב אבק" → would match "שואב אבק" | Removed |
| Example-query coverage | Row 5 (dog hair on the sofa) expects P0207, but the hand vacuum "for sofas" had no hair tag | Added "שערות על הספה" / "hair on sofa" |
| Example queries vs tags | אייפון/iphone on P0401, P0407, P0413 (+3 accessories as "מטען לאייפון", "iphone case": correct, ranking decides) · dog hair: P0950, P0207, P0203, P0225 · cooking gift: 24 products · coffee gift: 23 · "מיקרוגל" on P0279, P0285 (typo-fallback target) | — |
| Relevance test, S3 (row 4 "מתנה למישהו שאוהב לבשל") | No tag had "לבשל" or "אוהב": the 24 cooking-gift tags say "מתנה לחובבי בישול", and the verb never reaches the noun. Kitchen items matched only "מתנה", so coffee gift sets won | Added "אוהב לבשל" to the 72 products in the 5 cooking subcategories (small appliances, pots and pans, baking, knives, gadgets), not serving or storage. Check script still passes (avg HE tags 10.7 → 10.8) |
| Random spot-check, 20 products (seed 2026) | All accepted by the developer. Two noted as harmless: P0525 camping projector also "מקרן לבית"; P0917 toy kitchen "משחק בישול" (no cooking-gift tag, ranks below real kitchen items) | None |
| Meaning pass, 2026-11-27 (after QA): 20 need-queries written with their expected subcategories **before** running (HE/EN, verbs, gifts, situations) | Before: 6 good, 7 partial, 7 wrong by subcategory. By hand, 4 of the "wrong" were fine (gamer, back pain, baby, kid: the right products sit in other subcategories), and 6 real misses shared one cause: **tags are nouns, people search with verbs**. "להתאמן בבית" → webcams ("ביתי"); "לקרר את הבית בקיץ" → a summer blanket; "לשמור על אוכל טרי" → dining chairs ("פינת אוכל"); "ציוד לטיול" → a stroller ("טיולון"); "gift for a runner" → a hallway runner rug; "birthday gift for teenager" → gift boxes for adults | 308 need tags added to 82 product links in 9 groups, by name, nothing removed: home workout (P0801–P0813), cooling (10 fans/ACs) + heating (5 heaters, for symmetry), keeping food fresh (12 storage items), car cleaning (4), hiking gear (P0839–P0851 + P0894), teen gift (11 consoles/gaming/audio), coffee at work (5 travel/desk mugs), runners (8). `bin/check-enrichment` passes (avg HE 10.8 → 10.9, EN 5.1 → 5.3). After: 19 of 20 good by hand; the only flaw is a model-car kit at #5 for "לנקות את הרכב" ("הרכבה" starts with "רכב"), below the 4 car-cleaning products. Five of them became relevance rows 22–26: red on the old tags, green on the new. **Regression check:** top 5 for 68 other queries (the brief's examples, QA queries, and words the new tags share: קפה, מתנה, רכב, ריצה, אוכל, טיול, gift, office) compared old vs new. 26 changed: most are reordering among relevant products, or improvements (`heating` 1 → 6 heaters, `fresh` now food storage, `running shoes` now running gear above a shoe rack, since the catalog sells no running shoes). Two small losses were accepted: `טיול` has a bike computer at #4 (the camping items' longer tag text lowers their BM25 score), and `runner rug` has a running bag at #4 below 3 rugs. A duplicate plural tag (ציוד לטיולים) was removed because the plural variants already cover it |

## 7. Known weaknesses
- Hebrew verb forms aren't linked to their nouns (לבשל ≠ בישול, להתאמן ≠ כושר); the tags carry the common need phrasings instead (§6). The meaning pass covered the verbs found in 20 realistic queries, not every verb a visitor might use.
- Tags cover typical phrasings. An unusual phrasing that no tag or description word covers will miss (README: embeddings under "With more time").
- Quality was verified by sampling (20 random products + every example query + every agent-flagged doubt), not by reading all 10,700 tags.
- Prices are synthetic and blind to the product within a subcategory (§3).
