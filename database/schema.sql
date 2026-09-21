CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_username_unique (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#5E8E3E',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY categories_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seeds (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    variety VARCHAR(160) NOT NULL,
    producer VARCHAR(160) NULL,
    package_count INT UNSIGNED NOT NULL DEFAULT 1,
    purchase_year SMALLINT UNSIGNED NULL,
    expiry_year SMALLINT UNSIGNED NULL,
    storage_location VARCHAR(120) NULL,
    notes TEXT NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY seeds_category_idx (category_id),
    KEY seeds_expiry_idx (expiry_year),
    KEY seeds_archived_idx (is_archived),
    CONSTRAINT seeds_category_fk FOREIGN KEY (category_id) REFERENCES categories (id),
    CONSTRAINT seeds_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name, color) VALUES
    ('Papriky', '#D95F49'),
    ('Chilli', '#B83A3A'),
    ('Rajčata', '#D55353'),
    ('Cherry rajčata', '#E77D6A'),
    ('Okurky', '#5E9B64'),
    ('Cukety a dýně', '#D99132'),
    ('Mrkev', '#E27B2E'),
    ('Saláty a listová zelenina', '#74A765'),
    ('Ředkvičky a ředkve', '#C85F7A'),
    ('Cibule a česnek', '#9A8154'),
    ('Fazole a hrách', '#648B52'),
    ('Košťáloviny', '#4D8E79'),
    ('Bylinky', '#538D62'),
    ('Květiny', '#A66CB0'),
    ('Jiné', '#707A84')
ON DUPLICATE KEY UPDATE name = VALUES(name);
