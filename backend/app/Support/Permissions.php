<?php

namespace App\Support;

/**
 * Single source of truth for the RBAC catalogue.
 *
 * Permission names are always "<module>.<action>" so they can be mapped
 * directly onto Angular route guards.
 */
final class Permissions
{
    /**
     * module => [label, actions => [action => human label]]
     *
     * @var array<string, array{label: string, actions: array<string, string>}>
     */
    public const MODULES = [
        'dashboard' => [
            'label' => 'Tableau de bord',
            'actions' => ['view' => 'Consulter le tableau de bord'],
        ],
        'clients' => [
            'label' => 'Clients',
            'actions' => [
                'view' => 'Consulter les clients',
                'create' => 'Créer un client',
                'update' => 'Modifier un client',
                'delete' => 'Supprimer un client',
            ],
        ],
        'suppliers' => [
            'label' => 'Fournisseurs',
            'actions' => [
                'view' => 'Consulter les fournisseurs',
                'create' => 'Créer un fournisseur',
                'update' => 'Modifier un fournisseur',
                'delete' => 'Supprimer un fournisseur',
            ],
        ],
        'categories' => [
            'label' => 'Catégories',
            'actions' => [
                'view' => 'Consulter les catégories',
                'create' => 'Créer une catégorie',
                'update' => 'Modifier une catégorie',
                'delete' => 'Supprimer une catégorie',
            ],
        ],
        'products' => [
            'label' => 'Produits',
            'actions' => [
                'view' => 'Consulter les produits',
                'create' => 'Créer un produit',
                'update' => 'Modifier un produit',
                'delete' => 'Supprimer un produit',
            ],
        ],
        'warehouses' => [
            'label' => 'Entrepôts',
            'actions' => [
                'view' => 'Consulter les entrepôts',
                'create' => 'Créer un entrepôt',
                'update' => 'Modifier un entrepôt',
                'delete' => 'Supprimer un entrepôt',
            ],
        ],
        'stock' => [
            'label' => 'Stock',
            'actions' => [
                'view' => 'Consulter le stock et les mouvements',
                'manage' => 'Enregistrer des mouvements de stock',
            ],
        ],
        'purchase_orders' => [
            'label' => 'Commandes fournisseurs',
            'actions' => [
                'view' => 'Consulter les commandes fournisseurs',
                'create' => 'Créer une commande fournisseur',
                'update' => 'Modifier / annuler une commande fournisseur',
                'delete' => 'Supprimer une commande fournisseur',
                'confirm' => 'Confirmer une commande fournisseur',
                'receive' => 'Réceptionner une commande fournisseur',
            ],
        ],
        'sales_orders' => [
            'label' => 'Commandes clients',
            'actions' => [
                'view' => 'Consulter les commandes clients',
                'create' => 'Créer une commande client',
                'update' => 'Modifier / annuler une commande client',
                'delete' => 'Supprimer une commande client',
                'confirm' => 'Confirmer une commande client',
                'deliver' => 'Livrer une commande client',
            ],
        ],
        'quotes' => [
            'label' => 'Devis',
            'actions' => [
                'view' => 'Consulter les devis',
                'create' => 'Créer un devis',
                'update' => 'Modifier un devis',
                'delete' => 'Supprimer un devis',
                'send' => 'Envoyer un devis',
                'convert' => 'Convertir un devis en commande',
            ],
        ],
        'invoices' => [
            'label' => 'Factures',
            'actions' => [
                'view' => 'Consulter les factures',
                'create' => 'Créer une facture',
                'update' => 'Modifier / annuler une facture',
                'delete' => 'Supprimer une facture',
                'send' => 'Envoyer une facture',
            ],
        ],
        'payments' => [
            'label' => 'Paiements',
            'actions' => [
                'view' => 'Consulter les paiements',
                'create' => 'Enregistrer un paiement (encaissement ou décaissement)',
            ],
        ],
        'bank' => [
            'label' => 'Rapprochement bancaire',
            'actions' => [
                'view' => 'Consulter les relevés bancaires',
                'create' => 'Saisir une ligne de relevé',
                'reconcile' => 'Rapprocher une ligne avec un paiement',
            ],
        ],
        'reports' => [
            'label' => 'Rapports',
            'actions' => [
                'view' => 'Consulter les rapports',
            ],
        ],
        'audit' => [
            'label' => 'Journal d\'activité',
            'actions' => [
                'view' => 'Consulter le journal d\'activité',
            ],
        ],
        'users' => [
            'label' => 'Utilisateurs',
            'actions' => [
                'view' => 'Consulter les utilisateurs',
                'create' => 'Créer un utilisateur',
                'update' => 'Modifier un utilisateur',
                'delete' => 'Supprimer un utilisateur',
            ],
        ],
        'roles' => [
            'label' => 'Rôles & permissions',
            'actions' => [
                'view' => 'Consulter les rôles et permissions',
            ],
        ],
    ];

    /**
     * @return array<int, array{name: string, label: string, module: string}>
     */
    public static function definitions(): array
    {
        $definitions = [];

        foreach (self::MODULES as $module => $config) {
            foreach ($config['actions'] as $action => $label) {
                $definitions[] = [
                    'name' => $module.'.'.$action,
                    'label' => $label,
                    'module' => $module,
                ];
            }
        }

        return $definitions;
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(self::definitions(), 'name');
    }
}
