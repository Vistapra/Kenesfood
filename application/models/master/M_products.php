<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_products extends CI_Model
{
	function __construct()
	{
		// Call the Model constructor
		parent::__construct();
	}

	// is exist product code
	function is_exist_product_code($params)
	{
		$sql = "SELECT * FROM data_product WHERE product_code = ?";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$query->free_result();
			return TRUE;
		} else {
			return FALSE;
		}
	}

	// is exist product name
	function is_exist_product_name($params)
	{
		$sql = "SELECT * FROM data_product WHERE product_name = ?";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$query->free_result();
			return TRUE;
		} else {
			return FALSE;
		}
	}


	// is exist product code
	function is_exist_product_code_by_id($params)
	{
		$sql = "SELECT * FROM data_product WHERE product_code = ? AND product_id <> ?";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$query->free_result();
			return TRUE;
		} else {
			return FALSE;
		}
	}

	// is exist product name
	function is_exist_product_name_by_id($params)
	{
		$sql = "SELECT * FROM data_product WHERE product_name = ? AND product_id <> ?";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$query->free_result();
			return TRUE;
		} else {
			return FALSE;
		}
	}

	// get total data
	public function get_total_data($keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
                data_product.product_code LIKE '%" . $keyword . "%'
                OR data_product.product_name LIKE '%" . $keyword . "%'
                OR data_product.product_brand LIKE '%" . $keyword . "%'
                OR data_product.product_desc LIKE '%" . $keyword . "%'
                OR data_product.product_st LIKE '%" . $keyword . "%'
            )";
		}
		$sql = "SELECT COUNT(*)'total'  FROM data_product 
                WHERE data_product.product_parent = 0
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
	function get_list_data($params, $keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
                data_product.product_code LIKE '%" . $keyword . "%'
                OR data_product.product_name LIKE '%" . $keyword . "%'
                OR data_product.product_brand LIKE '%" . $keyword . "%'
                OR data_product.product_desc LIKE '%" . $keyword . "%'
                OR data_product.product_st LIKE '%" . $keyword . "%'
            )";
		}
		$sql = "SELECT 
           data_product.product_id,
           data_product.product_code,
           data_product.product_name,
           data_product.parent_name,
           data_product.product_price,
           data_product.product_no,
           data_product.product_brand,
           data_product.product_promote,
           data_product.product_st,
           data_product.api_id,  /* Tambahkan field api_id */
           data_categories.cat_name,
           data_categories.parent_name_cat
       FROM data_product 
       INNER JOIN data_categories on data_product.cat_id=data_categories.cat_id
       WHERE data_product.product_parent = 0
       " . $conditions . "
       ORDER BY data_product.product_no, data_product.product_code
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

	// get total data varian
	public function get_total_data_varian($params, $keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
                data_product.product_code LIKE '%" . $keyword . "%'
                OR data_product.product_name LIKE '%" . $keyword . "%'
                OR data_product.product_brand LIKE '%" . $keyword . "%'
                OR data_product.product_desc LIKE '%" . $keyword . "%'
                OR data_product.product_st LIKE '%" . $keyword . "%'
            )";
		}
		$sql = "SELECT COUNT(*)'total'  FROM data_product 
                WHERE data_product.product_parent = ?
                " . $conditions;
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result['total'];
		} else {
			return 0;
		}
	}

	public function get_list_product_katalog()
	{
		$sql = "SELECT 
                    data_product.* 
                FROM data_product 
                ORDER BY data_product.product_code";
		$query = $this->db->query($sql);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// get list data varian
	function get_list_data_varian($params, $keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
                data_product.product_code LIKE '%" . $keyword . "%'
                OR data_product.product_name LIKE '%" . $keyword . "%'
                OR data_product.product_brand LIKE '%" . $keyword . "%'
                OR data_product.product_desc LIKE '%" . $keyword . "%'
                OR data_product.product_st LIKE '%" . $keyword . "%'
            )";
		}
		$sql = "SELECT 
                    data_product.* 
                FROM data_product 
                WHERE data_product.product_parent = ?
                " . $conditions . "
                ORDER BY data_product.product_code
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

	// get detail product
	public function get_detail_product($params)
	{
		$sql = "SELECT 
                data_product.product_id,
                data_product.product_parent,
                data_product.cat_id,
                data_product.package_category_id,
                data_product.varian,
                data_product.product_code,
                data_product.product_name,
                data_product.parent_name,
                data_product.product_no,
                data_product.product_brand,
                data_product.product_type,
                data_product.product_price,
                data_product.product_komposisi,
                data_product.expired_date,
                data_product.product_netto,
                data_product.product_pict,
                data_product.product_desc,
                data_product.stock,
                data_product.product_st,
                data_product.is_package,
                data_product.package_type,
                data_product.product_promote,
                data_product.product_popularity,
                data_product.status_product,
                data_product.ek_marketing,
                data_product.ek_customer,
                data_product.ek_outlet,
                data_product.created,
                data_product.created_by,
                data_product.modified,
                data_product.modified_by,
                data_product.api_id as product_api_id,  -- API ID produk dengan alias yang jelas
                data_categories.cat_id as category_id,
                data_categories.cat_parent,
                data_categories.cat_sub,
                data_categories.cat_code,
                data_categories.cat_name,
                data_categories.parent_name_cat,
                data_categories.cat_brand,
                data_categories.cat_desc,
                data_categories.cat_img,
                data_categories.cat_highlight,
                data_categories.cat_no,
                data_categories.cat_st,
                data_categories.cat_harga,
                data_categories.seasonal_id,
                data_categories.api_id as category_api_id  -- API ID kategori dengan alias yang jelas
            FROM data_product 
            INNER JOIN data_categories on data_product.cat_id=data_categories.cat_id
            WHERE data_product.product_id = ?";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();

			// Set api_id untuk konsistensi (ini adalah API ID produk)
			$result['api_id'] = $result['product_api_id'];

			// Log untuk debugging
			log_message('debug', "Product detail - Product ID: {$params}, Product API ID: " .
				($result['product_api_id'] ?? 'NULL') . ", Category API ID: " .
				($result['category_api_id'] ?? 'NULL'));

			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}
	function unlink_product_from_api($product_id)
	{
		// Log the unlink action
		log_message('info', "Unlinking product ID {$product_id} from API");

		$data = [
			'api_id' => null,
			'modified' => date('Y-m-d H:i:s'),
			'modified_by' => isset($this->user_data['user_id']) ? $this->user_data['user_id'] : 0
		];

		$this->db->where('product_id', $product_id);
		$result = $this->db->update('data_product', $data);

		if ($result) {
			log_message('info', "Successfully unlinked product ID {$product_id} from API");
		} else {
			log_message('error', "Failed to unlink product ID {$product_id} from API");
		}

		return $result;
	}

	function is_valid_api_id($api_id, $brand)
	{
		if (empty($api_id) || empty($brand)) {
			return false;
		}

		// Load Products_api library
		$CI = &get_instance();
		$CI->load->library('Products_api');

		// Get products from API
		$api_products = $CI->products_api->getProductsByBrand($brand);

		// Check if API ID exists in the response
		foreach ($api_products as $product) {
			if (isset($product['product_id']) && $product['product_id'] == $api_id) {
				return true;
			}
		}

		return false;
	}

	function get_api_product_by_id($api_id, $brand)
	{
		if (empty($api_id) || empty($brand)) {
			return null;
		}

		// Load Products_api library
		$CI = &get_instance();
		$CI->load->library('Products_api');

		// Get products from API
		$api_products = $CI->products_api->getProductsByBrand($brand);

		// Find the specific product
		foreach ($api_products as $product) {
			if (isset($product['product_id']) && $product['product_id'] == $api_id) {
				return $product;
			}
		}

		return null;
	}
	// get list varian product
	public function get_list_varian_product($params)
	{
		$sql = "SELECT 
                    data_product.* 
                FROM data_product 
                WHERE data_product.product_parent = ?";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	// insert product
	function add_product($params)
	{
		return $this->db->insert('data_product', $params);
	}

	// update product
	function update_product($product_id, $params)
	{
		$this->db->where('product_id', $product_id);
		return $this->db->update('data_product', $params);
	}

	// update product
	function update_variant_product($product_parent, $params)
	{
		$this->db->where('product_parent', $product_parent);
		return $this->db->update('data_product', $params);
	}

	// delete product
	function delete_product($product_id)
	{
		$this->db->where('product_id', $product_id);
		return $this->db->delete('data_product');
	}

	// delete varian product
	function delete_variant_product($product_parent)
	{
		$this->db->where('product_parent', $product_parent);
		return $this->db->delete('data_product');
	}

	/**
	 * Find product by API ID and Brand
	 * Mempertimbangkan brand untuk menghindari konflik produk
	 *
	 * @param int $api_id API ID
	 * @param string $brand Brand type (optional)
	 * @return array|null Product data or null if not found
	 */
	function find_by_api_id_and_brand($api_id, $brand = null)
	{
		// First check if api_id column exists in the table
		if (!$this->db->field_exists('api_id', 'data_product')) {
			$this->db->query('ALTER TABLE data_product ADD COLUMN api_id INT NULL');
		}

		$this->db->where('api_id', $api_id);

		// Tambahkan filter berdasarkan brand jika disediakan
		if (!empty($brand)) {
			$this->db->where('product_brand', $brand);
		}

		$query = $this->db->get('data_product');

		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			log_message('debug', "Found product with api_id={$api_id}, brand={$brand}: " . json_encode($result));
			return $result;
		}

		log_message('debug', "No product found with api_id={$api_id}, brand={$brand}");
		return null;
	}

	/**
	 * Find product by API ID (legacy)
	 *
	 * @param int $api_id API ID
	 * @return array|null Product data or null if not found
	 */
	function find_by_api_id($api_id)
	{
		// Forward to new function
		return $this->find_by_api_id_and_brand($api_id);
	}

	/**
	 * Clean duplicate products with same API ID and brand
	 * Fungsi untuk membersihkan duplikasi produk
	 *
	 * @param int $api_id API ID to clean
	 * @param string $brand Brand type
	 * @return bool Success status
	 */
	function clean_duplicate_products($api_id, $brand)
	{
		// Cari semua produk dengan api_id dan brand yang sama
		$this->db->where('api_id', $api_id);
		$this->db->where('product_brand', $brand);
		$this->db->order_by('product_id', 'ASC');
		$query = $this->db->get('data_product');

		if ($query->num_rows() <= 1) {
			// Tidak ada duplikasi, tidak perlu membersihkan
			return true;
		}

		$products = $query->result_array();

		// Simpan produk pertama (paling lama) sebagai produk utama
		$main_product = $products[0];

		// Ambil ID produk yang akan dihapus (semua kecuali yang pertama)
		$product_ids_to_delete = [];
		for ($i = 1; $i < count($products); $i++) {
			$product_ids_to_delete[] = $products[$i]['product_id'];
		}

		// Log tindakan yang akan dilakukan
		log_message('debug', "Cleaning duplicate products for api_id={$api_id} and brand={$brand}. " .
			"Keeping product_id={$main_product['product_id']}, deleting: " . implode(',', $product_ids_to_delete));

		// Update referensi di tabel lain ke produk utama
		if (!empty($product_ids_to_delete)) {
			// Update order_details
			$this->db->where_in('product_id', $product_ids_to_delete);
			$this->db->update('order_details', ['product_id' => $main_product['product_id']]);

			// Update packages jika ada
			if ($this->db->table_exists('packages')) {
				$this->db->where_in('product_id', $product_ids_to_delete);
				$this->db->update('packages', ['product_id' => $main_product['product_id']]);
			}

			// Update package_custom_products jika ada
			if ($this->db->table_exists('package_custom_products')) {
				$this->db->where_in('product_id', $product_ids_to_delete);
				$this->db->update('package_custom_products', ['product_id' => $main_product['product_id']]);
			}

			// Hapus produk duplikat
			$this->db->where_in('product_id', $product_ids_to_delete);
			$this->db->delete('data_product');
		}

		return true;
	}

	/**
	 * Find products by brand
	 * 
	 * @param string $brand Brand type
	 * @return array List of products
	 */
	function find_by_brand($brand)
	{
		$this->db->where('product_brand', $brand);
		$this->db->where('product_parent', 0); // Only main products, not variants
		$query = $this->db->get('data_product');

		if ($query->num_rows() > 0) {
			return $query->result_array();
		}

		return array();
	}

	/**
	 *  PUBLIC PAGE
	 */

	// get list product banner
	function get_list_product_banner($params)
	{
		$sql = "SELECT 
                    prod.*, cat.seasonal_id
                FROM `data_product` prod
                INNER JOIN data_categories cat ON prod.cat_id = cat.cat_id
                WHERE prod.`product_promote` IN ('arrival','prelaunch') 
                AND prod.`product_st` = '0'
                AND prod.product_brand = ?
            ";
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	/**
	 * Pencarian Produk dengan Filter Kompleks
	 * 
	 * @param array $filters Filter pencarian
	 * @param int $limit Batas produk
	 * @param int $offset Offset produk
	 * @return array Hasil pencarian produk
	 */
	public function searchProducts($filters = [], $limit = 20, $offset = 0)
	{
		$this->db->start_cache();

		// Filter dinamis
		if (!empty($filters['keyword'])) {
			$this->db->group_start()
				->like('product_name', $filters['keyword'])
				->or_like('product_code', $filters['keyword'])
				->or_like('product_desc', $filters['keyword'])
				->group_end();
		}

		if (!empty($filters['brand'])) {
			$this->db->where('product_brand', $filters['brand']);
		}

		if (!empty($filters['category'])) {
			$this->db->where('cat_id', $filters['category']);
		}

		if (isset($filters['status'])) {
			$this->db->where('product_st', $filters['status']);
		}

		$this->db->stop_cache();

		// Hitung total
		$total = $this->db->count_all_results('data_product');

		// Ambil data
		$this->db->select('*')
			->from('data_product')
			->limit($limit, $offset)
			->order_by('product_no', 'ASC');

		$results = $this->db->get()->result_array();

		$this->db->flush_cache();

		return [
			'products' => $results,
			'total' => $total,
			'pages' => ceil($total / $limit)
		];
	}

	/**
	 * Sinkronisasi Produk dari API
	 * 
	 * @param array $apiProducts Produk dari API
	 * @return array Statistik sinkronisasi
	 */
	public function syncProductsFromAPI($api_products)
	{
		$stats = [
			'total' => count($api_products),
			'new' => 0,
			'updated' => 0,
			'failed' => 0,
			'unchanged' => 0
		];

		foreach ($api_products as $product) {
			// Pastikan kita memiliki semua data yang diperlukan
			if (
				empty($product['product_id']) || empty($product['product_name']) ||
				empty($product['product_code']) || empty($product['category_id']) ||
				empty($product['product_brand'])
			) {
				$stats['failed']++;
				continue;
			}

			// Cari produk yang sudah ada berdasarkan API ID dan brand
			$existing_product = $this->find_by_api_id_and_brand(
				$product['product_id'],
				$product['product_brand']
			);

			// Cari kategori yang sesuai
			$this->load->model('master/M_categories', 'm_categories');
			$category = $this->m_categories->find_by_api_id_and_brand(
				$product['category_id'],
				$product['product_brand']
			);

			if (!$category) {
				// Jika kategori tidak ditemukan, buat kategori baru
				$cat_data = [
					'cat_parent' => '0',
					'cat_brand' => $product['product_brand'],
					'cat_code' => 'CAT' . $product['category_id'],
					'cat_name' => $product['category_name'] ?? 'Unknown Category',
					'cat_desc' => 'Kategori dari ' . $product['product_brand'],
					'cat_st' => '0', // Aktif
					'cat_highlight' => '1', // Disorot
					'cat_no' => 1,
					'cat_harga' => 0,
					'api_id' => $product['category_id'],
					'created' => date('Y-m-d H:i:s'),
					'created_by' => 0, // 0 for system
					'modified' => date('Y-m-d H:i:s'),
					'modified_by' => 0,
					'seasonal_id' => 0
				];

				$this->m_categories->add_category($cat_data);

				// Ambil ID kategori yang baru dibuat
				$category = $this->m_categories->find_by_api_id_and_brand(
					$product['category_id'],
					$product['product_brand']
				);

				// Bersihkan duplikasi kategori jika ada
				$this->m_categories->clean_duplicate_categories(
					$product['category_id'],
					$product['product_brand']
				);
			}

			// Siapkan data produk
			$product_data = [
				'product_parent' => '0',
				'cat_id' => $category['cat_id'] ?? 0,
				'product_brand' => $product['product_brand'],
				'product_code' => $product['product_code'],
				'product_name' => $product['product_name'],
				'product_price' => $product['product_price'] ?? 0,
				'product_desc' => $product['product_desc'] ?? '',
				'product_komposisi' => $product['product_komposisi'] ?? 'Disinkronkan dari API',
				'expired_date' => $product['expired_date'] ?? 30,
				'product_netto' => $product['product_netto'] ?? 0,
				'product_st' => '0', // Aktif
				'product_promote' => 'none',
				'api_id' => $product['product_id'],
				'modified' => date('Y-m-d H:i:s'),
				'modified_by' => 0 // 0 for system
			];

			if ($existing_product) {
				// Update produk yang sudah ada
				$product_data['product_pict'] = $existing_product['product_pict'];

				// Cek apakah ada perubahan data
				$changed = false;
				foreach ($product_data as $key => $value) {
					if (isset($existing_product[$key]) && $existing_product[$key] != $value) {
						$changed = true;
						break;
					}
				}

				if ($changed) {
					$this->update_product($existing_product['product_id'], $product_data);
					$stats['updated']++;
				} else {
					$stats['unchanged']++;
				}
			} else {
				// Buat produk baru
				$product_data['product_pict'] = 'default-product.jpg';
				$product_data['created'] = date('Y-m-d H:i:s');
				$product_data['created_by'] = 0; // 0 for system

				if ($this->add_product($product_data)) {
					$stats['new']++;
				} else {
					$stats['failed']++;
				}
			}

			// Bersihkan duplikasi produk jika ada
			$this->clean_duplicate_products($product['product_id'], $product['product_brand']);
		}

		return $stats;
	}

	/**
	 * Get all varian product details
	 * 
	 * @param int $product_parent Parent product ID
	 * @return array List of variant products with complete details
	 */
	function get_detail_varian_products($product_parent)
	{
		$sql = "SELECT 
                data_product.*,
                data_categories.*
            FROM data_product 
            INNER JOIN data_categories on data_product.cat_id=data_categories.cat_id
            WHERE data_product.product_parent = ?";
		$query = $this->db->query($sql, $product_parent);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	/**
	 * Get product update history
	 * 
	 * @param int $product_id Product ID
	 * @param int $limit Maximum number of records to return
	 * @return array List of update history entries
	 */
	function get_product_update_history($product_id, $limit = 10)
	{
		// Create the table if it doesn't exist
		if (!$this->db->table_exists('product_update_history')) {
			$this->db->query("
            CREATE TABLE `product_update_history` (
              `history_id` int(11) NOT NULL AUTO_INCREMENT,
              `product_id` int(11) NOT NULL,
              `update_source` enum('web','mrp') NOT NULL DEFAULT 'web',
              `updated_fields` text DEFAULT NULL,
              `old_values` text DEFAULT NULL,
              `new_values` text DEFAULT NULL,
              `created_at` datetime NOT NULL,
              `created_by` int(11) DEFAULT NULL,
              PRIMARY KEY (`history_id`),
              KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
		}

		$sql = "SELECT * FROM product_update_history 
            WHERE product_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?";
		$query = $this->db->query($sql, array($product_id, $limit));

		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	/**
	 * Log product update for tracking changes
	 * 
	 * @param int $product_id Product ID
	 * @param string $update_source Source of update (web/mrp)
	 * @param array $updated_fields Array of updated field names
	 * @param array $old_values Old values before update
	 * @param array $new_values New values after update
	 * @return bool Success status
	 */
	function log_product_update($product_id, $update_source, $updated_fields, $old_values, $new_values)
	{
		// Ensure the table exists
		if (!$this->db->table_exists('product_update_history')) {
			$this->db->query("
            CREATE TABLE `product_update_history` (
              `history_id` int(11) NOT NULL AUTO_INCREMENT,
              `product_id` int(11) NOT NULL,
              `update_source` enum('web','mrp') NOT NULL DEFAULT 'web',
              `updated_fields` text DEFAULT NULL,
              `old_values` text DEFAULT NULL,
              `new_values` text DEFAULT NULL,
              `created_at` datetime NOT NULL,
              `created_by` int(11) DEFAULT NULL,
              PRIMARY KEY (`history_id`),
              KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
		}

		$data = array(
			'product_id' => $product_id,
			'update_source' => $update_source,
			'updated_fields' => json_encode($updated_fields),
			'old_values' => json_encode($old_values),
			'new_values' => json_encode($new_values),
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => isset($this->user_data['user_id']) ? $this->user_data['user_id'] : 0
		);

		return $this->db->insert('product_update_history', $data);
	}

	/**
	 * Enhanced update_product method that logs changes
	 * 
	 * @param int $product_id Product ID to update
	 * @param array $params New data to apply
	 * @param string $update_source Source of update (web/mrp)
	 * @return bool Success status
	 */
	function update_product_with_logging($product_id, $params, $update_source = 'web')
	{
		// Get original product data
		$original_product = $this->get_detail_product($product_id);

		if (empty($original_product)) {
			return false;
		}

		// Determine which fields are being updated
		$updated_fields = array();
		$old_values = array();
		$new_values = array();

		foreach ($params as $field => $value) {
			if (isset($original_product[$field]) && $original_product[$field] != $value) {
				$updated_fields[] = $field;
				$old_values[$field] = $original_product[$field];
				$new_values[$field] = $value;
			}
		}

		// If there are changes to log
		if (!empty($updated_fields)) {
			// Update the product
			$this->db->where('product_id', $product_id);
			$update_success = $this->db->update('data_product', $params);

			if ($update_success) {
				// Log the update
				$this->log_product_update(
					$product_id,
					$update_source,
					$updated_fields,
					$old_values,
					$new_values
				);

				return true;
			}

			return false;
		}

		// No changes were made
		return true;
	}

	/**
	 * Find products with recent updates
	 * 
	 * @param int $hours Number of hours to look back
	 * @return array Products updated within the specified time period
	 */
	function find_recently_updated_products($hours = 24)
	{
		$sql = "SELECT p.*, c.cat_name, h.created_at as last_update, h.update_source
            FROM data_product p
            INNER JOIN data_categories c ON p.cat_id = c.cat_id
            INNER JOIN (
                SELECT product_id, MAX(created_at) as created_at, update_source 
                FROM product_update_history
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY product_id
            ) h ON p.product_id = h.product_id
            ORDER BY h.created_at DESC";

		$query = $this->db->query($sql, array($hours));

		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	/**
	 * Log perubahan produk dari API (tanpa user_id)
	 * 
	 * @param int $product_id ID produk yang diubah
	 * @param string $source Sumber perubahan (misalnya 'api_mrp')
	 * @param array $changed_fields Daftar field yang berubah
	 * @param array $old_values Nilai lama
	 * @param array $new_values Nilai baru
	 * @return bool Berhasil/gagal
	 */
	public function log_product_update_api($product_id, $source, $changed_fields, $old_values, $new_values)
	{
		// Cek apakah tabel product_updates ada, jika tidak buat
		if (!$this->db->table_exists('product_updates')) {
			$this->db->query("
            CREATE TABLE IF NOT EXISTS `product_updates` (
                `update_id` int(11) NOT NULL AUTO_INCREMENT,
                `product_id` int(11) NOT NULL,
                `source` varchar(50) NOT NULL,
                `changed_fields` text DEFAULT NULL,
                `old_values` text DEFAULT NULL,
                `new_values` text DEFAULT NULL,
                `update_time` datetime NOT NULL,
                PRIMARY KEY (`update_id`),
                KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
		}

		// Data untuk dimasukkan ke log, tanpa user_id
		$log_data = [
			'product_id' => $product_id,
			'source' => $source,
			'changed_fields' => json_encode($changed_fields),
			'old_values' => json_encode($old_values),
			'new_values' => json_encode($new_values),
			'update_time' => date('Y-m-d H:i:s')
		];

		// Insert ke database
		return $this->db->insert('product_updates', $log_data);
	}
	public function search_products($params = [])
	{
		$limit = isset($params['limit']) ? $params['limit'] : 20;
		$offset = isset($params['offset']) ? $params['offset'] : 0;
		$search = isset($params['search']) ? $params['search'] : '';
		$brand = isset($params['brand']) ? $params['brand'] : '';

		$this->db->select('product_id, product_name, product_code, product_brand, product_price');
		$this->db->from('data_product');

		// Filter pencarian
		if (!empty($search)) {
			$this->db->group_start();
			$this->db->like('product_name', $search);
			$this->db->or_like('product_code', $search);
			$this->db->group_end();
		}

		// Filter brand
		if (!empty($brand)) {
			$this->db->where('product_brand', $brand);
		}

		// Filter status aktif
		$this->db->where('product_st', '0'); // 0 = active

		// Batasi hasil
		$this->db->limit($limit, $offset);
		$this->db->order_by('product_name', 'ASC');

		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Get list of API IDs that are already used by products in database
	 * 
	 * @param string $brand Brand type to filter
	 * @param int $exclude_product_id Product ID to exclude from check (current editing product)
	 * @return array List of used API IDs
	 */
	function get_used_api_ids($brand = null, $exclude_product_id = null)
	{
		$this->db->select('api_id');
		$this->db->from('data_product');
		$this->db->where('api_id IS NOT NULL');
		$this->db->where('api_id !=', '');

		// Filter by brand if provided
		if (!empty($brand)) {
			$this->db->where('product_brand', $brand);
		}

		// Exclude current editing product if provided
		if (!empty($exclude_product_id)) {
			$this->db->where('product_id !=', $exclude_product_id);
		}

		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			$results = $query->result_array();
			$used_ids = array();

			foreach ($results as $row) {
				if (!empty($row['api_id'])) {
					$used_ids[] = $row['api_id'];
				}
			}

			log_message('debug', "Found " . count($used_ids) . " used API IDs for brand: " . ($brand ?? 'all'));
			return $used_ids;
		}

		return array();
	}

	/**
	 * Check if specific API ID is already used by another product
	 * 
	 * @param int $api_id API ID to check
	 * @param string $brand Brand type
	 * @param int $exclude_product_id Product ID to exclude from check
	 * @return bool True if API ID is already used
	 */
	function is_api_id_used($api_id, $brand = null, $exclude_product_id = null)
	{
		if (empty($api_id)) {
			return false;
		}

		$this->db->select('product_id, product_name, product_code');
		$this->db->from('data_product');
		$this->db->where('api_id', $api_id);

		// Filter by brand if provided
		if (!empty($brand)) {
			$this->db->where('product_brand', $brand);
		}

		// Exclude current editing product if provided
		if (!empty($exclude_product_id)) {
			$this->db->where('product_id !=', $exclude_product_id);
		}

		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			log_message('debug', "API ID {$api_id} is already used by product: {$result['product_name']} (ID: {$result['product_id']})");
			return $result; // Return product info that uses this API ID
		}

		return false;
	}

	/**
	 * Get products that are connected to API (have api_id)
	 * 
	 * @param string $brand Brand type to filter
	 * @return array List of products with API connections
	 */
	function get_api_connected_products($brand = null)
	{
		$this->db->select('product_id, product_name, product_code, api_id, product_brand');
		$this->db->from('data_product');
		$this->db->where('api_id IS NOT NULL');
		$this->db->where('api_id !=', '');

		if (!empty($brand)) {
			$this->db->where('product_brand', $brand);
		}

		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			return $query->result_array();
		}

		return array();
	}
}
