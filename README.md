# Multi-Vendor E-Commerce Engine & Escrow Settlement API

A high-concurrency, headless multi-vendor marketplace REST API built with **Laravel 11**, **PHP 8.2+**, and **MySQL 8.0**.

Designed for high data consistency, financial safety, and zero overselling using **pessimistic row-level locking (`SELECT ... FOR UPDATE`)**, an **automated multi-vendor escrow settlement engine**, and **cryptographically verified idempotent webhook processing**.

---

## Architecture & Engineering Highlights

```
                                      ┌────────────────────────────────────────┐
                                      │         Client Request (HTTP)          │
                                      └──────────────────┬─────────────────────┘
                                                         │
                                                         ▼
                                      ┌────────────────────────────────────────┐
                                      │      Laravel Sanctum Auth & RBAC       │
                                      │    (Admin | Vendor | Customer)         │
                                      └──────────────────┬─────────────────────┘
                                                         │
                        ┌────────────────────────────────┼────────────────────────────────┐
                        │                                │                                │
                        ▼                                ▼                                ▼
             ┌─────────────────────┐          ┌─────────────────────┐          ┌─────────────────────┐
             │   Public Catalog    │          │   Checkout Engine   │          │  Escrow Settlement  │
             │ (Categories/Products│          │   (Pessimistic Row  │          │   (Commission Split │
             │  Cached in Memory)  │          │   Lock: FOR UPDATE) │          │   & Ledger Entries) │
             └─────────────────────┘          └──────────┬──────────┘          └──────────┬──────────┘
                                                         │                                │
                                                         ▼                                ▼
                                      ┌────────────────────────────────────────────────────────┐
                                      │            MySQL 8.0 ACID Database Engine              │
                                      │  (products, orders, order_items, vendors, transactions)│
                                      └────────────────────────────────────────────────────────┘
```

1. **Pessimistic Inventory Locking (`SELECT ... FOR UPDATE`):**
   * Eliminates race conditions and inventory overselling during simultaneous checkout attempts.
   * Deterministically pre-sorts items by `product_id` before transaction execution to mathematically prevent database deadlocks.
2. **Escrow Commission & Multi-Vendor Payout Engine:**
   * Customer funds locked in escrow upon checkout.
   * Upon verified order delivery, platform commission (e.g. 10%) is automatically deducted, net balance is atomically credited to the vendor wallet, and double-entry ledger transactions (`ESCROW_RELEASE` and `COMMISSION_DEDUCTED`) are posted.
3. **Idempotent Webhook Processing:**
   * Validates incoming payment gateway signatures via timing-safe HMAC-SHA256 comparison (`hash_equals()`).
   * Guards against duplicate webhooks from gateway retries using unique event logs.
4. **Clean Domain-Driven Service Layer:**
   * Business logic isolated in `CheckoutService`, `EscrowSettlementService`, and `WebhookService`.
   * Controllers remain lean and handle only HTTP orchestration and validation.
5. **Interactive OpenAPI / Swagger Documentation:**
   * Automated documentation powered by `dedoc/scramble` available live at `/docs/api`.

---

## End-to-End Request Flow & Token Lifecycle

### 1. Macro Request Pipeline
Every request entering the application follows an explicit 7-stage pipeline:

```
[Client / Web / Mobile] 
      │ 1. Sends HTTP Request with `Authorization: Bearer 1|xyz...`
      ▼
[public/index.php] ───────> Boots Composer autoloader & Laravel Engine
      ▼
[bootstrap/app.php] ──────> Configures middleware pipeline and aliases
      ▼
[routes/api.php] ─────────> Matches URL path (`/api/v1/orders/checkout`)
      ▼
[auth:sanctum] ───────────> Hashes token secret, verifies against `personal_access_tokens` table
      ▼
[EnsureUserHasRole] ──────> Enforces RBAC permissions (`customer` vs `vendor` vs `admin`)
      ▼
[FormRequest] ────────────> Validates input data types, inventory existence, min bounds
      ▼
[Controller] ─────────────> Orchestrates request, delegates to Domain Service
      ▼
[Domain Service] ─────────> Runs `DB::transaction()`, acquires `lockForUpdate()`, writes DB
      ▼
[JsonResource] ───────────> Formats output JSON, masks internal DB columns
      ▼
[Client Response] ────────> Returns HTTP JSON response (201 Created / 200 OK)
```

### 2. How Authentication & Tokens Work Under the Hood
* **Login / Register:**
  * When a user logs in, `AuthController` calls `$user->createToken('auth_token', ['role:customer'])->plainTextToken`.
  * Laravel generates a 40-character random string, hashes it with SHA-256, and saves the hash in the `personal_access_tokens` table.
  * It returns `"1|plainSecretKey"` to the client.
* **Client Storage:**
  * Client stores this token in `localStorage` (React) or encrypted secure storage (Mobile).
* **Transmission:**
  * Client sends header with every protected call: `Authorization: Bearer 1|plainSecretKey`.
