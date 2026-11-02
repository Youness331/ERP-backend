# API REST — Référence

Base URL : `http://localhost:8000/api/v1`
Format : JSON UTF-8 · Monnaie : **MAD** · Dates : `YYYY-MM-DD` · Horodatages : ISO 8601.

---

## 1. Authentification

Sanctum, token Bearer.

```http
POST /auth/login
Content-Type: application/json

{ "email": "admin@erp.test", "password": "Password123!", "device_name": "angular" }
```

```json
{
  "token": "1|Q2x9...",
  "token_type": "Bearer",
  "user": {
    "id": 1, "name": "Youssef El Amrani", "email": "admin@erp.test",
    "is_admin": true,
    "role": { "id": 1, "name": "admin", "label": "Administrateur" },
    "permissions": ["dashboard.view", "clients.view", "clients.create", "..."]
  }
}
```

Toutes les autres routes exigent l'en-tête `Authorization: Bearer <token>`.

| Méthode | Route | Description |
| --- | --- | --- |
| POST | `/auth/login` | Connexion (throttle 6/min) |
| GET | `/auth/me` | Profil + rôle + permissions |
| PATCH | `/auth/profile` | Mise à jour du profil |
| POST | `/auth/change-password` | Changement de mot de passe (révoque les autres sessions) |
| POST | `/auth/logout` | Révocation du token courant |

## 2. Conventions

**Pagination** — liste : `?page=1&per_page=15` (max 100).

