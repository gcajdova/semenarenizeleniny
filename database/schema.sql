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
    product_name VARCHAR(160) NOT NULL,
    supplier VARCHAR(160) NULL,
    ean VARCHAR(32) NULL,
    amount_value DECIMAL(10,2) NULL,
    amount_unit ENUM('g', 'ks') NULL,
    packaged_on DATE NULL,
    expires_on DATE NULL,
    image_path VARCHAR(255) NULL,
    sowing_method ENUM('glasshouse', 'soil') NULL,
    sowing_from DATE NULL,
    spacing VARCHAR(100) NULL,
    harvest_period VARCHAR(120) NULL,
    notes TEXT NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY seeds_category_idx (category_id),
    KEY seeds_expiry_idx (expires_on),
    KEY seeds_ean_idx (ean),
    KEY seeds_archived_idx (is_archived),
    CONSTRAINT seeds_category_fk FOREIGN KEY (category_id) REFERENCES categories (id),
    CONSTRAINT seeds_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name, color) VALUES
    ('Zelenina', '#5E9B64'),
    ('Bylinky', '#538D62'),
    ('Květiny', '#A66CB0'),
    ('Ovoce', '#D95F49')
ON DUPLICATE KEY UPDATE name = VALUES(name);
