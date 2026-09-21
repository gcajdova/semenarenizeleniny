# Semínka

Jednoduchá, mobilně přívětivá evidence semínek zeleniny. Každý záznam patří do kategorie (např. papriky, okurky nebo cherry rajčata) a obsahuje odrůdu, výrobce, počet sáčků, rok nákupu, rok použitelnosti, umístění a poznámku.

Aplikace je postavená na **PHP 8.2+ a MariaDB**. Je instalovatelná jako PWA, takže se z mobilního prohlížeče přidá na plochu Androidu a běží jako samostatná aplikace.

## Funkce první verze

- přihlášení jedním nebo více uživateli,
- výchozí druhy zeleniny a možnost přidat vlastní kategorii,
- přidání, úprava, archivace a smazání záznamu,
- vyhledávání, filtrování podle druhu a zobrazení semínek po expiraci,
- souhrn počtu odrůd, sáčků a semínek k prověření,
- responzivní rozhraní a PWA základ pro Android.

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