```json
{
  "data": [ ... ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

**Recherche / filtres / tri** : `?search=`, `?status=`, `?from=&to=`, `?sort=name&direction=asc`.

**Erreurs**

| Code | Corps |
| --- | --- |
| 401 | `{ "message": "Authentification requise." }` |
| 403 | `{ "message": "Accès refusé : la permission « clients.create » est requise." }` |
| 404 | `{ "message": "Ressource introuvable." }` |
| 422 | `{ "message": "...", "errors": { "champ": ["..."] } }` |

## 3. Tableau de bord

`GET /dashboard` — permission `dashboard.view`

```json
{
  "data": {
    "company": { "name": "Atlas Operations SARL", "city": "Casablanca", "ice": "..." },
    "currency": { "code": "MAD", "symbol": "DH", "decimals": 2 },
    "period": { "from": "2026-11-01", "to": "2026-11-30" },
    "revenue": { "current_month": 125400, "previous_month": 98200, "growth_percent": 27.7, "collected_this_month": 84000 },
    "receivables": { "outstanding": 45200, "overdue_amount": 12000, "overdue_count": 2 },
    "sales": { "open_orders": 5, "to_deliver": 3, "orders_this_month": 4 },
    "purchasing": { "pending_orders": 2, "orders_this_month": 1 },
    "inventory": { "low_stock_count": 3, "stock_value": 318900, "product_count": 24 },
    "partners": { "clients": 14, "suppliers": 8 },
    "charts": { "sales_last_12_months": [ { "month": "2026-01", "label": "janv. 2026", "total": 88000 } ] },
    "top_products": [ { "id": 4, "name": "...", "sku": "REF-0012-AB", "quantity": 96, "revenue": 24120 } ],
    "low_stock_products": [ { "id": 7, "sku": "...", "name": "...", "reorder_level": 10, "quantity": 4 } ],
    "recent_invoices": [ { "id": 12, "reference": "FAC-2026-0009", "client": "...", "status": "sent", "status_label": "Envoyée", "total": 4800, "balance_due": 4800 } ]
  }
}
```

## 4. Clients — `clients.*`

| Méthode | Route | Permission |
| --- | --- | --- |
| GET | `/clients` | `clients.view` |
| POST | `/clients` | `clients.create` |
| GET | `/clients/{id}` | `clients.view` |
| PUT/PATCH | `/clients/{id}` | `clients.update` |
| DELETE | `/clients/{id}` | `clients.delete` |

```json
POST /clients
{
  "name": "Atlas Distribution",
  "company_name": "Atlas Distribution SARL",
  "email": "contact@atlas-distribution.ma",
  "phone": "+212 5 22 11 22 33",
  "address": "45, rue Ibn Batouta",
  "city": "Casablanca",
  "ice": "002345678000012",
  "tax_id": "23456789",
  "rc": "RC 456789",
  "credit_limit": 25000
}
```

`code` est généré automatiquement (`CLI-00001`) s'il n'est pas fourni.
Le `DELETE` échoue (`422`) si le client a des commandes en cours ou des factures impayées.

## 5. Fournisseurs — `suppliers.*`

Mêmes routes et permissions que les clients (préfixe `suppliers`).
Champs supplémentaires : `contact_name`.

## 6. Catalogue

### Catégories — `categories.*`

`GET|POST /categories`, `GET|PUT|PATCH|DELETE /categories/{id}`.
`?tree=1` retourne les catégories racines avec leurs enfants.
`slug` généré depuis le nom s'il est omis. Suppression refusée si des produits existent.

### Produits — `products.*`

`GET|POST /products`, `GET|PUT|PATCH|DELETE /products/{id}`

```json
POST /products
{
  "sku": "REF-0042",
  "name": "Papier A4 80g (carton)",
  "category_id": 2,
  "unit": "carton",
  "purchase_price": 180,
  "sale_price": 240,
  "tax_rate": 20,
  "reorder_level": 15,
  "opening_stock": [ { "warehouse_id": 1, "quantity": 120 } ]
}
```

Filtres : `?category_id=`, `?low_stock=1`, `?is_active=`.
La réponse inclut `stock_quantity`, `reserved_quantity` et `is_low_stock`.

### Entrepôts — `warehouses.*`

`GET|POST /warehouses`, `GET|PUT|PATCH|DELETE /warehouses/{id}`.
`is_default: true` retire le flag des autres entrepôts.

## 7. Stock — `stock.view` / `stock.manage`

| Méthode | Route | Description |
| --- | --- | --- |
| GET | `/stock` | Niveaux par produit/entrepôt (`?product_id=`, `?warehouse_id=`, `?search=`, `?in_stock=1`) |
| GET | `/stock/movements` | Journal (`?product_id=`, `?warehouse_id=`, `?type=`, `?from=`, `?to=`) |
| POST | `/stock/movements` | Entrée / sortie / ajustement |

```json
POST /stock/movements
{ "product_id": 7, "warehouse_id": 1, "type": "in", "quantity": 50, "unit_cost": 180, "reason": "Réception directe" }
```

`type` : `in`, `out`, `adjustment` (quantité = nouveau niveau absolu), `transfer_in`, `transfer_out`.
Une sortie supérieure au disponible renvoie `422` avec un message explicite.

## 8. Achats — `purchase_orders.*`

| Méthode | Route | Permission |
| --- | --- | --- |
| GET | `/purchase-orders` | `purchase_orders.view` |
| POST | `/purchase-orders` | `purchase_orders.create` |
| GET | `/purchase-orders/{id}` | `purchase_orders.view` |
| PUT/PATCH | `/purchase-orders/{id}` | `purchase_orders.update` |
| DELETE | `/purchase-orders/{id}` | `purchase_orders.delete` |
| POST | `/purchase-orders/{id}/confirm` | `purchase_orders.confirm` |
| POST | `/purchase-orders/{id}/receive` | `purchase_orders.receive` |
| POST | `/purchase-orders/{id}/cancel` | `purchase_orders.update` |

```json
POST /purchase-orders
{
  "supplier_id": 3,
  "warehouse_id": 1,
  "order_date": "2026-11-02",
  "expected_date": "2026-11-10",
  "lines": [
    { "product_id": 7, "quantity": 40, "unit_price": 175, "discount": 0, "tax_rate": 20 }
  ]
}
```

Réception totale (corps vide) :

```json
POST /purchase-orders/12/receive
{}
```

Réception partielle :

```json
POST /purchase-orders/12/receive
{ "lines": { "34": { "quantity": 10, "unit_price": 180 } } }
```

Les quantités reçues sont **plafonnées** au reste à recevoir ; le statut passe à
`partially_received` puis `received`.

## 9. Ventes — `sales_orders.*`

| Méthode | Route | Permission |
| --- | --- | --- |
| GET | `/sales-orders` | `sales_orders.view` |
| POST | `/sales-orders` | `sales_orders.create` |
| GET | `/sales-orders/{id}` | `sales_orders.view` |
| PUT/PATCH | `/sales-orders/{id}` | `sales_orders.update` |
| DELETE | `/sales-orders/{id}` | `sales_orders.delete` |
| POST | `/sales-orders/{id}/confirm` | `sales_orders.confirm` |
| POST | `/sales-orders/{id}/deliver` | `sales_orders.deliver` |
| POST | `/sales-orders/{id}/cancel` | `sales_orders.update` |
| POST | `/sales-orders/{id}/invoice` | `invoices.create` |

```json
POST /sales-orders
{
  "client_id": 4,
  "warehouse_id": 1,
  "order_date": "2026-11-02",
  "lines": [
    { "product_id": 7, "quantity": 5, "unit_price": 240 }
  ]
}
```

Réponse (extrait) :

```json
{
  "data": {
    "id": 21,
    "reference": "SO-2026-0004",
    "status": "draft",
    "status_label": "Brouillon",
    "subtotal": 1200,
    "discount_total": 0,
    "tax_total": 240,
    "total": 1440,
    "lines": [ { "id": 51, "quantity": 5, "delivered_quantity": 0, "line_total": 1200 } ]
  }
}
```

Livraison totale : `POST /sales-orders/21/deliver` avec `{}`.
Livraison partielle : `{ "quantities": { "51": 2 } }`.

### Cycle de vie

```
draft ──confirm──▶ confirmed ──deliver──▶ partially_delivered ──deliver──▶ delivered ──invoice──▶ invoiced
  │                    │                                                                   
  └──── cancel ────────┴──▶ cancelled (réservations libérées)
