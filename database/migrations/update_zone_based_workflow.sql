-- Zone-based vendor application workflow migration
-- Run this on the existing street_vendor database before using the updated pages.
-- It preserves existing tables/data and adds only missing columns/indexes.

USE street_vendor;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
    IN table_name_in VARCHAR(64),
    IN column_name_in VARCHAR(64),
    IN ddl_in TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_in
          AND COLUMN_NAME = column_name_in
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', table_name_in, '` ADD COLUMN ', ddl_in);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$
CREATE PROCEDURE add_index_if_missing(
    IN table_name_in VARCHAR(64),
    IN index_name_in VARCHAR(64),
    IN ddl_in TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_in
          AND INDEX_NAME = index_name_in
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', table_name_in, '` ADD ', ddl_in);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL add_column_if_missing('zones', 'max_capacity', '`max_capacity` INT DEFAULT NULL');
CALL add_column_if_missing('zones', 'current_capacity', '`current_capacity` INT NOT NULL DEFAULT 0');
CALL add_column_if_missing('zones', 'description', '`description` TEXT NULL');
CALL add_column_if_missing('zones', 'geometry_json', '`geometry_json` LONGTEXT NULL');
CALL add_column_if_missing('zones', 'geometry', '`geometry` LONGTEXT NULL');
CALL add_column_if_missing('zones', 'status', "`status` ENUM('available','not_available') NOT NULL DEFAULT 'available'");

UPDATE zones
SET max_capacity = COALESCE(max_capacity, max_vendors, 10),
    description = COALESCE(description, area_description),
    geometry_json = COALESCE(geometry_json, geometry),
    geometry = COALESCE(geometry, geometry_json),
    status = CASE WHEN COALESCE(is_active, 1) = 1 THEN 'available' ELSE 'not_available' END
WHERE id IS NOT NULL;

UPDATE zones z
SET current_capacity = (
    SELECT COUNT(*)
    FROM locations l
    WHERE l.zone_id = z.id AND COALESCE(l.is_active, 1) = 1
);

CALL add_column_if_missing('licenses', 'zone_id', '`zone_id` INT DEFAULT NULL');
CALL add_column_if_missing('licenses', 'business_type', '`business_type` VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('licenses', 'vendor_category', '`vendor_category` VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('licenses', 'priority_type', '`priority_type` VARCHAR(100) DEFAULT NULL');
CALL add_column_if_missing('licenses', 'aadhar_path', '`aadhar_path` VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('licenses', 'photo_path', '`photo_path` VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('licenses', 'business_proof_path', '`business_proof_path` VARCHAR(255) DEFAULT NULL');
CALL add_column_if_missing('licenses', 'applied_at', '`applied_at` DATETIME DEFAULT NULL');
CALL add_column_if_missing('licenses', 'reviewed_at', '`reviewed_at` DATETIME DEFAULT NULL');
CALL add_index_if_missing('licenses', 'idx_licenses_zone_id', 'INDEX `idx_licenses_zone_id` (`zone_id`)');

UPDATE licenses
SET applied_at = COALESCE(applied_at, created_at, CONCAT(applied_date, ' 00:00:00')),
    business_type = COALESCE(business_type, license_type)
WHERE id IS NOT NULL;

CALL add_column_if_missing('locations', 'application_id', '`application_id` INT DEFAULT NULL');
CALL add_column_if_missing('locations', 'latitude', '`latitude` DECIMAL(10, 7) DEFAULT NULL');
CALL add_column_if_missing('locations', 'longitude', '`longitude` DECIMAL(10, 7) DEFAULT NULL');
CALL add_column_if_missing('locations', 'status', "`status` ENUM('active','inactive') NOT NULL DEFAULT 'active'");
CALL add_column_if_missing('locations', 'assigned_at', '`assigned_at` DATETIME DEFAULT NULL');
CALL add_index_if_missing('locations', 'idx_locations_application_id', 'INDEX `idx_locations_application_id` (`application_id`)');

UPDATE locations
SET status = CASE WHEN COALESCE(is_active, 1) = 1 THEN 'active' ELSE 'inactive' END,
    assigned_at = COALESCE(assigned_at, CONCAT(allocated_date, ' 00:00:00'), created_at)
WHERE id IS NOT NULL;

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;
