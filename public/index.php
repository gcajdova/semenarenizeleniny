<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
start_session();

const VEGETABLE_SUBCATEGORIES = [
    'Paprika',
    'Chilli',
    'Rajče',
    'Cherry rajče',
    'Okurka salátová',
    'Okurka nakladačka',
    'Mrkev / celer / petržel',
    'Cibule / pórek',
    'Kedluben',
    'Dýně / cuketa / lilek',
    'Květák / brokolice',
    'Saláty',
    'Špenát / mangold',
    'Ředkvička / ředkev',
    'Řepa',
    'Kukuřice',
    'Hrách',
];

function seed_text(string $key, int $max): ?string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if (mb_strlen($value) > $max) {
        throw new InvalidArgumentException('Jedno z polí je příliš dlouhé.');
    }

    return $value === '' ? null : $value;
}

function safe_image_path(?string $path): ?string
{
    return $path && str_starts_with($path, 'uploads/seeds/') ? APP_ROOT . '/public/' . $path : null;
}

function save_month_date(string $key, string $label): ?string
{
    $month = trim((string) ($_POST[$key] ?? ''));
    if ($month === '') {
        return null;
    }
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        throw new InvalidArgumentException($label . ' musí obsahovat měsíc a rok.');
    }

    return $month . '-01';
}

function form_month_date(?string $date): string
{
    return $date ? substr($date, 0, 7) : '';
}

function display_month_date(?string $date): string
{
    return $date ? (new DateTimeImmutable($date))->format('m/Y') : '';
}

function expiry_end(?string $date): ?string
{
    return $date ? (new DateTimeImmutable($date))->modify('last day of this month')->format('Y-m-d') : null;
}

function month_number(string $key, string $label): ?int
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^(?:[1-9]|1[0-2])$/', $value)) {
        throw new InvalidArgumentException($label . ' musí být platný měsíc.');
    }

    return (int) $value;
}

function validate_month_range(?int $from, ?int $to, string $label): void
{
    if ($to !== null && $from === null) {
        throw new InvalidArgumentException($label . ': nejdřív vyber začátek období.');
    }
    if ($from !== null && $to !== null && $to < $from) {
        throw new InvalidArgumentException($label . ': konec období musí být stejný nebo pozdější měsíc.');
    }
}

function czech_months(): array
{
    return [
        1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben',
        5 => 'květen', 6 => 'červen', 7 => 'červenec', 8 => 'srpen',
        9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec',
    ];
}

function month_range(mixed $from, mixed $to): ?string
{
    $from = $from === null || $from === '' ? null : (int) $from;
    $to = $to === null || $to === '' ? null : (int) $to;
    if ($from === null) {
        return null;
    }
    $months = czech_months();
    if (!isset($months[$from])) {
        return null;
    }
    if ($to === null || $to === $from || !isset($months[$to])) {
        return $months[$from];
    }

    return $months[$from] . '–' . $months[$to];
}

function category_class(string $name): string
{
    return match ($name) {
        'Zelenina' => 'category-vegetable',
        'Ovoce' => 'category-fruit',
        'Bylinky' => 'category-herb',
        'Květiny' => 'category-flower',
        default => 'category-neutral',
    };
}

function query_url(array $values): string
{
    $values = array_filter($values, static fn (mixed $value): bool => $value !== null && $value !== '');
    return '?' . http_build_query($values);
}

