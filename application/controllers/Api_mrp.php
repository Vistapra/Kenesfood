<?php if (!defined('BASEPATH')) exit('No direct script access allowed');


class Api_mrp extends CI_Controller
{

	private $api_key = "0a6a2c9d1b10d26ef70f773c68c0f58e5eb85a6624015f12d70c635a23376c1f"; // API key

	/**
	 * Constructor yang tidak mewarisi atau memeriksa session admin
	 */
	public function __construct()
	{
		parent::__construct();

		// Load model yang diperlukan
		$this->load->model('master/M_products', 'm_products');
		$this->load->model('master/M_categories', 'm_categories');
		$this->load->model('master/M_brands', 'm_brands');

		// Load helpers
		$this->load->helper('url');
		$this->load->helper('security');

		// PENTING: Tandai header untuk mengatasi CORS
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Allow-Headers: Content-Type, Authorization');

		// Handle preflight requests
		if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			exit(0);
		}
	}

	/**
	 * Kirim response JSON
	 */
	private function _send_response($status_code, $message, $data = null)
	{
		$this->output
			->set_status_header($status_code)
			->set_content_type('application/json')
			->set_output(json_encode([
				'status' => ($status_code == 200 || $status_code == 201) ? 'OK' : 'ERROR',
				'message' => $message,
				'result' => $data
			]));
	}

	/**
	 * Verifikasi API key dari berbagai sumber dengan dukungan format yang lebih fleksibel
	 *
	 * @param bool $required Apakah API key wajib
	 * @return bool Status verifikasi
	 */
	private function _verify_api_key($required = true)
	{
		$headers = $this->input->request_headers();
		$api_key = $this->api_key;

		// 1. Cek Authorization header (Basic Auth)
		if (isset($headers['Authorization'])) {
			// Mendukung format "Basic base64(api:token)" dan format lainnya
			if (strpos($headers['Authorization'], 'Basic ') === 0) {
				// Format Basic Auth
				$credentials = base64_decode(substr($headers['Authorization'], 6));

				// Coba ekstrak username dan password
				if (strpos($credentials, ':') !== false) {
					list($username, $token) = explode(':', $credentials, 2);

					// Verifikasi username dan token
					if ($username === 'api' && $token === $api_key) {
						return true;
					}
				} else {
					// Kasus ketika Basic Auth hanya berisi token tanpa username
					if ($credentials === $api_key) {
						return true;
					}
				}
			}
			// Mendukung format "Bearer token"
			else if (strpos($headers['Authorization'], 'Bearer ') === 0) {
				$token = substr($headers['Authorization'], 7);
				if ($token === $api_key) {
					return true;
				}
			}
			// Mendukung format "Token token"
			else if (strpos($headers['Authorization'], 'Token ') === 0) {
				$token = substr($headers['Authorization'], 6);
				if ($token === $api_key) {
					return true;
				}
			}
			// Jika header ada tapi tidak sesuai format yang didukung
			else {
				// Coba gunakan raw header value sebagai token
				$raw_auth = $headers['Authorization'];
				if ($raw_auth === $api_key) {
					return true;
				}
			}
		}

		// 2. Periksa jika ada API key dalam parameter URL
		$url_api_key = $this->input->get('api_key');
		if ($url_api_key && $url_api_key === $api_key) {
			return true;
		}

		// 3. Periksa jika ada API key dalam POST data
		$post_api_key = $this->input->post('api_key');
		if ($post_api_key && $post_api_key === $api_key) {
			return true;
		}

		// 4. Cek X-API-Key custom header
		if (isset($headers['X-API-Key']) && $headers['X-API-Key'] === $api_key) {
			return true;
		}

		// 5. Coba parse request body untuk JSON requests
		if ($this->input->method() === 'post') {
			$json_data = json_decode($this->input->raw_input_stream, true);
			if (isset($json_data['api_key']) && $json_data['api_key'] === $api_key) {
				return true;
			}
		}

		// Jika API key tidak wajib, izinkan akses
		if (!$required) {
			return true;
		}

		// API key diperlukan namun tidak ditemukan atau tidak valid
		$this->_send_response(401, 'API key required. Add it in one of the following ways:
    1. Authorization header: "Basic ' . base64_encode("api:$api_key") . '"
    2. Bearer token: "Bearer ' . $api_key . '"
    3. URL parameter: ?api_key=' . $api_key . '
    4. Custom header: X-API-Key: ' . $api_key . '
    5. Include in JSON body: {"api_key": "' . $api_key . '", ...}');
		return false;
	}

	/**
	 * Endpoint ping publik yang dapat diakses oleh siapa saja
	 */
	public function ping()
	{
		$this->_send_response(200, 'Connection successful', [
			'timestamp' => time(),
			'server_time' => date('Y-m-d H:i:s'),
			'message' => 'Welcome to MRP-Website Sync API'
		]);
	}

	/**
	 * Endpoint untuk status sistem
	 */
	public function status()
	{
		// Verifikasi API key (opsional untuk endpoint ini)
		$is_authenticated = $this->_verify_api_key(false);

		// Hitung data dasar
		$product_count = $this->db->count_all('data_product');
		$category_count = $this->db->count_all('data_categories');

		// Dapatkan brand yang tersedia
		$this->db->select('DISTINCT(product_brand) as brand');
		$brands_query = $this->db->get('data_product');
		$brands = [];

		if ($brands_query->num_rows() > 0) {
			foreach ($brands_query->result_array() as $row) {
				$brands[] = $row['brand'];
			}
		}

		// Respons yang berbeda untuk pengguna terautentikasi vs tidak
		if ($is_authenticated) {
			// Dapatkan produk terbaru
			$query = $this->db->select('product_id, product_code, product_name, product_price, product_brand, modified')
				->from('data_product')
				->order_by('modified', 'DESC')
				->limit(5)
				->get();

			$latest_products = $query->result_array();

			$this->_send_response(200, 'System status (authenticated)', [
				'server_time' => date('Y-m-d H:i:s'),
				'products_count' => $product_count,
				'categories_count' => $category_count,
				'brands' => $brands,
				'environment' => ENVIRONMENT,
				'latest_products' => $latest_products,
				'authenticated' => true
			]);
		} else {
			$this->_send_response(200, 'System status', [
				'server_time' => date('Y-m-d H:i:s'),
				'products_count' => $product_count,
				'categories_count' => $category_count,
				'brands' => $brands,
				'environment' => ENVIRONMENT,
				'authentication_notice' => 'Add API key for more detailed information',
				'authenticated' => false
			]);
		}
	}

	/**
	 * Endpoint untuk melihat daftar produk
	 */
	public function products()
	{
		// Verifikasi API key (opsional untuk endpoint ini)
		$is_authenticated = $this->_verify_api_key(false);

		// Parameter filter opsional
		$brand = $this->input->get('brand');
		$limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 10;
		$offset = $this->input->get('offset') ? (int)$this->input->get('offset') : 0;
		$category = $this->input->get('category');

		// Query dasar
		$this->db->select('product_id, product_code, product_name, product_price, cat_id, product_brand, api_id');
		$this->db->from('data_product');

		// Filter berdasarkan brand jika disediakan
		if ($brand) {
			$this->db->where('product_brand', $brand);
		}

		// Filter berdasarkan kategori jika disediakan
		if ($category) {
			$this->db->where('cat_id', $category);
		}

		// Hanya produk utama (bukan varian)
		$this->db->where('product_parent', 0);

		// Urutkan berdasarkan ID
		$this->db->order_by('product_id', 'DESC');

		// Batasi hasil
		$this->db->limit($limit, $offset);
		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			$products = $query->result_array();

			// Hitung total
			$this->db->select('COUNT(*) as total');
			$this->db->from('data_product');
			$this->db->where('product_parent', 0);

			if ($brand) {
				$this->db->where('product_brand', $brand);
			}

			if ($category) {
				$this->db->where('cat_id', $category);
			}

			$count_query = $this->db->get();
			$total = $count_query->row()->total;

			$this->_send_response(200, 'Products retrieved successfully', [
				'products' => $products,
				'total' => $total,
				'offset' => $offset,
				'limit' => $limit,
				'authenticated' => $is_authenticated
			]);
		} else {
			$this->_send_response(200, 'No products found', [
				'products' => [],
				'total' => 0,
				'offset' => $offset,
				'limit' => $limit,
				'authenticated' => $is_authenticated
			]);
		}
	}

	/**
	 * Endpoint untuk detail produk
	 */
	public function product_detail($product_id)
	{
		// Verifikasi API key (opsional untuk endpoint ini)
		$is_authenticated = $this->_verify_api_key(false);

		// Ambil detail produk
		$product = $this->m_products->get_detail_product($product_id);

		if (!empty($product)) {
			// Dapatkan varian produk jika ini adalah produk induk
			$variants = [];

			if ($product['product_parent'] == 0) {
				$variants = $this->m_products->get_list_varian_product($product_id);
			}

			$this->_send_response(200, 'Product detail retrieved', [
				'product' => $product,
				'variants' => $variants,
				'authenticated' => $is_authenticated
			]);
		} else {
			$this->_send_response(404, 'Product not found', null);
		}
	}

	/**
	 * Endpoint untuk melihat daftar kategori
	 */
	public function categories()
	{
		// Verifikasi API key (opsional untuk endpoint ini)
		$is_authenticated = $this->_verify_api_key(false);

		// Parameter filter opsional
		$brand = $this->input->get('brand');

		// Query dasar
		$this->db->select('cat_id, cat_code, cat_name, cat_brand, api_id');
		$this->db->from('data_categories');

		// Filter berdasarkan brand jika disediakan
		if ($brand) {
			$this->db->where('cat_brand', $brand);
		}

		// Hanya kategori induk
		$this->db->where('cat_parent', 0);

		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			$categories = $query->result_array();
			$this->_send_response(200, 'Categories retrieved successfully', [
				'categories' => $categories,
				'authenticated' => $is_authenticated
			]);
		} else {
			$this->_send_response(200, 'No categories found', [
				'categories' => [],
				'authenticated' => $is_authenticated
			]);
		}
	}

	/**
	 * Endpoint untuk detail kategori
	 */
	public function category_detail($cat_id)
	{
		// Verifikasi API key (opsional untuk endpoint ini)
		$is_authenticated = $this->_verify_api_key(false);

		// Ambil detail kategori
		$category = $this->m_categories->get_detail_category($cat_id);

		if (!empty($category)) {
			// Dapatkan sub-kategori jika ini adalah kategori induk
			$subcategories = [];

			if ($category['cat_parent'] == 0) {
				// Get the first 20 records for API preview
				$params = array($cat_id, 0, 20);
				$subcategories = $this->m_categories->get_list_data_sub_category($params, '');
			}

			$this->_send_response(200, 'Category detail retrieved', [
				'category' => $category,
				'subcategories' => $subcategories,
				'authenticated' => $is_authenticated
			]);
		} else {
			$this->_send_response(404, 'Category not found', null);
		}
	}

	/**
	 * Endpoint untuk memperbarui produk dari MRP
	 */
	public function update_product()
	{
		// Verifikasi API key
		if (!$this->_verify_api_key()) {
			return;
		}

		// Ambil data dari request body
		$raw_input = $this->input->raw_input_stream;

		// Log raw input untuk debugging
		log_message('debug', 'Raw update_product input: ' . $raw_input);

		// Parse JSON data dengan penanganan error yang lebih baik
		$data = json_decode($raw_input, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->_send_response(400, 'Invalid JSON data: ' . json_last_error_msg());
			return;
		}

		// Jika data kosong atau tidak valid
		if (!$data) {
			$this->_send_response(400, 'Empty or invalid JSON data');
			return;
		}

		// API ID adalah wajib
		if (!isset($data['api_id']) || empty($data['api_id'])) {
			$this->_send_response(400, 'api_id is required');
			return;
		}

		// Log data yang diterima untuk debugging
		log_message('debug', 'MRP Update Product Data: ' . print_r($data, true));

		// Cari produk berdasarkan api_id
		$product = $this->m_products->find_by_api_id_and_brand($data['api_id'], $data['product_brand']);

		if (!$product) {
			$this->_send_response(404, 'Product with api_id=' . $data['api_id'] . ' and brand=' . $data['product_brand'] . ' not found in database.');
			return;
		}


		// Persiapkan data untuk update
		$update_data = [];

		if (isset($data['product_name']) && !empty($data['product_name'])) {
			$update_data['product_name'] = $data['product_name'];
		}
		if (isset($data['product_price']) && is_numeric($data['product_price'])) {
			$update_data['product_price'] = $data['product_price'];
		}
		if (isset($data['product_code']) && !empty($data['product_code'])) {
			$update_data['product_code'] = $data['product_code'];
		}
		if (isset($data['product_desc'])) {
			$update_data['product_desc'] = $data['product_desc'];
		}
		if (isset($data['product_st']) && in_array($data['product_st'], ['0', '1'])) {
			$update_data['product_st'] = $data['product_st'];
		}
		if (isset($data['product_promote']) && in_array($data['product_promote'], ['arrival', 'prelaunch', 'promote', 'none'])) {
			$update_data['product_promote'] = $data['product_promote'];
		}

		// Jika tidak ada data yang diperbarui
		if (empty($update_data)) {
			$this->_send_response(400, 'No valid data to update');
			return;
		}

		// Tambahkan timestamp perubahan
		$update_data['modified'] = date('Y-m-d H:i:s');
		$update_data['modified_by'] = 0; // 0 menandakan update dari sistem MRP

		// Simpan nilai asli sebelum update
		$old_values = [];
		foreach ($update_data as $key => $value) {
			if ($key !== 'modified' && $key !== 'modified_by') {
				$old_values[$key] = isset($product[$key]) ? $product[$key] : null;
			}
		}

		// Perbarui produk di database
		if ($this->m_products->update_product($product['product_id'], $update_data)) {

			// Jika nama produk diperbarui, perbarui juga parent_name di varian
			if (isset($update_data['product_name'])) {
				$variant_data = [
					'parent_name' => $update_data['product_name'],
					'modified' => date('Y-m-d H:i:s'),
					'modified_by' => 0
				];
				$this->m_products->update_variant_product($product['product_id'], $variant_data);
				log_message('debug', 'Updated parent_name in variants for product_id=' . $product['product_id']);
			}

			// Jika harga produk diperbarui, perbarui juga harga di varian yang sesuai
			if (isset($update_data['product_price'])) {
				$variants = $this->m_products->get_list_varian_product($product['product_id']);
				if (!empty($variants)) {
					$updated_variants = 0;
					foreach ($variants as $variant) {
						if ($variant['product_price'] == $old_values['product_price']) {
							$variant_price_data = [
								'product_price' => $update_data['product_price'],
								'modified' => date('Y-m-d H:i:s'),
								'modified_by' => 0
							];
							if ($this->m_products->update_product($variant['product_id'], $variant_price_data)) {
								$updated_variants++;
							}
						}
					}
					log_message('debug', 'Updated price in ' . $updated_variants . ' variants for product_id=' . $product['product_id']);
				}
			}

			// Catat perubahan di log jika method tersedia
			if (method_exists($this->m_products, 'log_product_update_api')) {
				$this->m_products->log_product_update_api(
					$product['product_id'],
					'api_mrp',
					array_keys($update_data),
					$old_values,
					$update_data
				);
				log_message('debug', 'Logged product update for product_id=' . $product['product_id']);
			}

			// Kirim respons sukses
			$this->_send_response(200, 'Product updated successfully', [
				'product_id' => $product['product_id'],
				'api_id' => $data['api_id'],
				'product_brand' => $product['product_brand'],
				'product_name' => isset($update_data['product_name']) ? $update_data['product_name'] : $product['product_name'],
				'updated_fields' => array_keys($update_data)
			]);
		} else {
			// Gagal memperbarui produk
			$this->_send_response(500, 'Failed to update product');
		}
	}


	/**
	 * Endpoint untuk memperbarui kategori dari MRP
	 */
	public function update_category()
	{
		// Verifikasi API key
		if (!$this->_verify_api_key()) {
			return;
		}

		// Ambil data dari request body
		$json_data = $this->security->xss_clean($this->input->raw_input_stream);
		$data = json_decode($json_data, true);

		// Validasi data
		if (!$data) {
			$this->_send_response(400, 'Invalid JSON data');
			return;
		}

		// API ID kategori adalah wajib
		if (!isset($data['api_id']) || empty($data['api_id'])) {
			$this->_send_response(400, 'api_id is required');
			return;
		}

		// Brand kategori adalah wajib
		if (!isset($data['cat_brand']) || empty($data['cat_brand'])) {
			$this->_send_response(400, 'cat_brand is required');
			return;
		}

		// Validasi brand kategori
		$valid_brands = ['bakery', 'resto', 'kopitiam', 'seasonal'];
		if (!in_array($data['cat_brand'], $valid_brands)) {
			$this->_send_response(400, 'Invalid cat_brand. Must be one of: ' . implode(', ', $valid_brands));
			return;
		}

		// Log data yang diterima
		log_message('debug', 'MRP Update Category Data: ' . print_r($data, true));

		// Cari kategori berdasarkan api_id
		$category = $this->m_categories->find_by_api_id($data['api_id']);

		// Persiapkan data untuk update
		$update_data = [];

		// Update nama kategori jika tersedia
		if (isset($data['cat_name']) && !empty($data['cat_name'])) {
			$update_data['cat_name'] = $data['cat_name'];
		}

		// Update deskripsi kategori jika tersedia
		if (isset($data['cat_desc'])) {
			$update_data['cat_desc'] = $data['cat_desc'];
		}

		// Update status kategori jika tersedia
		if (isset($data['cat_st']) && in_array($data['cat_st'], ['0', '1'])) {
			$update_data['cat_st'] = $data['cat_st'];
		}

		// Update highlight kategori jika tersedia
		if (isset($data['cat_highlight']) && in_array($data['cat_highlight'], ['0', '1'])) {
			$update_data['cat_highlight'] = $data['cat_highlight'];
		}

		// Update urutan tampil jika tersedia
		if (isset($data['cat_no']) && is_numeric($data['cat_no'])) {
			$update_data['cat_no'] = $data['cat_no'];
		}

		// Update harga kategori jika tersedia
		if (isset($data['cat_harga']) && is_numeric($data['cat_harga'])) {
			$update_data['cat_harga'] = $data['cat_harga'];
		}

		// Update brand kategori jika kategori belum ada
		// Jika kategori sudah ada, brand tidak boleh diubah
		if (!$category) {
			$update_data['cat_brand'] = $data['cat_brand'];
		} else if ($category['cat_brand'] !== $data['cat_brand']) {
			// Brand kategori tidak boleh diubah
			$this->_send_response(400, 'Cannot change category brand. Existing brand=' . $category['cat_brand'] .
				', requested brand=' . $data['cat_brand']);
			return;
		}

		// Tambahkan timestamp perubahan
		$update_data['modified'] = date('Y-m-d H:i:s');
		$update_data['modified_by'] = 0; // 0 menandakan update dari sistem MRP

		if ($category) {
			// Update kategori yang sudah ada

			// Jika tidak ada data yang valid untuk diperbarui
			if (empty($update_data) || (count($update_data) === 2 && isset($update_data['modified']) && isset($update_data['modified_by']))) {
				$this->_send_response(400, 'No valid data to update');
				return;
			}

			if ($this->m_categories->update_category($category['cat_id'], $update_data)) {
				$this->_send_response(200, 'Category updated successfully', [
					'cat_id' => $category['cat_id'],
					'api_id' => $data['api_id'],
					'cat_brand' => $category['cat_brand'],
					'cat_name' => isset($update_data['cat_name']) ? $update_data['cat_name'] : $category['cat_name']
				]);
			} else {
				$this->_send_response(500, 'Failed to update category');
			}
		} else {
			// Buat kategori baru karena tidak ditemukan

			// Pastikan nama kategori tersedia untuk kategori baru
			if (!isset($data['cat_name']) || empty($data['cat_name'])) {
				$this->_send_response(400, 'cat_name is required for new category');
				return;
			}

			// Kode kategori
			$cat_code = isset($data['cat_code']) && !empty($data['cat_code']) ?
				$data['cat_code'] : 'CAT' . $data['api_id'];

			$insert_data = [
				'cat_parent' => '0',
				'cat_brand' => $data['cat_brand'],
				'cat_code' => $cat_code,
				'cat_name' => $data['cat_name'],
				'cat_desc' => isset($data['cat_desc']) ? $data['cat_desc'] : '',
				'cat_st' => isset($data['cat_st']) ? $data['cat_st'] : '0', // Default aktif
				'cat_highlight' => isset($data['cat_highlight']) ? $data['cat_highlight'] : '1', // Default disorot
				'cat_no' => isset($data['cat_no']) ? $data['cat_no'] : 1,
				'cat_harga' => isset($data['cat_harga']) ? $data['cat_harga'] : 0,
				'api_id' => $data['api_id'],
				'created' => date('Y-m-d H:i:s'),
				'created_by' => 0,
				'modified' => date('Y-m-d H:i:s'),
				'modified_by' => 0,
				'seasonal_id' => 0
			];

			if ($this->m_categories->add_category($insert_data)) {
				$this->_send_response(201, 'Category created successfully', [
					'api_id' => $data['api_id'],
					'cat_brand' => $data['cat_brand'],
					'cat_name' => $data['cat_name']
				]);
			} else {
				$this->_send_response(500, 'Failed to create category');
			}
		}
	}

	/**
	 * Pencarian produk berdasarkan keyword
	 */
	public function search()
	{
		// Verifikasi API key (opsional untuk endpoint ini)
		$is_authenticated = $this->_verify_api_key(false);

		// Parameter pencarian
		$keyword = $this->input->get('keyword');
		$brand = $this->input->get('brand');
		$limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 10;
		$offset = $this->input->get('offset') ? (int)$this->input->get('offset') : 0;

		// Keyword adalah wajib
		if (!$keyword) {
			$this->_send_response(400, 'keyword parameter is required');
			return;
		}

		// Query dasar
		$this->db->select('product_id, product_code, product_name, product_price, cat_id, product_brand, api_id');
		$this->db->from('data_product');

		// Filter pencarian
		$this->db->group_start();
		$this->db->like('product_name', $keyword);
		$this->db->or_like('product_code', $keyword);
		$this->db->or_like('product_desc', $keyword);
		$this->db->group_end();

		// Filter berdasarkan brand jika disediakan
		if ($brand) {
			$this->db->where('product_brand', $brand);
		}

		// Hanya produk utama (bukan varian)
		$this->db->where('product_parent', 0);

		// Hitung total sebelum limit
		$count_query = clone $this->db;
		$total = $count_query->count_all_results();

		// Lanjutkan dengan batasan untuk hasil pencarian
		$this->db->limit($limit, $offset);
		$this->db->order_by('product_name', 'ASC');

		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			$this->_send_response(200, 'Search results', [
				'keyword' => $keyword,
				'brand' => $brand,
				'total' => $total,
				'offset' => $offset,
				'limit' => $limit,
				'products' => $query->result_array(),
				'authenticated' => $is_authenticated
			]);
		} else {
			$this->_send_response(200, 'No products found for keyword: ' . $keyword, [
				'keyword' => $keyword,
				'brand' => $brand,
				'total' => 0,
				'products' => [],
				'authenticated' => $is_authenticated
			]);
		}
	}

	/**
	 * Daftar brand yang tersedia
	 */
	public function brands()
	{
		// Verifikasi API key (opsional untuk endpoint ini)
		$is_authenticated = $this->_verify_api_key(false);

		// Ambil daftar brand dari M_brands
		$brands = $this->m_brands->get_list_brand();

		if (!empty($brands)) {
			$this->_send_response(200, 'Brand list retrieved successfully', [
				'brands' => $brands,
				'total' => count($brands),
				'authenticated' => $is_authenticated
			]);
		} else {
			$this->_send_response(200, 'No brands found', [
				'brands' => [],
				'authenticated' => $is_authenticated
			]);
		}
	}

	/**
	 * Status sinkronisasi terakhir
	 */
	public function sync_status()
	{
		// Verifikasi API key (wajib untuk endpoint ini)
		if (!$this->_verify_api_key()) {
			return;
		}

		// Periksa apakah tabel log sinkronisasi ada
		if (!$this->db->table_exists('sync_logs')) {
			// Buat tabel jika belum ada
			$this->db->query('
                CREATE TABLE IF NOT EXISTS `sync_logs` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `sync_type` varchar(50) NOT NULL,
                  `source` varchar(50) NOT NULL,
                  `items_count` int(11) NOT NULL DEFAULT 0,
                  `success_count` int(11) NOT NULL DEFAULT 0,
                  `failed_count` int(11) NOT NULL DEFAULT 0,
                  `status` varchar(50) NOT NULL,
                  `start_time` datetime NOT NULL,
                  `end_time` datetime DEFAULT NULL,
                  `details` text DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ');
		}

		// Ambil catatan sinkronisasi terakhir
		$this->db->select('*');
		$this->db->from('sync_logs');
		$this->db->order_by('start_time', 'DESC');
		$this->db->limit(10);

		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			$sync_logs = $query->result_array();

			// Analisis status sinkronisasi
			$last_sync = $sync_logs[0];
			$sync_status = [
				'last_sync_time' => $last_sync['start_time'],
				'last_sync_type' => $last_sync['sync_type'],
				'last_sync_source' => $last_sync['source'],
				'last_sync_status' => $last_sync['status'],
				'items_processed' => $last_sync['items_count'],
				'success_count' => $last_sync['success_count'],
				'failed_count' => $last_sync['failed_count'],
				'success_rate' => ($last_sync['items_count'] > 0) ?
					round(($last_sync['success_count'] / $last_sync['items_count']) * 100, 2) : 0
			];

			$this->_send_response(200, 'Sync status retrieved successfully', [
				'current_status' => $sync_status,
				'recent_syncs' => $sync_logs
			]);
		} else {
			$this->_send_response(200, 'No sync logs found', [
				'current_status' => null,
				'recent_syncs' => []
			]);
		}
	}

	/**
	 * Endpoint untuk menambahkan log sinkronisasi
	 */
	public function log_sync()
	{
		// Verifikasi API key
		if (!$this->_verify_api_key()) {
			return;
		}

		// Ambil data dari request body
		$json_data = $this->security->xss_clean($this->input->raw_input_stream);
		$data = json_decode($json_data, true);

		// Validasi data
		if (!$data) {
			$this->_send_response(400, 'Invalid JSON data');
			return;
		}

		// Validasi field yang diperlukan
		$required_fields = ['sync_type', 'source', 'items_count', 'success_count', 'failed_count', 'status'];
		foreach ($required_fields as $field) {
			if (!isset($data[$field])) {
				$this->_send_response(400, $field . ' is required');
				return;
			}
		}

		// Pastikan tabel sync_logs ada
		if (!$this->db->table_exists('sync_logs')) {
			$this->db->query('
                CREATE TABLE IF NOT EXISTS `sync_logs` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `sync_type` varchar(50) NOT NULL,
                  `source` varchar(50) NOT NULL,
                  `items_count` int(11) NOT NULL DEFAULT 0,
                  `success_count` int(11) NOT NULL DEFAULT 0,
                  `failed_count` int(11) NOT NULL DEFAULT 0,
                  `status` varchar(50) NOT NULL,
                  `start_time` datetime NOT NULL,
                  `end_time` datetime DEFAULT NULL,
                  `details` text DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ');
		}

		// Siapkan data untuk dimasukkan
		$insert_data = [
			'sync_type' => $data['sync_type'],
			'source' => $data['source'],
			'items_count' => $data['items_count'],
			'success_count' => $data['success_count'],
			'failed_count' => $data['failed_count'],
			'status' => $data['status'],
			'start_time' => isset($data['start_time']) ? $data['start_time'] : date('Y-m-d H:i:s'),
			'end_time' => isset($data['end_time']) ? $data['end_time'] : date('Y-m-d H:i:s'),
			'details' => isset($data['details']) ? json_encode($data['details']) : null
		];

		// Masukkan ke database
		$this->db->insert('sync_logs', $insert_data);

		if ($this->db->affected_rows() > 0) {
			$this->_send_response(201, 'Sync log added successfully', [
				'log_id' => $this->db->insert_id(),
				'sync_type' => $data['sync_type'],
				'status' => $data['status']
			]);
		} else {
			$this->_send_response(500, 'Failed to add sync log');
		}
	}

	/**
	 * Halaman HTML untuk dokumentasi API
	 */
	public function docs()
	{
		// Data dasar untuk template
		$data = [
			'base_url' => base_url(),
			'current_time' => date('Y-m-d H:i:s'),
			'api_key_sample' => 'YXBpOjBhNmEyYzlkMWIxMGQyNmVmNzBmNzczYzY4YzBmNThlNWViODVhNjYyNDAxNWYxMmQ3MGM2MzVhMjMzNzZjMWY=', // Base64 encoded "api:0a6a2c9d1b10d26ef70f773c68c0f58e5eb85a6624015f12d70c635a23376c1f"
			'endpoints' => [
				[
					'name' => 'ping',
					'method' => 'GET',
					'url' => site_url('api/mrp/ping'),
					'description' => 'Test konektivitas dasar (publik)',
					'auth_required' => false
				],
				[
					'name' => 'status',
					'method' => 'GET',
					'url' => site_url('api/mrp/status'),
					'description' => 'Status sistem dengan statistik dasar',
					'auth_required' => false,
					'params' => [
						'api_key' => 'Opsional, untuk melihat informasi lebih detail'
					]
				],
				[
					'name' => 'products',
					'method' => 'GET',
					'url' => site_url('api/mrp/products'),
					'description' => 'Daftar produk dengan filter opsional',
					'auth_required' => false,
					'params' => [
						'brand' => 'Filter berdasarkan brand (opsional)',
						'category' => 'Filter berdasarkan ID kategori (opsional)',
						'limit' => 'Batasi jumlah hasil (default: 10)',
						'offset' => 'Mulai dari offset (default: 0)'
					]
				],
				[
					'name' => 'product_detail/{id}',
					'method' => 'GET',
					'url' => site_url('api/mrp/product_detail/1'),
					'description' => 'Detail produk berdasarkan ID',
					'auth_required' => false
				],
				[
					'name' => 'categories',
					'method' => 'GET',
					'url' => site_url('api/mrp/categories'),
					'description' => 'Daftar kategori dengan filter opsional',
					'auth_required' => false,
					'params' => [
						'brand' => 'Filter berdasarkan brand (opsional)'
					]
				],
				[
					'name' => 'category_detail/{id}',
					'method' => 'GET',
					'url' => site_url('api/mrp/category_detail/1'),
					'description' => 'Detail kategori berdasarkan ID',
					'auth_required' => false
				],
				[
					'name' => 'update_product',
					'method' => 'POST',
					'url' => site_url('api/mrp/update_product'),
					'description' => 'Update produk berdasarkan api_id',
					'auth_required' => true,
					'body_example' => json_encode([
						'api_id' => 8,
						'product_name' => 'UPDATED PRODUCT NAME',
						'product_brand' => 'kopitiam',
						'product_price' => 32500,
						'product_desc' => 'Updated product description'
					], JSON_PRETTY_PRINT)
				],
				[
					'name' => 'update_category',
					'method' => 'POST',
					'url' => site_url('api/mrp/update_category'),
					'description' => 'Update atau buat kategori berdasarkan api_id',
					'auth_required' => true,
					'body_example' => json_encode([
						'api_id' => 42,
						'cat_brand' => 'kopitiam',
						'cat_name' => 'UPDATED CATEGORY NAME',
						'cat_desc' => 'Updated category description',
						'cat_highlight' => '1'
					], JSON_PRETTY_PRINT)
				],
				[
					'name' => 'search',
					'method' => 'GET',
					'url' => site_url('api/mrp/search?keyword=noodle&brand=kopitiam'),
					'description' => 'Pencarian produk berbasis keyword',
					'auth_required' => false,
					'params' => [
						'keyword' => 'Kata kunci pencarian (wajib)',
						'brand' => 'Filter berdasarkan brand (opsional)',
						'limit' => 'Batasi jumlah hasil (default: 10)',
						'offset' => 'Mulai dari offset (default: 0)'
					]
				],
				[
					'name' => 'brands',
					'method' => 'GET',
					'url' => site_url('api/mrp/brands'),
					'description' => 'Daftar brand yang tersedia',
					'auth_required' => false
				],
				[
					'name' => 'sync_status',
					'method' => 'GET',
					'url' => site_url('api/mrp/sync_status'),
					'description' => 'Status terakhir sinkronisasi',
					'auth_required' => true
				],
				[
					'name' => 'log_sync',
					'method' => 'POST',
					'url' => site_url('api/mrp/log_sync'),
					'description' => 'Tambahkan log sinkronisasi baru',
					'auth_required' => true,
					'body_example' => json_encode([
						'sync_type' => 'products',
						'source' => 'mrp',
						'items_count' => 100,
						'success_count' => 98,
						'failed_count' => 2,
						'status' => 'completed'
					], JSON_PRETTY_PRINT)
				]
			]
		];

		// Cek apakah browser meminta JSON atau HTML
		if ($this->input->get('format') === 'json') {
			// Return JSON format
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode($data));
		} else {
			// Buat HTML template untuk dokumentasi
			$html = '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>MRP-Website Sync API Documentation</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { padding-top: 2rem; padding-bottom: 2rem; }
                    .endpoint { margin-bottom: 2rem; border-bottom: 1px solid #eee; padding-bottom: 1rem; }
                    .code { background-color: #f8f9fa; padding: 1rem; border-radius: 0.25rem; overflow-x: auto; }
                    .auth-required { color: #dc3545; font-weight: bold; }
                    .auth-optional { color: #0d6efd; }
                    pre { margin-bottom: 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <header class="mb-5">
                        <h1>MRP-Website Sync API Documentation</h1>
                        <p class="lead">API untuk sinkronisasi data antara sistem MRP dan website</p>
                        <p>Server time: ' . $data['current_time'] . '</p>
                        <div class="alert alert-info">
                            <h5>Autentikasi API</h5>
                            <p>Beberapa endpoint memerlukan autentikasi dengan API key. Ada 3 cara untuk menyertakan API key:</p>
                            <ol>
                                <li><strong>Header Authorization:</strong> 
                                    <code>Authorization: Basic ' . $data['api_key_sample'] . '</code>
                                </li>
                                <li><strong>Parameter URL:</strong> 
                                    <code>?api_key=' . $this->api_key . '</code>
                                </li>
                                <li><strong>POST data:</strong> 
                                    <code>api_key=' . $this->api_key . '</code>
                                </li>
                            </ol>
                        </div>
                    </header>

                    <main>';

			// Generate documentation for each endpoint
			foreach ($data['endpoints'] as $endpoint) {
				$html .= '<div class="endpoint">
                    <h3>' . $endpoint['method'] . ' /' . $endpoint['name'] . '</h3>
                    <p>' . $endpoint['description'] . '</p>
                    <p>Autentikasi: ' .
					($endpoint['auth_required']
						? '<span class="auth-required">Wajib</span>'
						: '<span class="auth-optional">Opsional</span>'
					) .
					'</p>
                    <h5>URL:</h5>
                    <div class="code">
                        <code>' . $endpoint['url'] . '</code>
                    </div>';

				// Add parameters if any
				if (isset($endpoint['params']) && !empty($endpoint['params'])) {
					$html .= '<h5>Parameter:</h5>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Deskripsi</th>
                            </tr>
                        </thead>
                        <tbody>';

					foreach ($endpoint['params'] as $param => $desc) {
						$html .= '<tr>
                            <td><code>' . $param . '</code></td>
                            <td>' . $desc . '</td>
                        </tr>';
					}

					$html .= '</tbody>
                    </table>';
				}

				// Add body example for POST requests
				if ($endpoint['method'] === 'POST' && isset($endpoint['body_example'])) {
					$html .= '<h5>Contoh Body:</h5>
                    <div class="code">
                        <pre><code>' . $endpoint['body_example'] . '</code></pre>
                    </div>';
				}

				// Add request and response examples
				$html .= '<h5>Contoh CURL:</h5>
                <div class="code">
                    <pre><code>';

				if ($endpoint['method'] === 'GET') {
					$html .= 'curl -X GET "' . $endpoint['url'] . '"';
					// Add auth if required
					if ($endpoint['auth_required']) {
						$html .= ' -H "Authorization: Basic ' . $data['api_key_sample'] . '"';
					}
				} else if ($endpoint['method'] === 'POST') {
					$html .= 'curl -X POST "' . $endpoint['url'] . '"';
					// Add auth if required
					if ($endpoint['auth_required']) {
						$html .= ' -H "Authorization: Basic ' . $data['api_key_sample'] . '"';
					}
					// Add content-type and body
					if (isset($endpoint['body_example'])) {
						$html .= ' -H "Content-Type: application/json" \\
                            -d \'' . $endpoint['body_example'] . '\'';
					}
				}

				$html .= '</code></pre>
                </div>
                </div>';
			}

			$html .= '</main>
                <footer class="mt-5 pt-3 border-top text-center text-muted">
                    <p>MRP-Website Sync API &copy; ' . date('Y') . '</p>
                </footer>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
            </body>
            </html>';

			// Output HTML
			$this->output
				->set_content_type('text/html')
				->set_output($html);
		}
	}
}
