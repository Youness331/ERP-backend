# Plateforme ERP de Gestion des Opérations — Backend

API REST de gestion des opérations commerciales (clients, fournisseurs, catalogue,
stock, achats, ventes, facturation, encaissements) pour une PME marocaine :
**devise MAD**, **interface en français**, **fuseau Africa/Casablanca**.

> Frontend Angular : à venir (`frontend/`). Ce dépôt contient pour l'instant le backend.

---

## Sommaire

- [Stack](#stack)
- [Architecture](#architecture)
- [Modules & fonctionnalités](#modules--fonctionnalités)
- [Règles métier](#règles-métier)
- [Démarrage rapide (Docker)](#démarrage-rapide-docker)
- [Démarrage local (sans Docker)](#démarrage-local-sans-docker)
- [Comptes de démonstration](#comptes-de-démonstration)
- [Tests](#tests)
- [Documentation de l'API](#documentation-de-lapi)
- [Structure du projet](#structure-du-projet)
- [Prochaines étapes](#prochaines-étapes)

---

## Stack

| Élément | Choix |
| --- | --- |
| Framework | Laravel 12 (PHP 8.4) |
| Base de données | MySQL 8.4 (SQLite pour les tests) |
| Cache / files d'attente | Redis 7 |
| Authentification | Laravel Sanctum (tokens Bearer) |
| Serveur web | Nginx + PHP-FPM |
| Conteneurisation | Docker Compose |
| Tests | PHPUnit 11 (160 tests, 613 assertions) |
| Style | Laravel Pint (PSR-12) |

## Architecture

```
HTTP → Controller (mince) → FormRequest (validation) → Service (règles métier)
     → Model / DB transaction → API Resource (sérialisation JSON)
```

- **Controllers** : aucune logique métier, uniquement orchestration + réponses.
- **Services** (`app/Services`) : cœur métier transactionnel — numérotation,
  mouvements de stock, cycle de vie des commandes et factures.
- **Enums PHP 8** (`app/Enums`) : statuts typés (commande, facture, paiement,
  mouvement de stock) avec libellés français.
- **RBAC maison** : rôles / permissions en base, middleware `permission:...`.
- **Migrations portables** : MySQL en production, SQLite en test (mêmes fichiers).

## Modules & fonctionnalités

| Module | Fonctionnalités |
| --- | --- |
| **Authentification** | Login token, profil, changement de mot de passe, déconnexion, comptes désactivables |
| **Rôles & permissions** | 5 rôles, 54 permissions granulaires, matrice consultable |
| **Clients** | CRUD, recherche, pagination, champs fiscaux marocains (ICE, IF, RC, patente), limite de crédit |
| **Fournisseurs** | CRUD, recherche, contacts |
| **Catalogue** | Catégories hiérarchiques, produits (SKU, code-barres, TVA, seuil de réapprovisionnement, stock initial) |
| **Entrepôts** | CRUD, entrepôt par défaut |
| **Stock** | Niveaux par entrepôt, quantité réservée/disponible, entrées/sorties/ajustements, **journal immuable** |
| **Achats** | Commandes fournisseurs, confirmation, réception totale/partielle, coût moyen pondéré |
| **Ventes** | Commandes clients, confirmation (réservation), livraison totale/partielle, annulation |
| **Devis** | Brouillon → envoyé → accepté/refusé → conversion en commande, validité automatique, expiration planifiée |
| **Facturation** | Factures & avoirs, envoi, annulation, génération depuis une commande livrée |
| **Paiements** | Encaissements clients et décaissements fournisseurs, partiels ou totaux, statuts automatiques, détection des retards |
| **Journal d'activité** | Traçabilité des créations, modifications et suppressions : auteur, IP et champs modifiés |
| **Tableau de bord** | CA du mois (+ croissance), encaissements, créances, retards, stock faible, valeur du stock, top produits, série 12 mois |

## Règles métier

1. **Numérotation sans trou** : `SO-2026-0001`, `PO-2026-0001`, `FAC-2026-0001`,
   `PAY-2026-0001`, `CLI-00001`… compteur par type **et par année**, incrémenté
   sous verrou transactionnel (table `sequences`).
2. **Confirmation d'une commande client** → réservation du stock
   (`reserved_quantity`), refus si disponible insuffisant.
3. **Livraison** → consommation de la réservation et sortie physique, avec
   mouvement de stock tracé ; livraison partielle supportée.
4. **Réception fournisseur** → entrée en stock + recalcul du **coût moyen pondéré**.
5. **Facturation** possible uniquement depuis une commande livrée (au moins
   partiellement) et une seule fois.
6. **Paiements** : jamais supérieurs au solde dû ; statut recalculé
   (`partially_paid`, `paid`, `overdue`).
7. **TVA** : calcul par ligne, arrondi au centime ; totaux = somme des lignes.
8. **Suppressions protégées** : un client avec commandes en cours ou factures
   impayées ne peut pas être supprimé, etc.
9. **Mouvements de stock immuables** : jamais modifiés, uniquement ajoutés.

## Démarrage rapide (Docker)

Prérequis : Docker Desktop.

```bash
docker compose up -d --build          # php-fpm, nginx, mysql, redis, queue worker
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

| Service | URL |
| --- | --- |
| API | http://localhost:8000/api/v1 |
| MySQL | `localhost:3307` (base `erp`, user `erp`, mot de passe `secret`) |
| Redis | `localhost:6380` |

```bash
docker compose exec app php artisan test    # suite de tests
docker compose exec app ./vendor/bin/pint   # formatage
docker compose logs -f app                  # logs
```

## Démarrage local (sans Docker)

PHP 8.4 avec `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo` :

```bash
cd backend
composer install
cp .env.example .env        # puis mettre DB_CONNECTION=sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

> **Note Windows** : si Laravel refuse de démarrer avec
> « bootstrap/cache directory must be present and writable » alors que le
> dossier est inscriptible (`is_writable()` renvoie faux sur certains hôtes
> verrouillés), exécuter `php scripts/build-package-manifest.php` depuis la
> racine du dépôt, ou utiliser Docker (recommandé).

## Comptes de démonstration

Mot de passe commun : `Password123!`

| Email | Rôle |
| --- | --- |
| `admin@erp.test` | Administrateur (accès complet) |
| `manager@erp.test` | Responsable des opérations |
| `comptable@erp.test` | Comptable (facturation, encaissements) |
| `stock@erp.test` | Magasinier (catalogue, stock, réceptions) |
| `commercial@erp.test` | Commercial (clients, ventes, livraisons) |

Le seeder de démonstration crée 3 entrepôts, 6 catégories, 24 produits,
14 clients, 8 fournisseurs, 6 commandes d'achat, 10 commandes de vente,
des factures (payées, partielles, en retard) et leurs encaissements.

## Tests

```bash
cd backend && php artisan test
```

Tests unitaires (logique pure, sans base de données) :

- `LineTotalsTest` — arrondis des totaux de ligne, TVA, remises, document vide
- `PermissionsTest` — unicité et nommage des 54 permissions du catalogue RBAC
- `SalesOrderStatusTest`, `PurchaseOrderStatusTest`, `InvoiceStatusTest`,
  `InvoiceTypeTest`, `StockMovementTypeTest` — libellés et transitions de statut

Tests fonctionnels :

- `AuthenticationTest` — login, token, compte désactivé, profil, logout
- `AuthorizationTest` — permissions par action, bypass admin, compte désactivé
- `ClientApiTest` — pagination, recherche, validation, suppression protégée
- `StockTest` — entrées/sorties/ajustements, stock insuffisant, réservations
- `StockServiceTest` — réceptions, sorties, réservations, inventaire, journal
- `ReferenceGeneratorTest` — séquences par type, par année, sans collision
- `SalesOrderFlowTest` — totaux, réservation, livraison, facturation, paiements
- `PurchaseOrderFlowTest` — réception totale/partielle, plafonnement, coût moyen
- `DashboardTest` — payload KPI, commande de détection des retards

## Documentation de l'API

- Référence complète : [`docs/API.md`](docs/API.md)
- Conventions : pagination `?page=&per_page=`, recherche `?search=`,
  filtres (`?status=`, `?from=`, `?to=`), tri `?sort=&direction=`.
- Erreurs : `422` validation `{message, errors}`, `403` permission,
  `401` authentification, `404` ressource.

## Structure du projet

```
.
├── backend/                  # Application Laravel 12
│   ├── app/
│   │   ├── Enums/            # Statuts typés (FR)
│   │   ├── Exceptions/       # Erreurs métier → 422
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   ├── Middleware/   # permission:...
│   │   │   ├── Requests/     # Validation
│   │   │   └── Resources/    # Sérialisation JSON
│   │   ├── Models/
│   │   ├── Services/         # Règles métier transactionnelles
│   │   └── Support/          # LineTotals, Permissions
│   ├── database/
│   │   ├── factories/
│   │   ├── migrations/       # 10 migrations métier
│   │   └── seeders/
│   ├── routes/api.php
│   └── tests/                # 160 tests (unit + feature)
├── docker/                   # PHP-FPM, Nginx, php.ini
├── docker-compose.yml
├── docs/API.md
└── scripts/                  # Outils de dev (manifest, bootstrap PHP)
```

## Prochaines étapes

- [ ] Frontend Angular (dashboard, listes, formulaires, guards par permission)
- [ ] Export PDF des factures / bons de livraison
- [ ] Rapprochement bancaire des décaissements
- [ ] Avoirs générés depuis une facture
- [ ] Notifications (email facture, alerte stock) via queue Redis
- [ ] Multi-société / multi-devise
