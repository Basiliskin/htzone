# Stack & commands

Project-specific input for the generic role skills in `.claude/skills/`. Commands were confirmed in S0. Update this file when they change.

## Stack
| Concern | Choice |
|---|---|
| Language | PHP 8.3, `declare(strict_types=1)` everywhere, Composer, PSR-4 autoloading, no framework |
| DB | SQLite + FTS5 (full-text index, prefix matching, BM25 ranking with field weights), accessed via PDO prepared statements |
| Migrations | Numbered SQL files in `migrations/` + a small PHP runner, tracked in a `schema_migrations` table |
| Data | `assets/catalog_no_prices.csv` (source, 1000 products, no prices) + `data/enrichment.jsonl` (Claude-written HE/EN tags, committed) + prices generated with a fixed seed per category, stored in agorot |
| Frontend | Plain HTML + modern CSS + vanilla JS modules, no build step, served by PHP; RTL, mobile-first |
| Static analysis | PHPStan (max level), PHP CS Fixer (PSR-12) |
| Tests | PHPUnit (unit, integration, relevance), Playwright (one E2E) |
| Runtime | `php:8.3.37-apache-trixie` (mod_php, SQLite 3.46.1) in Docker. One `Dockerfile`, stages `dev` (Composer + Xdebug, project mounted) and `prod` (code baked in, OPcache, read-only code) (D18, D26). Apache runs as `www-data` on port **8080**; DB built inside the image in S1 |
| CI | GitHub Actions, inside the same `dev` image (D28): composer validate → format check → PHPStan → PHPUnit → enrichment check → build `prod` image + smoke test (200, CSP header, non-root) → Playwright E2E against that container (D38) |
| Hosting | Render free web service (Docker), keep-awake ping to `/health` |

## Commands (confirmed in S0)
| Task | Command |
|---|---|
| Start local (first run builds the image) | `docker compose up -d --build` (service `app`, project mounted at `/app`) |
| Install deps | `docker compose exec app composer install` |
| All checks at once | `docker compose exec app composer check` (= format check + PHPStan + PHPUnit) |
| Migrate only | `docker compose exec app php bin/migrate` (safe to repeat) |
| Migrate + import catalog | `docker compose exec app php bin/import [--csv=… --enrichment=…]` (exit 1 = invalid input, all problems listed, nothing written) |
| Check enrichment | `docker compose exec app php bin/check-enrichment` |
| All PHP tests | `docker compose exec app vendor/bin/phpunit` (suites: `--testsuite Unit\|Integration\|Relevance`) |
| Static analysis | `docker compose exec app vendor/bin/phpstan analyse` |
| Format check / fix | `docker compose exec app vendor/bin/php-cs-fixer fix --dry-run --diff` / `... fix` |
| Step debugging (D27) | `XDEBUG_MODE=debug docker compose up -d`, then VS Code "Listen for Xdebug (Docker)" (`.vscode/launch.json`, port 9003, PHP Debug extension). Back to normal: `docker compose up -d` |
| JS unit tests (D36) | `npm test` (= `node --test`, Node ≥ 22, no `npm install` needed) |
| E2E (S6, D37–D38) | Once: `npm ci && npx playwright install chromium`. Then `npm run e2e` (= `playwright test`, against `http://localhost:8080`; set `E2E_BASE_URL` for another server, e.g. the prod image on 8081). Failure trace: `npx playwright show-trace test-results/…/trace.zip` |
| Build / run prod image | `docker build -t htzone .` / `docker run --rm -p 8080:8080 htzone` |
| Open | http://localhost:8080 (compose maps 8080 → container 8080) |
| Logs | `docker compose logs -f app` |

## Hooks (`.claude/settings.json`)
- `UserPromptSubmit` → `.claude/hooks/log-prompt.mjs`: appends each prompt to `docs/ai-log/PROMPTS.md` (skips `/interview`).
- `PostToolUse` (Edit/Write) → `.claude/hooks/php-check.mjs`: for edited `.php` files, runs PHP CS Fixer + PHPStan in the `app` container; silently skips if the container or `vendor/` isn't there yet; reports PHPStan errors back to Claude.
