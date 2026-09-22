# Frontend

React 19 + TypeScript SPA for the Mini Inventory & Order System. See the
[root README](../README.md) for setup, architecture and the API.

It runs in the `frontend` Docker Compose service (Node 24) at
http://localhost:5173. The Vite dev server proxies `/api/*` to the Laravel
container, so the app is same-origin and needs no CORS configuration.

```bash
docker compose exec frontend npm run build   # type-check + production build
docker compose exec frontend npm run lint    # oxlint
```

`node_modules` lives in a Docker volume (Linux-native binaries), not on the
host — so editors may not resolve imports unless you also run `npm install`
locally.

## Layout

- `src/api/` — typed fetch client (`ApiError` carries status, field errors and
  the raw body) and one module per resource
- `src/auth/` — token storage and `AuthProvider` (restores sessions via `/me`,
  logs out on any 401)
- `src/cart/` — session-scoped cart, discarded on logout
- `src/hooks/` — `useResource` / `usePaginated` data loading
- `src/pages/` — login, products (admin CRUD + stock), cart/checkout, orders
