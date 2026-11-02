<?php

/**
 * Dev helper (Windows sandbox workaround).
 *
 * Laravel's PackageManifest refuses to write bootstrap/cache/packages.php when
 * is_writable() reports false. In some locked-down Windows environments every
 * path reports "not writable" while actual writes still succeed, which blocks
 * the framework from booting. Generating the manifest with a plain
 * file_put_contents() call sidesteps the check.
 *
 * Docker containers build this file automatically; running this script is only
 * needed when booting Laravel directly on such a Windows host.
 */

$base = dirname(__DIR__).'/backend';
$vendorPath = $base.'/vendor';

$installedFile = $vendorPath.'/composer/installed.json';

if (! is_file($installedFile)) {
    fwrite(STDERR, "vendor/composer/installed.json not found — run composer install first.\n");
    exit(1);
}

$installed = json_decode(file_get_contents($installedFile), true);
$packages = $installed['packages'] ?? $installed;

$composerConfig = json_decode(file_get_contents($base.'/composer.json'), true);
$ignore = $composerConfig['extra']['laravel']['dont-discover'] ?? [];

$manifest = [];

foreach ($packages as $package) {
    $name = $package['name'];
    $configuration = $package['extra']['laravel'] ?? [];

    if (in_array($name, $ignore, true) || empty($configuration)) {
        continue;
    }

    $manifest[$name] = $configuration;
}

$target = $base.'/bootstrap/cache/packages.php';

file_put_contents($target, '<?php return '.var_export($manifest, true).';');

printf("Wrote %s (%d packages discovered)\n", $target, count($manifest));
