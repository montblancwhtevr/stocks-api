# Warehouse Stock Management API - Agentic Development Specification

## 1. Project Overview

Build a simple warehouse stock management system using **PHP backend API** for a small VPS environment.

The system is used by an admin to manage item stock movement:

- Stock In: items added to warehouse stock
- Stock Out: items taken from warehouse stock
- Stock Out Request: someone requests items, but only admin can approve/input the final stock out
- Report: admin can view stock in/out history and current stock balance

This project is designed to be lightweight and suitable for a small VPS with limited resources:

- 1 CPU Core
- 512 MB RAM
- 10 GB Storage

Tech stack:

- Debian 10 / Buster
- PHP 7.3 compatibility
- Nginx + PHP-FPM socket path
- PHP-FPM
- SQLite-first setup
- Slim Framework

---

## 2. Main Goals

The application should allow admin to:

1. Manage departments or organizations.
2. Manage item master data.
3. Record stock-in transactions.
4. Record stock-out transactions.
5. Record who requested stock-out.
6. View current stock balance.
7. Generate simple reports for stock movement.

---

## 3. User Roles

### Admin

Admin is the only user allowed to:

- Add/edit/delete department data
- Add/edit/delete item data
- Input stock in
- Input stock out
- Record requester information when stock goes out
- View reports

### Requester Information

There is no requester login or requester workflow in version 1.

If someone asks for an item, admin records the requester details directly when creating a stock-out transaction:

- requester name
- department/organization
- notes/purpose

This data is stored in `db_stock_movements`.

---

## 4. Core Business Rules

### Stock In

Stock in increases item quantity.

Example:

- Item: Laptop Charger
- Quantity In: 10
- Current Stock Before: 5
- Current Stock After: 15

### Stock Out

Stock out decreases item quantity.

Example:

- Item: Laptop Charger
- Quantity Out: 3
- Current Stock Before: 15
- Current Stock After: 12

Rules:

- Stock out quantity cannot be greater than current stock.
- Only admin can input stock out.
- Every stock out should include requester name and department/organization.

### Current Stock

Current stock can be stored directly in the `items.quantity` column for simplicity.

Every stock transaction must also be recorded in a stock movement table for reporting and audit history.

---

## 5. Database Design

Use simple table names. Prefix `db_` is optional, but this specification uses it based on the initial idea.

---

## 5.1 Table: db_departments

Stores department or organization master data.

```sql
CREATE TABLE db_departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    department_name VARCHAR(150) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

Example data:

| id | department_name |
|---:|-----------------|
| 1 | IT Department |
| 2 | Finance |
| 3 | Warehouse |
| 4 | Student Affairs |

---

## 5.2 Item Category Field

Item categories do not need a separate database table in version 1.

Use a simple optional `category_name` field on `db_items` instead. This field is not mandatory, so items can be created without a category.

Example category values:

| category_name |
|---------------|
| Office Supplies |
| Electronics |
| Cleaning Tools |

---

## 5.3 Table: db_items

Stores item master data and current stock quantity.

```sql
CREATE TABLE db_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_name VARCHAR(150) NOT NULL,
    category_name VARCHAR(150) NULL,
    unit VARCHAR(50) DEFAULT 'pcs',
    quantity INTEGER NOT NULL DEFAULT 0,
    minimum_stock INTEGER DEFAULT 0,
    description TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

Example data:

| id | item_name | category_name | unit | quantity |
|---:|-----------|---------------|------|---------:|
| 1 | Laptop Charger | Electronics | pcs | 15 |
| 2 | A4 Paper | Office Supplies | rim | 30 |
| 3 | Marker | Office Supplies | pcs | 50 |

---

## 5.4 Table: db_stock_movements

Main transaction table for stock in and stock out.

