# Selling Platform — agent guide

Multi-marketplace selling tool. v1: list **books on Amazon**, architected so any
marketplace/item type plugs in behind a stable interface.

## Stack & environment
- Laravel 12 / PHP 8.4, Blade + Tailwind 4 + Vite 7, MySQL 8, Docker + queue
  workers + scheduler. Server-rendered first (Alpine for the few interactive bits).
- **This host *is* `192.168.0.147`** (shared dev/staging). Never run
  `composer`/`artisan`/`npm` on the host — run them **inside the container**:
  `docker compose exec app php artisan …`. Build assets via a node container.
- Containers: `sp_app` (php-fpm) · `sp_web` (nginx, host port **8092**) ·
  `sp_worker` (queue) · `sp_scheduler` · `sp_db` (mysql:8). Network
  `sellingplatform_sp`. Fronted by the shared Caddy at `https://salesplatform.horler.net`.
- Bring up dev: `docker compose -f docker-compose.yml -f docker-compose.local.yml up -d`
  (`.147` deploy uses the base file only). After PHP/Blade changes restart the PHP
  containers (OPcache); after recreating `app`, restart `web` (nginx caches its IP).

## Core flow
Scan/type ISBN → Open Library fetch (cached in `products`) → pick condition →
suggested price → save to `inventory_items` → publish to Amazon → orders sync back
to `sales`.

## Key seams (depend on these, not concretes)
- `App\Channels\Contracts\MarketplaceChannel` — every marketplace implements it;
  resolved by `ChannelManager` from `config/channels.php`. `AmazonChannel` is the
  only impl. Services/jobs/UI never touch Amazon directly.
- `App\Services\Pricing\PricingStrategy` — `ManualMultiplierStrategy` (reference
  price × per-condition multiplier) and `CompetitivePricingStrategy` (live Amazon).
  Resolved by `PricingService` from `config/pricing.php`.
- `App\Models\Concerns\BelongsToUser` — row-level multi-seller isolation (global
  scope + auto user_id). **No scope in queue/console context** — jobs scope
  explicitly by account/user.

## Layout
- `app/Channels` — interface, DTOs (`Data/`), `AmazonChannel`, `ChannelManager`.
- `app/Services` — `OpenLibraryService`, `InventoryService`, `ListingService`,
  `OrderSyncService`, `Pricing/`, `Amazon/AmazonOAuth`.
- `app/Jobs` — `PublishListingJob`, `PollListingStatusJob` (async publish + poll).
- `app/Console/Commands/SyncOrders` — `marketplace:sync-orders`, scheduled every 15m.
- `app/Enums` — `Condition`, `InventoryStatus`, `ListingStatus`, `MarketplaceAccountStatus`.

## Auth
Two seeded users only (`AllowedUsersSeeder`); no public registration. Each sets a
password on first use (`/first-use`).

## Amazon SP-API
`jlevers/selling-partner-api` via `highsidelabs/laravel-spapi`, used directly
(not the package's models). Config in `config/amazon.php` + `.env`
(`SPAPI_APP_ID`, `SPAPI_LWA_CLIENT_ID/SECRET`, `AMAZON_MARKETPLACE=GB`,
`SPAPI_SANDBOX`). Per-seller OAuth stores an encrypted refresh token on
`marketplace_accounts`. **`AmazonChannel` request bodies (esp. `putListingsItem`
for the BOOK product type) are best-effort and need sandbox validation.** The pure
`parse*` helpers are unit-tested.

## Testing
`docker compose exec app php artisan test`. Runs against a dedicated **MySQL**
test DB `sellingplatform_test` (not SQLite). `phpunit.xml` forces
`APP_ENV=testing` (`force="true"` — the compose env would otherwise win and break
CSRF skipping). Channel-dependent code is tested via `Tests\Support\FakeChannel`
(`UsesFakeChannel` trait rebinds `ChannelManager`/`PricingService`).
