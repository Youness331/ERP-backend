# Backend application

This folder contains the Laravel API used by the ERP frontend. The frontend is
maintained in the separate
[ERP-frontend repository](https://github.com/Youness331/ERP-frontend).

To start the API and its services, run these commands from the
[repository root](../README.md):

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The API runs at `http://localhost:8000/api/v1`. Run backend tests with
`docker compose exec app php artisan test`.