```sql
CREATE TABLE db_stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    stock_in_transaction_id INTEGER NULL,
    stock_out_transaction_id INTEGER NULL,
    movement_type VARCHAR(20) NOT NULL,
    quantity INTEGER NOT NULL,
    stock_before INTEGER NOT NULL,
    stock_after INTEGER NOT NULL,
    requester_name VARCHAR(150) NULL,
    department_id INTEGER NULL,
    purpose TEXT NULL,
    notes TEXT NULL,
    requested_at DATETIME NULL,
    received_at DATETIME NULL,
    movement_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES db_items(id),
    FOREIGN KEY (stock_in_transaction_id) REFERENCES db_stock_in_transactions(id),
    FOREIGN KEY (stock_out_transaction_id) REFERENCES db_stock_out_transactions(id),
    FOREIGN KEY (department_id) REFERENCES db_departments(id)
);
```

Allowed `movement_type` values:

```text
IN
OUT
```

For stock in:

- requester_name can be null
- department_id can be null
- purpose can be null
- requested_at can be null
- received_at should be filled if the admin needs to track when the item arrived
- stock_in_transaction_id should be filled when the stock in was submitted in bulk

For stock out:

- requester_name should be filled
- department_id should be filled if available
- purpose is recommended
- requested_at should be filled if the admin needs to track when the item was requested
- received_at can be null
- stock_out_transaction_id should be filled when the stock out was submitted in bulk

Date meaning:

- `requested_at`: when the stock request was made
- `received_at`: when incoming stock arrived at the warehouse
- `movement_date`: when the stock movement was recorded and stock quantity changed

Example data:

| id | item_id | movement_type | quantity | stock_before | stock_after | requester_name | department_id | requested_at | received_at | movement_date |
|---:|--------:|---------------|---------:|-------------:|------------:|----------------|--------------:|--------------|-------------|---------------|
| 1 | 1 | IN | 10 | 5 | 15 | NULL | NULL | NULL | 2026-01-10 09:00:00 | 2026-01-10 10:30:00 |
| 2 | 1 | OUT | 3 | 15 | 12 | Budi | 1 | 2026-01-11 08:15:00 | NULL | 2026-01-11 09:00:00 |

---

## 5.5 Table: db_stock_in_transactions

Stores the header record for a stock-in delivery that contains multiple item lines.

```sql
CREATE TABLE db_stock_in_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_no VARCHAR(100) NOT NULL UNIQUE,
    source_name VARCHAR(150) NULL,
    received_at DATETIME NULL,
    notes TEXT NULL,
    created_by VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

Example:

| id | transaction_no | source_name | received_at |
|---:|----------------|-------------|-------------|
| 1 | SI-20260523-001 | Supplier ABC | 2026-05-23 14:00:00 |

---

## 5.6 Table: db_stock_in_transaction_items

Stores item lines for a grouped stock-in transaction.

```sql
CREATE TABLE db_stock_in_transaction_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    stock_before INTEGER NOT NULL,
    stock_after INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES db_stock_in_transactions(id),
    FOREIGN KEY (item_id) REFERENCES db_items(id)
);
```

Important:

- Also insert one `db_stock_movements` row per item line for reporting.
- Update all item quantities in one database transaction.

---

## 5.7 Table: db_stock_out_transactions

Stores the header record for a stock-out request that contains multiple item lines.

Use this table when one requester or department asks for many items at the same time.

```sql
CREATE TABLE db_stock_out_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_no VARCHAR(100) NOT NULL UNIQUE,
    requester_name VARCHAR(150) NOT NULL,
    department_id INTEGER NULL,
    purpose TEXT NULL,
    requested_at DATETIME NULL,
    notes TEXT NULL,
    created_by VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES db_departments(id)
);
```

Example:

| id | transaction_no | requester_name | department_id | requested_at |
|---:|----------------|----------------|--------------:|--------------|
| 1 | SO-20260523-001 | Budi | 1 | 2026-05-23 10:00:00 |

---

## 5.8 Table: db_stock_out_transaction_items

Stores item lines for a grouped stock-out transaction.

```sql
CREATE TABLE db_stock_out_transaction_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    transaction_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    stock_before INTEGER NOT NULL,
    stock_after INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES db_stock_out_transactions(id),
    FOREIGN KEY (item_id) REFERENCES db_items(id)
);
```

Example:

| id | transaction_id | item_id | quantity | stock_before | stock_after |
|---:|---------------:|--------:|---------:|-------------:|------------:|
| 1 | 1 | 1 | 2 | 15 | 13 |
| 2 | 1 | 2 | 5 | 30 | 25 |

Important:

- Backend must validate all item stock first.
- If any item has insufficient stock, reject the whole transaction.
- Do not partially process stock out.
- Also insert one `db_stock_movements` row per item line for reporting.

---

## 5.9 Table: db_activity_logs

Stores admin activity logs for create, update, delete, and important stock actions.

This table is for audit/debugging only. Stock reports should still use `db_stock_movements`.

```sql
CREATE TABLE db_activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_name VARCHAR(100) NULL,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INTEGER NULL,
    old_data TEXT NULL,
    new_data TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

