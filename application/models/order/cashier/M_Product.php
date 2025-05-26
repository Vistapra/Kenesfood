<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_Product extends Ci_model
{
	private $table = "data_product";
	private $primaryKey = "product_id";

	public function getAll($params, $select = NULL): ?array
	{
		$selected = "*";
		if (!is_null($select)) {
			$selected = implode(",", $select);
		}

		$query = $this->db->select($selected);
		if (array_key_exists("where", $params)) {
			$query = $query->where($params["where"]);
		}
		if (array_key_exists("where_in", $params)) {
			foreach ($params["where_in"] as $key => $value) {
				$query = $query->where_in($key, $value);
			}
		}
		$query = $query->get($this->table);

		return $query->result_array();
	}

	public function countAll($keyword)
	{
		$this->db->where([
			"product_brand" => 'kopitiam'
		]);

		if (!is_null($keyword)) {
			$this->db->like('product_name', $keyword);
		}

		$this->db->from($this->table);

		return $this->db->count_all_results();
	}


	public function getList($keyword = NULL, $limit = 10, $offset = 0)
	{
		$this->db->select(
			"
            product_id AS id,
            product_code AS code,
            product_name AS name,
            stock,
            product_st AS status"
		);

		$this->db->where([
			"product_brand" => "kopitiam"
		]);

		if (!is_null($keyword)) {
			$this->db->like('product_name', $keyword);
		}

		$query = $this->db->get($this->table, $limit, $offset);

		if ($query->num_rows() > 0) {
			return $query->result_array();
		}

		return array();
	}

	public function detail($id)
{
    $query = $this->db
        ->select('dp.*, 
            dc.cat_name, 
            p.id as package_id, 
            p.base_price as package_base_price,
            pc.id as package_category_id,
            pc.name as package_category_name,
            dp.is_package,
            dp.package_type,
            COALESCE(
                pcp.custom_price, 
                dp.product_price,
                p.base_price,
                0
            ) as final_price')
        ->from($this->table . ' as dp')
        ->join('data_categories as dc', 'dp.cat_id = dc.cat_id', 'left')
        ->join('packages p', 'p.product_id = dp.product_id', 'left')
        ->join('package_categories pc', 'pc.package_id = p.id', 'left')
        ->join(
            'package_custom_products pcp',
            'pcp.package_category_id = pc.id AND pcp.product_id = dp.product_id',
            'left'
        )
        ->where('dp.product_id', $id)
        ->where('dp.product_st', '0')  // Hanya produk aktif
        ->group_by('dp.product_id, p.id, pc.id, pcp.id')
        ->limit(1);

    $result = $query->get();
    $productData = $result->row_array();

    // Tambahkan penanganan detail paket yang lebih fleksibel
    if ($productData['is_package'] == '1') {
        $packageDetails = $this->getPackageDetails($productData['package_id']);
        $productData['package_details'] = $packageDetails;
    }

    return $productData;
}

private function getPackageDetails($packageId)
{
    $categories = $this->db
        ->select('pc.*, pcr.min_items, pcr.max_items')
        ->from('package_categories pc')
        ->join('package_category_requirements pcr', 'pcr.package_category_id = pc.id')
        ->where('pc.package_id', $packageId)
        ->get()
        ->result_array();

    $products = [];
    foreach ($categories as $category) {
        $categoryProducts = $this->db
            ->select('
                pcp.*, 
                dp.product_name, 
                dp.stock, 
                dp.product_desc, 
                dp.product_pict,
                dp.product_price,
                COALESCE(pcp.custom_price, dp.product_price, 0) as final_price
            ')
            ->from('package_custom_products pcp')
            ->join('data_product dp', 'pcp.product_id = dp.product_id')
            ->where('pcp.package_category_id', $category['id'])
            ->where('dp.product_st', '0')
            ->get()
            ->result_array();

        $processedProducts = array_map(function ($product) {
            return [
                'id' => $product['id'],
                'product_id' => $product['product_id'],
                'package_category_id' => $product['package_category_id'],
                'custom_price' => $product['custom_price'],
                'final_price' => $product['final_price'],
                'is_default' => $product['is_default'],
                'name' => $product['product_name'],
                'stock' => $product['stock'],
                'description' => $product['product_desc'],
                'image' => $product['product_pict'],
                'price' => floatval($product['final_price'])
            ];
        }, $categoryProducts);

        $products[$category['id']] = $processedProducts;
    }

    return [
        'categories' => $categories,
        'products_by_category' => $products
    ];
}

	public function update($id, $data): array
	{
		$this->db->where($this->primaryKey, $id);
		$query = $this->db->update($this->table, $data);

		$data = self::detail($id);

		return $data;
	}

	public function updateAll($params, $data)
	{
		$this->db->update_batch($this->table, $data, $params);
	}

	private function getPackageProducts($packageId)
{
    try {
        $products = $this->db
            ->select('pcp.*, 
                    dp.product_name, 
                    dp.stock, 
                    dp.product_pict, 
                    dp.product_desc,
                    pc.name as category_name,
                    COALESCE(pcp.custom_price, dp.product_price) as final_price')
            ->from('package_custom_products pcp')
            ->join('data_product dp', 'pcp.product_id = dp.product_id')
            ->join('package_categories pc', 'pc.id = pcp.package_category_id')
            ->join('packages p', 'pc.package_id = p.id') 
            ->where([
                'p.id' => $packageId,
                'pcp.deleted_at' => NULL,
                'dp.product_st' => '0',
                'dp.stock >' => 0
            ])
            ->get()
            ->result_array();

        // Log query untuk debugging
        log_message('debug', 'Package products query: ' . $this->db->last_query());
        log_message('debug', 'Package products result: ' . json_encode($products));

        return $products;
    } catch (Exception $e) {
        log_message('error', 'Error in getPackageProducts: ' . $e->getMessage());
        return [];
    }
}

	/**
	 * Method khusus untuk filter produk berdasarkan kategori paket
	 * 
	 * @param array $params Parameter filter
	 * @return array Daftar produk yang telah difilter
	 */
	public function get_package_category_filter($params)
	{
		// Validasi input
		if (empty($params['brand']) || empty($params['category'])) {
			log_message('error', 'Invalid parameters for package category filter');
			return [];
		}

		try {
			$query = $this->db
				->select('
                dp.product_id, 
                dp.product_name, 
                dp.product_desc, 
                dp.product_pict, 
                dp.stock, 
                dp.product_price, 
                pc.cat_name,
                pkg.id as package_category_id,
                pkg.name as package_category_name
            ')
				->from('data_product dp')
				->join('package_custom_products pcp', 'dp.product_id = pcp.product_id', 'inner')
				->join('package_categories pkg', 'pcp.category_id = pkg.id', 'inner')
				->join('data_categories pc', 'dp.cat_id = pc.cat_id', 'left')
				->where('dp.product_brand', $params['brand'])
				->where('pkg.id', $params['category'])
				->where('dp.product_st', '0')  // Hanya produk aktif
				->where('dp.stock >', 0)       // Hanya produk dengan stok
				->group_by('dp.product_id')
				->get();

			$results = $query->result_array();

			// Proses dan normalisasi data
			return array_map(function ($product) {
				return [
					'product_id' => $product['product_id'],
					'product_name' => $product['product_name'],
					'product_desc' => $product['product_desc'] ?? '',
					'product_pict' => $product['product_pict'] ?? 'default.png',
					'stock' => (int)$product['stock'],
					'price' => (float)$product['product_price'],
					'category_id' => $product['package_category_id'],
					'category_name' => $product['package_category_name']
				];
			}, $results);
		} catch (Exception $e) {
			log_message('error', 'Error in get_package_category_filter: ' . $e->getMessage());
			return [];
		}
	}
}
