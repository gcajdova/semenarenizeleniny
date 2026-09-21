<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
start_session();

$page = (string) ($_GET['page'] ?? 'dashboard');
$action = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $statement = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
    $statement->execute(['username' => $username]);
    $account = $statement->fetch();
    if ($account && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        $_SESSION['username'] = $account['username'];
        flash('success', 'Vítej v evidenci semínek.');
        redirect('?');
    }
    flash('error', 'Přihlašovací údaje nesedí.');
    redirect('?page=login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    verify_csrf();
    $_SESSION = [];
    session_destroy();
    redirect('?page=login');
}

if (current_user() === null && $page !== 'login') {
    redirect('?page=login');
}

if (current_user() === null) {
    $flashes = consume_flashes();
    ?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#315d3a">
    <title>Přihlášení · Semínka</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="login-body">
<main class="login-card">
    <div class="brand-mark" aria-hidden="true">✿</div>
    <p class="eyebrow">Moje zahrada</p>
    <h1>Semínka</h1>
    <p class="intro">Přehled odrůd, zásoby a použitelnosti na jednom místě.</p>
    <?php foreach ($flashes as $flash): ?>
        <p class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
    <?php endforeach; ?>
    <form method="post" class="stack-form" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">
        <label>Uživatelské jméno
            <input name="username" required autocomplete="username" autofocus>
        </label>
        <label>Heslo
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="button primary" type="submit">Přihlásit se</button>
    </form>
</main>
</body>
</html>
<?php
    exit;
}

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    verify_csrf();
    try {
        if ($action === 'save_seed') {
            $seedId = filter_var($_POST['seed_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
            $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $variety = trim((string) ($_POST['variety'] ?? ''));
            $producer = trim((string) ($_POST['producer'] ?? ''));
            $location = trim((string) ($_POST['storage_location'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $packageCount = filter_var($_POST['package_count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 5000]]);

            if (!$categoryId) {
                throw new InvalidArgumentException('Vyber platnou kategorii.');
            }
            $checkCategory = db()->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
            $checkCategory->execute([$categoryId]);
            if (!(int) $checkCategory->fetchColumn()) {
                throw new InvalidArgumentException('Vyber platnou kategorii.');
            }
            if ($variety === '' || mb_strlen($variety) > 160) {
                throw new InvalidArgumentException('Odrůda je povinná a může mít nejvýše 160 znaků.');
            }
            if (mb_strlen($producer) > 160 || mb_strlen($location) > 120 || mb_strlen($notes) > 3000) {
                throw new InvalidArgumentException('Jedno z polí je příliš dlouhé.');
            }
            if ($packageCount === false) {
                throw new InvalidArgumentException('Počet sáčků musí být celé číslo od 0 do 5000.');
            }
            $purchaseYear = nullable_year($_POST['purchase_year'] ?? null);
            $expiryYear = nullable_year($_POST['expiry_year'] ?? null);

            $values = [
                'category_id' => $categoryId,
                'variety' => $variety,
                'producer' => $producer !== '' ? $producer : null,
                'package_count' => $packageCount,
                'purchase_year' => $purchaseYear,
                'expiry_year' => $expiryYear,
                'storage_location' => $location !== '' ? $location : null,
                'notes' => $notes !== '' ? $notes : null,
            ];
            if ($seedId) {
                $values['id'] = $seedId;
                $statement = db()->prepare('UPDATE seeds SET category_id = :category_id, variety = :variety, producer = :producer, package_count = :package_count, purchase_year = :purchase_year, expiry_year = :expiry_year, storage_location = :storage_location, notes = :notes WHERE id = :id');
                $statement->execute($values);
                flash('success', 'Záznam byl upraven.');
            } else {
                $values['created_by'] = $user['id'];
                $statement = db()->prepare('INSERT INTO seeds (category_id, variety, producer, package_count, purchase_year, expiry_year, storage_location, notes, created_by) VALUES (:category_id, :variety, :producer, :package_count, :purchase_year, :expiry_year, :storage_location, :notes, :created_by)');
                $statement->execute($values);
                flash('success', 'Semínko bylo přidáno do evidence.');
            }
        } elseif ($action === 'set_archive') {
            $seedId = filter_var($_POST['seed_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $archive = (int) ($_POST['archive'] ?? 1) === 1 ? 1 : 0;
            if (!$seedId) {
                throw new InvalidArgumentException('Záznam se nepodařilo určit.');
            }
            $statement = db()->prepare('UPDATE seeds SET is_archived = :archived WHERE id = :id');
            $statement->execute(['archived' => $archive, 'id' => $seedId]);
            flash('success', $archive ? 'Záznam byl přesunut do archivu.' : 'Záznam byl obnoven z archivu.');
        } elseif ($action === 'delete_seed') {
            $seedId = filter_var($_POST['seed_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$seedId) {
                throw new InvalidArgumentException('Záznam se nepodařilo určit.');
            }
            db()->prepare('DELETE FROM seeds WHERE id = ?')->execute([$seedId]);
            flash('success', 'Záznam byl smazán.');
        } elseif ($action === 'add_category') {
            $name = trim((string) ($_POST['category_name'] ?? ''));
            $color = strtoupper(trim((string) ($_POST['category_color'] ?? '#5E8E3E')));
            if ($name === '' || mb_strlen($name) > 100) {
                throw new InvalidArgumentException('Název kategorie je povinný a může mít nejvýše 100 znaků.');
            }
            if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
                throw new InvalidArgumentException('Barva kategorie nemá správný formát.');
            }
            db()->prepare('INSERT INTO categories (name, color) VALUES (?, ?)')->execute([$name, $color]);
            flash('success', 'Nová kategorie byla přidána.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Změnu se nepodařilo uložit.');
    }
    redirect('?');
}

$categories = db()->query('SELECT id, name, color FROM categories ORDER BY name')->fetchAll();
$seedToEdit = null;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($editId) {
    $statement = db()->prepare('SELECT * FROM seeds WHERE id = ? LIMIT 1');
    $statement->execute([$editId]);
    $seedToEdit = $statement->fetch() ?: null;
}

$selectedCategory = filter_var($_GET['category'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$query = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? 'active');
if (!in_array($status, ['active', 'review', 'archived'], true)) {
    $status = 'active';
}
$conditions = [$status === 'archived' ? 's.is_archived = 1' : 's.is_archived = 0'];
$params = [];
if ($selectedCategory) {
    $conditions[] = 's.category_id = :category_id';
    $params['category_id'] = $selectedCategory;
}
if ($query !== '') {
    $conditions[] = '(s.variety LIKE :query OR s.producer LIKE :query OR s.storage_location LIKE :query)';
    $params['query'] = '%' . $query . '%';
}
if ($status === 'review') {
    $conditions[] = 's.expiry_year IS NOT NULL AND s.expiry_year <= :current_year';
    $params['current_year'] = (int) date('Y');
}
$statement = db()->prepare('SELECT s.*, c.name AS category_name, c.color AS category_color FROM seeds s JOIN categories c ON c.id = s.category_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY c.name, s.variety');
$statement->execute($params);
$seeds = $statement->fetchAll();

$stats = db()->query('SELECT COUNT(*) AS varieties, COALESCE(SUM(package_count), 0) AS packages, COALESCE(SUM(expiry_year IS NOT NULL AND expiry_year <= YEAR(CURDATE())), 0) AS review_count FROM seeds WHERE is_archived = 0')->fetch();
$flashes = consume_flashes();
$form = $seedToEdit ?? ['id' => '', 'category_id' => '', 'variety' => '', 'producer' => '', 'package_count' => 1, 'purchase_year' => '', 'expiry_year' => '', 'storage_location' => '', 'notes' => ''];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#315d3a">
    <meta name="description" content="Evidence semínek zeleniny">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/app.css">
    <title>Semínka · Moje zahrada</title>
</head>
<body>
<header class="topbar">
    <a class="brand" href="?" aria-label="Semínka – přehled"><span aria-hidden="true">✿</span> Semínka</a>
    <div class="user-menu"><span><?= e($user['username']) ?></span><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="text-button" type="submit">Odhlásit</button></form></div>
</header>

<main class="app-shell">
    <section class="hero">
        <div><p class="eyebrow">Moje zahrada</p><h1>Evidence semínek</h1><p>Přehledně víš, co máš doma a co je potřeba prověřit.</p></div>
        <a class="button primary" href="#seed-form">+ Přidat semínko</a>
    </section>

    <?php foreach ($flashes as $flash): ?>
        <p class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
    <?php endforeach; ?>

    <section class="stats" aria-label="Souhrn zásoby">
        <article><span>Odrůd v evidenci</span><strong><?= (int) $stats['varieties'] ?></strong></article>
        <article><span>Sáčků celkem</span><strong><?= (int) $stats['packages'] ?></strong></article>
        <article><span>K prověření</span><strong><?= (int) $stats['review_count'] ?></strong><small>použitelnost letos nebo dříve</small></article>
    </section>

    <section class="workspace">
        <div class="inventory-panel">
            <div class="section-heading"><div><p class="eyebrow">Zásoba</p><h2>Moje semínka</h2></div><span class="record-count"><?= count($seeds) ?> záznamů</span></div>
            <form class="filters" method="get">
                <label class="sr-only" for="q">Hledat</label><input id="q" name="q" value="<?= e($query) ?>" placeholder="Hledat odrůdu, výrobce…">
                <label class="sr-only" for="category">Kategorie</label><select id="category" name="category"><option value="">Všechny druhy</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $selectedCategory === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select>
                <label class="sr-only" for="status">Stav</label><select id="status" name="status"><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktivní</option><option value="review" <?= $status === 'review' ? 'selected' : '' ?>>K prověření</option><option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archiv</option></select>
                <button class="button secondary" type="submit">Filtrovat</button>
            </form>
            <?php if ($seeds === []): ?>
                <div class="empty"><div aria-hidden="true">🌱</div><h3>Zatím tu nic není</h3><p>Přidej první sáček – třeba papriku, okurku nebo mrkev.</p></div>
            <?php else: ?>
                <div class="seed-list">
                <?php foreach ($seeds as $seed): ?>
                    <article class="seed-card">
                        <div class="seed-card-head"><span class="category-pill"><?= e($seed['category_name']) ?></span><span class="packages"><?= (int) $seed['package_count'] ?>× sáček</span></div>
                        <h3><?= e($seed['variety']) ?></h3>
                        <p class="producer"><?= e($seed['producer'] ?: 'Výrobce neuveden') ?></p>
                        <dl class="seed-meta">
                            <?php if ($seed['purchase_year']): ?><div><dt>Koupeno</dt><dd><?= (int) $seed['purchase_year'] ?></dd></div><?php endif; ?>
                            <?php if ($seed['expiry_year']): ?><div><dt>Použít do</dt><dd class="<?= (int) $seed['expiry_year'] <= (int) date('Y') ? 'attention' : '' ?>"><?= (int) $seed['expiry_year'] ?></dd></div><?php endif; ?>
                            <?php if ($seed['storage_location']): ?><div><dt>Uloženo</dt><dd><?= e($seed['storage_location']) ?></dd></div><?php endif; ?>
                        </dl>
                        <?php if ($seed['notes']): ?><p class="notes"><?= nl2br(e($seed['notes'])) ?></p><?php endif; ?>
                        <div class="card-actions"><a class="text-button" href="?edit=<?= (int) $seed['id'] ?>#seed-form">Upravit</a>
                            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="set_archive"><input type="hidden" name="seed_id" value="<?= (int) $seed['id'] ?>"><input type="hidden" name="archive" value="<?= $seed['is_archived'] ? '0' : '1' ?>"><button class="text-button" type="submit"><?= $seed['is_archived'] ? 'Obnovit' : 'Archivovat' ?></button></form>
                            <form method="post" data-confirm="Opravdu chceš tento záznam trvale smazat?"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_seed"><input type="hidden" name="seed_id" value="<?= (int) $seed['id'] ?>"><button class="text-button danger" type="submit">Smazat</button></form>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <aside class="form-panel" id="seed-form">
            <div class="section-heading"><div><p class="eyebrow"><?= $seedToEdit ? 'Úprava záznamu' : 'Nový záznam' ?></p><h2><?= $seedToEdit ? 'Upravit semínko' : 'Přidat semínko' ?></h2></div><?php if ($seedToEdit): ?><a class="text-button" href="?">Zrušit</a><?php endif; ?></div>
            <form method="post" class="stack-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_seed"><input type="hidden" name="seed_id" value="<?= (int) $form['id'] ?>">
                <label>Kategorie<select name="category_id" required><option value="">Vyber druh zeleniny</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) $form['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
                <label>Odrůda<input name="variety" maxlength="160" required value="<?= e($form['variety']) ?>" placeholder="Např. California Wonder"></label>
                <label>Výrobce / značka<input name="producer" maxlength="160" value="<?= e($form['producer']) ?>" placeholder="Např. Semo"></label>
                <div class="two-columns"><label>Počet sáčků<input name="package_count" type="number" min="0" max="5000" required value="<?= (int) $form['package_count'] ?>"></label><label>Uloženo kde<input name="storage_location" maxlength="120" value="<?= e($form['storage_location']) ?>" placeholder="Krabička ve skříni"></label></div>
                <div class="two-columns"><label>Rok nákupu<input name="purchase_year" type="number" min="1900" max="2200" value="<?= e((string) $form['purchase_year']) ?>" placeholder="2026"></label><label>Použít do roku<input name="expiry_year" type="number" min="1900" max="2200" value="<?= e((string) $form['expiry_year']) ?>" placeholder="2029"></label></div>
                <label>Poznámka<textarea name="notes" rows="3" maxlength="3000" placeholder="Barva, odkud semínka jsou, zkušenost…"><?= e($form['notes']) ?></textarea></label>
                <button class="button primary" type="submit"><?= $seedToEdit ? 'Uložit změny' : 'Přidat do evidence' ?></button>
            </form>
            <details class="category-manager"><summary>+ Přidat vlastní kategorii</summary><form method="post" class="category-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_category"><input name="category_name" maxlength="100" placeholder="Např. Jahody" required><input name="category_color" type="color" value="#5E8E3E" aria-label="Barva kategorie"><button class="button secondary" type="submit">Přidat</button></form></details>
        </aside>
    </section>
</main>
<script src="assets/app.js" defer></script>
</body>
</html>