Allowed `action` examples:

```text
CREATE
UPDATE
DELETE
STOCK_IN
STOCK_IN_BULK
STOCK_OUT
STOCK_OUT_BULK
LOGIN
LOGOUT
```

Data format:

- `old_data` stores JSON before update/delete
- `new_data` stores JSON after create/update
- For stock in/out, log the affected `db_stock_movements.id` as `record_id`
- For bulk stock in, log the affected `db_stock_in_transactions.id` as `record_id`
- For bulk stock out, log the affected `db_stock_out_transactions.id` as `record_id`

Example data:

| id | admin_name | action | table_name | record_id | created_at |
|---:|------------|--------|------------|----------:|------------|
| 1 | admin | CREATE | db_items | 1 | 2026-01-10 08:00:00 |
| 2 | admin | STOCK_IN | db_stock_movements | 1 | 2026-01-10 10:30:00 |
| 3 | admin | STOCK_OUT | db_stock_movements | 2 | 2026-01-11 09:00:00 |

---

## 6. Recommended Minimum Version

For version 1, use only these tables:

1. `db_departments`
2. `db_items`
3. `db_stock_movements`
4. `db_stock_in_transactions`
5. `db_stock_in_transaction_items`
6. `db_stock_out_transactions`
7. `db_stock_out_transaction_items`
8. `db_activity_logs`

Reason:

Only admin uses the system. If someone requests an item, admin records `requester_name`, `department_id`, `purpose`, and `requested_at` directly in `db_stock_movements` during stock-out entry.

---

## 7. API Endpoints

Base URL example:

```text
http://YOUR_VPS_IP/api
```

---

## 7.1 Department API

### List Departments

```http
GET /api/departments
```

Response:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "department_name": "IT Department"
    }
  ]
}
```

### Create Department

```http
POST /api/departments
```

Request:

```json
{
  "department_name": "Finance"
}
```

### Update Department

```http
PUT /api/departments/{id}
```

### Delete Department

```http
DELETE /api/departments/{id}
```

---

## 7.2 Category Handling

There is no separate category API in version 1 because categories are stored as an optional text field on items.

If the admin wants to categorize an item, send `category_name` when creating or updating the item. If no category is needed, omit `category_name` or send `null`.

---

## 7.3 Item API

### List Items

```http
GET /api/items
```

Query filters:

```text
?search=paper&category_name=Office%20Supplies
```

Response:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "item_name": "A4 Paper",
      "category_name": "Office Supplies",
      "unit": "rim",
      "quantity": 30,
      "minimum_stock": 5
    }
  ]
}
```

### Create Item

```http
POST /api/items
```

Request:

```json
{
  "item_name": "A4 Paper",
  "category_name": "Office Supplies",
  "unit": "rim",
  "quantity": 0,
  "minimum_stock": 5,
  "description": "A4 paper for office usage"
}
```

