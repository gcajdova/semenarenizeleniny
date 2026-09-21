#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Použij tento příkaz z příkazové řádky.\n");
}

$username = $argv[1] ?? '';
if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $username)) {
    exit("Použití: php bin/create-user.php uzivatelske_jmeno\nJméno může obsahovat písmena, čísla, tečku, pomlčku a podtržítko.\n");
}

fwrite(STDOUT, 'Heslo: ');
$password = trim((string) fgets(STDIN));
if (mb_strlen($password) < 10) {
    exit("Heslo musí mít alespoň 10 znaků.\n");
}

$stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
try {
    $stmt->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    fwrite(STDOUT, "Uživatel {$username} byl vytvořen.\n");
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        exit("Takový uživatel už existuje.\n");
    }
    throw $exception;
}
