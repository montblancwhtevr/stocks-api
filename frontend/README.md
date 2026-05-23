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

Copy `.env.example` to `.env` and set the API URL plus token before running or building:

```env
VITE_API_BASE_URL=https://api.hiada.my.id
VITE_API_TOKEN=your_secret_token_here
```

The token is compiled into the frontend bundle. This is convenient for a private admin UI, but it is not the same as secure login because browser code can be inspected.

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
