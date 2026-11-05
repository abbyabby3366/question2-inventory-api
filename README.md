# Product Inventory API (Laravel 12)

A REST API for managing products, categories and suppliers. Authentication uses **Laravel Sanctum**
tokens. The product list supports filtering, sorting and pagination, and is cached and rate limited.

| | |
|---|---|
| Framework | Laravel **12.71.0** on PHP 8.2+ |
| Auth | Sanctum personal access tokens (Bearer) |
| DB | SQLite locally (zero setup) / MySQL 8 in Docker |
| Docs | Swagger UI at **`/docs`** ([`public/openapi.yaml`](public/openapi.yaml)) |
| Tests | 23 tests / 130 assertions (`php artisan test`) |

---

## 1. Setup

### Option A: Local (PHP 8.2 + Composer)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # Windows: type nul > database\database.sqlite
php artisan migrate --seed
php artisan serve                        # http://127.0.0.1:8000  -> Swagger UI at /docs
```

### Option B: Docker (nginx + PHP-FPM + MySQL 8.4 + Redis)

```bash
docker compose up -d --build             # http://localhost:8080  -> Swagger UI at /docs
docker compose exec app php artisan test
```

The container entrypoint runs migrations and seeds demo data on first start. The seeder is idempotent.

### Demo account (seeded)

| Email | Password |
|---|---|
| `demo@example.com` | `password` |

### Run the tests

```bash
php artisan test
```

The tests run against in-memory SQLite (see `phpunit.xml`), so no database setup is needed.

---

## 2. Using the API

```bash
# 1) Get a token
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"demo@example.com","password":"password","device_name":"curl"}'

# 2) Call protected endpoints
curl "http://127.0.0.1:8000/api/products?category_id=1&min_price=10&max_price=500&stock_level=low_stock&sort=-price&per_page=10" \
  -H "Authorization: Bearer <token>" -H "Accept: application/json"
