# Warehouse Stock API

Lightweight PHP + SQLite JSON API for warehouse stock in/out management.

## Requirements

- PHP 7.3 or newer
- PDO SQLite extension

On Debian/Ubuntu, install SQLite support with:

```bash
sudo apt install php-sqlite3
```

## Setup

1. Copy `.env.example` to `.env`.
2. Change `APP_API_TOKEN`.
3. Optional: set `APP_ADMIN_PASSWORD_HASH` if using `/api/auth/login`.
4. Start the local server:

```bash
php -S 127.0.0.1:8000 -t public
```

The SQLite database is created automatically at `database/warehouse.sqlite`.

## Authentication

Send the API token with every protected request:

```http
Authorization: Bearer change_this_secret_token
```

`GET /api/health` and `POST /api/auth/login` are public.

## Core Endpoints

- `GET /api/health`
- `GET /api/departments`
- `POST /api/departments`
- `PUT /api/departments/{id}`
- `DELETE /api/departments/{id}`
- `GET /api/items`
- `POST /api/items`
- `PUT /api/items/{id}`
- `DELETE /api/items/{id}`
- `POST /api/stock/in`
- `POST /api/stock/out`
- `GET /api/stock/movements`
- `GET /api/reports/current-stock`
- `GET /api/reports/stock-movement`
- `GET /api/reports/stock-out-by-department`
- `GET /api/activity-logs`
