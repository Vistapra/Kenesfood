<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_packages extends CI_Model
{
	function __construct()
	{
		// Call the Model constructor
		parent::__construct();
	}

	// get total data
	public function get_total_data($keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
            dp.product_code LIKE '%" . $keyword . "%'
            OR dp.product_name LIKE '%" . $keyword . "%'
            OR dp.product_brand LIKE '%" . $keyword . "%'
            OR dp.product_desc LIKE '%" . $keyword . "%'
            OR dp.product_st LIKE '%" . $keyword . "%'
        )";
		}
		$sql = "SELECT COUNT(*)'total' FROM packages p
            INNER JOIN data_product dp ON p.product_id = dp.product_id
            WHERE p.deleted_at IS NULL
            " . $conditions;
		$query = $this->db->query($sql);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result['total'];
		} else {
			return 0;
		}
	}

	// get list data
	public function get_list_data($params, $keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
            dp.product_code LIKE '%" . $keyword . "%'
            OR dp.product_name LIKE '%" . $keyword . "%'
            OR dp.product_brand LIKE '%" . $keyword . "%'
            OR dp.product_desc LIKE '%" . $keyword . "%'
            OR dp.product_st LIKE '%" . $keyword . "%'
        )";
		}
		$sql = "SELECT 
    p.*,
    dp.product_id,
    dp.product_code,
    dp.product_name,
    dp.product_price,
    dp.product_no,
    dp.product_brand,
    dp.product_promote,
    dp.product_st,
    dp.product_pict,
    c.cat_name,
    CASE 
        WHEN (
            SELECT MIN(dp_inner.stock) 
            FROM package_categories pc 
            JOIN package_custom_products pcp ON pc.id = pcp.package_category_id 
            JOIN data_product dp_inner ON pcp.product_id = dp_inner.product_id 
            WHERE pc.package_id = p.id AND pc.deleted_at IS NULL AND pcp.deleted_at IS NULL
        ) = 0 THEN 'empty'
        WHEN (
            SELECT MIN(dp_inner.stock) 
            FROM package_categories pc 
            JOIN package_custom_products pcp ON pc.id = pcp.package_category_id 
            JOIN data_product dp_inner ON pcp.product_id = dp_inner.product_id 
            WHERE pc.package_id = p.id AND pc.deleted_at IS NULL AND pcp.deleted_at IS NULL
        ) <= 5 THEN 'low'
        ELSE 'sufficient'
    END as stock_status,
    (SELECT COUNT(*) FROM package_categories WHERE package_id = p.id AND deleted_at IS NULL) as category_count
    FROM packages p
    INNER JOIN data_product dp ON p.product_id = dp.product_id
    LEFT JOIN data_categories c ON dp.cat_id = c.cat_id
    WHERE p.deleted_at IS NULL
    " . $conditions . "
    ORDER BY dp.created DESC, dp.product_no, dp.product_code
    LIMIT ?, ?";
		$query = $this->db->query($sql, $params);

		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get detail package
	function get_detail_package($package_id)
	{
		$sql = "SELECT 
            p.*,
            dp.*,
            c.cat_name,
            c.parent_name_cat
        FROM packages p
        INNER JOIN data_product dp ON p.product_id = dp.product_id
        LEFT JOIN data_categories c ON dp.cat_id = c.cat_id
        WHERE p.id = ?
        AND p.deleted_at IS NULL";
		$query = $this->db->query($sql, $package_id);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get package categories
	function get_package_categories($package_id)
	{
		$sql = "SELECT 
        pc.*,
        pcr.min_items,
        pcr.max_items,
        (SELECT COUNT(*) FROM package_custom_products WHERE package_category_id = pc.id AND deleted_at IS NULL) as product_count
    FROM package_categories pc
    LEFT JOIN package_category_requirements pcr ON pc.id = pcr.package_category_id
    WHERE pc.package_id = ?
    AND pc.deleted_at IS NULL
    ORDER BY pc.display_order ASC";
		$query = $this->db->query($sql, $package_id);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get category detail
	function get_category_detail($category_id)
	{
		$sql = "SELECT 
                pc.*,
                pcr.min_items,
                pcr.max_items,
                p.id as package_id, 
                p.product_id,
                dp.product_name,
                dp.product_brand
            FROM package_categories pc
            LEFT JOIN package_category_requirements pcr ON pc.id = pcr.package_category_id
            LEFT JOIN packages p ON pc.package_id = p.id
            LEFT JOIN data_product dp ON p.product_id = dp.product_id
            WHERE pc.id = ?
            AND pc.deleted_at IS NULL
            AND pcr.deleted_at IS NULL";

		$query = $this->db->query($sql, $category_id);

		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result;
		} else {
			return array(); // Return empty array instead of FALSE
		}
	}

	// get package products
	function get_package_products($package_id, $category_id)
	{
		$sql = "SELECT 
        pcp.*,
        dp.product_code,
        dp.product_name,
        dp.product_desc,
        dp.product_price,
        dp.product_pict,
        dp.stock,
        c.cat_name
    FROM package_custom_products pcp
    INNER JOIN data_product dp ON pcp.product_id = dp.product_id
    LEFT JOIN data_categories c ON dp.cat_id = c.cat_id
    WHERE pcp.package_category_id = ?
    AND pcp.deleted_at IS NULL
    ORDER BY dp.product_name ASC";

		$query = $this->db->query($sql, $category_id);

		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	function get_custom_product_detail($custom_product_id)
	{
		$sql = "SELECT 
        pcp.*, 
        dp.product_name, 
        dp.product_code, 
        dp.product_price,
        pc.package_id,
        pc.id as package_category_id
    FROM package_custom_products pcp
    JOIN data_product dp ON pcp.product_id = dp.product_id
    JOIN package_categories pc ON pcp.package_category_id = pc.id
    WHERE pcp.id = ? 
    AND pcp.deleted_at IS NULL";

		$query = $this->db->query($sql, $custom_product_id);

		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	function get_available_products($brand, $package_id, $category_id)
	{
		// Dapatkan ID produk yang sudah ada dalam paket dan kategori
		$existing_products = $this->db->select('product_id')
			->from('package_custom_products')
			->where('package_category_id', $category_id)
			->where('deleted_at IS NULL')
			->get()
			->result_array();

		$excluded_ids = array_column($existing_products, 'product_id');

		// Dapatkan ID produk paket itu sendiri
		$package_product = $this->db->select('product_id')
			->from('packages')
			->where('id', $package_id)
			->get()
			->row();

		if ($package_product && $package_product->product_id) {
			$excluded_ids[] = $package_product->product_id;
		}

		// Query untuk mendapatkan produk yang tersedia
		$query = $this->db->select('p.product_id, p.product_code, p.product_name, p.product_price, c.cat_name')
			->from('data_product p')
			->join('data_categories c', 'p.cat_id = c.cat_id', 'left')
			->where('p.product_brand', $brand)
			->where('p.product_st', '0') // Hanya produk aktif
			->where('p.is_package', '0') // Kecualikan produk yang sudah menjadi paket
			->where('p.stock >', 0); // Hanya produk dengan stok tersedia

		// Kecualikan produk yang sudah ada
		if (!empty($excluded_ids)) {
			$this->db->where_not_in('p.product_id', $excluded_ids);
		}

		$query = $this->db->order_by('p.product_name', 'ASC')
			->get();

		// Debug: Tambahkan logging
		log_message('debug', 'Query produk tersedia: ' . $this->db->last_query());

		if ($query->num_rows() > 0) {
			$products = $query->result_array();
			log_message('debug', 'Jumlah produk: ' . count($products));
			return $products;
		} else {
			log_message('debug', 'Tidak ada produk tersedia');
			return array();
		}
	}


	// check if product already exists in category

	function check_product_in_category($category_id, $product_id)
	{
		$query = $this->db->where('package_category_id', $category_id)
			->where('product_id', $product_id)
			->where('deleted_at IS NULL')
			->get('package_custom_products');

		return $query->num_rows() > 0;
	}

	// get package statistics
	function get_package_statistics($package_id)
	{
		$package = $this->get_detail_package($package_id);
		$categories = $this->get_package_categories($package_id);

		$total_products = 0;
		$active_products = 0;
		$inactive_products = 0;

		foreach ($categories as $category) {
			$products = $this->get_package_products($package_id, $category['id']);
			$total_products += count($products);

			foreach ($products as $product) {
				if (isset($product['is_active']) && $product['is_active'] == 1) {
					$active_products++;
				} else {
					$inactive_products++;
				}
			}
		}

		$category_count = count($categories);

		return [
			'total_products' => $total_products,
			'active_products' => $active_products,
			'inactive_products' => $inactive_products,
			'category_count' => $category_count,
			'base_price' => $package['base_price'] ?? $package['product_price']
		];
	}

	public function check_package_stock($package_id)
	{
		// get package detail
		$package = $this->m_packages->get_detail_package($package_id);
		if (empty($package)) {
			$this->output->set_content_type('application/json');
			echo json_encode(['success' => false, 'message' => 'Paket tidak ditemukan.']);
			return;
		}

		// get package categories
		$categories = $this->m_packages->get_package_categories($package_id);

		$stock_status = [];
		$has_low_stock = false;

		foreach ($categories as $category) {
			$products = $this->m_packages->get_package_products($package_id, $category['id']);

			$category_status = [
				'category_id' => $category['id'],
				'category_name' => $category['name'],
				'products' => []
			];

			foreach ($products as $product) {
				$stock = intval($product['stock'] ?? 0);
				$is_low_stock = $stock < 5; // Consider low stock if less than 5

				if ($is_low_stock) {
					$has_low_stock = true;
				}

				$category_status['products'][] = [
					'product_id' => $product['product_id'],
					'product_name' => $product['product_name'],
					'stock' => $stock,
					'is_low_stock' => $is_low_stock
				];
			}

			$stock_status[] = $category_status;
		}

		$this->output->set_content_type('application/json');
		echo json_encode([
			'success' => true,
			'has_low_stock' => $has_low_stock,
			'stock_status' => $stock_status
		]);
	}

	function update_product_stock($product_id, $stock_change, $is_increment = true)
	{
		// Validasi input
		$product_id = intval($product_id);
		$stock_change = intval($stock_change);

		// Cek apakah produk ada
		$product = $this->db
			->select('product_id, stock')
			->from('data_product')
			->where('product_id', $product_id)
			->get()
			->row_array();

		if (!$product) {
			log_message('error', "Produk dengan ID $product_id tidak ditemukan");
			return false;
		}

		// Hitung stok baru
		$current_stock = intval($product['stock']);

		if ($is_increment) {
			// Jika increment, tambahkan stok
			$new_stock = $current_stock + $stock_change;
		} else {
			// Jika set ulang, gunakan nilai yang diberikan
			$new_stock = $stock_change;
		}

		// Pastikan stok tidak negatif
		$new_stock = max(0, $new_stock);

		// Update stok
		$update_data = [
			'stock' => $new_stock,
			'modified' => date('Y-m-d H:i:s')
		];

		$this->db->where('product_id', $product_id);
		$result = $this->db->update('data_product', $update_data);

		if ($result) {
			log_message('info', "Stok produk $product_id diperbarui dari $current_stock menjadi $new_stock");
			return true;
		} else {
			log_message('error', "Gagal memperbarui stok produk $product_id");
			return false;
		}
	}

	// Function to check if stock is sufficient
	function check_product_stock($product_id, $required_quantity)
	{
		$stock = $this->db->select('stock')
			->from('data_product')
			->where('product_id', $product_id)
			->get()
			->row_array();

		if (!$stock) {
			return false;
		}

		return intval($stock['stock']) >= intval($required_quantity);
	}

	// Get product stock
	function get_product_stock($product_id)
	{
		$stock = $this->db->select('stock')
			->from('data_product')
			->where('product_id', $product_id)
			->get()
			->row_array();

		return $stock ? intval($stock['stock']) : 0;
	}
}
