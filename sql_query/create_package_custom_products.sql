CREATE TABLE `package_custom_products` (
	`package_id` INT UNSIGNED NOT NULL,
	`product_id` INT UNSIGNED NOT NULL,
	`sale_price` DECIMAL(8, 2) NOT NULL,
	`created_at` DATETIME NOT NULL,
	`created_by` INT NULL,
	`updated_at` DATETIME NOT NULL,
	`updated_by` INT NULL,
	`deleted_at` DATETIME NULL,
	`deleted_by` INT NULL
);