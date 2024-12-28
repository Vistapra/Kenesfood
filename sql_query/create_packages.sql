/*
packages digunakan untuk membuat rule package pada fungsionalitas order.
*/
CREATE TABLE `packages` (
	`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	`product_id` INT UNSIGNED NOT NULL,
	`package_category_id` INT UNSIGNED NOT NULL,
	`quantity` TINYINT UNSIGNED NOT NULL,
	`created_at` DATETIME NOT NULL,
	`created_by` INT NULL,
	`updated_at` DATETIME NOT NULL,
	`updated_by` INT NULL,
	`deleted_at` DATETIME NULL,
	`deleted_by` INT NULL
);