# AgriPrice Carmen

A municipal agricultural price tracker for Carmen: market price transparency for
farmers and buyers, plus a light farmer-to-buyer crop marketplace.

## The core model

`products` is the shared **type hub** — one row per commodity ("Rice"). Two
independent sides FK into it and never into each other:

- **Observation side** — `price_reports`: a commodity's observed rate at a
  `market` (a *place*, never an actor). Aggregated daily into `price_history`
  for trend charts.
- **Instance side** — `crops`: a farmer's physical batch of a product, owned by
  the farmer (`farmer_id`), carrying the farmer's own **asking price**.

Those are the two distinct price concepts in the system: the market rate
(nobody's personal price) and the farmer's ask. Both are normalized to the
product's base unit via `product_units.factor_to_base` (per product — a sack of
rice is 50 kg, a sack of corn is 57 kg), which is what makes them directly
comparable: the app shows every asking price as % above/below the going market
rate.

## Roles

| Role   | Can do |
|--------|--------|
| admin  | Record market price reports (reference data is seeded) |
| farmer | List/edit/delete their own crops, see their ask vs the market rate |
| buyer  | Browse available crops with market-rate comparison |

Everyone sees the dashboard, product trends, markets, and the report log.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

SQLite by default — no DB server needed. The seeder creates 30 days of price
reports (some deliberately in sacks/crates to exercise unit normalization),
daily history snapshots, and demo crops.

## Demo accounts (password: `password`)

- `admin@agriprice.test` — admin
- `khaya@agriprice.test`, `rynna@agriprice.test` — farmers
- `buyer@agriprice.test` — buyer

## Maintenance

`php artisan prices:snapshot [date]` aggregates a day's reports into
`price_history`; it is scheduled daily at 18:00 (`routes/console.php`).

## Tests

```bash
php artisan test
```

Feature smoke tests cover every page, role boundaries, unit normalization
(₱2,500/sack of rice → ₱50/kg), rejection of unconfigured units, and crop
ownership enforcement.
