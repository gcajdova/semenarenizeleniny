#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Použij tento příkaz z příkazové řádky.\n");
}

$checks = [];
$checks['.env'] = is_file(APP_ROOT . '/.env');
$checks['Databáze'] = false;
$checks['Kategorie'] = false;

try {
    $pdo = db();
    $checks['Databáze'] = (bool) $pdo->query('SELECT 1')->fetchColumn();
    $checks['Kategorie'] = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() > 0;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Chyba databáze: ' . $exception->getMessage() . PHP_EOL);
}

$failed = false;
foreach ($checks as $name => $passed) {
    fwrite(STDOUT, sprintf("[%s] %s\n", $passed ? 'OK' : 'CHYBA', $name));
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
