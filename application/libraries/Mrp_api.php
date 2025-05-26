<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Mrp_api
{
	protected $ci;
	private $api_url = 'https://mrp.kenesproduction.com/apis';
	private $username = 'api'; // Username API
	private $password = '0a6a2c9d1b10d26ef70f773c68c0f58e5eb85a6624015f12d70c635a23376c1f'; // Password API
	private $cache_expiry = 300; // 5 menit cache

	public function __construct()
	{
		$this->ci = &get_instance();
		$this->ci->load->helper('url');
		$this->ci->load->library('session');
		$this->ci->load->driver('cache', array('adapter' => 'file'));
	}

	/**
	 * Registrasi member baru ke MRP API
	 * 
	 * @param array $memberData Data member untuk didaftarkan
	 * @return array Response dari API
	 */
	public function registerMember($memberData)
	{
		log_message('debug', 'Registering member to MRP: ' . json_encode($memberData));

		// Endpoint untuk registrasi member
		$endpoint = '/customers/add';

		// Format data sesuai permintaan API MRP - menggunakan format yang diminta
		$data = [
			'data' => [
				'name' => $memberData['name'] ?? '',
				'phone' => $this->formatPhoneNumber($memberData['phone'] ?? ''),
				'email' => $memberData['email'] ?? '',
				'gender' => $memberData['gender'] ?? 'L', // Default: Laki-laki (L)
				'work' => $memberData['work'] ?? '',
				'birthPlace' => $memberData['birthPlace'] ?? '',
				'dateBirth' => !empty($memberData['dateBirth']) ? date('Y-m-d', strtotime($memberData['dateBirth'])) : null,
				'address' => $memberData['address'] ?? '',
				'recentAddress' => $memberData['recentAddress'] ?? '',
				'source' => 'website' // Sumber registrasi
			]
		];

		// Tambahkan password jika ada - namun simpan di luar wrapper 'data'
		if (isset($memberData['password'])) {
			$data['password'] = $memberData['password'];
		}

		log_message('debug', 'Data to be sent to MRP: ' . json_encode($data));

		// Kirim request dengan metode POST
		$response = $this->request($endpoint, $data, 'POST');

		// Log response
		log_message('debug', 'MRP Register Response: ' . print_r($response, true));

		return $response;
	}

	private function formatPhoneNumber($phone)
	{
		// Hapus semua non-angka
		$phone = preg_replace('/[^0-9]/', '', $phone);

		// Format untuk Indonesia: pastikan format 08xxx
		if (substr($phone, 0, 2) === '62') {
			$phone = '0' . substr($phone, 2);
		} else if (substr($phone, 0, 3) === '+62') {
			$phone = '0' . substr($phone, 3);
		} else if (substr($phone, 0, 1) !== '0') {
			$phone = '0' . $phone;
		}

		return $phone;
	}
	public function request($endpoint, $params = [], $method = 'GET')
	{
		// Generate cache key berdasarkan endpoint dan params
		$cache_key = 'mrp_' . md5($endpoint . json_encode($params) . $method);

		// Cek cache dulu, kecuali untuk operasi write (POST/PUT) dan verifikasi
		$skip_cache = ($method !== 'GET' || in_array($endpoint, ['/auth/validateOTP', '/data/registerMember']));

		if (!$skip_cache) {
			$cached_response = $this->ci->cache->get($cache_key);
			if ($cached_response) {
				// Periksa apakah data cache lengkap (memiliki customer_point_details)
				if (strpos($endpoint, '/data/getCustomerById/') === 0 || strpos($endpoint, '/data/getCustomerByPhone/') === 0) {
					if (
						isset($cached_response['result']['customer_point']) &&
						isset($cached_response['result']['customer_point']['customer_point_details'])
					) {
						log_message('debug', 'MRP API Cache Hit (Complete): ' . $endpoint);
						return $cached_response;
					}

					// Data cache tidak lengkap, hapus cache
					log_message('debug', 'MRP API Cache Hit but incomplete, deleting cache: ' . $endpoint);
					$this->ci->cache->delete($cache_key);
				} else {
					log_message('debug', 'MRP API Cache Hit: ' . $endpoint);
					return $cached_response;
				}
			}
		}

		$url = $this->api_url . $endpoint;

		$ch = curl_init();

		if ($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
		} else {
			if (!empty($params)) {
				$url .= '?' . http_build_query($params);
			}
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Accept: application/json'
		]);
		curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 detik timeout
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		$response = curl_exec($ch);
		$error = curl_error($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if ($error) {
			log_message('error', 'MRP API Request Error: ' . $error);
			return [
				'status' => 'ERROR',
				'message' => $error,
				'result' => null
			];
		}

		$decoded_response = json_decode($response, true);

		// Log raw response for debugging
		log_message('debug', 'MRP API Raw Response: ' . print_r($decoded_response, true));

		// Simpan ke cache jika response sukses dan bukan endpoint write/auth
		if (
			!$skip_cache &&
			isset($decoded_response['status']) && $decoded_response['status'] == 'OK'
		) {
			// Untuk endpoint customer, pastikan memiliki customer_point_details sebelum di-cache
			if ((strpos($endpoint, '/data/getCustomerById/') === 0 || strpos($endpoint, '/data/getCustomerByPhone/') === 0)) {
				if (
					isset($decoded_response['result']['customer_point']) &&
					isset($decoded_response['result']['customer_point']['customer_point_details'])
				) {
					$this->ci->cache->save($cache_key, $decoded_response, $this->cache_expiry);
				}
			} else {
				$this->ci->cache->save($cache_key, $decoded_response, $this->cache_expiry);
			}
		}

		return $decoded_response;
	}
	public function getMemberByPhone($phone)
	{
		log_message('debug', 'Getting member by phone: ' . $phone);
		// Gunakan endpoint ini karena memberikan data lengkap
		$endpoint = '/data/getCustomerByPhone/' . urlencode($phone);
		$response = $this->request($endpoint);

		if (isset($response['status']) && $response['status'] == 'OK' && !empty($response['result'])) {
			$member = $response['result'];
			return $this->formatMemberData($member);
		}

		return null;
	}

	/**
	 * Mendapatkan data member berdasarkan ID
	 * 
	 * @param int $id ID member di MRP
	 * @return array|null Data member atau null jika tidak ditemukan
	 */
	public function getMemberById($id)
	{
		// Hapus semua cache terkait user ini
		$cache_key = 'mrp_' . md5('/data/getCustomerById/' . $id);
		$this->ci->cache->delete($cache_key);

		// Gunakan endpoint getCustomerById dulu
		$endpoint = '/data/getCustomerById/' . $id;
		$response = $this->request($endpoint);

		log_message('debug', 'getMemberById Raw Response: ' . print_r($response, true));

		// Jika respons berhasil tapi tidak lengkap (tidak ada customer_point_details)
		if (isset($response['status']) && $response['status'] == 'OK' && !empty($response['result'])) {
			if (!isset($response['result']['customer_point']) || !isset($response['result']['customer_point']['customer_point_details'])) {
				// Coba gunakan getCustomerByPhone sebagai alternatif
				$phone = isset($response['result']['phone']) ? $response['result']['phone'] : '';
				if (!empty($phone)) {
					$phone_response = $this->getMemberByPhone($phone);
					if ($phone_response) {
						return $phone_response;
					}
				}
			}

			$member = $response['result'];
			return $this->formatMemberData($member);
		}

		return null;
	}

	/**
	 * Format data member dari API ke struktur yang dibutuhkan aplikasi
	 * 
	 * @param array $member Data member dari API
	 * @return array Data member terformat
	 */
	private function formatMemberData($member)
	{
		// Extract data from the expected structure
		$points = isset($member['customer_point']['point_amount'])
			? $member['customer_point']['point_amount']
			: (isset($member['point_amount']) ? $member['point_amount'] : 0);

		$transactions = [];
		$total_transactions = 0;
		$points_earned = 0;
		$points_used = 0;

		// Periksa apakah ada customer_point_details
		if (isset($member['customer_point']['customer_point_details'])) {
			$total_transactions = count($member['customer_point']['customer_point_details']);

			foreach ($member['customer_point']['customer_point_details'] as $detail) {
				if (isset($detail['sale'])) {
					$transaction = $this->formatTransactionData($detail, $detail['sale']);
					$transactions[] = $transaction;

					// Hitung poin masuk dan keluar
					if ($detail['status'] == 1) {
						$points_earned += $detail['point'];
					} else {
						$points_used += $detail['point'];
					}
				}
			}
		}

		// Sort transactions by date, newest first
		if (!empty($transactions)) {
			usort($transactions, function ($a, $b) {
				return strtotime($b['purchase_date']) - strtotime($a['purchase_date']);
			});
		}

		return [
			'user_id' => $member['id'] ?? null,
			'mrp_id' => $member['id'] ?? null,
			'user_name' => $member['name'] ?? '',
			'fullname' => $member['name'] ?? '',
			'user_email' => $member['email'] ?? '',
			'email' => $member['email'] ?? '',
			'phone' => $member['phone'] ?? '',
			'point_amount' => $points,
			'points_earned' => $points_earned,
			'points_used' => $points_used,
			'address' => $member['address'] ?? '',
			'gender' => $member['gender'] ?? '',
			'date_of_birth' => !empty($member['dateBirth']) ? date('Y-m-d', strtotime($member['dateBirth'])) : null,
			'transactions' => $transactions,
			'total_transactions' => $total_transactions
		];
	}

	/**
	 * Format data transaksi dari API ke struktur yang dibutuhkan aplikasi
	 * 
	 * @param array $pointDetail Data detail point
	 * @param array $sale Data transaksi dari API
	 * @return array Data transaksi terformat
	 */
	private function formatTransactionData($pointDetail, $sale)
	{
		// Status Poin: 0 = Point Keluar, 1 = Point Masuk
		$point_type = ($pointDetail['status'] == 0) ? 'Point Keluar' : 'Point Masuk';

		// Detail items from sale_details
		$items = [];
		if (isset($sale['sale_details'])) {
			$details = $sale['sale_details'];

			// Handle nested arrays in sale_details
			if (is_array($details) && !empty($details)) {
				// If first element is array, it's nested
				if (isset($details[0]) && is_array($details[0]) && !isset($details[0]['id'])) {
					$details = $details[0];
				}

				foreach ($details as $item) {
					if (is_array($item)) {
						$subtotal = isset($item['price']) && isset($item['qty']) ? ($item['price'] * $item['qty']) : 0;

						$items[] = [
							'product_id' => $item['id'] ?? null,
							'product_name' => $item['product_name'] ?? '',
							'product_qty' => $item['qty'] ?? 0,
							'product_price' => $item['price'] ?? 0,
							'discount' => $item['discount'] ?? 0,
							'subtotal' => $subtotal,
							'product_pict' => 'default-product.jpg' // Default image since API doesn't provide images
						];
					}
				}
			}
		}

		// Jika ada detail pembatalan
		$canceled = isset($pointDetail['canceled']) && $pointDetail['canceled'] == 1;
		if ($canceled) {
			$point_type = 'Dibatalkan';
		}

		return [
			'purchase_id' => $sale['id'] ?? null,
			'transaction_id' => $sale['id'] ?? null,
			'purchase_code' => $sale['code'] ?? '',
			'purchase_date' => isset($sale['sale_date']) ? date('Y-m-d', strtotime($sale['sale_date'])) : date('Y-m-d'),
			'purchase_status' => $point_type,
			'purchase_total_amount' => $sale['total_amount'] ?? 0,
			'subtotal' => $sale['subtotal'] ?? 0,
			'discount' => $sale['disc'] ?? 0,
			'point_earned' => $pointDetail['point'] ?? 0,
			'previous_point' => $pointDetail['previous_point'] ?? 0,
			'remaining_points' => $pointDetail['remaining_points'] ?? 0,
			'is_point_used' => ($pointDetail['status'] == 0), // true if status = 0 (point used)
			'is_point_earned' => ($pointDetail['status'] == 1), // true if status = 1 (point earned)
			'is_canceled' => $canceled,
			'point_status' => $pointDetail['status'] ?? 0,
			'point_id' => $pointDetail['id'] ?? 0,
			'items' => $items
		];
	}

	/**
	 * Mendapatkan detail transaksi berdasarkan ID transaksi
	 * 
	 * @param int $transactionId ID transaksi
	 * @return array|null Detail transaksi atau null jika tidak ditemukan
	 */
	public function getTransactionDetails($transactionId)
	{
		// Coba cari transaksi dari session atau data cached member
		$member_session = $this->ci->session->userdata('member');
		if ($member_session && isset($member_session['user_id'])) {
			$member = $this->getMemberById($member_session['user_id']);

			if ($member && isset($member['transactions'])) {
				// Cari transaksi berdasarkan ID
				foreach ($member['transactions'] as $transaction) {
					if ($transaction['transaction_id'] == $transactionId) {
						// Format items untuk tampilan
						if (!empty($transaction['items'])) {
							foreach ($transaction['items'] as &$item) {
								// Pastikan memiliki subtotal
								if (!isset($item['subtotal']) && isset($item['product_price']) && isset($item['product_qty'])) {
									$item['subtotal'] = $item['product_price'] * $item['product_qty'];
								}
							}
						}

						return [
							'transaction' => $transaction,
							'items' => $transaction['items'] ?? []
						];
					}
				}
			}
		}

		// Jika tidak ditemukan di data member, coba ambil dari API langsung
		// Endpoint API untuk detail transaksi (jika tersedia)
		try {
			$endpoint = '/data/getSaleById/' . $transactionId;
			$response = $this->request($endpoint);

			if (isset($response['status']) && $response['status'] == 'OK' && !empty($response['result'])) {
				$sale = $response['result'];

				// Cari point detail untuk sale ini
				$point_detail = null;
				$point_status = 1; // Default: point masuk

				// Format data transaksi
				$transaction = [
					'purchase_id' => $sale['id'] ?? null,
					'transaction_id' => $sale['id'] ?? null,
					'purchase_code' => $sale['code'] ?? '',
					'purchase_date' => isset($sale['sale_date']) ? date('Y-m-d', strtotime($sale['sale_date'])) : date('Y-m-d'),
					'purchase_status' => ($point_status == 0) ? 'Point Keluar' : 'Point Masuk',
					'purchase_total_amount' => $sale['total_amount'] ?? 0,
					'subtotal' => $sale['subtotal'] ?? 0,
					'discount' => $sale['disc'] ?? 0,
					'point_earned' => 0,
					'is_point_used' => ($point_status == 0),
					'is_point_earned' => ($point_status == 1),
					'is_canceled' => false,
					'point_status' => $point_status
				];

				// Format items
				$items = [];
				if (isset($sale['sale_details']) && is_array($sale['sale_details'])) {
					foreach ($sale['sale_details'] as $item) {
						$subtotal = isset($item['price']) && isset($item['qty']) ? ($item['price'] * $item['qty']) : 0;

						$items[] = [
							'product_id' => $item['id'] ?? null,
							'product_name' => $item['product_name'] ?? '',
							'product_qty' => $item['qty'] ?? 0,
							'product_price' => $item['price'] ?? 0,
							'discount' => $item['discount'] ?? 0,
							'subtotal' => $subtotal,
							'product_pict' => 'default-product.jpg'
						];
					}
				}

				return [
					'transaction' => $transaction,
					'items' => $items
				];
			}
		} catch (Exception $e) {
			log_message('error', 'Error getting transaction details: ' . $e->getMessage());
		}

		// Data fallback jika tidak ditemukan
		return [
			'transaction' => [
				'purchase_id' => $transactionId,
				'transaction_id' => $transactionId,
				'purchase_code' => 'N/A',
				'purchase_date' => date('Y-m-d'),
				'purchase_status' => 'Tidak Diketahui',
				'purchase_total_amount' => 0,
				'subtotal' => 0,
				'discount' => 0,
				'point_earned' => 0,
				'is_point_used' => false,
				'is_point_earned' => false,
				'is_canceled' => false,
				'point_status' => 0
			],
			'items' => []
		];
	}

	/**
	 * Mendapatkan level member berdasarkan jumlah poin
	 * 
	 * @param int $points Jumlah poin
	 * @return string Level member (Bronze, Silver, Gold)
	 */
	public function getMemberLevel($points)
	{
		if ($points >= 20000) {
			return 'Platinum';
		} else if ($points >= 10000) {
			return 'Gold';
		} else if ($points >= 5000) {
			return 'Silver';
		} else {
			return 'Bronze';
		}
	}

	/**
	 * Merefresh cache data member
	 * 
	 * @param int $memberId ID member di MRP
	 * @return bool Success status
	 */
	public function refreshMemberData($memberId)
	{
		// Hapus cache untuk endpoint getCustomerById
		$cache_key1 = 'mrp_' . md5('/data/getCustomerById/' . $memberId);
		$this->ci->cache->delete($cache_key1);

		// Cari phone number dari session
		$member_session = $this->ci->session->userdata('member');
		if ($member_session && isset($member_session['user_phone'])) {
			$phone = $member_session['user_phone'];
			$cache_key2 = 'mrp_' . md5('/data/getCustomerByPhone/' . $phone);
			$this->ci->cache->delete($cache_key2);
		}

		// Ambil ulang data member
		$member = $this->getMemberById($memberId);

		return ($member !== null);
	}
}
