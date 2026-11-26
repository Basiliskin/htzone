# Enrichment guide (D2, D23)

How search tags are written for each product in `assets/catalog_no_prices.csv`. The output is `data/enrichment.jsonl`, and the import loads it into the `tags` search column (weight 6, between brand and category). Tags exist so that a Visitor finds the product **by the words a real Israeli shopper would type**, including words that aren't in the product's own name or description.

Tags were written by Claude Code helper agents, 100 products each (in two batches of 50), from this guide. They were then validated by `bin/check-enrichment` and spot-checked by a human (see `docs/data.md`).

## Output format: one JSON object per line, in id order

```json
{"id":"P0001","tags_he":["מטגן אוויר","מטגנים","טיגון בלי שמן","צ׳יפס","כנפיים","מתנה לחובבי בישול","מתנה לבית חדש"],"tags_en":["air fryer","airfryer","oil free fryer","philips","gift for cooks"],"issues":["name typo: מטהג should be מטגן"]}
```

| Field | Rules |
|---|---|
| `id` | Copied exactly from the CSV. Never invent or skip ids. |
| `tags_he` | **8–20** tags. Hebrew letters (plus digits, spaces, `-`, `׳`, `"`) only, **no Latin letters**. Each 2–40 characters. |
| `tags_en` | **4–12** tags. Latin letters (plus digits, spaces, `-`) only, **no Hebrew letters**. Lowercase. Each 2–40 characters. |
| `issues` | Usually `[]`. Add a short English note only for a **real data problem in the CSV row**: a typo in the name, a description that doesn't match the name or category, or attributes that contradict the name. Don't comment on style. |

No duplicate tags within a product (case-insensitive). Don't repeat the exact product name as a tag, because the name is already searched. Single words and short phrases of 1–4 words work best.

## What to include in `tags_he` (in roughly this priority)
1. **What it is, in everyday words**: the common name and its synonyms. For example "שואב אבק" also gets "שואב" and "שואב אבק אלחוטי". Use the correct spelling, especially if the CSV name has a typo (מטהג → **מטגן**).
2. **Plural and construct forms of the main nouns**: "שואבי אבק", "מטגנים", "כוסות קפה", "סירים".
3. **Hebrew spellings of foreign brand and model names**: Dyson → "דייסון", iRobot → "איירובוט", Nespresso → "נספרסו", Apple → "אפל". Apple smartphones get **"אייפון"** and "טלפון אפל".
4. **Needs and use-cases**, the way a shopper describes a problem: "ניקוי שערות כלב", "שערות על הספה", "טיגון בלי שמן", "קפה בבוקר", "עבודה מהבית", "גב תפוס".
5. **Gift intents**, where reasonable: "מתנה ל…" followed by who would enjoy it, for example "מתנה לחובבי בישול", "מתנה למי שאוהב קפה", "מתנה לגיימר", "מתנה לבעלי כלבים", "מתנה ליום הולדת". Not every product is a gift (a cable usually isn't), so use your judgement.
6. **Who it suits / the occasion**: "לסטודנטים", "למשפחה", "לתינוקות", "לטיולים", "לדירה קטנה".
7. **The material or feature people actually search for**: "אלחוטי", "נטען", "נירוסטה", "עמיד במים".

## What to include in `tags_en`
- The English product type and its synonyms: "robot vacuum", "vacuum cleaner", "coffee machine", "espresso".
- The brand in lowercase, if there is one, plus a well-known series or model **only if it's clearly the product**. Apple smartphones get **"iphone"**, Apple laptops get "macbook", Samsung phones get "galaxy". Never invent model numbers such as "iphone 16" (the catalog doesn't say which model it is).
- One or two needs or gift intents in English: "pet hair", "gift for coffee lovers".

## Don'ts
- Don't add tags about things the product isn't, or doesn't do according to its name, description and attributes.
- Don't add generic filler ("מוצר", "איכותי", "best", "sale", "מבצע").
- Don't add prices, stock or shipping words.
- Don't copy the whole description.
- Don't add tags that would make the product a false match for common queries. For example, a vacuum-sealer for food must **not** get "שואב אבק" (it gets "ואקום למזון", "איטום שקיות").

## Worked examples

```json
{"id":"P0401","tags_he":["אייפון","טלפון אפל","סמארטפון","טלפון חכם","טלפונים","פלאפון","נייד","מכשיר סלולרי","טלפון עם טעינה אלחוטית","מתנה לבני נוער"],"tags_en":["iphone","apple phone","smartphone","mobile phone","cell phone"],"issues":[]}
{"id":"P0950","tags_he":["מברשת לכלב","מברשת לחתול","הסרת שיער","שערות כלב","שערות חתול","שערות על הספה","שיער של חיות","נשירת שיער","טיפוח כלבים","מברשות","מתנה לבעלי כלבים"],"tags_en":["pet hair","pet brush","dog brush","cat brush","deshedding","furaway"],"issues":[]}
{"id":"P0078","tags_he":["מכשיר ואקום למזון","ואקום למזון","איטום שקיות","אורז ואקום","שימור מזון","הקפאה","מטבח","סו ויד","מתנה לחובבי בישול"],"tags_en":["vacuum sealer","food sealer","food storage","foodsaver","sous vide"],"issues":[]}
```

## Self-check before saving a batch
- Every id in your range is present exactly once, in order.
- `bin/check-enrichment --part=<your file>` passes (see `docs/stack.md`).
