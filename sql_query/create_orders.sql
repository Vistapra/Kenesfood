CREATE TABLE `orders` (
	`id` INT PRIMARY KEY AUTO_INCREMENT,
	`outlet_id` INT NOT NULL,
	`table_id` TINYINT UNSIGNED NOT NULL,
	`brand` ENUM('kopitiam', 'bakery', 'resto'),
	`name` VARCHAR(255) NOT NULL,
	`passcode` CHAR(60) NOT NULL,
	`status` TINYINT UNSIGNED NOT NULL,
	`expire_at` DATETIME NOT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	`deleted_at` DATETIME NULL
);