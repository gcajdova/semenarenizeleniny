-- Spusť po 001_detailed_seed_cards.sql při přechodu na výsevní kalendář.
-- Datum balení a expirace zůstávají uložené jako DATE, ale aplikace je nově
-- zadává a zobrazuje pouze jako měsíc a rok.
ALTER TABLE seeds
    ADD COLUMN subcategory VARCHAR(100) NULL AFTER category_id,
    ADD COLUMN sowing_greenhouse_from TINYINT UNSIGNED NULL AFTER image_path,
    ADD COLUMN sowing_greenhouse_to TINYINT UNSIGNED NULL AFTER sowing_greenhouse_from,
    ADD COLUMN sowing_soil_from TINYINT UNSIGNED NULL AFTER sowing_greenhouse_to,
    ADD COLUMN sowing_soil_to TINYINT UNSIGNED NULL AFTER sowing_soil_from,
    ADD COLUMN sowing_depth VARCHAR(80) NULL AFTER sowing_soil_to,
    ADD COLUMN transplant_from TINYINT UNSIGNED NULL AFTER sowing_depth,
    ADD COLUMN transplant_to TINYINT UNSIGNED NULL AFTER transplant_from,
    ADD KEY seeds_subcategory_idx (subcategory);

-- Přenese dřívější jediný termín výsevu do správné nové varianty.
UPDATE seeds
SET
    sowing_greenhouse_from = CASE WHEN sowing_method = 'glasshouse' AND sowing_from IS NOT NULL THEN MONTH(sowing_from) END,
    sowing_soil_from = CASE WHEN sowing_method = 'soil' AND sowing_from IS NOT NULL THEN MONTH(sowing_from) END
WHERE sowing_from IS NOT NULL;
