# ERP Opérations — Backend (Laravel 12)

API REST de la plateforme ERP de gestion des opérations.
Documentation complète : [../README.md](../README.md) · [../docs/API.md](../docs/API.md).

## Démarrage

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Avec Docker (depuis la racine du dépôt) :

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate --seed
```

## Tests

```bash
php artisan test
```

## Organisation

| Dossier | Rôle |
| --- | --- |
| `app/Http/Controllers/Api/V1` | Points d'entrée REST (contrôleurs minces) |
| `app/Http/Requests` | Validation des entrées |
| `app/Http/Resources` | Contrats de sortie JSON |
| `app/Services` | Règles métier transactionnelles (stock, commandes, factures) |
| `app/Enums` | Statuts typés et libellés français |
| `app/Support` | `LineTotals` (TVA/totaux), `Permissions` (catalogue RBAC) |
| `database/migrations` | Schéma métier (10 migrations) |
| `tests/Feature` | Tests fonctionnels des parcours critiques |
