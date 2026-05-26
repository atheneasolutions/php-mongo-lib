#!/usr/bin/env php
<?php

/**
 * Test php-mongo-lib against all Symfony versions listed in composer.json.
 *
 * Usage: php test-all-symfony-versions.php [phpunit-args...]
 */

$scriptDir = dirname(__FILE__);
chdir($scriptDir . '/..');

$symfonyPackages = ['symfony/property-info', 'symfony/property-access', 'symfony/serializer'];

// Map major version -> specific test version
$versionMap = [
    5 => '5.4',
    6 => '6.4',
    7 => '7.4',
    8 => '8.0',
];

// Parse composer.json
$composerJson = json_decode(file_get_contents('composer.json'), true);
$require = $composerJson['require'] ?? [];

// Extract unique major versions from Symfony constraints
$majors = [];
foreach ($symfonyPackages as $pkg) {
    $constraint = $require[$pkg] ?? null;
    if (!$constraint) {
        echo "ERROR: {$pkg} not found in composer.json require\n";
        exit(1);
    }
    // Parse "^5.0 || ^6.0 || ^7.0 || ^8.0" style constraints
    preg_match_all('/\^(\d+)\./', $constraint, $matches);
    foreach ($matches[1] as $major) {
        $majors[(int)$major] = true;
    }
}
$majors = array_keys($majors);
sort($majors);

// Map to specific test versions
$testVersions = [];
foreach ($majors as $major) {
    $testVersions[] = $versionMap[$major] ?? "{$major}.0";
}

echo "========================================\n";
echo " Testing php-mongo-lib across Symfony versions\n";
echo " Versions: " . implode(', ', $testVersions) . "\n";
echo "========================================\n";

$originalComposerJson = file_get_contents('composer.json');
$originalComposerLock = file_exists('composer.lock') ? file_get_contents('composer.lock') : null;
$restored = false;

function restoreAndInstall(): void {
    global $originalComposerJson, $originalComposerLock, $restored;
    if ($restored) return;
    $restored = true;
    echo "\n>>> Restoring original composer.json and composer.lock ...\n";
    file_put_contents('composer.json', $originalComposerJson);
    if ($originalComposerLock !== null) {
        file_put_contents('composer.lock', $originalComposerLock);
    }
    passthru('composer install --no-interaction --quiet --ignore-platform-req=ext-mongodb 2>/dev/null');
    echo ">>> Restored.\n";
}

register_shutdown_function('restoreAndInstall');

// Also catch SIGINT (Ctrl+C)
pcntl_signal(SIGINT, function() { restoreAndInstall(); exit(130); });
pcntl_signal(SIGTERM, function() { restoreAndInstall(); exit(143); });

$phpunitArgs = array_slice($argv, 1);
$results = [];
$fail = 0;

foreach ($testVersions as $version) {
    echo "\n>>> Installing Symfony {$version} ...\n";

    $pkgArgs = [];
    foreach ($symfonyPackages as $pkg) {
        $pkgArgs[] = "{$pkg}:^{$version}";
    }

    $cmd = sprintf(
        'composer require %s --with-all-dependencies --ignore-platform-req=ext-mongodb --no-interaction --quiet 2>/dev/null',
        implode(' ', $pkgArgs)
    );
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        echo ">>> composer require failed for SF {$version}\n";
        $results[] = "SF {$version}: INSTALL FAILED";
        $fail = 1;
        continue;
    }

    echo ">>> Running tests with Symfony {$version} ...\n";

    $phpunitCmd = 'php vendor/bin/phpunit ' . implode(' ', array_map('escapeshellarg', $phpunitArgs));
    $output = shell_exec($phpunitCmd . ' 2>&1');
    echo $output;

    if (str_contains($output, "\nOK") || preg_match('/^OK/m', $output)) {
        preg_match('/Tests:\s*(\d+)/', $output, $testsMatch);
        preg_match('/Assertions:\s*(\d+)/', $output, $assertMatch);
        preg_match('/Skipped:\s*(\d+)/', $output, $skippedMatch);
        $tests = $testsMatch[1] ?? '?';
        $assertions = $assertMatch[1] ?? '?';
        $skipped = $skippedMatch[1] ?? '0';
        $results[] = "SF {$version}: OK ({$tests} tests, {$assertions} assertions, {$skipped} skipped)";
        echo ">>> SF {$version}: PASSED\n";
    } else {
        $errorLines = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^[0-9]+\)\s/', $line)) {
                $errorLines[] = $line;
                if (count($errorLines) >= 5) break;
            }
        }
        $results[] = "SF {$version}: FAILED";
        $fail = 1;
        echo ">>> SF {$version}: FAILED\n";
        if ($errorLines) {
            echo "    " . implode("\n    ", $errorLines) . "\n";
        }
    }
}

echo "\n========================================\n";
echo " Results Summary\n";
echo "========================================\n";
foreach ($results as $result) {
    echo "  {$result}\n";
}

if ($fail) {
    echo "\nSome versions FAILED.\n";
} else {
    echo "\nAll versions PASSED.\n";
}

restoreAndInstall();

if ($fail) {
    exit(1);
}