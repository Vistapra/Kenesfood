<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Promo Controller - Endpoint untuk pengelolaan dan pengambilan promo
 */
class BadgePromo extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();

		// Pastikan model dimuat dengan benar
		$this->load->model('promo/M_Promo', 'M_Promo');
		$this->load->database();

		// Set header untuk JSON response
		header('Content-Type: application/json');
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

		// Handle OPTIONS request untuk CORS
		if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			exit();
		}
	}

	public function getActivePromos()
	{
		try {
			// Cek apakah hanya mengecek ketersediaan endpoint
			if ($this->input->get('check') === 'availability') {
				$this->output
					->set_status_header(200)
					->set_output(json_encode([
						'success' => true,
						'message' => 'Endpoint tersedia'
					]));
				return;
			}

			// Ambil parameter brand dari request
			$brand = $this->input->get('brand');
			if (empty($brand)) {
				$this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Parameter brand diperlukan'
					]));
				return;
			}

			// Log untuk debugging
			log_message('debug', 'Mengambil promo aktif untuk brand: ' . $brand);

			// Ambil semua promo aktif untuk brand ini
			$params = [
				'promo_brand' => $brand,
				'promo_status' => 'active',
				'active_now' => true
			];

			$activePromos = $this->M_Promo->getAllPromos($params);
			log_message('debug', 'Menemukan ' . count($activePromos) . ' promo aktif');

			// Transform data untuk konsumsi frontend
			$transformedPromos = [];
			foreach ($activePromos as $promo) {
				// Ambil info produk dan kategori yang terkait dengan promo
				$promoProducts = $this->M_Promo->getPromoProducts($promo['promo_id']);
				$promoCategories = $this->M_Promo->getPromoCategories($promo['promo_id']);

				// Ekstrak ID produk dan kategori
				$productIds = array_column($promoProducts, 'product_id');
				$categoryIds = array_column($promoCategories, 'cat_id');

				// Data tambahan berdasarkan tipe promo
				$additionalInfo = [
					'promo_products' => $productIds,
					'promo_categories' => $categoryIds
				];

				// Tambahkan informasi spesifik berdasarkan tipe promo
				if ($promo['promo_type'] === 'percentage') {
					$additionalInfo['max_discount'] = $promo['maximum_discount'];
				} else if ($promo['promo_type'] === 'bundling') {
					$bundleDetails = $this->M_Promo->getPromoBundles($promo['promo_id']);
					$additionalInfo['bundle_details'] = $bundleDetails;
				} else if ($promo['promo_type'] === 'bogo') {
					$bogoDetails = $this->M_Promo->getPromoBogo($promo['promo_id']);
					$additionalInfo['bogo_details'] = $bogoDetails;
				}

				// Format data promo untuk frontend
				$transformedPromos[] = [
					'promo_id' => $promo['promo_id'],
					'code' => $promo['promo_code'],
					'name' => $promo['promo_name'],
					'type' => $promo['promo_type'],
					'value' => $promo['promo_type'] === 'percentage' ? floatval($promo['promo_value']) : intval($promo['promo_value']),
					'minimum_order' => floatval($promo['minimum_order']),
					'description' => $promo['description'],
					'start_date' => $promo['start_date'],
					'end_date' => $promo['end_date'],
					'additional_info' => $additionalInfo
				];
			}

			// Return response
			$this->output
				->set_status_header(200)
				->set_output(json_encode([
					'success' => true,
					'message' => 'Promo berhasil diambil',
					'data' => $transformedPromos
				]));
		} catch (Exception $e) {
			log_message('error', 'Error dalam getActivePromos: ' . $e->getMessage());
			$this->output
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Terjadi kesalahan: ' . $e->getMessage()
				]));
		}
	}
}
