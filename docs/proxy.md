# Reverse Proxy Configuration

Pathary works behind reverse proxies like Nginx, Traefik, Caddy, or HAProxy. This document covers the required configuration.

## Quick Setup

1. Set `APPLICATION_URL` to your public URL (e.g., `https://<your_domain>`)
2. Configure your reverse proxy to forward required headers
3. Ensure WebSocket/long-polling is not blocked (if using real-time features)

## Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `APPLICATION_URL` | Yes | Your public-facing URL (e.g., `https://<your_domain>`). Must match the URL users access in their browser. |

## Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name <your_domain>;

    # SSL configuration
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://pathary-app:80;

        # Required headers
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;

        # WebSocket support (if needed)
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";

        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

## Traefik Configuration

```yaml
# docker-compose.yml with Traefik labels
services:
  pathary:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.pathary.rule=Host(`<your_domain>`)"
      - "traefik.http.routers.pathary.entrypoints=websecure"
      - "traefik.http.routers.pathary.tls=true"
      - "traefik.http.services.pathary.loadbalancer.server.port=80"
```

## Caddy Configuration

```
<your_domain> {
    reverse_proxy pathary-app:80
}
```

Caddy automatically handles forwarded headers and SSL.

## Required Proxy Headers

These headers should be forwarded by your reverse proxy:

| Header | Purpose |
|--------|---------|
| `Host` | Original host header |
| `X-Real-IP` | Client's real IP address |
| `X-Forwarded-For` | Chain of proxy IPs |
| `X-Forwarded-Proto` | Original protocol (http/https) |
| `X-Forwarded-Host` | Original host requested by client |

## X-Movary-Client Header

The `X-Movary-Client` header is used internally for API authentication to identify the client type. **You do not need to configure this in your proxy** - the web UI sends it automatically via JavaScript.

If you see "Missing request header X-Movary-Client" errors:
1. This is handled automatically by the web interface
2. The application normalizes header casing to work with proxies that alter header case
3. No proxy configuration is needed for this header

## Cookie Settings

When running behind HTTPS, cookies are automatically set with:
- `Secure` flag (for HTTPS)
- `SameSite=Lax` (CSRF protection)

Ensure your proxy correctly forwards the `X-Forwarded-Proto: https` header so the application knows it's behind SSL.

## Troubleshooting

### Login fails with "Missing request header X-Movary-Client"

This error should not occur with recent versions. The application normalizes HTTP header casing to handle proxies that alter header case (e.g., converting `X-Movary-Client` to `x-movary-client`).

If you still see this error:
1. Check browser developer tools → Network tab → look at the login request
2. Verify the `X-Movary-Client` header is present in the request
3. Check if your proxy is stripping custom headers (some WAFs do this)

### Redirect loops or wrong URLs

1. Verify `APPLICATION_URL` matches your public URL exactly
2. Include the protocol: `https://<your_domain>` not just `<your_domain>`
3. Do not include a trailing slash

### Mixed content warnings

Ensure `X-Forwarded-Proto: https` is being forwarded so the app generates HTTPS URLs.

## Docker Compose Example with Nginx Proxy

```yaml
version: '3.8'

services:
  pathary:
    image: ghcr.io/leepeuker/pathary:latest
    environment:
      - APPLICATION_URL=https://<your_domain>
      - DATABASE_MODE=mysql
      - DATABASE_MYSQL_HOST=mysql
      - DATABASE_MYSQL_NAME=<database_name>
      - DATABASE_MYSQL_USER=<database_user>
      - DATABASE_MYSQL_PASSWORD=<database_password>
    networks:
      - proxy
      - internal

  mysql:
    image: mysql:8.0
    environment:
      - MYSQL_DATABASE=<database_name>
      - MYSQL_USER=<database_user>
      - MYSQL_PASSWORD=<database_password>
      - MYSQL_ROOT_PASSWORD=<mysql_root_password>
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - internal

networks:
  proxy:
    external: true
  internal:

volumes:
  mysql_data:
```