$action = (string) ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $statement = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
    $statement->execute([$username]);
    $account = $statement->fetch();
    if ($account && password_verify((string) ($_POST['password'] ?? ''), $account['password_hash'])) {
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
if (current_user() === null && ($_GET['page'] ?? '') !== 'login') {
    redirect('?page=login');
}
if (current_user() === null):
    $flashes = consume_flashes();
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#315d3a">
    <script>!function(){try{const e=localStorage.getItem('seminka-theme');if('dark'===e||!e&&matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.dataset.theme='dark'}catch(e){}}();</script>
    <title>Přihlášení · Semínka</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="login-body">
    <main class="login-card">
        <div class="brand-mark">✿</div>
        <p class="eyebrow">Moje zahrada</p>
        <h1>Semínka</h1>
        <p class="intro">Přehled sáčků, výsevů a použitelnosti.</p>
        <?php foreach ($flashes as $flash): ?>
            <p class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
        <?php endforeach; ?>
        <form method="post" class="stack-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="login">
            <label>Uživatelské jméno<input name="username" required autocomplete="username" autofocus></label>
            <label>Heslo<input type="password" name="password" required autocomplete="current-password"></label>
            <button class="button primary">Přihlásit se</button>
        </form>
    </main>
</body>
</html>
<?php exit; endif;

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    verify_csrf();
    try {
        if ($action === 'save_seed') {
            $seedId = filter_var($_POST['seed_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
            $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $name = seed_text('product_name', 160);
            $amount = ($_POST['amount_value'] ?? '') === '' ? null : filter_var($_POST['amount_value'], FILTER_VALIDATE_FLOAT);
            $unit = (string) ($_POST['amount_unit'] ?? '');
            if (!$categoryId || !$name) {
                throw new InvalidArgumentException('Vyber kategorii a napiš název semínka.');
            }
            $category = db()->prepare('SELECT id, name FROM categories WHERE id = ?');
            $category->execute([$categoryId]);
            $category = $category->fetch();
            if (!$category) {
                throw new InvalidArgumentException('Vyber platnou hlavní kategorii.');
            }
            $subcategory = seed_text('subcategory', 100);
            if ($subcategory !== null && ($category['name'] !== 'Zelenina' || !in_array($subcategory, VEGETABLE_SUBCATEGORIES, true))) {
                throw new InvalidArgumentException('Vyber platnou podkategorii zeleniny.');
            }
            if ($category['name'] !== 'Zelenina') {
                $subcategory = null;
            }
            if ($amount !== null && ($amount === false || $amount < 0 || $amount > 100000)) {
                throw new InvalidArgumentException('Množství není platné.');
            }
            if ($amount !== null && !in_array($unit, ['g', 'ks'], true)) {
                throw new InvalidArgumentException('Vyber jednotku gramů nebo kusů.');
            }

            $packagedOn = save_month_date('packaged_on', 'Datum balení');
            $expiresOn = save_month_date('expires_on', 'Datum expirace');
            $hasGreenhouse = isset($_POST['sowing_greenhouse_enabled']);
            $hasSoil = isset($_POST['sowing_soil_enabled']);
            $greenhouseFrom = $hasGreenhouse ? month_number('sowing_greenhouse_from', 'Výsev ve skleníku') : null;
            $greenhouseTo = $hasGreenhouse ? month_number('sowing_greenhouse_to', 'Výsev ve skleníku') : null;
            $soilFrom = $hasSoil ? month_number('sowing_soil_from', 'Výsev do půdy') : null;
            $soilTo = $hasSoil ? month_number('sowing_soil_to', 'Výsev do půdy') : null;
            $transplantFrom = month_number('transplant_from', 'Přesazení');
            $transplantTo = month_number('transplant_to', 'Přesazení');
            validate_month_range($greenhouseFrom, $greenhouseTo, 'Výsev ve skleníku');
            validate_month_range($soilFrom, $soilTo, 'Výsev do půdy');
            validate_month_range($transplantFrom, $transplantTo, 'Přesazení');

            $values = [
                'category_id' => $categoryId,
                'subcategory' => $subcategory,
                'product_name' => $name,
                'supplier' => seed_text('supplier', 160),
                'ean' => seed_text('ean', 32),
                'amount_value' => $amount,
                'amount_unit' => $amount === null ? null : $unit,
                'packaged_on' => $packagedOn,
                'expires_on' => $expiresOn,
                'sowing_greenhouse_from' => $greenhouseFrom,
                'sowing_greenhouse_to' => $greenhouseTo,
                'sowing_soil_from' => $soilFrom,
                'sowing_soil_to' => $soilTo,
                'sowing_depth' => seed_text('sowing_depth', 80),
                'transplant_from' => $transplantFrom,
                'transplant_to' => $transplantTo,
                'spacing' => seed_text('spacing', 100),
                'harvest_period' => seed_text('harvest_period', 120),
                'notes' => seed_text('notes', 3000),
            ];
            $oldImage = null;
            if ($seedId) {
                $old = db()->prepare('SELECT image_path FROM seeds WHERE id = ?');
                $old->execute([$seedId]);
                $oldImage = $old->fetchColumn();
                if ($oldImage === false) {
                    throw new InvalidArgumentException('Záznam už neexistuje.');
                }
                $values['id'] = $seedId;
                db()->prepare(
                    'UPDATE seeds SET category_id=:category_id, subcategory=:subcategory, product_name=:product_name, supplier=:supplier, ean=:ean, amount_value=:amount_value, amount_unit=:amount_unit, packaged_on=:packaged_on, expires_on=:expires_on, sowing_greenhouse_from=:sowing_greenhouse_from, sowing_greenhouse_to=:sowing_greenhouse_to, sowing_soil_from=:sowing_soil_from, sowing_soil_to=:sowing_soil_to, sowing_depth=:sowing_depth, transplant_from=:transplant_from, transplant_to=:transplant_to, spacing=:spacing, harvest_period=:harvest_period, notes=:notes WHERE id=:id'
                )->execute($values);
            } else {
                $values['created_by'] = $user['id'];
                db()->prepare(
                    'INSERT INTO seeds (category_id, subcategory, product_name, supplier, ean, amount_value, amount_unit, packaged_on, expires_on, sowing_greenhouse_from, sowing_greenhouse_to, sowing_soil_from, sowing_soil_to, sowing_depth, transplant_from, transplant_to, spacing, harvest_period, notes, created_by) VALUES (:category_id,:subcategory,:product_name,:supplier,:ean,:amount_value,:amount_unit,:packaged_on,:expires_on,:sowing_greenhouse_from,:sowing_greenhouse_to,:sowing_soil_from,:sowing_soil_to,:sowing_depth,:transplant_from,:transplant_to,:spacing,:harvest_period,:notes,:created_by)'
                )->execute($values);
                $seedId = (int) db()->lastInsertId();
            }

            $upload = $_FILES['image'] ?? null;
            if (is_array($upload) && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > (int) env('UPLOAD_MAX_BYTES', '5242880')) {
                    throw new InvalidArgumentException('Fotku se nepodařilo nahrát nebo je větší než 5 MB.');
                }
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensions[$mime])) {
                    throw new InvalidArgumentException('Nahraj prosím fotografii JPG, PNG nebo WebP.');
                }
                $relative = 'uploads/seeds/seed-' . $seedId . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
                $target = APP_ROOT . '/public/' . $relative;
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0750, true);
                }
                if (!move_uploaded_file($upload['tmp_name'], $target)) {
                    throw new InvalidArgumentException('Fotku se nepodařilo uložit.');
                }
                db()->prepare('UPDATE seeds SET image_path = ? WHERE id = ?')->execute([$relative, $seedId]);
                $oldFile = safe_image_path(is_string($oldImage) ? $oldImage : null);
                if ($oldFile && is_file($oldFile)) {
                    unlink($oldFile);
                }
            }
            flash('success', 'Karta sáčku byla uložena.');
        } elseif ($action === 'set_archive') {
            $id = filter_var($_POST['seed_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                throw new InvalidArgumentException('Záznam se nepodařilo určit.');
            }
            $archived = (int) ($_POST['archive'] ?? 1) === 1 ? 1 : 0;
            db()->prepare('UPDATE seeds SET is_archived = ? WHERE id = ?')->execute([$archived, $id]);
            flash('success', $archived ? 'Sáček byl přesunut do archivu.' : 'Sáček byl obnoven.');
        } elseif ($action === 'delete_seed') {
            $id = filter_var($_POST['seed_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) {
                throw new InvalidArgumentException('Záznam se nepodařilo určit.');
            }
            $get = db()->prepare('SELECT image_path FROM seeds WHERE id = ?');
            $get->execute([$id]);
            $path = safe_image_path($get->fetchColumn() ?: null);
            db()->prepare('DELETE FROM seeds WHERE id = ?')->execute([$id]);
            if ($path && is_file($path)) {
                unlink($path);
            }
            flash('success', 'Sáček byl smazán.');
        }
    } catch (Throwable $error) {
        flash('error', $error instanceof InvalidArgumentException ? $error->getMessage() : 'Změnu se nepodařilo uložit.');
    }
    redirect('?');
}

