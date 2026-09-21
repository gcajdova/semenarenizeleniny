# Semínka

Mobilně přívětivá evidence sáčků semínek. Každá karta patří do jedné ze čtyř hlavních kategorií: **zelenina, bylinky, květiny nebo ovoce**. Zelenina má navíc výběr podkategorie. Karta obsahuje název/odrůdu, výrobce či dodavatele, EAN, množství v gramech nebo kusech, datum balení a expirace po měsících, fotku sáčku i pěstební plán.

Aplikace je postavená na **PHP 8.2+ a MariaDB**. Je instalovatelná jako PWA, takže se z mobilního prohlížeče přidá na plochu Androidu a běží jako samostatná aplikace.

## Funkce první verze

- přihlášení jedním nebo více uživateli,
- čtyři barevné hlavní kategorie: zelenina, ovoce, bylinky a květiny,
- 17 rozbalovacích podkategorií zeleniny včetně samostatného filtrování,
- nahrání fotografie konkrétního sáčku, EAN a poznámky k výrobku,
- plán výsevu po měsících — samostatně do skleníku, do půdy, nebo obě varianty zároveň,
- rozsah měsíců výsevu i přesazování, hloubka výsevu, rozestupy a období sklizně,
- seznam sáčků k obnově při blížící se nebo proběhlé expiraci,
- vyhledávání, filtrování, archivace a smazání záznamu,
- responzivní rozhraní, tmavý režim a PWA základ pro Android.

## Instalace na serveru

Požadavky: Debian 12/13, Apache 2.4, PHP 8.2+, MariaDB a certbot. Všechen veřejný provoz směřuje výhradně do `public/`; `.env`, databáze a CLI nástroje se z webu neotevřou.

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server \
  php libapache2-mod-php php-cli php-mysql php-mbstring \
  certbot python3-certbot-apache
sudo a2enmod rewrite headers
```

### 1. Aplikace

```bash
sudo git clone https://github.com/gcajdova/semenarenizeleniny.git /var/www/seminka
cd /var/www/seminka
sudo cp .env.example .env
sudo nano .env
```

Do `.env` nastav skutečnou doménu a silné databázové heslo. Příklad názvu databáze je `seed_inventory`; můžeš jej změnit, ale stejné údaje musí být v MariaDB i `.env`.

```bash
sudo mariadb
```

```sql
CREATE DATABASE seed_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'seed_inventory'@'localhost' IDENTIFIED BY 'VELMI_SILNE_HESLO';
GRANT ALL PRIVILEGES ON seed_inventory.* TO 'seed_inventory'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
sudo mariadb seed_inventory < database/schema.sql
sudo chown -R root:www-data /var/www/seminka
sudo find /var/www/seminka -type d -exec chmod 750 {} \;
sudo find /var/www/seminka -type f -exec chmod 640 {} \;
sudo chmod 750 /var/www/seminka/bin/*.php
sudo chown -R www-data:www-data /var/www/seminka/public/uploads/seeds
```

### 2. První přihlášení

Příkaz se zeptá na uživatelské jméno a heslo. Heslo se do databáze ukládá jen jako bezpečný hash.

```bash
cd /var/www/seminka
sudo -u www-data php bin/create-user.php gabriela
sudo -u www-data php bin/self-check.php
```

### 3. Apache a HTTPS

```bash
sudo cp /var/www/seminka/apache/seminka.conf /etc/apache2/sites-available/seminka.conf
sudo nano /etc/apache2/sites-available/seminka.conf
sudo a2ensite seminka.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
sudo certbot --apache -d seminka.example.cz
```

V Apache konfiguraci změň `seminka.example.cz` na skutečnou doménu. Po HTTPS ponech v `.env` `SESSION_SECURE_COOKIE=1`.

## Aktualizace a záloha

Při aktualizaci nezapisuj do Gitu soubor `.env` a vždy nejdřív zazálohuj databázi:

```bash
sudo mariadb-dump seed_inventory > ~/seed-inventory-backup-$(date +%F).sql
cd /var/www/seminka
sudo git pull --ff-only
sudo chown -R root:www-data /var/www/seminka
sudo -u www-data php bin/self-check.php
sudo apache2ctl configtest
sudo systemctl reload apache2
```

PWA se na Androidu nainstaluje v Chrome přes nabídku **Nainstalovat aplikaci** nebo **Přidat na plochu**. Při pozdějším vytváření APK/AAB může Android obal používat stejnou webovou aplikaci i databázi.

## Expirace, fotografie a EAN

Výchozí upozornění na obnovu semínek se ukáže **90 dní** před datem expirace. Počet dní lze upravit v `.env` položkou `EXPIRY_ALERT_DAYS`.

Fotografie jsou omezené na JPG, PNG nebo WebP do 5 MB a ukládají se do `public/uploads/seeds/`. Tento adresář musí patřit uživateli `www-data`, jak je uvedeno výše.

EAN lze napsat ručně nebo na podporovaném Androidu načíst kamerou. Tlačítko **Hledat online** otevře vyhledání kódu na webu. Kód se bezpečně uloží ke kartě sáčku; automatické doplnění parametrů výrobku není součástí aplikace, protože pro osiva není spolehlivá jednotná veřejná databáze.

## Přechod na výsevní kalendář a podkategorie

Pokud už běží současná verze s detailními kartami, před aktualizací zazálohuj databázi a po `git pull` spusť jednou novou migraci:

```bash
sudo mariadb-dump seed_inventory > ~/seed-inventory-before-calendar.sql
cd /var/www/seminka
sudo mariadb seed_inventory < database/migrations/002_seed_calendar_and_subcategories.sql
sudo -u www-data php bin/self-check.php
```

Dosavadní jediný termín výsevu se automaticky převede do skleníku nebo do půdy podle toho, co bylo u sáčku zadané. Datum balení a expirace se nově zadává i zobrazuje jen jako měsíc a rok; pro upozornění se expirace počítá až k poslednímu dni zadaného měsíce.

## Přechod z původní skladové verze

Pokud už máš nainstalovanou původní skladovou verzi, před aktualizací zazálohuj databázi a po `git pull` spusť jednou tuto migraci:

```bash
sudo mariadb-dump seed_inventory > ~/seed-inventory-before-detailed-cards.sql
cd /var/www/seminka
sudo mariadb seed_inventory < database/migrations/001_detailed_seed_cards.sql
sudo mariadb seed_inventory < database/migrations/002_seed_calendar_and_subcategories.sql
sudo chown -R www-data:www-data public/uploads/seeds
sudo -u www-data php bin/self-check.php
```
