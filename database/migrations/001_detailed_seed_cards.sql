-- Spusť pouze při přechodu z první skladové verze aplikace.
-- Před spuštěním si vždy zazálohuj databázi.
ALTER TABLE seeds
    ADD COLUMN product_name VARCHAR(160) NULL AFTER category_id,
    ADD COLUMN supplier VARCHAR(160) NULL AFTER product_name,
    ADD COLUMN ean VARCHAR(32) NULL AFTER supplier,
    ADD COLUMN amount_value DECIMAL(10,2) NULL AFTER ean,
    ADD COLUMN amount_unit ENUM('g', 'ks') NULL AFTER amount_value,
    ADD COLUMN packaged_on DATE NULL AFTER amount_unit,
    ADD COLUMN expires_on DATE NULL AFTER packaged_on,
    ADD COLUMN image_path VARCHAR(255) NULL AFTER expires_on,
    ADD COLUMN sowing_method ENUM('glasshouse', 'soil') NULL AFTER image_path,
    ADD COLUMN sowing_from DATE NULL AFTER sowing_method,
    ADD COLUMN spacing VARCHAR(100) NULL AFTER sowing_from,
    ADD COLUMN harvest_period VARCHAR(120) NULL AFTER spacing;

UPDATE seeds
SET product_name = variety,
    supplier = producer,
    amount_value = package_count,
    amount_unit = 'ks',
    expires_on = CASE
        WHEN expiry_year IS NULL THEN NULL
        ELSE STR_TO_DATE(CONCAT(expiry_year, '-12-31'), '%Y-%m-%d')
    END
WHERE product_name IS NULL;

ALTER TABLE seeds
    MODIFY product_name VARCHAR(160) NOT NULL,
    ADD KEY seeds_ean_idx (ean),
    ADD KEY seeds_expires_on_idx (expires_on);

INSERT INTO categories (name, color) VALUES
    ('Zelenina', '#5E9B64'),
    ('Bylinky', '#538D62'),
    ('Květiny', '#A66CB0'),
    ('Ovoce', '#D95F49')
ON DUPLICATE KEY UPDATE color = VALUES(color);