### Update Item

```http
PUT /api/items/{id}
```

### Delete Item

```http
DELETE /api/items/{id}
```

Important:

Do not allow deleting items that already have stock movement history, unless soft delete is implemented.

---

## 7.4 Stock Movement API

### Stock In

```http
POST /api/stock/in
```

This endpoint records stock in for one item only.

Request:

```json
{
  "item_id": 1,
  "quantity": 10,
  "received_at": "2026-01-10 09:00:00",
  "notes": "Initial stock from supplier",
  "created_by": "admin"
}
```

Backend process:

1. Validate item exists.
2. Validate quantity > 0.
3. Get current item quantity.
4. Calculate stock_after = stock_before + quantity.
5. Insert record into `db_stock_movements`.
6. Update `db_items.quantity`.
7. Return success response.

Response:

```json
{
  "success": true,
  "message": "Stock in recorded successfully",
  "data": {
    "item_id": 1,
    "quantity": 10,
    "stock_before": 5,
    "stock_after": 15,
    "received_at": "2026-01-10 09:00:00",
    "movement_date": "2026-01-10 10:30:00"
  }
}
```

---

### Bulk Stock In

```http
POST /api/stock/in-bulk
```

Use this endpoint for the normal admin workflow when a delivery or purchase contains multiple items.

Request:

```json
{
  "source_name": "Supplier ABC",
  "received_at": "2026-05-23 14:00:00",
  "notes": "Invoice INV-001",
  "created_by": "admin",
  "items": [
    {
      "item_id": 1,
      "quantity": 10
    },
    {
      "item_id": 2,
      "quantity": 20
    }
  ]
}
```

Backend process:

1. Validate items array is not empty.
2. Validate every item exists.
3. Validate every quantity > 0.
4. Create one `db_stock_in_transactions` header.
5. Create one `db_stock_in_transaction_items` row per item.
6. Create one `db_stock_movements` row per item.
7. Update each `db_items.quantity`.
8. Commit all changes in one database transaction.

---

### List Stock In Transactions

```http
GET /api/stock/in-transactions
```

Shows grouped stock-in transactions.

---

### Stock Out

```http
POST /api/stock/out
```

This endpoint records stock out for one item only.

Request:

```json
{
  "item_id": 1,
  "quantity": 3,
  "requester_name": "Budi",
  "department_id": 1,
  "purpose": "Replacement charger for office laptop",
  "requested_at": "2026-01-11 08:15:00",
  "notes": "Recorded by admin",
  "created_by": "admin"
}
```

Backend process:

1. Validate item exists.
2. Validate quantity > 0.
3. Validate requester_name is not empty.
4. Validate department exists if department_id is provided.
5. Get current item quantity.
6. Check current stock is enough.
7. Calculate stock_after = stock_before - quantity.
8. Insert record into `db_stock_movements`.
9. Update `db_items.quantity`.
10. Return success response.

If stock is not enough:

```json
{
  "success": false,
  "message": "Insufficient stock"
}
```

---

### Bulk Stock Out

```http
POST /api/stock/out-bulk
```

Use this endpoint for the normal admin workflow when one requester or department asks for multiple items at the same time.

Request:

```json
{
  "requester_name": "Budi",
  "department_id": 1,
  "purpose": "Monthly office supplies request",
  "requested_at": "2026-05-23 10:00:00",
  "notes": "Recorded by admin",
  "created_by": "admin",
  "items": [
    {
      "item_id": 1,
      "quantity": 2
    },
    {
      "item_id": 2,
      "quantity": 5
    }
  ]
}
```

Backend process:

1. Validate requester_name is not empty.
2. Validate department exists if department_id is provided.
3. Validate items array is not empty.
4. Validate every item exists.
5. Validate every quantity > 0.
6. Validate every item has enough stock.
7. Create one `db_stock_out_transactions` header.
8. Create one `db_stock_out_transaction_items` row per item.
9. Create one `db_stock_movements` row per item.
10. Update each `db_items.quantity`.
11. Commit all changes in one database transaction.