$categories = db()->query("SELECT id, name, color FROM categories WHERE name IN ('Zelenina', 'Bylinky', 'Květiny', 'Ovoce') ORDER BY FIELD(name, 'Zelenina', 'Ovoce', 'Bylinky', 'Květiny')")->fetchAll();
$vegetableCategoryId = null;
foreach ($categories as $category) {
    if ($category['name'] === 'Zelenina') {
        $vegetableCategoryId = (int) $category['id'];
        break;
    }
}
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$edit = null;
if ($editId) {
    $statement = db()->prepare('SELECT * FROM seeds WHERE id = ?');
    $statement->execute([$editId]);
    $edit = $statement->fetch() ?: null;
}
$categoryId = filter_var($_GET['category'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? 'active');
if (!in_array($status, ['active', 'renew', 'archived'], true)) {
    $status = 'active';
}
$subcategoryFilter = trim((string) ($_GET['subcategory'] ?? ''));
if ($categoryId !== $vegetableCategoryId || !in_array($subcategoryFilter, VEGETABLE_SUBCATEGORIES, true)) {
    $subcategoryFilter = '';
}
$days = max(1, min(365, (int) env('EXPIRY_ALERT_DAYS', '90')));
$limit = (new DateTimeImmutable('today'))->modify('+' . $days . ' days')->format('Y-m-d');
$conditions = [$status === 'archived' ? 's.is_archived=1' : 's.is_archived=0'];
$params = [];
if ($categoryId) {
    $conditions[] = 's.category_id=:category';
    $params['category'] = $categoryId;
}
if ($subcategoryFilter !== '') {
    $conditions[] = 's.subcategory=:subcategory';
    $params['subcategory'] = $subcategoryFilter;
}
if ($q !== '') {
    $conditions[] = '(s.product_name LIKE :q OR s.supplier LIKE :q OR s.ean LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($status === 'renew') {
    $conditions[] = 's.expires_on IS NOT NULL AND LAST_DAY(s.expires_on) <= :limit';
    $params['limit'] = $limit;
}
$list = db()->prepare('SELECT s.*, c.name category_name, c.color category_color FROM seeds s JOIN categories c ON c.id=s.category_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY s.expires_on IS NULL, LAST_DAY(s.expires_on), s.product_name');
$list->execute($params);
$seeds = $list->fetchAll();
$stats = db()->prepare('SELECT COUNT(*) cards, COALESCE(SUM(image_path IS NOT NULL),0) photos, COALESCE(SUM(expires_on IS NOT NULL AND LAST_DAY(expires_on) <= :limit),0) renew FROM seeds WHERE is_archived=0');
$stats->execute(['limit' => $limit]);
$stats = $stats->fetch();
$renew = db()->prepare('SELECT id, product_name, expires_on FROM seeds WHERE is_archived=0 AND expires_on IS NOT NULL AND LAST_DAY(expires_on) <= ? ORDER BY LAST_DAY(expires_on) LIMIT 8');
$renew->execute([$limit]);
$renew = $renew->fetchAll();
$form = $edit ?? [
    'id' => '', 'category_id' => '', 'subcategory' => '', 'product_name' => '', 'supplier' => '', 'ean' => '',
    'amount_value' => '', 'amount_unit' => 'ks', 'packaged_on' => '', 'expires_on' => '', 'image_path' => '',
    'sowing_greenhouse_from' => '', 'sowing_greenhouse_to' => '', 'sowing_soil_from' => '', 'sowing_soil_to' => '',
    'sowing_depth' => '', 'transplant_from' => '', 'transplant_to' => '', 'spacing' => '', 'harvest_period' => '', 'notes' => '',
];
$flashes = consume_flashes();
$formIsVegetable = (int) $form['category_id'] === $vegetableCategoryId;
$formHasGreenhouse = $form['sowing_greenhouse_from'] !== null && $form['sowing_greenhouse_from'] !== '';
$formHasSoil = $form['sowing_soil_from'] !== null && $form['sowing_soil_from'] !== '';
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#315d3a">
    <script>!function(){try{const e=localStorage.getItem('seminka-theme');if('dark'===e||!e&&matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.dataset.theme='dark'}catch(e){}}();</script>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/details.css">
    <title>Semínka · Moje zahrada</title>
</head>
<body>
<header class="topbar">
    <a class="brand" href="?"><span>✿</span> Semínka</a>
    <div class="user-menu">
        <button class="theme-toggle" type="button" data-theme-toggle aria-pressed="false" title="Přepnout tmavý režim">
            <span aria-hidden="true" data-theme-icon>☾</span><span data-theme-label>Tmavý režim</span>
        </button>
        <span><?= e($user['username']) ?></span>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="logout">
            <button class="text-button">Odhlásit</button>
        </form>
    </div>
</header>
<main class="app-shell">
    <section class="hero">
        <div>
            <p class="eyebrow">Moje zahrada</p>
            <h1>Karty sáčků semínek</h1>
            <p>Od balení přes výsev až po sklizeň — včetně hlídání čerstvosti.</p>
        </div>
        <a class="button primary" href="#seed-form">+ Přidat sáček</a>
    </section>
    <?php foreach ($flashes as $flash): ?>
        <p class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
    <?php endforeach; ?>
    <section class="stats">
        <article><span>Sáčků v evidenci</span><strong><?= (int) $stats['cards'] ?></strong></article>
        <article><span>Vyfocených sáčků</span><strong><?= (int) $stats['photos'] ?></strong></article>
        <article><span>Čerstvě koupit</span><strong><?= (int) $stats['renew'] ?></strong><small>do <?= e((new DateTimeImmutable($limit))->format('j. n. Y')) ?></small></article>
    </section>
    <?php if ($renew): ?>
        <section class="renewal">
            <div>
                <p class="eyebrow">Nákup čerstvých semínek</p>
                <h2>Blížící se expirace</h2>
                <p>Tyto sáčky končí nejpozději v následujících <?= $days ?> dnech.</p>
            </div>
            <ul>
                <?php foreach ($renew as $row): ?>
                    <li><a href="?edit=<?= (int) $row['id'] ?>#seed-form"><?= e($row['product_name']) ?></a><span>do <?= e(display_month_date($row['expires_on'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <a class="button secondary" href="?status=renew">Zobrazit celý seznam</a>
        </section>
    <?php endif; ?>
    <section class="workspace">
        <div class="inventory-panel">
            <div class="section-heading">
                <div><p class="eyebrow">Zásoba</p><h2>Moje semínka</h2></div>
                <span class="record-count"><?= count($seeds) ?> záznamů</span>
            </div>
            <nav class="category-tabs" aria-label="Hlavní kategorie">
                <a class="all-categories <?= $categoryId === null ? 'is-active' : '' ?>" href="<?= e(query_url(['q' => $q, 'status' => $status])) ?>">Vše</a>
                <?php foreach ($categories as $category): ?>
                    <?php $id = (int) $category['id']; ?>
                    <a class="category-tab <?= e(category_class($category['name'])) ?> <?= $categoryId === $id ? 'is-active' : '' ?>" href="<?= e(query_url(['category' => $id, 'q' => $q, 'status' => $status])) ?>"><?= e($category['name']) ?></a>
                <?php endforeach; ?>
            </nav>
            <form class="filters <?= $categoryId === $vegetableCategoryId ? 'with-subcategory' : '' ?>" method="get">
                <?php if ($categoryId): ?><input type="hidden" name="category" value="<?= $categoryId ?>"><?php endif; ?>
                <input name="q" value="<?= e($q) ?>" placeholder="Hledat název, dodavatele, EAN…">
                <?php if ($categoryId === $vegetableCategoryId): ?>
                    <select name="subcategory">
                        <option value="">Všechny druhy zeleniny</option>
                        <?php foreach (VEGETABLE_SUBCATEGORIES as $subcategory): ?>
                            <option value="<?= e($subcategory) ?>" <?= $subcategoryFilter === $subcategory ? 'selected' : '' ?>><?= e($subcategory) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <select name="status">
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktivní</option>
                    <option value="renew" <?= $status === 'renew' ? 'selected' : '' ?>>K nákupu</option>
                    <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archiv</option>
                </select>
                <button class="button secondary">Filtrovat</button>
            </form>
            <?php if (!$seeds): ?>
                <div class="empty"><div>🌱</div><h3>Zatím tu nic není</h3><p>Přidej první sáček a jeho pěstební údaje.</p></div>
            <?php else: ?>
                <div class="seed-list">
                    <?php foreach ($seeds as $seed): ?>
                        <?php $isExpiring = ($end = expiry_end($seed['expires_on'])) !== null && $end <= $limit; ?>
                        <article class="seed-card detailed-card">
                            <?php if ($seed['image_path']): ?><img class="seed-photo" src="<?= e($seed['image_path']) ?>" alt="Sáček <?= e($seed['product_name']) ?>"><?php endif; ?>
                            <div class="seed-card-head">
                                <span class="category-pill <?= e(category_class($seed['category_name'])) ?>"><?= e($seed['category_name']) ?></span>
                                <?php if ($seed['expires_on']): ?><span class="packages <?= $isExpiring ? 'attention' : '' ?>">do <?= e(display_month_date($seed['expires_on'])) ?></span><?php endif; ?>
                            </div>
                            <h3><?= e($seed['product_name']) ?></h3>
                            <p class="producer"><?= e($seed['supplier'] ?: 'Dodavatel neuveden') ?><?= $seed['ean'] ? ' · EAN ' . e($seed['ean']) : '' ?></p>
                            <?php if ($seed['subcategory']): ?><p class="subcategory-label"><?= e($seed['subcategory']) ?></p><?php endif; ?>
                            <dl class="seed-meta">
                                <?php if ($seed['amount_value'] !== null): ?><div><dt>Balení</dt><dd><?= e(rtrim(rtrim((string) $seed['amount_value'], '0'), '.')) ?> <?= e($seed['amount_unit']) ?></dd></div><?php endif; ?>
                                <?php if ($range = month_range($seed['sowing_greenhouse_from'], $seed['sowing_greenhouse_to'])): ?><div><dt>Skleník</dt><dd><?= e($range) ?></dd></div><?php endif; ?>
                                <?php if ($range = month_range($seed['sowing_soil_from'], $seed['sowing_soil_to'])): ?><div><dt>Do půdy</dt><dd><?= e($range) ?></dd></div><?php endif; ?>
                                <?php if ($seed['sowing_depth']): ?><div><dt>Hloubka</dt><dd><?= e($seed['sowing_depth']) ?></dd></div><?php endif; ?>
                                <?php if ($range = month_range($seed['transplant_from'], $seed['transplant_to'])): ?><div><dt>Přesadit</dt><dd><?= e($range) ?></dd></div><?php endif; ?>
                                <?php if ($seed['spacing']): ?><div><dt>Rozestupy</dt><dd><?= e($seed['spacing']) ?></dd></div><?php endif; ?>
                                <?php if ($seed['harvest_period']): ?><div><dt>Sklizeň</dt><dd><?= e($seed['harvest_period']) ?></dd></div><?php endif; ?>
                            </dl>
                            <?php if ($seed['notes']): ?><p class="notes"><?= nl2br(e($seed['notes'])) ?></p><?php endif; ?>
                            <div class="card-actions">
                                <a class="text-button" href="?edit=<?= (int) $seed['id'] ?>#seed-form">Upravit</a>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="set_archive">
                                    <input type="hidden" name="seed_id" value="<?= (int) $seed['id'] ?>">
                                    <input type="hidden" name="archive" value="<?= $seed['is_archived'] ? '0' : '1' ?>">
                                    <button class="text-button"><?= $seed['is_archived'] ? 'Obnovit' : 'Archivovat' ?></button>
                                </form>
                                <form method="post" data-confirm="Opravdu chceš tento záznam trvale smazat?">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_seed">
                                    <input type="hidden" name="seed_id" value="<?= (int) $seed['id'] ?>">
                                    <button class="text-button danger">Smazat</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <aside class="form-panel" id="seed-form">
            <div class="section-heading">
                <div><p class="eyebrow"><?= $edit ? 'Úprava' : 'Nový sáček' ?></p><h2><?= $edit ? 'Upravit kartu' : 'Přidat kartu sáčku' ?></h2></div>
                <?php if ($edit): ?><a class="text-button" href="?">Zrušit</a><?php endif; ?>
            </div>
            <form method="post" class="stack-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_seed">
                <input type="hidden" name="seed_id" value="<?= (int) $form['id'] ?>">
                <label>Hlavní kategorie
                    <select id="seed-category" name="category_id" required data-category-select data-vegetable-category-id="<?= (int) $vegetableCategoryId ?>">
                        <option value="">Vyber kategorii</option>
                        <?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) $form['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="<?= $formIsVegetable ? '' : 'is-hidden' ?>" data-vegetable-subcategory>Podkategorie zeleniny
                    <select name="subcategory" <?= $formIsVegetable ? '' : 'disabled' ?>>
                        <option value="">Vyber druh zeleniny</option>
                        <?php foreach (VEGETABLE_SUBCATEGORIES as $subcategory): ?><option value="<?= e($subcategory) ?>" <?= $form['subcategory'] === $subcategory ? 'selected' : '' ?>><?= e($subcategory) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Název / odrůda<input name="product_name" required maxlength="160" value="<?= e($form['product_name']) ?>" placeholder="Např. Rajče Tornádo F1"></label>
                <label>Výrobce nebo dodavatel<input name="supplier" maxlength="160" value="<?= e($form['supplier']) ?>" placeholder="Např. Semo"></label>
                <label>EAN kód
                    <span class="ean-row">
                        <input id="ean" name="ean" inputmode="numeric" maxlength="32" value="<?= e($form['ean']) ?>" placeholder="Číslo pod čárovým kódem">
                        <button type="button" class="button secondary" data-ean-scan>Naskenovat</button>
                        <button type="button" class="button secondary" data-ean-search>Hledat online</button>
                    </span>
                    <small class="help">Skenování funguje v podporovaném Android prohlížeči; kód lze vždy napsat ručně.</small>
                </label>
                <div class="two-columns">
                    <label>Množství<input name="amount_value" type="number" min="0" step="0.01" value="<?= e((string) $form['amount_value']) ?>" placeholder="50"></label>
                    <label>Jednotka<select name="amount_unit"><option value="ks" <?= $form['amount_unit'] === 'ks' ? 'selected' : '' ?>>kusy</option><option value="g" <?= $form['amount_unit'] === 'g' ? 'selected' : '' ?>>gramy</option></select></label>
                </div>
                <div class="two-columns">
                    <label>Datum balení<input name="packaged_on" type="month" value="<?= e(form_month_date($form['packaged_on'])) ?>"></label>
                    <label>Datum expirace<input name="expires_on" type="month" value="<?= e(form_month_date($form['expires_on'])) ?>"></label>
                </div>
                <label>Fotka sáčku<input name="image" type="file" accept="image/jpeg,image/png,image/webp"><?php if ($form['image_path']): ?><span class="help">Nová fotka nahradí uloženou.</span><?php endif; ?></label>
                <hr>
                <p class="eyebrow">Pěstební plán</p>
                <fieldset class="sowing-option" data-sowing-option>
                    <legend><label class="checkbox-label"><input type="checkbox" name="sowing_greenhouse_enabled" value="1" data-sowing-toggle="greenhouse" <?= $formHasGreenhouse ? 'checked' : '' ?>> Předpěstování ve skleníku</label></legend>
                    <div class="two-columns <?= $formHasGreenhouse ? '' : 'is-hidden' ?>" data-sowing-fields="greenhouse">
                        <label>Od<select name="sowing_greenhouse_from" <?= $formHasGreenhouse ? '' : 'disabled' ?>><option value="">Vyber měsíc</option><?php foreach (czech_months() as $number => $month): ?><option value="<?= $number ?>" <?= (int) $form['sowing_greenhouse_from'] === $number ? 'selected' : '' ?>><?= e($month) ?></option><?php endforeach; ?></select></label>
                        <label>Do<select name="sowing_greenhouse_to" <?= $formHasGreenhouse ? '' : 'disabled' ?>><option value="">Stejný měsíc</option><?php foreach (czech_months() as $number => $month): ?><option value="<?= $number ?>" <?= (int) $form['sowing_greenhouse_to'] === $number ? 'selected' : '' ?>><?= e($month) ?></option><?php endforeach; ?></select></label>
                    </div>
                </fieldset>
                <fieldset class="sowing-option" data-sowing-option>
                    <legend><label class="checkbox-label"><input type="checkbox" name="sowing_soil_enabled" value="1" data-sowing-toggle="soil" <?= $formHasSoil ? 'checked' : '' ?>> Výsev rovnou do půdy</label></legend>
                    <div class="two-columns <?= $formHasSoil ? '' : 'is-hidden' ?>" data-sowing-fields="soil">
                        <label>Od<select name="sowing_soil_from" <?= $formHasSoil ? '' : 'disabled' ?>><option value="">Vyber měsíc</option><?php foreach (czech_months() as $number => $month): ?><option value="<?= $number ?>" <?= (int) $form['sowing_soil_from'] === $number ? 'selected' : '' ?>><?= e($month) ?></option><?php endforeach; ?></select></label>
                        <label>Do<select name="sowing_soil_to" <?= $formHasSoil ? '' : 'disabled' ?>><option value="">Stejný měsíc</option><?php foreach (czech_months() as $number => $month): ?><option value="<?= $number ?>" <?= (int) $form['sowing_soil_to'] === $number ? 'selected' : '' ?>><?= e($month) ?></option><?php endforeach; ?></select></label>
                    </div>
                </fieldset>
                <div class="two-columns">
                    <label>Hloubka výsevu<input name="sowing_depth" maxlength="80" value="<?= e($form['sowing_depth']) ?>" placeholder="např. 0,5–1 cm"></label>
                    <label>Rozestupy<input name="spacing" maxlength="100" value="<?= e($form['spacing']) ?>" placeholder="např. 50 × 50 cm"></label>
                </div>
                <div class="two-columns">
                    <label>Přesadit od<select name="transplant_from"><option value="">Nevyplněno</option><?php foreach (czech_months() as $number => $month): ?><option value="<?= $number ?>" <?= (int) $form['transplant_from'] === $number ? 'selected' : '' ?>><?= e($month) ?></option><?php endforeach; ?></select></label>
                    <label>Přesadit do<select name="transplant_to"><option value="">Stejný měsíc</option><?php foreach (czech_months() as $number => $month): ?><option value="<?= $number ?>" <?= (int) $form['transplant_to'] === $number ? 'selected' : '' ?>><?= e($month) ?></option><?php endforeach; ?></select></label>
                </div>
                <label>Sklizeň přibližně<input name="harvest_period" maxlength="120" value="<?= e($form['harvest_period']) ?>" placeholder="např. červenec–září"></label>
                <label>Popis / vlastní zkušenost<textarea name="notes" rows="3" maxlength="3000" placeholder="Chuť, barva, odkud je, doporučení…"><?= e($form['notes']) ?></textarea></label>
                <button class="button primary"><?= $edit ? 'Uložit změny' : 'Přidat do evidence' ?></button>
            </form>
        </aside>
    </section>
</main>
<script src="assets/app.js" defer></script>
</body>
</html>
