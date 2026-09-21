<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

function env(string $key, string $default = ''): string
{
    static $values = null;
    if ($values === null) {
        $values = [];
        $path = APP_ROOT . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $values[trim($name)] = trim(trim($value), "\"'");
            }
        }
    }

    return $values[$key] ?? (getenv($key) !== false ? (string) getenv($key) : $default);
}

date_default_timezone_set(env('APP_TIMEZONE', 'Europe/Prague'));

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', 'localhost'),
        env('DB_PORT', '3306'),
        env('DB_NAME', 'seed_inventory')
    );
    $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(env('SESSION_NAME', 'seed_inventory'));
    session_set_cookie_params([
        'httponly' => true,
        'secure' => env('SESSION_SECURE_COOKIE', '0') === '1',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
        http_response_code(419);
        exit('Neplatný požadavek. Obnov stránku a zkus to znovu.');
    }
}

function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return ['id' => (int) $_SESSION['user_id'], 'username' => (string) $_SESSION['username']];
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: ?page=login');
        exit;
    }

    return $user;
}

function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flashes(): array
{
    start_session();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function redirect(string $path = '?'): void
{
    header('Location: ' . $path);
    exit;
}

function nullable_year(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    $year = filter_var($value, FILTER_VALIDATE_INT);
    if ($year === false || $year < 1900 || $year > 2200) {
        throw new InvalidArgumentException('Rok musí být mezi 1900 a 2200.');
    }

    return $year;
}