If any item has insufficient stock, reject the whole transaction and do not update any item quantity.

---

### List Stock Out Transactions

```http
GET /api/stock/out-transactions
```

Shows grouped stock-out transactions.

---

### List Stock Movements

```http
GET /api/stock/movements
```

Query filters:

```text
?item_id=1&type=OUT&department_id=1&date_from=2026-01-01&date_to=2026-01-31
```

Response:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "item_name": "Laptop Charger",
      "movement_type": "OUT",
      "quantity": 3,
      "stock_before": 15,
      "stock_after": 12,
      "requester_name": "Budi",
      "department_name": "IT Department",
      "purpose": "Replacement charger for office laptop",
      "requested_at": "2026-01-11 08:15:00",
      "received_at": null,
      "movement_date": "2026-01-11 09:00:00"
    }
  ]
}
```

---

## 7.5 Report API

### Current Stock Report

```http
GET /api/reports/current-stock
```

Shows all items and current quantity.

Optional filters:

```text
?category_name=Office%20Supplies&low_stock=1
```

Low stock means:

```text
quantity <= minimum_stock
```

---

### Stock Movement Report

```http
GET /api/reports/stock-movement
```

Query filters:

```text
?date_from=2026-01-01&date_to=2026-01-31&type=OUT
```

---

### Stock Out by Department Report

```http
GET /api/reports/stock-out-by-department
```

Example response:

```json
{
  "success": true,
  "data": [
    {
      "department_name": "IT Department",
      "total_items_out": 20
    },
    {
      "department_name": "Finance",
      "total_items_out": 8
    }
  ]
}
```

---

## 8. Suggested Folder Structure

For Slim Framework:

```text
warehouse-stock-api/
├── public/
│   └── index.php
├── src/
│   ├── Controllers/
│   │   ├── DepartmentController.php
│   │   ├── ItemController.php
│   │   ├── StockController.php
│   │   └── ReportController.php
│   ├── Database/
│   │   └── connection.php
│   ├── Middleware/
│   │   └── AuthMiddleware.php
│   └── Helpers/
│       └── ResponseHelper.php
├── database/
│   ├── schema.sql
│   └── warehouse.sqlite
├── logs/
│   └── app.log
├── .env
├── composer.json
└── README.md
```

---

## 9. Authentication

For version 1, keep authentication simple because there is only one admin.

Options:

### Option A: Static API Token

Admin must send header:

```http
Authorization: Bearer YOUR_SECRET_TOKEN
```

Store token in `.env`:

```env
APP_API_TOKEN=change_this_secret_token
```

This is the simplest option for JSON API-only usage.

### Option B: Single Admin Login Using .env

Use this if a simple frontend login page is needed.

Do not create a `db_users` table for version 1.

Store the single admin username and password hash in `.env`:

```env
APP_ADMIN_USERNAME=admin
APP_ADMIN_PASSWORD_HASH=your_bcrypt_hash_here
```

Important:

- Do not store the plain password in `.env`.
- Store a hash generated by PHP `password_hash()`.
- Verify login with PHP `password_verify()`.
- After login, use a PHP session or return a short-lived token.

### When to Add db_users Later

Add a `db_users` table only if the system needs:

- multiple admins
- different roles or permissions
- password changes from the UI
- password reset
- per-admin login history

Recommended for version 1:

- API-only: use Option A
- simple frontend: use Option B

---

## 10. Validation Rules

### Department

- department_name is required
- department_name max length 150

### Item

- item_name is required
- category_name is optional
- category_name max length 150
- unit is optional, default `pcs`
- quantity must be >= 0
- minimum_stock must be >= 0

### Stock In

- item_id is required
- quantity is required
- quantity must be > 0
- received_at is optional, format `YYYY-MM-DD HH:MM:SS`

### Bulk Stock In

- source_name is optional
- received_at is optional, format `YYYY-MM-DD HH:MM:SS`
- items array is required
- each item line must have item_id
- each item line must have quantity > 0

### Stock Out

- item_id is required
- quantity is required
- quantity must be > 0
- requester_name is required
- requested_at is optional, format `YYYY-MM-DD HH:MM:SS`
- quantity must not exceed current stock

### Bulk Stock Out

- requester_name is required
- department_id is optional, but must exist if provided
- requested_at is optional, format `YYYY-MM-DD HH:MM:SS`
- items array is required
- each item line must have item_id
- each item line must have quantity > 0
- every requested quantity must not exceed current stock

---

## 11. Important Implementation Notes

### Use Database Transaction

Stock in/out must use database transaction to prevent incorrect stock quantity.

Pseudo logic:

```php
$db->beginTransaction();

