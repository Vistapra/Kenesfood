/*
Categories digunakan untuk mengelompokan data product
untuk fungsional order pada product yang bersifat package.
Field sale_price berlaku untuk pemberian
harga pada product yang tidak diberikan rule harga tersendiri. 
*/
CREATE TABLE `package_categories` (
	`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	`parent_id` INT UNSIGNED NULL,
	`name` VARCHAR(255) NOT NULL,
	`sale_price` DECIMAL(8, 2) NULL,
	`created_at` DATETIME NOT NULL,
	`created_by` INT NULL,
	`updated_at` DATETIME NOT NULL,
	`updated_by` INT NULL,
	`deleted_at` DATETIME NULL,
	`deleted_by` INT NULL
);