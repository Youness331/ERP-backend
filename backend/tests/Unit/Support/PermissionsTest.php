<?php

namespace Tests\Unit\Support;

use App\Support\Permissions;
use PHPUnit\Framework\TestCase;

class PermissionsTest extends TestCase
{
    public function test_the_catalogue_exposes_every_permission(): void
    {
        $expected = 0;

        foreach (Permissions::MODULES as $module) {
            $expected += count($module['actions']);
        }

        $this->assertSame($expected, count(Permissions::definitions()));
        $this->assertSame(58, count(Permissions::definitions()));
    }

    public function test_permission_names_are_unique(): void
    {
        $names = Permissions::names();

        $this->assertSame($names, array_values(array_unique($names)));
    }

    public function test_every_permission_is_namespaced_by_its_module(): void
    {
        foreach (Permissions::definitions() as $definition) {
            $this->assertStringStartsWith(
                $definition['module'].'.',
                $definition['name'],
                sprintf('Permission %s does not belong to module %s', $definition['name'], $definition['module']),
            );

            $this->assertArrayHasKey($definition['module'], Permissions::MODULES);
            $this->assertNotSame('', $definition['label']);
        }
    }

    public function test_every_module_has_a_label_and_at_least_one_action(): void
    {
        foreach (Permissions::MODULES as $module => $config) {
            $this->assertNotSame('', $config['label'], "Module {$module} is missing a label");
            $this->assertNotEmpty($config['actions'], "Module {$module} has no actions");
        }
    }

    public function test_names_returns_the_definitions_in_order(): void
    {
        $this->assertSame(
            array_column(Permissions::definitions(), 'name'),
            Permissions::names(),
        );
    }
}
