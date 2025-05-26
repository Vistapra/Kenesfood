<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class PromoApi extends CI_Controller
{
	private $api_key = "0a6a2c9d1b10d26ef70f773c68c0f58e5eb85a6624015f12d70c635a23376c1f"; // API key
	private $mrp_api_url = "https://mrp.kenesproduction.com/apis/data/getVoucherCode";
	private $brands = ['bakery', 'kopitiam', 'resto']; // Semua brand yang didukung

	public function __construct()
	{
		parent::__construct();
		$this->load->model('promo/M_Promo');
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
	 * Endpoint untuk mensinkronkan voucher MRP dengan sistem promo 
	 */
	public function syncVouchers()
	{
		// Verifikasi API key
		if (!$this->_verify_api_key()) {
			return;
		}

		// 1. Ambil data voucher dari MRP
		$vouchers = $this->getVouchersFromMRP();

		if (!$vouchers || !isset($vouchers['result']) || !is_array($vouchers['result'])) {
			$this->_send_response(500, 'Gagal mengambil data voucher dari MRP atau format tidak valid');
			return;
		}

		// 2. Lakukan sinkronisasi
		$sync_result = $this->syncPromoWithVouchers($vouchers['result']);

		// 3. Kirim respons
		$this->_send_response(200, 'Sinkronisasi voucher berhasil', [
			'total_vouchers' => count($vouchers['result']),
			'added' => $sync_result['added'],
			'updated' => $sync_result['updated'],
			'deactivated' => $sync_result['deactivated'],
			'timestamp' => date('Y-m-d H:i:s')
		]);
	}

	/**
	 * Ambil data voucher dari API MRP
	 * @return array Data voucher dari MRP atau false jika gagal
	 */
	private function getVouchersFromMRP()
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $this->mrp_api_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, "api:" . $this->api_key);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Hanya untuk development, hilangkan di production

		$response = curl_exec($ch);
		$error = curl_error($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if ($error) {
			log_message('error', 'Curl error saat mengambil voucher: ' . $error);
			return false;
		}

		if ($http_code != 200) {
			log_message('error', 'HTTP error saat mengambil voucher. Code: ' . $http_code);
			return false;
		}

		$vouchers = json_decode($response, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			log_message('error', 'Error saat parsing JSON voucher: ' . json_last_error_msg());
			return false;
		}

		return $vouchers;
	}

	// Perbaikan fungsi syncPromoWithVouchers dalam PromoApi.php
	private function syncPromoWithVouchers($vouchers)
	{
		$stats = ['added' => 0, 'updated' => 0, 'deactivated' => 0];

		// Cari semua voucher MRP yang sudah ada di database
		$this->db->where('is_mrp_voucher', 1);
		$existing_promos = $this->db->get('promos')->result_array();

		// Buat array kode voucher untuk mempermudah pengecekan
		$existing_voucher_codes = [];
		foreach ($existing_promos as $promo) {
			$existing_voucher_codes[$promo['promo_code']] = $promo;
		}

		// Flag untuk menandai voucher yang masih ada di MRP
		$active_voucher_codes = [];

		// Proses setiap voucher dari MRP
		foreach ($vouchers as $voucher) {
			$voucher_code = $voucher['code'];
			$active_voucher_codes[] = $voucher_code;

			// PERBAIKAN BARU: Tentukan jenis promo berdasarkan voucher_type dari MRP
			// Default ke nominal jika tidak dikenali
			$promo_type = 'nominal';

			// PERBAIKAN BARU: Pastikan tipe voucher dikonversi dengan benar
			// Menangani kasus tipe voucher dalam Bahasa Indonesia dan Inggris
			if (isset($voucher['voucher_type'])) {
				$voucher_type_lower = strtolower(trim($voucher['voucher_type']));

				// Penanganan tipe dalam Bahasa Indonesia
				if (
					$voucher_type_lower === 'persen' ||
					$voucher_type_lower === 'persentase' ||
					$voucher_type_lower === 'percent' ||
					$voucher_type_lower === 'percentage'
				) {
					$promo_type = 'percentage';
				} else if (
					$voucher_type_lower === 'rupiah' ||
					$voucher_type_lower === 'nominal' ||
					$voucher_type_lower === 'amount'
				) {
					$promo_type = 'nominal';
				}

				// Log tipe voucher untuk debugging
				log_message('debug', 'Voucher type from MRP: ' . $voucher['voucher_type'] . ' converted to: ' . $promo_type);
			} else {
				log_message('warning', 'No voucher_type specified for voucher: ' . $voucher_code . ', defaulting to nominal');
			}

			// Siapkan data promo
			$promo_data = [
				'promo_code' => $voucher_code,
				'promo_name' => $voucher['voucher_name'] ?? 'Voucher MRP ' . $voucher_code,
				'promo_type' => $promo_type,
				'is_mrp_voucher' => 1,
				'voucher_id' => $voucher['voucher_id'],
				'voucher_type' => $voucher['voucher_type'] ?? null, // Simpan tipe asli dari MRP
				'sync_status' => 'synced',
				'last_sync' => date('Y-m-d H:i:s'),
				'supported_brands' => implode(',', $this->brands) // Semua brand didukung
			];

			// PERBAIKAN BARU: Tambahkan nilai diskon sesuai tipe voucher dengan pengecekan lebih komprehensif
			if ($promo_type == 'percentage') {
				// Cek berbagai kemungkinan nama field untuk diskon persentase
				if (isset($voucher['discount'])) {
					$promo_data['promo_value'] = floatval($voucher['discount']);
				} elseif (isset($voucher['discount_percentage'])) {
					$promo_data['promo_value'] = floatval($voucher['discount_percentage']);
				} elseif (isset($voucher['discount_percent'])) {
					$promo_data['promo_value'] = floatval($voucher['discount_percent']);
				} elseif (isset($voucher['discount_value'])) {
					$promo_data['promo_value'] = floatval($voucher['discount_value']);
				} else {
					$promo_data['promo_value'] = 0;
					log_message('warning', 'No discount value found for percentage voucher: ' . $voucher_code);
				}

				// Maximum discount bisa ditambahkan jika tersedia
				if (isset($voucher['max_discount']) && $voucher['max_discount'] > 0) {
					$promo_data['maximum_discount'] = floatval($voucher['max_discount']);
				} elseif (isset($voucher['maximum_discount']) && $voucher['maximum_discount'] > 0) {
					$promo_data['maximum_discount'] = floatval($voucher['maximum_discount']);
				}
			} else {
				// Tipe nominal - cek berbagai kemungkinan nama field
				if (isset($voucher['discount_amount'])) {
					$promo_data['promo_value'] = floatval($voucher['discount_amount']);
				} elseif (isset($voucher['discount_value'])) {
					$promo_data['promo_value'] = floatval($voucher['discount_value']);
				} elseif (isset($voucher['discount'])) {
					$promo_data['promo_value'] = floatval($voucher['discount']);
				} else {
					$promo_data['promo_value'] = 0;
					log_message('warning', 'No discount value found for nominal voucher: ' . $voucher_code);
				}

				// Pastikan tidak ada maximum_discount untuk tipe nominal
				$promo_data['maximum_discount'] = null;
			}

			// Log data promo untuk debugging
			log_message('debug', 'Promo data prepared: ' . json_encode($promo_data));

			// Set periode voucher (default 1 tahun jika tidak ada)
			$now = date('Y-m-d H:i:s');
			$one_year_later = date('Y-m-d H:i:s', strtotime('+1 year'));
			$promo_data['start_date'] = $voucher['start_date'] ?? $now;
			$promo_data['end_date'] = $voucher['end_date'] ?? $one_year_later;

			// Set quota jika tersedia
			if (isset($voucher['usage_limit']) && $voucher['usage_limit'] > 0) {
				$promo_data['quota'] = $voucher['usage_limit'];
			}

			// Jika voucher sudah ada di database, update
			if (isset($existing_voucher_codes[$voucher_code])) {
				$existing_promo = $existing_voucher_codes[$voucher_code];
				$promo_id = $existing_promo['promo_id'];

				// Update hanya jika ada perubahan
				$has_changes = false;
				foreach ($promo_data as $key => $value) {
					if (!isset($existing_promo[$key]) || $existing_promo[$key] != $value) {
						$has_changes = true;
						break;
					}
				}

				if ($has_changes) {
					$this->db->where('promo_id', $promo_id);
					$this->db->update('promos', $promo_data);
					$stats['updated']++;
					log_message('debug', 'Updated voucher promo: ' . $voucher_code . ' with type: ' . $promo_type);
				}
			}
			// Jika voucher belum ada, tambahkan untuk brand pertama saja (akan mendukung semua brand)
			else {
				// Gunakan brand pertama sebagai default, tetapi voucher akan mendukung semua brand
				$promo_data['promo_brand'] = $this->brands[0]; // Brand pertama sebagai default
				$promo_data['promo_status'] = 'active';
				$promo_data['created_at'] = date('Y-m-d H:i:s');
				$promo_data['created_by'] = 0; // System created

				$this->db->insert('promos', $promo_data);
				$stats['added']++;
				log_message('debug', 'Added new voucher promo: ' . $voucher_code . ' with type: ' . $promo_type);
			}
		}

		// Nonaktifkan voucher yang tidak ada lagi di MRP
		foreach ($existing_promos as $promo) {
			if (!in_array($promo['promo_code'], $active_voucher_codes) && $promo['promo_status'] == 'active') {
				$this->db->where('promo_id', $promo['promo_id']);
				$this->db->update('promos', [
					'promo_status' => 'inactive',
					'sync_status' => 'deleted',
					'last_sync' => date('Y-m-d H:i:s')
				]);
				$stats['deactivated']++;
				log_message('debug', 'Deactivated voucher promo: ' . $promo['promo_code']);
			}
		}

		return $stats;
	}

	/**
	 * Endpoint untuk menandai voucher sebagai telah digunakan
	 */
	public function markVoucherAsUsed()
	{
		// Verifikasi API key
		if (!$this->_verify_api_key()) {
			return;
		}

		// Dapatkan data dari request
		$json_data = $this->security->xss_clean($this->input->raw_input_stream);
		$data = json_decode($json_data, true);

		if (!$data || !isset($data['voucher_code']) || !isset($data['order_id'])) {
			$this->_send_response(400, 'Data tidak valid. Voucher code dan order_id diperlukan');
			return;
		}

		$voucher_code = $data['voucher_code'];
		$order_id = $data['order_id'];

		// Cari promo berdasarkan kode voucher
		$this->db->where('promo_code', $voucher_code);
		$this->db->where('is_mrp_voucher', 1);
		$promo = $this->db->get('promos')->row_array();

		if (!$promo) {
			$this->_send_response(404, 'Voucher tidak ditemukan');
			return;
		}

		// Cek apakah voucher sudah pernah digunakan untuk order ini
		$this->db->where('promo_id', $promo['promo_id']);
		$this->db->where('order_id', $order_id);
		$existing_usage = $this->db->get('promo_usage')->row_array();

		if ($existing_usage) {
			$this->_send_response(200, 'Voucher sudah digunakan untuk order ini', [
				'voucher_code' => $voucher_code,
				'order_id' => $order_id,
				'usage_id' => $existing_usage['usage_id'],
				'usage_time' => $existing_usage['usage_time']
			]);
			return;
		}

		// Catat penggunaan voucher
		$usage_data = [
			'promo_id' => $promo['promo_id'],
			'order_id' => $order_id,
			'discount_amount' => $data['discount_amount'] ?? $promo['promo_value'],
			'usage_time' => date('Y-m-d H:i:s'),
			'counted_in_usage' => 1
		];

		$this->db->insert('promo_usage', $usage_data);
		$usage_id = $this->db->insert_id();

		// Update usage_count di tabel promos
		$this->db->set('usage_count', 'usage_count + 1', FALSE);
		$this->db->where('promo_id', $promo['promo_id']);
		$this->db->update('promos');

		// Kirim notifikasi ke MRP jika perlu
		$mrp_notify_result = $this->notifyMRPVoucherUsed($voucher_code, $order_id, $data);

		$this->_send_response(200, 'Voucher berhasil ditandai sebagai digunakan', [
			'voucher_code' => $voucher_code,
			'order_id' => $order_id,
			'usage_id' => $usage_id,
			'usage_time' => $usage_data['usage_time'],
			'mrp_notification' => $mrp_notify_result
		]);
	}

	/**
	 * Kirim notifikasi ke MRP bahwa voucher telah digunakan
	 * @param string $voucher_code Kode voucher
	 * @param int $order_id ID order
	 * @param array $data Data tambahan
	 * @return array Status notifikasi
	 */
	private function notifyMRPVoucherUsed($voucher_code, $order_id, $data = [])
	{
		// URL endpoint untuk notifikasi ke MRP (ganti dengan URL sebenarnya)
		$notification_url = "https://mrp.kenesproduction.com/apis/voucher/markAsUsed";

		$notification_data = [
			'voucher_code' => $voucher_code,
			'order_id' => $order_id,
			'used_at' => date('Y-m-d H:i:s'),
			'order_amount' => $data['order_amount'] ?? 0,
			'discount_amount' => $data['discount_amount'] ?? 0
		];

		// Kirim notifikasi hanya jika URL notifikasi tersedia
		if (!$notification_url) {
			return ['success' => false, 'message' => 'Notification URL not configured'];
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $notification_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification_data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, "api:" . $this->api_key);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Hanya untuk development

		$response = curl_exec($ch);
		$error = curl_error($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if ($error) {
			log_message('error', 'Curl error saat notifikasi MRP: ' . $error);
			return ['success' => false, 'message' => 'Curl error: ' . $error];
		}

		return [
			'success' => ($http_code >= 200 && $http_code < 300),
			'http_code' => $http_code,
			'response' => json_decode($response, true)
		];
	}

	/**
	 * Endpoint status sinkronisasi terakhir
	 */
	public function syncStatus()
	{
		// Verifikasi API key (wajib untuk endpoint ini)
		if (!$this->_verify_api_key()) {
			return;
		}

		// Dapatkan statistik voucher MRP
		$stats = [
			'total' => 0,
			'active' => 0,
			'inactive' => 0,
			'last_sync' => null
		];

		$this->db->select('COUNT(*) as total');
		$this->db->where('is_mrp_voucher', 1);
		$stats['total'] = $this->db->get('promos')->row()->total;

		$this->db->select('COUNT(*) as active');
		$this->db->where('is_mrp_voucher', 1);
		$this->db->where('promo_status', 'active');
		$stats['active'] = $this->db->get('promos')->row()->active;

		$this->db->select('MAX(last_sync) as last_sync');
		$this->db->where('is_mrp_voucher', 1);
		$last_sync = $this->db->get('promos')->row()->last_sync;
		$stats['last_sync'] = $last_sync;

		$stats['inactive'] = $stats['total'] - $stats['active'];

		$this->_send_response(200, 'Statistik sinkronisasi voucher MRP', $stats);
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
	 * Verifikasi API key dari berbagai sumber
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
		$this->_send_response(401, 'API key diperlukan. Tambahkan dengan salah satu cara berikut: 1. Header Authorization: "Basic ' . base64_encode("api:$api_key") . '" 2. Bearer token: "Bearer ' . $api_key . '" 3. Parameter URL: ?api_key=' . $api_key . ' 4. Custom header: X-API-Key: ' . $api_key . ' 5. Di JSON body: {"api_key": "' . $api_key . '", ...}');
		return false;
	}

	/**
	 * Endpoint untuk mengubah status voucher MRP
	 */
	public function updateVoucherStatus()
	{
		// Verifikasi API key
		if (!$this->_verify_api_key()) {
			return;
		}

		// Dapatkan data dari request
		$json_data = $this->security->xss_clean($this->input->raw_input_stream);
		$data = json_decode($json_data, true);

		if (!$data || !isset($data['promo_id']) || !isset($data['status'])) {
			$this->_send_response(400, 'Data tidak valid. Promo ID dan status diperlukan');
			return;
		}

		$promo_id = $data['promo_id'];
		$status = $data['status'];

		// Validasi status
		if ($status !== 'active' && $status !== 'inactive') {
			$this->_send_response(400, 'Status tidak valid. Gunakan "active" atau "inactive"');
			return;
		}

		// Cari promo berdasarkan ID
		$this->db->where('promo_id', $promo_id);
		$this->db->where('is_mrp_voucher', 1);
		$promo = $this->db->get('promos')->row_array();

		if (!$promo) {
			$this->_send_response(404, 'Voucher MRP tidak ditemukan');
			return;
		}

		// Update status
		$this->db->where('promo_id', $promo_id);
		$this->db->update('promos', [
			'promo_status' => $status,
			'updated_at' => date('Y-m-d H:i:s')
		]);

		$this->_send_response(200, 'Status voucher berhasil diperbarui', [
			'promo_id' => $promo_id,
			'new_status' => $status,
			'timestamp' => date('Y-m-d H:i:s')
		]);
	}
}