* **Server Verification:**
  * Sanctum extracts ID `1`, hashes `plainSecretKey` with SHA-256, and executes:
    `SELECT * FROM personal_access_tokens WHERE id = 1 AND token = <hash> LIMIT 1;`
  * If valid, loads User record and attaches to `$request->user()`.
  * If invalid or expired, immediately halts with `401 Unauthorized`.
* **Instant Revocation (Logout):**
  * Calling `$request->user()->currentAccessToken()->delete()` deletes the row from MySQL, instantly invalidating the token on all devices.

---

## Tech Stack (Zero Docker Setup)

* **Backend Framework:** Laravel 11.x
* **Language:** PHP 8.2+
* **Database:** MySQL 8.0
* **Authentication:** Laravel Sanctum (Role-based Bearer tokens)
* **API Documentation:** Scramble (OpenAPI 3.0)
* **Testing:** PHPUnit / Pest (12 Feature & Unit Test Suites, 48 Assertions)

---

## Database Schema (8 Normalized Tables)

* **`users`:** Customer, Vendor, and Admin accounts with encrypted passwords and role enums.
* **`vendors`:** Store details, slug, commission percentage rate, and wallet balance in cents.
* **`categories`:** Hierarchical category tree with self-referencing parent-child relationships.
* **`products`:** Indexed catalog items with prices in cents, inventory counts, and soft deletes.
* **`orders`:** Order headers tracking customer reference, delivery status, and order totals.
* **`order_items`:** Individual items tagged to specific vendors with distinct escrow payout statuses.
* **`transactions`:** Double-entry financial audit ledger tracking all credit and debit movements with idempotency keys.
* **`webhook_logs`:** Audit table for processed gateway events preventing duplicate executions.

---

## Local Installation & Setup

### Prerequisites
* PHP 8.2+
* Composer 2.x
* MySQL 8.0 running locally

### 1. Clone & Install Dependencies
```bash
cd marketplace-api
composer install
```

### 2. Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```
Ensure your MySQL credentials are set in `.env`:
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marketplace_api
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Run Migrations & Seed Default Records
```bash
php artisan migrate:fresh --seed
```
*Creates default Admin, 2 Verified Vendors, Retail Customer, and sample categorized products.*

### 4. Run Automated Test Suite
```bash
php artisan test
```
*Runs all 12 test suites verifying auth, concurrency stock locking, escrow settlement, and webhook idempotency.*

### 5. Start Development Server
```bash
php artisan serve
```
* Server running at: `http://127.0.0.1:8000`
* Interactive API Documentation: `http://127.0.0.1:8000/docs/api`

---

## Default Seeded Accounts

| Role | Email | Password | Store Name |
|---|---|---|---|
| **Admin** | `admin@marketplace.com` | `password123` | N/A |
| **Vendor 1** | `tech@vendor.com` | `password123` | TechZone Electronics (10% fee) |
| **Vendor 2** | `fashion@vendor.com` | `password123` | Aura Luxury Apparel (12.5% fee) |
| **Customer** | `customer@gmail.com` | `password123` | N/A |

---

## API Endpoints Reference

### Authentication (`/api/v1/auth`)
* `POST /api/v1/auth/register` — Register as Customer or Vendor (`store_name` required for vendor).
* `POST /api/v1/auth/login` — Returns Bearer Token with role abilities.
* `POST /api/v1/auth/logout` — Revokes active token (`auth:sanctum`).
* `GET  /api/v1/auth/me` — Returns authenticated user profile and vendor store info.

### Public Catalog (`/api/v1`)
* `GET /api/v1/categories` — Cached hierarchical category tree.
* `GET /api/v1/products` — Filter products by `category_id`, `vendor_id`, `search`, and `per_page`.
* `GET /api/v1/products/{id_or_slug}` — Product details with vendor and category models.

### Customer Orders (`/api/v1`) — `auth:sanctum`, `role:customer`
* `POST /api/v1/orders/checkout` — Executes atomic checkout with pessimistic inventory locking (`FOR UPDATE`).
* `GET  /api/v1/orders` — Customer order history.
* `GET  /api/v1/orders/{id}` — Order breakdown with multi-vendor item statuses.

### Vendor Portal (`/api/v1/vendor`) — `auth:sanctum`, `role:vendor`
* `GET    /api/v1/vendor/products` — View products owned by authenticated vendor.
* `POST   /api/v1/vendor/products` — Create new catalog product with stock.
* `PUT    /api/v1/vendor/products/{id}` — Update price, details, or inventory.
* `DELETE /api/v1/vendor/products/{id}` — Soft delete product.
* `GET    /api/v1/vendor/wallet` — View current balance in cents and recent ledger transactions.

### Admin Oversight (`/api/v1/admin`) — `auth:sanctum`, `role:admin`
* `PATCH /api/v1/admin/orders/{id}/deliver` — Marks order delivered, triggering automatic escrow balance release to vendor wallets.
* `PATCH /api/v1/admin/vendors/{id}/verify` — Verify vendor store for public listing.

### Payment Webhooks (`/api/v1/webhooks`)
* `POST /api/v1/webhooks/payment` — Ingests gateway webhooks, verifies HMAC-SHA256 signature, transitions order to `paid`, and writes idempotent event logs.
