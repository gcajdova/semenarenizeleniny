<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
start_session();

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
if (current_user() === null && ($_GET['page'] ?? '') !== 'login') redirect('?page=login');
if (current_user() === null): $flashes = consume_flashes(); ?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#315d3a"><title>Přihlášení · Semínka</title><link rel="stylesheet" href="assets/app.css"></head><body class="login-body"><main class="login-card"><div class="brand-mark">✿</div><p class="eyebrow">Moje zahrada</p><h1>Semínka</h1><p class="intro">Přehled sáčků, výsevů a použitelnosti.</p><?php foreach ($flashes as $flash): ?><p class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p><?php endforeach; ?><form method="post" class="stack-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="login"><label>Uživatelské jméno<input name="username" required autocomplete="username" autofocus></label><label>Heslo<input type="password" name="password" required autocomplete="current-password"></label><button class="button primary">Přihlásit se</button></form></main></body></html>
<?php exit; endif;

$user = require_login();
function seed_text(string $key, int $max): ?string { $value = trim((string) ($_POST[$key] ?? '')); if (mb_strlen($value) > $max) throw new InvalidArgumentException('Jedno z polí je příliš dlouhé.'); return $value === '' ? null : $value; }
function safe_image_path(?string $path): ?string { return $path && str_starts_with($path, 'uploads/seeds/') ? APP_ROOT . '/public/' . $path : null; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    verify_csrf();
    try {
        if ($action === 'save_seed') {
            $seedId = filter_var($_POST['seed_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
            $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $name = seed_text('product_name', 160);
            $amount = ($_POST['amount_value'] ?? '') === '' ? null : filter_var($_POST['amount_value'], FILTER_VALIDATE_FLOAT);
            $unit = (string) ($_POST['amount_unit'] ?? '');
            $method = (string) ($_POST['sowing_method'] ?? '');
            if (!$categoryId || !$name) throw new InvalidArgumentException('Vyber kategorii a napiš název semínka.');
            $category = db()->prepare('SELECT COUNT(*) FROM categories WHERE id = ?'); $category->execute([$categoryId]);
            if (!(int) $category->fetchColumn()) throw new InvalidArgumentException('Vyber platnou hlavní kategorii.');
            if ($amount !== null && ($amount === false || $amount < 0 || $amount > 100000)) throw new InvalidArgumentException('Množství není platné.');
            if ($amount !== null && !in_array($unit, ['g', 'ks'], true)) throw new InvalidArgumentException('Vyber jednotku gramů nebo kusů.');
            if ($method !== '' && !in_array($method, ['glasshouse', 'soil'], true)) throw new InvalidArgumentException('Místo výsevu není platné.');
            $packagedOn = ($_POST['packaged_on'] ?? '') ?: null;
            $expiresOn = ($_POST['expires_on'] ?? '') ?: null;
            $sowingFrom = ($_POST['sowing_from'] ?? '') ?: null;
            foreach ([$packagedOn, $expiresOn, $sowingFrom] as $date) if ($date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new InvalidArgumentException('Jedno z dat nemá správný formát.');
            $values = ['category_id'=>$categoryId,'product_name'=>$name,'supplier'=>seed_text('supplier',160),'ean'=>seed_text('ean',32),'amount_value'=>$amount,'amount_unit'=>$amount === null ? null : $unit,'packaged_on'=>$packagedOn,'expires_on'=>$expiresOn,'sowing_method'=>$method ?: null,'sowing_from'=>$sowingFrom,'spacing'=>seed_text('spacing',100),'harvest_period'=>seed_text('harvest_period',120),'notes'=>seed_text('notes',3000)];
            $oldImage = null;
            if ($seedId) {
                $old = db()->prepare('SELECT image_path FROM seeds WHERE id = ?'); $old->execute([$seedId]); $oldImage = $old->fetchColumn();
                if ($oldImage === false) throw new InvalidArgumentException('Záznam už neexistuje.');
                $values['id'] = $seedId;
                db()->prepare('UPDATE seeds SET category_id=:category_id, product_name=:product_name, supplier=:supplier, ean=:ean, amount_value=:amount_value, amount_unit=:amount_unit, packaged_on=:packaged_on, expires_on=:expires_on, sowing_method=:sowing_method, sowing_from=:sowing_from, spacing=:spacing, harvest_period=:harvest_period, notes=:notes WHERE id=:id')->execute($values);
            } else {
                $values['created_by'] = $user['id'];
                db()->prepare('INSERT INTO seeds (category_id, product_name, supplier, ean, amount_value, amount_unit, packaged_on, expires_on, sowing_method, sowing_from, spacing, harvest_period, notes, created_by) VALUES (:category_id,:product_name,:supplier,:ean,:amount_value,:amount_unit,:packaged_on,:expires_on,:sowing_method,:sowing_from,:spacing,:harvest_period,:notes,:created_by)')->execute($values);
                $seedId = (int) db()->lastInsertId();
            }
            $upload = $_FILES['image'] ?? null;
            if (is_array($upload) && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > (int) env('UPLOAD_MAX_BYTES', '5242880')) throw new InvalidArgumentException('Fotku se nepodařilo nahrát nebo je větší než 5 MB.');
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if (!isset($extensions[$mime])) throw new InvalidArgumentException('Nahraj prosím fotografii JPG, PNG nebo WebP.');
                $relative = 'uploads/seeds/seed-' . $seedId . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
                $target = APP_ROOT . '/public/' . $relative;
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0750, true);
                if (!move_uploaded_file($upload['tmp_name'], $target)) throw new InvalidArgumentException('Fotku se nepodařilo uložit.');
                db()->prepare('UPDATE seeds SET image_path = ? WHERE id = ?')->execute([$relative, $seedId]);
                $oldFile = safe_image_path(is_string($oldImage) ? $oldImage : null); if ($oldFile && is_file($oldFile)) unlink($oldFile);
            }
            flash('success', 'Karta sáčku byla uložena.');
        } elseif ($action === 'set_archive') {
            $id = filter_var($_POST['seed_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]); if (!$id) throw new InvalidArgumentException('Záznam se nepodařilo určit.');
            $archived = (int) ($_POST['archive'] ?? 1) === 1 ? 1 : 0; db()->prepare('UPDATE seeds SET is_archived = ? WHERE id = ?')->execute([$archived,$id]); flash('success',$archived?'Sáček byl přesunut do archivu.':'Sáček byl obnoven.');
        } elseif ($action === 'delete_seed') {
            $id = filter_var($_POST['seed_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]); if (!$id) throw new InvalidArgumentException('Záznam se nepodařilo určit.');
            $get = db()->prepare('SELECT image_path FROM seeds WHERE id = ?'); $get->execute([$id]); $path = safe_image_path($get->fetchColumn() ?: null); db()->prepare('DELETE FROM seeds WHERE id = ?')->execute([$id]); if ($path && is_file($path)) unlink($path); flash('success','Sáček byl smazán.');
        }
    } catch (Throwable $error) { flash('error',$error instanceof InvalidArgumentException ? $error->getMessage() : 'Změnu se nepodařilo uložit.'); }
    redirect('?');
}

$categories = db()->query("SELECT id, name FROM categories WHERE name IN ('Zelenina', 'Bylinky', 'Květiny', 'Ovoce') ORDER BY FIELD(name, 'Zelenina', 'Bylinky', 'Květiny', 'Ovoce')")->fetchAll();
$editId = filter_var($_GET['edit'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$edit = null; if ($editId) { $s=db()->prepare('SELECT * FROM seeds WHERE id=?');$s->execute([$editId]);$edit=$s->fetch() ?: null; }
$categoryId=filter_var($_GET['category'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null; $q=trim((string)($_GET['q']??'')); $status=(string)($_GET['status']??'active'); if(!in_array($status,['active','renew','archived'],true))$status='active';
$days=max(1,min(365,(int)env('EXPIRY_ALERT_DAYS','90'))); $limit=(new DateTimeImmutable('today'))->modify('+' . $days . ' days')->format('Y-m-d');
$conditions=[$status==='archived'?'s.is_archived=1':'s.is_archived=0'];$params=[]; if($categoryId){$conditions[]='s.category_id=:category';$params['category']=$categoryId;}if($q!==''){$conditions[]='(s.product_name LIKE :q OR s.supplier LIKE :q OR s.ean LIKE :q)';$params['q']='%'.$q.'%';}if($status==='renew'){$conditions[]='s.expires_on IS NOT NULL AND s.expires_on <= :limit';$params['limit']=$limit;}
$list=db()->prepare('SELECT s.*,c.name category_name FROM seeds s JOIN categories c ON c.id=s.category_id WHERE '.implode(' AND ',$conditions).' ORDER BY s.expires_on IS NULL,s.expires_on,s.product_name');$list->execute($params);$seeds=$list->fetchAll();
$stats=db()->prepare('SELECT COUNT(*) cards,COALESCE(SUM(image_path IS NOT NULL),0) photos,COALESCE(SUM(expires_on IS NOT NULL AND expires_on <= :limit),0) renew FROM seeds WHERE is_archived=0');$stats->execute(['limit'=>$limit]);$stats=$stats->fetch();
$renew=db()->prepare('SELECT id,product_name,expires_on FROM seeds WHERE is_archived=0 AND expires_on IS NOT NULL AND expires_on <= ? ORDER BY expires_on LIMIT 8');$renew->execute([$limit]);$renew=$renew->fetchAll();$form=$edit??['id'=>'','category_id'=>'','product_name'=>'','supplier'=>'','ean'=>'','amount_value'=>'','amount_unit'=>'ks','packaged_on'=>'','expires_on'=>'','image_path'=>'','sowing_method'=>'','sowing_from'=>'','spacing'=>'','harvest_period'=>'','notes'=>''];$flashes=consume_flashes();
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#315d3a"><link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="assets/icon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/details.css"><title>Semínka · Moje zahrada</title></head><body>
<header class="topbar"><a class="brand" href="?"><span>✿</span> Semínka</a><div class="user-menu"><span><?=e($user['username'])?></span><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="logout"><button class="text-button">Odhlásit</button></form></div></header><main class="app-shell">
<section class="hero"><div><p class="eyebrow">Moje zahrada</p><h1>Karty sáčků semínek</h1><p>Od balení přes výsev až po sklizeň — včetně hlídání čerstvosti.</p></div><a class="button primary" href="#seed-form">+ Přidat sáček</a></section>
<?php foreach($flashes as $flash):?><p class="flash <?=e($flash['type'])?>"><?=e($flash['message'])?></p><?php endforeach;?>
<section class="stats"><article><span>Sáčků v evidenci</span><strong><?= (int)$stats['cards']?></strong></article><article><span>Vyfocených sáčků</span><strong><?= (int)$stats['photos']?></strong></article><article><span>Čerstvě koupit</span><strong><?= (int)$stats['renew']?></strong><small>do <?=e((new DateTimeImmutable($limit))->format('j. n. Y'))?></small></article></section>
<?php if($renew):?><section class="renewal"><div><p class="eyebrow">Nákup čerstvých semínek</p><h2>Blížící se expirace</h2><p>Tyto sáčky končí nejpozději v následujících <?= $days ?> dnech.</p></div><ul><?php foreach($renew as $row):?><li><a href="?edit=<?=(int)$row['id']?>#seed-form"><?=e($row['product_name'])?></a><span><?=e((new DateTimeImmutable($row['expires_on']))->format('j. n. Y'))?></span></li><?php endforeach;?></ul><a class="button secondary" href="?status=renew">Zobrazit celý seznam</a></section><?php endif;?>
<section class="workspace"><div class="inventory-panel"><div class="section-heading"><div><p class="eyebrow">Zásoba</p><h2>Moje semínka</h2></div><span class="record-count"><?=count($seeds)?> záznamů</span></div><form class="filters" method="get"><input name="q" value="<?=e($q)?>" placeholder="Hledat název, dodavatele, EAN…"><select name="category"><option value="">Všechny kategorie</option><?php foreach($categories as $category):?><option value="<?= (int)$category['id']?>" <?=$categoryId===(int)$category['id']?'selected':''?>><?=e($category['name'])?></option><?php endforeach;?></select><select name="status"><option value="active" <?=$status==='active'?'selected':''?>>Aktivní</option><option value="renew" <?=$status==='renew'?'selected':''?>>K nákupu</option><option value="archived" <?=$status==='archived'?'selected':''?>>Archiv</option></select><button class="button secondary">Filtrovat</button></form>
<?php if(!$seeds):?><div class="empty"><div>🌱</div><h3>Zatím tu nic není</h3><p>Přidej první sáček a jeho pěstební údaje.</p></div><?php else:?><div class="seed-list"><?php foreach($seeds as $seed):?><article class="seed-card detailed-card"><?php if($seed['image_path']):?><img class="seed-photo" src="<?=e($seed['image_path'])?>" alt="Sáček <?=e($seed['product_name'])?>"><?php endif;?><div class="seed-card-head"><span class="category-pill"><?=e($seed['category_name'])?></span><?php if($seed['expires_on']):?><span class="packages <?=$seed['expires_on']<=$limit?'attention':''?>">do <?=e((new DateTimeImmutable($seed['expires_on']))->format('j. n. Y'))?></span><?php endif;?></div><h3><?=e($seed['product_name'])?></h3><p class="producer"><?=e($seed['supplier']?:'Dodavatel neuveden')?><?=$seed['ean']?' · EAN '.$seed['ean']:''?></p><dl class="seed-meta"><?php if($seed['amount_value']!==null):?><div><dt>Balení</dt><dd><?=e(rtrim(rtrim((string)$seed['amount_value'],'0'),'.'))?> <?=e($seed['amount_unit'])?></dd></div><?php endif;?><?php if($seed['sowing_method']):?><div><dt>Výsev</dt><dd><?= $seed['sowing_method']==='glasshouse'?'do skleníku':'rovnou do půdy'?></dd></div><?php endif;?><?php if($seed['sowing_from']):?><div><dt>Od</dt><dd><?=e((new DateTimeImmutable($seed['sowing_from']))->format('j. n. Y'))?></dd></div><?php endif;?><?php if($seed['spacing']):?><div><dt>Rozestupy</dt><dd><?=e($seed['spacing'])?></dd></div><?php endif;?><?php if($seed['harvest_period']):?><div><dt>Sklizeň</dt><dd><?=e($seed['harvest_period'])?></dd></div><?php endif;?></dl><?php if($seed['notes']):?><p class="notes"><?=nl2br(e($seed['notes']))?></p><?php endif;?><div class="card-actions"><a class="text-button" href="?edit=<?=(int)$seed['id']?>#seed-form">Upravit</a><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="set_archive"><input type="hidden" name="seed_id" value="<?= (int)$seed['id']?>"><input type="hidden" name="archive" value="<?=$seed['is_archived']?'0':'1'?>"><button class="text-button"><?=$seed['is_archived']?'Obnovit':'Archivovat'?></button></form><form method="post" data-confirm="Opravdu chceš tento záznam trvale smazat?"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_seed"><input type="hidden" name="seed_id" value="<?= (int)$seed['id']?>"><button class="text-button danger">Smazat</button></form></div></article><?php endforeach;?></div><?php endif;?></div>
<aside class="form-panel" id="seed-form"><div class="section-heading"><div><p class="eyebrow"><?=$edit?'Úprava':'Nový sáček'?></p><h2><?=$edit?'Upravit kartu':'Přidat kartu sáčku'?></h2></div><?php if($edit):?><a class="text-button" href="?">Zrušit</a><?php endif;?></div><form method="post" class="stack-form" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_seed"><input type="hidden" name="seed_id" value="<?= (int)$form['id']?>"><label>Hlavní kategorie<select name="category_id" required><option value="">Vyber kategorii</option><?php foreach($categories as $category):?><option value="<?= (int)$category['id']?>" <?=((int)$form['category_id']===(int)$category['id'])?'selected':''?>><?=e($category['name'])?></option><?php endforeach;?></select></label><label>Název / odrůda<input name="product_name" required maxlength="160" value="<?=e($form['product_name'])?>" placeholder="Např. Rajče Tornádo F1"></label><label>Výrobce nebo dodavatel<input name="supplier" maxlength="160" value="<?=e($form['supplier'])?>" placeholder="Např. Semo"></label><label>EAN kód<span class="ean-row"><input id="ean" name="ean" inputmode="numeric" maxlength="32" value="<?=e($form['ean'])?>" placeholder="Číslo pod čárovým kódem"><button type="button" class="button secondary" data-ean-scan>Naskenovat</button><button type="button" class="button secondary" data-ean-search>Hledat online</button></span><small class="help">Skenování funguje v podporovaném Android prohlížeči; kód lze vždy napsat ručně.</small></label><div class="two-columns"><label>Množství<input name="amount_value" type="number" min="0" step="0.01" value="<?=e((string)$form['amount_value'])?>" placeholder="50"></label><label>Jednotka<select name="amount_unit"><option value="ks" <?=$form['amount_unit']==='ks'?'selected':''?>>kusy</option><option value="g" <?=$form['amount_unit']==='g'?'selected':''?>>gramy</option></select></label></div><div class="two-columns"><label>Datum balení<input name="packaged_on" type="date" value="<?=e((string)$form['packaged_on'])?>"></label><label>Datum expirace<input name="expires_on" type="date" value="<?=e((string)$form['expires_on'])?>"></label></div><label>Fotka sáčku<input name="image" type="file" accept="image/jpeg,image/png,image/webp"><?php if($form['image_path']):?><span class="help">Nová fotka nahradí uloženou.</span><?php endif;?></label><hr><p class="eyebrow">Pěstební plán</p><div class="two-columns"><label>Výsev<select name="sowing_method"><option value="">Nevyplněno</option><option value="glasshouse" <?=$form['sowing_method']==='glasshouse'?'selected':''?>>Předpěstovat ve skleníku</option><option value="soil" <?=$form['sowing_method']==='soil'?'selected':''?>>Rovnou do půdy</option></select></label><label>Začít výsev<input name="sowing_from" type="date" value="<?=e((string)$form['sowing_from'])?>"></label></div><div class="two-columns"><label>Rozestupy<input name="spacing" maxlength="100" value="<?=e($form['spacing'])?>" placeholder="např. 50 × 50 cm"></label><label>Sklizeň přibližně<input name="harvest_period" maxlength="120" value="<?=e($form['harvest_period'])?>" placeholder="např. červenec–září"></label></div><label>Popis / vlastní zkušenost<textarea name="notes" rows="3" maxlength="3000" placeholder="Chuť, barva, odkud je, doporučení…"><?=e($form['notes'])?></textarea></label><button class="button primary"><?=$edit?'Uložit změny':'Přidat do evidence'?></button></form></aside></section></main><script src="assets/app.js" defer></script></body></html>
