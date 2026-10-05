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
