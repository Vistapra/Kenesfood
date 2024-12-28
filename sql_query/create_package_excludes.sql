CREATE TABLE `package_excludes` (
	`package_id` INT UNSIGNED NOT NULL,
	`product_id` INT UNSIGNED NOT NULL,
	`created_at` DATETIME NOT NULL,
	`created_by` INT NULL,
	`updated_at` DATETIME NOT NULL,
	`updated_by` INT NULL,
	`deleted_at` DATETIME NULL,
	`deleted_by` INT NULL
);