```

## 10. Facturation — `invoices.*`

| Méthode | Route | Permission |
| --- | --- | --- |
| GET | `/invoices` | `invoices.view` |
| POST | `/invoices` | `invoices.create` |
| GET | `/invoices/{id}` | `invoices.view` |
| PUT/PATCH | `/invoices/{id}` | `invoices.update` |
| DELETE | `/invoices/{id}` | `invoices.delete` |
| POST | `/invoices/{id}/send` | `invoices.send` |
| POST | `/invoices/{id}/cancel` | `invoices.update` |
| POST | `/invoices/{id}/payments` | `payments.create` |

Filtres : `?status=`, `?type=invoice|credit_note`, `?outstanding=1`, `?client_id=`, `?from=`, `?to=`.

```json
POST /invoices
{
  "client_id": 4,
  "issue_date": "2026-11-02",
  "due_date": "2026-12-02",
  "lines": [
    { "description": "Prestation de conseil", "quantity": 1, "unit_price": 5000, "tax_rate": 20 }
  ]
}
```

`reference` : `FAC-YYYY-NNNN` (facture) ou `AVR-YYYY-NNNN` (avoir).
`balance_due` initialisé au total ; `due_date` par défaut = +30 jours.

### Encaissement

```json
POST /invoices/12/payments
{ "amount": 2000, "method": "bank_transfer", "payment_date": "2026-11-05", "reference_number": "VIR-8891" }
```

`method` : `cash`, `bank_transfer`, `check`, `card`, `other`.
Un montant supérieur au solde dû est refusé (`422`). Les statuts évoluent
automatiquement : `sent` → `partially_paid` → `paid`, ou `overdue` après échéance.

## 11. Paiements — `payments.view`

| Méthode | Route |
| --- | --- |
| GET | `/payments?direction=in&method=bank_transfer&from=&to=` |
| GET | `/payments/{id}` |

## 12. Administration

| Méthode | Route | Permission |
| --- | --- | --- |
| GET | `/users` | `users.view` |
| POST | `/users` | `users.create` |
| GET | `/users/{id}` | `users.view` |
| PUT/PATCH | `/users/{id}` | `users.update` |
| DELETE | `/users/{id}` | `users.delete` |
| GET | `/roles` | `roles.view` |
| GET | `/roles/{id}` | `roles.view` |
| GET | `/permissions` | `roles.view` |

`GET /permissions` renvoie le catalogue groupé par module, prêt pour une matrice
de droits côté Angular :

```json
{ "data": [ { "module": "clients", "permissions": [ { "id": 1, "name": "clients.view", "label": "Consulter les clients" } ] } ] }
```

## 13. Rôles livrés

| Rôle | Portée |
| --- | --- |
| `admin` | Toutes les permissions (bypass) |
| `manager` | Tout sauf administration des utilisateurs et rôles |
| `accountant` | Facturation, encaissements, consultation clients/produits |
| `stock_manager` | Catalogue, entrepôts, stock, réceptions |
| `sales` | Clients, commandes de vente, livraisons, facturation |

## 14. Tâches planifiées

| Commande | Fréquence | Effet |
| --- | --- | --- |
| `php artisan erp:refresh-overdue-invoices` | Quotidien 01:00 | Passe en `overdue` les factures impayées échues |
