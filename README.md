# Selling Platform

A self-hosted tool to list and sell items on online marketplaces. v1 lists
**books on Amazon**, but is built around a marketplace-agnostic interface so eBay,
Facebook, etc. can be added without touching the core.

The core flow: **scan/type an ISBN → fetch book data (Open Library) → pick a
condition → get a suggested price → save to inventory → publish to Amazon →
auto-reconcile sales back into a sales history.**

## Stack

Laravel 12 / PHP 8.4 · Blade + Tailwind 4 + Vite 7 (Alpine for interactive bits) ·
MySQL 8 · Docker (app / nginx / queue worker / scheduler / db) · jlevers SP-API.
Installable PWA with in-browser barcode scanning (BarcodeDetector + ZXing fallback).

## Local development (on the `.147` host)

All tooling runs **inside containers** — never on the host.

```bash
# Bring up the stack (dev override exposes MySQL on 3307, debug on)
docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build

# First run: key, migrate, seed the two allowed users
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# Build front-end assets (node container — no host npm)
docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -v "$PWD":/app -w /app node:22-alpine sh -lc 'npm install && npm run build'
```

App: `http://192.168.0.147:8092` (LAN) or `https://salesplatform.horler.net`
(via Caddy; required for camera + PWA, which need HTTPS).

After PHP/Blade changes restart the PHP containers (OPcache); after recreating
`app`, also restart `web` (nginx caches the app container IP).

## Accounts

No public sign-up. Two users are seeded (`AllowedUsersSeeder`); each sets their
own password the first time at `/first-use`.

## Amazon setup

Set in `.env` (see `.env.example`):

```
SPAPI_APP_ID=          # SP-API application id
SPAPI_LWA_CLIENT_ID=   # Login with Amazon client id/secret
SPAPI_LWA_CLIENT_SECRET=
AMAZON_MARKETPLACE=GB
SPAPI_SANDBOX=true     # validate against the sandbox before going live
SPAPI_DRAFT_APP=true   # version=beta in the consent URL until the app is published
```

Then **Marketplaces → Connect Amazon** runs the per-seller OAuth flow and stores an
encrypted refresh token. The redirect URI must be HTTPS and match the SP-API app
config (`https://salesplatform.horler.net/marketplace/amazon/callback`).

> The `AmazonChannel` request payloads (especially `putListingsItem` for the BOOK
> product type) are best-effort and should be validated against the SP-API sandbox
> before live submission.

## Pricing data (Keepa)

While SP-API access is pending (or alongside it), competitive pricing and market
monitoring use [Keepa](https://keepa.com/#!api) — a ToS-safe Amazon price source
(no scraping). Set in `.env`:

```
KEEPA_API_KEY=        # your Keepa API key
KEEPA_DOMAIN=2        # 2 = amazon.co.uk
```

Then the **Live price** button on an item undercuts the lowest competitive price,
and `keepa:refresh-prices` (scheduled daily) records lowest new/used + sales rank
into a price history shown on the item page. Without a key these features stay
dormant (manual pricing still works).

## Testing

```bash
docker compose exec app php artisan test
```

Runs against the dedicated MySQL DB `sellingplatform_test`. Amazon-dependent code
is covered with a fake channel, so no credentials are needed for CI.

## Deploying

Helper scripts live in `_deploy/` (git-ignored). See `_deploy/README.md`.

More detail for contributors/agents: see [CLAUDE.md](CLAUDE.md).
