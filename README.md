# ERP Backend

This repository contains the Laravel API for the ERP app. It handles users,
customers, products, stock, orders, invoices, and reports.

The Angular frontend is in a separate repository:
[ERP-frontend](https://github.com/Youness331/ERP-frontend).

## Run with Docker

From the repository root, with Docker running:

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The API is available at `http://localhost:8000/api/v1`. To stop the services,
run `docker compose down`.

Run the backend tests with:

```bash
docker compose exec app php artisan test
```

See [`docs/API.md`](docs/API.md) for the API reference.
