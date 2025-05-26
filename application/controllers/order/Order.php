<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');
require_once(APPPATH . 'controllers/base/PublicBase.php');

class Order extends ApplicationBase
{
	private $cartCache = [];
	private $cacheExpiry = 300;
	public function __construct()
	{
		parent::__construct();

		$this->load->model('M_categories');
		$this->load->model('M_Order_Detail');
		$this->load->model('order/M_Order', 'M_Order');
		$this->load->model('M_products');
		$this->load->model("order/cashier/M_Product", "MOC_Product");
		$this->load->model("order/cashier/M_Order_Detail", "MOC_Order_Detail");
		$this->load->model("M_Package");
		$this->load->model("M_Package_Category");
		$this->load->model("settings/M_outlets", "MS_Outlet");
		$this->load->library("libgeneralmap");
		$this->load->driver('cache', array('adapter' => 'file'));
		$this->load->model('order/M_Order_History', 'M_Order_History');
		$this->load->model('promo/M_Promo', 'M_Promo');
	}

	public function session()
	{
		$this->output->set_content_type('application/json');

		$params = [
			"outlet_id" => $this->input->get('outletId'),
			"table_id" => $this->input->get('tableId'),
			"brand" => $this->input->get('brand')
		];

		foreach ($params as $key => $value) {
			if (empty($value)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"code" => "001",
						"message" => "Missing required parameter: {$key}"
					]));
			}
		}

		// Validate brand type
		$validBrands = ['kopitiam', 'bakery', 'resto'];
		if (!in_array($params['brand'], $validBrands)) {
			return $this->output
				->set_status_header(400)
				->set_output(json_encode([
					"success" => false,
					"code" => "002",
					"message" => "Invalid brand type"
				]));
		}

		// Validate outlet exists
		$outlet = $this->MS_Outlet->get_detail_outlet([
			"outlet_id" => $params["outlet_id"]
		]);

		if (!$outlet) {
			return $this->output
				->set_status_header(404)
				->set_output(json_encode([
					"success" => false,
					"code" => "003",
					"message" => "Outlet not found"
				]));
		}

		// Validate table number
		if ($params["table_id"] > $outlet["count_table"]) {
			return $this->output
				->set_status_header(400)
				->set_output(json_encode([
					"success" => false,
					"code" => "004",
					"message" => "Invalid table number"
				]));
		}

		$timezone = new DateTimeZone('Asia/Jakarta');
		$currentTime = new DateTime('now', $timezone);
		$openTime = new DateTime($outlet["hour_open"], $timezone);
		$closeTime = new DateTime($outlet["hour_close"], $timezone);

		if ($currentTime < $openTime || $currentTime > $closeTime) {
			return $this->output
				->set_status_header(403)
				->set_output(json_encode([
					"success" => false,
					"code" => "005",
					"message" => "Outlet is currently closed. Operating hours: " .
						$outlet["hour_open"] . " - " . $outlet["hour_close"]
				]));
		}

		// Check existing session
		$params["deleted_at"] = NULL;
		$activeSession = $this->M_Order->getOne($params, [
			"id",
			"name",
			"status",
			"expire_at",
			"created_at"
		]);

		if (empty($activeSession)) {
			return $this->output
				->set_status_header(404)
				->set_output(json_encode([
					"success" => false,
					"code" => "006",
					"message" => "No active session found"
				]));
		}

		// Check session expiration
		$datetime_current = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
		$datetime_exp = new DateTime($activeSession["expire_at"]);

		if ($datetime_exp <= $datetime_current) {
			$this->M_Order->deleteById($activeSession["id"]);

			return $this->output
				->set_status_header(404)
				->set_output(json_encode([
					"success" => false,
					"code" => "007",
					"message" => "Session has expired"
				]));
		}

		$cartItems = [];
		if ($activeSession["status"] == $this->M_Order::STATUS_RESERVED) {
			$cartItems = $this->M_Order_Detail->getAll([
				"where" => [
					"order_id" => $activeSession["id"],
					"deleted_at" => NULL
				]
			]);

			// Get package details if any
			foreach ($cartItems as &$item) {
				if (!empty($item["parent_id"])) {
					$packageDetails = $this->M_Order_Detail->getAll([
						"where" => [
							"parent_id" => $item["parent_id"],
							"deleted_at" => NULL
						]
					]);
					$item["package_items"] = $packageDetails;
				}
			}
		}

		$sessionStart = new DateTime($activeSession["created_at"]);
		$sessionDuration = $datetime_current->diff($sessionStart);

		$response = [
			"success" => true,
			"code" => "000",
			"message" => "Session retrieved successfully",
			"data" => [
				"session" => [
					"id" => $activeSession["id"],
					"name" => $activeSession["name"],
					"status" => $activeSession["status"],
					"expire_at" => $activeSession["expire_at"],
					"duration" => [
						"minutes" => $sessionDuration->i,
						"seconds" => $sessionDuration->s
					]
				],
				"outlet" => [
					"name" => $outlet["outlet_name"],
					"address" => $outlet["outlet_address"],
					"operating_hours" => [
						"open" => $outlet["hour_open"],
						"close" => $outlet["hour_close"]
					]
				],
				"table" => [
					"number" => $params["table_id"]
				]
			]
		];

		if (!empty($cartItems)) {
			$response["data"]["cart"] = $cartItems;
		}

		return $this->output
			->set_status_header(200)
			->set_output(json_encode($response));
	}

	public function createSession()
	{
		$this->output->set_content_type('application/json');
		$res = ["success" => false];

		try {
			// Validasi akses pengguna
			$accessInfo = $this->validateUserAccess();

			if (!$accessInfo['access_granted']) {
				return $this->handleAccessDenied($accessInfo);
			}

			$payload = $this->input->raw_input_stream;
			$payload = $this->security->xss_clean($payload);
			$payload = json_decode($payload);

			if (!$this->validatePayload($payload)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"code" => "001",
						"message" => "Invalid request format"
					]));
			}

			$params = [
				"outlet_id" => $payload->outletId,
				"table_id" => $payload->tableId,
				"brand" => $payload->brand,
			];

			// Validasi outlet dan jam operasional
			$outlet = $this->MS_Outlet->get_detail_outlet([
				"outlet_id" => $params["outlet_id"]
			]);

			if (!$outlet) {
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						"success" => false,
						"code" => "002",
						"message" => "Outlet not found"
					]));
			}

			// Validasi jam operasional (dengan bypass untuk kasir)
			$this->validateOperatingHours($outlet, $accessInfo);

			// Validasi lokasi jika diperlukan
			if (isset($payload->verifyLocation) && $payload->verifyLocation) {
				$outletCoordinate = [
					"latitude" => $outlet["latitude"],
					"longitude" => $outlet["longitude"]
				];

				$customerLocation = [[
					"latitude" => $payload->latitude,
					"longitude" => $payload->longitude
				]];

				$inRadius = $this->libgeneralmap->inRadius(
					0.1,
					$outletCoordinate,
					$customerLocation
				);

				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						"success" => !in_array(false, $inRadius),
						"code" => "000",
						"message" => !in_array(false, $inRadius) ?
							"Location validated" :
							"You must be within 100 meters of the outlet"
					]));
			}

			// Cek sesi yang sudah ada
			$existingSession = $this->M_Order->getOne(
				array_merge($params, ["deleted_at" => null])
			);

			if (!empty($existingSession)) {
				if ($this->verifyPasscode($payload->passcode, $existingSession["passcode"])) {
					return $this->output
						->set_status_header(200)
						->set_output(json_encode([
							"success" => true,
							"code" => "001",
							"message" => "Session resumed successfully",
							"data" => [
								"session_id" => $existingSession["id"],
								"expire_at" => $existingSession["expire_at"],
								"access_info" => [
									"type" => $accessInfo['access_type'],
									"kasir_id" => $accessInfo['kasir_id']
								]
							]
						]));
				}

				return $this->output
					->set_status_header(422)
					->set_output(json_encode([
						"success" => false,
						"code" => "006",
						"message" => "Table is currently occupied"
					]));
			}

			// Buat data sesi baru
			$timezone = new DateTimeZone('Asia/Jakarta');
			$currentTime = new DateTime('now', $timezone);

			$sessionData = [
				"outlet_id" => $payload->outletId,
				"table_id" => $payload->tableId,
				"brand" => $payload->brand,
				"name" => $this->security->xss_clean($payload->name),
				"passcode" => password_hash($payload->passcode, PASSWORD_BCRYPT),
				"status" => $this->M_Order::STATUS_RESERVED,
				"created_at" => $currentTime->format("Y-m-d H:i:s"),
				"updated_at" => $currentTime->format("Y-m-d H:i:s"),
				"expire_at" => $currentTime
					->add(new DateInterval('PT15M'))
					->format("Y-m-d H:i:s"),
			];

			// Tambahkan kasir_id jika akses melalui kasir
			if ($accessInfo['access_type'] === 'kasir') {
				$sessionData['kasir_id'] = $accessInfo['kasir_id'];
			}

			$this->db->trans_begin();

			try {
				$result = $this->M_Order->insertOrder($sessionData);

				if (!$result["success"]) {
					$this->db->trans_rollback();
					return $this->output
						->set_status_header(500)
						->set_output(json_encode([
							"success" => false,
							"code" => "007",
							"message" => "Failed to create session"
						]));
				}

				// Buat notifikasi dengan informasi kasir jika ada
				$this->createSessionNotification($result["id"], $sessionData, $accessInfo);

				$this->db->trans_commit();

				$response = [
					"success" => true,
					"code" => "000",
					"message" => "Session created successfully",
					"data" => [
						"session_id" => $result["id"],
						"customer" => [
							"name" => $sessionData["name"]
						],
						"table" => [
							"number" => $sessionData["table_id"]
						],
						"outlet" => [
							"id" => $outlet["outlet_id"],
							"name" => $outlet["outlet_name"]
						],
						"timing" => [
							"created_at" => $sessionData["created_at"],
							"expire_at" => $sessionData["expire_at"]
						],
						"access_info" => [
							"type" => $accessInfo['access_type'],
							"kasir_id" => $accessInfo['kasir_id'],
							"kasir_name" => $accessInfo['kasir_info']['user_alias'] ?? null
						]
					]
				];

				return $this->output
					->set_status_header(201)
					->set_output(json_encode($response));
			} catch (Exception $e) {
				$this->db->trans_rollback();
				log_message('error', 'Session creation error: ' . $e->getMessage());
				return $this->output
					->set_status_header(500)
					->set_output(json_encode([
						"success" => false,
						"code" => "008",
						"message" => "Internal server error: " . $e->getMessage()
					]));
			}
		} catch (Exception $e) {
			log_message('error', 'General error in createSession: ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"code" => "009",
					"message" => "Internal server error: " . $e->getMessage()
				]));
		}
	}

	private function createSessionNotification($sessionId, $sessionData, $accessInfo)
	{
		$tableExists = $this->db
			->query("SHOW TABLES LIKE 'session_notifications'")
			->num_rows() > 0;

		if ($tableExists) {
			$sessionNotification = [
				'session_id' => $sessionId,
				'outlet_id' => $sessionData["outlet_id"],
				'table_id' => $sessionData["table_id"],
				'brand' => $sessionData["brand"],
				'customer_name' => $sessionData["name"],
				'status' => 'new_session',
				'created_at' => $sessionData["created_at"]
			];

			// Tambahkan informasi kasir jika ada
			if ($accessInfo['access_type'] === 'kasir') {
				$sessionNotification['kasir_id'] = $accessInfo['kasir_id'];
				$sessionNotification['kasir_name'] = $accessInfo['kasir_info']['user_alias'] ?? '';
				$sessionNotification['access_type'] = 'kasir';
			} else {
				$sessionNotification['access_type'] = 'public';
			}

			$notificationInserted = $this->db->insert('session_notifications', $sessionNotification);

			if (!$notificationInserted) {
				log_message('error', 'Failed to insert session notification: ' . $this->db->error()['message']);
			} else {
				log_message('debug', 'Created session notification: ' . json_encode($sessionNotification));
			}
		}
	}


	private function validatePayload($payload)
	{
		$requiredFields = ['outletId', 'tableId', 'brand', 'name'];
		foreach ($requiredFields as $field) {
			if (!isset($payload->$field) || empty($payload->$field)) {
				return false;
			}
		}

		$validBrands = ['kopitiam', 'bakery', 'resto'];
		if (!in_array($payload->brand, $validBrands)) {
			return false;
		}

		return true;
	}

	private function verifyPasscode($inputPasscode, $storedPasscode)
	{
		return password_verify($inputPasscode, $storedPasscode);
	}

	private function validateUserAccess()
	{
		$kasirId = $this->input->get('kasirId');
		$isPublicAccess = empty($kasirId);

		$accessInfo = [
			'is_public' => $isPublicAccess,
			'kasir_id' => null,
			'kasir_info' => null,
			'access_granted' => true,
			'access_type' => 'public'
		];

		// Jika ada kasirId, validasi autentikasi kasir
		if (!$isPublicAccess) {
			$kasirValidation = $this->validateKasirAccess($kasirId);

			if (!$kasirValidation['valid']) {
				$accessInfo['access_granted'] = false;
				$accessInfo['error_message'] = $kasirValidation['message'];
				return $accessInfo;
			}

			$accessInfo['kasir_id'] = $kasirId;
			$accessInfo['kasir_info'] = $kasirValidation['kasir_data'];
			$accessInfo['access_type'] = 'kasir';
			$accessInfo['is_public'] = false;
		}

		return $accessInfo;
	}

	/**
	 * Validasi khusus untuk akses kasir
	 * @param int $kasirId
	 * @return array
	 */
	private function validateKasirAccess($kasirId)
	{
		try {
			log_message('debug', '=== KASIR VALIDATION START ===');
			log_message('debug', 'Validating kasir ID: ' . $kasirId);

			// Validasi format kasirId
			if (!is_numeric($kasirId) || $kasirId <= 0) {
				log_message('error', 'Invalid kasir ID format: ' . $kasirId);
				return [
					'valid' => false,
					'message' => 'Format ID kasir tidak valid'
				];
			}

			// LANGKAH 1: Cek apakah user ada dan aktif
			$userCheck = $this->db
				->select('user_id, user_name, user_alias, user_st')
				->from('app_user')
				->where('user_id', $kasirId)
				->get();

			log_message('debug', 'User check query: ' . $this->db->last_query());
			log_message('debug', 'User found: ' . $userCheck->num_rows());

			if ($userCheck->num_rows() === 0) {
				log_message('error', 'User not found with ID: ' . $kasirId);
				return [
					'valid' => false,
					'message' => 'User dengan ID ' . $kasirId . ' tidak ditemukan'
				];
			}

			$userData = $userCheck->row_array();
			log_message('debug', 'User data: ' . json_encode($userData));

			// Cek status user
			if ($userData['user_st'] !== '0') {
				log_message('error', 'User not active. Status: ' . $userData['user_st']);
				return [
					'valid' => false,
					'message' => 'User tidak aktif (Status: ' . $userData['user_st'] . ')'
				];
			}

			// LANGKAH 2: Cek role user
			$roleCheck = $this->db
				->select('ru.user_id, ru.role_id, r.role_nm, r.role_id as role_id_check')
				->from('app_role_user ru')
				->join('app_role r', 'ru.role_id = r.role_id')
				->where('ru.user_id', $kasirId)
				->get();

			log_message('debug', 'Role check query: ' . $this->db->last_query());
			log_message('debug', 'Roles found: ' . $roleCheck->num_rows());
			log_message('debug', 'Role data: ' . json_encode($roleCheck->result_array()));

			if ($roleCheck->num_rows() === 0) {
				log_message('error', 'No roles found for user ID: ' . $kasirId);
				return [
					'valid' => false,
					'message' => 'User tidak memiliki role yang ditetapkan'
				];
			}

			// LANGKAH 3: Cek apakah memiliki role kasir (ID 2003)
			$kasirRoleCheck = $this->db
				->select('*')
				->from('app_role_user')
				->where('user_id', $kasirId)
				->where('role_id', 2003)
				->get();

			log_message('debug', 'Kasir role check query: ' . $this->db->last_query());
			log_message('debug', 'Kasir role found: ' . $kasirRoleCheck->num_rows());

			if ($kasirRoleCheck->num_rows() === 0) {
				log_message('error', 'User does not have kasir role (2003)');

				// Debug: Tampilkan role yang ada
				$existingRoles = [];
				foreach ($roleCheck->result_array() as $role) {
					$existingRoles[] = $role['role_id'] . ':' . $role['role_nm'];
				}

				return [
					'valid' => false,
					'message' => 'User tidak memiliki role Kasir. Role yang ada: ' . implode(', ', $existingRoles)
				];
			}

			// LANGKAH 4: Query gabungan untuk mendapatkan data lengkap
			$fullQuery = $this->db
				->select('u.user_id, u.user_name, u.user_alias, u.user_st, r.role_nm, r.role_id')
				->from('app_user u')
				->join('app_role_user ru', 'u.user_id = ru.user_id')
				->join('app_role r', 'ru.role_id = r.role_id')
				->where('u.user_id', $kasirId)
				->where('u.user_st', '0')
				->where('r.role_id', 2003)
				->get();

			log_message('debug', 'Full validation query: ' . $this->db->last_query());
			log_message('debug', 'Full query result count: ' . $fullQuery->num_rows());

			if ($fullQuery->num_rows() === 0) {
				log_message('error', 'Full validation query returned no results');
				return [
					'valid' => false,
					'message' => 'Validasi lengkap gagal - kondisi gabungan tidak terpenuhi'
				];
			}

			$kasirData = $fullQuery->row_array();
			log_message('debug', 'Final kasir data: ' . json_encode($kasirData));

			// LANGKAH 5: Validasi sesi kasir (opsional)
			$sessionValidation = $this->validateKasirSession($kasirId);
			log_message('debug', 'Session validation: ' . json_encode($sessionValidation));

			log_message('debug', '=== KASIR VALIDATION SUCCESS ===');

			return [
				'valid' => true,
				'kasir_data' => $kasirData,
				'session_info' => $sessionValidation
			];
		} catch (Exception $e) {
			log_message('error', 'Exception in validateKasirAccess: ' . $e->getMessage());
			log_message('error', 'Stack trace: ' . $e->getTraceAsString());
			return [
				'valid' => false,
				'message' => 'Terjadi kesalahan validasi akses kasir: ' . $e->getMessage()
			];
		}
	}

	/**
	 * Validasi sesi kasir (opsional - untuk keamanan tambahan) - DIPERBAIKI
	 * @param int $kasirId
	 * @return array
	 */
	private function validateKasirSession($kasirId)
	{
		try {
			log_message('debug', 'Validating kasir session for ID: ' . $kasirId);

			// Cek last activity kasir
			$lastActivity = $this->db
				->select('modified, created')
				->from('app_user')
				->where('user_id', $kasirId)
				->get()
				->row_array();

			if (!$lastActivity) {
				log_message('warning', 'No activity data found for kasir: ' . $kasirId);
				return [
					'valid' => true, // Default valid jika tidak ada data
					'message' => 'Tidak ada data aktivitas',
					'session_data' => null
				];
			}

			// Tentukan waktu referensi (gunakan modified jika ada, otherwise created)
			$referenceTime = !empty($lastActivity['modified']) ? $lastActivity['modified'] : $lastActivity['created'];

			if ($referenceTime) {
				$lastModified = strtotime($referenceTime);
				$currentTime = time();
				$sessionTimeout = 8 * 60 * 60; // 8 jam

				$timeDiff = $currentTime - $lastModified;

				log_message('debug', 'Session time check:');
				log_message('debug', '- Reference time: ' . $referenceTime);
				log_message('debug', '- Time difference: ' . $timeDiff . ' seconds');
				log_message('debug', '- Session timeout: ' . $sessionTimeout . ' seconds');

				if ($timeDiff > $sessionTimeout) {
					log_message('warning', 'Kasir session expired. Time diff: ' . $timeDiff);
					return [
						'valid' => false,
						'message' => 'Sesi kasir sudah kedaluwarsa (' . round($timeDiff / 3600, 1) . ' jam)',
						'session_data' => $lastActivity
					];
				}
			}

			log_message('debug', 'Kasir session is valid');
			return [
				'valid' => true,
				'message' => 'Sesi kasir valid',
				'session_data' => $lastActivity
			];
		} catch (Exception $e) {
			log_message('error', 'Error validating kasir session: ' . $e->getMessage());
			return [
				'valid' => true, // Default valid untuk menghindari blocking
				'message' => 'Error validasi sesi, mengizinkan akses',
				'session_data' => null
			];
		}
	}

	/**
	 * Method bantuan untuk membuat role kasir jika tidak ada
	 */
	private function ensureKasirRoleExists()
	{
		try {
			// Cek apakah role kasir (2003) ada
			$roleExists = $this->db
				->select('role_id')
				->from('app_role')
				->where('role_id', 2003)
				->get()
				->num_rows() > 0;

			if (!$roleExists) {
				log_message('info', 'Kasir role not found, creating...');

				$roleData = [
					'role_id' => 2003,
					'site_id' => 20,
					'role_nm' => 'Kasir',
					'role_st' => '0'
				];

				$this->db->insert('app_role', $roleData);
				log_message('info', 'Kasir role created successfully');

				return true;
			}

			return true;
		} catch (Exception $e) {
			log_message('error', 'Error ensuring kasir role exists: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Method bantuan untuk assign role kasir ke user
	 */
	private function assignKasirRole($userId)
	{
		try {
			// Cek apakah user sudah memiliki role kasir
			$hasRole = $this->db
				->select('user_id')
				->from('app_role_user')
				->where('user_id', $userId)
				->where('role_id', 2003)
				->get()
				->num_rows() > 0;

			if (!$hasRole) {
				log_message('info', 'Assigning kasir role to user: ' . $userId);

				$roleUserData = [
					'user_id' => $userId,
					'role_id' => 2003,
					'role_default' => 2003
				];

				$this->db->insert('app_role_user', $roleUserData);
				log_message('info', 'Kasir role assigned successfully');

				return true;
			}

			return true;
		} catch (Exception $e) {
			log_message('error', 'Error assigning kasir role: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Modifikasi method list() untuk mendukung dual access
	 */
	public function list()
	{
		try {
			// 1. Validasi akses pengguna
			$accessInfo = $this->validateUserAccess();

			if (!$accessInfo['access_granted']) {
				return $this->handleAccessDenied($accessInfo);
			}

			// 2. Ekstraksi Parameter dengan Validasi
			$params = $this->extractListParameters();

			// 3. Tambahkan informasi akses ke params
			$params['access_info'] = $accessInfo;

			// 4. Validasi Parameter Awal
			$this->validateListParams($params);

			// 5. Validasi Outlet
			$outlet = $this->validateOutlet($params);

			// 6. Validasi Jam Operasional (bisa dikustomisasi berdasarkan access type)
			$this->validateOperatingHours($outlet, $accessInfo);

			// 7. Deteksi Tipe Request
			$isAjax = $this->isAjaxRequest();

			// 8. Ambil Kategori
			$categories = $this->getCategoriesForBrand($params['brand']);

			// 9. Ambil Produk dengan filter berdasarkan access type
			$productsData = $this->fetchProductsData($params, $categories);

			// 10. Ambil Paket
			$packages = $this->fetchPackages();

			// 11. Proses Data Produk
			$processedProducts = $this->processProductData(
				$productsData['products'],
				$categories,
				$packages
			);

			// 12. Validasi Sesi dengan informasi kasir jika ada
			$sessionData = $this->validateSessionData($params);

			// 13. Persiapkan Response dengan informasi access
			$responseData = $this->prepareResponseData(
				$processedProducts,
				$categories,
				$packages,
				$sessionData,
				$accessInfo
			);

			// 14. Kirim Response
			return $this->sendListResponse($isAjax, $responseData);
		} catch (Exception $e) {
			return $this->handleListError($e, $isAjax ?? false);
		}
	}

	/**
	 * Handle akses ditolak
	 * @param array $accessInfo
	 * @return void
	 */
	private function handleAccessDenied($accessInfo)
	{
		$this->output->set_content_type('application/json');

		return $this->output
			->set_status_header(403)
			->set_output(json_encode([
				'success' => false,
				'code' => '403',
				'message' => $accessInfo['error_message'] ?? 'Akses ditolak',
				'access_type' => 'denied'
			]));
	}

	private function extractListParameters()
	{
		return [
			'tableId' => $this->input->get('tableId'),
			'outletId' => $this->input->get('outletId'),
			'category' => $this->input->get('category'),
			'brand' => $this->input->get('brand') ?? 'kopitiam'
		];
	}

	private function validateListParams(array $params)
	{
		// Validasi parameter wajib
		$requiredParams = ['tableId', 'outletId'];
		foreach ($requiredParams as $param) {
			if (empty($params[$param])) {
				log_message('debug', "Missing required parameter: {$param}");
				throw new InvalidArgumentException("Parameter {$param} tidak boleh kosong");
			}
		}

		// Validasi brand
		$validBrands = ['kopitiam', 'bakery', 'resto'];
		if (!in_array($params['brand'], $validBrands)) {
			throw new InvalidArgumentException("Brand tidak valid");
		}
	}

	private function validateOutlet(array $params)
	{
		// Gunakan metode dari M_outlets yang sudah ada
		$outlet = $this->MS_Outlet->get_detail_outlet([
			"outlet_id" => $params["outletId"]
		]);

		if (!$outlet) {
			log_message('debug', 'Outlet not found: ' . $params["outletId"]);
			throw new RuntimeException("Outlet tidak ditemukan");
		}

		// Validasi status outlet
		if ($outlet['outlet_status'] != '0') {
			log_message('debug', 'Outlet not active. Status: ' . $outlet['outlet_status']);
			throw new RuntimeException("Outlet tidak aktif");
		}

		// Validasi nomor meja
		if ($params["tableId"] > $outlet['count_table']) {
			log_message('debug', 'Invalid table number');
			throw new InvalidArgumentException("Nomor meja tidak valid");
		}

		return $outlet;
	}

	private function validateOperatingHours($outlet, $accessInfo)
	{
		// Kasir bisa bypass jam operasional
		if ($accessInfo['access_type'] === 'kasir') {
			log_message('debug', 'Operating hours validation bypassed for kasir: ' . $accessInfo['kasir_id']);
			return;
		}

		// Validasi jam operasional untuk publik
		$timezone = new DateTimeZone('Asia/Jakarta');
		$currentTime = new DateTime('now', $timezone);
		$openTime = new DateTime($outlet['hour_open'], $timezone);
		$closeTime = new DateTime($outlet['hour_close'], $timezone);

		if ($currentTime < $openTime || $currentTime > $closeTime) {
			log_message('debug', 'Outside operating hours for public access');
			throw new RuntimeException(sprintf(
				'Outlet sedang tutup. Jam operasional: %s - %s',
				$outlet['hour_open'],
				$outlet['hour_close']
			));
		}
	}

	private function isAjaxRequest()
	{
		return
			$this->input->is_ajax_request() ||
			$this->input->get('ajax') === 'true';
	}

	private function getCategoriesForBrand($brand)
	{
		// Ambil kategori dari model
		$rawCategories = $this->M_categories->get_catalogue_categories($brand);

		// Mengurutkan kategori agar Value Meals muncul pertama
		usort($rawCategories, function ($a, $b) {
			// Pastikan cat_code ada sebelum diakses
			$a_cat_code = isset($a['cat_code']) ? $a['cat_code'] : null;
			$b_cat_code = isset($b['cat_code']) ? $b['cat_code'] : null;

			// Cek jika $a adalah Value Meals
			if ($a_cat_code == 'SKT00001') {
				return -1; // Prioritaskan Value Meals
			} elseif ($b_cat_code == 'SKT00001') {
				return 1; // Jika $b adalah Value Meals, letakkan di belakang
			}

			// Jika tidak ada kategori Value Meals, urutkan berdasarkan cat_id
			return $b['cat_id'] - $a['cat_id'];
		});

		// Mapping kategori ke format yang diinginkan
		return array_map(function ($cat) {
			return [
				'cat_id' => $cat['cat_id'] ?? '',
				'cat_code' => $cat['cat_code'] ?? '',
				'cat_name' => $cat['cat_name'] ?? 'Unnamed Category',
				'cat_brand' => $cat['cat_brand'] ?? '',
				'catalogue_category' => $cat['cat_code'] ?? '',
				'value' => $cat['cat_code'] ?? ''
			];
		}, $rawCategories);
	}

	private function fetchProductsData(array $params, array $categories)
	{
		// Log for debugging
		log_message('debug', 'Fetching products data with params: ' . json_encode($params));

		try {
			// Validate input parameters
			if (empty($params['brand'])) {
				throw new InvalidArgumentException("Brand parameter is required");
			}

			// PERUBAHAN: Query dengan prioritas produk dalam promo dan termasuk api_id field
			$this->db->select('dp.*, dp.api_id, pc.cat_name, 
							  (CASE 
								  WHEN pp.promo_id IS NOT NULL THEN 1 
								  WHEN pcat.promo_id IS NOT NULL THEN 1 
								  ELSE 0 
							  END) as is_promo,
							  p.promo_type, p.promo_code, p.promo_value, p.maximum_discount'); // Tambahkan field promo
			$this->db->from('data_product dp');
			$this->db->join('data_categories pc', 'dp.cat_id = pc.cat_id', 'left');

			// PERUBAHAN: Join dengan tabel promo_products untuk mengetahui produk yang sedang promo
			$this->db->join('promo_products pp', 'dp.product_id = pp.product_id', 'left');

			// PERUBAHAN: Join dengan tabel promo untuk mendapatkan detail promo
			$this->db->join('promos p', 'pp.promo_id = p.promo_id AND p.promo_status = "active" AND p.start_date <= NOW() AND p.end_date >= NOW() AND p.deleted_at IS NULL', 'left');

			// PERUBAHAN: Join dengan tabel promo_categories untuk mengetahui kategori yang sedang promo
			$this->db->join('promo_categories pcat', 'dp.cat_id = pcat.cat_id', 'left');

			// PERUBAHAN: Join dengan tabel promos untuk kategori promo
			$this->db->join('promos pcat_promo', 'pcat.promo_id = pcat_promo.promo_id AND pcat_promo.promo_status = "active" AND pcat_promo.start_date <= NOW() AND pcat_promo.end_date >= NOW() AND pcat_promo.deleted_at IS NULL', 'left');

			$this->db->where('dp.product_brand', $params['brand']);
			$this->db->where('dp.product_st', '0');  // Only active products
			$this->db->where('dp.stock >', 0);       // Only products with available stock

			// PERUBAHAN: Urutkan berdasarkan status promo terlebih dahulu
			$this->db->order_by('is_promo', 'DESC');  // Products with active promos first
			$this->db->order_by('dp.created', 'DESC');  // Then by creation date (newest)
			$this->db->order_by('dp.product_no', 'ASC');  // Finally by product number

			// Filter by category if specified
			if (!empty($params['category'])) {
				$this->db->group_start()
					->where('dp.cat_id', $params['category'])
					->group_end();
			}

			$products = $this->db->get()->result_array();

			// Add additional information for each product
			$processedProducts = array_map(function ($product) {
				// Format price
				$product['price_catalogue'] = floor($product['product_price'] ?? 0);

				// Stock information
				$product['current_stock'] = isset($product['stock']) ? (int)$product['stock'] : 0;

				// Add timestamp for ordering
				$product['created_timestamp'] = strtotime($product['created']);

				// PERUBAHAN: Tambahkan informasi promo jika ada
				if ($product['is_promo'] == 1) {
					$product['promo_info'] = [
						'type' => $product['promo_type'] ?? 'unknown',
						'code' => $product['promo_code'] ?? '',
						'value' => $product['promo_value'] ?? 0,
						'maximum_discount' => $product['maximum_discount'] ?? null
					];

					// Format label promo berdasarkan tipe
					if ($product['promo_type'] == 'percentage') {
						$product['promo_label'] = $product['promo_value'] . '%';
						if ($product['maximum_discount']) {
							$product['promo_label'] .= " (max " . number_format($product['maximum_discount'], 0, ',', '.') . ")";
						}
					} elseif ($product['promo_type'] == 'nominal') {
						$product['promo_label'] = "Rp " . number_format($product['promo_value'], 0, ',', '.');
					} elseif ($product['promo_type'] == 'bundling') {
						$product['promo_label'] = "Bundle";
					} elseif ($product['promo_type'] == 'bogo') {
						$product['promo_label'] = "Buy 1 Get 1";
					} else {
						$product['promo_label'] = "Promo";
					}
				}

				return $product;
			}, $products);

			// Log the number of products found
			log_message('debug', 'Total products found: ' . count($processedProducts));

			return [
				'products' => $processedProducts,
				'categories' => $categories
			];
		} catch (Exception $e) {
			// Handle error with logging and return empty array
			log_message('error', 'Error in fetchProductsData: ' . $e->getMessage());
			return [
				'products' => [],
				'categories' => $categories
			];
		}
	}

	private function fetchPackages()
	{
		// PERBAIKAN PENTING: Tambahkan kondisi untuk status produk aktif
		return $this->M_Package->getAll([
			"where" => [
				"deleted_at" => NULL
			]
		]) ?? [];
	}
	private function processProductData($products, $categories, $packages)
	{
		$processedProducts = [];
		$groupedProducts = [];

		if (!empty($products)) {
			// Urutkan produk berdasarkan status promo (promo dulu) dan waktu pembuatan dari yang terbaru
			usort($products, function ($a, $b) {
				// Prioritaskan produk dengan promo
				if (isset($a['is_promo']) && isset($b['is_promo']) && $a['is_promo'] != $b['is_promo']) {
					return $b['is_promo'] - $a['is_promo']; // Produk dengan promo lebih dulu
				}

				// Jika status promo sama, urutkan berdasarkan waktu pembuatan
				return $b['created_timestamp'] - $a['created_timestamp'];
			});

			foreach ($products as $product) {
				$catId = $product['cat_id'] ?? 0;

				// Inisialisasi kategori
				if (!isset($groupedProducts[$catId])) {
					$groupedProducts[$catId] = [
						'category_name' => $product['cat_name'] ?? 'Uncategorized',
						'products' => []
					];
				}

				// Proses produk
				$processedProduct = $this->prepareSingleProduct($product, $packages);
				$processedProducts[] = $processedProduct;
				$groupedProducts[$catId]['products'][] = $processedProduct;
			}
		}

		return [
			'processed_products' => $processedProducts,
			'grouped_products' => $groupedProducts
		];
	}

	private function prepareSingleProduct($product, $packages)
	{
		$price = floor($product['price_catalogue'] ?? 0);
		$priceDisplay = $price == $product['price_catalogue'] ? $price : str_replace('.', ',', strval($product['price_catalogue'] ?? 0));

		$processedProduct = [
			'product_id' => $product['product_id'] ?? 0,
			'product_name' => $product['product_name'] ?? 'Unnamed Product',
			'product_pict' => $product['product_pict'] ?? 'default.png',
			'product_desc' => $product['product_desc'] ?? '',
			'cat_id' => $product['cat_id'] ?? '',
			'cat_name' => $product['cat_name'] ?? 'Uncategorized',
			'price_catalogue' => $product['price_catalogue'] ?? 0,
			'price_display' => $priceDisplay,
			'stock' => isset($product['stock']) ? (int)$product['stock'] : 0,
			'current_stock' => isset($product['stock']) ? (int)$product['stock'] : 0,
			'api_id' => $product['api_id'] ?? null,
			'created_at' => $product['created'] ?? date('Y-m-d H:i:s'),
			'created_timestamp' => $product['created_timestamp'] ?? time(),

			// PERUBAHAN: Tambahkan informasi promo
			'is_promo' => $product['is_promo'] ?? 0,
			'promo_type' => $product['promo_type'] ?? null,
			'promo_code' => $product['promo_code'] ?? null,
			'promo_value' => $product['promo_value'] ?? null,
			'promo_label' => $product['promo_label'] ?? null,
			'maximum_discount' => $product['maximum_discount'] ?? null
		];

		// Add package information
		$processedProduct['available_in_packages'] = array_filter($packages, function ($package) use ($processedProduct) {
			return ($package['product_id'] ?? '') == $processedProduct['product_id'];
		});

		return $processedProduct;
	}
	private function validateSessionData(array $params)
	{
		log_message('debug', '=== Start Session Validation ===');
		log_message('debug', 'Params: ' . json_encode($params));

		$sessionData = $this->M_Order->getOne([
			"outlet_id" => $params['outletId'],
			"table_id" => $params['tableId'],
			"brand" => $params['brand'],
			"deleted_at" => NULL
		]);

		log_message('debug', 'Session Data Found: ' . json_encode($sessionData));

		if ($sessionData) {
			// Log status session
			log_message('debug', 'Session Status: ' . $sessionData['status']);
			log_message('debug', 'STATUS_RESERVED Value: ' . $this->M_Order::STATUS_RESERVED);

			// Assign data to template
			$this->tsmarty->assign('STATUS_RESERVED', $this->M_Order::STATUS_RESERVED);
			$this->tsmarty->assign('STATUS_ORDERED', $this->M_Order::STATUS_ORDERED);
			$this->tsmarty->assign('session', $sessionData);

			log_message('debug', 'Assigned Session Data to Template');

			if ($sessionData['status'] == $this->M_Order::STATUS_RESERVED) {
				// Get cart items
				$cartItems = $this->M_Order_Detail->getAll([
					"where" => [
						"order_id" => $sessionData["id"],
						"deleted_at" => NULL
					]
				]);

				log_message('debug', 'Cart Items Found: ' . count($cartItems));
				log_message('debug', 'Cart Items: ' . json_encode($cartItems));

				$this->tsmarty->assign("cart_items", $cartItems);
			}
		} else {
			log_message('debug', 'No Session Data Found');
		}

		log_message('debug', '=== End Session Validation ===');
		return $sessionData;
	}

	private function prepareResponseData($processedProducts, $categories, $packages, $sessionData, $accessInfo = null)
	{
		// Ambil kategori paket
		$packageCategories = $this->M_Package_Category->getAll([
			"where" => ["deleted_at" => NULL]
		]) ?? [];

		// Proses kategori paket
		foreach ($packageCategories as &$packageCat) {
			$packageCat['name'] = $packageCat['name'] ?? 'Unnamed Package';
			$packageCat['required_qty'] = $packageCat['required_qty'] ?? 0;
		}

		// Ambil item keranjang jika ada sesi
		$cartItems = $this->fetchCartItems($sessionData);

		$responseData = [
			'products' => $processedProducts['processed_products'],
			'categories' => $categories,
			'grouped_products' => $processedProducts['grouped_products'],
			'packages' => $packages,
			'package_categories' => $packageCategories,
			'cart_items' => $cartItems,
			'session' => $sessionData
		];

		// Tambahkan informasi akses jika tersedia
		if ($accessInfo) {
			$responseData['access_info'] = [
				'type' => $accessInfo['access_type'],
				'is_public' => $accessInfo['is_public'],
				'kasir_info' => $accessInfo['kasir_info'] ?? null
			];
		}

		return $responseData;
	}

	private function fetchCartItems($sessionData)
	{
		if (!$sessionData) return [];

		return $this->M_Order_Detail->getAll([
			"where" => [
				"order_id" => $sessionData["id"],
				"deleted_at" => NULL
			]
		]) ?? [];
	}

	private function sendListResponse($isAjax, $responseData)
	{
		if ($isAjax) {
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => true,
					'data' => $responseData
				]));
		}

		// Render view untuk non-AJAX
		$this->renderListView($responseData);
	}

	private function renderListView($data)
	{
		$this->tsmarty->assign("template_content", "order/order.html");
		$this->tsmarty->assign('product_mb', $data['products']);
		$this->tsmarty->assign('grouped_products', $data['grouped_products']);
		$this->tsmarty->assign("catalogueCategories", $data['categories']);
		$this->tsmarty->assign("packages", $data['packages']);
		$this->tsmarty->assign("package_categories", $data['package_categories']);

		// Assign outlet data
		if (!empty($data['session'])) {
			$outlet = $this->MS_Outlet->get_detail_outlet([
				'outlet_id' => $data['session']['outlet_id']
			]);
			$this->tsmarty->assign("outlet", $outlet);
			$this->tsmarty->assign("table_id", $data['session']['table_id']);
		}

		$cartItems = [];
		if (isset($data['cart_items']) && is_array($data['cart_items'])) {
			$cartItems = $data['cart_items'];
		}
		$this->tsmarty->assign("cart_items", $cartItems);

		// Pisahkan item reguler dan paket
		$regularItems = [];
		$packageItems = [];

		// Prepare variabel untuk tampilan keranjang
		$this->tsmarty->assign("regular_items", $regularItems);
		$this->tsmarty->assign("package_items", $packageItems);

		// Jangan lupa assign variabel total
		$this->tsmarty->assign("total_items", 0);
		$this->tsmarty->assign("total_amount", 0);

		parent::display();
	}

	private function handleListError($exception, $isAjax)
	{
		log_message('error', 'List method error: ' . $exception->getMessage());

		if ($isAjax) {
			return $this->output
				->set_content_type('application/json')
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => $exception->getMessage()
				]));
		}

		show_error($exception->getMessage());
	}

	public function getPackageDetails($packageId)
	{
		try {
			$this->db->trans_begin();

			// 1. Get package info with caching
			$package = $this->getPackageWithCache($packageId);
			if (!$package) {
				throw new Exception('Package not found or inactive');
			}

			// 2. Get categories with requirements
			$categories = $this->getPackageCategories($packageId);

			// 3. Get products by category
			$products = $this->getPackageProducts($packageId, $categories);

			// 4. Build response
			$response = [
				'success' => true,
				'data' => [
					'package' => $package,
					'categories' => $categories,
					'products_by_category' => $products
				]
			];

			$this->db->trans_commit();
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode($response));
		} catch (Exception $e) {
			$this->db->trans_rollback();
			log_message('error', 'Error in getPackageDetails: ' . $e->getMessage());

			return $this->output
				->set_content_type('application/json')
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => $e->getMessage()
				]));
		}
	}

	private function getPackageWithCache($packageId)
	{
		$cacheKey = "package_{$packageId}";
		$package = $this->cache->get($cacheKey);

		if (!$package) {
			$package = $this->db
				->select('p.*, dp.product_name, dp.product_desc, dp.product_pict, dp.stock, dp.product_price')
				->from('packages p')
				->join('data_product dp', 'p.product_id = dp.product_id')
				->join('package_categories pc', 'p.package_category_id = pc.id')
				->where([
					'p.id' => $packageId,
					'p.deleted_at' => NULL,
					'dp.product_st' => '0'
				])
				->get()
				->row_array();

			if ($package) {
				$this->cache->save($cacheKey, $package, 300); // Cache for 5 minutes
			}
		}

		return $package;
	}

	private function getPackageCategories($packageId)
	{
		$cacheKey = "package_categories_{$packageId}";
		$categories = $this->cache->get($cacheKey);

		if (!$categories) {
			$categories = $this->db
				->select('
					pc.*,
					pcr.required_items,
					pcr.max_items,
					COUNT(DISTINCT pcp.product_id) as available_products
				')
				->from('package_categories pc')
				->join(
					'package_category_requirements pcr',
					'pc.id = pcr.category_id AND pcr.package_id = ' . $this->db->escape($packageId)
				)
				->join(
					'package_custom_products pcp',
					'pc.id = pcp.category_id AND pcp.deleted_at IS NULL',
					'left'
				)
				->where('pc.deleted_at IS NULL')
				->group_by('pc.id')
				->order_by('pc.display_order', 'ASC')
				->get()
				->result_array();

			if ($categories) {
				$this->cache->save($cacheKey, $categories, 300);
			}
		}

		return $categories;
	}

	private function getPackageProducts($packageId, $categories)
	{
		$products = [];

		foreach ($categories as $category) {
			$cacheKey = "package_products_{$packageId}_{$category['id']}";
			$categoryProducts = $this->cache->get($cacheKey);

			if (!$categoryProducts) {
				$categoryProducts = $this->db
					->select('
						pcp.*,
						dp.product_name,
						dp.product_desc,
						dp.product_pict,
						dp.stock,
						COALESCE(pcp.sale_price, dp.product_price) as final_price
					')
					->from('package_custom_products pcp')
					->join('data_product dp', 'pcp.product_id = dp.product_id')
					->where([
						'pcp.package_id' => $packageId,
						'pcp.category_id' => $category['id'],
						'pcp.deleted_at' => NULL,
						'dp.product_st' => '0',
						'dp.stock >' => 0
					])
					->get()
					->result_array();

				if ($categoryProducts) {
					$this->cache->save($cacheKey, $categoryProducts, 300);
				}
			}

			$products[$category['id']] = $categoryProducts;
		}

		return $products;
	}

	/**
	 * Handle adding package to cart
	 */
	public function addPackageToCart()
	{
		try {
			// Set tipe konten JSON
			$this->output->set_content_type('application/json');

			// Parse input JSON dengan validasi keamanan
			$input = json_decode($this->security->xss_clean($this->input->raw_input_stream));

			// Validasi input dasar
			if (!$this->validatePackageInput($input)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Input paket tidak valid'
					]));
			}

			// Mulai transaksi database
			$this->db->trans_start();

			// Validasi paket dengan error handling yang lebih baik
			$packageValidation = $this->M_Package->validatePackage(
				$input->packageId,
				$input->products
			);

			if (!$packageValidation['valid']) {
				$this->db->trans_rollback();
				return $this->output
					->set_status_header(422)
					->set_output(json_encode([
						'success' => false,
						'message' => implode(', ', $packageValidation['messages'] ?? ['Validasi paket gagal'])
					]));
			}

			// Ambil order aktif dengan pemeriksaan yang lebih ketat
			$order = $this->M_Order->getOne([
				'id' => $input->orderId,
				'deleted_at' => NULL,
				'status' => $this->M_Order::STATUS_RESERVED
			]);

			if (!$order) {
				$this->db->trans_rollback();
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Sesi order tidak valid'
					]));
			}

			// Dapatkan detail paket dari database
			$package = $this->M_Package->getOne(['product_id' => $input->packageId]);

			if (!$package) {
				$this->db->trans_rollback();
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Paket tidak ditemukan'
					]));
			}

			// Hitung harga dasar paket
			$basePrice = floatval($package['base_price'] ?? $package['product_price'] ?? 0);

			// Tambahkan header paket dengan informasi tambahan
			$packageHeader = [
				'order_id' => $order['id'],
				'product_id' => $input->packageId,
				'package_id' => $package['id'], // Pastikan package_id disimpan
				'quantity' => 1,
				'unit_price' => $basePrice, // Gunakan harga dasar dari paket
				'subtotal' => $basePrice, // Awalnya sama dengan harga dasar
				'created_at' => date('Y-m-d H:i:s'),
				'notes' => $input->notes ?? null
			];

			$packageHeaderId = $this->M_Order_Detail->insertOne($packageHeader);

			if (!$packageHeaderId) {
				$this->db->trans_rollback();
				return $this->output
					->set_status_header(500)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Gagal menambahkan header paket'
					]));
			}

			// Tambahkan item paket dengan validasi stok
			$totalItemsPrice = 0;
			$addedPackageItems = [];

			foreach ($input->products as $product) {
				// Validasi stok tambahan
				$productDetail = $this->MOC_Product->detail($product->productId);

				if (!$productDetail) {
					$this->db->trans_rollback();
					return $this->output
						->set_status_header(404)
						->set_output(json_encode([
							'success' => false,
							'message' => "Produk tidak ditemukan: {$product->productId}"
						]));
				}

				$productStock = $productDetail['stock'] ?? 0;
				if ($productStock < $product->quantity) {
					$this->db->trans_rollback();
					return $this->output
						->set_status_header(400)
						->set_output(json_encode([
							'success' => false,
							'message' => "Stok tidak mencukupi untuk produk: {$productDetail['product_name']}"
						]));
				}

				// Dapatkan harga item paket
				$customPrice = 0;
				$customPriceQuery = $this->db
					->select('custom_price, sale_price')
					->from('package_custom_products')
					->where([
						'package_id' => $package['id'],
						'product_id' => $product->productId,
						'deleted_at' => NULL
					])
					->get()
					->row_array();

				if ($customPriceQuery) {
					$customPrice = floatval($customPriceQuery['sale_price'] ?? $customPriceQuery['custom_price'] ?? 0);
				} else {
					$customPrice = floatval($productDetail['product_price'] ?? 0);
				}

				// Hitung subtotal item
				$itemSubtotal = $customPrice * $product->quantity;
				$totalItemsPrice += $itemSubtotal;

				// Tambahkan detail item paket
				$packageItem = [
					'order_id' => $order['id'],
					'product_id' => $product->productId,
					'parent_id' => $packageHeaderId,
					'quantity' => $product->quantity,
					'unit_price' => $customPrice,
					'subtotal' => $itemSubtotal,
					'created_at' => date('Y-m-d H:i:s'),
					'notes' => $product->notes ?? null
				];

				$itemId = $this->M_Order_Detail->insertOne($packageItem);

				if (!$itemId) {
					$this->db->trans_rollback();
					return $this->output
						->set_status_header(500)
						->set_output(json_encode([
							'success' => false,
							'message' => 'Gagal menambahkan item paket'
						]));
				}

				$packageItem['id'] = $itemId;
				$addedPackageItems[] = $packageItem;
			}

			// Update total header paket (base_price + semua item)
			$totalPackagePrice = $basePrice + $totalItemsPrice;

			$this->M_Order_Detail->update(
				['id' => $packageHeaderId],
				['subtotal' => $totalPackagePrice]
			);

			// Commit transaksi
			$this->db->trans_complete();

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return $this->output
					->set_status_header(500)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Terjadi kesalahan transaksi database'
					]));
			}

			// Kirim respons sukses dengan detail lengkap
			return $this->output
				->set_status_header(200)
				->set_output(json_encode([
					'success' => true,
					'message' => 'Paket berhasil ditambahkan',
					'data' => [
						'package_header' => [
							'id' => $packageHeaderId,
							'product_id' => $input->packageId,
							'package_id' => $package['id'],
							'base_price' => $basePrice,
							'total_price' => $totalPackagePrice
						],
						'package_items' => $addedPackageItems,
						'order_id' => $order['id']
					]
				]));
		} catch (Exception $e) {
			// Tangani kesalahan dengan log
			log_message('error', 'Kesalahan menambahkan paket: ' . $e->getMessage());
			$this->db->trans_rollback();

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Kesalahan internal server',
					'error_details' => $e->getMessage()
				]));
		}
	}

	public function getProductDetail()
	{
		try {
			$this->output->set_content_type('application/json');

			// Validasi parameter
			$productId = $this->input->get('productId');
			$outletId = $this->input->get('outletId');
			$brand = $this->input->get('brand');
			$tableId = $this->input->get('tableId');

			// Log parameter untuk debugging
			log_message('debug', 'Product Detail Request: ' . json_encode([
				'productId' => $productId,
				'outletId' => $outletId,
				'brand' => $brand,
				'tableId' => $tableId
			]));

			// Validasi parameter wajib
			if (!$productId) {
				log_message('error', 'Missing productId in getProductDetail');
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Product ID is required'
					]));
			}

			// Tambahkan method validateProduct() di sini
			$product = $this->validateProduct($productId);
			if (!$product) {
				log_message('error', 'Product validation failed: ID ' . $productId);
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Produk tidak ditemukan atau tidak aktif'
					]));
			}

			// Ambil detail produk
			$productDetail = $this->MOC_Product->detail($productId);

			// Tambahkan log untuk hasil query
			if ($productDetail === null) {
				log_message('error', 'Product details retrieval failed: ID ' . $productId);
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Produk tidak ditemukan atau tidak aktif'
					]));
			}

			// Normalisasi data produk
			$normalizedProduct = $this->normalizeProductData($productDetail);

			// Kirim respons
			return $this->output
				->set_status_header(200)
				->set_output(json_encode([
					'success' => true,
					'data' => $normalizedProduct
				]));
		} catch (Exception $e) {
			log_message('error', 'Error in getProductDetail: ' . $e->getMessage());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Internal server error',
					'error_details' => $e->getMessage()
				]));
		}
	}

	// Tambahkan method validateProduct()
	private function validateProduct($productId)
	{
		$product = $this->db
			->select('product_id')
			->from('data_product')
			->where('product_id', $productId)
			->where('product_st', '0')
			->where('stock >', 0)
			->get()
			->row_array();

		return $product ? true : false;
	}

	private function normalizeProductData($product)
	{
		return [
			'product_id' => $product['product_id'] ?? null,
			'product_name' => $product['product_name'] ?? 'Produk Tidak Bernama',
			'product_desc' => $product['product_desc'] ?? '',
			'product_price' => floatval($product['product_price'] ?? 0),
			'product_pict' => $product['product_pict'] ?? 'default.png',
			'stock' => intval($product['stock'] ?? 0),
			'api_id' => $product['api_id'] ?? null, // Include API ID
			'is_package' => $product['is_package'] === '1',
			'package_type' => $product['package_type'] ?? null,
			'package_id' => $product['package_id'] ?? null,
			'package_base_price' => floatval($product['package_base_price'] ?? 0),
			'package_details' => $product['package_details'] ?? null
		];
	}

	public function getPackageDetail($productId)
	{
		try {
			$cacheKey = "package_detail_{$productId}";
			$packageData = $this->cache->get($cacheKey);

			if (!$packageData) {
				// Perbaikan query dengan spesifikasi kolom yang jelas
				$package = $this->db
					->select('p.*, 
							  dp.product_name, 
							  dp.product_desc, 
							  dp.product_pict, 
							  dp.stock, 
							  dp.product_price')
					->from('packages p')
					->join('data_product dp', 'p.product_id = dp.product_id')
					// Gunakan dp.product_id untuk menghindari ambiguitas
					->where('dp.product_id', $productId)
					->where('p.deleted_at IS NULL')
					->where('dp.deleted_at IS NULL')
					->get()
					->row_array();

				if (!$package) {
					return $this->output
						->set_content_type('application/json')
						->set_status_header(404)
						->set_output(json_encode([
							'success' => false,
							'message' => 'Paket tidak ditemukan',
							'error_details' => "Tidak ada paket dengan product_id {$productId}"
						]));
				}

				// Perbaikan query kategori paket
				$packageCategories = $this->db
					->select('pc.id, 
							  pc.name, 
							  pc.selection_type, 
							  pcr.required_items, 
							  pcr.max_items, 
							  (SELECT COUNT(pcp.product_id) 
							   FROM package_custom_products pcp 
							   WHERE pcp.category_id = pc.id 
							   AND pcp.package_id = p.id 
							   AND pcp.deleted_at IS NULL) as available_products')
					->from('package_categories pc')
					->join(
						'package_category_requirements pcr',
						'pc.id = pcr.category_id AND pcr.package_id = ' . $this->db->escape($package['id']),
						'inner'
					)
					->join('packages p', 'pcr.package_id = p.id')
					->where('pc.deleted_at IS NULL')
					->order_by('pc.display_order', 'ASC')
					->get()
					->result_array();

				// Perbaikan query produk kustom
				$customProducts = $this->db
					->select('pcp.*, dp.product_name, dp.stock, dp.product_pict, dp.product_desc, COALESCE(pcp.custom_price, dp.product_price) as final_price, pc.name as category_name, pc.selection_type')
					->from('package_custom_products pcp')
					->join('data_product dp', 'pcp.product_id = dp.product_id')
					->join('package_categories pc', 'pc.id = pcp.category_id')
					->where([
						'pcp.package_id' => $package['id'],
						'pcp.deleted_at IS NULL',
						'dp.product_st' => '0',
						'dp.stock >' => 0
					])
					->get()
					->result_array();


				// Kelompokkan produk berdasarkan kategori
				$groupedProducts = [];
				foreach ($customProducts as $product) {
					$groupedProducts[$product['category_id']][] = [
						'id' => $product['product_id'],
						'name' => $product['product_name'],
						'description' => $product['product_desc'],
						'image' => $product['product_pict'],
						'price' => floatval($product['sale_price'] ?? $product['product_price']),
						'stock' => intval($product['stock']),
						'category_id' => $product['category_id'],
						'category_name' => $product['category_name'],
						'selection_type' => $product['selection_type']
					];
				}

				$packageData = [
					'success' => true,
					'data' => [
						'baseInfo' => [
							'id' => $package['id'],
							'product_id' => $package['product_id'],
							'name' => $package['product_name'],
							'description' => $package['product_desc'],
							'image' => $package['product_pict'],
							'base_price' => floatval($package['product_price']),
							'stock' => intval($package['stock'])
						],
						'package_categories' => $packageCategories,
						'products_by_category' => $groupedProducts
					]
				];

				// Cache untuk 5 menit
				$this->cache->save($cacheKey, $packageData, 300);
			}

			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode($packageData));
		} catch (Exception $e) {
			log_message('error', 'Error in getPackageDetail: ' . $e->getMessage());
			return $this->output
				->set_content_type('application/json')
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Internal server error',
					'error_details' => $e->getMessage()
				]));
		}
	}

	public function cart()
	{
		try {
			$this->output->set_content_type('application/json');
			// Validate parameters
			$params = [
				"outlet_id" => $this->input->get("outletId"),
				"brand" => $this->input->get("brand"),
				"table_id" => $this->input->get("tableId"),
			];

			// Log request untuk debugging
			log_message('debug', 'Cart request params: ' . json_encode($params));

			// Validate required parameters
			foreach ($params as $key => $value) {
				if (empty($value)) {
					return $this->output
						->set_status_header(400)
						->set_output(json_encode([
							"success" => false,
							"message" => "Missing required parameter: {$key}"
						]));
				}
			}

			// Get active session
			$session = $this->M_Order->getOne([
				"outlet_id" => $params["outlet_id"],
				"brand" => $params["brand"],
				"table_id" => $params["table_id"],
				"deleted_at" => NULL
			]);

			if (!$session) {
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						"success" => false,
						"message" => "No active session found"
					]));
			}

			// PERBAIKAN: Tambahkan try-catch khusus untuk mengambil cart items
			try {
				// Get cart items with proper handling
				$cartItems = $this->M_Order_Detail->getAll([
					"where" => [
						"order_id" => $session["id"],
						"deleted_at" => NULL
					]
				]);

				// Log untuk debugging
				log_message('debug', 'Found ' . count($cartItems) . ' cart items');
			} catch (Exception $cartError) {
				log_message('error', 'Error fetching cart items: ' . $cartError->getMessage());
				$cartItems = []; // Use empty array on error
			}

			// PERBAIKAN: Tambahkan promo details ke response jika ada
			$promoDetails = null;
			if (!empty($session['discount_code'])) {
				try {
					// Load promo model jika belum loaded
					if (!isset($this->M_Promo)) {
						$this->load->model('promo/M_Promo');
					}

					$promo = $this->M_Promo->getPromoByCode($session['discount_code']);
					if ($promo) {
						$promoDetails = [
							'code' => $session['discount_code'],
							'type' => $promo['promo_type'],
							'discount' => floatval($session['discount_amount'] ?? 0),
							'message' => 'Promo ' . $session['discount_code'] . ' diterapkan',
							'isValid' => true,
							'details' => $promo
						];

						log_message('debug', 'Found active promo: ' . json_encode($promoDetails));
					}
				} catch (Exception $promoError) {
					log_message('error', 'Error fetching promo details: ' . $promoError->getMessage());
				}
			}

			// PERBAIKAN: Wrap processing dalam try-catch terpisah
			try {
				// Process and group items
				$processedItems = $this->processCartItems($cartItems);

				// Calculate summary
				$summary = $this->calculateCartSummary($processedItems);

				$response = [
					"success" => true,
					"data" => [
						"cart" => [
							"regular_items" => $processedItems['regular_items'] ?? [],
							"package_items" => $processedItems['package_items'] ?? [],
							"summary" => $summary,
							"promo" => $promoDetails // Tambahkan informasi promo
						],
						"session" => [
							"id" => $session["id"],
							"status" => $session["status"],
							"name" => $session["name"] ?? '-',
							"created_at" => $session["created_at"],
							"expire_at" => $session["expire_at"],
							"updated_at" => $session["updated_at"] // Tambahkan timestamp update
						]
					]
				];
			} catch (Exception $processError) {
				log_message('error', 'Error processing cart data: ' . $processError->getMessage());

				// Return basic response with empty cart
				$response = [
					"success" => true,
					"data" => [
						"cart" => [
							"regular_items" => [],
							"package_items" => [],
							"summary" => [
								"subtotal" => 0,
								"tax" => 0,
								"total" => 0,
								"regularCount" => 0,
								"packageCount" => 0
							],
							"promo" => $promoDetails
						],
						"session" => [
							"id" => $session["id"],
							"status" => $session["status"],
							"name" => $session["name"] ?? '-',
							"created_at" => $session["created_at"],
							"expire_at" => $session["expire_at"],
							"updated_at" => $session["updated_at"]
						]
					]
				];
			}

			return $this->output
				->set_status_header(200)
				->set_output(json_encode($response));
		} catch (Exception $e) {
			log_message('error', 'Cart general error: ' . $e->getMessage());
			log_message('error', 'Trace: ' . $e->getTraceAsString());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"message" => "Internal server error",
					"error_details" => $e->getMessage()
				]));
		}
	}

	private function processCartItems($items)
	{
		// Inisialisasi array untuk hasil
		$regular_items = [];
		$package_items = [];

		// Log untuk debugging
		log_message('debug', 'Processing cart items: ' . count($items));

		try {
			// Iterasi melalui setiap item
			foreach ($items as $item) {
				// Skip child items - we process them with their package headers
				if (!empty($item['parent_id'])) {
					continue;
				}

				// Get product details with error handling
				try {
					$product = $this->MOC_Product->detail($item['product_id']);
					if (!$product) {
						log_message('warning', 'Product not found for ID: ' . $item['product_id']);
						continue;
					}
				} catch (Exception $productError) {
					log_message('error', 'Error fetching product: ' . $productError->getMessage());
					continue;
				}

				// PERBAIKAN: Prioritaskan harga dari detail order daripada dari tabel produk
				$price = (float)($item['unit_price'] ?? $item['price'] ?? $product['product_price'] ?? 0);
				$quantity = (int)($item['quantity'] ?? $item['qty'] ?? 0);
				$subtotal = (float)($item['subtotal'] ?? ($price * $quantity));

				// PERBAIKAN: Tambahkan penanda untuk item promo
				$isPromoItem = !empty($item['is_promo_item']) ||
					!empty($item['promo_type']) ||
					($price === 0 && $subtotal === 0) ||
					(strpos(strtolower($item['notes'] ?? ''), 'promo') !== false);

				$processedItem = [
					'id' => $item['id'],
					'product_id' => $item['product_id'],
					'product_name' => $product['product_name'] ?? 'Unknown Product',
					'product_pict' => $product['product_pict'] ?? 'default.png',
					'quantity' => $quantity,
					'price' => $price,
					'subtotal' => $subtotal,
					'notes' => $item['notes'] ?? '',
					'stock' => (int)($product['stock'] ?? 0),
					'is_promo_item' => $isPromoItem ? 1 : 0, // Tambahkan penanda promo
					'promo_type' => $item['promo_type'] ?? null  // Tambahkan tipe promo jika ada
				];

				// Cek apakah item adalah package header
				if (!empty($item['package_id'])) {
					// Handle package with proper error handling
					try {
						// Get package items
						$packageItems = $this->M_Order_Detail->getAll([
							"where" => [
								"parent_id" => $item['id'],
								"deleted_at" => NULL
							]
						]);

						$packageItemsProcessed = [];
						$packageBasePrice = (float)$price;

						// Use subtotal from database directly if available
						$packageTotalPrice = (float)($item['subtotal'] ?? 0);

						// If subtotal is not available, calculate from package items
						if ($packageTotalPrice <= 0) {
							$packageTotalPrice = $packageBasePrice;

							foreach ($packageItems as $pkgItem) {
								try {
									$pkgProduct = $this->MOC_Product->detail($pkgItem['product_id']);
									if ($pkgProduct) {
										$pkgPrice = (float)($pkgItem['unit_price'] ?? $pkgItem['price'] ?? $pkgProduct['product_price'] ?? 0);
										$pkgQuantity = (int)($pkgItem['quantity'] ?? $pkgItem['qty'] ?? 1);
										$pkgSubtotal = $pkgPrice * $pkgQuantity;

										$packageTotalPrice += $pkgSubtotal;
									}
								} catch (Exception $pkgItemError) {
									log_message('error', 'Error processing package item: ' . $pkgItemError->getMessage());
									continue;
								}
							}
						}

						// Process package items for display
						foreach ($packageItems as $pkgItem) {
							try {
								$pkgProduct = $this->MOC_Product->detail($pkgItem['product_id']);
								if ($pkgProduct) {
									$pkgPrice = (float)($pkgItem['unit_price'] ?? $pkgItem['price'] ?? $pkgProduct['product_price'] ?? 0);
									$pkgQuantity = (int)($pkgItem['quantity'] ?? $pkgItem['qty'] ?? 1);
									$pkgSubtotal = $pkgPrice * $pkgQuantity;

									// PERBAIKAN: Tambahkan penanda untuk item promo dalam paket
									$isPkgPromoItem = !empty($pkgItem['is_promo_item']) ||
										!empty($pkgItem['promo_type']) ||
										($pkgPrice === 0 && $pkgSubtotal === 0) ||
										(strpos(strtolower($pkgItem['notes'] ?? ''), 'promo') !== false);

									$packageItemDetail = [
										'id' => $pkgItem['id'],
										'product_id' => $pkgItem['product_id'],
										'product_name' => $pkgProduct['product_name'] ?? 'Unknown Product',
										'product_pict' => $pkgProduct['product_pict'] ?? 'default.png',
										'quantity' => $pkgQuantity,
										'price' => $pkgPrice,
										'subtotal' => $pkgSubtotal,
										'notes' => $pkgItem['notes'] ?? '',
										'stock' => (int)($pkgProduct['stock'] ?? 0),
										'is_promo_item' => $isPkgPromoItem ? 1 : 0,
										'promo_type' => $pkgItem['promo_type'] ?? null
									];

									$packageItemsProcessed[] = $packageItemDetail;
								}
							} catch (Exception $pkgItemError) {
								log_message('error', 'Error processing package item: ' . $pkgItemError->getMessage());
								continue;
							}
						}

						$package_items[] = [
							'id' => $item['id'],
							'name' => $product['product_name'],
							'base_price' => $packageBasePrice,
							'total' => $packageTotalPrice,
							'items' => $packageItemsProcessed
						];
					} catch (Exception $packageProcessError) {
						log_message('error', 'Error processing package: ' . $packageProcessError->getMessage());
						// Skip this package if processing fails
						continue;
					}
				} else {
					// This is a regular item
					$regular_items[] = $processedItem;
				}
			}
		} catch (Exception $generalError) {
			log_message('error', 'General error in processCartItems: ' . $generalError->getMessage());
			// Return empty arrays on general error
			return [
				'regular_items' => [],
				'package_items' => []
			];
		}

		return [
			'regular_items' => $regular_items,
			'package_items' => $package_items
		];
	}

	private function calculateCartSummary($processedItems)
	{
		// Inisialisasi summary
		$regularSubtotal = 0;
		$packageSubtotal = 0;
		$regularCount = count($processedItems['regular_items'] ?? []);
		$packageCount = count($processedItems['package_items'] ?? []);

		// Hitung subtotal untuk regular items
		foreach ($processedItems['regular_items'] as $item) {
			$regularSubtotal += $item['subtotal'];
		}

		// Hitung subtotal untuk package items
		foreach ($processedItems['package_items'] as $package) {
			$packageSubtotal += $package['total'];
		}

		// Hitung total dan pajak
		$subtotal = $regularSubtotal + $packageSubtotal;
		$tax = $subtotal * 0.1; // 10% pajak
		$total = $subtotal + $tax;

		return [
			"regularCount" => $regularCount,
			"packageCount" => $packageCount,
			"regularSubtotal" => $regularSubtotal,
			"packageSubtotal" => $packageSubtotal,
			"subtotal" => $subtotal,
			"tax" => $tax,
			"total" => $total
		];
	}

	private function calculateOrderSummary($orderDetails)
	{
		log_message('debug', '=== Start Order Summary Calculation (COMPREHENSIVE FIX) ===');
		log_message('debug', 'Order Details Count: ' . count($orderDetails));

		$summary = [
			"total_amount" => 0,          // Subtotal dari semua item yang dibayar
			"total_items" => 0,           // Jumlah item total (headers only)
			"packages" => [],             // Detail package
			"regular_items" => [],        // Item reguler
			"total_quantity" => 0,        // Total kuantitas produk
			"discount_amount" => 0,       // Diskon regular (percentage/nominal)
			"bundle_bogo_discount" => 0   // Nilai produk gratis (bundling/BOGO)
		];

		// PERBAIKAN: Track package headers dan children
		$packageHeaders = [];
		$packageChildren = [];

		// PERBAIKAN: Kategorisasi yang lebih tepat
		foreach ($orderDetails as $detail) {
			$detailData = $detail["detail"] ?? $detail;
			$productData = $detail["product"] ?? [];

			$detailId = $detailData["id"] ?? null;
			$parentId = $detailData["parent_id"] ?? null;
			$packageId = $detailData["package_id"] ?? null;

			log_message('debug', "Processing item: {$detailData['product_name']}, ID: {$detailId}, Parent: {$parentId}, Package: {$packageId}");

			if (!empty($parentId)) {
				// Item anak paket
				if (!isset($packageChildren[$parentId])) {
					$packageChildren[$parentId] = [];
				}
				$packageChildren[$parentId][] = $detail;
			} elseif (!empty($packageId)) {
				// Header paket
				$packageHeaders[$detailId] = $detail;
			} else {
				// Item reguler (bukan paket)
				$quantity = (int)($detailData["quantity"] ?? 1);
				$price = (float)($detailData["unit_price"] ?? $detailData["price"] ?? 0);
				$itemSubtotal = (float)($detailData["subtotal"] ?? ($price * $quantity));

				// PERBAIKAN: Deteksi promo item dengan logika yang konsisten
				$isPromoFreeItem = $this->isPromoFreeItem($detail);

				if ($isPromoFreeItem) {
					// Item gratis dari promo
					$originalValue = $this->getOriginalItemValue($detail, $itemSubtotal);
					$summary["bundle_bogo_discount"] += $originalValue;
					log_message('debug', 'Free promo item added to bundle_bogo_discount: ' . $originalValue);
				} else {
					// Item reguler yang dibayar
					$summary["total_amount"] += $itemSubtotal;
					log_message('debug', 'Regular item added to total_amount: ' . $itemSubtotal);
				}

				$summary["regular_items"][] = [
					"product_id" => $detailData["product_id"] ?? null,
					"product_name" => $detailData["product_name"] ?? $productData["product_name"] ?? 'Unknown',
					"quantity" => $quantity,
					"price" => $price,
					"subtotal" => $itemSubtotal,
					"is_promo_free" => $isPromoFreeItem
				];

				$summary["total_items"]++;
				$summary["total_quantity"] += $quantity;
			}
		}

		// PERBAIKAN: Proses package headers dengan children
		foreach ($packageHeaders as $headerId => $header) {
			$headerDetail = $header["detail"] ?? $header;
			$quantity = (int)($headerDetail["quantity"] ?? 1);
			$headerPrice = (float)($headerDetail["unit_price"] ?? $headerDetail["price"] ?? 0);
			$packageSubtotal = (float)($headerDetail["subtotal"] ?? 0);

			// PERBAIKAN: Hitung total package termasuk children
			$totalPackagePrice = $packageSubtotal;

			// Tambahkan harga children jika ada
			if (isset($packageChildren[$headerId])) {
				foreach ($packageChildren[$headerId] as $child) {
					$childDetail = $child["detail"] ?? $child;
					$childPrice = (float)($childDetail["unit_price"] ?? $childDetail["price"] ?? 0);
					$childQuantity = (int)($childDetail["quantity"] ?? 1);
					$childSubtotal = (float)($childDetail["subtotal"] ?? ($childPrice * $childQuantity));

					// PERBAIKAN: Hanya tambahkan jika bukan item promo gratis
					if (!$this->isPromoFreeItem($child)) {
						// Subtotal sudah termasuk dalam package, jadi tidak perlu ditambah lagi
						// Tapi jika ada additional charge, tambahkan
						if ($childPrice > 0) {
							log_message('debug', "Package child with additional charge: {$childDetail['product_name']}, price: {$childPrice}");
						}
					} else {
						// Item gratis dalam package
						$originalValue = $this->getOriginalItemValue($child, $childSubtotal);
						$summary["bundle_bogo_discount"] += $originalValue;
						log_message('debug', "Free item in package added to bundle_bogo_discount: {$originalValue}");
					}
				}
			}

			// PERBAIKAN: Deteksi apakah package ini gratis dari promo
			$isPromoFreePackage = $this->isPromoFreeItem($header);

			if ($isPromoFreePackage) {
				// Package gratis dari promo
				$summary["bundle_bogo_discount"] += $totalPackagePrice;
				log_message('debug', 'Free promo package added to bundle_bogo_discount: ' . $totalPackagePrice);
			} else {
				// Package reguler yang dibayar
				$summary["total_amount"] += $totalPackagePrice;
				log_message('debug', 'Regular package added to total_amount: ' . $totalPackagePrice);
			}

			$summary["packages"][] = [
				"package_id" => $headerDetail["package_id"] ?? null,
				"product_name" => $headerDetail["product_name"] ?? 'Unknown Package',
				"quantity" => $quantity,
				"total_price" => $totalPackagePrice,
				"is_promo_free" => $isPromoFreePackage
			];

			$summary["total_items"]++;
			$summary["total_quantity"] += $quantity;
		}

		// PERBAIKAN: Handle regular discount dari order
		$order_id = null;
		if (!empty($orderDetails)) {
			$order_id = ($orderDetails[0]["detail"] ?? $orderDetails[0])["order_id"] ?? null;
		}

		if ($order_id) {
			$order = $this->M_Order->getOne(['id' => $order_id]);
			if ($order && isset($order['discount_amount']) && $order['discount_amount'] > 0) {
				// Tentukan jenis discount berdasarkan promo code
				$isRegularDiscount = true;
				if (!empty($order['discount_code'])) {
					if (!isset($this->M_Promo)) {
						$this->load->model('promo/M_Promo');
					}

					try {
						$promo = $this->M_Promo->getPromoByCode($order['discount_code']);
						if ($promo && ($promo['promo_type'] === 'bundling' || $promo['promo_type'] === 'bogo')) {
							$isRegularDiscount = false;
							log_message('debug', 'Bundling/BOGO discount detected, not adding to regular discount');
						}
					} catch (Exception $e) {
						log_message('error', 'Error fetching promo details: ' . $e->getMessage());
					}
				}

				if ($isRegularDiscount) {
					// Regular discount (percentage/nominal) yang mengurangi total
					$summary["discount_amount"] = floatval($order['discount_amount']);
					log_message('debug', 'Applied regular discount: ' . $summary["discount_amount"]);
				}
			}
		}

		// PERBAIKAN: Perhitungan tax yang konsisten
		$taxableAmount = max(0, $summary["total_amount"] - $summary["discount_amount"]);
		$summary["tax"] = $taxableAmount * 0.1; // 10% tax
		$summary["grand_total"] = $taxableAmount + $summary["tax"];

		log_message('debug', 'Final Summary Calculation: ' . json_encode([
			'total_amount' => $summary["total_amount"],
			'discount_amount' => $summary["discount_amount"],
			'bundle_bogo_discount' => $summary["bundle_bogo_discount"],
			'taxable_amount' => $taxableAmount,
			'tax' => $summary["tax"],
			'grand_total' => $summary["grand_total"],
			'total_items' => $summary["total_items"],
			'total_quantity' => $summary["total_quantity"]
		]));

		log_message('debug', '=== End Order Summary Calculation (COMPREHENSIVE FIX) ===');
		return $summary;
	}
	private function isPromoFreeItem($detail)
	{
		// Item gratis jika:
		// 1. is_promo_item = 1 DAN promo_type bundling/bogo
		// 2. Atau harga 0 dengan indikasi promo di notes
		$isPromoItem = !empty($detail["detail"]["is_promo_item"]) && $detail["detail"]["is_promo_item"] == 1;
		$promoType = $detail["detail"]["promo_type"] ?? null;
		$price = (float)($detail["detail"]["unit_price"] ?? $detail["detail"]["price"] ?? 0);
		$notes = strtolower($detail["detail"]["notes"] ?? '');

		return (
			($isPromoItem && ($promoType === 'bundling' || $promoType === 'bogo')) ||
			($price === 0.0 && (strpos($notes, 'gratis') !== false || strpos($notes, 'promo') !== false))
		);
	}

	private function getOriginalItemValue($detail, $fallbackValue)
	{
		// Ambil harga asli jika tersedia, atau gunakan fallback
		$originalPrice = (float)($detail["detail"]["original_price"] ?? 0);
		$quantity = (int)($detail["detail"]["quantity"] ?? 1);

		if ($originalPrice > 0) {
			return $originalPrice * $quantity;
		}

		return $fallbackValue;
	}

	private function revalidateAppliedPromo($orderDetailId)
	{
		// Log untuk debugging
		log_message('debug', '=== Start Promo Revalidation ===');
		log_message('debug', 'Revalidating promo for order detail ID: ' . $orderDetailId);

		// Dapatkan informasi order dari detail item
		$orderDetail = $this->M_Order_Detail->getOne(['id' => $orderDetailId]);
		if (!$orderDetail) {
			log_message('debug', 'Order detail not found: ' . $orderDetailId);
			return false;
		}

		$orderId = $orderDetail['order_id'];

		// Dapatkan informasi order
		$order = $this->M_Order->getOne(['id' => $orderId]);
		if (!$order) {
			log_message('debug', 'Order not found: ' . $orderId);
			return false;
		}

		// Jika tidak ada kode promo aktif, tidak perlu validasi
		if (empty($order['discount_code'])) {
			log_message('debug', 'No active promo code for this order');
			return false;
		}

		log_message('debug', 'Found active promo code: ' . $order['discount_code']);

		// Load model promo jika belum
		if (!isset($this->M_Promo)) {
			$this->load->model('promo/M_Promo');
		}

		// Hitung ulang total order saat ini
		$orderTotal = $this->M_Order_Detail->getTotalAmount($orderId);
		log_message('debug', 'Current order total: ' . $orderTotal);

		// Dapatkan detail cart untuk validasi
		$cartDetails = $this->prepareCartDetails($orderId);
		log_message('debug', 'Cart details prepared, item count: ' . count($cartDetails));

		// Validasi promo dengan kondisi keranjang terbaru
		try {
			$validationResult = $this->M_Promo->validatePromo(
				$order['discount_code'],
				$order['brand'],
				$orderTotal,
				$cartDetails,
				true // Calculate eligible total
			);

			log_message('debug', 'Promo validation result: ' . json_encode($validationResult));
		} catch (Exception $e) {
			log_message('error', 'Error validating promo: ' . $e->getMessage());
			$validationResult = [
				'valid' => false,
				'message' => 'Error validating promo: ' . $e->getMessage()
			];
		}

		// Persiapkan hasil validasi
		$promoStatus = [
			'valid' => $validationResult['valid'] ?? false,
			'message' => $validationResult['message'] ?? 'Unknown validation error',
			'discount' => $validationResult['valid'] ? ($validationResult['discount_amount'] ?? 0) : 0,
			'promo_code' => $order['discount_code']
		];

		// Jika promo tidak valid lagi, hapus dari order
		if (!$promoStatus['valid']) {
			log_message('debug', 'Promo is no longer valid - removing from order');

			// Update order untuk menghapus diskon
			$this->db->trans_begin();

			$this->M_Order->update(['id' => $orderId], [
				'discount_code' => null,
				'discount_amount' => 0,
				'updated_at' => date('Y-m-d H:i:s')
			]);

			// Hapus item promo gratis jika ada
			if ($validationResult['promo_type'] == 'bundling' || $validationResult['promo_type'] == 'bogo') {
				// Mark promo items as deleted
				$this->db->where('order_id', $orderId)
					->where('is_promo_item', 1)
					->update('order_details', ['deleted_at' => date('Y-m-d H:i:s')]);

				log_message('debug', 'Removed free promo items from order');
			}

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				log_message('error', 'Failed to update order after promo invalidation');
			} else {
				$this->db->trans_commit();
				log_message('debug', 'Successfully removed invalid promo from order');
			}
		}
		// Jika promo masih valid tapi nilai diskon berubah, update nilai diskon
		else if ($validationResult['valid'] && $order['discount_amount'] != $validationResult['discount_amount']) {
			log_message('debug', 'Promo is still valid but discount amount changed - updating');
			log_message('debug', 'Old discount: ' . $order['discount_amount'] . ', New discount: ' . $validationResult['discount_amount']);

			$this->M_Order->update(['id' => $orderId], [
				'discount_amount' => $validationResult['discount_amount'],
				'updated_at' => date('Y-m-d H:i:s')
			]);
		}

		// Simpan status validasi untuk digunakan di UI
		$this->session->set_userdata('promo_status', $promoStatus);

		log_message('debug', 'Final promo status: ' . json_encode($promoStatus));
		log_message('debug', '=== End Promo Revalidation ===');

		return $promoStatus;
	}

	private function getOrderDetailByTable($tableId, $outletId, $brand)
	{
		try {
			// Log para debugging
			log_message('debug', '=== Get Order Detail By Table ===');
			log_message('debug', "Parameters: tableId=$tableId, outletId=$outletId, brand=$brand");

			// PERBAIKAN: Jangan filter berdasarkan status, ambil order aktif apapun statusnya
			$order = $this->M_Order->getOne([
				'outlet_id' => $outletId,
				'table_id' => $tableId,
				'brand' => $brand,
				'deleted_at' => NULL
			]);

			if (!$order) {
				log_message('debug', 'No active order found for this table.');
				return [
					'order' => null,
					'details' => [],
					'summary' => null
				];
			}

			log_message('debug', 'Found order: ' . json_encode($order));

			// PERBAIKAN: Ambil semua detail order dengan join yang benar
			$this->db->select('od.*, od.unit_price as price, dp.product_name, dp.product_pict, dp.stock');
			$this->db->from('order_details od');
			$this->db->join('data_product dp', 'od.product_id = dp.product_id', 'left');
			$this->db->where('od.order_id', $order['id']);
			$this->db->where('od.deleted_at IS NULL');
			$orderDetails = $this->db->get()->result_array();

			log_message('debug', 'Raw Order Details count: ' . count($orderDetails));

			// Procesar y agrupar items
			$processedDetails = [];
			$packageItems = [];
			$subtotal = 0;

			// PERBAIKAN: Pertama pisahkan package items berdasarkan parent_id
			foreach ($orderDetails as $detail) {
				if (!empty($detail['parent_id'])) {
					if (!isset($packageItems[$detail['parent_id']])) {
						$packageItems[$detail['parent_id']] = [];
					}
					$packageItems[$detail['parent_id']][] = $detail;
				}
			}

			// Procesar details (baik item regular maupun package headers)
			foreach ($orderDetails as $detail) {
				// Skip package items - akan diproses dengan parent
				if (!empty($detail['parent_id'])) {
					continue;
				}

				// Ambil unit price dan quantity yang konsisten
				$unitPrice = (float)($detail['unit_price'] ?? $detail['price'] ?? 0);
				$quantity = (int)($detail['quantity']);

				// PERBAIKAN: Gunakan subtotal dari DB jika ada
				$itemSubtotal = (float)($detail['subtotal'] ?? ($unitPrice * $quantity));

				// Cek apakah item ini package header
				$isPackage = !empty($detail['package_id']);
				$packageItemsList = isset($packageItems[$detail['id']]) ? $packageItems[$detail['id']] : [];

				$processedDetail = [
					'id' => $detail['id'],
					'product_id' => $detail['product_id'],
					'product_name' => $detail['product_name'] ?? 'Unknown Product',
					'quantity' => $quantity,
					'unit_price' => $unitPrice,
					'price' => $unitPrice, // Duplicate for compatibility
					'subtotal' => $itemSubtotal,
					'notes' => $detail['notes'] ?? '',
					'is_package' => $isPackage,
					'package_id' => $detail['package_id'] ?? null,
					'parent_id' => null
				];

				$processedDetails[] = $processedDetail;
				$subtotal += $itemSubtotal;

				// Jika adalah package, proses item-itemnya
				if ($isPackage && !empty($packageItemsList)) {
					foreach ($packageItemsList as $packageItem) {
						$packageItemPrice = (float)($packageItem['unit_price'] ?? $packageItem['price'] ?? 0);
						$packageItemQty = (int)($packageItem['quantity']);
						$packageItemSubtotal = (float)($packageItem['subtotal'] ?? ($packageItemPrice * $packageItemQty));

						$processedDetails[] = [
							'id' => $packageItem['id'],
							'product_id' => $packageItem['product_id'],
							'product_name' => "-- " . ($packageItem['product_name'] ?? 'Package Item'),
							'quantity' => $packageItemQty,
							'unit_price' => $packageItemPrice,
							'price' => $packageItemPrice,
							'subtotal' => $packageItemSubtotal,
							'notes' => $packageItem['notes'] ?? '',
							'is_package_item' => true,
							'parent_id' => $detail['id']
						];
					}
				}
			}

			// Hitung total
			$tax = $subtotal * 0.1;
			$total = $subtotal + $tax;

			log_message('debug', 'Final Calculation - Subtotal: ' . $subtotal . ', Tax: ' . $tax . ', Total: ' . $total);

			return [
				'order' => $order,
				'details' => $processedDetails,
				'summary' => [
					'subtotal' => $subtotal,
					'tax' => $tax,
					'total' => $total,
					'status' => $order['status'] // PERBAIKAN: Tambahkan status order ke summary
				]
			];
		} catch (Exception $e) {
			log_message('error', 'Error in getOrderDetailByTable: ' . $e->getMessage());
			log_message('error', 'Stack trace: ' . $e->getTraceAsString());
			return [
				'order' => null,
				'details' => [],
				'summary' => null,
				'error' => $e->getMessage()
			];
		}
	}
	private function formatCurrency($amount)
	{
		return 'Rp ' . number_format($amount, 0, ',', '.') . ',-';
	}

	public function updateQuantity()
	{
		try {
			$this->output->set_content_type('application/json');
			log_message('debug', '=== Start Update Quantity ===');

			// Parse data dari request
			$data = json_decode($this->input->raw_input_stream);
			log_message('debug', 'Update quantity request data: ' . json_encode($data));

			// Validasi input parameter
			if (!isset($data->itemId) || !isset($data->quantity)) {
				log_message('error', 'Missing required parameters for update quantity');
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Invalid input parameters'
					]));
			}

			// Validasi nilai quantity
			$quantity = intval($data->quantity);
			if ($quantity < 1) {
				log_message('error', 'Invalid quantity value: ' . $quantity);
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Quantity must be at least 1'
					]));
			}

			// Verifikasi stock sebelum update
			$orderDetail = $this->M_Order_Detail->getOne(['id' => $data->itemId]);
			if (!$orderDetail) {
				log_message('error', 'Order detail not found: ' . $data->itemId);
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Item not found in order'
					]));
			}

			// Cek stok produk
			$product = $this->MOC_Product->detail($orderDetail['product_id']);
			if (!$product) {
				log_message('error', 'Product not found: ' . $orderDetail['product_id']);
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Product not found'
					]));
			}

			// Verifikasi stok mencukupi
			if ($quantity > $product['stock']) {
				log_message('error', 'Insufficient stock. Requested: ' . $quantity . ', Available: ' . $product['stock']);
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Insufficient stock. Only ' . $product['stock'] . ' available.'
					]));
			}

			// Begin transaction
			$this->db->trans_begin();

			try {
				// Hitung subtotal baru
				$price = floatval($orderDetail['unit_price'] ?? $orderDetail['price'] ?? $product['product_price'] ?? 0);
				$subtotal = $price * $quantity;

				// Update kuantitas dalam database dengan subtotal yang baru dihitung
				$result = $this->M_Order_Detail->update(
					['id' => $data->itemId],
					[
						'quantity' => $quantity,
						'subtotal' => $subtotal,
						'updated_at' => date('Y-m-d H:i:s')
					]
				);

				if (!$result) {
					$this->db->trans_rollback();
					log_message('error', 'Failed to update quantity in database');
					throw new Exception('Failed to update quantity');
				}

				log_message('debug', 'Quantity updated successfully. New quantity: ' . $quantity . ', New subtotal: ' . $subtotal);

				// PERBAIKAN UTAMA: Lakukan revalidasi promo setelah update quantity
				$orderId = $orderDetail['order_id'];
				$order = $this->M_Order->getOne(['id' => $orderId]);
				$promoStatus = null;
				$promoRefreshItems = []; // PERBAIKAN: Array untuk menyimpan item yang perlu direfresh

				// Revalidasi promo hanya jika order memiliki kode promo aktif
				if ($order && !empty($order['discount_code'])) {
					log_message('debug', 'Revalidating promo after quantity update: ' . $order['discount_code']);

					// Load model promo jika belum
					if (!isset($this->M_Promo)) {
						$this->load->model('promo/M_Promo');
					}

					// Dapatkan informasi order yang diperbarui
					$orderTotal = $this->M_Order_Detail->getTotalAmount($orderId);

					// Dapatkan detail cart untuk validasi
					$cartDetails = $this->prepareCartDetails($orderId);

					// Validasi promo dengan kondisi keranjang terbaru
					$validationResult = $this->M_Promo->validatePromo(
						$order['discount_code'],
						$order['brand'],
						$orderTotal,
						$cartDetails,
						true // Calculate eligible total
					);

					// Persiapkan hasil validasi
					$promoStatus = [
						'valid' => $validationResult['valid'] ?? false,
						'message' => $validationResult['message'] ?? 'Unknown validation error',
						'discount' => $validationResult['valid'] ? ($validationResult['discount_amount'] ?? 0) : 0,
						'promo_code' => $order['discount_code']
					];

					// PERBAIKAN: Tangani logika pembaruan item promo berdasarkan perubahan kuantitas
					if ($validationResult['promo_type'] == 'bogo' || $validationResult['promo_type'] == 'bundling') {
						// Dapatkan semua item promo saat ini
						$currentPromoItems = $this->db->select('*')
							->from('order_details')
							->where('order_id', $orderId)
							->where('is_promo_item', 1)
							->where('deleted_at IS NULL')
							->get()->result_array();

						log_message('debug', 'Current promo items: ' . count($currentPromoItems));

						if ($validationResult['valid']) {
							// Promo masih valid, periksa apakah perlu menambah/mengurangi item promo gratis
							if ($validationResult['promo_type'] == 'bogo') {
								// Handle BOGO items update
								$bogoResult = $this->M_Promo->checkBogoEligibility(
									$validationResult['promo']['promo_id'],
									$cartDetails
								);

								if ($bogoResult['eligible']) {
									// Bandingkan item promo saat ini dengan yang seharusnya berdasarkan validasi
									$this->handlePromoItemsUpdate(
										$orderId,
										$currentPromoItems,
										$bogoResult['bogos'],
										'bogo',
										$promoRefreshItems
									);
								}
							} else if ($validationResult['promo_type'] == 'bundling') {
								// Handle Bundle items update
								$bundleResult = $this->M_Promo->checkBundleEligibility(
									$validationResult['promo']['promo_id'],
									$cartDetails
								);

								if ($bundleResult['eligible']) {
									// Bandingkan item promo saat ini dengan yang seharusnya berdasarkan validasi
									$this->handlePromoItemsUpdate(
										$orderId,
										$currentPromoItems,
										$bundleResult['bundles'],
										'bundling',
										$promoRefreshItems
									);
								}
							}
						} else {
							// Promo tidak valid lagi, tandai semua item promo untuk dihapus
							foreach ($currentPromoItems as $item) {
								$promoRefreshItems[] = [
									'action' => 'remove',
									'id' => $item['id'],
									'product_id' => $item['product_id'],
									'promo_type' => $item['promo_type']
								];
							}
						}
					}

					// Jika promo tidak valid lagi, hapus dari order
					if (!$promoStatus['valid']) {
						log_message('debug', 'Promo is no longer valid - removing from order');

						// Update order untuk menghapus diskon
						$this->M_Order->update(['id' => $orderId], [
							'discount_code' => null,
							'discount_amount' => 0,
							'updated_at' => date('Y-m-d H:i:s')
						]);

						// Hapus item promo gratis jika ada (untuk promo bundling/BOGO)
						if (
							isset($validationResult['promo_type']) &&
							($validationResult['promo_type'] == 'bundling' || $validationResult['promo_type'] == 'bogo')
						) {
							// Mark promo items as deleted
							$this->db->where('order_id', $orderId)
								->where('is_promo_item', 1)
								->update('order_details', ['deleted_at' => date('Y-m-d H:i:s')]);

							log_message('debug', 'Removed free promo items from order');
						}
					}
					// Jika promo masih valid tapi nilai diskon berubah, update nilai diskon
					else if ($validationResult['valid'] && $order['discount_amount'] != $validationResult['discount_amount']) {
						log_message('debug', 'Promo is still valid but discount amount changed - updating');
						log_message('debug', 'Old discount: ' . $order['discount_amount'] . ', New discount: ' . $validationResult['discount_amount']);

						$this->M_Order->update(['id' => $orderId], [
							'discount_amount' => $validationResult['discount_amount'],
							'updated_at' => date('Y-m-d H:i:s')
						]);
					}
				}

				$this->db->trans_commit();

				// Hitung cart total baru setelah update
				$total = $this->M_Order_Detail->getTotalAmount($orderId);

				// Siapkan respons dengan status promo terbaru
				$response = [
					'success' => true,
					'message' => 'Quantity updated successfully',
					'data' => [
						'itemId' => $data->itemId,
						'newQuantity' => $quantity,
						'newSubtotal' => $subtotal,
						'total' => $total
					]
				];

				// Tambahkan status promo ke respons jika ada
				if ($promoStatus) {
					$response['promo_status'] = $promoStatus;
				}

				// PERBAIKAN: Tambahkan informasi item yang perlu diperbarui di frontend
				if (!empty($promoRefreshItems)) {
					$response['refresh_items'] = $promoRefreshItems;
				}

				log_message('debug', '=== End Update Quantity ===');

				return $this->output
					->set_content_type('application/json')
					->set_output(json_encode($response));
			} catch (Exception $innerEx) {
				$this->db->trans_rollback();
				log_message('error', 'Error in update quantity transaction: ' . $innerEx->getMessage());
				throw $innerEx;
			}
		} catch (Exception $e) {
			log_message('error', 'Error in updateQuantity: ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => $e->getMessage()
				]));
		}
	}

	private function handlePromoItemsUpdate($orderId, $currentItems, $eligibleItems, $promoType, &$refreshItems)
	{
		log_message('debug', "=== Handling promo items update for $promoType ===");

		// Kumpulkan informasi item yang memenuhi syarat saat ini
		$eligibleProductMap = [];

		if ($promoType == 'bogo') {
			// Format untuk BOGO
			foreach ($eligibleItems as $bogo) {
				$freeProductId = $bogo['free_product_id'];
				$freeQuantity = $bogo['total_free'];
				$productName = $bogo['free_product_name'];

				// Dapatkan detail produk
				$product = $this->db->select('product_price, product_pict')
					->from('data_product')
					->where('product_id', $freeProductId)
					->get()->row_array();

				$eligibleProductMap[$freeProductId] = [
					'product_id' => $freeProductId,
					'quantity' => $freeQuantity,
					'product_name' => $productName,
					'product_pict' => $product['product_pict'] ?? null,
					'price' => $product['product_price'] ?? 0
				];
			}
		} else if ($promoType == 'bundling') {
			// Format untuk Bundling
			foreach ($eligibleItems as $bundle) {
				$freeProductId = $bundle['free_product_id'];
				$freeQuantity = $bundle['free_quantity'];
				$productName = $bundle['free_product_name'];

				// Dapatkan detail produk
				$product = $this->db->select('product_price, product_pict')
					->from('data_product')
					->where('product_id', $freeProductId)
					->get()->row_array();

				$eligibleProductMap[$freeProductId] = [
					'product_id' => $freeProductId,
					'quantity' => $freeQuantity,
					'product_name' => $productName,
					'product_pict' => $product['product_pict'] ?? null,
					'price' => $product['product_price'] ?? 0
				];
			}
		}

		log_message('debug', 'Eligible products map: ' . json_encode($eligibleProductMap));

		// Kumpulkan informasi item promo saat ini
		$currentProductMap = [];
		foreach ($currentItems as $item) {
			if ($item['promo_type'] == $promoType) {
				$productId = $item['product_id'];

				if (!isset($currentProductMap[$productId])) {
					$currentProductMap[$productId] = [
						'id' => $item['id'],
						'product_id' => $productId,
						'quantity' => $item['quantity'],
						'product_name' => null // Nama produk akan diisi nanti jika diperlukan
					];
				} else {
					// Jika sudah ada, tambahkan quantity
					$currentProductMap[$productId]['quantity'] += $item['quantity'];
				}
			}
		}

		log_message('debug', 'Current products map: ' . json_encode($currentProductMap));

		// Produk yang perlu ditambahkan
		foreach ($eligibleProductMap as $productId => $eligibleProduct) {
			if (!isset($currentProductMap[$productId])) {
				// Produk ini perlu ditambahkan
				$productPrice = $eligibleProduct['price'];

				// Tambahkan ke database
				$insertData = [
					'order_id' => $orderId,
					'product_id' => $productId,
					'quantity' => $eligibleProduct['quantity'],
					'unit_price' => 0, // Produk gratis
					'subtotal' => 0,
					'notes' => 'Produk ' . $promoType . ' gratis',
					'created_at' => date('Y-m-d H:i:s'),
					'is_promo_item' => 1,
					'promo_type' => $promoType,
					'original_price' => $productPrice // Simpan harga asli untuk referensi
				];

				$this->db->insert('order_details', $insertData);
				$newItemId = $this->db->insert_id();

				// Tambahkan ke daftar item yang perlu direfresh
				$refreshItems[] = [
					'action' => 'add',
					'id' => $newItemId,
					'product_id' => $productId,
					'product_name' => $eligibleProduct['product_name'],
					'product_pict' => $eligibleProduct['product_pict'],
					'quantity' => $eligibleProduct['quantity'],
					'price' => 0,
					'subtotal' => 0,
					'original_price' => $productPrice,
					'is_promo_item' => 1,
					'promo_type' => $promoType,
					'notes' => 'Produk ' . $promoType . ' gratis'
				];

				log_message('debug', "Added new promo item: Product ID $productId, Quantity {$eligibleProduct['quantity']}");
			}
		}

		// Produk yang perlu diperbarui
		foreach ($currentProductMap as $productId => $currentProduct) {
			if (isset($eligibleProductMap[$productId])) {
				$eligibleQuantity = $eligibleProductMap[$productId]['quantity'];
				$currentQuantity = $currentProduct['quantity'];

				if ($eligibleQuantity != $currentQuantity) {
					// Perbarui quantity di database
					$this->db->where('id', $currentProduct['id'])
						->update('order_details', [
							'quantity' => $eligibleQuantity,
							'updated_at' => date('Y-m-d H:i:s')
						]);

					// Tambahkan ke daftar item yang perlu direfresh
					$refreshItems[] = [
						'action' => 'update',
						'id' => $currentProduct['id'],
						'product_id' => $productId,
						'quantity' => $eligibleQuantity,
						'subtotal' => 0
					];

					log_message('debug', "Updated promo item: Product ID $productId, Quantity $currentQuantity -> $eligibleQuantity");
				}
			} else {
				// Produk ini tidak lagi memenuhi syarat, hapus
				$this->db->where('id', $currentProduct['id'])
					->update('order_details', ['deleted_at' => date('Y-m-d H:i:s')]);

				// Tambahkan ke daftar item yang perlu direfresh
				$refreshItems[] = [
					'action' => 'remove',
					'id' => $currentProduct['id'],
					'product_id' => $productId
				];

				log_message('debug', "Removed promo item: Product ID $productId");
			}
		}

		log_message('debug', "=== Finished promo items update, items to refresh: " . count($refreshItems) . " ===");
	}

	public function countCart()
	{
		try {
			$this->output->set_content_type('application/json');

			// 1. Validasi parameter
			$params = [
				"outlet_id" => $this->input->get("outletId"),
				"brand" => $this->input->get("brand"),
				"table_id" => $this->input->get("tableId"),
			];

			foreach ($params as $key => $value) {
				if (empty($value)) {
					return $this->sendErrorResponse(
						400,
						"001",
						"Missing required parameter: {$key}",
						$this->getEmptyCartData()
					);
				}
			}

			// 2. Get active session dengan proper error handling
			$session = $this->M_Order->getOne([
				"outlet_id" => $params["outlet_id"],
				"brand" => $params["brand"],
				"table_id" => $params["table_id"],
				"status" => $this->M_Order::STATUS_RESERVED,
				"deleted_at" => NULL
			]);

			if (!$session) {
				return $this->sendSuccessResponse(
					"002",
					"No active session",
					$this->getEmptyCartData()
				);
			}

			// 3. Cek cache untuk optimasi
			$cacheKey = "cart_count_{$session['id']}";
			$cachedData = $this->cache->file->get($cacheKey);

			if ($cachedData !== FALSE) {
				return $this->sendSuccessResponse(
					"003",
					"Cart count retrieved from cache",
					$cachedData
				);
			}

			// 4. Hitung cart data dengan proper error handling
			$cartData = $this->calculateCartData($session);

			// 5. Simpan ke cache untuk 5 menit
			$this->cache->file->save($cacheKey, $cartData, 300);

			return $this->sendSuccessResponse(
				"000",
				"Cart count retrieved successfully",
				$cartData
			);
		} catch (Exception $e) {
			log_message('error', 'Error in countCart(): ' . $e->getMessage());
			return $this->sendErrorResponse(
				500,
				"999",
				"Internal server error",
				$this->getEmptyCartData()
			);
		}
	}

	private function calculateCartData($session)
	{
		// 1. Ambil item reguler dengan proper join
		$regularItems = $this->M_Order_Detail->getAll([
			"where" => [
				"order_id" => $session["id"],
				"parent_id" => NULL,
				"deleted_at" => NULL
			]
		]);

		$totalRegularQuantity = 0;
		$totalPackageQuantity = 0;
		$totalUniqueItems = 0;
		$packageCount = 0;

		foreach ($regularItems as $item) {
			// 2. Cek apakah item adalah package
			$isPackage = $this->M_Package->getOne([
				"product_id" => $item["product_id"],
				"deleted_at" => NULL
			]);

			if ($isPackage) {
				// 3. Hitung item dalam package
				$packageItems = $this->M_Order_Detail->getAll([
					"where" => [
						"order_id" => $session["id"],
						"parent_id" => $item["id"],
						"deleted_at" => NULL
					]
				]);

				$totalPackageQuantity += ($item["quantity"] * count($packageItems));
				$packageCount++;
			} else {
				// 4. Hitung item reguler dengan validasi stok
				$product = $this->MOC_Product->detail($item["product_id"]);
				if ($product && $product["stock"] >= $item["quantity"]) {
					$totalRegularQuantity += $item["quantity"];
				}
			}
			$totalUniqueItems++;
		}

		$totalQuantity = $totalRegularQuantity + $totalPackageQuantity;

		// 5. Format response data
		return [
			"metrics" => [
				"total_items" => $totalUniqueItems,
				"total_quantity" => $totalQuantity,
				"unique_items" => $totalUniqueItems
			],
			"breakdown" => [
				"regular_items" => [
					"count" => $totalUniqueItems - $packageCount,
					"quantity" => $totalRegularQuantity
				],
				"package_items" => [
					"count" => $packageCount,
					"quantity" => $totalPackageQuantity
				]
			]
		];
	}

	private function getEmptyCartData()
	{
		return [
			"metrics" => [
				"total_items" => 0,
				"total_quantity" => 0,
				"unique_items" => 0
			],
			"breakdown" => [
				"regular_items" => [
					"count" => 0,
					"quantity" => 0
				],
				"package_items" => [
					"count" => 0,
					"quantity" => 0
				]
			]
		];
	}

	private function sendErrorResponse($status, $code, $message, $data = null)
	{
		return $this->output
			->set_status_header($status)
			->set_output(json_encode([
				"success" => false,
				"code" => $code,
				"message" => $message,
				"data" => $data
			]));
	}

	public function add()
	{
		try {
			$this->output->set_content_type('application/json');

			$payload = json_decode($this->security->xss_clean($this->input->raw_input_stream));

			// Log payload untuk debugging
			log_message('debug', 'Payload untuk penambahan: ' . json_encode($payload));

			$this->db->trans_begin();

			try {
				$order = $this->M_Order->getOne([
					"id" => $payload->orderId,
					"deleted_at" => NULL,
					"status" => $this->M_Order::STATUS_RESERVED
				]);

				if (!$order) {
					throw new Exception("Invalid order session");
				}

				$result = null;
				switch ($payload->action) {
					case 2: // Regular Product
						$result = $this->handleAddRegularProduct($payload, $order);
						break;
					case 3: // Package Product
						$result = $this->handleAddPackageProduct($payload, $order);
						break;
					default:
						throw new Exception("Invalid action type");
				}

				if ($this->db->trans_status() === FALSE) {
					$this->db->trans_rollback();
					throw new Exception("Transaction failed");
				}

				$this->db->trans_commit();

				// Perbaikan: Tambahkan kode respons dan data
				return $this->sendSuccessResponse(
					"000",
					"Product added to cart successfully",
					$result
				);
			} catch (Exception $e) {
				$this->db->trans_rollback();
				log_message('error', 'Error in add transaction: ' . $e->getMessage());
				throw $e;
			}
		} catch (Exception $e) {
			log_message('error', 'Error in add method: ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"message" => $e->getMessage()
				]));
		}
	}

	private function handleAddRegularProduct($payload, $order)
	{
		$resultsData = [];

		foreach ($payload->data as $item) {
			$product = $this->MOC_Product->detail($item->product_id);

			if (!$product) {
				throw new Exception("Product not found: {$item->product_id}");
			}

			// Validasi stok total
			$totalRequestedQuantity = $item->quantity;

			// Cek item yang sudah ada di keranjang
			$existingItem = $this->M_Order_Detail->getOne([
				"order_id" => $order["id"],
				"product_id" => $item->product_id,
				"deleted_at" => NULL,
				"parent_id" => NULL
			]);

			// Jika item sudah ada di keranjang, gunakan quantity dari request
			// Jangan tambahkan ke quantity existing
			if ($existingItem) {
				// Langsung update dengan quantity yang dikirim
				$updatedItem = $this->M_Order_Detail->update(
					["id" => $existingItem["id"]],
					[
						"quantity" => $item->quantity, // Ganti total quantity
						"notes" => $item->notes ?? $existingItem["notes"],
						"updated_at" => date('Y-m-d H:i:s')
					]
				);

				$resultsData[] = $updatedItem;
			} else {
				// Jika item baru, insert dengan quantity dari request
				$newItem = $this->M_Order_Detail->insertOne([
					"order_id" => $order["id"],
					"product_id" => $item->product_id,
					"quantity" => $item->quantity,
					"notes" => $item->notes ?? null,
					"created_at" => date('Y-m-d H:i:s')
				]);

				$resultsData[] = $newItem;
			}

			// Validasi stok akhir
			if ($totalRequestedQuantity > $product['stock']) {
				throw new Exception("Total quantity exceeds stock for {$product['product_name']}");
			}
		}

		return $resultsData;
	}

	private function handleAddPackageProduct($payload, $order)
	{
		// Validate package
		$package = $this->M_Package->getOne([
			'product_id' => $payload->packageId
		]);

		if (!$package) {
			throw new Exception("Package not found");
		}

		// Prepare package data with explicit price handling
		$packageData = [
			'order_id' => $order['id'],
			'package_id' => $payload->packageId,
			'base_price' => $package['base_price'] ?? $package['product_price'],
			'products' => array_map(function ($product) {
				// Ensure price is properly passed through
				return [
					'product_id' => $product->productId,
					'quantity' => $product->quantity,
					'price' => $product->price, // Ensure this is the price shown in cart
					'notes' => $product->notes ?? null
				];
			}, $payload->products)
		];

		// Insert package with details - pass total price context
		$result = $this->M_Order_Detail->insertPackageOrder($packageData);
		if (!$result['success']) {
			throw new Exception($result['message']);
		}

		return true;
	}

	private function maintainSession($order)
	{
		$currentTime = new DateTime();
		$expiredAt = new DateTime($order['expire_at']);

		// Tambah 15 menit dari waktu terakhir
		$newExpiredAt = $currentTime->add(new DateInterval('PT15M'));

		$this->M_Order->qBUpdate($order['id'], [
			'expire_at' => $newExpiredAt->format('Y-m-d H:i:s'),
			'updated_at' => $currentTime->format('Y-m-d H:i:s')
		]);
	}

	public function removeCartItem()
	{
		try {
			$this->output->set_content_type('application/json');
			log_message('debug', '🛠️ [REMOVE ITEM] Proses penghapusan item dimulai');

			// Parse payload dengan validasi keamanan
			$payload = json_decode($this->security->xss_clean($this->input->raw_input_stream));
			log_message('debug', '📦 [REMOVE ITEM] Payload: ' . json_encode($payload));

			// Validasi payload yang lebih ketat
			if (!$this->validateRemovePayload($payload)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"message" => "Parameter tidak valid"
					]));
			}

			// Validasi sesi aktif
			$order = $this->M_Order->getOne([
				"outlet_id" => $payload->outletId,
				"table_id" => $payload->tableId,
				"brand" => $payload->brand,
				"deleted_at" => NULL
			]);

			if (!$order) {
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						"success" => false,
						"message" => "Sesi order tidak ditemukan"
					]));
			}

			// Store order ID for promo revalidation
			$orderId = $order['id'];
			$hasActivePromo = !empty($order['discount_code']);
			log_message('debug', '🔍 [REMOVE ITEM] Order ID: ' . $orderId . ', Has Active Promo: ' . ($hasActivePromo ? 'Yes' : 'No'));

			// Proses penghapusan item
			$this->db->trans_begin();

			if ($payload->type === 'package') {
				$result = $this->removePackageFromCart($order['id'], $payload->itemId);
			} else {
				$result = $this->removeRegularItemFromCart($order['id'], $payload->itemId);
			}

			if (!$result['success']) {
				$this->db->trans_rollback();
				return $this->output
					->set_status_header(400)
					->set_output(json_encode($result));
			}

			// PERBAIKAN UTAMA: Revalidasi promo setelah penghapusan item
			$promoStatus = null;
			if ($hasActivePromo) {
				log_message('debug', '🔄 [REMOVE ITEM] Revalidating promo after item removal');

				// Revalidasi promo dengan cara khusus untuk penghapusan item
				$promoStatus = $this->revalidatePromoAfterRemoval($orderId, $order['discount_code'], $payload);
				log_message('debug', '✅ [REMOVE ITEM] Promo revalidation result: ' . json_encode($promoStatus));
			}

			$this->db->trans_commit();

			// Calculate updated cart summary
			$updatedSummary = null;
			try {
				// Get updated order details
				$orderDetails = $this->M_Order_Detail->getAll([
					"where" => [
						"order_id" => $order["id"],
						"deleted_at" => NULL
					]
				]);

				// Process for summary calculation
				$processedDetails = [];
				foreach ($orderDetails as $detail) {
					$product = $this->MOC_Product->detail($detail["product_id"]);
					if (!$product) continue;

					$processedDetails[] = [
						"detail" => $detail,
						"product" => $product
					];
				}

				$updatedSummary = $this->calculateOrderSummary($processedDetails);
			} catch (Exception $summaryError) {
				log_message('error', 'Error calculating updated summary: ' . $summaryError->getMessage());
			}

			// Persiapkan respons
			$response = [
				'success' => true,
				'message' => 'Item berhasil dihapus',
				'data' => $result
			];

			// Tambahkan status promo jika relevan
			if ($promoStatus) {
				$response['promo_status'] = $promoStatus;
			}

			// Add updated summary if available
			if ($updatedSummary) {
				$response['summary'] = $updatedSummary;
			}

			// Force clients to refresh cart data
			$response['refresh_cart'] = true;

			log_message('debug', '✅ [REMOVE ITEM] Process completed successfully');

			return $this->output
				->set_status_header(200)
				->set_output(json_encode($response));
		} catch (Exception $e) {
			$this->db->trans_rollback();
			log_message('error', 'Remove Cart Item Error: ' . $e->getMessage());
			log_message('error', 'Stack trace: ' . $e->getTraceAsString());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"message" => "Kesalahan internal server",
					"details" => $e->getMessage()
				]));
		}
	}

	private function revalidatePromoAfterRemoval($orderId, $promoCode, $payload)
	{
		log_message('debug', '=== Start Promo Revalidation After Removal ===');

		// Load promo model jika belum
		if (!isset($this->M_Promo)) {
			$this->load->model('promo/M_Promo');
		}

		// Dapatkan informasi order
		$order = $this->M_Order->getOne(['id' => $orderId]);
		if (!$order) {
			log_message('error', 'Order not found: ' . $orderId);
			return ['valid' => false, 'message' => 'Order tidak ditemukan'];
		}

		// Hitung ulang total order
		$orderTotal = $this->M_Order_Detail->getTotalAmount($orderId);
		log_message('debug', 'New order total after removal: ' . $orderTotal);

		// Dapatkan detail cart terbaru (setelah penghapusan item)
		$cartDetails = $this->prepareCartDetails($orderId);
		log_message('debug', 'Cart details after removal: ' . count($cartDetails) . ' items');

		// Validasi promo dengan kondisi keranjang terbaru
		try {
			$validationResult = $this->M_Promo->validatePromo(
				$promoCode,
				$order['brand'],
				$orderTotal,
				$cartDetails,
				true // Calculate eligible total
			);
			log_message('debug', 'Promo validation result: ' . json_encode($validationResult));
		} catch (Exception $e) {
			log_message('error', 'Error validating promo: ' . $e->getMessage());
			return [
				'valid' => false,
				'message' => 'Error validating promo: ' . $e->getMessage()
			];
		}

		// Persiapkan hasil validasi
		$promoStatus = [
			'valid' => $validationResult['valid'] ?? false,
			'message' => $validationResult['message'] ?? 'Unknown validation error',
			'discount' => $validationResult['valid'] ? ($validationResult['discount_amount'] ?? 0) : 0,
			'promo_code' => $promoCode
		];

		// Jika promo tidak valid lagi, hapus dari order
		if (!$promoStatus['valid']) {
			log_message('debug', 'Promo is no longer valid after item removal - removing from order');

			// Update order untuk menghapus diskon
			$this->M_Order->update(['id' => $orderId], [
				'discount_code' => null,
				'discount_amount' => 0,
				'updated_at' => date('Y-m-d H:i:s')
			]);

			// Hapus item promo gratis jika ada (untuk promo bundling/BOGO)
			if (
				isset($validationResult['promo_type']) &&
				($validationResult['promo_type'] == 'bundling' || $validationResult['promo_type'] == 'bogo')
			) {
				// Mark promo items as deleted
				$this->db->where('order_id', $orderId)
					->where('is_promo_item', 1)
					->update('order_details', ['deleted_at' => date('Y-m-d H:i:s')]);
				log_message('debug', 'Removed free promo items from order');
			}

			log_message('debug', 'Successfully removed invalid promo from order');
		}
		// Jika promo masih valid tapi nilai diskon berubah, update nilai diskon
		else if ($validationResult['valid'] && $order['discount_amount'] != $validationResult['discount_amount']) {
			log_message('debug', 'Promo is still valid but discount amount changed - updating');
			log_message('debug', 'Old discount: ' . $order['discount_amount'] .
				', New discount: ' . $validationResult['discount_amount']);

			$this->M_Order->update(['id' => $orderId], [
				'discount_amount' => $validationResult['discount_amount'],
				'updated_at' => date('Y-m-d H:i:s')
			]);
		}

		log_message('debug', 'Final promo status: ' . json_encode($promoStatus));
		log_message('debug', '=== End Promo Revalidation After Removal ===');

		return $promoStatus;
	}

	private function validateRemovePayload($payload)
	{
		$requiredFields = [
			'outletId',
			'tableId',
			'brand',
			'itemId',
			'type'
		];

		foreach ($requiredFields as $field) {
			if (!isset($payload->$field) || empty($payload->$field)) {
				log_message('error', "Missing required field: $field");
				return false;
			}
		}

		$validTypes = ['regular', 'package'];
		if (!in_array($payload->type, $validTypes)) {
			log_message('error', "Invalid type: {$payload->type}");
			return false;
		}

		return true;
	}

	private function removeRegularItemFromCart($orderId, $itemId)
	{
		$result = $this->M_Order_Detail->update(
			['id' => $itemId, 'order_id' => $orderId],
			['deleted_at' => date('Y-m-d H:i:s')]
		);

		return [
			'success' => true,
			'message' => 'Item berhasil dihapus',
			'data' => $result
		];
	}

	private function removePackageFromCart($orderId, $packageId)
	{
		try {
			// Hapus package header
			$this->db->where('id', $packageId)
				->where('order_id', $orderId)
				->update('order_details', ['deleted_at' => date('Y-m-d H:i:s')]);

			// Hapus semua child items dari package
			$this->db->where('parent_id', $packageId)
				->where('order_id', $orderId)
				->update('order_details', ['deleted_at' => date('Y-m-d H:i:s')]);

			return [
				'success' => true,
				'message' => 'Paket berhasil dihapus'
			];
		} catch (Exception $e) {
			log_message('error', 'Remove Package Error: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => 'Gagal menghapus paket'
			];
		}
	}

	public function validatePromoCode()
	{
		// Set content type
		$this->output->set_content_type('application/json');

		try {
			// PERBAIKAN: Tambahkan log awal untuk tracking
			log_message('debug', '=== Start Promo Validation ===');

			// Ambil parameter dari input dengan penanganan yang lebih baik
			$rawPayload = $this->input->raw_input_stream;

			// PERBAIKAN: Tambahkan pengecekan payload kosong
			if (empty($rawPayload)) {
				log_message('error', 'Empty payload received in validatePromoCode');
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Empty request payload'
					]));
			}

			// PERBAIKAN: Gunakan json_decode dengan parameter assoc = true untuk konsistensi
			$payload = json_decode($rawPayload, true);

			// PERBAIKAN: Log raw input
			log_message('debug', 'Raw input: ' . $rawPayload);
			log_message('debug', 'Decoded payload: ' . json_encode($payload));

			// Validasi parameter yang diperlukan
			if (!isset($payload['promo_code']) || !isset($payload['order_total']) || !isset($payload['brand'])) {
				log_message('error', 'Missing required parameters');
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Missing required parameters: promo_code, order_total, brand'
					]));
			}

			// PERBAIKAN: Persiapkan informasi detail keranjang jika tersedia
			$cartProducts = [];
			$cartDetails = [];

			// Jika cart_products tersedia sebagai array ID
			if (isset($payload['cart_products']) && is_array($payload['cart_products'])) {
				$cartProducts = $payload['cart_products'];
			}

			// Jika cart_details tersedia dengan informasi harga dan quantity
			if (isset($payload['cart_details']) && is_array($payload['cart_details'])) {
				$cartDetails = $payload['cart_details'];

				// Extrak product IDs dari cart_details jika cartProducts masih kosong
				if (empty($cartProducts)) {
					foreach ($cartDetails as $item) {
						if (isset($item['product_id'])) {
							$cartProducts[] = $item['product_id'];
						}
					}
				}
			}

			log_message('debug', 'Cart products count: ' . count($cartProducts));
			log_message('debug', 'Cart details count: ' . count($cartDetails));

			// PERBAIKAN: Pastikan model M_Promo dimuat
			if (!isset($this->M_Promo) || !is_object($this->M_Promo)) {
				log_message('debug', 'Loading M_Promo model');
				$this->load->model('promo/M_Promo');
			}

			try {
				// PERBAIKAN: Panggil metode validatePromo() dengan informasi lengkap keranjang
				log_message('debug', 'Calling M_Promo->validatePromo()');

				// Jika kita memiliki detail keranjang, gunakan itu untuk validasi yang lebih akurat
				if (!empty($cartDetails)) {
					$result = $this->M_Promo->validatePromo(
						$payload['promo_code'],
						$payload['brand'],
						$payload['order_total'],
						$cartDetails,
						true // Calculate eligible total
					);
				} else {
					// Jika hanya memiliki product IDs
					$result = $this->M_Promo->validatePromo(
						$payload['promo_code'],
						$payload['brand'],
						$payload['order_total'],
						$cartProducts
					);
				}

				log_message('debug', 'Validation result: ' . json_encode($result));
			} catch (Exception $modelError) {
				log_message('error', 'Error in M_Promo->validatePromo(): ' . $modelError->getMessage());
				throw new Exception('Error validating promo: ' . $modelError->getMessage());
			}

			// PERBAIKAN: Standardisasi format respons
			if (!isset($result['success'])) {
				$result['success'] = $result['valid'] ?? false;
			}

			// PERBAIKAN: Pastikan respons memiliki semua field yang diperlukan
			$standardizedResponse = [
				'success' => $result['success'] ?? $result['valid'] ?? false,
				'valid' => $result['valid'] ?? $result['success'] ?? false,
				'message' => $result['message'] ?? '',
				'promo_code' => $payload['promo_code'],
				'promo_type' => $result['promo_type'] ?? ($result['promo']['promo_type'] ?? 'unknown'),
				'discount_amount' => $result['discount_amount'] ?? 0,
				'original_amount' => $result['original_amount'] ?? $payload['order_total'],
				'final_amount' => $result['final_amount'] ?? $payload['order_total'],
				'product_specific' => $result['product_specific'] ?? false,
				'promo_products' => $result['promo_products'] ?? [],
				'promo_categories' => $result['promo_categories'] ?? [],
				'eligible_amount' => $result['eligible_amount'] ?? $payload['order_total'],
				'eligible_products' => $result['eligible_products'] ?? []
			];

			// Tambahkan data promo jika ada
			if (isset($result['promo'])) {
				$standardizedResponse['promo'] = $result['promo'];
			} else if (isset($result['data']) && isset($result['data']['promo'])) {
				$standardizedResponse['promo'] = $result['data']['promo'];
			}

			// Tambahkan data lain jika ada
			if (isset($result['data'])) {
				$standardizedResponse['data'] = $result['data'];
			}

			log_message('debug', 'Standardized response: ' . json_encode($standardizedResponse));
			log_message('debug', '=== End Promo Validation ===');

			// PERBAIKAN: Set header cache control
			$this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
			$this->output->set_header('Pragma: no-cache');

			// Kembalikan hasil validasi
			return $this->output
				->set_status_header(200)
				->set_output(json_encode($standardizedResponse));
		} catch (Exception $e) {
			// Log error
			log_message('error', 'Error in validatePromoCode: ' . $e->getMessage());
			log_message('error', 'Stack trace: ' . $e->getTraceAsString());

			// PERBAIKAN: Set header cache control
			$this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
			$this->output->set_header('Pragma: no-cache');

			// Kembalikan respons error
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'valid' => false,
					'message' => 'Internal server error: ' . $e->getMessage(),
					'trace' => ENVIRONMENT === 'development' ? $e->getTraceAsString() : null
				]));
		}
	}

	public function getPromoSuggestions()
	{
		$this->output->set_content_type('application/json');

		try {
			// Parse input
			$input = json_decode($this->input->raw_input_stream, true);

			// Validasi input
			if (!isset($input['brand']) || !isset($input['order_total'])) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Missing required parameters: brand, order_total'
					]));
			}

			// Pastikan model promo dimuat
			if (!isset($this->M_Promo)) {
				$this->load->model('promo/M_Promo', 'M_Promo');
			}

			// Persiapkan cart_details jika ada
			$cartDetails = isset($input['cart_details']) ? $input['cart_details'] : [];

			// Dapatkan saran promo
			$suggestions = $this->M_Promo->getPromoSuggestions(
				$input['brand'],
				$input['order_total'],
				$cartDetails
			);

			// Kirim respons
			return $this->output
				->set_status_header(200)
				->set_output(json_encode([
					'success' => true,
					'data' => [
						'suggestions' => $suggestions
					]
				]));
		} catch (Exception $e) {
			log_message('error', 'Error getting promo suggestions: ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Error processing promo suggestions: ' . $e->getMessage()
				]));
		}
	}

	public function applyPromoToOrder()
	{
		// Set content type
		$this->output->set_content_type('application/json');

		try {
			// Nonaktifkan display errors untuk mencegah output HTML
			ini_set('display_errors', 0);

			log_message('debug', '=== Start Apply Promo To Order ===');
			$rawPayload = $this->input->raw_input_stream;
			$payload = json_decode($rawPayload, true);

			// Log payload untuk debugging
			log_message('debug', 'Apply promo payload: ' . $rawPayload);

			// Validasi parameter yang diperlukan
			if (!isset($payload['orderId']) || !isset($payload['promoCode']) || !isset($payload['brand'])) {
				log_message('error', 'Missing required parameters');
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Missing required parameters: orderId, promoCode, brand'
					]));
			}

			// Load model promo jika belum diload
			if (!isset($this->M_Promo)) {
				$this->load->model('promo/M_Promo');
			}

			// Tambahkan custom error handler
			set_error_handler(function ($errno, $errstr, $errfile, $errline) {
				// Log error tanpa menampilkan
				log_message('error', "PHP Error [$errno]: $errstr in $errfile on line $errline");

				// Hindari error undefined index
				if (strpos($errstr, 'Undefined index:') !== false) {
					return true; // Abaikan error
				}

				// Untuk error lain, lempar exception
				throw new Exception("PHP Error: $errstr", $errno);
			}, E_ALL & ~E_NOTICE & ~E_WARNING);

			try {
				// Proses validasi dan aplikasi promo
				$orderTotal = $this->M_Order_Detail->getTotalAmount($payload['orderId']);

				// Ambil detail cart
				$cartDetails = $this->prepareCartDetails($payload['orderId']);

				// Validasi promo
				$validationResult = $this->M_Promo->validatePromo(
					$payload['promoCode'],
					$payload['brand'],
					$orderTotal,
					$cartDetails,
					true
				);

				// Log validasi
				log_message('debug', 'Promo validation result: ' . json_encode($validationResult));

				// Jika validasi berhasil
				if ($validationResult['valid']) {
					// Terapkan promo ke order
					$applyResult = $this->M_Promo->applyPromoToOrder(
						$payload['orderId'],
						$payload['promoCode'],
						$payload['brand'],
						$orderTotal,
						$cartDetails
					);

					// Log hasil aplikasi promo
					log_message('debug', 'Apply promo result: ' . json_encode($applyResult));

					// Kembalikan response
					return $this->output
						->set_status_header(200)
						->set_output(json_encode([
							'success' => true,
							'message' => 'Promo applied successfully',
							'data' => $applyResult['data'] ?? []
						]));
				} else {
					// Validasi promo gagal
					return $this->output
						->set_status_header(422)
						->set_output(json_encode([
							'success' => false,
							'message' => $validationResult['message'] ?? 'Promo validation failed'
						]));
				}
			} catch (Exception $modelError) {
				log_message('error', 'Promo Model Error: ' . $modelError->getMessage());
				return $this->output
					->set_status_header(500)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Error processing promo: ' . $modelError->getMessage()
					]));
			}
		} catch (Exception $mainError) {
			log_message('error', 'Main Error in applyPromoToOrder: ' . $mainError->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Internal server error: ' . $mainError->getMessage()
				]));
		} finally {
			// Restore error handler
			restore_error_handler();
		}
	}

	// Metode baru untuk mempersiapkan detail cart
	private function prepareCartDetails($orderId)
	{
		$cartDetails = [];

		// Ambil detail order
		$orderDetails = $this->M_Order_Detail->getAll([
			"where" => [
				"order_id" => $orderId,
				"deleted_at" => NULL,
				"parent_id" => NULL
			]
		]);

		foreach ($orderDetails as $detail) {
			// Ambil detail produk untuk mendapatkan category_id
			$product = $this->db
				->select('cat_id')
				->from('data_product')
				->where('product_id', $detail['product_id'])
				->get()
				->row_array();

			$cartDetails[] = [
				'product_id' => $detail['product_id'],
				'price' => floatval($detail['unit_price'] ?? $detail['price'] ?? 0),
				'quantity' => intval($detail['quantity'] ?? 1),
				'subtotal' => floatval($detail['subtotal'] ?? 0),
				'cat_id' => $product ? $product['cat_id'] : null
			];
		}

		return $cartDetails;
	}
	public function doneOrder()
	{
		try {
			// Set header untuk logging yang lebih detail
			$this->output->set_content_type('application/json');
			log_message('DEBUG', '===== DONE ORDER START =====');

			// Validasi request AJAX
			if (!$this->input->is_ajax_request()) {
				log_message('ERROR', 'Non-AJAX request detected');
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"code" => "001",
						"message" => "Invalid request method"
					]));
			}

			// Parse payload dengan logging tambahan
			$rawPayload = $this->input->raw_input_stream;
			log_message('DEBUG', 'Raw Payload: ' . $rawPayload);
			$payload = json_decode($this->security->xss_clean($rawPayload));

			// Logging payload
			log_message('DEBUG', 'Parsed Payload: ' . json_encode($payload));

			// Validasi payload
			if (!$this->validateDoneOrderPayload($payload)) {
				log_message('ERROR', 'Invalid payload format');
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"code" => "001",
						"message" => "Invalid request format"
					]));
			}

			$params = [
				"outlet_id" => $payload->outletId,
				"table_id" => $payload->tableId,
				"brand" => $payload->brand
			];

			// Logging params
			log_message('DEBUG', 'Order Params: ' . json_encode($params));

			// PERBAIKAN: Memulai transaksi tanpa mengubah isolation level
			$this->db->trans_begin();
			try {
				// Ambil data sesi aktif
				$query = $this->db->select('*')
					->from('orders')
					->where('outlet_id', $params["outlet_id"])
					->where('table_id', $params["table_id"])
					->where('brand', $params["brand"])
					->where('deleted_at IS NULL')
					->get();
				$activeSession = $query->row_array();

				if (!$activeSession) {
					$this->db->trans_rollback();
					return $this->output
						->set_status_header(404)
						->set_output(json_encode([
							"success" => false,
							"code" => "002",
							"message" => "Order tidak ditemukan"
						]));
				}

				// PERBAIKAN: Periksa optimistic concurrency dengan modified_at timestamp
				if (isset($payload->lastModified) && $activeSession['updated_at'] !== $payload->lastModified) {
					$this->db->trans_rollback();
					return $this->output
						->set_status_header(409) // Conflict
						->set_output(json_encode([
							"success" => false,
							"code" => "409",
							"message" => "Order telah diubah oleh pengguna lain. Silakan refresh keranjang Anda."
						]));
				}

				// PERBAIKAN: Validasi status sesi dengan lebih ketat
				$currentStatus = $activeSession["status"] ?? '';
				if ($currentStatus == $this->M_Order::STATUS_ORDERED) {
					$this->db->trans_rollback();
					return $this->output
						->set_status_header(400)
						->set_output(json_encode([
							"success" => false,
							"code" => "010",
							"message" => "Order sudah diproses sebelumnya"
						]));
				}

				// Proses order details
				$orderDetails = $this->processOrderDetails($activeSession["id"]);
				log_message('DEBUG', 'Order Details: ' . json_encode($orderDetails));

				// Validasi stok
				$stockValidation = $this->validateAndUpdateStock($orderDetails);
				if (!$stockValidation["success"]) {
					log_message('ERROR', 'Stock validation failed: ' . json_encode($stockValidation));
					$this->db->trans_rollback();
					return $this->output
						->set_status_header(422)
						->set_output(json_encode($stockValidation));
				}

				// Hitung ringkasan order
				$orderSummary = $this->calculateOrderSummary($orderDetails);
				log_message('DEBUG', 'Order Summary: ' . json_encode($orderSummary));

				// PERBAIKAN: Cek dan proses promo jika ada
				$discountAmount = 0;
				$promoCode = null;

				// Cek apakah ada promo yang perlu diproses
				if (!empty($activeSession['discount_code'])) {
					$promoCode = $activeSession['discount_code'];
					$discountAmount = !empty($activeSession['discount_amount']) ? $activeSession['discount_amount'] : 0;
					log_message('DEBUG', "Found promo code: $promoCode with discount: $discountAmount");

					// Cek apakah promo perlu diupdate usage-nya
					if (!empty($promoCode) && $discountAmount > 0) {
						// Load promo model
						$this->load->model('promo/M_Promo', 'M_Promo');

						// Cek promo yang digunakan
						$promo = $this->M_Promo->getPromoByCode($promoCode);
						if ($promo) {
							log_message('DEBUG', 'Found promo: ' . json_encode($promo));

							// PERBAIKAN UTAMA: Record penggunaan promo
							$promoUsageResult = $this->M_Promo->recordPromoUsage(
								$promo['promo_id'],
								$activeSession["id"],
								$discountAmount
							);
							log_message('DEBUG', 'Promo usage record result: ' . json_encode($promoUsageResult));
						} else {
							log_message('WARNING', "Promo code $promoCode not found");
						}
					}
				} else if (isset($payload->promo) && !empty($payload->promo->code)) {
					// Promo dari frontend yang belum diapply
					log_message('DEBUG', 'Processing promo from payload: ' . json_encode($payload->promo));

					// Load promo model
					$this->load->model('promo/M_Promo', 'M_Promo');

					// Apply promo to order
					$promoResult = $this->M_Promo->applyPromoToOrder(
						$activeSession["id"],
						$payload->promo->code,
						$payload->brand,
						$orderSummary["total_amount"]
					);
					log_message('DEBUG', 'Apply promo result: ' . json_encode($promoResult));

					if ($promoResult['success']) {
						$discountAmount = $promoResult['discount_amount'];
						$promoCode = $payload->promo->code;

						// Update order summary dengan diskon
						$orderSummary["discount_amount"] = $discountAmount;

						// PERBAIKAN: Pastikan diskon tidak membuat grand_total menjadi negatif
						$discountedSubtotal = $orderSummary["total_amount"] - $discountAmount;
						if ($discountedSubtotal < 0) $discountedSubtotal = 0;

						// Hitung ulang tax berdasarkan subtotal setelah diskon
						$orderSummary["tax"] = $discountedSubtotal * 0.1;
						$orderSummary["grand_total"] = $discountedSubtotal + $orderSummary["tax"];
					}
				}

				// Perbarui status order dengan total yang benar
				$currentTime = date('Y-m-d H:i:s');
				$updateData = [
					"status" => $this->M_Order::STATUS_ORDERED,
					"updated_at" => $currentTime,
					"total_amount" => $orderSummary["total_amount"],
					"total_items" => $orderSummary["total_items"],
					"discount_amount" => $discountAmount,
					"discount_code" => $promoCode
				];
				log_message('DEBUG', 'Update Order Data: ' . json_encode($updateData));

				$this->M_Order->qBUpdate($activeSession["id"], $updateData);

				// TAMBAHAN: Catat riwayat status order menjadi ORDERED
				$this->load->model('order/M_Order_History', 'M_Order_History');
				$historyData = [
					'order_id' => $activeSession["id"],
					'status' => $this->M_Order::STATUS_ORDERED,
					'created_at' => $currentTime,
					'updated_by' => 1
				];
				$this->M_Order_History->insertOrderHistory($historyData);

				// Generate nomor struk
				$receiptNumber = $this->generateReceiptNumber($activeSession);

				// PERBAIKAN: Ambil semua detail item untuk receipt dengan query biasa
				$orderItems = $this->M_Order_Detail->getAll([
					"where" => [
						"order_id" => $activeSession["id"],
						"deleted_at" => NULL
					]
				]);

				// Format items untuk kemudahan rendering di frontend
				$formattedItems = [];
				foreach ($orderItems as $item) {
					// Normalisasi data item
					$itemData = [
						'id' => $item['id'],
						'product_id' => $item['product_id'],
						'product_name' => $item['product_name'] ?? 'Unknown Product',
						'quantity' => intval($item['quantity']),
						'unit_price' => floatval($item['unit_price'] ?? $item['price'] ?? 0),
						'subtotal' => floatval($item['subtotal'] ?? ($item['unit_price'] * $item['quantity'])),
						'notes' => $item['notes'] ?? '',
						'parent_id' => $item['parent_id'] ?? null,
						'is_package' => !empty($item['package_id']),
						'package_id' => $item['package_id'] ?? null,
						'is_promo_item' => $item['is_promo_item'] ?? 0,
						'promo_type' => $item['promo_type'] ?? null
					];
					$formattedItems[] = $itemData;
				}

				// Tambahkan detail items ke summary
				$orderSummary['items'] = $formattedItems;

				// Simpan ringkasan order yang lengkap
				$this->storeOrderSummary($activeSession["id"], $orderSummary, $receiptNumber);

				// PERBAIKAN: Periksa adanya tabel notifikasi dan buat notifikasi pesanan
				$tableExists = $this->db
					->query("SHOW TABLES LIKE 'order_notifications'")
					->num_rows() > 0;
				if ($tableExists) {
					// Persiapkan data notifikasi
					$notificationData = [
						'order_id' => $activeSession["id"],
						'outlet_id' => $activeSession["outlet_id"],
						'table_id' => $activeSession["table_id"],
						'brand' => $activeSession["brand"],
						'customer_name' => $activeSession["name"],
						'total_amount' => $orderSummary["total_amount"],
						'items_count' => $orderSummary["total_items"],
						'status' => 'new',
						'created_at' => $currentTime
					];

					// Gunakan metode insert biasa
					$this->db->insert('order_notifications', $notificationData);
					if ($this->db->affected_rows() > 0) {
						log_message('debug', 'Created order notification for cashier: ' . json_encode($notificationData));
					} else {
						log_message('error', 'Failed to insert order notification: ' . $this->db->error()['message']);
					}
				} else {
					log_message('warning', 'order_notifications table does not exist, notification not created');
				}

				// Kirim data penjualan ke API
				$apiResponse = $this->sendSalesData($activeSession["id"]);
				log_message('info', 'Sales data sent for order: ' . $activeSession["id"] . ' - Result: ' . ($apiResponse['success'] ? 'Success' : 'Failed'));

				$this->db->trans_commit();

				// Periksa apakah ada promo yang digunakan
				$promoInfo = null;
				if (!empty($discountAmount) && !empty($promoCode)) {
					$promoInfo = [
						'code' => $promoCode,
						'amount' => floatval($discountAmount),
						'message' => 'Discount applied'
					];
				}

				$response = [
					"success" => true,
					"code" => "000",
					"message" => "Order berhasil diproses",
					"data" => [
						"order_id" => $activeSession["id"],
						"receipt_number" => $receiptNumber,
						"summary" => [
							"subtotal" => $orderSummary["total_amount"],
							"discount" => floatval($orderSummary["discount_amount"] ?? $discountAmount),  // Diskon reguler
							"bundle_bogo_discount" => floatval($orderSummary["bundle_bogo_discount"] ?? 0), // Nilai produk gratis (informational)
							"tax" => $orderSummary["tax"],
							"total" => $orderSummary["grand_total"],      // Total akhir sudah dihitung dengan benar
							"items_count" => $orderSummary["total_items"]
						],
						"promo" => $promoInfo,
						"items" => $formattedItems,
						"customer" => [
							"name" => $activeSession["name"],
							"table" => $activeSession["table_id"]
						],
						"timing" => [
							"order_time" => $currentTime,
							"session_duration" => $this->calculateSessionDuration($activeSession)
						]
					]
				];

				// Tambahkan informasi respons API ke dalam respons
				if (isset($apiResponse)) {
					$response["data"]["api_sale"] = [
						"success" => $apiResponse["success"],
						"message" => $apiResponse["message"]
					];
					if (isset($apiResponse["data"])) {
						$response["data"]["api_sale"]["data"] = $apiResponse["data"];
					}
				}

				log_message('DEBUG', 'Final Response: ' . json_encode($response));

				// Kirim notifikasi ke dapur
				$this->sendKitchenNotification($activeSession["id"], $orderDetails);

				return $this->output
					->set_status_header(200)
					->set_output(json_encode($response));
			} catch (Exception $e) {
				// Logging detail error
				log_message('ERROR', 'Order Process Error: ' . $e->getMessage());
				log_message('ERROR', 'Error Trace: ' . $e->getTraceAsString());
				$this->db->trans_rollback();
				return $this->output
					->set_status_header(500)
					->set_output(json_encode([
						"success" => false,
						"code" => "999",
						"message" => "Kesalahan internal: " . $e->getMessage(),
						"trace" => $e->getTraceAsString()
					]));
			}
		} catch (Exception $e) {
			// Logging error umum
			log_message('ERROR', 'General Error in doneOrder: ' . $e->getMessage());
			log_message('ERROR', 'Error Trace: ' . $e->getTraceAsString());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"code" => "999",
					"message" => "Kesalahan sistem: " . $e->getMessage(),
					"trace" => $e->getTraceAsString()
				]));
		}
	}

	private function processOrderDetails($orderId)
	{
		try {
			// Validasi input
			if (!$orderId) {
				log_message('error', 'Invalid order ID in processOrderDetails');
				return [];
			}

			// PERBAIKAN: Ganti query dengan menggunakan model dan batasi join agar tidak bermasalah dengan locking
			$orderDetails = $this->db
				->select('od.*, dp.product_name, dp.product_pict, dp.stock')
				->from('order_details od')
				->join('data_product dp', 'od.product_id = dp.product_id', 'left')
				->where('od.order_id', $orderId)
				->where('od.deleted_at IS NULL')
				->get()
				->result_array();

			log_message('DEBUG', 'Order Details Retrieved: ' . json_encode($orderDetails));

			// Jika tidak ada order details
			if (empty($orderDetails)) {
				log_message('warn', 'No order details found for order ID: ' . $orderId);
				return [];
			}

			$processedDetails = [];
			foreach ($orderDetails as $detail) {
				// Ambil detail produk
				$product = $this->MOC_Product->detail($detail["product_id"]);

				if (!$product) {
					log_message('warn', 'Product not found for ID: ' . $detail["product_id"]);
					continue;
				}

				$detailData = [
					"detail" => $detail,
					"product" => $product
				];

				// Cek apakah item adalah bagian dari paket
				if (!empty($detail["parent_id"])) {
					$parent = $this->M_Order_Detail->getOne([
						'id' => $detail["parent_id"]
					]);

					if ($parent) {
						$detailData["parent"] = $parent;

						$package = $this->M_Package->getOne([
							"product_id" => $parent["product_id"]
						]);

						if ($package) {
							$detailData["package"] = $package;
						}
					}
				}

				$processedDetails[] = $detailData;
			}

			return $processedDetails;
		} catch (Exception $e) {
			log_message('error', 'Error in processOrderDetails: ' . $e->getMessage());
			log_message('error', 'Trace: ' . $e->getTraceAsString());
			return [];
		}
	}

	private function validateAndUpdateStock($orderDetails)
	{
		$stockUpdates = [];
		$insufficientStock = [];

		// Cek stok semua produk terlebih dahulu
		foreach ($orderDetails as $detail) {
			$product_id = $detail["product"]["product_id"];
			$quantity = $detail["detail"]["quantity"];
			$currentStock = intval($detail["product"]["stock"]);

			// Jika stok tidak cukup, tambahkan ke daftar produk dengan stok tidak cukup
			if ($currentStock < $quantity) {
				$insufficientStock[] = [
					'product_name' => $detail["product"]["product_name"],
					'current_stock' => $currentStock,
					'required' => $quantity
				];
			}
		}

		// Jika ada produk dengan stok tidak cukup, kembalikan pesan error
		if (!empty($insufficientStock)) {
			$errorMessages = [];
			foreach ($insufficientStock as $item) {
				$errorMessages[] = "Produk {$item['product_name']} hanya tersedia {$item['current_stock']} (dibutuhkan {$item['required']})";
			}

			return [
				"success" => false,
				"code" => "004",
				"message" => "Stok tidak mencukupi: " . implode(", ", $errorMessages)
			];
		}

		// Jika semua stok mencukupi, lakukan update stok
		foreach ($orderDetails as $detail) {
			$product_id = $detail["product"]["product_id"];
			$quantity = $detail["detail"]["quantity"];
			$currentStock = intval($detail["product"]["stock"]);
			$newStock = $currentStock - $quantity;

			$stockUpdates[] = [
				"product_id" => $product_id,
				"stock" => $newStock
			];
		}

		// Update stok semua produk
		$this->MOC_Product->updateAll('product_id', $stockUpdates);

		return ["success" => true];
	}

	private function generateReceiptNumber($order)
	{
		$prefix = strtoupper(substr($order["brand"], 0, 3));
		$date = date('Ymd');
		$random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
		return $prefix . $date . $random;
	}

	private function calculateSessionDuration($order)
	{
		$start = new DateTime($order["created_at"]);
		$end = new DateTime();
		$duration = $end->diff($start);

		return [
			"hours" => $duration->h,
			"minutes" => $duration->i,
			"seconds" => $duration->s
		];
	}

	private function storeOrderSummary($orderId, $summary, $receiptNumber)
	{
		log_message('debug', 'Storing order summary. Order ID: ' . $orderId);

		try {
			// PERBAIKAN: Pastikan tabel order_summaries ada dan gunakan transaction yang sudah berjalan
			$tableExists = $this->db
				->query("SHOW TABLES LIKE 'order_summaries'")
				->num_rows() > 0;

			if (!$tableExists) {
				// Buat tabel jika belum ada
				$this->db->query("
					CREATE TABLE `order_summaries` (
						`id` int(11) NOT NULL AUTO_INCREMENT,
						`order_id` int(11) NOT NULL,
						`receipt_number` varchar(50) NOT NULL,
						`summary_data` text NOT NULL,
						`created_at` datetime NOT NULL,
						PRIMARY KEY (`id`),
						KEY `order_id` (`order_id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
				");

				log_message('debug', 'Created order_summaries table');
			}

			// PERBAIKAN: Ambil data diskon dari order
			$order = $this->M_Order->getOne(['id' => $orderId]);

			// PERBAIKAN: Pastikan data promo tersimpan dengan benar
			if ($order && !empty($order['discount_code'])) {
				if (!isset($this->M_Promo)) {
					$this->load->model('promo/M_Promo');
				}
				$promo = $this->M_Promo->getPromoByCode($order['discount_code']);

				// PERBAIKAN UTAMA: Pastikan discount_amount dan bundle_bogo_discount diatur dengan benar
				if ($promo) {
					if ($promo['promo_type'] === 'bundling' || $promo['promo_type'] === 'bogo') {
						// Jika promo bundling/BOGO, buat discount_amount = 0
						// dan simpan nilai di bundle_bogo_discount
						if (!isset($summary['bundle_bogo_discount']) || $summary['bundle_bogo_discount'] == 0) {
							$summary['bundle_bogo_discount'] = floatval($order['discount_amount'] ?? 0);
						}

						// PERBAIKAN KRITIS: Pastikan discount_amount tidak dikurangi dari subtotal
						$summary['discount_amount'] = 0;

						log_message('debug', 'Stored bundling/BOGO promo with bundle_bogo_discount: ' .
							$summary['bundle_bogo_discount'] . ' and discount_amount: ' .
							$summary['discount_amount']);
					} else {
						// Regular discount (percentage/nominal)
						$summary['discount_amount'] = floatval($order['discount_amount'] ?? 0);
						log_message('debug', 'Stored regular discount with amount: ' . $summary['discount_amount']);
					}

					// PERBAIKAN: Tambahkan informasi promo ke summary
					$summary['promo'] = [
						'code' => $order['discount_code'],
						'type' => $promo['promo_type'],
						'value' => $promo['promo_value']
					];
				}
			}

			// PERBAIKAN: Hitung ulang grand_total untuk memastikan konsistensi
			$discounted_total = max(0, $summary['total_amount'] - $summary['discount_amount']);
			$summary['tax'] = $discounted_total * 0.1; // 10% tax
			$summary['grand_total'] = $discounted_total + $summary['tax'];

			log_message('debug', 'Final summary for storage: ' . json_encode([
				'total_amount' => $summary['total_amount'],
				'discount_amount' => $summary['discount_amount'],
				'bundle_bogo_discount' => $summary['bundle_bogo_discount'],
				'tax' => $summary['tax'],
				'grand_total' => $summary['grand_total']
			]));

			// Format data sebagai JSON
			$summaryData = json_encode($summary);

			// Simpan ke database
			$result = $this->db->insert('order_summaries', [
				'order_id' => $orderId,
				'receipt_number' => $receiptNumber,
				'summary_data' => $summaryData,
				'created_at' => date('Y-m-d H:i:s')
			]);

			if (!$result) {
				log_message('error', 'Error storing order summary: ' . $this->db->error()['message']);
				return false;
			}

			log_message('debug', 'Order summary stored successfully');
			return true;
		} catch (Exception $e) {
			log_message('error', 'Error storing order summary: ' . $e->getMessage());
			return false;
		}
	}

	private function validateDoneOrderPayload($payload)
	{
		$requiredFields = ['outletId', 'tableId', 'brand'];
		foreach ($requiredFields as $field) {
			if (!isset($payload->$field)) {
				return false;
			}
		}
		return true;
	}

	private function sendKitchenNotification($orderId, $orderDetails)
	{
		// Notification system
		log_message('info', 'New order notification sent to Kasir: Order #' . $orderId);
	}

	private function sendSuccessResponse($code, $message, $data = null)
	{
		return $this->output
			->set_status_header(200)
			->set_output(json_encode([
				"success" => true,
				"code" => $code,
				"message" => $message,
				"data" => $data
			]));
	}

	public function getReceipt()
	{
		try {
			$this->output->set_content_type('application/json');

			// Validasi parameter dengan logging yang lebih lengkap
			$sessionId = $this->input->get('sessionId');
			$outletId = $this->input->get('outletId');
			$tableId = $this->input->get('tableId');
			$brand = $this->input->get('brand');

			// Log detail parameter untuk debugging
			log_message('debug', 'Get Receipt Request - Parameters: ' . json_encode([
				'sessionId' => $sessionId,
				'outletId' => $outletId,
				'tableId' => $tableId,
				'brand' => $brand
			]));

			// PERBAIKAN: Cek jika sessionId tidak ada, tapi tableId, outletId, dan brand ada
			if (empty($sessionId) && !empty($tableId) && !empty($outletId) && !empty($brand)) {
				// Cari sesi berdasarkan parameter lain dengan scope yang diperluas
				$session = $this->M_Order->getOne([
					'outlet_id' => $outletId,
					'table_id' => $tableId,
					'brand' => $brand,
					'deleted_at' => NULL
				]);

				if ($session) {
					$sessionId = $session['id'];
					log_message('debug', 'Found session by table parameters: ' . $sessionId);
				}
			}

			// Validasi parameter yang lebih ketat
			if (empty($sessionId) || empty($outletId) || empty($tableId) || empty($brand)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Missing required parameters',
						'parameters' => [
							'sessionId' => $sessionId,
							'outletId' => $outletId,
							'tableId' => $tableId,
							'brand' => $brand
						]
					]));
			}

			$orderSession = $this->M_Order->getOne([
				'id' => $sessionId,
				'outlet_id' => $outletId,
				'table_id' => $tableId,
				'brand' => $brand,
				'status' => $this->M_Order::STATUS_ORDERED,
				'deleted_at' => NULL
			]);

			// Jika tidak ditemukan, coba cek untuk status RESERVED atau EXPIRED, etc.
			if (!$orderSession) {
				log_message('debug', 'Session not found with ORDER status, checking other statuses');

				$anySession = $this->M_Order->getOne([
					'id' => $sessionId,
					'outlet_id' => $outletId,
					'table_id' => $tableId,
					'brand' => $brand,
					'deleted_at' => NULL
				]);

				if ($anySession) {
					log_message('debug', 'Found session with status: ' . ($anySession['status'] ?? 'unknown'));
					$orderSession = $anySession;
				} else {
					log_message('debug', 'No session found with any status');
				}
			}

			// Jika tidak ditemukan di database order, coba cari di tabel receipt/order_summaries
			if (!$orderSession) {
				log_message('debug', 'No session found in orders table, checking order_summaries');

				// PERBAIKAN: Tambahan cek di tabel order_summaries
				$receiptData = $this->db
					->select('*')
					->from('order_summaries')
					->where('order_id', $sessionId)
					->order_by('created_at', 'DESC')
					->limit(1)
					->get()
					->row_array();

				if ($receiptData) {
					log_message('debug', 'Found summary data in order_summaries table');

					// Parse summary data
					$summaryData = json_decode($receiptData['summary_data'], true);

					// Return data from summary table
					return $this->output
						->set_status_header(200)
						->set_output(json_encode([
							'success' => true,
							'data' => [
								'sessionId' => $sessionId,
								'receiptNumber' => $receiptData['receipt_number'],
								'customerName' => $summaryData['customer']['name'] ?? '-',
								'tableId' => $tableId,
								'orderTime' => $summaryData['timing']['order_time'] ?? date('Y-m-d H:i:s'),
								'items' => $summaryData['items'] ?? [],
								'summary' => [
									'subtotal' => $summaryData['subtotal'] ?? 0,
									'tax' => $summaryData['tax'] ?? 0,
									'total' => $summaryData['total'] ?? 0
								]
							]
						]));
				}

				log_message('debug', 'No receipt data found in order_summaries, checking other tables...');
			}

			if (!$orderSession && !isset($receiptData)) {
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Receipt data not found',
						'sessionId' => $sessionId
					]));
			}

			if ($orderSession) {
				// Get order details dengan join ke product data
				$orderItems = $this->db
					->select('od.*, od.unit_price as price, dp.product_name, dp.product_pict')
					->from('order_details od')
					->join('data_product dp', 'od.product_id = dp.product_id', 'left')
					->where('od.order_id', $sessionId)
					->where('od.deleted_at IS NULL')
					->get()
					->result_array();

				// Hitung ringkasan order jika perlu
				$summary = [
					'subtotal' => 0,
					'tax' => 0,
					'total' => 0
				];

				// Hitung subtotal dari item
				foreach ($orderItems as $item) {
					if (empty($item['parent_id'])) {
						$price = floatval($item['unit_price'] ?? $item['price'] ?? 0);
						$quantity = intval($item['quantity'] ?? 1);
						$summary['subtotal'] += $price * $quantity;
					}
				}

				// Hitung pajak dan total
				$summary['tax'] = $summary['subtotal'] * 0.1; // 10% tax
				$summary['total'] = $summary['subtotal'] + $summary['tax'];

				// Generate receipt number jika tidak ada
				$receiptNumber = '';

				// Cek jika ada di order_summaries
				$receiptData = $this->db
					->select('*')
					->from('order_summaries')
					->where('order_id', $sessionId)
					->order_by('created_at', 'DESC')
					->limit(1)
					->get()
					->row_array();

				if ($receiptData) {
					$receiptNumber = $receiptData['receipt_number'];
				} else {
					// Generate receipt number if not found
					$prefix = strtoupper(substr($orderSession['brand'], 0, 3));
					$date = date('Ymd');
					$random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
					$receiptNumber = $prefix . $date . $random;
				}

				// Prepare final response
				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						'success' => true,
						'data' => [
							'sessionId' => $sessionId,
							'receiptNumber' => $receiptNumber,
							'customerName' => $orderSession['name'] ?? '-',
							'tableId' => $orderSession['table_id'] ?? $tableId,
							'orderTime' => $orderSession['created_at'] ?? date('Y-m-d H:i:s'),
							'items' => $orderItems,
							'summary' => $summary
						]
					]));
			}

			// Fallback error
			return $this->output
				->set_status_header(404)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Failed to retrieve receipt data',
					'sessionId' => $sessionId
				]));
		} catch (Exception $e) {
			log_message('error', 'Error in getReceipt: ' . $e->getMessage());
			log_message('error', 'Trace: ' . $e->getTraceAsString());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Internal server error',
					'error_details' => $e->getMessage()
				]));
		}
	}

	/**
	 * End current session to create new order
	 */
	public function endSession()
	{
		try {
			$this->output->set_content_type('application/json');

			// Log awal
			log_message('debug', '⭐ [END SESSION] Process started');

			// Get payload dengan detail logging
			$raw_payload = $this->input->raw_input_stream;
			log_message('debug', '📦 [END SESSION] Raw payload: ' . $raw_payload);

			$payload = json_decode($raw_payload, true);
			log_message('debug', '🔍 [END SESSION] Parsed payload: ' . json_encode($payload));

			// Validate payload with more robust checking
			if (empty($payload['outletId']) || empty($payload['tableId']) || empty($payload['brand'])) {
				log_message('error', '❌ [END SESSION] Missing required parameters: ' . json_encode($payload));
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						'success' => false,
						'message' => 'Missing required parameters',
						'details' => 'outletId, tableId, and brand are required',
						'received' => $payload
					]));
			}

			// Begin transaction untuk konsistensi data
			log_message('debug', '🔄 [END SESSION] Starting database transaction');
			$this->db->trans_begin();

			try {
				// First, find active session with any status
				$session = $this->M_Order->getOne([
					'outlet_id' => $payload['outletId'],
					'table_id' => $payload['tableId'],
					'brand' => $payload['brand'],
					'deleted_at' => NULL
				]);

				log_message('debug', '🔍 [END SESSION] Session found: ' . ($session ? 'Yes (ID: ' . $session['id'] . ')' : 'No'));

				if (!$session) {
					log_message('info', '⚠️ [END SESSION] No active session found to end');
					$this->db->trans_commit();
					return $this->output
						->set_status_header(200)
						->set_output(json_encode([
							'success' => true,
							'message' => 'No active session found to end',
							'data' => [
								'action' => 'none',
								'reason' => 'no_session'
							]
						]));
				}

				$sessionId = $session['id'];
				log_message('debug', '📝 [END SESSION] Working with session ID: ' . $sessionId);

				// PERBAIKAN KRITIS: Jika forceEnd = true, gunakan status CANCELLED (3)
				// untuk memastikan session tidak bisa direstore
				$forceEnd = isset($payload['forceEnd']) && $payload['forceEnd'] === true;
				$newStatus = $forceEnd ? 5 : // 5 = CANCELLED
					($session['status'] == $this->M_Order::STATUS_ORDERED ?
						$this->M_Order::STATUS_ORDERED : 2); // 2 = EXPIRED

				log_message('debug', '🔄 [END SESSION] Setting new status: ' . $newStatus .
					($forceEnd ? ' (Force end requested)' : ''));

				// PERBAIKAN UTAMA: Gunakan soft delete dengan timestamp saat ini
				// Soft delete lebih aman daripada hard delete
				$updateData = [
					'status' => $newStatus,
					'updated_at' => date('Y-m-d H:i:s'),
					'deleted_at' => date('Y-m-d H:i:s') // CRITICAL: Soft delete session
				];

				log_message('debug', '📝 [END SESSION] Update data: ' . json_encode($updateData));

				// Update session
				$this->db->where('id', $sessionId);
				$updateResult = $this->db->update('orders', $updateData);

				// Log update result
				log_message('debug', '📊 [END SESSION] Update result: ' . ($updateResult ? 'Success' : 'Failed') .
					' - Affected rows: ' . $this->db->affected_rows());

				if (!$updateResult) {
					$error = $this->db->error();
					log_message('error', '❌ [END SESSION] Database error: ' . json_encode($error));
					throw new Exception('Failed to update session: ' . $error['message']);
				}

				// PERBAIKAN: Juga hapus semua item di cart dengan soft delete
				log_message('debug', '🔄 [END SESSION] Soft deleting cart items');
				$this->db->where('order_id', $sessionId);
				$this->db->update('order_details', ['deleted_at' => date('Y-m-d H:i:s')]);

				log_message('debug', '📊 [END SESSION] Cart items soft deleted: ' . $this->db->affected_rows() . ' rows');

				// Clear notifications for this session
				log_message('debug', '🔄 [END SESSION] Clearing notifications');
				$this->clearSessionNotifications($payload['outletId'], $payload['tableId'], $payload['brand']);

				// Commit transaction
				log_message('debug', '✅ [END SESSION] Committing transaction');
				$this->db->trans_commit();

				// Final log
				log_message('info', '✅ [END SESSION] Successfully ended session ID: ' . $sessionId);

				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						'success' => true,
						'message' => 'Session ended successfully',
						'data' => [
							'session_id' => $sessionId,
							'new_status' => $newStatus,
							'force_end' => $forceEnd,
							'timestamp' => time()
						]
					]));
			} catch (Exception $e) {
				// Rollback on error
				log_message('error', '❌ [END SESSION] Transaction error: ' . $e->getMessage());
				$this->db->trans_rollback();
				throw $e;
			}
		} catch (Exception $e) {
			log_message('error', '❌ [END SESSION] General error: ' . $e->getMessage());
			log_message('error', '📋 [END SESSION] Stack trace: ' . $e->getTraceAsString());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Failed to end session: ' . $e->getMessage(),
					'trace' => ENVIRONMENT === 'development' ? $e->getTraceAsString() : null
				]));
		}
	}

	// Helper method untuk membersihkan notifikasi
	private function clearSessionNotifications($outletId, $tableId, $brand)
	{
		try {
			// Check if tables exist
			$sessionTableExists = $this->db->query("SHOW TABLES LIKE 'session_notifications'")->num_rows() > 0;
			$orderTableExists = $this->db->query("SHOW TABLES LIKE 'order_notifications'")->num_rows() > 0;

			log_message('debug', '📋 [END SESSION] Tables exist check - Session: ' .
				($sessionTableExists ? 'Yes' : 'No') . ', Order: ' .
				($orderTableExists ? 'Yes' : 'No'));

			// Clear session notifications
			if ($sessionTableExists) {
				$this->db->where([
					'outlet_id' => $outletId,
					'table_id' => $tableId,
					'brand' => $brand
				])->update('session_notifications', ['status' => 'read']);

				log_message('debug', '📊 [END SESSION] Session notifications cleared: ' .
					$this->db->affected_rows() . ' rows');
			}

			// Clear order notifications
			if ($orderTableExists) {
				$this->db->where([
					'outlet_id' => $outletId,
					'table_id' => $tableId,
					'brand' => $brand
				])->update('order_notifications', ['status' => 'read']);

				log_message('debug', '📊 [END SESSION] Order notifications cleared: ' .
					$this->db->affected_rows() . ' rows');
			}

			return true;
		} catch (Exception $e) {
			log_message('error', '❌ [END SESSION] Error clearing notifications: ' . $e->getMessage());
			return false;
		}
	}

	public function checkNotifications()
	{
		// Aktifkan error reporting untuk menampilkan semua error
		error_reporting(E_ALL);
		ini_set('display_errors', 1);

		try {
			// Log semua input untuk debugging
			log_message('DEBUG', 'CHECK NOTIFICATIONS REQUEST');
			log_message('DEBUG', 'GET Params: ' . json_encode($_GET));
			log_message('DEBUG', 'POST Params: ' . json_encode($_POST));
			log_message('DEBUG', 'Raw Input: ' . file_get_contents('php://input'));

			$this->output->set_content_type('application/json');

			// Validasi parameter dengan logging
			$outletId = $this->input->get('outletId');
			$brand = $this->input->get('brand');

			log_message('DEBUG', "Outlet ID: $outletId, Brand: $brand");

			// Tambahkan validasi ketat
			if (empty($outletId) || empty($brand)) {
				log_message('ERROR', 'Missing required parameters');
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"message" => "Missing required parameters",
						"details" => [
							"outletId" => $outletId,
							"brand" => $brand
						]
					]));
			}

			// Inisialisasi array kosong dengan logging
			$newSessions = [];
			$newOrders = [];

			// Tambahkan try-catch untuk setiap operasi database
			try {
				// Cek eksistensi tabel
				$sessionTableExists = $this->db
					->query("SHOW TABLES LIKE 'session_notifications'")
					->num_rows() > 0;

				$orderTableExists = $this->db
					->query("SHOW TABLES LIKE 'order_notifications'")
					->num_rows() > 0;

				log_message('DEBUG', "Table Exists - Sessions: $sessionTableExists, Orders: $orderTableExists");

				// Ambil sesi baru
				if ($sessionTableExists) {
					$fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));

					$sessionsQuery = $this->db
						->select('*')
						->from('session_notifications')
						->where('status', 'new_session')
						->where('outlet_id', $outletId)
						->where('brand', $brand)
						->where('created_at >', $fifteenMinutesAgo);

					$newSessions = $sessionsQuery->get()->result_array();
					log_message('DEBUG', 'New Sessions: ' . json_encode($newSessions));
				}

				// Ambil order baru
				if ($orderTableExists) {
					$fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));

					$ordersQuery = $this->db
						->select('*')
						->from('order_notifications')
						->where('status', 'new')
						->where('outlet_id', $outletId)
						->where('brand', $brand)
						->where('created_at >', $fifteenMinutesAgo);

					$newOrders = $ordersQuery->get()->result_array();
					log_message('DEBUG', 'New Orders: ' . json_encode($newOrders));
				}
			} catch (Exception $dbError) {
				log_message('ERROR', 'Database Error: ' . $dbError->getMessage());
				log_message('ERROR', 'Trace: ' . $dbError->getTraceAsString());

				return $this->output
					->set_status_header(500)
					->set_output(json_encode([
						"success" => false,
						"message" => "Database error",
						"error_details" => $dbError->getMessage()
					]));
			}

			// Siapkan respons
			$responseData = [
				"success" => true,
				"newSessions" => $newSessions,
				"newOrders" => $newOrders
			];

			log_message('DEBUG', 'Final Response: ' . json_encode($responseData));

			return $this->output
				->set_status_header(200)
				->set_output(json_encode($responseData));
		} catch (Exception $mainError) {
			log_message('ERROR', 'Main Error: ' . $mainError->getMessage());
			log_message('ERROR', 'Trace: ' . $mainError->getTraceAsString());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"message" => "Internal server error",
					"error_details" => $mainError->getMessage()
				]));
		}
	}

	public function markSessionsAsRead()
	{
		try {
			$this->output->set_content_type('application/json');

			// Dapatkan payload dengan validasi lebih ketat
			$payload = json_decode($this->input->raw_input_stream, true);

			// Validasi payload
			if (!isset($payload['tableIds']) || !is_array($payload['tableIds'])) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"message" => "Invalid table IDs"
					]));
			}

			// Validasi tambahan untuk outletId dan brand
			$outletId = $payload['outletId'] ?? null;
			$brand = $payload['brand'] ?? null;

			if (!$outletId || !$brand) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"message" => "Missing outletId or brand"
					]));
			}

			// Cek eksistensi tabel dengan error handling
			$sessionTableExists = $this->db
				->query("SHOW TABLES LIKE 'session_notifications'")
				->num_rows() > 0;

			if (!$sessionTableExists) {
				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						"success" => true,
						"message" => "No session notifications table found"
					]));
			}

			// Perbarui status dengan kondisi tambahan
			$updatedRows = $this->db
				->where_in('table_id', $payload['tableIds'])
				->where('outlet_id', $outletId)
				->where('brand', $brand)
				->where('status', 'new_session')
				->update('session_notifications', ['status' => 'read']);

			return $this->output
				->set_status_header(200)
				->set_output(json_encode([
					"success" => true,
					"message" => "Sessions marked as read",
					"updated_rows" => $updatedRows
				]));
		} catch (Exception $e) {
			log_message('error', 'Error marking sessions as read: ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"message" => "Server error: " . $e->getMessage()
				]));
		}
	}
	public function markOrdersAsRead()
	{
		try {
			// Set content type JSON
			$this->output->set_content_type('application/json');

			// Ambil payload JSON dengan validasi keamanan
			$payload = json_decode($this->input->raw_input_stream, true);

			// Validasi payload
			if (!isset($payload['tableIds']) || !is_array($payload['tableIds'])) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"message" => "Invalid table IDs"
					]));
			}

			// Validasi parameter tambahan
			$outletId = $payload['outletId'] ?? null;
			$brand = $payload['brand'] ?? null;

			if (!$outletId || !$brand) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"message" => "Missing outletId or brand"
					]));
			}

			// Log untuk debugging
			log_message('DEBUG', 'Marking Orders as Read');
			log_message('DEBUG', 'Payload: ' . json_encode($payload));

			// Cek eksistensi tabel
			$tableExists = $this->db
				->query("SHOW TABLES LIKE 'order_notifications'")
				->num_rows() > 0;

			if (!$tableExists) {
				log_message('WARNING', 'order_notifications table does not exist');
				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						"success" => true,
						"message" => "No order notifications table found"
					]));
			}

			// Transaksi database untuk marking orders
			$this->db->trans_start();

			// Update status order dengan kondisi tambahan
			$updatedRows = $this->db
				->where_in('table_id', $payload['tableIds'])
				->where('outlet_id', $outletId)
				->where('brand', $brand)
				->where('status', 'new')
				->update('order_notifications', ['status' => 'read']);

			$this->db->trans_complete();

			// Log hasil update
			log_message('DEBUG', 'Updated Rows: ' . $updatedRows);

			// Kirim respons
			return $this->output
				->set_status_header(200)
				->set_output(json_encode([
					"success" => true,
					"message" => "Orders marked as read",
					"updated_rows" => $updatedRows
				]));
		} catch (Exception $e) {
			// Log error secara menyeluruh
			log_message('ERROR', 'Error marking orders as read: ' . $e->getMessage());
			log_message('ERROR', 'Trace: ' . $e->getTraceAsString());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"message" => "Server error",
					"error_details" => $e->getMessage()
				]));
		}
	}

	public function getData()
	{
		// PERBAIKAN: Logging dan validasi yang lebih ketat
		$this->output->set_content_type('application/json');

		$action = $this->input->get("action");
		$tableId = $this->input->get("tableId");
		$outletId = $this->input->get("outletId");
		$brand = $this->input->get("brand");

		// Log semua parameter yang diterima
		log_message('debug', 'getData Request Parameters: ' . json_encode([
			'action' => $action,
			'tableId' => $tableId,
			'outletId' => $outletId,
			'brand' => $brand
		]));

		try {
			// Validasi parameter dengan lebih detail
			$errors = [];
			if (empty($action)) $errors[] = 'Action tidak boleh kosong';
			if (empty($tableId)) $errors[] = 'Table ID tidak boleh kosong';
			if (empty($outletId)) $errors[] = 'Outlet ID tidak boleh kosong';
			if (empty($brand)) $errors[] = 'Brand tidak boleh kosong';

			if (!empty($errors)) {
				throw new Exception(implode(', ', $errors));
			}

			// Validasi brand yang diizinkan
			$validBrands = ['kopitiam', 'bakery', 'resto'];
			if (!in_array(strtolower($brand), $validBrands)) {
				throw new Exception("Brand tidak valid");
			}

			// Proses aksi sesuai parameter
			switch ($action) {
				case 'getStatusTable':
					// PERBAIKAN: Tangkap array status lokal jika ada
					$localStatuses = $this->input->get("localStatuses");
					if ($localStatuses) {
						$localStatuses = json_decode($localStatuses, true);
					}

					$statusData = $this->getStatusTable($outletId, $brand, $localStatuses);

					return $this->output
						->set_status_header(200)
						->set_output(json_encode([
							'success' => true,
							'data' => $statusData
						]));

				case 'getOrderDetail':
					$orderData = $this->getOrderDetailByTable($tableId, $outletId, $brand);

					// PERBAIKAN: Log hasil pemrosesan order untuk debugging
					log_message('debug', 'Returning Order Data: ' . json_encode([
						'order_summary' => $orderData['summary'] ?? null,
						'details_count' => count($orderData['details'] ?? [])
					]));

					return $this->output
						->set_status_header(200)
						->set_output(json_encode([
							'success' => true,
							'order' => $orderData['order'] ?? null,
							'orderDetails' => $orderData['details'] ?? [],
							'summary' => $orderData['summary'] ?? null
						]));

				default:
					throw new Exception("Aksi tidak valid");
			}
		} catch (Exception $e) {
			// Log error dengan detail
			log_message('error', 'Error in getData: ' . $e->getMessage());
			log_message('error', 'Error Trace: ' . $e->getTraceAsString());

			return $this->output
				->set_status_header(400)
				->set_output(json_encode([
					'success' => false,
					'message' => $e->getMessage(),
					'trace' => ENVIRONMENT === 'development' ? $e->getTraceAsString() : null
				]));
		}
	}

	private function getStatusTable($outletId, $brand, $localStatusesArray = null)
	{
		log_message('debug', 'Getting table statuses for outlet ' . $outletId . ', brand ' . $brand);

		$outlet = $this->MS_Outlet->get_detail_outlet([
			"outlet_id" => $outletId
		]);

		if (!$outlet) {
			log_message('error', 'Outlet not found: ' . $outletId);
			throw new Exception("Outlet tidak ditemukan");
		}

		$tableCount = $outlet['count_table'];
		$statuses = array_fill(0, $tableCount, null);
		$customers = [];
		$orderTimes = [];
		$totals = [];

		$activeOrders = $this->M_Order->getAll([
			"where" => [
				"outlet_id" => $outletId,
				"brand" => $brand,
				"deleted_at" => NULL
			]
		]);

		log_message('debug', 'Found ' . count($activeOrders) . ' active orders for outlet ' . $outletId);

		$serverLastUpdated = [];
		$localLastUpdated = [];
		$respectedLocalStatuses = false;

		foreach ($activeOrders as $order) {
			$index = $order['table_id'] - 1;

			if ($index < 0 || $index >= $tableCount) {
				continue;
			}

			$statuses[$index] = (int)$order['status'];

			$tableId = $order['table_id'];
			$customers[$tableId] = $order['name'];
			$orderTimes[$tableId] = $order['created_at'];

			if (!empty($order['total_amount'])) {
				$totals[$tableId] = $order['total_amount'];
			} else {
				$orderDetails = $this->M_Order_Detail->getAll([
					"where" => [
						"order_id" => $order['id'],
						"deleted_at" => NULL
					]
				]);

				$total = 0;
				foreach ($orderDetails as $detail) {
					$total += $detail['subtotal'] ?? 0;
				}

				$totals[$tableId] = $total;
			}
		}

		$newSessions = $this->getNewSessions($outletId, $brand);
		$newOrders = $this->getNewOrders($outletId, $brand);

		return [
			'statuses' => $statuses,
			'customers' => $customers,
			'orderTimes' => $orderTimes,
			'totals' => $totals,
			'newSessions' => $newSessions,
			'newOrders' => $newOrders,
			'serverLastUpdated' => $serverLastUpdated,
			'localLastUpdated' => $localLastUpdated,
			'respectedLocalStatuses' => $respectedLocalStatuses
		];
	}

	private function getNewSessions($outletId, $brand)
	{
		// Check if table exists
		$tableExists = $this->db
			->query("SHOW TABLES LIKE 'session_notifications'")
			->num_rows() > 0;

		if (!$tableExists) {
			return [];
		}

		return $this->db
			->where('outlet_id', $outletId)
			->where('brand', $brand)
			->where('status', 'new_session')
			->get('session_notifications')
			->result_array();
	}

	// Helper untuk mendapatkan order baru
	private function getNewOrders($outletId, $brand)
	{
		// Check if table exists
		$tableExists = $this->db
			->query("SHOW TABLES LIKE 'order_notifications'")
			->num_rows() > 0;

		if (!$tableExists) {
			return [];
		}

		return $this->db
			->where('outlet_id', $outletId)
			->where('brand', $brand)
			->where('status', 'new')
			->get('order_notifications')
			->result_array();
	}
	public function callWaiter()
	{
		try {
			$this->output->set_content_type('application/json');

			// Ambil payload
			$payload = json_decode($this->input->raw_input_stream);

			// Validasi payload
			if (!isset($payload->outletId) || !isset($payload->tableId) || !isset($payload->brand)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"message" => "Missing required parameters"
					]));
			}

			// Validasi outlet dan meja
			$outlet = $this->MS_Outlet->get_detail_outlet([
				"outlet_id" => $payload->outletId
			]);

			if (!$outlet) {
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						"success" => false,
						"message" => "Outlet not found"
					]));
			}

			// Cek apakah tabel waiter_calls ada
			$tableExists = $this->db
				->query("SHOW TABLES LIKE 'waiter_calls'")
				->num_rows() > 0;

			if (!$tableExists) {
				return $this->output
					->set_status_header(500)
					->set_output(json_encode([
						"success" => false,
						"message" => "Table 'waiter_calls' does not exist"
					]));
			}

			// PERUBAHAN: Cek apakah ada panggilan yang masih 'new' (belum diproses)
			$existingNewCall = $this->db
				->where('outlet_id', $payload->outletId)
				->where('table_id', $payload->tableId)
				->where('brand', $payload->brand)
				->where('status', 'new')
				->get('waiter_calls')
				->row_array();

			if ($existingNewCall) {
				// Jika masih ada yang 'new', beri tahu pelanggan
				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						"success" => true,
						"message" => "Panggilan Anda sedang diproses, silakan tunggu pelayan datang",
						"data" => $existingNewCall
					]));
			}

			// PERUBAHAN: Cek apakah ada panggilan yang masih 'processing'
			$existingProcessingCall = $this->db
				->where('outlet_id', $payload->outletId)
				->where('table_id', $payload->tableId)
				->where('brand', $payload->brand)
				->where('status', 'processing')
				->get('waiter_calls')
				->row_array();

			if ($existingProcessingCall) {
				// Jika masih ada yang 'processing', beri tahu pelanggan tapi tetap buat panggilan baru
				// Lanjutkan untuk membuat panggilan baru
			}

			// Tambahkan panggilan baru
			$now = date('Y-m-d H:i:s');
			$this->db->insert('waiter_calls', [
				'outlet_id' => $payload->outletId,
				'table_id' => $payload->tableId,
				'brand' => $payload->brand,
				'status' => 'new',
				'created_at' => $now
			]);

			$newCallId = $this->db->insert_id();

			if ($existingProcessingCall) {
				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						"success" => true,
						"message" => "Panggilan baru berhasil dibuat, sedangkan panggilan sebelumnya sedang diproses",
						"data" => [
							"id" => $newCallId,
							"created_at" => $now,
							"processing_call_exists" => true
						]
					]));
			}

			return $this->output
				->set_status_header(200)
				->set_output(json_encode([
					"success" => true,
					"message" => "Waiter call created successfully",
					"data" => [
						"id" => $newCallId,
						"created_at" => $now
					]
				]));
		} catch (Exception $e) {
			log_message('error', 'Error in callWaiter: ' . $e->getMessage());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"message" => "Internal server error"
				]));
		}
	}

	public function checkWaiterCalls()
	{
		try {
			$this->output->set_content_type('application/json');

			// Ambil parameter
			$outletId = $this->input->get('outletId');
			$brand = $this->input->get('brand');

			// Validasi parameter
			if (!$outletId || !$brand) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"message" => "Missing required parameters"
					]));
			}

			// Cek apakah tabel ada
			$tableExists = $this->db
				->query("SHOW TABLES LIKE 'waiter_calls'")
				->num_rows() > 0;

			if (!$tableExists) {
				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						"success" => true,
						"waiterCalls" => []
					]));
			}

			// Ambil panggilan yang belum selesai
			$waiterCalls = $this->db
				->where('outlet_id', $outletId)
				->where('brand', $brand)
				->where_in('status', ['new', 'processing'])
				->order_by('created_at', 'ASC')
				->get('waiter_calls')
				->result_array();

			return $this->output
				->set_status_header(200)
				->set_output(json_encode([
					"success" => true,
					"waiterCalls" => $waiterCalls
				]));
		} catch (Exception $e) {
			log_message('error', 'Error in checkWaiterCalls: ' . $e->getMessage());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"message" => "Internal server error"
				]));
		}
	}

	public function processWaiterCall()
	{
		try {
			$this->output->set_content_type('application/json');

			// Ambil payload
			$payload = json_decode($this->input->raw_input_stream);

			// Validasi payload
			if (!isset($payload->callId)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"message" => "Call ID is required"
					]));
			}

			// Update status panggilan
			$this->db
				->where('id', $payload->callId)
				->update('waiter_calls', [
					'status' => 'processing',
					'updated_at' => date('Y-m-d H:i:s')
				]);

			return $this->output
				->set_status_header(200)
				->set_output(json_encode([
					"success" => true,
					"message" => "Waiter call updated successfully"
				]));
		} catch (Exception $e) {
			log_message('error', 'Error in processWaiterCall: ' . $e->getMessage());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"message" => "Internal server error"
				]));
		}
	}

	public function completeWaiterCall()
	{
		try {
			$this->output->set_content_type('application/json');

			// Ambil payload
			$payload = json_decode($this->input->raw_input_stream);

			// Validasi payload
			if (!isset($payload->callId)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"message" => "Call ID is required"
					]));
			}

			// Update status panggilan
			$this->db
				->where('id', $payload->callId)
				->update('waiter_calls', [
					'status' => 'completed',
					'updated_at' => date('Y-m-d H:i:s')
				]);

			return $this->output
				->set_status_header(200)
				->set_output(json_encode([
					"success" => true,
					"message" => "Waiter call completed successfully"
				]));
		} catch (Exception $e) {
			log_message('error', 'Error in completeWaiterCall: ' . $e->getMessage());

			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"message" => "Internal server error"
				]));
		}
	}

	private function sendSalesData($orderId)
	{
		try {
			// Konfigurasi API
			$apiEndpoint = "https://kopitiam.kenesproduction.co.id/apis/sales/add";
			$apiUsername = "apis";
			$apiToken = "6e6d04678199f13a1a9337faa9d43ffb43752b536dcba452c9a5944e7a0d0be9";

			log_message('debug', '=== Sending Sales Data to API (COMPREHENSIVE FIX) ===');
			log_message('debug', 'Order ID: ' . $orderId);

			// Dapatkan data order
			$order = $this->M_Order->getOne(['id' => $orderId]);
			if (!$order) {
				throw new Exception('Order tidak ditemukan dengan ID: ' . $orderId);
			}

			// Dapatkan ID cabang dari outlet
			$outlet = $this->db
				->select('outlet_id, id_api')
				->from('data_outlet')
				->where('outlet_id', $order['outlet_id'])
				->get()
				->row_array();
			$branchId = !empty($outlet['id_api']) ? $outlet['id_api'] : '20';

			// Ambil detail order dengan informasi lengkap
			$orderDetails = $this->db
				->select('od.*, od.unit_price as price, dp.product_name, COALESCE(dp.api_id, dp.product_id) as api_id, COALESCE(od.original_price, dp.product_price) as original_db_price')
				->from('order_details od')
				->join('data_product dp', 'od.product_id = dp.product_id', 'left')
				->where('od.order_id', $orderId)
				->where('od.deleted_at IS NULL')
				->get()
				->result_array();

			log_message('debug', 'Order Details Count: ' . count($orderDetails));

			if (empty($orderDetails)) {
				throw new Exception('Tidak ada detail order ditemukan untuk ID: ' . $orderId);
			}

			// Ambil informasi promo dengan validasi lengkap
			$promoInfo = null;
			$promoType = null;
			$totalOrderDiscount = floatval($order['discount_amount'] ?? 0);
			$promoCode = $order['discount_code'] ?? null;

			log_message('debug', "Promo info check: promoCode={$promoCode}, totalOrderDiscount={$totalOrderDiscount}");

			if (!empty($promoCode)) {
				if (!isset($this->M_Promo)) {
					$this->load->model('promo/M_Promo');
				}
				$promo = $this->M_Promo->getPromoByCode($promoCode);
				if ($promo) {
					$promoInfo = $promo;
					$promoType = $promo['promo_type'];
					log_message('debug', 'Found promo: ' . $promoCode . ', Type: ' . $promoType . ', Value: ' . ($promo['promo_value'] ?? 'N/A') . ', Max Discount: ' . ($promo['maximum_discount'] ?? 'N/A') . ', Total Discount: ' . $totalOrderDiscount);
				} else {
					log_message('warning', 'Promo not found in database: ' . $promoCode);
				}
			} else {
				log_message('debug', 'No promo code found in order');
			}

			// ============ PERBAIKAN UTAMA: KATEGORIASI ITEM YANG BENAR ============
			$regularItems = [];
			$promoFreeItems = [];
			$packageHeaders = [];
			$packageItemsMap = [];
			$discountedItems = [];

			log_message('debug', '=== Starting Item Categorization (COMPREHENSIVE FIX) ===');

			// Kategorisasi item dengan logic yang diperbaiki
			foreach ($orderDetails as $item) {
				$isPromoItem = !empty($item['is_promo_item']) && $item['is_promo_item'] == 1;
				$itemPromoType = $item['promo_type'] ?? null;
				$isPackageHeader = !empty($item['package_id']) && empty($item['parent_id']);
				$isPackageChild = !empty($item['parent_id']);
				$hasApiId = !empty($item['api_id']);

				// PERBAIKAN: Logic yang lebih jelas untuk menentukan item type
				$isDiscountedItem = $isPromoItem && in_array($itemPromoType, ['percentage', 'nominal']);
				$isFreeItem = $isPromoItem && in_array($itemPromoType, ['bundling', 'bogo']);

				log_message('debug', "Item {$item['product_name']}: isPromoItem={$isPromoItem}, itemPromoType={$itemPromoType}, hasApiId={$hasApiId}, isDiscountedItem={$isDiscountedItem}, isFreeItem={$isFreeItem}");

				if ($isPackageChild) {
					$parentId = $item['parent_id'];
					if (!isset($packageItemsMap[$parentId])) {
						$packageItemsMap[$parentId] = [];
					}
					$packageItemsMap[$parentId][] = $item;
				} elseif ($isPackageHeader) {
					$packageHeaders[] = $item;
					if ($isDiscountedItem) {
						$discountedItems[] = $item;
					}
				} elseif ($isFreeItem) {
					$promoFreeItems[] = $item;
				} else {
					$regularItems[] = $item;
					if ($isDiscountedItem) {
						$discountedItems[] = $item;
					}
				}
			}

			// Siapkan detail penjualan untuk API
			$saleDetails = [];
			$packageCounter = 1;

			// ============ PERBAIKAN: PROSES ITEM REGULER DENGAN PERHITUNGAN DISKON YANG BENAR ============
			foreach ($regularItems as $item) {
				if (empty($item['api_id'])) {
					log_message('warning', 'Skipping regular item without api_id: ' . ($item['product_name'] ?? 'Unknown'));
					continue;
				}

				$price = floatval($item['unit_price'] ?? $item['price'] ?? 0);
				$quantity = intval($item['quantity'] ?? 1);
				// PERBAIKAN: Batasi panjang keterangan untuk menghindari MySQL error
				$notes = $this->sanitizeKeterangan($item['notes'] ?? '');

				// PERBAIKAN UTAMA: Perhitungan diskon yang akurat
				$discountType = '';
				$discountValue = 0;
				$discountAmount = 0;

				// PERBAIKAN: Simplified logic untuk cek discount item
				$isPromoItem = (!empty($item['is_promo_item']) && $item['is_promo_item'] == 1)
					|| in_array($promoType, ['percentage', 'nominal']);
				$itemPromoType = $item['promo_type'] ?? $promoType ?? null;


				log_message('debug', "Processing regular item {$item['product_name']}: isPromoItem={$isPromoItem}, itemPromoType={$itemPromoType}, hasPromoInfo=" . ($promoInfo ? 'yes' : 'no'));

				// PERBAIKAN: Langsung cek promo type tanpa kondisi tambahan yang kompleks
				if (!empty($itemPromoType) && $promoInfo && in_array($itemPromoType, ['percentage', 'nominal'])) {

					if ($itemPromoType == 'percentage') {
						$discountType = 'percen'; // Format yang benar untuk API
						$discountValue = floatval($promoInfo['promo_value'] ?? 0);
						$originalPrice = floatval($item['original_price'] ?? $item['original_db_price'] ?? $price);

						log_message('debug', "Percentage discount calculation: discountValue={$discountValue}, originalPrice={$originalPrice}, quantity={$quantity}");

						// Hitung diskon dasar
						$basicDiscountAmount = ($originalPrice * $quantity) * ($discountValue / 100);

						// PERBAIKAN: Terapkan maximum_discount dengan benar
						if (!empty($promoInfo['maximum_discount']) && $promoInfo['maximum_discount'] > 0) {
							$maxDiscount = floatval($promoInfo['maximum_discount']);
							$orderTotal = floatval($order['total_amount'] ?? 1);

							log_message('debug', "Applying maximum discount: maxDiscount={$maxDiscount}, orderTotal={$orderTotal}");

							// Hitung proporsi item terhadap total order
							if ($orderTotal > 0) {
								$itemProportion = ($originalPrice * $quantity) / $orderTotal;
								$maxItemDiscount = $maxDiscount * $itemProportion;

								$discountAmount = min($basicDiscountAmount, $maxItemDiscount);

								// Update discount value based on actual amount applied
								if ($originalPrice * $quantity > 0) {
									$actualDiscountPercentage = ($discountAmount / ($originalPrice * $quantity)) * 100;
									$discountValue = $actualDiscountPercentage;
								}

								log_message('debug', "After max discount applied: itemProportion={$itemProportion}, maxItemDiscount={$maxItemDiscount}, finalDiscountAmount={$discountAmount}, finalDiscountValue={$discountValue}");
							} else {
								$discountAmount = $basicDiscountAmount;
							}
						} else {
							$discountAmount = $basicDiscountAmount;
							log_message('debug', "No maximum discount, using basic: {$basicDiscountAmount}");
						}
					} elseif ($itemPromoType == 'nominal') {
						$discountType = 'rupiah'; // Format yang benar untuk API
						$orderTotal = floatval($order['total_amount'] ?? 1);

						log_message('debug', "Nominal discount calculation: totalOrderDiscount={$totalOrderDiscount}, orderTotal={$orderTotal}");

						// PERBAIKAN: Distribusi proporsional yang akurat untuk nominal discount
						if ($orderTotal > 0) {
							$itemProportion = ($price * $quantity) / $orderTotal;
							$discountValue = $totalOrderDiscount * $itemProportion;
							$discountAmount = $discountValue;

							log_message('debug', "Nominal discount applied: itemProportion={$itemProportion}, discountValue={$discountValue}");
						}
					}

					log_message('debug', "Applied discount to item {$item['product_name']}: Type={$discountType}, Value={$discountValue}, Amount={$discountAmount}");
				} else {
					// PERBAIKAN: Fallback check jika item tidak ter-kategorisasi dengan benar  
					if ($isPromoItem && !empty($itemPromoType)) {
						log_message('warning', "Item {$item['product_name']} has promo but no promoInfo available. ItemPromoType: {$itemPromoType}, PromoCode: {$promoCode}");
					} else {
						log_message('debug', "No discount applied to item {$item['product_name']}: isPromoItem={$isPromoItem}, itemPromoType={$itemPromoType}, hasPromoInfo=" . ($promoInfo ? 'yes' : 'no'));
					}
				}

				$saleDetails[] = [
					'product_id' => intval($item['api_id']),
					'qty' => $quantity,
					'price' => $price,
					'discount_type' => $discountType,
					'discount' => $discountValue,
					'discount_amount' => $discountAmount,
					'keterangan' => $notes
				];
			}

			// ============ PERBAIKAN: PROSES PROMO FREE ITEMS (BUNDLING/BOGO) ============
			foreach ($promoFreeItems as $item) {
				if (empty($item['api_id'])) {
					log_message('warning', 'Skipping promo item without api_id: ' . ($item['product_name'] ?? 'Unknown'));
					continue;
				}

				$quantity = intval($item['quantity'] ?? 1);
				// PERBAIKAN: Keterangan yang lebih singkat untuk bundling/bogo items
				$baseNotes = $this->sanitizeKeterangan($item['notes'] ?? '');
				$originalPrice = floatval($item['original_price'] ?? $item['original_db_price'] ?? 0);

				if ($item['promo_type'] == 'bundling') {
					$saleDetails[] = [
						'product_id' => intval($item['api_id']),
						'qty' => $quantity,
						'price' => $originalPrice,
						'discount_type' => 'rupiah',
						'discount' => $originalPrice,
						'discount_amount' => $originalPrice * $quantity,
						'keterangan' => $this->createBundlingKeterangan($item['product_name'], $baseNotes)
					];

					log_message('debug', "Bundling item sent as 'rupiah' discount: {$item['product_name']}, Original Price: {$originalPrice}");
				} elseif ($item['promo_type'] == 'bogo') {
					$saleDetails[] = [
						'product_id' => intval($item['api_id']),
						'qty' => $quantity,
						'price' => $originalPrice,
						'discount_type' => 'bogo',
						'discount' => 100, // 100% discount untuk BOGO
						'discount_amount' => $originalPrice * $quantity,
						'keterangan' => $this->createBogoKeterangan($item['product_name'], $baseNotes)
					];

					log_message('debug', "BOGO item sent: {$item['product_name']}, Original Price: {$originalPrice}");
				}
			}

			// ============ PERBAIKAN: PROSES PACKAGES DENGAN HANDLING YANG BENAR ============
			foreach ($packageHeaders as $header) {
				if (empty($header['api_id'])) {
					continue;
				}

				$headerPrice = floatval($header['unit_price'] ?? $header['price'] ?? 0);
				$headerQuantity = intval($header['quantity'] ?? 1);
				$headerNotes = $this->sanitizeKeterangan($header['notes'] ?? '');

				// PERBAIKAN: Perhitungan diskon package yang akurat
				$packageDiscountType = '';
				$packageDiscountValue = 0;
				$packageDiscountAmount = 0;

				// PERBAIKAN: Simplified logic untuk cek discount package
				$isPromoItem = !empty($header['is_promo_item']) && $header['is_promo_item'] == 1;
				$headerPromoType = $header['promo_type'] ?? null;

				log_message('debug', "Processing package {$header['product_name']}: isPromoItem={$isPromoItem}, headerPromoType={$headerPromoType}, hasPromoInfo=" . ($promoInfo ? 'yes' : 'no'));

				// PERBAIKAN: Langsung cek promo type untuk package
				if ($isPromoItem && !empty($headerPromoType) && $promoInfo) {

					if ($headerPromoType == 'percentage') {
						$packageDiscountType = 'percen';
						$packageDiscountValue = floatval($promoInfo['promo_value'] ?? 0);
						$originalPrice = floatval($header['original_price'] ?? $header['original_db_price'] ?? $headerPrice);

						log_message('debug', "Package percentage discount calculation: discountValue={$packageDiscountValue}, originalPrice={$originalPrice}, quantity={$headerQuantity}");

						// Hitung diskon dasar untuk package
						$basicDiscountAmount = ($originalPrice * $headerQuantity) * ($packageDiscountValue / 100);

						// PERBAIKAN: Terapkan maximum_discount untuk package
						if (!empty($promoInfo['maximum_discount']) && $promoInfo['maximum_discount'] > 0) {
							$maxDiscount = floatval($promoInfo['maximum_discount']);
							$orderTotal = floatval($order['total_amount'] ?? 1);

							log_message('debug', "Applying maximum discount for package: maxDiscount={$maxDiscount}, orderTotal={$orderTotal}");

							if ($orderTotal > 0) {
								$packageProportion = ($originalPrice * $headerQuantity) / $orderTotal;
								$maxPackageDiscount = $maxDiscount * $packageProportion;

								$packageDiscountAmount = min($basicDiscountAmount, $maxPackageDiscount);

								if ($originalPrice * $headerQuantity > 0) {
									$actualDiscountPercentage = ($packageDiscountAmount / ($originalPrice * $headerQuantity)) * 100;
									$packageDiscountValue = $actualDiscountPercentage;
								}

								log_message('debug', "After max discount applied for package: packageProportion={$packageProportion}, maxPackageDiscount={$maxPackageDiscount}, finalDiscountAmount={$packageDiscountAmount}, finalDiscountValue={$packageDiscountValue}");
							} else {
								$packageDiscountAmount = $basicDiscountAmount;
							}
						} else {
							$packageDiscountAmount = $basicDiscountAmount;
							log_message('debug', "No maximum discount for package, using basic: {$basicDiscountAmount}");
						}
					} elseif ($headerPromoType == 'nominal') {
						$packageDiscountType = 'rupiah';
						$orderTotal = floatval($order['total_amount'] ?? 1);

						log_message('debug', "Package nominal discount calculation: totalOrderDiscount={$totalOrderDiscount}, orderTotal={$orderTotal}");

						if ($orderTotal > 0) {
							$packageProportion = ($headerPrice * $headerQuantity) / $orderTotal;
							$packageDiscountValue = $totalOrderDiscount * $packageProportion;
							$packageDiscountAmount = $packageDiscountValue;

							log_message('debug', "Nominal discount applied for package: packageProportion={$packageProportion}, discountValue={$packageDiscountValue}");
						}
					}

					log_message('debug', "Applied discount to package {$header['product_name']}: Type={$packageDiscountType}, Value={$packageDiscountValue}, Amount={$packageDiscountAmount}");
				} else {
					// PERBAIKAN: Fallback check jika package tidak ter-kategorisasi dengan benar
					if ($isPromoItem && !empty($headerPromoType)) {
						log_message('warning', "Package {$header['product_name']} has promo but no promoInfo available. HeaderPromoType: {$headerPromoType}, PromoCode: {$promoCode}");
					} else {
						log_message('debug', "No discount applied to package {$header['product_name']}: isPromoItem={$isPromoItem}, headerPromoType={$headerPromoType}, hasPromoInfo=" . ($promoInfo ? 'yes' : 'no'));
					}
				}

				$packageData = [
					'package' => 'yes',
					'product_id' => intval($header['api_id']),
					'sale_id_package' => $packageCounter,
					'qty' => $headerQuantity,
					'price' => $headerPrice,
					'discount_type' => $packageDiscountType,
					'discount' => $packageDiscountValue,
					'discount_amount' => $packageDiscountAmount,
					'keterangan' => $headerNotes,
					'sale_detail_package' => []
				];

				// Tambahkan child items dari package
				$headerId = $header['id'];
				if (isset($packageItemsMap[$headerId]) && !empty($packageItemsMap[$headerId])) {
					foreach ($packageItemsMap[$headerId] as $child) {
						$childPrice = floatval($child['unit_price'] ?? $child['price'] ?? 0);
						$childQuantity = intval($child['quantity'] ?? 1);
						$childNotes = $this->sanitizeKeterangan($child['notes'] ?? '');
						$childApiId = !empty($child['api_id']) ? $child['api_id'] : $child['product_id'];

						$packageData['sale_detail_package'][] = [
							'product_id' => intval($childApiId),
							'qty' => $childQuantity,
							'price' => $childPrice,
							'discount_type' => '',
							'discount' => 0,
							'discount_amount' => 0,
							'keterangan' => $childNotes
						];
					}
				}

				$saleDetails[] = $packageData;
				$packageCounter++;
			}

			// PERBAIKAN: Validasi comprehensive sebelum kirim
			if (empty($saleDetails)) {
				throw new Exception('Tidak ada item valid untuk dikirim ke API');
			}

			// PERBAIKAN: Log summary sebelum validasi
			$discountItemsCount = 0;
			$freeItemsCount = 0;
			$regularItemsCount = 0;
			$packageItemsCount = 0;

			foreach ($saleDetails as $detail) {
				if (isset($detail['package']) && $detail['package'] === 'yes') {
					$packageItemsCount++;
					if (!empty($detail['discount_type'])) {
						$discountItemsCount++;
					}
				} else {
					if (!empty($detail['discount_type'])) {
						if ($detail['discount_type'] === 'bogo' || ($detail['discount_type'] === 'rupiah' && $detail['discount'] == $detail['price'])) {
							$freeItemsCount++;
						} else {
							$discountItemsCount++;
						}
					} else {
						$regularItemsCount++;
					}
				}
			}

			log_message('debug', "Sale Details Summary: total=" . count($saleDetails) . ", regular={$regularItemsCount}, discounted={$discountItemsCount}, free={$freeItemsCount}, packages={$packageItemsCount}");

			$this->validateSaleDetails($saleDetails);

			// Buat payload untuk API
			$payload = [
				'sale' => [
					'customer_name' => !empty($order['name']) ? trim(substr($order['name'], 0, 50)) : 'Guest',
					'no_meja' => (string)$order['table_id'],
					'sale_date' => date('Y-m-d'),
					'branch_id' => (string)$branchId,
					'customer_id' => '',
					'poin_amount' => 0,
					'discount_amount' => 0, // Semua discount di level item
					'sale_details' => $saleDetails
				]
			];

			log_message('debug', 'Final Sales API Payload (COMPREHENSIVE FIX): ' . json_encode($payload, JSON_PRETTY_PRINT));

			// Kirim ke API dengan error handling yang lebih baik
			$apiResponse = $this->sendToSalesAPI($apiEndpoint, $payload, $apiUsername, $apiToken);

			log_message('debug', '=== Sales Data Successfully Sent (COMPREHENSIVE FIX) ===');

			return [
				'success' => true,
				'message' => 'Data penjualan berhasil dikirim',
				'data' => $apiResponse
			];
		} catch (Exception $e) {
			log_message('error', 'Error sending sales data (COMPREHENSIVE FIX): ' . $e->getMessage());
			log_message('error', 'Stack trace: ' . $e->getTraceAsString());

			return [
				'success' => false,
				'message' => 'Gagal mengirim data penjualan: ' . $e->getMessage()
			];
		}
	}

	/**
	 * PERBAIKAN: Helper function untuk sanitasi keterangan
	 */
	private function sanitizeKeterangan($notes)
	{
		$sanitized = trim($notes);
		// Batasi panjang maksimal 45 karakter untuk menghindari MySQL error
		if (strlen($sanitized) > 45) {
			$sanitized = substr($sanitized, 0, 42) . '...';
		}
		return $sanitized;
	}

	/**
	 * PERBAIKAN: Helper function untuk membuat keterangan bundling
	 */
	private function createBundlingKeterangan($productName, $baseNotes = '')
	{
		$shortProductName = strlen($productName) > 15 ? substr($productName, 0, 12) . '...' : $productName;
		$keterangan = "Bundle Gratis - {$shortProductName}";

		if (!empty($baseNotes) && strlen($keterangan) < 35) {
			$remainingSpace = 45 - strlen($keterangan) - 3; // 3 for " - "
			if ($remainingSpace > 0) {
				$shortNotes = strlen($baseNotes) > $remainingSpace ? substr($baseNotes, 0, $remainingSpace - 3) . '...' : $baseNotes;
				$keterangan .= " - {$shortNotes}";
			}
		}

		return substr($keterangan, 0, 45);
	}

	/**
	 * PERBAIKAN: Helper function untuk membuat keterangan BOGO
	 */
	private function createBogoKeterangan($productName, $baseNotes = '')
	{
		$shortProductName = strlen($productName) > 20 ? substr($productName, 0, 17) . '...' : $productName;
		$keterangan = "BOGO - {$shortProductName}";

		if (!empty($baseNotes) && strlen($keterangan) < 35) {
			$remainingSpace = 45 - strlen($keterangan) - 3;
			if ($remainingSpace > 0) {
				$shortNotes = strlen($baseNotes) > $remainingSpace ? substr($baseNotes, 0, $remainingSpace - 3) . '...' : $baseNotes;
				$keterangan .= " - {$shortNotes}";
			}
		}

		return substr($keterangan, 0, 45);
	}

	/**
	 * PERBAIKAN: Validasi sale details yang komprehensif
	 */
	private function validateSaleDetails($saleDetails)
	{
		foreach ($saleDetails as $index => $detail) {
			if (empty($detail['product_id'])) {
				throw new Exception("Invalid product_id at sale detail index {$index}");
			}

			if (!isset($detail['price']) || $detail['price'] < 0) {
				throw new Exception("Invalid price at sale detail index {$index}");
			}

			if (!isset($detail['qty']) || $detail['qty'] <= 0) {
				throw new Exception("Invalid quantity at sale detail index {$index}");
			}

			// Validasi keterangan length
			if (isset($detail['keterangan']) && strlen($detail['keterangan']) > 45) {
				throw new Exception("Keterangan too long at sale detail index {$index}: " . strlen($detail['keterangan']) . " chars");
			}

			// Validasi package details jika ada
			if (isset($detail['sale_detail_package']) && is_array($detail['sale_detail_package'])) {
				foreach ($detail['sale_detail_package'] as $childIndex => $childDetail) {
					if (empty($childDetail['product_id'])) {
						throw new Exception("Invalid child product_id at package index {$index}, child {$childIndex}");
					}

					if (isset($childDetail['keterangan']) && strlen($childDetail['keterangan']) > 45) {
						throw new Exception("Child keterangan too long at package index {$index}, child {$childIndex}");
					}
				}
			}
		}
	}

	/**
	 * PERBAIKAN: Fungsi terpisah untuk API call dengan error handling yang lebih baik
	 */
	private function sendToSalesAPI($apiEndpoint, $payload, $apiUsername, $apiToken)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Accept: application/json'
		]);
		curl_setopt($ch, CURLOPT_USERPWD, $apiUsername . ':' . $apiToken);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_HEADER, true);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$responseBody = substr($response, $headerSize);
		$curlInfo = curl_getinfo($ch);

		if ($error = curl_error($ch)) {
			curl_close($ch);
			log_message('error', 'cURL Error Details: ' . json_encode([
				'error' => $error,
				'curl_info' => $curlInfo,
				'payload_size' => strlen(json_encode($payload))
			]));
			throw new Exception('cURL Error: ' . $error);
		}

		curl_close($ch);

		log_message('debug', 'API Response HTTP Code: ' . $httpCode);
		log_message('debug', 'API Response Headers Size: ' . $headerSize);
		log_message('debug', 'API Response Body: ' . substr($responseBody, 0, 1000));

		// Handle HTTP errors
		if ($httpCode >= 400) {
			log_message('error', 'API HTTP Error Details: ' . json_encode([
				'http_code' => $httpCode,
				'response_body' => $responseBody,
				'curl_info' => $curlInfo,
				'payload' => $payload
			]));

			if ($httpCode == 500) {
				throw new Exception('Server API mengalami kesalahan internal (HTTP 500). Response: ' . substr($responseBody, 0, 200));
			} else {
				throw new Exception("API Error HTTP {$httpCode}: " . substr($responseBody, 0, 200));
			}
		}

		// Parse JSON response
		$responseData = json_decode($responseBody, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			log_message('error', 'Invalid JSON Response: ' . json_last_error_msg() . ' - Body: ' . substr($responseBody, 0, 500));
			throw new Exception('Respons JSON tidak valid dari API: ' . json_last_error_msg());
		}

		// Check API response status
		if (!isset($responseData['status']) || $responseData['status'] !== 'OK') {
			$errorMessage = $responseData['message'] ?? 'Kesalahan tidak dikenal dari API';
			log_message('error', 'API Business Logic Error: ' . json_encode($responseData));
			throw new Exception('API Error: ' . $errorMessage);
		}

		return $responseData;
	}

	public function updateOrderStatus()
	{
		try {
			$this->output->set_content_type('application/json');
			// Validasi metode request
			if (!$this->input->is_ajax_request()) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"code" => "001",
						"message" => "Invalid request method"
					]));
			}

			// Parse payload
			$payload = json_decode($this->security->xss_clean($this->input->raw_input_stream));
			if (!isset($payload->tableId) || !isset($payload->status)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"code" => "002",
						"message" => "Missing required parameters"
					]));
			}

			// Validasi status
			$validStatuses = [
				$this->M_Order::STATUS_PROCESSING,
				$this->M_Order::STATUS_SERVED,
				$this->M_Order::STATUS_COMPLETED,
				$this->M_Order::STATUS_CANCELLED
			];
			if (!in_array($payload->status, $validStatuses)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"code" => "003",
						"message" => "Invalid status value"
					]));
			}

			// Ambil order berdasarkan parameter
			$params = [
				"outlet_id" => $payload->outletId,
				"table_id" => $payload->tableId,
				"brand" => $payload->brand,
				"deleted_at" => NULL
			];

			// Jika orderId disertakan, gunakan itu untuk query yang lebih spesifik
			if (isset($payload->orderId) && !empty($payload->orderId)) {
				$params["id"] = $payload->orderId;
			}
			$order = $this->M_Order->getOne($params);
			if (!$order) {
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						"success" => false,
						"code" => "004",
						"message" => "Order not found"
					]));
			}

			// PERBAIKAN: Gunakan transaksi database
			$this->db->trans_begin();

			// Update status order tanpa mengubah deleted_at
			$updateData = [
				"status" => $payload->status,
				"updated_at" => date('Y-m-d H:i:s')
			];

			// Jika status = COMPLETED, tambahkan timestamp completion
			if ($payload->status == $this->M_Order::STATUS_COMPLETED) {
				$updateData["completed_at"] = date('Y-m-d H:i:s');
			}

			// PERBAIKAN: Jika status = CANCELLED, perbarui deleted_at juga
			if ($payload->status == $this->M_Order::STATUS_CANCELLED) {
				$updateData["deleted_at"] = date('Y-m-d H:i:s');
				log_message('info', 'Order cancelled and deleted: ' . $order["id"]);
			}

			// Update database
			$this->M_Order->qBUpdate($order["id"], $updateData);
			$this->load->model('order/M_Order_History', 'M_Order_History');
			$historyData = [
				'order_id' => $order["id"],
				'status' => $payload->status,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_by' => $this->session->userdata('user_id') ?? 1
			];
			$this->M_Order_History->insertOrderHistory($historyData);

			// Check database transaction status
			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return $this->output
					->set_status_header(500)
					->set_output(json_encode([
						"success" => false,
						"code" => "005",
						"message" => "Database error occurred"
					]));
			}

			$apiResponse = null;

			$this->db->trans_commit();

			// Get status label untuk response
			$statusLabels = [
				$this->M_Order::STATUS_RESERVED => "Dipesan",
				$this->M_Order::STATUS_ORDERED => "Order Diproses",
				$this->M_Order::STATUS_PROCESSING => "Sedang Diproses",
				$this->M_Order::STATUS_SERVED => "Sudah Diantar",
				$this->M_Order::STATUS_COMPLETED => "Selesai",
				$this->M_Order::STATUS_CANCELLED => "Dibatalkan"
			];

			// Paksa update data notifikasi untuk meja ini jika ada
			$this->syncTableStatus($payload->tableId, $payload->status);

			// Kirim response dengan info status
			$responseData = [
				"success" => true,
				"code" => "000",
				"message" => "Status berhasil diperbarui",
				"data" => [
					"order_id" => $order["id"],
					"table_id" => $payload->tableId,
					"status" => $payload->status,
					"status_label" => $statusLabels[$payload->status] ?? "Unknown Status",
					"updated_at" => $updateData["updated_at"]
				]
			];

			return $this->output
				->set_status_header(200)
				->set_output(json_encode($responseData));
		} catch (Exception $e) {
			log_message('error', 'Error in updateOrderStatus: ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"code" => "999",
					"message" => "Internal server error: " . $e->getMessage()
				]));
		}
	}

	private function syncTableStatus($tableId, $status)
	{
		try {
			// Update di tabel yang mungkin menyimpan status meja
			$updateData = [
				'status' => $status,
				'updated_at' => date('Y-m-d H:i:s')
			];

			// Update order_notifications untuk meja ini
			$tableExists = $this->db
				->query("SHOW TABLES LIKE 'order_notifications'")
				->num_rows() > 0;

			if ($tableExists) {
				$this->db->where('table_id', $tableId)
					->where('status', 'new')
					->update('order_notifications', ['status' => 'read']);
			}

			// Catat dalam log
			log_message('debug', 'Table status synchronized: Table ' . $tableId . ', Status ' . $status);

			return true;
		} catch (Exception $e) {
			log_message('error', 'Error in syncTableStatus: ' . $e->getMessage());
			return false;
		}
	}

	public function getOrderTimings()
	{
		$orderId = $this->input->get('orderId');

		if (!$orderId) {
			return $this->output
				->set_content_type('application/json')
				->set_status_header(400)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Order ID required'
				]));
		}

		// Get order history
		$this->load->model('order/M_Order_History', 'M_Order_History');
		$history = $this->M_Order_History->getOrderStatusHistory($orderId);

		// Calculate timings
		$timings = $this->calculateOrderTimings($history);

		return $this->output
			->set_content_type('application/json')
			->set_status_header(200)
			->set_output(json_encode([
				'success' => true,
				'data' => $timings
			]));
	}

	private function calculateOrderTimings($history)
	{
		$timings = [
			'ordered' => null,
			'processing' => null,
			'served' => null,
			'completed' => null,
			'duration' => [
				'ordering_to_processing' => null,
				'processing_to_served' => null,
				'served_to_completed' => null,
				'total' => null
			]
		];

		// Ekstrak timestamps dari riwayat
		foreach ($history as $entry) {
			switch ($entry['status']) {
				case 1: // ORDERED
					$timings['ordered'] = $entry['created_at'];
					break;
				case 2: // PROCESSING
					$timings['processing'] = $entry['created_at'];
					break;
				case 3: // SERVED
					$timings['served'] = $entry['created_at'];
					break;
				case 4: // COMPLETED
					$timings['completed'] = $entry['created_at'];
					break;
			}
		}

		// Hitung durasi antar status
		if ($timings['ordered'] && $timings['processing']) {
			$start = new DateTime($timings['ordered']);
			$end = new DateTime($timings['processing']);
			$timings['duration']['ordering_to_processing'] = $this->formatTimeDiff($start, $end);
		}

		if ($timings['processing'] && $timings['served']) {
			$start = new DateTime($timings['processing']);
			$end = new DateTime($timings['served']);
			$timings['duration']['processing_to_served'] = $this->formatTimeDiff($start, $end);
		}

		if ($timings['served'] && $timings['completed']) {
			$start = new DateTime($timings['served']);
			$end = new DateTime($timings['completed']);
			$timings['duration']['served_to_completed'] = $this->formatTimeDiff($start, $end);
		}

		if ($timings['ordered'] && $timings['completed']) {
			$start = new DateTime($timings['ordered']);
			$end = new DateTime($timings['completed']);
			$timings['duration']['total'] = $this->formatTimeDiff($start, $end);
		}

		return $timings;
	}

	private function formatTimeDiff($start, $end)
	{
		$diff = $end->diff($start);
		return [
			'hours' => $diff->h + ($diff->days * 24),
			'minutes' => $diff->i,
			'seconds' => $diff->s,
			'total_seconds' => $start->getTimestamp() - $end->getTimestamp()
		];
	}
}
