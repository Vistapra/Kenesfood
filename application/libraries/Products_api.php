<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Products_api
{
	protected $ci;
	private $api_url = 'https://mrp.kenesproduction.com/apis'; // Base API URL
	private $username = 'api'; // API username
	private $password = '0a6a2c9d1b10d26ef70f773c68c0f58e5eb85a6624015f12d70c635a23376c1f'; // API token
	private $cache_expiry = 300; // 5 minutes cache

	public function __construct()
	{
		$this->ci = &get_instance();
		$this->ci->load->helper('url');
		$this->ci->load->library('session');
		$this->ci->load->driver('cache', array('adapter' => 'file'));
	}

	/**
	 * Pencarian Produk dengan Fitur Filter yang Ditingkatkan
	 * 
	 * @param string $brand Brand produk
	 * @param array $filters Filter pencarian (keyword, category, etc)
	 * @param int $page Halaman produk
	 * @param int $limit Jumlah produk per halaman
	 * @return array Produk yang difilter dengan pagination
	 */
	public function searchProducts($brand, $filters = [], $page = 1, $limit = 20)
	{
		$endpoint = '/data/searchProducts';
		$params = [
			'brand' => $brand,
			'page' => $page,
			'limit' => $limit,
			'filters' => $filters
		];

		// Mencoba ambil dari cache terlebih dahulu
		$cache_key = 'products_api_search_' . md5(json_encode($params));
		$cached_response = $this->ci->cache->get($cache_key);

		if ($cached_response) {
			log_message('debug', 'Products API Search Cache Hit: ' . $endpoint);
			return $cached_response;
		}

		$response = $this->request($endpoint, $params, 'POST');

		if ($response['status'] == 'OK') {
			$result = [
				'products' => $response['result']['products'] ?? [],
				'total' => $response['result']['total'] ?? 0,
				'pages' => $response['result']['pages'] ?? 1
			];

			// Cache hasil untuk meningkatkan performa
			$this->ci->cache->save($cache_key, $result, $this->cache_expiry);

			return $result;
		}

		// Fallback jika API gagal, gunakan getProductsByBrand dengan filter manual
		if (!empty($brand)) {
			$products = $this->getProductsByBrand($brand);

			// Filter produk berdasarkan keyword jika ada
			if (!empty($filters['keyword'])) {
				$keyword = strtolower($filters['keyword']);
				$filtered = [];

				foreach ($products as $product) {
					if (
						strpos(strtolower($product['product_code']), $keyword) !== false ||
						strpos(strtolower($product['product_name']), $keyword) !== false ||
						strpos(strtolower($product['category_name'] ?? ''), $keyword) !== false
					) {
						$filtered[] = $product;
					}
				}

				$products = $filtered;
			}

			// Implementasi pagination manual
			$total = count($products);
			$offset = ($page - 1) * $limit;
			$products = array_slice($products, $offset, $limit);

			return [
				'products' => $products,
				'total' => $total,
				'pages' => ceil($total / $limit)
			];
		}

		return ['products' => [], 'total' => 0, 'pages' => 1];
	}

	/**
	 * Synchronize categories with products from API
	 * 
	 * @param string $brand Brand for which to sync categories
	 * @return array List of categories
	 */
	public function syncCategoriesWithProducts($brand)
	{
		// Validasi parameter
		if (empty($brand)) {
			log_message('error', "Empty brand parameter in syncCategoriesWithProducts");
			return [];
		}

		// Buat cache key yang unik untuk kombinasi brand
		$cache_key = 'categories_' . md5($brand);

		// Cek apakah ada di cache
		$cached_categories = $this->ci->cache->get($cache_key);
		if ($cached_categories) {
			log_message('debug', "Found cached categories for brand: $brand");
			return $cached_categories;
		}

		// Jika tidak ada di cache, ambil dari API
		log_message('debug', "Syncing categories for brand: $brand");

		// Dapatkan produk berdasarkan brand terlebih dahulu
		$products = $this->getProductsByBrand($brand);

		log_message('debug', "Products found for $brand: " . count($products));

		// Jika produk kosong, coba alternatif endpoint atau refresh cache
		if (empty($products)) {
			log_message('debug', "No products found for $brand. Trying to refresh cache.");
			$this->refreshCache();
			$products = $this->getProductsByBrand($brand);
			log_message('debug', "After refreshing cache, products found: " . count($products));
		}

		// Kemudian ekstrak kategori dari produk tersebut
		$categories = [];
		$used_category_ids = [];

		foreach ($products as $product) {
			$category_id = $product['category_id'] ?? null;
			$category_name = $product['category_name'] ?? null;

			// Pastikan category ID dan nama ada, serta belum pernah diproses
			if ($category_id && $category_name && !in_array($category_id, $used_category_ids)) {
				// PERBAIKAN: Selalu tambahkan brand ke data kategori
				$categories[] = [
					'cat_id' => $category_id,
					'cat_name' => $category_name,
					'cat_brand' => $brand // Tambahkan brand untuk memastikan kategorisasi yang benar
				];
				$used_category_ids[] = $category_id;

				log_message('debug', "Added category ID: $category_id, Name: $category_name, Brand: $brand");
			}
		}

		// Log jumlah kategori yang ditemukan
		log_message('debug', "Categories extracted for $brand: " . count($categories));

		// Jika masih kosong, coba buat kategori default
		if (empty($categories) && !empty($products)) {
			log_message('debug', "Creating default category for $brand.");
			$categories[] = [
				'cat_id' => 999, // ID sementara
				'cat_name' => ucfirst($brand) . ' Products', // Nama default
				'cat_brand' => $brand // Tetap tambahkan brand
			];
		}

		// Simpan dalam cache untuk digunakan nanti
		$this->ci->cache->save($cache_key, $categories, $this->cache_expiry);

		return $categories;
	}

	/**
	 * Cari produk berdasarkan keyword
	 * 
	 * @param string $brand Brand produk
	 * @param string $keyword Kata kunci pencarian
	 * @param int $page Halaman
	 * @param int $limit Jumlah item per halaman
	 * @return array Hasil pencarian
	 */
	public function searchProductsByKeyword($brand, $keyword = '', $page = 1, $limit = 10)
	{
		// Dapatkan semua produk brand tersebut
		$products = $this->getProductsByBrand($brand);

		// Filter produk berdasarkan keyword jika ada
		if (!empty($keyword)) {
			$keyword = strtolower($keyword);
			$filtered = [];

			foreach ($products as $product) {
				if (
					strpos(strtolower($product['product_code']), $keyword) !== false ||
					strpos(strtolower($product['product_name']), $keyword) !== false ||
					strpos(strtolower($product['category_name'] ?? ''), $keyword) !== false
				) {
					$filtered[] = $product;
				}
			}

			$products = $filtered;
		}

		// Hitung total
		$total = count($products);

		// Implementasi pagination
		$offset = ($page - 1) * $limit;
		$paginatedProducts = array_slice($products, $offset, $limit);

		return [
			'products' => $paginatedProducts,
			'total' => $total,
			'pages' => ceil($total / $limit),
			'current_page' => $page
		];
	}


	/**
	 * Make a request to the API
	 * 
	 * @param string $endpoint Endpoint URL
	 * @param array $params Request parameters
	 * @param string $method HTTP method (GET/POST)
	 * @return array API response
	 */
	public function request($endpoint, $params = [], $method = 'GET', $bypass_cache = false)
	{
		// Generate cache key based on endpoint and params
		$cache_key = 'products_api_' . md5($endpoint . json_encode($params) . $method);

		// Check cache first unless bypass is requested
		if (!$bypass_cache) {
			$cached_response = $this->ci->cache->get($cache_key);
			if ($cached_response) {
				log_message('debug', 'Products API Cache Hit: ' . $endpoint);
				return $cached_response;
			}
		}

		$url = $this->api_url . $endpoint;
		log_message('debug', "Making API request to: $url with method: $method");

		$ch = curl_init();

		if ($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
			log_message('debug', "POST data: " . json_encode($params));
		} else {
			if (!empty($params)) {
				$url .= '?' . http_build_query($params);
				log_message('debug', "GET params: " . http_build_query($params));
			}
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Accept: application/json'
		]);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 seconds timeout
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($ch);
		$error = curl_error($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		// Log HTTP code for debugging
		log_message('debug', "API Response HTTP Code: $http_code");

		if ($error) {
			log_message('error', 'Products API Request Error: ' . $error);
			return [
				'status' => 'ERROR',
				'message' => $error,
				'result' => null
			];
		}

		// Log raw response for debugging
		log_message('debug', 'Products API Raw Response (first 1000 chars): ' . substr($response, 0, 1000));

		$decoded_response = json_decode($response, true);

		// Handle potential JSON decode errors
		if (json_last_error() !== JSON_ERROR_NONE) {
			log_message('error', 'JSON decode error: ' . json_last_error_msg());
			log_message('error', 'Original response: ' . $response);
			return [
				'status' => 'ERROR',
				'message' => 'Invalid JSON response: ' . json_last_error_msg(),
				'result' => null
			];
		}

		// Normalize response structure if needed
		$normalized_response = $this->normalizeResponseStructure($decoded_response);

		// Log the normalized structure
		log_message('debug', 'Normalized API Response: ' . print_r($normalized_response, true));

		// Save to cache if response is successful
		if (isset($normalized_response['status']) && $normalized_response['status'] == 'OK') {
			$this->ci->cache->save($cache_key, $normalized_response, $this->cache_expiry);
		}

		return $normalized_response;
	}

	private function normalizeResponseStructure($response)
	{
		// Log untuk debugging
		log_message('debug', "Normalizing API response structure");

		// Jika respons null atau bukan array
		if (is_null($response) || !is_array($response)) {
			log_message('error', "API response is not valid: " . print_r($response, true));
			return [
				'status' => 'ERROR',
				'message' => 'Invalid response format',
				'result' => []
			];
		}

		// Jika sudah dalam format yang diharapkan
		if (isset($response['status'])) {
			// Periksa jika result ada
			if (!isset($response['result'])) {
				$response['result'] = [];
			}
			return $response;
		}

		// Format respons bersarang - jika result berisi status
		if (isset($response['result']) && is_array($response['result']) && isset($response['result']['status'])) {
			return $response['result'];
		}

		// Format dengan data langsung di root
		if (is_array($response)) {
			// Periksa untuk kasus array produk tanpa pembungkus
			$is_product_array = false;
			if (isset($response[0]) && is_array($response[0])) {
				// Cek apakah ini array produk
				$sample = $response[0];
				if (isset($sample['product_id']) || isset($sample['product_name'])) {
					$is_product_array = true;
				}
			}

			if ($is_product_array) {
				log_message('debug', "Detected product array without wrapper, normalizing");
				return [
					'status' => 'OK',
					'message' => 'Success (normalized from product array)',
					'result' => $response
				];
			}

			// Selain itu, bungkus sebagai result
			return [
				'status' => 'OK',
				'message' => 'Success (normalized)',
				'result' => $response
			];
		}

		// Fallback untuk kasus tidak terduga
		log_message('error', "Could not normalize response: " . print_r($response, true));
		return [
			'status' => 'ERROR',
			'message' => 'Could not normalize response format',
			'result' => []
		];
	}

	private function standardizeApiResponse($response)
	{
		// Cek jika respons kosong atau tidak memiliki status
		if (empty($response) || !isset($response['status'])) {
			return [];
		}

		// Case 1: Format normal - status/message/result
		if ($response['status'] == 'OK' && isset($response['result']) && is_array($response['result'])) {
			return $response['result'];
		}

		// Case 2: Format nested - result/status/message/result
		if (
			isset($response['result']['status']) && $response['result']['status'] == 'OK' &&
			isset($response['result']['result']) && is_array($response['result']['result'])
		) {
			return $response['result']['result'];
		}

		// Case 3: Format dengan result di dalam result
		if (
			isset($response['result']) && is_array($response['result']) &&
			isset($response['result']['result']) && is_array($response['result']['result'])
		) {
			return $response['result']['result'];
		}

		// Default case: kosong jika tidak ada yang cocok
		return [];
	}

	public function getKopitiamProducts()
	{
		$endpoint = '/data/getProductsKopitiam';
		$response = $this->request($endpoint);

		// Gunakan metode standarisasi
		return $this->standardizeApiResponse($response);
	}

	public function getBakeryProducts()
	{
		$endpoint = '/data/getProductsBakery';
		$response = $this->request($endpoint);

		// Gunakan metode standarisasi
		return $this->standardizeApiResponse($response);
	}

	public function getRestoProducts()
	{
		$endpoint = '/data/getProductsResto';
		$response = $this->request($endpoint);

		// Gunakan metode standarisasi
		return $this->standardizeApiResponse($response);
	}

	/**
	 * Get products based on brand
	 * 
	 * @param string $brand Brand type (kopitiam, bakery, resto)
	 * @return array List of products for the specified brand
	 */
	public function getProductsByBrand($brand)
	{
		// Tambahkan log untuk debug
		log_message('debug', "Getting products for brand: $brand");

		$products = [];
		$raw_products = []; // Untuk menyimpan data mentah dari API

		switch (strtolower($brand)) {
			case 'kopitiam':
				$raw_products = $this->getKopitiamProducts();
				break;
			case 'bakery':
				$raw_products = $this->getBakeryProducts();
				break;
			case 'resto':
				$raw_products = $this->getRestoProducts();
				break;
			default:
				log_message('debug', "Unknown brand type: $brand");
				return [];
		}

		// Log data mentah dari API untuk debugging
		log_message('debug', "Raw products from API: " . count($raw_products));
		if (!empty($raw_products) && isset($raw_products[0])) {
			log_message('debug', "First raw product structure: " . print_r($raw_products[0], true));
		}

		// Standarisasi dan validasi data produk
		foreach ($raw_products as $product) {
			// Pastikan kunci yang diperlukan ada
			if (isset($product['product_id']) && !empty($product['product_name'])) {
				// Ekstrak kategori dengan jelas
				$category_id = null;
				$category_name = 'Uncategorized';

				// Periksa kemungkinan struktur kategori yang berbeda
				if (isset($product['category_id']) && !empty($product['category_id'])) {
					$category_id = $product['category_id'];

					// Jika nama kategori tersedia, gunakan
					if (isset($product['category_name']) && !empty($product['category_name'])) {
						$category_name = $product['category_name'];
					}
				} else if (isset($product['cat_id']) && !empty($product['cat_id'])) {
					// Alternatif jika menggunakan cat_id
					$category_id = $product['cat_id'];

					if (isset($product['cat_name']) && !empty($product['cat_name'])) {
						$category_name = $product['cat_name'];
					}
				}

				// Log informasi kategori untuk debugging
				log_message('debug', "Product {$product['product_name']} - Category ID: " .
					($category_id ?? 'NULL') . ", Category Name: {$category_name}");

				// Standarisasi format produk
				$standardized_product = [
					'product_id' => $product['product_id'],
					'product_code' => $product['product_code'] ?? 'CODE-' . $product['product_id'],
					'product_name' => $product['product_name'],
					'product_price' => $product['product_price'] ?? 0,
					'category_id' => $category_id,
					'category_name' => $category_name,
					'product_brand' => $brand // Tambahkan brand secara eksplisit
				];

				$products[] = $standardized_product;
			}
		}

		// Log hasil standarisasi
		log_message('debug', "Standardized products: " . count($products));

		// Jika kosong setelah standarisasi, coba refresh cache
		if (empty($products)) {
			log_message('debug', "No valid products after standardization, refreshing cache");
			$this->refreshCache();

			// Coba lagi dengan cache baru
			return $this->getProductsByBrand($brand);
		}

		return $products;
	}

	/**
	 * Get categories from product data
	 * 
	 * @param array $products List of products
	 * @return array List of unique categories
	 */
	public function getCategoriesFromProducts($products)
	{
		log_message('debug', "Starting extraction of categories from " . count($products) . " products");

		$categories = [];
		$used_category_ids = [];

		foreach ($products as $product) {
			// Ekstrak data kategori dari produk
			$category_id = isset($product['category_id']) ? $product['category_id'] : null;
			$category_name = isset($product['category_name']) ? $product['category_name'] : null;
			$product_brand = isset($product['product_brand']) ? $product['product_brand'] : null;

			// Log data kategori untuk debugging
			log_message('debug', "Processing product category - ID: " . ($category_id ?? 'NULL') .
				", Name: " . ($category_name ?? 'NULL') .
				", Brand: " . ($product_brand ?? 'NULL'));

			// Pastikan kategori memiliki ID, nama, dan belum pernah diproses
			if ($category_id && $category_name && !in_array($category_id, $used_category_ids)) {
				$categories[] = [
					'cat_id' => $category_id,
					'cat_name' => $category_name,
					'cat_brand' => $product_brand // Selalu sertakan brand
				];
				$used_category_ids[] = $category_id;

				log_message('debug', "Added category ID: $category_id, Name: $category_name, Brand: $product_brand");
			} else if ($category_id && $category_name) {
				log_message('debug', "Skipping duplicate category ID: $category_id");
			} else {
				log_message('debug', "Skipping category with incomplete data");
			}
		}

		log_message('debug', "Extracted " . count($categories) . " unique categories");
		return $categories;
	}



	/**
	 * Update product data in the MRP system
	 * 
	 * @param int $api_id API ID of the product to update
	 * @param string $product_name New product name
	 * @param float $product_price New product price
	 * @return array API response
	 */
	public function updateProductInMRP($api_id, $product_name, $product_price)
	{
		$endpoint = '/data/updateProduct';
		$params = [
			'product_id' => $api_id,
			'product_name' => $product_name,
			'product_price' => $product_price
		];

		// Use bypass_cache=true to ensure we're sending a fresh request, not using cached data
		$response = $this->request($endpoint, $params, 'POST', true);

		// Log the complete response for debugging
		log_message('debug', 'Update Product API Response: ' . print_r($response, true));

		return $response;
	}

	/**
	 * Check if a product exists in the MRP system
	 * 
	 * @param int $api_id API ID to check
	 * @return bool True if product exists, false otherwise
	 */
	public function productExistsInMRP($api_id)
	{
		$endpoint = '/data/checkProduct';
		$params = ['product_id' => $api_id];

		$response = $this->request($endpoint, $params, 'GET', true);

		return (isset($response['status']) && $response['status'] == 'OK' &&
			isset($response['result']['exists']) && $response['result']['exists'] === true);
	}

	/**
	 * Refresh API cache
	 * 
	 * @return bool Success status
	 */
	public function refreshCache()
	{
		// Clear all product API cache
		$this->ci->cache->delete('products_api_' . md5('/data/getProductsKopitiam'));
		$this->ci->cache->delete('products_api_' . md5('/data/getProductsBakery'));
		$this->ci->cache->delete('products_api_' . md5('/data/getProductsResto'));

		return true;
	}

	/**
	 * Refresh kategori cache dan sinkronisasi ulang
	 * 
	 * @param string $brand Brand untuk disinkronisasi ulang
	 * @return array Kategori baru setelah refresh
	 */
	public function refreshCategoriesCache($brand)
	{
		log_message('debug', "Refreshing categories cache for brand: $brand");

		// Hapus cache terkait kategori dan produk
		$this->refreshCache();

		// Dapatkan produk baru setelah refresh cache
		$products = $this->getProductsByBrand($brand);

		// Debug data produk
		log_message('debug', "Products after cache refresh: " . count($products));
		if (!empty($products)) {
			log_message('debug', "Sample product after refresh: " . print_r($products[0], true));
		}

		// Ekstrak kategori dari produk yang sudah di-refresh
		$categories = [];
		$used_category_ids = [];

		foreach ($products as $product) {
			$category_id = $product['category_id'] ?? null;
			$category_name = $product['category_name'] ?? null;

			if ($category_id && $category_name && !in_array($category_id, $used_category_ids)) {
				$categories[] = [
					'cat_id' => $category_id,
					'cat_name' => $category_name,
					'cat_brand' => $brand
				];
				$used_category_ids[] = $category_id;

				log_message('debug', "Refreshed category - ID: $category_id, Name: $category_name");
			}
		}

		// Debug kategori
		log_message('debug', "Categories after refresh: " . count($categories));

		return $categories;
	}
}