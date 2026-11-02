<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::definitions() as $definition) {
            Permission::updateOrCreate(
                ['name' => $definition['name']],
                ['label' => $definition['label'], 'module' => $definition['module']],
            );
        }

        $permissionIds = Permission::query()->pluck('id', 'name');

        $matrix = [
            'admin' => [
                'label' => 'Administrateur',
                'description' => 'Accès complet à toutes les fonctionnalités.',
                'permissions' => Permissions::names(),
            ],
            'manager' => [
                'label' => 'Responsable des opérations',
                'description' => 'Pilote les achats, les ventes et le stock.',
                'permissions' => array_values(array_filter(
                    Permissions::names(),
                    fn (string $name) => ! str_starts_with($name, 'users.') && ! str_starts_with($name, 'roles.'),
                )),
            ],
            'accountant' => [
                'label' => 'Comptable',
                'description' => 'Gère la facturation et les encaissements.',
                'permissions' => [
                    'dashboard.view',
                    'clients.view',
                    'suppliers.view',
                    'products.view',
                    'sales_orders.view',
                    'invoices.view',
                    'invoices.create',
                    'invoices.update',
                    'invoices.send',
                    'invoices.delete',
                    'payments.view',
                    'payments.create',
                ],
            ],
            'stock_manager' => [
                'label' => 'Magasinier',
                'description' => 'Gère le catalogue, les entrepôts et les réceptions.',
                'permissions' => [
                    'dashboard.view',
                    'products.view',
                    'products.create',
                    'products.update',
                    'categories.view',
                    'categories.create',
                    'categories.update',
                    'warehouses.view',
                    'warehouses.create',
                    'warehouses.update',
                    'stock.view',
                    'stock.manage',
                    'suppliers.view',
                    'suppliers.create',
                    'suppliers.update',
                    'purchase_orders.view',
                    'purchase_orders.create',
                    'purchase_orders.update',
                    'purchase_orders.confirm',
                    'purchase_orders.receive',
                ],
            ],
            'sales' => [
                'label' => 'Commercial',
                'description' => 'Prospecte, vend et suit les livraisons.',
                'permissions' => [
                    'dashboard.view',
                    'clients.view',
                    'clients.create',
                    'clients.update',
                    'products.view',
                    'stock.view',
                    'sales_orders.view',
                    'sales_orders.create',
                    'sales_orders.update',
                    'sales_orders.confirm',
                    'sales_orders.deliver',
                    'invoices.view',
                    'invoices.create',
                    'invoices.send',
                    'payments.view',
                ],
            ],
        ];

        foreach ($matrix as $name => $config) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                ['label' => $config['label'], 'description' => $config['description']],
            );

            $ids = $permissionIds->only($config['permissions'])->values()->all();
            $role->permissions()->sync($ids);
        }
    }
}
