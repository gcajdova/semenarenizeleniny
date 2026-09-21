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
$checks['Detailní karty'] = false;
$checks['Výsevní kalendář'] = false;
$checks['Fotky'] = is_dir(APP_ROOT . '/public/uploads/seeds') && is_writable(APP_ROOT . '/public/uploads/seeds');

try {
    $pdo = db();
    $checks['Databáze'] = (bool) $pdo->query('SELECT 1')->fetchColumn();
    $checks['Kategorie'] = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() > 0;
    $checks['Detailní karty'] = (bool) $pdo->query("SHOW COLUMNS FROM seeds LIKE 'product_name'")->fetchColumn();
    $checks['Výsevní kalendář'] = (bool) $pdo->query("SHOW COLUMNS FROM seeds LIKE 'sowing_greenhouse_from'")->fetchColumn();
} catch (Throwable $exception) {
    fwrite(STDERR, 'Chyba databáze: ' . $exception->getMessage() . PHP_EOL);
}

$failed = false;
foreach ($checks as $name => $passed) {
    fwrite(STDOUT, sprintf("[%s] %s\n", $passed ? 'OK' : 'CHYBA', $name));
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
