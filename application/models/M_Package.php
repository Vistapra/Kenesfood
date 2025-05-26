<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_Package extends Ci_model
{
	private $table = "packages";
	private $primaryKey = "id";

	private function updateOrderDetail()
	{
		return "UPDATE
				order_details
			SET
				qty = ?
			WHERE
				order_id = ?
				AND product_id = ?
		";
	}

	private function getProductInCart(): string
	{
		return "SELECT *
			FROM
				order_details AS od
			WHERE
				od.order_id = ?
				AND od.product_id = ?
			LIMIT 1
		";
	}

	public function addToCart($data)
	{
		$query = $this->db->insert('order_details', $data);

		if (!$query)
			return ["success" => false, "message" => $this->db->error()];

		$data = [
			"success" => true,
			"message" => "Data was created",
			"data" => $data
		];

		return $data;
	}

	public function getCart()
	{
		return "SELECT 
			o.outlet_id,
			o.table_id,
			od.product_id,
			od.notes,
			dp.product_name,
			dp.product_pict,
			dp.stock AS product_stock,
			od.qty AS product_count
			FROM
				orders AS o
			INNER JOIN
				order_details as od ON o.id = od.order_id
			INNER JOIN
				data_product AS dp ON od.product_id = dp.product_id
			WHERE
				o.status = 0
				AND o.outlet_id = ?
				AND o.brand = ?
				AND o.table_id = ?
				AND o.deleted_at IS NULL
				AND od.deleted_at IS NULL
			GROUP BY 
				od.product_id
		";
	}

	public function getCartByProduct()
	{
		return "SELECT

			FROM 
				data_orders
			WHERE
				outlet_id = ?
				AND brand = ?
				AND table_id = ?
				AND product_id = ?
		";
	}

	public function getCountCart()
	{
		return "SELECT
			SUM(od.qty) AS count
			FROM 
				{$this->table} AS o
			INNER JOIN
				order_details AS od ON o.id = od.order_id
			WHERE
				o.outlet_id = ?
				AND o.brand = ?
				AND o.table_id = ?
				AND o.status = 0
				AND od.deleted_at IS NULL
				AND o.deleted_at IS NULL
		";
	}

	public function getPlacedOrder()
	{
		return "SELECT
			SUM(od.qty) AS count
			FROM 
				{$this->table} AS o
			INNER JOIN
				order_details AS od ON o.id = od.order_id
			WHERE
				o.outlet_id = ?
				AND o.table_id = ?
				AND o.brand = ?
				AND o.status = 1
		";
	}

	public function removeCartItem($data, $count)
	{
		$orders = $this->queryDB('getCartByProduct', $data);

		$deletion = array_slice($orders, 0, $count);
		$deletion = array_map(function ($item) {
			return $item->order_code;
		}, $deletion);

		$this->db->where_in('order_code', $deletion)->delete('data_orders');

		$data = [
			"success" => true,
			"message" => "Data was deleted",
			"data" => $data
		];

		return $data;
	}

	public function updateDoneOrder()
	{
		return "UPDATE {$this->table}
			SET status=1
			WHERE
				outlet_id = ?
				AND table_id = ?
				AND brand = ?
		";
	}

	public function updateOrder($action, $params)
	{
		if (!method_exists($this, $action))
			return [];

		$sql   = $this->$action();
		$query = $this->db->query($sql, $params);

		if (!$query) {
			return [
				"success" => false,
				"message" => $this->db->error(),
			];
		}

		return [
			"success" => true,
			"message" => "Order has been placed!"
		];
	}

	public function getOneByIdentity(): string
	{
		return "SELECT *
			FROM
				{$this->table}
			WHERE
				outlet_id = ?
			AND
				table_id = ?
			AND
				brand = ?
			AND
				deleted_at IS NULL
			LIMIT 1
		";
	}

	/**
	 * @param int $id id on orders table
	 */
	public function deleteById($id)
	{
		$this->db->trans_begin();

		// delete orders
		$order = $this
			->db
			->where('id', $id)
			->set('deleted_at', date('Y-m-d H:i:s'))
			->update($this->table);

		// delete detail
		$detail = $this
			->db
			->where('order_id', $id)
			->set('deleted_at', date('Y-m-d H:i:s'))
			->update('order_details');

		if ($this->db->trans_status() == FALSE) {
			$this->db->trans_rollback();
		}

		$this->db->trans_commit();
	}

	/**
	 * @param array $data data contains outlet_id, table_id, brand and name
	 */
	public function insertOrder($data)
	{
		$query = $this->db->insert($this->table, $data);

		if (!$query) {
			return [
				"success" => false,
				"message" => $this->db->error(),
			];
		}

		return [
			"success" => true,
			"id" => $this->db->insert_id()
		];
	}

	public function	queryDB($action, $params)
	{
		if (!method_exists($this, $action))
			return [];

		$sql   = $this->$action();
		$query = $this->db->query($sql, $params);

		return $query->result();
	}

	public function qBUpdate($id, $data)
	{
		$this->db->where('id', $id);
		$this->db->update("{$this->table}", $data);
	}

	public function qBOrderDetailUpdate($orderId, $productId, $data)
	{
		$this->db->where('order_id', $orderId)->where("product_id", $productId);
		$this->db->update("order_details", $data);
	}
	/**
	 * Retrieve a single package with detailed information
	 * 
	 * @param array $params Search parameters
	 * @return array|null Package details
	 */
	public function getAll($params)
	{
		$query = $this->db
			->select('p.*, dp.product_name, dp.product_desc, dp.product_pict, dp.stock, dp.product_price')
			->from($this->table . ' p')
			->join('data_product dp', 'p.product_id = dp.product_id', 'left');

		// Pastikan product_st = '0' untuk hanya menampilkan produk aktif
		$this->db->where('dp.product_st', '0');

		if (isset($params["where"])) {
			foreach ($params["where"] as $key => $value) {
				if (is_null($value)) {
					$this->db->where("$key IS NULL");
				} else {
					$this->db->where("p.$key", $value);
				}
			}
		}

		$result = $this->db->get()->result_array();
		return $result;
	}

	/**
	 * Retrieve a single package with detailed information
	 * 
	 * @param array $params Search parameters
	 * @return array|null Package details
	 */
	public function getOne($params)
	{
		try {
			$query = $this->db
				->select('packages.*, dp.product_name, dp.product_desc, dp.product_pict, dp.stock, dp.product_price')
				->from($this->table)
				->join('data_product dp', 'packages.product_id = dp.product_id')
				->where(array(
					'packages.product_id' => $params["product_id"],
					'packages.deleted_at' => NULL
				))
				->get();

			if (!$query->num_rows()) {
				return null;
			}

			$package = $query->row_array();

			// Get categories with proper join
			$categories = $this->db
				->select('pc.*, pcr.min_items, pcr.max_items')
				->from('package_categories pc')
				->join('package_category_requirements pcr', 'pc.id = pcr.package_category_id', 'left')
				->where('pc.package_id', $package['id'])
				->where('pc.deleted_at IS NULL')
				->order_by('pc.display_order', 'ASC')
				->get()
				->result_array();

			$package['categories'] = $categories;

			// Initialize products array
			$products = [];

			// Get products per category
			foreach ($categories as $category) {
				$categoryProducts = $this->db
					->select('pcp.*, dp.product_name, dp.stock, dp.product_pict, dp.product_desc, COALESCE(pcp.custom_price, dp.product_price) as final_price')
					->from('package_custom_products pcp')
					->join('data_product dp', 'pcp.product_id = dp.product_id')
					->where([
						'pcp.package_category_id' => $category['id'],
						'pcp.deleted_at' => NULL,
						'dp.product_st' => '0'
					])
					->get()
					->result_array();

				$products[$category['id']] = $categoryProducts;
			}

			$package['products_by_category'] = $products;

			return $package;
		} catch (Exception $e) {
			log_message('error', 'Error in getOne: ' . $e->getMessage());
			return null;
		}
	}

	private function getPackageProducts($packageId)
	{
		try {
			$query = $this->db
				->select('
                pcp.*,
                dp.product_name,
                dp.stock,
                dp.product_pict,
                dp.product_desc,
                pc.name as category_name,
                pc.id as category_id,
                COALESCE(pcp.custom_price, dp.product_price) as final_price
            ')
				->from('package_custom_products pcp')
				->join('data_product dp', 'pcp.product_id = dp.product_id')
				->join('package_categories pc', 'pc.id = pcp.category_id')
				->where([
					'pcp.package_id' => $packageId,
					'pcp.deleted_at' => NULL,
					'dp.product_st' => '0',
					'dp.stock >' => 0
				])
				->get();

			if (!($query->num_rows() > 0)) {
				return [];
			}

			$products = $query->result_array();

			// Group products by category
			$groupedProducts = [];
			foreach ($products as $product) {
				$categoryId = $product['category_id'];
				if (!isset($groupedProducts[$categoryId])) {
					$groupedProducts[$categoryId] = [];
				}
				$groupedProducts[$categoryId][] = $product;
			}

			return $groupedProducts;
		} catch (Exception $e) {
			log_message('error', 'Error in getPackageProducts: ' . $e->getMessage());
			return [];
		}
	}

	public function validatePackage($packageId, $selectedProducts)
	{
		try {
			$package = $this->getOne(['product_id' => $packageId]);

			if (!$package) {
				return [
					'valid' => false,
					'messages' => ['Paket tidak ditemukan']
				];
			}

			$validationErrors = [];
			$availableProductsPerCategory = [];
			$allowPartialPackage = true; // Flag untuk memungkinkan paket sebagian

			// Validasi per kategori dengan toleransi produk stok 0
			foreach ($package['categories'] as $category) {
				$categoryProducts = array_filter($selectedProducts, function ($product) use ($category) {
					return $product->categoryId == $category['id'];
				});

				// Filter produk dengan stok > 0
				$availableProducts = array_filter($categoryProducts, function ($product) {
					$productDetails = $this->getProductDetails($product->productId);
					return $productDetails && $productDetails['stock'] > 0;
				});

				$availableProductsPerCategory[$category['id']] = $availableProducts;

				// Log untuk debug
				log_message(
					'debug',
					'Validasi Kategori ' . $category['name'] . ': ' .
						json_encode([
							'min_items' => $category['min_items'],
							'max_items' => $category['max_items'],
							'all_selected_count' => count($categoryProducts),
							'available_count' => count($availableProducts)
						])
				);

				// Jika mode partial package diaktifkan, lewati validasi kategori kosong
				if ($allowPartialPackage) {
					// Jika tidak ada produk tersedia di kategori, lewati
					if (count($availableProducts) == 0) {
						continue;
					}
				}

				// Validasi jumlah item dalam kategori
				if (count($availableProducts) < $category['min_items']) {
					$validationErrors[] = sprintf(
						"Kategori %s membutuhkan minimal %d item yang tersedia",
						$category['name'],
						$category['min_items']
					);
				}

				if (count($availableProducts) > $category['max_items']) {
					$validationErrors[] = sprintf(
						"Kategori %s maksimal %d item yang tersedia",
						$category['name'],
						$category['max_items']
					);
				}

				// Validasi detail produk yang tersedia
				foreach ($availableProducts as $product) {
					$productDetails = $this->getProductDetails($product->productId);

					if (!$productDetails) {
						$validationErrors[] = "Produk tidak ditemukan: {$product->productId}";
						continue;
					}

					if ($productDetails['stock'] < $product->quantity) {
						$validationErrors[] = sprintf(
							"Stok tidak mencukupi untuk %s. Tersedia: %d, Diminta: %d",
							$productDetails['product_name'],
							$productDetails['stock'],
							$product->quantity
						);
					}
				}
			}

			// Tambahkan informasi produk yang tersedia ke dalam respons
			$response = [
				'valid' => empty($validationErrors),
				'messages' => $validationErrors,
				'available_products' => $availableProductsPerCategory
			];

			return $response;
		} catch (Exception $e) {
			log_message('error', 'Kesalahan validasi paket: ' . $e->getMessage());
			return [
				'valid' => false,
				'messages' => ['Kesalahan sistem dalam validasi paket']
			];
		}
	}

	private function getProductDetails($productId)
	{
		return $this->db
			->select('product_id, product_name, stock, product_price')
			->from('data_product')
			->where('product_id', $productId)
			->where('product_st', '0')
			->get()
			->row_array();
	}

	/**
	 * Get total packages count
	 */
	public function get_total_packages($keyword = '')
	{
		$this->db->select('COUNT(*) as total')
			->from($this->table . ' p')
			->join('data_product dp', 'p.product_id = dp.product_id')
			->where('p.deleted_at IS NULL');

		if (!empty($keyword)) {
			$this->db->group_start()
				->like('p.name', $keyword)
				->or_like('dp.product_name', $keyword)
				->or_like('p.description', $keyword)
				->group_end();
		}

		$result = $this->db->get()->row_array();
		return $result['total'];
	}

	/**
	 * Get packages list
	 */
	public function get_list_packages($params, $keyword = '')
	{
		$this->db->select('p.*, dp.product_name, dp.product_pict')
			->from($this->table . ' p')
			->join('data_product dp', 'p.product_id = dp.product_id')
			->where('p.deleted_at IS NULL');

		if (!empty($keyword)) {
			$this->db->group_start()
				->like('p.name', $keyword)
				->or_like('dp.product_name', $keyword)
				->or_like('p.description', $keyword)
				->group_end();
		}

		$this->db->order_by('p.created_at', 'DESC')
			->limit($params[1], $params[0]);

		return $this->db->get()->result_array();
	}

	/**
	 * Add a new package
	 */
	public function add_package($data)
	{
		$this->db->insert($this->table, $data);
		return $this->db->insert_id();
	}

	/**
	 * Update package
	 */
	public function update_package($package_id, $data)
	{
		$this->db->where('id', $package_id);
		return $this->db->update($this->table, $data);
	}

	/**
	 * Get package details
	 */
	public function get_detail_package($package_id)
	{
		$this->db->select('p.*, dp.product_name, dp.product_pict, dp.stock')
			->from($this->table . ' p')
			->join('data_product dp', 'p.product_id = dp.product_id')
			->where('p.id', $package_id)
			->where('p.deleted_at IS NULL');

		return $this->db->get()->row_array();
	}

	/**
	 * Get products in a category
	 */
	public function get_category_products($category_id)
	{
		$this->db->select('pcp.*, dp.product_name, dp.product_pict, dp.product_price')
			->from('package_custom_products pcp')
			->join('data_product dp', 'pcp.product_id = dp.product_id')
			->where('pcp.category_id', $category_id)
			->where('pcp.deleted_at IS NULL');

		return $this->db->get()->result_array();
	}
}