// 1. get current stock
// 2. calculate new stock
// 3. insert movement record
// 4. update item quantity

$db->commit();
```

If error happens:

```php
$db->rollBack();
```

---

### Do Not Update Stock Directly

Avoid manually editing `db_items.quantity` except through stock in/out process.

Reason:

Stock movements are the audit trail. If quantity is changed directly, reports become inconsistent.

---

### Log Admin Actions

Write an activity log record after successful admin actions.

Recommended actions to log:

- create/update/delete department
- create/update/delete item
- stock in
- stock out
- login/logout if admin login is added later

Keep logging simple. Do not block the main API response if writing a non-critical log fails, but write stock movement logs inside the same database transaction when possible.

---

### Use SQLite First

For a small VPS with 512 MB RAM, SQLite is recommended because it is lightweight.

Use MySQL/MariaDB only if:

- many users will access the app
- data grows large
- concurrent writes become frequent

---

## 12. Nginx Configuration Example

Example Nginx server block:

```nginx
server {
    listen 80;
    server_name _;

    root /var/www/warehouse-stock-api/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

If no domain is available, access using VPS public IP:

```text
http://YOUR_VPS_IP
```

---

## 13. Sample Development Phases

### Phase 1: Basic Setup

- Install PHP
- Install Nginx
- Setup project folder
- Create SQLite database
- Create `/api/health` endpoint

Example response:

```json
{
  "success": true,
  "message": "Warehouse API is running"
}
```

---

### Phase 2: Master Data

Build APIs for:

- departments
- items

---

### Phase 3: Stock Transaction

Build APIs for:

- stock in
- stock out
- stock movement list

---

### Phase 4: Report

Build APIs for:

- current stock
- low stock
- stock movement by date
- stock out by department

---

### Phase 5: Simple Frontend

Optional frontend pages:

- dashboard
- item list
- stock in form
- stock out form
- report page

Frontend can be simple PHP views or a separate Next.js frontend.

---

## 14. Acceptance Criteria

The project is considered working when:

1. Admin can create departments.
2. Admin can create items.
3. Admin can create items with or without `category_name`.
4. Admin can record stock in.
5. Admin can record stock out.
6. Stock out fails when quantity is greater than current stock.
7. Current item quantity updates correctly.
8. Every stock in/out is recorded in movement history.
9. Admin can filter stock movement report by date.
10. Admin can see current stock report.

---

## 15. Future Improvements

Possible next features:

- Admin login
- Export report to CSV/PDF
- Upload item photo
- Barcode/QR code support
- Low stock alert
- Telegram notification
- Multi-warehouse support
- Soft delete for master data

---

## 16. Agent Instruction

When implementing this project, prioritize simplicity and low memory usage.

Do not create a complex enterprise system.

Use simple PHP code, clear database tables, and readable controller methods.

Recommended first implementation:

- PHP Slim Framework
- SQLite database
- Token-based admin authentication
- JSON API only
- No frontend at first

After the API is stable, add simple frontend pages.
