# Warehouse Stock Frontend

React + Vite admin UI for the Warehouse Stock API.

## Local Development

```bash
cd frontend
npm install
npm run dev
```

Default API URL:

```text
https://api.hiada.my.id
```

You can override it by copying `.env.example` to `.env` and changing:

```env
VITE_API_BASE_URL=https://api.hiada.my.id
```

## Build

```bash
npm run build
```

The static files will be created in:

```text
frontend/dist
```

## VPS Deployment Idea

Serve frontend from the root domain:

```text
https://hiada.my.id
```

Keep the API on:

```text
https://api.hiada.my.id
```

Example Nginx site for frontend:

```nginx
server {
    listen 80;
    server_name hiada.my.id;

    root /var/www/stocks-ui/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

After enabling this site, run Certbot for `hiada.my.id`.
