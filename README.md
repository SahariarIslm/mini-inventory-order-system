# Mini Inventory & Order System

Laravel API + React (TypeScript) technical assessment for Akaar IT Ltd.

> **The scenario driving the design:** two customers try to buy the last unit
> of the same product at the same instant. Exactly one order must succeed —
> **by design, not by luck** — and a customer who double-clicks or retries
> after a timeout must never end up with two orders.

- **Backend:** Laravel 13 (PHP 8.4), MySQL 8, Redis (via `predis`), Sanctum token auth
- **Frontend:** React 19 + TypeScript, Vite, React Router
- **Runtime:** Docker Compose — nothing needs to be installed locally besides Docker

---

## Quick start

```bash
# 1. Environment files (the defaults work together as-is)
cp .env.example .env
cp backend/.env.example backend/.env

# 2. Build, install PHP dependencies, generate the app key
docker compose build
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate

# 3. Start everything, then create the schema and demo data
docker compose up -d
docker compose exec app php artisan migrate --seed
```

Open **http://localhost:5173** and sign in with a demo account (the login page
has one-click buttons for both):

| Role  | Email               | Password   | Can…                                                     |
|-------|---------------------|------------|----------------------------------------------------------|
| Admin | `admin@example.com` | `password` | manage products, adjust stock, see and cancel all orders |
| Staff | `staff@example.com` | `password` | browse products, place orders, see/cancel their own      |

The seed data includes **`DEMO-LAST-UNIT`** — a product with exactly one unit
in stock, for trying the race by hand (see below).

| Service    | URL / port              | Notes                                              |
|------------|-------------------------|----------------------------------------------------|
| Frontend   | http://localhost:5173   | Vite dev server; proxies `/api/*` to Laravel        |
| API        | http://localhost:8000   | `php artisan serve`                                |
| MySQL      | `localhost:3307`        | databases `mios` (dev) and `mios_testing` (tests)  |
| Redis      | `localhost:6379`        | cache / queue / session                            |

> **Why step 2 installs Composer deps separately:** `./backend` is bind-mounted
> over the image's copy for live editing, which hides the `vendor/` baked into
> the image. The frontend container installs its own `node_modules` on start.

---

## How the "last unit" guarantee works

### Placing an order

`App\Services\OrderService::place()` runs in one database transaction:

1. **Claim the idempotency key.** The `orders` row is inserted *first*. A
   unique index on `(user_id, idempotency_key)` makes this an atomic claim: a
   concurrent retry with the same key blocks on that insert, then fails with a
   duplicate-key error once the first request commits — and is handed the
   original order instead.
2. **Lock each product row** with `SELECT … FOR UPDATE`
   (`StockService::adjust()`), in **ascending product id order**, so two
   multi-item orders can never lock the same rows in opposite orders and
   deadlock.
3. **Check and decrement against the locked value** — never against a value
   read earlier. A second buyer's `FOR UPDATE` waits until the first buyer's
   transaction commits, then reads the already-decremented stock.
4. If any line is short, **the whole transaction rolls back** — including the
   order row, so the idempotency key is free for a genuine retry — and the API
   returns `409 Conflict` with `{available, requested}`.

```
 Buyer A                               Buyer B
 ───────                               ───────
 BEGIN                                 BEGIN
 INSERT order (key A)                  INSERT order (key B)
 SELECT stock … FOR UPDATE  → 1
                                       SELECT stock … FOR UPDATE   ⏳ waits for A
 UPDATE stock = 0
 COMMIT  ✅ 201 Created
                                       → reads 0 (A's committed value)
                                       ROLLBACK  ❌ 409 Insufficient stock
```

**Why pessimistic locking rather than optimistic (version columns)?** This is
the textbook case for it: many requests contending for *one hot row*, with a
very short critical section. Optimistic locking would make most contenders
fail and retry exactly when contention peaks; a row lock simply queues them
for a few milliseconds.

**Belt and braces:** `products.stock_quantity` is `UNSIGNED`, so even a logic
bug could not drive stock negative — MySQL would reject the write.

### Idempotency (retries and double-clicks)

Idempotency is a **separate mechanism** from the stock lock: the lock stops
*different* requests from overselling; the key stops *the same* request from
being applied twice.

- Clients send an `Idempotency-Key` header with `POST /api/orders` (required).
- A replay returns the original order with **`200 OK`** and
  **`Idempotent-Replayed: true`**; a new order returns `201`.
- Keys are **scoped per user**, so one user can never collide with — or read
  back — another user's order by reusing their key.
- The frontend generates one key per *cart version*: retrying an unchanged
  cart (e.g. after a network error) reuses it; changing the cart creates a new
  one.

### Other stock changes use the same locking

- **Admin stock adjustments** (`POST /api/products/{id}/stock-adjustments`)
  are *relative* (`+25` / `-4`), applied to the locked row. Absolute writes
  ("set stock to 10") are rejected, because they would silently overwrite
  concurrent order decrements (a lost update).
- **Cancelling an order** locks the *order* row first, so two concurrent
  cancels can't both return the stock; then returns each line through
  `StockService` in the same ascending lock order. Cancelling twice is a
  harmless no-op.

---

## Tests

Two suites, both run inside Docker:

```bash
# Main suite — 76 tests: auth, products, stock, orders, idempotency, policies.
# Runs on in-memory SQLite, so it's fast and never touches your data.
docker compose exec app php artisan test

# Concurrency suite — 5 tests against real MySQL (the mios_testing database).
docker compose exec app php artisan test -c phpunit.concurrency.xml
```

**Why a separate MySQL suite?** SQLite ignores `SELECT … FOR UPDATE`, so it
can prove the *logic* but not the *locking*. The concurrency suite
(`tests/Concurrency/OrderRaceTest.php`) spawns **separate PHP processes, each
with its own MySQL connection**, waits until all are booted and connected, then
releases them simultaneously through a file barrier:

| Test | Asserts |
|------|---------|
| Two buyers, one unit | exactly one `201`, one `409`; stock 0 |
| Ten buyers, three units | exactly three orders; stock 0; never oversold |
| Five concurrent retries, same key | one order created, four replays of the *same* order; stock charged once |
| Opposite item order, shared products | no deadlock; stock accounting exact |
| Five concurrent cancels | stock returned exactly once |

The suite was also checked against deliberately broken code: with
`lockForUpdate()` removed, both buyers "won" the last unit and 10 of 10
buyers bought from a stock of 3; without the order-row lock, five concurrent
cancels returned the stock five times.

**Safety net:** `tests/TestCase.php` refuses to run unless the connection is
SQLite `:memory:` or a MySQL database named `*_testing`, so a misconfigured
environment can never wipe the dev database. (Docker Compose exports DB
settings as real environment variables, which Laravel reads ahead of
phpunit's `<env>` — hence the `<server>` overrides in both phpunit configs.)

Frontend checks: `docker compose exec frontend npm run build` (type-check +
build) and `docker compose exec frontend npm run lint`.

---

## Trying the race by hand

Log in as **Admin** in one browser and **Staff** in another (or a private
window), add `DEMO-LAST-UNIT` to both carts, and place both orders. The first
succeeds; the second gets *"Not enough stock … only 0 left. Nothing was
ordered."* Cancelling the winning order puts the unit back.

---

## API

All endpoints are under `/api` and return JSON. Authenticate with
`Authorization: Bearer <token>` from `/api/login`. Unauthenticated requests get
`401`; validation errors `422` with per-field `errors`.

| Method | Path                                    | Who         | Notes |
|--------|-----------------------------------------|-------------|-------|
| POST   | `/login`                                | anyone      | `{email, password}` → `{user, token}` |
| POST   | `/logout`                               | auth        | revokes the current token only |
| GET    | `/me`                                   | auth        | current user |
| GET    | `/products`                             | auth        | paginated (15) |
| GET    | `/products/{id}`                        | auth        | |
| POST   | `/products`                             | admin       | includes initial `stock_quantity` |
| PATCH  | `/products/{id}`                        | admin       | `stock_quantity` is rejected here |
| DELETE | `/products/{id}`                        | admin       | order history keeps a snapshot |
| POST   | `/products/{id}/stock-adjustments`      | admin       | `{quantity: ±N}`; `409` if it would go negative |
| GET    | `/orders`                               | auth        | staff: own orders; admin: all |
| GET    | `/orders/{id}`                          | owner/admin | |
| POST   | `/orders`                               | auth        | `Idempotency-Key` header; `{items: [{product_id, quantity}]}`; `201` / `200` replay / `409` |
| POST   | `/orders/{id}/cancel`                   | owner/admin | returns stock; idempotent |

---

## Project structure

```
backend/
  app/Services/          OrderService (place/cancel), StockService (locked adjustments)
  app/Http/Requests/     validation + authorization per endpoint
  app/Http/Resources/    response shaping (prices as exact decimal strings)
  app/Policies/          ProductPolicy, OrderPolicy
  app/Exceptions/        InsufficientStockException → 409
  tests/Feature/         main suite (SQLite)
  tests/Concurrency/     real-MySQL race suite + worker script
frontend/src/
  api/                   typed fetch client, per-resource API modules
  auth/, cart/           context providers
  pages/                 login, products, cart/checkout, orders
docker/mysql/init/       creates mios_testing on first MySQL start
```

---

## Design decisions & trade-offs

- **Business logic lives in services**, not controllers or models. Form
  Requests handle validation and authorization; API Resources shape responses.
- **Money is never a float:** `DECIMAL` columns, decimal-string JSON,
  `bcmath` on the server, integer cents for the cart estimate in the browser.
- **Order lines snapshot product name, SKU and price**, so price changes and
  product deletion never rewrite order history.
- **Roles are a DB-level `enum('admin','staff')`** — a cheap correctness
  guarantee for two fixed roles; a permissions system would be overkill here.
- **`predis` rather than `phpredis`**, avoiding a C extension in the image.
- **`artisan serve` rather than nginx + php-fpm**, for setup simplicity. Fine
  for an assessment; production would use php-fpm behind a web server.
- **The Vite dev server proxies `/api`**, so the SPA is same-origin and needs
  no CORS configuration.
- **The token is kept in `localStorage`** — simple and survives reloads, but
  readable by any injected script. Sanctum's cookie-based SPA auth would
  remove that XSS exposure at the cost of CSRF handling.

### Known limitations / next steps

- A replay with the same idempotency key but a *different* body returns the
  original order rather than an error (storing a request hash would detect it).
- No stock movement history / audit log.
- The cart is in memory and is lost on page reload.
- No frontend unit tests yet (the backend suites cover the behaviour; the
  frontend is type-checked and linted).
