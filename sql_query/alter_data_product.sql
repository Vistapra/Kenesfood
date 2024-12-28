/*
IF package_category_id is NULL then it must be a package.
*/
ALTER TABLE `data_product`
ADD package_category_id INT UNSIGNED NULL AFTER cat_id;