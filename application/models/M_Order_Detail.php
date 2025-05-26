<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_Order_Detail extends Ci_model
{
	public $table = "order_details";
	public $primaryKey = "id";


	public function getList($params, $select = NULL, $offset = NULL, $limit = 10): ?array
	{
		$selected = "*";
		if (!is_null($select)) {
			$selected = implode(",", $select);
		}

		$query = $this->db->select($selected);

		if (!is_null($offset)) {
			$query = $query->limit($limit, $offset);
		}

		$query = $query
			->where($params)
			->get($this->table);

		return $query->result_array();
	}

	public function getOne($params, $select = NULL): ?array
	{
		$selected = "*";
		if (!is_null($select)) {
			$selected = implode(",", $select);
		}

		$query = $this->db->select($selected);
		$query = $query->where($params)->get($this->table);

		return $query->row_array();
	}

	public function updateAll($data, $primaryKey = NULL)
	{
		if (is_null($primaryKey)) {
			$primaryKey = $this->primaryKey;
		}

		$affected_rows = $this->db->update_batch($this->table, $data, $primaryKey);

		$error = $this->db->error();

		if ($error['code'] !== 0) {
			return $error;
		}

		return $affected_rows;
	}

	public function updateByOrderId($id, $data)
	{
		$this->db->where('order_id', $id);
		$query = $this->db->update($this->table, $data);

		return $data;
	}

	/**
	 * Insert multiple records into the database.
	 *
	 * @param array $data
	 * @return int|bool
	 */
	public function insertAll($data)
	{
		return $this->db->insert_batch($this->table, $data);
	}

	/**
	 * Insert single record into the database.
	 * 
	 * @param array $data
	 * @return int|bool
	 */
	// M_Order_Detail.php
	public function insertOne($data)
	{
		try {
			// Validasi field wajib 
			$requiredFields = ['order_id', 'product_id', 'quantity'];
			foreach ($requiredFields as $field) {
				if (!isset($data[$field])) {
					throw new Exception("Missing required field: {$field}");
				}
			}

			// Ambil product price dan cek stok
			$product = $this->db
				->select('product_price, stock')
				->from('data_product')
				->where('product_id', $data['product_id'])
				->get()
				->row_array();

			if (!$product) {
				throw new Exception("Product not found");
			}

			if ($product['stock'] < $data['quantity']) {
				throw new Exception("Insufficient stock");
			}

			// Set harga per unit dan subtotal
			$data['unit_price'] = $product['product_price'];
			$data['subtotal'] = $data['quantity'] * $data['unit_price'];

			// Sanitasi notes
			if (isset($data['notes'])) {
				$data['notes'] = $this->sanitizeNotes($data['notes']);
			}

			// Set timestamps
			$currentTime = date('Y-m-d H:i:s');
			$data['created_at'] = $currentTime;
			$data['updated_at'] = $currentTime;

			$this->db->insert($this->table, $data);
			return $this->db->insert_id();
		} catch (Exception $e) {
			log_message('error', 'Error in insertOne: ' . $e->getMessage());
			throw $e;
		}
	}

	public function insertPackageOrder($data)
	{
		try {
			// Debug info
			log_message('debug', '=== Start Package Order Insert ===');
			log_message('debug', 'Input Data: ' . json_encode($data));

			// Get package from packages table
			$package = $this->db
				->select('id, product_id, base_price')
				->from('packages')
				->where('product_id', $data['package_id'])
				->get()
				->row_array();

			log_message('debug', 'Found Package: ' . json_encode($package));

			if (!$package) {
				throw new Exception("Package not found for product_id: " . $data['package_id']);
			}

			// Use transaction for data integrity
			$this->db->trans_begin();

			// PERBAIKAN UTAMA: Gunakan base_price dari request jika ada, atau dari packages table
			$basePrice = isset($data['base_price']) ?
				floatval($data['base_price']) :
				floatval($package['base_price'] ?? 0);

			// Insert package header
			$packageHeader = [
				'order_id' => $data['order_id'],
				'product_id' => $data['package_id'],
				'package_id' => $package['id'],
				'quantity' => 1,
				'unit_price' => $basePrice, // Simpan base_price sebagai unit_price
				'subtotal' => $basePrice,   // Awalnya simpan base_price sebagai subtotal
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s')
			];

			log_message('debug', 'Package Header Data: ' . json_encode($packageHeader));

			$this->db->insert('order_details', $packageHeader);
			$packageDetailId = $this->db->insert_id();

			log_message('debug', 'Package Header ID: ' . $packageDetailId);

			// Hitung total harga item dalam paket
			$totalItemsPrice = 0;
			$packageItems = [];

			// PERBAIKAN: Debug product data sebelum insert
			log_message('debug', 'Products data: ' . json_encode($data['products']));

			// Insert items dengan harga dan subtotal yang benar
			foreach ($data['products'] as $product) {
				// PERBAIKAN: Check property names and provide verbose debug
				log_message('debug', 'Processing product: ' . json_encode($product));

				// PERBAIKAN KRITIS: Pastikan kita menggunakan property name yang benar
				// Jika productId tidak ada, coba menggunakan product_id
				$productId = null;
				if (isset($product['productId'])) {
					$productId = $product['productId'];
				} elseif (isset($product['product_id'])) {
					$productId = $product['product_id'];
				}

				// Validasi productId
				if (empty($productId)) {
					log_message('error', 'Product ID is missing or null: ' . json_encode($product));
					throw new Exception("Product ID cannot be null in package");
				}

				$productPrice = floatval($product['price'] ?? 0);
				$productQuantity = intval($product['quantity'] ?? 1);
				$productSubtotal = $productPrice * $productQuantity;

				$productDetail = [
					'order_id' => $data['order_id'],
					'parent_id' => $packageDetailId,
					'product_id' => $productId, // PERBAIKAN: Gunakan variabel yang sudah divalidasi
					'package_id' => $package['id'],
					'quantity' => $productQuantity,
					'unit_price' => $productPrice, // Simpan harga per unit
					'subtotal' => $productSubtotal, // Simpan subtotal yang benar
					'notes' => $product['notes'] ?? '',
					'created_at' => date('Y-m-d H:i:s'),
					'updated_at' => date('Y-m-d H:i:s')
				];

				log_message('debug', 'Product Detail Data to Insert: ' . json_encode($productDetail));

				$totalItemsPrice += $productSubtotal;
				$packageItems[] = $productDetail;

				$this->db->insert('order_details', $productDetail);
			}

			// PERBAIKAN UTAMA: Update package header dengan total yang benar
			// Total paket = harga dasar + total harga item
			$totalPackagePrice = $basePrice + $totalItemsPrice;

			$this->db->where('id', $packageDetailId);
			$this->db->update('order_details', [
				'subtotal' => $totalPackagePrice,
				'updated_at' => date('Y-m-d H:i:s')
			]);

			log_message('debug', 'Updated Package Header with Total Price: ' . $totalPackagePrice);

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				log_message('error', 'Transaction failed in insertPackageOrder');
				throw new Exception("Transaction failed");
			}

			$this->db->trans_commit();
			log_message('debug', '=== End Package Order Insert ===');

			return [
				'success' => true,
				'package_detail_id' => $packageDetailId,
				'total_price' => $totalPackagePrice, // Return total yang benar
				'items' => $packageItems
			];
		} catch (Exception $e) {
			$this->db->trans_rollback();
			log_message('error', 'Package Order Insert Error: ' . $e->getMessage());
			log_message('error', 'Last Query: ' . $this->db->last_query());
			throw $e;
		}
	}

	// Helper method untuk sanitasi notes
	private function sanitizeNotes($notes)
	{
		if (empty($notes)) {
			return null;
		}

		// Bersihkan notes dari karakter yang tidak diinginkan
		$notes = strip_tags($notes);
		$notes = trim($notes);

		// Batasi panjang notes
		if (strlen($notes) > 255) {
			$notes = substr($notes, 0, 255);
		}

		return $notes;
	}

	// Get all dengan include notes
	public function getAll($params)
	{
		try {
			// Validasi params sebelum digunakan
			if (!isset($params['where']) || !is_array($params['where'])) {
				log_message('ERROR', 'Invalid params in getAll: ' . json_encode($params));
				return [];
			}

			// Mulai query builder dengan kondisi aman
			$this->db->select('order_details.*, order_details.unit_price as price, data_product.product_name');
			$this->db->from('order_details');
			$this->db->join('data_product', 'order_details.product_id = data_product.product_id', 'left');

			// Tambahkan where conditions dengan aman
			foreach ($params['where'] as $key => $value) {
				if ($value === NULL) {
					$this->db->where($key . ' IS NULL');
				} else {
					$this->db->where($key, $value);
				}
			}

			try {
				$query = $this->db->get();

				// Periksa apakah query berhasil
				if ($query === FALSE) {
					log_message('ERROR', 'Query failed: ' . $this->db->last_query());
					return [];
				}

				// Log hasil query untuk membantu debugging
				$result = $query->result_array();
				log_message('DEBUG', 'Order Details Result Count: ' . count($result));
				log_message('DEBUG', 'Sample Data: ' . json_encode(array_slice($result, 0, 2)));

				// Kembalikan hasil
				return $result;
			} catch (Exception $e) {
				log_message('ERROR', 'Error in getAll: ' . $e->getMessage());
				log_message('ERROR', 'Last Query: ' . $this->db->last_query());
				return [];
			}
		} catch (Exception $e) {
			log_message('ERROR', 'General error in getAll: ' . $e->getMessage());
			return [];
		}
	}

	public function getAllByOrderId($id)
	{
		try {
			log_message('debug', "Getting order details for order ID: {$id}");

			// PERBAIKAN: Gunakan query yang konsisten dengan getAll()
			$this->db->select('od.*, od.unit_price as price, dp.product_name, dp.product_pict, dp.product_desc');
			$this->db->from("{$this->table} od");
			$this->db->join('data_product dp', 'od.product_id = dp.product_id', 'left');
			$this->db->where('od.order_id', $id);
			$this->db->where('od.deleted_at IS NULL');

			// PERBAIKAN: Gunakan ORDER BY yang benar
			$this->db->order_by('od.parent_id IS NULL', 'DESC', false);
			$this->db->order_by('od.parent_id', 'ASC');

			$query = $this->db->get();

			// PERBAIKAN: Tambahkan pengecekan error query
			if ($query === FALSE) {
				log_message('error', 'Query failed: ' . $this->db->last_query());
				return [];
			}

			// Periksa apakah ada hasil
			if ($query->num_rows() === 0) {
				log_message('debug', "No order details found for order ID: {$id}");
				return [];
			}

			$result = $query->result_array();
			log_message('debug', "Found " . count($result) . " order details for order ID: {$id}");

			// PERBAIKAN: Log sample data
			if (count($result) > 0) {
				log_message('debug', "Sample order detail: " . json_encode($result[0]));
			}

			return $result;
		} catch (Exception $e) {
			log_message('error', "Error in getAllByOrderId: " . $e->getMessage());
			log_message('error', "Last Query: " . $this->db->last_query());
			return [];
		}
	}

	// Update detail order dengan notes handling
	public function update($params, $data)
	{
		// Sanitasi notes jika ada
		if (isset($data['notes'])) {
			$data['notes'] = $this->sanitizeNotes($data['notes']);
		}

		$data['updated_at'] = date('Y-m-d H:i:s');

		$this->db->where($params);
		$result = $this->db->update($this->table, $data);

		if ($result) {
			log_message('info', sprintf(
				'Order detail updated. Params: %s, Data: %s',
				json_encode($params),
				json_encode($data)
			));
		}

		return $data;
	}

	// Get package details dengan notes
	public function getPackageOrderDetails($cartItemId)
	{
		try {
			// Get package header
			$package = $this->db
				->select('od.*, dp.product_name, dp.product_pict')
				->from('order_details od')
				->join('data_product dp', 'od.product_id = dp.product_id')
				->where([
					'od.id' => $cartItemId,
					'od.deleted_at' => NULL
				])
				->get()
				->row_array();

			if (!$package) {
				return null;
			}

			// Get package items with notes
			$items = $this->db
				->select('od.*, dp.product_name, dp.product_pict')
				->from('order_details od')
				->join('data_product dp', 'od.product_id = dp.product_id')
				->where([
					'od.parent_id' => $cartItemId,
					'od.deleted_at' => NULL
				])
				->get()
				->result_array();

			return [
				'package' => $package,
				'items' => $items
			];
		} catch (Exception $e) {
			log_message('error', 'Error getting package order details: ' . $e->getMessage());
			return null;
		}
	}

	public function getTotalAmount($orderId)
	{
		$this->db->select('SUM(subtotal) as total');
		$this->db->from('order_details');
		$this->db->where('order_id', $orderId);
		$this->db->where('deleted_at IS NULL');

		$query = $this->db->get();
		$result = $query->row();

		return $result ? floatval($result->total) : 0;
	}
}