```

### Endpoints

| Method | URI | Description |
|---|---|---|
| POST | `/api/auth/register` | Register and receive a token (rate limited) |
| POST | `/api/auth/login` | Exchange credentials for a token (rate limited) |
| GET | `/api/auth/me` | Current user |
| POST | `/api/auth/logout` | Revoke the current token |
| GET | `/api/products` | List products: filters, sorting, pagination |
| POST | `/api/products` | Create a product (+ supplier links) |
| GET | `/api/products/{id}` | Show a product |
| PUT/PATCH | `/api/products/{id}` | Partial update (+ optional supplier sync) |
| DELETE | `/api/products/{id}` | **Soft** delete → `204` |
| POST | `/api/products/{id}/restore` | Restore a soft-deleted product |
| GET | `/api/categories` | Categories with product counts (for filter UIs) |
| GET | `/api/suppliers` | Suppliers |

All `/api/*` routes except register and login need `Authorization: Bearer <token>`.

### Product list filters (`GET /api/products`)

| Param | Example | Notes |
|---|---|---|
| `category_id` | `3` | Must exist |
| `min_price` / `max_price` | `10` / `99.90` | Inclusive; `max_price >= min_price` |
| `stock_level` | `in_stock` \| `low_stock` \| `out_of_stock` | `low_stock` = 1..`low_stock_threshold` |
| `min_stock` / `max_stock` | `1` / `50` | Raw quantity range |
| `search` | `mouse` | Name or SKU contains |
| `sort` | `price`, `-price`, `name`, `stock_quantity`, `created_at` | `-` = descending, default `-created_at` |
| `per_page` / `page` | `15` / `2` | `per_page` 1-100 |

Invalid filters return `422` with field errors. They are never silently ignored.

### Create product example

```json
POST /api/products
{
  "category_id": 1,
  "name": "Wireless Mouse",
  "sku": "wm-001",
  "price": 49.90,
  "stock_quantity": 120,
  "low_stock_threshold": 10,
  "suppliers": [{ "id": 1, "cost_price": 30.00 }, { "id": 2 }]
}
```

Response `201`:

```json
{
  "data": {
    "id": 56, "name": "Wireless Mouse", "sku": "WM-001", "price": "49.90",
    "stock_quantity": 120, "low_stock_threshold": 10, "stock_status": "in_stock",
    "category": { "id": 1, "name": "Electronics", "slug": "electronics", "description": "..." },
    "suppliers": [{ "id": 1, "name": "...", "email": "...", "phone": "...", "cost_price": "30.00" }],
    "created_at": "2026-11-05T10:15:00+08:00", "updated_at": "2026-11-05T10:15:00+08:00"
  }
}
```

---

## 3. Requirements checklist

| Requirement | Where |
|---|---|
| Models `Product`, `Category`, `Supplier`; Product **belongsTo** Category, **belongsToMany** Supplier | [`app/Models`](app/Models). The pivot `product_supplier` carries `cost_price` via the [`ProductSupplier`](app/Models/ProductSupplier.php) pivot model |
| Full CRUD for products + filtering (category, price range, stock level) + pagination | [`ProductController`](app/Http/Controllers/Api/ProductController.php) |
| Sanctum authentication | [`AuthController`](app/Http/Controllers/Api/AuthController.php), [`routes/api.php`](routes/api.php) |
| Form Request validation | [`app/Http/Requests`](app/Http/Requests): `IndexProductRequest`, `StoreProductRequest`, `UpdateProductRequest`, `LoginRequest`, `RegisterRequest` |
| API Resources | [`app/Http/Resources`](app/Http/Resources) |
| Eloquent scope(s) | `Product::scopeFilter / scopeInCategory / scopePriceBetween / scopeStockLevel / scopeSearch` |
| Accessor / mutator | `Product::stockStatus()` accessor (derived `stock_status`), `Product::sku()` mutator (trim + upper-case), `Category::slug()` mutator |
| Soft deletes on products | `SoftDeletes` trait + `deleted_at` column + restore endpoint |
| Feature tests (min. 5) | [`tests/Feature`](tests/Feature): **18 feature tests** + 5 unit tests |
| Migrations, seeders, README | [`database/`](database), this file |
| **Bonus:** Docker | [`Dockerfile`](Dockerfile), [`docker-compose.yml`](docker-compose.yml) |
| **Bonus:** OpenAPI / Swagger | [`public/openapi.yaml`](public/openapi.yaml), served with Swagger UI at `/docs` |
| **Bonus:** Caching layer | [`ProductCache`](app/Support/ProductCache.php) + [`FlushProductCache`](app/Observers/FlushProductCache.php) observer |
| **Bonus:** Rate limiting | `api` (60/min per user or IP) and `auth` (5/min per email+IP) limiters in [`AppServiceProvider`](app/Providers/AppServiceProvider.php) |

---

## 4. Design notes

* **Money** is stored as `DECIMAL(10,2)` and returned as a string (`"49.90"`), so no float rounding errors creep in.
* **Stock level** is derived, not stored. `stock_status` is computed from `stock_quantity` vs the
  per-product `low_stock_threshold`, and the `stockLevel` scope uses the same rule in SQL. So the
  filter and the response can never disagree.
* **SKU normalisation:** the mutator upper-cases and trims. The Form Request applies the same
  normalisation *before* the `unique` rule, so `" wm-001 "` and `"WM-001"` count as a duplicate. The
  DB unique index also covers soft-deleted rows, so a deleted product's SKU cannot be reused by
  accident.
* **Supplier links** are written with `sync()`. Sending `suppliers` replaces the set, and omitting it
  leaves the set untouched. Pivot changes `touch()` the product, which updates `updated_at` and
  invalidates the cache.
* **Caching:** product list pages are cached for 10 minutes under a key built from the validated query.
  Invalidation rotates a *version key* on any Product/Category/Supplier write. This works on every
  cache driver (no tags required): Redis in Docker, the database driver locally, and the array
  driver in tests.
* **N+1 protection:** relations are eager-loaded. `Model::shouldBeStrict()` makes any lazy load throw
  outside production.
* **Errors:** every `/api/*` error is JSON (401/404/409/422/429), even without an `Accept` header.
  There is never a redirect to a login page.
* **Transactions:** create/update plus supplier sync run inside `DB::transaction()`, so a product is
  never saved with half its supplier links.
* **Timestamps** are stored in UTC and returned as ISO-8601 in GMT+8 (`2026-11-05T10:15:00+08:00`).
* **Login** returns the same error for an unknown email and a wrong password, which prevents user
  enumeration. `logout` revokes only the current device's token.

### Possible next steps (out of scope)
* Role-based authorisation (policies) so that, for example, only admins can delete or restore. Right now
  any authenticated user has full product CRUD.
* Stock movement ledger (`stock_movements`) instead of directly editing `stock_quantity`.
* Full CRUD for categories and suppliers.

### Note on the Laravel version
The brief asks for **Laravel 11.x**, but Laravel 11 no longer receives security fixes and Composer
blocks installing it because of published security advisories (TokenGuard auth bypass, CRLF injection
in the `email` rule, debug-page XSS, signed-URL path confusion). With the reviewers' agreement,
the project uses **Laravel 12** (>= 12.69, which has all of those fixes). The upgrade from 11 to 12
needs no code changes, so everything the brief asks for applies unchanged. `composer audit` is clean.
