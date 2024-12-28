CREATE TABLE `order_details` (
	`order_id` INT UNSIGNED NOT NULL,
	`product_id` INT NOT NULL,
	`parent_product_id` INT NULL,
	`qty` TINYINT UNSIGNED NOT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	`deleted_at` DATETIME NULL
);