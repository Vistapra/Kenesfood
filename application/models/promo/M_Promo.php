<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class M_Promo extends CI_Model
{
	private $table = 'promos';
	private $usage_table = 'promo_usage';
	private $products_table = 'promo_products';
	private $categories_table = 'promo_categories';
	private $bundle_table = 'promo_bundles';
	private $bogo_table = 'promo_bogo';

	public function __construct()
	{
		parent::__construct();
		$this->load->database();
	}

	public function getAllPromos($params = [])
	{
		$this->db->select('p.*');
		$this->db->from($this->table . ' AS p');

		// Apply filters
		if (!empty($params['promo_brand'])) {
			// Untuk voucher MRP, cek supported_brands juga
			$this->db->group_start();
			$this->db->where('p.promo_brand', $params['promo_brand']);
			$this->db->or_where("p.is_mrp_voucher = 1 AND p.supported_brands LIKE '%" . $params['promo_brand'] . "%'");
			$this->db->group_end();
		}

		if (isset($params['promo_status'])) {
			$this->db->where('p.promo_status', $params['promo_status']);
		}

		if (isset($params['active_now']) && $params['active_now']) {
			$now = date('Y-m-d H:i:s');
			$this->db->where('p.start_date <=', $now);
			$this->db->where('p.end_date >=', $now);
		}

		// Filter berdasarkan is_mrp_voucher
		if (isset($params['is_mrp_voucher'])) {
			$this->db->where('p.is_mrp_voucher', $params['is_mrp_voucher']);
		}

		// Filter berdasarkan promo_type
		if (!empty($params['promo_type'])) {
			$this->db->where('p.promo_type', $params['promo_type']);
		}

		// Filter berdasarkan multiple promo_type
		if (!empty($params['promo_type_in']) && is_array($params['promo_type_in'])) {
			$this->db->where_in('p.promo_type', $params['promo_type_in']);
		}

		// Filter berdasarkan product ID
		if (!empty($params['product_id'])) {
			$this->db->join($this->products_table . ' AS pp', 'p.promo_id = pp.promo_id', 'inner');
			$this->db->where('pp.product_id', $params['product_id']);
		}

		// Filter berdasarkan category ID
		if (!empty($params['category_id'])) {
			$this->db->join($this->categories_table . ' AS pc', 'p.promo_id = pc.promo_id', 'inner');
			$this->db->where('pc.cat_id', $params['category_id']);
		}

		// Search by code or name
		if (!empty($params['search'])) {
			$this->db->group_start();
			$this->db->like('p.promo_code', $params['search']);
			$this->db->or_like('p.promo_name', $params['search']);
			$this->db->group_end();
		}

		// Only show non-deleted promos
		$this->db->where('p.deleted_at IS NULL');

		// Apply sorting
		if (!empty($params['sort_by'])) {
			$this->db->order_by($params['sort_by'], !empty($params['sort_dir']) ? $params['sort_dir'] : 'ASC');
		} else {
			$this->db->order_by('p.created_at', 'DESC');
		}

		// Group by promo_id untuk menghindari duplikasi karena join
		$this->db->group_by('p.promo_id');

		// Apply pagination
		if (isset($params['limit']) && isset($params['offset'])) {
			$this->db->limit($params['limit'], $params['offset']);
		}

		$query = $this->db->get();
		return $query->result_array();
	}


	public function countAllPromos($params = [])
	{
		$this->db->select('COUNT(DISTINCT p.promo_id) as total');
		$this->db->from($this->table . ' AS p');

		// Apply filters
		if (!empty($params['promo_brand'])) {
			// Untuk voucher MRP, cek supported_brands juga
			$this->db->group_start();
			$this->db->where('p.promo_brand', $params['promo_brand']);
			$this->db->or_where("p.is_mrp_voucher = 1 AND p.supported_brands LIKE '%" . $params['promo_brand'] . "%'");
			$this->db->group_end();
		}

		if (isset($params['promo_status'])) {
			$this->db->where('p.promo_status', $params['promo_status']);
		}

		if (isset($params['active_now']) && $params['active_now']) {
			$now = date('Y-m-d H:i:s');
			$this->db->where('p.start_date <=', $now);
			$this->db->where('p.end_date >=', $now);
		}

		// Filter is_mrp_voucher
		if (isset($params['is_mrp_voucher'])) {
			$this->db->where('p.is_mrp_voucher', $params['is_mrp_voucher']);
		}

		// Filter based on promo_type
		if (!empty($params['promo_type'])) {
			$this->db->where('p.promo_type', $params['promo_type']);
		}

		// Filter based on multiple promo_type
		if (!empty($params['promo_type_in']) && is_array($params['promo_type_in'])) {
			$this->db->where_in('p.promo_type', $params['promo_type_in']);
		}

		// Filter berdasarkan product ID
		if (!empty($params['product_id'])) {
			$this->db->join($this->products_table . ' AS pp', 'p.promo_id = pp.promo_id', 'inner');
			$this->db->where('pp.product_id', $params['product_id']);
		}

		// Filter berdasarkan category ID
		if (!empty($params['category_id'])) {
			$this->db->join($this->categories_table . ' AS pc', 'p.promo_id = pc.promo_id', 'inner');
			$this->db->where('pc.cat_id', $params['category_id']);
		}

		// Search by code or name
		if (!empty($params['search'])) {
			$this->db->group_start();
			$this->db->like('p.promo_code', $params['search']);
			$this->db->or_like('p.promo_name', $params['search']);
			$this->db->group_end();
		}

		// Only count non-deleted promos
		$this->db->where('p.deleted_at IS NULL');

		$result = $this->db->get()->row();
		return $result->total;
	}

	/**
	 * Get promo statistics
	 * 
	 * @return array Statistics
	 */
	public function getPromoStats()
	{
		$now = date('Y-m-d H:i:s');

		// Count active promos
		$this->db->where('promo_status', 'active');
		$this->db->where('start_date <=', $now);
		$this->db->where('end_date >=', $now);
		$this->db->where('deleted_at IS NULL');
		$active_count = $this->db->count_all_results($this->table);

		// Count upcoming promos
		$this->db->where('promo_status', 'active');
		$this->db->where('start_date >', $now);
		$this->db->where('deleted_at IS NULL');
		$upcoming_count = $this->db->count_all_results($this->table);

		// Count expired promos
		$this->db->where('promo_status', 'active');
		$this->db->where('end_date <', $now);
		$this->db->where('deleted_at IS NULL');
		$expired_count = $this->db->count_all_results($this->table);

		return [
			'active_count' => $active_count,
			'upcoming_count' => $upcoming_count,
			'expired_count' => $expired_count
		];
	}

	/**
	 * Get a single promo by ID
	 * 
	 * @param int $promo_id Promo ID
	 * @return array|null Promo data or null if not found
	 */
	public function getPromoById($promo_id)
	{
		$this->db->where('promo_id', $promo_id);
		$this->db->where('deleted_at IS NULL');
		$query = $this->db->get($this->table);

		if ($query->num_rows() > 0) {
			return $query->row_array();
		}

		return null;
	}

	/**
	 * Get a single promo by code
	 * 
	 * @param string $promo_code Promo code
	 * @return array|null Promo data or null if not found
	 */
	public function getPromoByCode($promo_code)
	{
		$this->db->where('promo_code', $promo_code);
		$this->db->where('deleted_at IS NULL');
		$query = $this->db->get($this->table);

		if ($query->num_rows() > 0) {
			return $query->row_array();
		}

		return null;
	}

	/**
	 * Create a new promo
	 * 
	 * @param array $data Promo data
	 * @return array Result with success status and ID or error message
	 */
	public function createPromo($data)
	{
		$this->db->trans_begin();

		try {
			// Log input data for debugging
			log_message('info', 'Create Promo Input Data: ' . json_encode($data));

			// Validate promo code is unique
			$this->db->where('promo_code', $data['promo_code']);
			$this->db->where('deleted_at IS NULL');
			$existing = $this->db->get($this->table)->row();

			if ($existing) {
				return [
					'success' => false,
					'message' => 'Kode promo sudah digunakan'
				];
			}

			// Prepare promo data with safe defaults
			$promo_data = [
				'promo_code' => strtoupper($data['promo_code']),
				'promo_name' => $data['promo_name'],
				'promo_brand' => $data['promo_brand'],
				'promo_type' => $data['promo_type'],
				'start_date' => $data['start_date'],
				'end_date' => $data['end_date'],
				'quota' => $data['quota'] ?? null,
				'description' => $data['description'] ?? null,
				'promo_status' => $data['promo_status'] ?? 'active',
				'created_at' => date('Y-m-d H:i:s'),
				'created_by' => $this->session->userdata('user_id') ?? 1,
				'minimum_order' => isset($data['minimum_order']) ?
					max(0, floatval($data['minimum_order'])) : 0
			];

			// Handle different promo types
			switch ($data['promo_type']) {
				case 'percentage':
					$promo_data['promo_value'] = $data['promo_value'] ?? 0;
					$promo_data['maximum_discount'] = $data['maximum_discount'] ?? null;
					$promo_data['minimum_order'] = $data['minimum_order'] ?? 0;
					break;
				case 'nominal':
					$promo_data['promo_value'] = $data['promo_value'] ?? 0;
					$promo_data['minimum_order'] = $data['minimum_order'] ?? 0;
					break;
				case 'bundling':
				case 'bogo':
					$promo_data['promo_value'] = 0;
					$promo_data['minimum_order'] = 0;
					break;
				default:
					log_message('error', 'Invalid promo type: ' . $data['promo_type']);
					return [
						'success' => false,
						'message' => 'Tipe promo tidak valid'
					];
			}

			// Insert promo data
			$this->db->insert($this->table, $promo_data);
			$promo_id = $this->db->insert_id();

			// Handle product specific promos
			if (!empty($data['product_ids']) && is_array($data['product_ids'])) {
				foreach ($data['product_ids'] as $product_id) {
					$this->db->insert($this->products_table, [
						'promo_id' => $promo_id,
						'product_id' => $product_id
					]);
				}
			}

			// Handle category specific promos
			if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
				foreach ($data['category_ids'] as $cat_id) {
					$this->db->insert($this->categories_table, [
						'promo_id' => $promo_id,
						'cat_id' => $cat_id
					]);
				}
			}

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return [
					'success' => false,
					'message' => 'Terjadi kesalahan database'
				];
			} else {
				$this->db->trans_commit();
				return [
					'success' => true,
					'promo_id' => $promo_id
				];
			}
		} catch (Exception $e) {
			$this->db->trans_rollback();
			log_message('error', 'Promo Creation Error: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}

	/**
	 * Update an existing promo
	 * 
	 * @param int $promo_id Promo ID
	 * @param array $data Updated promo data
	 * @return array Result with success status and message
	 */
	public function updatePromo($promo_id, $data)
	{
		$this->db->trans_begin();

		try {
			// Check if promo exists
			$existing = $this->getPromoById($promo_id);
			if (!$existing) {
				return [
					'success' => false,
					'message' => 'Promo tidak ditemukan'
				];
			}

			// Check if promo code is being changed and validate uniqueness
			if (isset($data['promo_code']) && $data['promo_code'] !== $existing['promo_code']) {
				$this->db->where('promo_code', $data['promo_code']);
				$this->db->where('promo_id !=', $promo_id);
				$this->db->where('deleted_at IS NULL');
				$duplicate = $this->db->get($this->table)->row();

				if ($duplicate) {
					return [
						'success' => false,
						'message' => 'Kode promo sudah digunakan'
					];
				}
			}

			// Prepare update data
			$update_data = [];

			// Only update fields that are provided
			$updatable_fields = [
				'promo_code',
				'promo_name',
				'promo_brand',
				'promo_type',
				'promo_value',
				'minimum_order',
				'maximum_discount',
				'start_date',
				'end_date',
				'quota',
				'description',
				'promo_status'
			];

			foreach ($updatable_fields as $field) {
				if (isset($data[$field])) {
					$update_data[$field] = $data[$field];
				}
			}

			// Add update metadata
			$update_data['updated_at'] = date('Y-m-d H:i:s');
			$update_data['updated_by'] = $this->session->userdata('user_id') ?? 1;

			// Update promo record
			$this->db->where('promo_id', $promo_id);
			$this->db->update($this->table, $update_data);

			// Handle product specific promos
			if (isset($data['product_ids'])) {
				// Remove existing product associations
				$this->db->where('promo_id', $promo_id);
				$this->db->delete($this->products_table);

				// Add new product associations
				if (is_array($data['product_ids']) && count($data['product_ids']) > 0) {
					foreach ($data['product_ids'] as $product_id) {
						$this->db->insert($this->products_table, [
							'promo_id' => $promo_id,
							'product_id' => $product_id
						]);
					}
				}
			}

			// Handle category specific promos
			if (isset($data['category_ids'])) {
				// Remove existing category associations
				$this->db->where('promo_id', $promo_id);
				$this->db->delete($this->categories_table);

				// Add new category associations
				if (is_array($data['category_ids']) && count($data['category_ids']) > 0) {
					foreach ($data['category_ids'] as $cat_id) {
						$this->db->insert($this->categories_table, [
							'promo_id' => $promo_id,
							'cat_id' => $cat_id
						]);
					}
				}
			}

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return [
					'success' => false,
					'message' => 'Terjadi kesalahan database'
				];
			} else {
				$this->db->trans_commit();
				return [
					'success' => true,
					'message' => 'Promo berhasil diperbarui'
				];
			}
		} catch (Exception $e) {
			$this->db->trans_rollback();
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}

	public function recordPromoUsage($promo_id, $order_id, $discount_amount)
	{
		$this->db->trans_begin();
		try {
			// Log untuk debugging
			log_message('debug', 'Recording promo usage - promo_id: ' . $promo_id . ', order_id: ' . $order_id . ', discount: ' . $discount_amount);

			// PERBAIKAN: Validasi parameter wajib
			if (empty($promo_id) || empty($order_id)) {
				throw new Exception('Invalid parameters: promo_id and order_id are required');
			}

			// PERBAIKAN: Pastikan diskon amount tidak minus
			$discount_amount = max(0, floatval($discount_amount));

			// Cek apakah penggunaan sudah pernah dicatat
			$this->db->where('promo_id', $promo_id);
			$this->db->where('order_id', $order_id);
			$existing_usage = $this->db->get($this->usage_table)->row_array();

			if ($existing_usage) {
				// Jika sudah ada, periksa apakah sudah dihitung dalam usage_count
				if ($existing_usage['counted_in_usage'] == 0) {
					// PERBAIKAN: Lebih baik gunakan query terpisah untuk update usage_count
					$this->db->set('usage_count', 'usage_count + 1', FALSE);
					$this->db->where('promo_id', $promo_id);
					$update_count = $this->db->update($this->table);
					if (!$update_count) {
						throw new Exception('Failed to update usage count');
					}

					// Update flag counted_in_usage
					$this->db->where('usage_id', $existing_usage['usage_id']);
					$update_flag = $this->db->update($this->usage_table, ['counted_in_usage' => 1]);
					if (!$update_flag) {
						throw new Exception('Failed to update counted_in_usage flag');
					}

					log_message('debug', 'Updated existing promo usage record and incremented usage count for promo_id: ' . $promo_id);
				} else {
					log_message('debug', 'Promo usage already counted for promo_id: ' . $promo_id . ', order_id: ' . $order_id);
				}
			} else {
				// PERBAIKAN: Tambahkan validasi promo sebelum insert
				$promo = $this->getPromoById($promo_id);
				if (!$promo) {
					throw new Exception('Promo not found with ID: ' . $promo_id);
				}

				// PERBAIKAN UTAMA: Periksa apakah discount_amount sudah diterapkan
				$orderInfo = $this->db->select('discount_amount')->where('id', $order_id)->get('orders')->row_array();
				if (!$orderInfo || !isset($orderInfo['discount_amount'])) {
					// Update order dengan discount amount
					$this->db->where('id', $order_id)
						->update('orders', [
							'discount_amount' => $discount_amount,
							'discount_code' => $promo['promo_code'] ?? null
						]);

					log_message('debug', 'Updated order with discount_amount: ' . $discount_amount);
				}

				// Insert usage record with counted_in_usage = 1
				$insert_data = [
					'promo_id' => $promo_id,
					'order_id' => $order_id,
					'discount_amount' => $discount_amount,
					'usage_time' => date('Y-m-d H:i:s'),
					'counted_in_usage' => 1 // Set flag to 1 directly
				];

				$insert_success = $this->db->insert($this->usage_table, $insert_data);
				if (!$insert_success) {
					throw new Exception('Failed to insert promo usage record');
				}

				// PERBAIKAN: Gunakan query terpisah untuk update usage_count
				$this->db->set('usage_count', 'usage_count + 1', FALSE);
				$this->db->where('promo_id', $promo_id);
				$update_count = $this->db->update($this->table);
				if (!$update_count) {
					throw new Exception('Failed to increment usage count');
				}

				log_message('debug', 'Inserted new promo usage record and incremented usage count for promo_id: ' . $promo_id);

				// PERBAIKAN: Increment quota untuk tracking
				if (isset($promo['quota']) && $promo['quota'] !== null) {
					$remaining = max(0, intval($promo['quota']) - intval($promo['usage_count']) - 1);
					log_message('debug', 'Remaining quota for promo: ' . $remaining);
				}
			}

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				log_message('error', 'Database error in recordPromoUsage: ' . json_encode($this->db->error()));
				return [
					'success' => false,
					'message' => 'Database error: ' . $this->db->error()['message']
				];
			} else {
				$this->db->trans_commit();
				return [
					'success' => true,
					'message' => 'Promo usage recorded successfully'
				];
			}
		} catch (Exception $e) {
			log_message('error', 'Error in recordPromoUsage: ' . $e->getMessage());
			$this->db->trans_rollback();
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}
	public function getPromoUsage($promo_id, $params = [])
	{
		$this->db->select('u.*, o.name as customer_name, o.table_id');
		$this->db->from($this->usage_table . ' AS u');
		$this->db->join('orders AS o', 'u.order_id = o.id', 'left');
		$this->db->where('u.promo_id', $promo_id);

		// Apply sorting
		if (!empty($params['sort_by'])) {
			$this->db->order_by($params['sort_by'], !empty($params['sort_dir']) ? $params['sort_dir'] : 'ASC');
		} else {
			$this->db->order_by('u.usage_time', 'DESC');
		}

		// Apply pagination
		if (isset($params['limit']) && isset($params['offset'])) {
			$this->db->limit($params['limit'], $params['offset']);
		}

		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Get promo usage count
	 * 
	 * @param int $promo_id Promo ID
	 * @return int Usage count
	 */
	public function getPromoUsageCount($promo_id)
	{
		$this->db->where('promo_id', $promo_id);
		return $this->db->count_all_results($this->usage_table);
	}

	/**
	 * Get promo usage statistics
	 * 
	 * @param int $promo_id Promo ID
	 * @return array Usage statistics
	 */
	public function getPromoUsageStats($promo_id)
	{
		$this->db->select('SUM(discount_amount) as total_discount, AVG(discount_amount) as avg_discount');
		$this->db->from($this->usage_table);
		$this->db->where('promo_id', $promo_id);
		$query = $this->db->get();
		$result = $query->row_array();

		return [
			'total_discount' => $result['total_discount'] ?? 0,
			'avg_discount' => $result['avg_discount'] ?? 0
		];
	}

	/**
	 * Get associated products for a promo
	 * 
	 * @param int $promo_id Promo ID
	 * @return array Product IDs
	 */
	public function getPromoProducts($promo_id)
	{
		$this->db->select('pp.product_id, dp.product_name, COALESCE(dp.product_brand, "tidak diketahui") as product_brand');
		$this->db->from($this->products_table . ' AS pp');
		$this->db->join('data_product AS dp', 'pp.product_id = dp.product_id', 'left');
		$this->db->where('pp.promo_id', $promo_id);
		$query = $this->db->get();

		return $query->result_array();
	}

	public function getPromoCategories($promo_id)
	{
		$this->db->select('pc.cat_id, dc.cat_name, COALESCE(dc.cat_brand, "tidak diketahui") as cat_brand');
		$this->db->from($this->categories_table . ' AS pc');
		$this->db->join('data_categories AS dc', 'pc.cat_id = dc.cat_id', 'left');
		$this->db->where('pc.promo_id', $promo_id);
		$query = $this->db->get();

		return $query->result_array();
	}

	/**
	 * Get product details by ID
	 * 
	 * @param int $product_id Product ID
	 * @return array Product details
	 */
	private function get_product_detail($product_id)
	{
		$this->db->select('product_id, product_name, product_price, product_brand, product_pict');
		$this->db->from('data_product');
		$this->db->where('product_id', $product_id);
		$product = $this->db->get()->row_array();
		return $product ? $product : [
			'product_id' => $product_id,
			'product_name' => 'Unknown Product',
			'product_price' => 0,
			'product_brand' => '',
			'product_pict' => 'default.png'
		];
	}

	public function validatePromo($promo_code, $brand, $order_total = 0, $cart_products = [], $calculate_eligible_total = true)
	{
		// Log input parameters for debugging
		log_message('debug', 'validatePromo called with params: ' . json_encode([
			'promo_code' => $promo_code,
			'brand' => $brand,
			'order_total' => $order_total,
			'cart_products_count' => count($cart_products),
			'calculate_eligible_total' => $calculate_eligible_total
		]));

		// Get promo details
		$promo = $this->getPromoByCode($promo_code);
		if (!$promo) {
			return [
				'valid' => false,
				'message' => 'Kode promo tidak ditemukan',
				'success' => false
			];
		}

		if ($promo['is_mrp_voucher'] == 1) {
			log_message('debug', 'Detected MRP voucher, redirecting to validateMRPVoucher');
			return $this->validateMRPVoucher($promo_code, $brand, $order_total);
		}	

		// Basic validation checks
		if ($promo['promo_status'] !== 'active') {
			return [
				'valid' => false,
				'message' => 'Kode promo tidak aktif',
				'success' => false
			];
		}

		if ($promo['promo_brand'] !== $brand) {
			return [
				'valid' => false,
				'message' => 'Kode promo tidak berlaku untuk brand ini',
				'success' => false
			];
		}

		$now = date('Y-m-d H:i:s');
		if ($now < $promo['start_date']) {
			return [
				'valid' => false,
				'message' => 'Promo belum dimulai (mulai ' . date('d M Y H:i', strtotime($promo['start_date'])) . ')',
				'success' => false
			];
		}

		if ($now > $promo['end_date']) {
			return [
				'valid' => false,
				'message' => 'Promo sudah berakhir (berakhir ' . date('d M Y H:i', strtotime($promo['end_date'])) . ')',
				'success' => false
			];
		}

		if ($promo['quota'] !== null && $promo['usage_count'] >= $promo['quota']) {
			return [
				'valid' => false,
				'message' => 'Kuota promo sudah habis',
				'success' => false
			];
		}

		// BUGFIX: Inisialisasi eligible_total dengan 0 dari awal
		$eligible_total = 0;
		$product_specific = false;
		$eligible_product_ids = [];
		$eligible_category_ids = [];
		$eligible_products_in_cart = [];

		// Get promo products if any
		$promo_products = $this->getPromoProducts($promo['promo_id']);
		if (!empty($promo_products)) {
			$product_specific = true;
			$eligible_product_ids = array_column($promo_products, 'product_id');
			log_message('debug', 'Promo has product restrictions: ' . json_encode($eligible_product_ids));
		}

		// Get promo categories if any
		$promo_categories = $this->getPromoCategories($promo['promo_id']);
		if (!empty($promo_categories)) {
			$product_specific = true;
			$eligible_category_ids = array_column($promo_categories, 'cat_id');
			log_message('debug', 'Promo has category restrictions: ' . json_encode($eligible_category_ids));
		}

		// PERBAIKAN: Khusus untuk promo bundling dan BOGO, periksa kelayakan dengan metode khusus
		if ($promo['promo_type'] === 'bundling') {
			// Cek syarat promo bundling dari cart_products
			$bundleEligibility = $this->checkBundleEligibility($promo['promo_id'], $cart_products);
			if (!$bundleEligibility['eligible']) {
				return [
					'valid' => false,
					'message' => $bundleEligibility['message'],
					'success' => false,
					'promo_type' => 'bundling'
				];
			}

			return [
				'valid' => true,
				'promo' => $promo,
				'discount_amount' => 0,
				'final_amount' => $order_total,
				'success' => true,
				'message' => 'Promo berhasil diterapkan. Produk gratis akan ditambahkan ke keranjang Anda.',
				'promo_code' => $promo_code,
				'original_amount' => $order_total,
				'eligible_amount' => $order_total,
				'discount_label' => 'Bundle Deal',
				'promo_type' => 'bundling',
				'product_specific' => $product_specific,
				'promo_products' => $eligible_product_ids,
				'promo_categories' => $eligible_category_ids,
				'bundles' => $bundleEligibility['bundles']
			];
		}

		if ($promo['promo_type'] === 'bogo') {
			// Cek syarat promo BOGO dari cart_products
			$bogoEligibility = $this->checkBogoEligibility($promo['promo_id'], $cart_products);
			if (!$bogoEligibility['eligible']) {
				return [
					'valid' => false,
					'message' => $bogoEligibility['message'],
					'success' => false,
					'promo_type' => 'bogo'
				];
			}

			return [
				'valid' => true,
				'promo' => $promo,
				'discount_amount' => 0, // BOGO tidak mengurangi subtotal, hanya menambahkan produk gratis
				'final_amount' => $order_total, // Total tetap sama, tidak dikurangi diskon
				'success' => true,
				'message' => 'Promo berhasil diterapkan. Produk gratis akan ditambahkan ke keranjang Anda.',
				'promo_code' => $promo_code,
				'original_amount' => $order_total,
				'eligible_amount' => $order_total,
				'discount_label' => 'Buy One Get One',
				'promo_type' => 'bogo',
				'product_specific' => $product_specific,
				'promo_products' => $eligible_product_ids,
				'promo_categories' => $eligible_category_ids,
				'bogos' => $bogoEligibility['bogos']
			];
		}

		// BUGFIX: Jika BUKAN promo spesifik produk/kategori, gunakan total order
		if (!$product_specific) {
			$eligible_total = $order_total;
			log_message('debug', 'Not a product/category specific promo, eligible_total = order_total: ' . $eligible_total);
		}
		// BUGFIX: Jika promo spesifik produk/kategori dan ada cart data, hitung total eligible
		else if (!empty($cart_products)) {
			log_message('debug', 'Calculating eligible total for product/category specific promo');
			$found_eligible_products = false;

			// Get detailed cart items with prices
			$detailed_cart = [];
			if (is_array($cart_products)) {
				// If cart_products is already an array of objects with product_id, price, quantity
				if (isset($cart_products[0]) && is_array($cart_products[0]) && isset($cart_products[0]['product_id'])) {
					$detailed_cart = $cart_products;
					log_message('debug', 'Using detailed cart information provided');
				}
				// If cart_products is just an array of product IDs, get details from database
				else {
					log_message('debug', 'Getting product details from database for IDs');
					$this->db->select('dp.product_id, dp.product_price, dp.cat_id');
					$this->db->from('data_product dp');
					$this->db->where_in('dp.product_id', $cart_products);
					$product_details = $this->db->get()->result_array();

					// Convert to format with quantity = 1 (since we don't know actual quantities)
					foreach ($product_details as $product) {
						$detailed_cart[] = [
							'product_id' => $product['product_id'],
							'price' => $product['product_price'],
							'quantity' => 1,
							'cat_id' => $product['cat_id']
						];
					}
				}
			}

			log_message('debug', 'Detailed cart for eligibility calculation: ' . json_encode($detailed_cart));

			// Calculate eligible total based on product and category restrictions
			foreach ($detailed_cart as $item) {
				$product_id = $item['product_id'];
				$price = isset($item['price']) ? floatval($item['price']) : 0;
				$quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
				$subtotal = $price * $quantity;
				$cat_id = isset($item['cat_id']) ? $item['cat_id'] : null;

				// If we don't have cat_id in the cart item, try to get it from database
				if ($cat_id === null) {
					$this->db->select('cat_id');
					$this->db->from('data_product');
					$this->db->where('product_id', $product_id);
					$product = $this->db->get()->row_array();
					$cat_id = $product ? $product['cat_id'] : null;
				}

				$is_eligible = false;

				// Check if product is directly eligible
				if (in_array($product_id, $eligible_product_ids)) {
					$is_eligible = true;
					log_message('debug', "Product ID $product_id is directly eligible for promo");
				}
				// Check if product category is eligible
				elseif (!empty($cat_id) && in_array($cat_id, $eligible_category_ids)) {
					$is_eligible = true;
					log_message('debug', "Product ID $product_id in category $cat_id is eligible for promo");
				}

				if ($is_eligible) {
					$eligible_total += $subtotal;
					$found_eligible_products = true;
					$eligible_products_in_cart[] = [
						'product_id' => $product_id,
						'subtotal' => $subtotal
					];
					log_message('debug', "Added eligible product: ID=$product_id, price=$price, qty=$quantity, subtotal=$subtotal");
					log_message('debug', "Current eligible total: $eligible_total");
				}
			}

			if (!$found_eligible_products) {
				return [
					'valid' => false,
					'message' => 'Promo hanya berlaku untuk produk atau kategori tertentu',
					'success' => false,
					'product_specific' => true,
					'promo_products' => $eligible_product_ids,
					'promo_categories' => $eligible_category_ids
				];
			}
		}
		// BUGFIX: Jika tidak ada cart_products tapi ini adalah promo spesifik, gunakan order_total dengan warning
		else if ($product_specific) {
			log_message('warning', 'Product/category specific promo but no cart details provided, using order_total as fallback');
			$eligible_total = $order_total;
		}

		log_message('debug', "Final eligible total: $eligible_total (original order total: $order_total)");

		// Check minimum order value against eligible total
		if ($eligible_total < $promo['minimum_order']) {
			return [
				'valid' => false,
				'message' => 'Total pembelian produk yang memenuhi syarat tidak mencukupi minimum order Rp ' . number_format($promo['minimum_order'], 0, ',', '.'),
				'success' => false,
				'minimum_order' => $promo['minimum_order'],
				'promo_code' => $promo_code,
				'original_amount' => $order_total,
				'eligible_amount' => $eligible_total,
				'discount_amount' => 0,
				'final_amount' => $order_total
			];
		}

		// Calculate discount based on eligible total for product/category specific promos
		$discount_amount = 0;
		$discount_label = '';

		// For percentage discount
		if ($promo['promo_type'] === 'percentage') {
			$discount_amount = $eligible_total * ($promo['promo_value'] / 100);
			$discount_label = $promo['promo_value'] . '%';

			// Apply maximum discount if set
			if ($promo['maximum_discount'] !== null && $discount_amount > $promo['maximum_discount']) {
				$discount_amount = $promo['maximum_discount'];
				$discount_label .= ' (max Rp ' . number_format($promo['maximum_discount'], 0, ',', '.') . ')';
			}
		}
		// For nominal discount
		else {
			$discount_amount = $promo['promo_value'];
			$discount_label = 'Rp ' . number_format($promo['promo_value'], 0, ',', '.');

			// PERBAIKAN: Pastikan diskon tidak melebihi eligible_total
			if ($discount_amount > $eligible_total) {
				$discount_amount = $eligible_total;
			}
		}

		// BUGFIX: Final calculation with proper logging
		log_message('debug', "Final calculation - Discount: $discount_amount, Eligible: $eligible_total, Original: $order_total");

		// Prepare final response
		return [
			'valid' => true,
			'promo' => $promo,
			'discount_amount' => $discount_amount,
			'final_amount' => $order_total - $discount_amount,
			'success' => true,
			'message' => 'Promo berhasil diterapkan',
			'promo_code' => $promo_code,
			'original_amount' => $order_total,
			'eligible_amount' => $eligible_total,
			'discount_label' => $discount_label,
			'promo_type' => $promo['promo_type'],
			'product_specific' => $product_specific,
			'promo_products' => $eligible_product_ids,
			'promo_categories' => $eligible_category_ids,
			'eligible_products' => $eligible_products_in_cart
		];
	}

	public function applyPromoToOrder($orderId, $promo_code, $brand, $order_total, $cart_details = [])
	{
		$this->db->trans_begin();

		try {
			// Log debugging information
			log_message('debug', 'applyPromoToOrder - Parameters: ' . json_encode([
				'order_id' => $orderId,
				'promo_code' => $promo_code,
				'brand' => $brand,
				'order_total' => $order_total,
				'cart_details_count' => count($cart_details)
			]));

			// Check if order exists
			$this->db->where('id', $orderId);
			$this->db->where('deleted_at IS NULL');
			$order = $this->db->get('orders')->row_array();

			if (!$order) {
				return [
					'success' => false,
					'message' => 'Order tidak ditemukan'
				];
			}

			// Check if order already has a promo applied
			$this->db->where('order_id', $orderId);
			$existing_usage = $this->db->get($this->usage_table)->row();

			if ($existing_usage) {
				// PERBAIKAN: Check if this is same promo
				if ($existing_usage->promo_id) {
					$existing_promo = $this->getPromoById($existing_usage->promo_id);

					if ($existing_promo && $existing_promo['promo_code'] === $promo_code) {
						log_message('info', 'Order already using the same promo: ' . $promo_code);

						// Update order dengan discount_code dan discount_amount yang sama
						$this->db->where('id', $orderId);
						$this->db->update('orders', [
							'discount_code' => $promo_code,
							'discount_amount' => $existing_usage->discount_amount,
							'updated_at' => date('Y-m-d H:i:s')
						]);

						return [
							'success' => true,
							'message' => 'Promo sudah diterapkan sebelumnya',
							'discount_amount' => $existing_usage->discount_amount,
							'final_amount' => $order_total - $existing_usage->discount_amount,
							'promo_type' => $existing_promo['promo_type'],
							'data' => ['promo' => $existing_promo]
						];
					}

					// Remove previous promo if this is a different one
					log_message('warning', 'Order already using different promo, will be replaced with new promo');
					$this->db->where('order_id', $orderId);
					$this->db->delete($this->usage_table);
				}
			}

			// Get cart items for product/category specific validation
			$this->db->select('od.id, od.product_id, od.quantity, od.unit_price, od.subtotal, od.parent_id, dp.cat_id, dp.product_price as original_db_price')
				->from('order_details od')
				->join('data_product dp', 'od.product_id = dp.product_id', 'left')
				->where('od.order_id', $orderId)
				->where('od.deleted_at IS NULL')
				->where('od.parent_id IS NULL'); // Exclude package child items for calculation

			$cart_items = $this->db->get()->result_array();
			log_message('debug', 'Retrieved ' . count($cart_items) . ' cart items for promo validation');

			// Format cart items with complete information for more accurate validation
			$detailed_cart = [];

			foreach ($cart_items as $item) {
				// Skip package child items
				if (!empty($item['parent_id'])) continue;

				$detailed_cart[] = [
					'product_id' => $item['product_id'],
					'price' => floatval($item['unit_price'] ?? $item['price'] ?? 0),
					'quantity' => intval($item['quantity']),
					'subtotal' => floatval($item['subtotal'] ?? ($item['unit_price'] * $item['quantity'])),
					'cat_id' => $item['cat_id'],
					'original_db_price' => floatval($item['original_db_price'] ?? 0) // Add database price
				];
			}

			log_message('debug', 'Formatted ' . count($detailed_cart) . ' cart items for promo validation');

			// Validate promo with detailed cart information
			$validation = $this->validatePromo(
				$promo_code,
				$brand,
				$order_total,
				!empty($cart_details) ? $cart_details : $detailed_cart,
				true // Calculate eligible total
			);

			log_message('debug', 'Promo validation result: ' . json_encode($validation));

			if (!$validation['valid']) {
				return [
					'success' => false,
					'message' => $validation['message']
				];
			}

			$promo = $validation['promo'];
			$promo_id = $promo['promo_id'];
			$bundle_result = null;
			$bogo_result = null;

			// PERBAIKAN: Handle specific promo types properly
			if ($promo['promo_type'] === 'bundling') {
				// Format cart items for bundle eligibility check
				$formatted_cart = [];

				foreach ($cart_items as $item) {
					// Skip package child items
					if (!empty($item['parent_id'])) continue;

					$formatted_cart[] = [
						'product_id' => $item['product_id'],
						'quantity' => $item['quantity']
					];
				}

				// Check bundle eligibility
				$bundle_result = $this->checkBundleEligibility($promo_id, $formatted_cart);

				if (!$bundle_result['eligible']) {
					return [
						'success' => false,
						'message' => $bundle_result['message']
					];
				}

				// Apply bundle promo by adding free products
				$bundle_apply_result = $this->applyBundlePromoToOrder($orderId, $bundle_result['bundles']);

				if (!$bundle_apply_result['success']) {
					$this->db->trans_rollback();
					return $bundle_apply_result;
				}

				// PERBAIKAN: Calculate total discount from bundle promo for order
				$total_discount = 0;

				if (isset($bundle_apply_result['items']) && is_array($bundle_apply_result['items'])) {
					foreach ($bundle_apply_result['items'] as $item) {
						$total_discount += floatval($item['discount_value'] ?? 0);
					}
				}

				// Update discount_amount in order with total value of free products
				$this->db->where('id', $orderId);
				$this->db->update('orders', [
					'discount_amount' => $total_discount,
					'discount_code' => $promo_code
				]);

				log_message('debug', 'Bundle promo applied with total discount: ' . $total_discount);
			} else if ($promo['promo_type'] === 'bogo') {
				// Format cart items for BOGO eligibility check
				$formatted_cart = [];

				foreach ($cart_items as $item) {
					// Skip package child items
					if (!empty($item['parent_id'])) continue;

					$formatted_cart[] = [
						'product_id' => $item['product_id'],
						'quantity' => $item['quantity']
					];
				}

				// Check BOGO eligibility
				$bogo_result = $this->checkBogoEligibility($promo_id, $formatted_cart);

				if (!$bogo_result['eligible']) {
					return [
						'success' => false,
						'message' => $bogo_result['message']
					];
				}

				// Apply BOGO promo by adding free products
				$bogo_apply_result = $this->applyBogoPromoToOrder($orderId, $bogo_result['bogos']);

				if (!$bogo_apply_result['success']) {
					$this->db->trans_rollback();
					return $bogo_apply_result;
				}

				// PERBAIKAN: Calculate total discount from BOGO promo for order
				$total_discount = 0;

				if (isset($bogo_apply_result['items']) && is_array($bogo_apply_result['items'])) {
					foreach ($bogo_apply_result['items'] as $item) {
						$total_discount += floatval($item['discount_value'] ?? 0);
					}
				}

				// Update discount_amount in order with total value of free products
				$this->db->where('id', $orderId);
				$this->db->update('orders', [
					'discount_amount' => $total_discount,
					'discount_code' => $promo_code
				]);

				log_message('debug', 'BOGO promo applied with total discount: ' . $total_discount);
			} else {
				// PERBAIKAN KRITIS: Record promo usage for percentage/nominal promos
				$usage_result = $this->recordPromoUsage(
					$validation['promo']['promo_id'],
					$orderId,
					$validation['discount_amount']
				);

				if (!$usage_result['success']) {
					log_message('error', 'Failed to record promo usage: ' . $usage_result['message']);
					// Continue process despite failing to record usage, but log warning
					log_message('warning', 'Continuing despite failed promo usage recording');
				} else {
					log_message('debug', 'Successfully recorded promo usage for promo_id: ' . $promo_id);
				}

				// Update order with discount
				$update_result = $this->db->where('id', $orderId)
					->update('orders', [
						'discount_amount' => $validation['discount_amount'],
						'discount_code' => $promo_code,
						'updated_at' => date('Y-m-d H:i:s')
					]);

				if (!$update_result) {
					$this->db->trans_rollback();
					return [
						'success' => false,
						'message' => 'Failed to update order with discount: ' . $this->db->error()['message']
					];
				}

				log_message('debug', 'Updated order with discount_amount: ' . $validation['discount_amount'] . ' and promo_code: ' . $promo_code);

				// PERBAIKAN UTAMA: Update is_promo_item, promo_type, and original_price flags on order_details for percentage/nominal promos
				foreach ($cart_items as $item) {
					// Skip package child items
					if (!empty($item['parent_id'])) continue;

					// Get original price from database if not already set
					$original_price = floatval($item['original_db_price'] ?? $item['unit_price']);

					// Update promo flag on item and original_price
					$this->db->where('id', $item['id'])
						->update('order_details', [
							'is_promo_item' => 1, // Set flag that this item has promo
							'promo_type' => $promo['promo_type'], // Set promo type (percentage/nominal)
							'original_price' => $original_price, // PERBAIKAN: Set original price
							'updated_at' => date('Y-m-d H:i:s')
						]);

					log_message('debug', 'Updated order_detail ID: ' . $item['id'] . ' with promo_type: ' . $promo['promo_type'] . ' and original_price: ' . $original_price);
				}
			}

			// PERBAIKAN: Explicitly check transaction status
			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				log_message('error', 'Transaction failed in applyPromoToOrder: ' . json_encode($this->db->error()));
				return [
					'success' => false,
					'message' => 'Terjadi kesalahan database'
				];
			} else {
				$this->db->trans_commit();

				// Prepare response data
				$response = [
					'success' => true,
					'message' => 'Promo berhasil diterapkan',
					'discount_amount' => $validation['discount_amount'],
					'final_amount' => $validation['final_amount'],
					'promo_type' => $promo['promo_type'],
					'data' => [
						'promo' => $promo, // Include all promo data
						'bundles' => $promo['promo_type'] === 'bundling' ? $bundle_result['bundles'] ?? [] : [],
						'bogos' => $promo['promo_type'] === 'bogo' ? $bogo_result['bogos'] ?? [] : [],
						'product_specific' => $validation['product_specific'] ?? false,
						'promo_products' => $validation['promo_products'] ?? [],
						'promo_categories' => $validation['promo_categories'] ?? [],
						'eligible_amount' => $validation['eligible_amount'] ?? $order_total,
						'eligible_products' => $validation['eligible_products'] ?? []
					]
				];

				// Add bundle items to response if available
				if ($promo['promo_type'] === 'bundling' && isset($bundle_apply_result['items'])) {
					$response['data']['free_items'] = $bundle_apply_result['items'];
				}

				// Add BOGO items to response if available
				if ($promo['promo_type'] === 'bogo' && isset($bogo_apply_result['items'])) {
					$response['data']['free_items'] = $bogo_apply_result['items'];
				}

				return $response;
			}
		} catch (Exception $e) {
			log_message('error', 'Error in applyPromoToOrder: ' . $e->getMessage());
			$this->db->trans_rollback();
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}
	public function calculateDiscount($promo_code, $order_total, $brand)
	{
		return $this->validatePromo($promo_code, $brand, $order_total);
	}

	/**
	 * Check if products in cart are eligible for promo
	 * 
	 * @param int $promo_id Promo ID
	 * @param array $cart_products Array of product IDs in cart
	 * @return bool True if eligible, false otherwise
	 */
	public function checkProductEligibility($promo_id, $cart_products)
	{
		// Get promo products
		$promo_products = $this->getPromoProducts($promo_id);

		if (empty($promo_products)) {
			// No specific products set, so all products are eligible
			return true;
		}

		// Extract just the product IDs
		$eligible_product_ids = array_column($promo_products, 'product_id');

		// Check if any product in cart is eligible
		foreach ($cart_products as $product_id) {
			if (in_array($product_id, $eligible_product_ids)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if products in cart are in eligible categories for promo
	 * 
	 * @param int $promo_id Promo ID
	 * @param array $cart_categories Array of category IDs for products in cart
	 * @return bool True if eligible, false otherwise
	 */
	public function checkCategoryEligibility($promo_id, $cart_categories)
	{
		// Get promo categories
		$promo_categories = $this->getPromoCategories($promo_id);

		if (empty($promo_categories)) {
			// No specific categories set, so all categories are eligible
			return true;
		}

		// Extract just the category IDs
		$eligible_category_ids = array_column($promo_categories, 'cat_id');

		// Check if any category in cart is eligible
		foreach ($cart_categories as $category_id) {
			if (in_array($category_id, $eligible_category_ids)) {
				return true;
			}
		}

		return false;
	}

	public function deletePromo($promo_id)
	{
		$this->db->trans_begin();

		try {
			// Check if promo exists
			$existing = $this->getPromoById($promo_id);
			if (!$existing) {
				return [
					'success' => false,
					'message' => 'Promo tidak ditemukan'
				];
			}

			// Soft delete promo
			$this->db->where('promo_id', $promo_id);
			$this->db->update($this->table, [
				'deleted_at' => date('Y-m-d H:i:s'),
				'promo_status' => 'inactive'
			]);

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return [
					'success' => false,
					'message' => 'Terjadi kesalahan database'
				];
			} else {
				$this->db->trans_commit();
				return [
					'success' => true,
					'message' => 'Promo berhasil dihapus'
				];
			}
		} catch (Exception $e) {
			$this->db->trans_rollback();
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}

	/**
	 * Menambahkan promo bundling (beli produk A dan B, gratis produk E)
	 * 
	 * @param int $promo_id ID Promo
	 * @param array $bundle_data Data bundle promo
	 * @return array Status operasi
	 */
	public function addPromoBundles($promo_id, $bundle_data)
	{
		$this->db->trans_begin();

		try {
			// Hapus bundle yang ada untuk promo ini (jika update)
			$this->db->where('promo_id', $promo_id);
			$this->db->delete($this->bundle_table);

			// Insert data bundle baru
			if (!empty($bundle_data) && is_array($bundle_data)) {
				foreach ($bundle_data as $bundle) {
					$insert_data = [
						'promo_id' => $promo_id,
						'required_product_id1' => $bundle['required_product_id1'],
						'required_product_id2' => $bundle['required_product_id2'],
						'free_product_id' => $bundle['free_product_id'],
						'min_quantity1' => $bundle['min_quantity1'] ?? 1,
						'min_quantity2' => $bundle['min_quantity2'] ?? 1,
						'free_quantity' => $bundle['free_quantity'] ?? 1,
						'created_at' => date('Y-m-d H:i:s')
					];

					$this->db->insert($this->bundle_table, $insert_data);
				}
			}

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return [
					'success' => false,
					'message' => 'Terjadi kesalahan saat menyimpan bundle promo'
				];
			} else {
				$this->db->trans_commit();
				return [
					'success' => true,
					'message' => 'Bundle promo berhasil disimpan'
				];
			}
		} catch (Exception $e) {
			$this->db->trans_rollback();
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}
	public function getPromoBundles($promo_id)
	{
		$this->db->select('pb.*, 
                      p1.product_name as required_product_name1, 
                      p1.product_brand as required_product_brand1,
                      p2.product_name as required_product_name2, 
                      p2.product_brand as required_product_brand2,
                      pf.product_name as free_product_name, 
                      pf.product_brand as free_product_brand');
		$this->db->from($this->bundle_table . ' as pb');
		$this->db->join('data_product as p1', 'pb.required_product_id1 = p1.product_id', 'left');
		$this->db->join('data_product as p2', 'pb.required_product_id2 = p2.product_id', 'left');
		$this->db->join('data_product as pf', 'pb.free_product_id = pf.product_id', 'left');
		$this->db->where('pb.promo_id', $promo_id);

		$query = $this->db->get();
		return $query->result_array();
	}

	public function checkBundleEligibility($promo_id, $cart_items)
	{
		// Log debugging
		log_message('debug', 'Checking bundle eligibility for promo ID: ' . $promo_id);
		log_message('debug', 'Cart items: ' . json_encode($cart_items));

		// Ambil semua bundle yang tersedia untuk promo ini
		$bundles = $this->getPromoBundles($promo_id);
		if (empty($bundles)) {
			log_message('debug', 'No bundles available for this promo');
			return [
				'eligible' => false,
				'message' => 'Tidak ada bundle yang tersedia untuk promo ini'
			];
		}

		log_message('debug', 'Found ' . count($bundles) . ' bundles for this promo');

		// Buat struktur data sederhana untuk keranjang
		$cart_products = [];
		foreach ($cart_items as $item) {
			// Pastikan item memiliki product_id dan quantity
			if (isset($item['product_id']) && isset($item['quantity'])) {
				$product_id = $item['product_id'];
				$quantity = $item['quantity'];
				if (isset($cart_products[$product_id])) {
					$cart_products[$product_id] += $quantity;
				} else {
					$cart_products[$product_id] = $quantity;
				}
			}
			// Jika item adalah array sederhana (product_id saja)
			else if (!is_array($item)) {
				$product_id = $item;
				if (isset($cart_products[$product_id])) {
					$cart_products[$product_id]++;
				} else {
					$cart_products[$product_id] = 1;
				}
			}
		}

		log_message('debug', 'Processed cart products: ' . json_encode($cart_products));

		// Cek setiap bundle
		$eligible_bundles = [];

		foreach ($bundles as $bundle) {
			$required_product1 = $bundle['required_product_id1'];
			$required_product2 = $bundle['required_product_id2'];
			$min_quantity1 = max(1, intval($bundle['min_quantity1']));
			$min_quantity2 = max(1, intval($bundle['min_quantity2']));
			$free_product_id = $bundle['free_product_id'];
			$free_quantity = max(1, intval($bundle['free_quantity']));

			log_message('debug', 'Checking bundle: ' . json_encode([
				'required_product1' => $required_product1,
				'required_product2' => $required_product2,
				'min_quantity1' => $min_quantity1,
				'min_quantity2' => $min_quantity2,
				'free_product_id' => $free_product_id,
				'free_quantity' => $free_quantity
			]));

			// PERBAIKAN: Buat array produk yang dibutuhkan untuk menghandle kasus produk yang sama
			$required_products = [];
			$product1Quantity = isset($cart_products[$required_product1]) ? $cart_products[$required_product1] : 0;
			$product2Quantity = isset($cart_products[$required_product2]) ? $cart_products[$required_product2] : 0;

			// PERBAIKAN: Kasus spesial - jika product1 dan product2 adalah produk yang sama
			if ($required_product1 === $required_product2) {
				// Jika produk yang sama, pastikan kuantitasnya cukup untuk kedua syarat
				$totalRequired = $min_quantity1 + $min_quantity2;
				if ($product1Quantity >= $totalRequired) {
					$bundle_eligible = true;
				} else {
					$bundle_eligible = false;
				}
			} else {
				// Jika produk berbeda, verifikasi masing-masing kuantitas
				$bundle_eligible = ($product1Quantity >= $min_quantity1) && ($product2Quantity >= $min_quantity2);
			}

			if ($bundle_eligible) {
				log_message('debug', 'Bundle eligible!');

				// PERBAIKAN: Hitung berapa kali bundle bisa diterapkan
				// Untuk bundle, biasanya 1 kali per kombinasi produk
				$apply_count = 1;

				// Namun, kita bisa menghitung berapa kali bundle bisa diterapkan berdasarkan kuantitas di cart
				if ($required_product1 === $required_product2) {
					// Kasus produk sama
					$apply_count = floor($product1Quantity / $totalRequired);
				} else {
					// Kasus produk berbeda
					$apply_count1 = floor($product1Quantity / $min_quantity1);
					$apply_count2 = floor($product2Quantity / $min_quantity2);
					$apply_count = min($apply_count1, $apply_count2);
				}

				// Batasi apply_count maksimal menjadi 1 jika tidak disebutkan lain
				// Ini adalah batasan default untuk bundle promo
				if (!isset($bundle['max_apply_count']) || $bundle['max_apply_count'] === null) {
					$apply_count = min($apply_count, 1);
				} else {
					$apply_count = min($apply_count, intval($bundle['max_apply_count']));
				}

				// Total produk gratis yang didapatkan
				$total_free_quantity = $apply_count * $free_quantity;

				// Ambil detail produk untuk tampilan lebih informatif
				$product1_details = $this->get_product_detail($required_product1);
				$product2_details = $this->get_product_detail($required_product2);
				$free_product_details = $this->get_product_detail($free_product_id);

				// Bundle ini memenuhi syarat
				$eligible_bundles[] = [
					'bundle' => $bundle,
					'free_product_id' => $free_product_id,
					'free_quantity' => $free_quantity * $apply_count, // Jumlah yang diberikan
					'free_product_name' => $bundle['free_product_name'] ?? $free_product_details['product_name'] ?? 'Produk Gratis',
					'apply_count' => $apply_count,
					'product_details' => [
						'product1' => [
							'name' => $product1_details['product_name'] ?? $bundle['required_product_name1'] ?? 'Produk 1',
							'quantity' => $product1Quantity,
							'required' => $min_quantity1
						],
						'product2' => [
							'name' => $product2_details['product_name'] ?? $bundle['required_product_name2'] ?? 'Produk 2',
							'quantity' => $product2Quantity,
							'required' => $min_quantity2
						],
						'free_product' => [
							'name' => $free_product_details['product_name'] ?? $bundle['free_product_name'] ?? 'Produk Gratis',
							'quantity' => $total_free_quantity
						]
					]
				];

				log_message('debug', "Bundle will be applied $apply_count times with $total_free_quantity free items");
			} else {
				log_message('debug', 'Bundle not eligible. Product 1 quantity: ' . $product1Quantity . ', Product 2 quantity: ' . $product2Quantity);
			}
		}

		if (empty($eligible_bundles)) {
			log_message('debug', 'No eligible bundles found');
			return [
				'eligible' => false,
				'message' => 'Tidak ada bundle yang memenuhi syarat. Pastikan Anda telah menambahkan produk yang diperlukan ke keranjang.'
			];
		}

		log_message('debug', 'Found ' . count($eligible_bundles) . ' eligible bundles');
		return [
			'eligible' => true,
			'bundles' => $eligible_bundles,
			'message' => 'Keranjang memenuhi syarat untuk bundling promo'
		];
	}

	public function applyBundlePromoToOrder($order_id, $eligible_bundles)
	{
		$this->db->trans_begin();
		try {
			$addedItems = [];
			$totalDiscount = 0;

			foreach ($eligible_bundles as $bundle) {
				// PERBAIKAN: Ambil harga asli produk gratis untuk disimpan sebagai nilai diskon
				$freeProductPrice = 0;
				$product = $this->db
					->select('product_price, product_name, product_pict')
					->from('data_product')
					->where('product_id', $bundle['free_product_id'])
					->get()
					->row_array();

				if (!$product) {
					log_message('warning', 'Free product not found: ' . $bundle['free_product_id']);
					continue;
				}

				// Ambil detail dari bundle
				$freeProductPrice = floatval($product['product_price']);
				$freeQuantity = intval($bundle['free_quantity']);

				// PERBAIKAN: Ambil informasi produk yang dibeli untuk referensi
				$product1Details = $bundle['product_details']['product1'];
				$product2Details = $bundle['product_details']['product2'];

				// PERBAIKAN: Hitung nilai total diskon (harga produk * quantity gratis)
				$bundleDiscount = $freeProductPrice * $freeQuantity;
				$totalDiscount += $bundleDiscount;

				// PERBAIKAN: Log detail bundle
				log_message('debug', 'Bundle details: ' . json_encode([
					'product1_id' => bundle['required_product_id1'] ?? '-',
					'product1_name' => $product1Details['name'],
					'product1_quantity' => $product1Details['required'],
					'product2_id' => bundle['required_product_id2'] ?? '-',
					'product2_name' => $product2Details['name'],
					'product2_quantity' => $product2Details['required'],
					'free_product_id' => $bundle['free_product_id'],
					'free_quantity' => $freeQuantity,
					'free_product_price' => $freeProductPrice
				]));

				// Tambahkan produk gratis ke order dengan flag promo
				$free_product_data = [
					'order_id' => $order_id,
					'product_id' => $bundle['free_product_id'],
					'quantity' => $freeQuantity,
					'unit_price' => 0.00, // Gratis
					'subtotal' => 0.00,
					'notes' => 'Produk bundling gratis - ' . $bundle['free_product_name'],
					'created_at' => date('Y-m-d H:i:s'),
					'is_promo_item' => 1,  // Tambahkan penanda item promo
					'promo_type' => 'bundling',  // Tambahkan tipe promo
					'original_price' => $freeProductPrice // PERBAIKAN: Simpan harga asli
				];

				$this->db->insert('order_details', $free_product_data);
				$insertId = $this->db->insert_id();

				$addedItems[] = [
					'id' => $insertId,
					'product_id' => $bundle['free_product_id'],
					'product_name' => $product['product_name'] ?? $bundle['free_product_name'],
					'product_pict' => $product['product_pict'] ?? null,
					'quantity' => $freeQuantity,
					'price' => 0,
					'subtotal' => 0,
					'original_price' => $freeProductPrice,
					'discount_value' => $bundleDiscount,
					'is_promo_item' => 1,
					'promo_type' => 'bundling',
					'notes' => 'Produk bundling gratis - ' . $bundle['free_product_name'],
					// PERBAIKAN: Tambahkan informasi referensi untuk tampilan
					'reference' => [
						'product1_name' => $product1Details['name'],
						'product1_quantity' => $product1Details['required'],
						'product2_name' => $product2Details['name'],
						'product2_quantity' => $product2Details['required']
					]
				];

				// PERBAIKAN: Log item yang ditambahkan
				log_message('debug', 'Added bundling free product: ' . $bundle['free_product_id'] .
					', Quantity: ' . $freeQuantity .
					', Discount Value: ' . $bundleDiscount);
			}

			// PERBAIKAN: Update discount_amount pada tabel orders dengan nilai promo
			$this->db->where('id', $order_id);
			$updateResult = $this->db->update('orders', [
				'discount_amount' => $totalDiscount
			]);

			if (!$updateResult) {
				log_message('error', 'Failed to update order discount amount: ' . $this->db->error()['message']);
			}

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return [
					'success' => false,
					'message' => 'Terjadi kesalahan saat menerapkan bundle promo',
					'error' => $this->db->error()['message']
				];
			} else {
				$this->db->trans_commit();

				// PERBAIKAN: Catat penggunaan promo
				$orderInfo = $this->db->select('discount_code')->where('id', $order_id)->get('orders')->row_array();
				if ($orderInfo && !empty($orderInfo['discount_code'])) {
					$promoInfo = $this->getPromoByCode($orderInfo['discount_code']);
					if ($promoInfo) {
						$this->recordPromoUsage($promoInfo['promo_id'], $order_id, $totalDiscount);
						log_message('debug', 'Recorded promo usage for bundle promo: ' . $promoInfo['promo_code']);
					}
				}

				return [
					'success' => true,
					'message' => 'Bundle promo berhasil diterapkan',
					'free_products_added' => count($eligible_bundles),
					'items' => $addedItems,
					'total_discount' => $totalDiscount
				];
			}
		} catch (Exception $e) {
			$this->db->trans_rollback();
			log_message('error', 'Error in applyBundlePromoToOrder: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}
	public function addPromoBogo($promo_id, $bogo_data)
	{
		$this->db->trans_begin();

		try {
			// Hapus BOGO yang ada untuk promo ini (jika update)
			$this->db->where('promo_id', $promo_id);
			$this->db->delete($this->bogo_table);

			// Insert data BOGO baru
			if (!empty($bogo_data) && is_array($bogo_data)) {
				foreach ($bogo_data as $bogo) {
					$insert_data = [
						'promo_id' => $promo_id,
						'product_id' => $bogo['product_id'],
						'buy_quantity' => $bogo['buy_quantity'],
						'free_quantity' => $bogo['free_quantity'],
						'free_product_id' => $bogo['free_product_id'] ?? $bogo['product_id'], // Sama dengan product_id jika tidak ditentukan
						'max_apply_count' => $bogo['max_apply_count'] ?? null, // Maksimal berapa kali promo bisa diterapkan
						'created_at' => date('Y-m-d H:i:s')
					];

					$this->db->insert($this->bogo_table, $insert_data);
				}
			}

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return [
					'success' => false,
					'message' => 'Terjadi kesalahan saat menyimpan promo BOGO'
				];
			} else {
				$this->db->trans_commit();
				return [
					'success' => true,
					'message' => 'Promo BOGO berhasil disimpan'
				];
			}
		} catch (Exception $e) {
			$this->db->trans_rollback();
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}

	/**
	 * Mendapatkan data BOGO promo
	 * 
	 * @param int $promo_id ID Promo
	 * @return array Data BOGO promo
	 */
	public function getPromoBogo($promo_id)
	{
		$this->db->select('pb.*, 
                  p1.product_name as product_name, 
                  p1.product_brand as product_brand,
                  pf.product_name as free_product_name, 
                  pf.product_brand as free_product_brand');
		$this->db->from($this->bogo_table . ' as pb');
		$this->db->join('data_product as p1', 'pb.product_id = p1.product_id', 'left');
		$this->db->join('data_product as pf', 'pb.free_product_id = pf.product_id', 'left');
		$this->db->where('pb.promo_id', $promo_id);

		$query = $this->db->get();
		$result = $query->result_array();

		// Tambahkan default values jika kosong
		return array_map(function ($bogo) {
			return [
				'promo_id' => $bogo['promo_id'] ?? null,
				'product_id' => $bogo['product_id'] ?? null,
				'buy_quantity' => $bogo['buy_quantity'] ?? 1,
				'free_quantity' => $bogo['free_quantity'] ?? 1,
				'free_product_id' => $bogo['free_product_id'] ?? $bogo['product_id'],
				'max_apply_count' => $bogo['max_apply_count'] ?? null,
				'product_name' => $bogo['product_name'] ?? 'Produk',
				'free_product_name' => $bogo['free_product_name'] ?? $bogo['product_name'] ?? 'Produk'
			];
		}, $result);
	}

	public function checkBogoEligibility($promo_id, $cart_items)
	{
		// Log debugging
		log_message('debug', 'Checking BOGO eligibility for promo ID: ' . $promo_id);
		log_message('debug', 'Cart items: ' . json_encode($cart_items));

		// Ambil semua BOGO yang tersedia untuk promo ini
		$bogos = $this->getPromoBogo($promo_id);
		if (empty($bogos)) {
			log_message('debug', 'No BOGO offers available for this promo');
			return [
				'eligible' => false,
				'message' => 'Tidak ada promo BOGO yang tersedia untuk promo ini'
			];
		}

		log_message('debug', 'Found ' . count($bogos) . ' BOGO offers for this promo');

		// Buat struktur data sederhana untuk keranjang
		$cart_products = [];
		foreach ($cart_items as $item) {
			// Pastikan item memiliki product_id dan quantity
			if (isset($item['product_id']) && isset($item['quantity'])) {
				$product_id = $item['product_id'];
				$quantity = $item['quantity'];
				if (isset($cart_products[$product_id])) {
					$cart_products[$product_id] += $quantity;
				} else {
					$cart_products[$product_id] = $quantity;
				}
			}
			// Jika item adalah array sederhana (product_id saja)
			else if (!is_array($item)) {
				$product_id = $item;
				if (isset($cart_products[$product_id])) {
					$cart_products[$product_id]++;
				} else {
					$cart_products[$product_id] = 1;
				}
			}
		}

		log_message('debug', 'Processed cart products: ' . json_encode($cart_products));

		// Cek setiap BOGO
		$eligible_bogos = [];

		foreach ($bogos as $bogo) {
			// Berikan default value jika properti tidak ada
			$product_id = $bogo['product_id'] ?? null;
			$buy_quantity = $bogo['buy_quantity'] ?? 1;
			$free_quantity = $bogo['free_quantity'] ?? 1;
			$free_product_id = $bogo['free_product_id'] ?? $product_id;
			$max_apply_count = $bogo['max_apply_count'] ?? null;
			$product_name = $bogo['product_name'] ?? 'Produk';
			$free_product_name = $bogo['free_product_name'] ?? $product_name;

			log_message('debug', 'Checking BOGO: ' . json_encode([
				'product_id' => $product_id,
				'buy_quantity' => $buy_quantity,
				'free_quantity' => $free_quantity,
				'free_product_id' => $free_product_id
			]));

			// Cek apakah produk yang diperlukan ada dalam keranjang dengan jumlah yang cukup
			if ($product_id && isset($cart_products[$product_id]) && $cart_products[$product_id] >= $buy_quantity) {
				log_message('debug', 'BOGO eligible!');

				$apply_count = floor($cart_products[$product_id] / $buy_quantity);

				// PERBAIKAN: Terapkan batasan maksimal jika ada
				if ($max_apply_count !== null) {
					if ($apply_count > $max_apply_count) {
						log_message('debug', "Limiting BOGO apply count from $apply_count to max: $max_apply_count");
						$apply_count = $max_apply_count;
					}
				}

				// PERBAIKAN: Total produk gratis yang didapatkan
				$total_free = $apply_count * $free_quantity;

				// PERBAIKAN: Ambil detail produk untuk tampilan lebih informatif
				$product_details = $this->get_product_detail($product_id);
				$free_product_details = $this->get_product_detail($free_product_id);

				$eligible_bogos[] = [
					'bogo' => $bogo,
					'product_id' => $product_id,
					'free_product_id' => $free_product_id,
					'apply_count' => $apply_count,
					'total_free' => $total_free,
					'product_name' => $product_details['product_name'] ?? $product_name,
					'free_product_name' => $free_product_details['product_name'] ?? $free_product_name,
					'product_details' => [
						'bought_product' => [
							'name' => $product_details['product_name'] ?? $product_name,
							'quantity_in_cart' => $cart_products[$product_id],
							'required_quantity' => $buy_quantity,
							'apply_times' => $apply_count
						],
						'free_product' => [
							'name' => $free_product_details['product_name'] ?? $free_product_name,
							'quantity_per_apply' => $free_quantity,
							'total_quantity' => $total_free
						]
					]
				];

				log_message('debug', "BOGO will be applied $apply_count times with $total_free free items");
			} else {
				log_message('debug', 'BOGO not eligible.');
			}
		}

		if (empty($eligible_bogos)) {
			log_message('debug', 'No eligible BOGO offers found');
			return [
				'eligible' => false,
				'message' => 'Tidak ada promo BOGO yang memenuhi syarat. Pastikan Anda telah menambahkan produk yang diperlukan ke keranjang.'
			];
		}

		log_message('debug', 'Found ' . count($eligible_bogos) . ' eligible BOGO offers');
		return [
			'eligible' => true,
			'bogos' => $eligible_bogos,
			'message' => 'Keranjang memenuhi syarat untuk promo BOGO'
		];
	}
	public function applyBogoPromoToOrder($order_id, $eligible_bogos)
	{
		$this->db->trans_begin();
		try {
			$addedItems = [];
			$totalDiscount = 0;

			foreach ($eligible_bogos as $bogo) {
				// PERBAIKAN: Ambil harga asli produk gratis untuk disimpan sebagai nilai diskon
				$freeProductPrice = 0;
				$product = $this->db
					->select('product_price, product_name, product_pict')
					->from('data_product')
					->where('product_id', $bogo['free_product_id'])
					->get()
					->row_array();

				if (!$product) {
					log_message('warning', 'Free product not found: ' . $bogo['free_product_id']);
					continue;
				}

				$freeProductPrice = floatval($product['product_price']);

				// PERBAIKAN: Ambil informasi produk yang dibeli untuk referensi
				$buyProductDetails = $bogo['product_details']['bought_product'];

				// PERBAIKAN: Tambahkan log detail
				log_message('debug', 'BOGO details: ' . json_encode([
					'product_id' => $bogo['product_id'],
					'buy_quantity' => $buyProductDetails['required_quantity'],
					'apply_times' => $buyProductDetails['apply_times'],
					'free_quantity' => $bogo['product_details']['free_product']['quantity_per_apply'],
					'total_free_quantity' => $bogo['total_free'],
					'free_product_price' => $freeProductPrice
				]));

				// PERBAIKAN: Hitung nilai total diskon (harga produk * quantity gratis)
				$itemDiscount = $freeProductPrice * $bogo['total_free'];
				$totalDiscount += $itemDiscount;

				// Tambahkan produk gratis ke order dengan flag promo
				$free_product_data = [
					'order_id' => $order_id,
					'product_id' => $bogo['free_product_id'],
					'quantity' => $bogo['total_free'],
					'unit_price' => 0.00,  // Gratis
					'subtotal' => 0.00,
					'notes' => 'Produk gratis promo BOGO - ' . $bogo['free_product_name'],
					'created_at' => date('Y-m-d H:i:s'),
					'is_promo_item' => 1,  // Tambahkan penanda item promo
					'promo_type' => 'bogo',  // Tambahkan tipe promo
					'original_price' => $freeProductPrice // PERBAIKAN: Simpan harga asli
				];

				$this->db->insert('order_details', $free_product_data);
				$insertId = $this->db->insert_id();

				$addedItems[] = [
					'id' => $insertId,
					'product_id' => $bogo['free_product_id'],
					'product_name' => $product['product_name'] ?? $bogo['free_product_name'],
					'product_pict' => $product['product_pict'] ?? null,
					'quantity' => $bogo['total_free'],
					'price' => 0,
					'subtotal' => 0,
					'original_price' => $freeProductPrice,
					'discount_value' => $itemDiscount,
					'is_promo_item' => 1,
					'promo_type' => 'bogo',
					'notes' => 'Produk gratis promo BOGO - ' . $bogo['free_product_name'],
					// PERBAIKAN: Tambahkan informasi referensi untuk tampilan
					'reference' => [
						'buy_product_name' => $buyProductDetails['name'],
						'buy_quantity' => $buyProductDetails['required_quantity'],
						'apply_times' => $buyProductDetails['apply_times']
					]
				];

				// PERBAIKAN: Log item yang ditambahkan
				log_message('debug', 'Added BOGO free product: ' . $bogo['free_product_id'] .
					', Quantity: ' . $bogo['total_free'] .
					', Discount Value: ' . $itemDiscount);
			}

			// PERBAIKAN: Update discount_amount pada tabel orders
			$this->db->where('id', $order_id);
			$updateResult = $this->db->update('orders', [
				'discount_amount' => $totalDiscount
			]);

			if (!$updateResult) {
				log_message('error', 'Failed to update order discount amount: ' . $this->db->error()['message']);
			}

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return [
					'success' => false,
					'message' => 'Terjadi kesalahan saat menerapkan promo BOGO',
					'error' => $this->db->error()['message']
				];
			} else {
				$this->db->trans_commit();

				// PERBAIKAN: Catat penggunaan promo
				// Dapatkan informasi promo_id
				$orderInfo = $this->db->select('discount_code')->where('id', $order_id)->get('orders')->row_array();
				if ($orderInfo && !empty($orderInfo['discount_code'])) {
					$promoInfo = $this->getPromoByCode($orderInfo['discount_code']);
					if ($promoInfo) {
						$this->recordPromoUsage($promoInfo['promo_id'], $order_id, $totalDiscount);
						log_message('debug', 'Recorded promo usage for BOGO promo: ' . $promoInfo['promo_code']);
					}
				}

				return [
					'success' => true,
					'message' => 'Promo BOGO berhasil diterapkan',
					'free_products_added' => array_sum(array_column($eligible_bogos, 'total_free')),
					'items' => $addedItems,
					'total_discount' => $totalDiscount
				];
			}
		} catch (Exception $e) {
			$this->db->trans_rollback();
			log_message('error', 'Error in applyBogoPromoToOrder: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}

	public function getPromoSuggestions($brand, $orderTotal, $cartDetails = [])
	{
		$suggestions = [];
		$now = date('Y-m-d H:i:s');

		// Ambil semua promo aktif untuk brand ini
		$this->db->select('*')
			->from($this->table)
			->where('promo_status', 'active')
			->where('promo_brand', $brand)
			->where('start_date <=', $now)
			->where('end_date >=', $now)
			->where('deleted_at IS NULL');

		// Jika kuota diatur, hanya ambil yang masih tersedia
		$this->db->where('(quota IS NULL OR usage_count < quota)');

		$activePromos = $this->db->get()->result_array();
		log_message('debug', 'Found ' . count($activePromos) . ' active promos for brand: ' . $brand);

		// Ekstrak category_ids dan product_ids dari cart untuk validasi cepat
		$cartProductIds = [];
		$cartCategoryIds = [];
		$cartProductQuantities = []; // Menyimpan kuantitas produk untuk validasi BOGO

		foreach ($cartDetails as $item) {
			if (isset($item['product_id'])) {
				$productId = $item['product_id'];
				$cartProductIds[] = $productId;

				// Simpan kuantitas untuk validasi BOGO
				if (isset($item['quantity'])) {
					if (isset($cartProductQuantities[$productId])) {
						$cartProductQuantities[$productId] += intval($item['quantity']);
					} else {
						$cartProductQuantities[$productId] = intval($item['quantity']);
					}
				}
			}

			if (isset($item['cat_id'])) {
				$cartCategoryIds[] = $item['cat_id'];
			}
		}

		$uniqueProductIds = array_unique($cartProductIds);
		$uniqueCategoryIds = array_unique($cartCategoryIds);

		log_message('debug', 'Cart contains products: ' . implode(',', $uniqueProductIds));
		log_message('debug', 'Cart contains categories: ' . implode(',', $uniqueCategoryIds));

		// Cek setiap promo apakah relevan dengan cart saat ini
		foreach ($activePromos as $promo) {
			$isRelevant = false;
			$description = '';
			$eligibilityStatus = 'eligible'; // Default: eligible, bisa jadi 'almost' atau 'not_eligible'
			$additionalInfo = [];

			// Cek minimum order
			if ($promo['minimum_order'] > $orderTotal) {
				$amountNeeded = $promo['minimum_order'] - $orderTotal;
				// Jika total order kurang dari minimum, namun tidak terlalu jauh, sarankan
				if ($orderTotal >= ($promo['minimum_order'] * 0.7)) {
					$isRelevant = true;
					$eligibilityStatus = 'almost';
					$description = 'Tambahkan Rp ' . number_format($amountNeeded, 0, ',', '.') . ' lagi untuk mendapatkan diskon.';
					$additionalInfo['amount_needed'] = $amountNeeded;
				} else {
					continue; // Terlalu jauh dari minimum, skip
				}
			} else {
				$isRelevant = true;
			}

			// Cek apakah promo spesifik untuk produk tertentu
			$promoProducts = $this->getPromoProducts($promo['promo_id']);
			$promoCategories = $this->getPromoCategories($promo['promo_id']);

			$hasProductRestriction = !empty($promoProducts);
			$hasCategoryRestriction = !empty($promoCategories);

			// Jika promo spesifik untuk produk atau kategori tertentu
			if ($hasProductRestriction || $hasCategoryRestriction) {
				$matchFound = false;
				$missingProducts = [];

				// Cek produk spesifik
				if ($hasProductRestriction) {
					$promoProductIds = array_column($promoProducts, 'product_id');
					$matchingProducts = array_intersect($uniqueProductIds, $promoProductIds);

					if (!empty($matchingProducts)) {
						$matchFound = true;

						// Tambahkan produk yang sudah ada di keranjang
						$additionalInfo['matching_products'] = [];
						foreach ($matchingProducts as $productId) {
							$productInfo = $this->db->select('product_name')
								->from('data_product')
								->where('product_id', $productId)
								->get()->row_array();

							if ($productInfo) {
								$additionalInfo['matching_products'][] = [
									'id' => $productId,
									'name' => $productInfo['product_name'],
									'quantity' => $cartProductQuantities[$productId] ?? 1
								];
							}
						}
					} else {
						// Produk yang diperlukan tidak ada di keranjang
						$missingProducts = array_diff($promoProductIds, $uniqueProductIds);
					}
				}

				// Cek kategori spesifik
				if ($hasCategoryRestriction && !$matchFound) {
					$promoCategoryIds = array_column($promoCategories, 'cat_id');
					$matchingCategories = array_intersect($uniqueCategoryIds, $promoCategoryIds);

					if (!empty($matchingCategories)) {
						$matchFound = true;
						$additionalInfo['matching_categories'] = $matchingCategories;
					}
				}

				// Jika tidak ada produk atau kategori yang cocok, cek apakah promo masih relevan
				if (!$matchFound) {
					// Jika ada produk yang kurang dan jumlahnya tidak terlalu banyak, sarankan
					if (!empty($missingProducts) && count($missingProducts) <= 3) {
						$missingProductDetails = [];
						foreach ($missingProducts as $productId) {
							$product = $this->db->select('product_id, product_name, product_price')
								->from('data_product')
								->where('product_id', $productId)
								->get()->row_array();

							if ($product) {
								$missingProductDetails[] = [
									'id' => $product['product_id'],
									'name' => $product['product_name'],
									'price' => $product['product_price']
								];
							}
						}

						if (!empty($missingProductDetails)) {
							$isRelevant = true;
							$eligibilityStatus = 'missing_products';
							$missingNames = array_column($missingProductDetails, 'name');
							$description = 'Tambahkan ' . implode(', ', $missingNames) . ' untuk mendapatkan promo ini.';
							$additionalInfo['missing_products'] = $missingProductDetails;
						} else {
							$isRelevant = false;
						}
					} else {
						$isRelevant = false;
					}
				}
			}

			// Cek tipe promo dan berikan info yang spesifik
			if ($isRelevant) {
				switch ($promo['promo_type']) {
					case 'percentage':
						$discountLabel = $promo['promo_value'] . '%';
						if ($promo['maximum_discount'] !== null) {
							$maxDiscount = 'maks Rp ' . number_format($promo['maximum_discount'], 0, ',', '.');
							$discountLabel .= " ($maxDiscount)";
						}

						if (empty($description)) {
							if ($eligibilityStatus === 'eligible') {
								$description = "Dapatkan diskon {$discountLabel} untuk pembelian Anda.";
							}
						}

						$additionalInfo['discount_percentage'] = $promo['promo_value'];
						$additionalInfo['max_discount'] = $promo['maximum_discount'];
						break;

					case 'nominal':
						$discountLabel = 'Rp ' . number_format($promo['promo_value'], 0, ',', '.');

						if (empty($description)) {
							if ($eligibilityStatus === 'eligible') {
								$description = "Dapatkan potongan {$discountLabel} untuk pembelian Anda.";
							}
						}

						$additionalInfo['discount_amount'] = $promo['promo_value'];
						break;

					case 'bogo':
						// Cek apakah BOGO berlaku - perlu cek kuantitas produk
						$isBOGOApplicable = false;
						$bogoDetails = $this->db->select('*')
							->from($this->bogo_table)
							->where('promo_id', $promo['promo_id'])
							->get()->result_array();

						if (!empty($bogoDetails)) {
							foreach ($bogoDetails as $bogo) {
								$bogoProductId = $bogo['product_id'];
								$buyQuantity = intval($bogo['buy_quantity']);
								$freeQuantity = intval($bogo['free_quantity']);

								// Cek apakah produk ada di keranjang dengan kuantitas cukup
								if (
									isset($cartProductQuantities[$bogoProductId]) &&
									$cartProductQuantities[$bogoProductId] >= $buyQuantity
								) {
									$isBOGOApplicable = true;

									// Produk free bisa sama atau berbeda
									$freeProductId = $bogo['free_product_id'] ?: $bogoProductId;
									$freeProduct = $this->db->select('product_name')
										->from('data_product')
										->where('product_id', $freeProductId)
										->get()->row_array();

									$buyProduct = $this->db->select('product_name')
										->from('data_product')
										->where('product_id', $bogoProductId)
										->get()->row_array();

									$additionalInfo['bogo_details'][] = [
										'buy_product_id' => $bogoProductId,
										'buy_product_name' => $buyProduct['product_name'] ?? 'Produk',
										'buy_quantity' => $buyQuantity,
										'free_product_id' => $freeProductId,
										'free_product_name' => $freeProduct['product_name'] ?? 'Produk',
										'free_quantity' => $freeQuantity
									];

									if (empty($description)) {
										$description = "Beli {$buyQuantity} {$buyProduct['product_name']}, gratis {$freeQuantity} {$freeProduct['product_name']}.";
									}
								} else if (in_array($bogoProductId, $uniqueProductIds)) {
									// Produk ada tapi kuantitas tidak cukup
									$qtyNeeded = $buyQuantity - ($cartProductQuantities[$bogoProductId] ?? 0);
									if ($qtyNeeded > 0) {
										$buyProduct = $this->db->select('product_name')
											->from('data_product')
											->where('product_id', $bogoProductId)
											->get()->row_array();

										$isRelevant = true;
										$eligibilityStatus = 'almost';
										$description = "Tambahkan {$qtyNeeded} {$buyProduct['product_name']} lagi untuk mendapatkan promo BOGO.";
										$additionalInfo['quantity_needed'] = $qtyNeeded;
										$additionalInfo['product_id'] = $bogoProductId;
										$additionalInfo['product_name'] = $buyProduct['product_name'] ?? 'Produk';
									}
								}
							}
						}

						// Jika BOGO tidak berlaku dan belum ada deskripsi, beri tahu general info
						if (!$isBOGOApplicable && $eligibilityStatus !== 'almost' && empty($description)) {
							$isRelevant = false;
						}
						break;

					case 'bundling':
						// Cek apakah bundling berlaku
						$isBundlingApplicable = false;
						$bundleDetails = $this->db->select('*')
							->from($this->bundle_table)
							->where('promo_id', $promo['promo_id'])
							->get()->result_array();

						if (!empty($bundleDetails)) {
							foreach ($bundleDetails as $bundle) {
								$requiredProductId1 = $bundle['required_product_id1'];
								$requiredProductId2 = $bundle['required_product_id2'];
								$minQty1 = intval($bundle['min_quantity1']);
								$minQty2 = intval($bundle['min_quantity2']);

								$hasProduct1 = isset($cartProductQuantities[$requiredProductId1]) &&
									$cartProductQuantities[$requiredProductId1] >= $minQty1;

								$hasProduct2 = isset($cartProductQuantities[$requiredProductId2]) &&
									$cartProductQuantities[$requiredProductId2] >= $minQty2;

								if ($hasProduct1 && $hasProduct2) {
									$isBundlingApplicable = true;

									// Get free product info
									$freeProductId = $bundle['free_product_id'];
									$freeProduct = $this->db->select('product_name, product_price')
										->from('data_product')
										->where('product_id', $freeProductId)
										->get()->row_array();

									// Get required products info
									$product1 = $this->db->select('product_name')
										->from('data_product')
										->where('product_id', $requiredProductId1)
										->get()->row_array();

									$product2 = $this->db->select('product_name')
										->from('data_product')
										->where('product_id', $requiredProductId2)
										->get()->row_array();

									$additionalInfo['bundle_details'][] = [
										'product1_id' => $requiredProductId1,
										'product1_name' => $product1['product_name'] ?? 'Produk 1',
										'product1_qty' => $minQty1,
										'product2_id' => $requiredProductId2,
										'product2_name' => $product2['product_name'] ?? 'Produk 2',
										'product2_qty' => $minQty2,
										'free_product_id' => $freeProductId,
										'free_product_name' => $freeProduct['product_name'] ?? 'Produk Gratis',
										'free_product_price' => $freeProduct['product_price'] ?? 0,
										'free_quantity' => intval($bundle['free_quantity'])
									];

									if (empty($description)) {
										$freeQty = intval($bundle['free_quantity']);
										$description = "Beli {$minQty1} {$product1['product_name']} dan {$minQty2} {$product2['product_name']}, gratis {$freeQty} {$freeProduct['product_name']}.";
									}
								} else if (
									in_array($requiredProductId1, $uniqueProductIds) ||
									in_array($requiredProductId2, $uniqueProductIds)
								) {
									// At least one product exists, suggest completing the bundle
									$missingDetails = [];

									if (!$hasProduct1) {
										$qtyNeeded1 = $minQty1 - ($cartProductQuantities[$requiredProductId1] ?? 0);
										if ($qtyNeeded1 > 0) {
											$product1 = $this->db->select('product_name')
												->from('data_product')
												->where('product_id', $requiredProductId1)
												->get()->row_array();
											$missingDetails[] = [
												'product_id' => $requiredProductId1,
												'product_name' => $product1['product_name'] ?? 'Produk 1',
												'qty_needed' => $qtyNeeded1
											];
										}
									}

									if (!$hasProduct2) {
										$qtyNeeded2 = $minQty2 - ($cartProductQuantities[$requiredProductId2] ?? 0);
										if ($qtyNeeded2 > 0) {
											$product2 = $this->db->select('product_name')
												->from('data_product')
												->where('product_id', $requiredProductId2)
												->get()->row_array();
											$missingDetails[] = [
												'product_id' => $requiredProductId2,
												'product_name' => $product2['product_name'] ?? 'Produk 2',
												'qty_needed' => $qtyNeeded2
											];
										}
									}

									if (!empty($missingDetails)) {
										$isRelevant = true;
										$eligibilityStatus = 'almost';

										// Create description
										$missingText = [];
										foreach ($missingDetails as $missing) {
											$missingText[] = "{$missing['qty_needed']} {$missing['product_name']}";
										}

										$description = "Tambahkan " . implode(' dan ', $missingText) . " lagi untuk mendapatkan bundle gratis.";
										$additionalInfo['missing_bundle_items'] = $missingDetails;
									}
								}
							}
						}

						// Jika bundle tidak berlaku dan belum ada deskripsi, skip
						if (!$isBundlingApplicable && $eligibilityStatus !== 'almost' && empty($description)) {
							$isRelevant = false;
						}
						break;
				}
			}

			// Jika promo relevan, tambahkan ke saran
			if ($isRelevant) {
				$suggestions[] = [
					'promo_id' => $promo['promo_id'],
					'code' => $promo['promo_code'],
					'name' => $promo['promo_name'],
					'type' => $promo['promo_type'],
					'value' => $promo['promo_value'],
					'minimum_order' => $promo['minimum_order'],
					'description' => $description,
					'eligibility' => $eligibilityStatus,
					'additional_info' => $additionalInfo
				];
			}
		}

		log_message('debug', 'Found ' . count($suggestions) . ' relevant promo suggestions');

		return $suggestions;
	}

	// Helper untuk format angka menjadi currency
	private function formatCurrency($amount)
	{
		return 'Rp ' . number_format($amount, 0, ',', '.');
	}

	/**
	 * Mendapatkan promo voucher MRP
	 * @param array $params Parameter filter
	 * @return array List promo voucher MRP
	 */
	public function getMRPVoucherPromos($params = [])
	{
		$this->db->select('p.*');
		$this->db->from($this->table . ' AS p');
		$this->db->where('p.is_mrp_voucher', 1);

		// Terapkan filter tambahan jika ada
		if (!empty($params['promo_brand'])) {
			$this->db->where('p.promo_brand', $params['promo_brand']);
		}

		if (isset($params['promo_status'])) {
			$this->db->where('p.promo_status', $params['promo_status']);
		}

		if (isset($params['active_now']) && $params['active_now']) {
			$now = date('Y-m-d H:i:s');
			$this->db->where('p.start_date <=', $now);
			$this->db->where('p.end_date >=', $now);
		}

		// Filter berdasarkan kode voucher
		if (!empty($params['voucher_code'])) {
			$this->db->where('p.promo_code', $params['voucher_code']);
		}

		// Hanya menampilkan promo yang tidak dihapus
		$this->db->where('p.deleted_at IS NULL');

		// Terapkan pengurutan
		if (!empty($params['sort_by'])) {
			$this->db->order_by($params['sort_by'], !empty($params['sort_dir']) ? $params['sort_dir'] : 'ASC');
		} else {
			$this->db->order_by('p.created_at', 'DESC');
		}

		// Grup berdasarkan promo_id untuk menghindari duplikasi
		$this->db->group_by('p.promo_id');

		// Terapkan pagination
		if (isset($params['limit']) && isset($params['offset'])) {
			$this->db->limit($params['limit'], $params['offset']);
		}

		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Validasi voucher MRP
	 * @param string $voucher_code Kode voucher
	 * @param string $brand Brand yang digunakan
	 * @param float $order_total Total order
	 * @return array Hasil validasi
	 */
	public function validateMRPVoucher($voucher_code, $brand, $order_total = 0)
	{
		// Cari promo berdasarkan kode voucher (hanya 1 promo per kode voucher)
		$this->db->where('promo_code', $voucher_code);
		$this->db->where('is_mrp_voucher', 1);
		$this->db->where('promo_status', 'active');
		$promo = $this->db->get('promos')->row_array();

		if (!$promo) {
			return [
				'valid' => false,
				'message' => 'Voucher tidak valid atau tidak tersedia',
				'success' => false
			];
		}

		// Cek apakah brand dalam daftar brand yang didukung
		$supported_brands = explode(',', $promo['supported_brands']);
		if (!in_array($brand, $supported_brands)) {
			return [
				'valid' => false,
				'message' => 'Voucher tidak berlaku untuk brand ini',
				'success' => false
			];
		}

		// Cek apakah voucher masih dalam periode aktif
		$now = date('Y-m-d H:i:s');
		if ($now < $promo['start_date'] || $now > $promo['end_date']) {
			$status = $now < $promo['start_date'] ? 'belum dimulai' : 'sudah berakhir';
			return [
				'valid' => false,
				'message' => 'Periode voucher ' . $status,
				'success' => false
			];
		}

		// Cek kuota
		if ($promo['quota'] !== null && $promo['usage_count'] >= $promo['quota']) {
			return [
				'valid' => false,
				'message' => 'Kuota voucher sudah habis',
				'success' => false
			];
		}

		// Cek minimum order
		if ($order_total < $promo['minimum_order']) {
			return [
				'valid' => false,
				'message' => 'Total pembelian tidak mencukupi minimum order Rp ' . number_format($promo['minimum_order'], 0, ',', '.'),
				'success' => false,
				'minimum_order' => $promo['minimum_order']
			];
		}

		// Hitung diskon
		$discount_amount = 0;
		$discount_label = '';

		if ($promo['promo_type'] === 'percentage') {
			$discount_amount = $order_total * ($promo['promo_value'] / 100);
			$discount_label = $promo['promo_value'] . '%';

			// Terapkan maximum discount jika ada
			if ($promo['maximum_discount'] !== null && $discount_amount > $promo['maximum_discount']) {
				$discount_amount = $promo['maximum_discount'];
				$discount_label .= ' (max Rp ' . number_format($promo['maximum_discount'], 0, ',', '.') . ')';
			}
		} else {
			$discount_amount = $promo['promo_value'];
			$discount_label = 'Rp ' . number_format($promo['promo_value'], 0, ',', '.');

			// Pastikan diskon tidak melebihi total order
			if ($discount_amount > $order_total) {
				$discount_amount = $order_total;
			}
		}

		return [
			'valid' => true,
			'promo' => $promo,
			'discount_amount' => $discount_amount,
			'final_amount' => $order_total - $discount_amount,
			'success' => true,
			'message' => 'Voucher berhasil diterapkan',
			'promo_code' => $voucher_code,
			'original_amount' => $order_total,
			'eligible_amount' => $order_total,
			'discount_label' => $discount_label,
			'promo_type' => $promo['promo_type'],
			'is_mrp_voucher' => true
		];
	}

	/**
	 * Menerapkan voucher MRP pada order
	 * @param int $orderId ID order
	 * @param string $voucher_code Kode voucher MRP
	 * @param string $brand Brand
	 * @param float $order_total Total order
	 * @return array Hasil penerapan voucher
	 */
	public function applyMRPVoucherToOrder($orderId, $voucher_code, $brand, $order_total)
	{
		$this->db->trans_begin();
		try {
			// Log debugging information
			log_message('debug', 'applyMRPVoucherToOrder - Parameters: ' . json_encode([
				'order_id' => $orderId,
				'voucher_code' => $voucher_code,
				'brand' => $brand,
				'order_total' => $order_total
			]));

			// Check if order exists
			$this->db->where('id', $orderId);
			$this->db->where('deleted_at IS NULL');
			$order = $this->db->get('orders')->row_array();

			if (!$order) {
				return [
					'success' => false,
					'message' => 'Order tidak ditemukan'
				];
			}

			// Validasi voucher
			$validation = $this->validateMRPVoucher($voucher_code, $brand, $order_total);

			if (!$validation['valid']) {
				return [
					'success' => false,
					'message' => $validation['message']
				];
			}

			$promo = $validation['promo'];
			$discount_amount = $validation['discount_amount'];

			// Check if order already has a promo applied
			$this->db->where('order_id', $orderId);
			$existing_usage = $this->db->get('promo_usage')->row();

			if ($existing_usage) {
				// If the same promo is already applied, just update the discount amount
				if ($existing_usage->promo_id == $promo['promo_id']) {
					$this->db->where('id', $orderId);
					$this->db->update('orders', [
						'discount_code' => $voucher_code,
						'discount_amount' => $discount_amount,
						'updated_at' => date('Y-m-d H:i:s')
					]);

					// Update the usage record if the discount amount changed
					if ($existing_usage->discount_amount != $discount_amount) {
						$this->db->where('usage_id', $existing_usage->usage_id);
						$this->db->update('promo_usage', [
							'discount_amount' => $discount_amount
						]);
					}

					$this->db->trans_commit();

					return [
						'success' => true,
						'message' => 'Voucher sudah diterapkan sebelumnya, nilai diskon diperbarui',
						'discount_amount' => $discount_amount,
						'final_amount' => $order_total - $discount_amount,
						'promo_type' => $promo['promo_type'],
						'data' => ['promo' => $promo]
					];
				}

				// If different promo is already applied, remove it first
				$this->db->where('usage_id', $existing_usage->usage_id);
				$this->db->delete('promo_usage');

				// Decrement usage_count for the previous promo
				$this->db->set('usage_count', 'usage_count - 1', FALSE);
				$this->db->where('promo_id', $existing_usage->promo_id);
				$this->db->where('usage_count >', 0);
				$this->db->update('promos');
			}

			// Record new promo usage
			$usage_data = [
				'promo_id' => $promo['promo_id'],
				'order_id' => $orderId,
				'discount_amount' => $discount_amount,
				'usage_time' => date('Y-m-d H:i:s'),
				'counted_in_usage' => 1
			];

			$this->db->insert('promo_usage', $usage_data);

			// Update usage_count in promos table
			$this->db->set('usage_count', 'usage_count + 1', FALSE);
			$this->db->where('promo_id', $promo['promo_id']);
			$this->db->update('promos');

			// Update order with discount
			$this->db->where('id', $orderId);
			$this->db->update('orders', [
				'discount_amount' => $discount_amount,
				'discount_code' => $voucher_code,
				'updated_at' => date('Y-m-d H:i:s')
			]);

			// Notify MRP that voucher has been used
			$this->notifyMRPVoucherUsed($voucher_code, $orderId, $order_total, $discount_amount);

			$this->db->trans_commit();

			return [
				'success' => true,
				'message' => 'Voucher berhasil diterapkan',
				'discount_amount' => $discount_amount,
				'final_amount' => $order_total - $discount_amount,
				'promo_type' => $promo['promo_type'],
				'data' => ['promo' => $promo]
			];
		} catch (Exception $e) {
			$this->db->trans_rollback();
			log_message('error', 'Error in applyMRPVoucherToOrder: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}

	/**
	 * Memberitahu MRP bahwa voucher telah digunakan
	 * @param string $voucher_code Kode voucher
	 * @param int $order_id ID order
	 * @param float $order_total Total order
	 * @param float $discount_amount Jumlah diskon
	 * @return array Hasil notifikasi
	 */
	private function notifyMRPVoucherUsed($voucher_code, $order_id, $order_total, $discount_amount)
	{
		try {
			// Kirim notifikasi ke API PromoApi untuk ditangani
			$api_url = site_url('apis/PromoApi/markVoucherAsUsed');

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $api_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
				'voucher_code' => $voucher_code,
				'order_id' => $order_id,
				'order_amount' => $order_total,
				'discount_amount' => $discount_amount
			]));
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json',
				'Authorization: Basic ' . base64_encode('api:0a6a2c9d1b10d26ef70f773c68c0f58e5eb85a6624015f12d70c635a23376c1f')
			]);

			$response = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			// Log the response
			log_message('debug', 'MRP notification response: ' . $response . ', HTTP code: ' . $http_code);

			return [
				'success' => ($http_code >= 200 && $http_code < 300),
				'http_code' => $http_code,
				'response' => json_decode($response, true)
			];
		} catch (Exception $e) {
			log_message('error', 'Error in notifyMRPVoucherUsed: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => 'Error: ' . $e->getMessage()
			];
		}
	}
}
