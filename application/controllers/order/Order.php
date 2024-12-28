<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

require_once(APPPATH . 'controllers/base/PublicBase.php');

class Order extends ApplicationBase
{
	
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

		// Add cart items if available
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

			if ($params["table_id"] > $outlet["count_table"]) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"code" => "003",
						"message" => "Invalid table number"
					]));
			}

			$timezone = new DateTimeZone('Asia/Jakarta');
			$currentTime = new DateTime('now', $timezone);
			$openTime = new DateTime($outlet['hour_open'], $timezone);
			$closeTime = new DateTime($outlet['hour_close'], $timezone);

			if ($currentTime < $openTime || $currentTime > $closeTime) {
				return $this->output
					->set_status_header(403)
					->set_output(json_encode([
						"success" => false,
						"code" => "004",
						"message" => "Outlet is currently closed. Operating hours: " .
							$outlet['hour_open'] . " - " . $outlet['hour_close']
					]));
			}

			if (isset($payload->latitude) && isset($payload->longitude)) {
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

				if (in_array(false, $inRadius)) {
					return $this->output
						->set_status_header(403)
						->set_output(json_encode([
							"success" => false,
							"code" => "005",
							"message" => "You must be within 100 meters of the outlet"
						]));
				}
			}

			$existingSession = $this->M_Order->getOne(
				array_merge($params, [
					"deleted_at" => null
				])
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
								"expire_at" => $existingSession["expire_at"]
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
						]
					]
				];

				return $this->output
					->set_status_header(201)
					->set_output(json_encode($response));
			} catch (Exception $e) {
				$this->db->trans_rollback();
				return $this->output
					->set_status_header(500)
					->set_output(json_encode([
						"success" => false,
						"code" => "008",
						"message" => "Internal server error"
					]));
			}
		} catch (Exception $e) {
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"code" => "009",
					"message" => "Internal server error"
				]));
		}
	}

	private function validatePayload($payload)
	{
		$requiredFields = ['outletId', 'tableId', 'brand', 'name', 'passcode'];
		foreach ($requiredFields as $field) {
			if (!isset($payload->$field) || empty($payload->$field)) {
				return false;
			}
		}

		$validBrands = ['kopitiam', 'bakery', 'resto'];
		if (!in_array($payload->brand, $validBrands)) {
			return false;
		}

		if (strlen($payload->passcode) < 4) {
			return false;
		}

		return true;
	}

	private function verifyPasscode($inputPasscode, $storedPasscode)
	{
		return password_verify($inputPasscode, $storedPasscode);
	}

	public function list()
	{
		try {
			$tableId = $this->input->get('tableId');
			$outletId = $this->input->get('outletId');
			$category = $this->input->get('category');
			$brandType = $this->input->get('brand');

			log_message('debug', 'Parameters: outletId=' . $outletId . ', tableId=' . $tableId . ', brand=' . $brandType);

			if (empty($outletId) || empty($tableId)) {
				log_message('debug', 'Missing required parameters');
				$this->session->set_flashdata('error', 'Parameter tidak lengkap');
				redirect(base_url());
				return;
			}

			$outlet = $this->MS_Outlet->get_detail_outlet([
				"outlet_id" => $outletId
			]);

			log_message('debug', 'Found outlet: ' . json_encode($outlet));

			if (!$outlet) {
				log_message('debug', 'Outlet not found');
				$this->session->set_flashdata('error', 'Outlet tidak ditemukan');
				redirect(base_url());
				return;
			}

			if ($outlet['outlet_status'] != '0') {
				log_message('debug', 'Outlet not active. Status: ' . $outlet['outlet_status']);
				$this->session->set_flashdata('error', 'Outlet tidak aktif');
				redirect(base_url());
				return;
			}

			if ($tableId > $outlet['count_table']) {
				log_message('debug', 'Invalid table number');
				$this->session->set_flashdata('error', 'Nomor meja tidak valid');
				redirect(base_url());
				return;
			}

			$timezone = new DateTimeZone('Asia/Jakarta');
			$currentTime = new DateTime('now', $timezone);
			$openTime = new DateTime($outlet['hour_open'], $timezone);
			$closeTime = new DateTime($outlet['hour_close'], $timezone);

			if ($currentTime < $openTime || $currentTime > $closeTime) {
				log_message('debug', 'Outside operating hours');
				$this->session->set_flashdata('error', 'Outlet sedang tutup. Jam operasional: ' .
					$outlet['hour_open'] . ' - ' . $outlet['hour_close']);
				redirect(base_url());
				return;
			}

			$this->tsmarty->assign("template_content", "order/order.html");

			switch ($brandType) {
				case "kopitiam":
					$categories = $this->M_categories->get_catalogue_categories($brandType);

					if ($category) {
						$products = $this->M_products->get_catalogues(
							'catalogue_outlet_filter_category',
							[$category, $brandType]
						);
					} else {
						$products = $this->M_products->get_catalogues(
							'get_list_product_outlet',
							[$brandType]
						);
					}
					break;

				default:
					redirect("order?outletId={$outletId}&tableId={$tableId}&brand=kopitiam");
			}

			$packages = $this->M_Package->getAll([
				"where" => [
					"deleted_at" => NULL
				]
			]);

			$groupedProducts = [];
			foreach ($products as $product) {
				if (!isset($groupedProducts[$product['cat_id']])) {
					$groupedProducts[$product['cat_id']] = [
						'category_name' => $product['cat_name'],
						'products' => []
					];
				}

				$price = floor($product['price_catalogue']);
				if ($price != $product['price_catalogue']) {
					$product['price_display'] = str_replace(
						'.',
						',',
						strval($product['price_catalogue'])
					);
				} else {
					$product['price_display'] = $price;
				}

				$product['available_in_packages'] = [];
				foreach ($packages as $package) {
					if ($package['product_id'] == $product['product_id']) {
						$packageDetails = $this->getPackageDetails($package['id']);
						$product['available_in_packages'][] = $packageDetails;
					}
				}

				$stock = $this->MOC_Product->detail($product['product_id']);
				$product['current_stock'] = $stock ? $stock['stock'] : 0;

				$groupedProducts[$product['cat_id']]['products'][] = $product;
			}

			$packageCategories = $this->M_Package_Category->getAll([
				"where" => [
					"deleted_at" => NULL
				]
			]);

			// Assign data to template
			$this->tsmarty->assign('product_mb', $products);
			$this->tsmarty->assign('grouped_products', $groupedProducts);
			$this->tsmarty->assign("catalogueCategories", $categories);
			$this->tsmarty->assign("packages", $packages);
			$this->tsmarty->assign("package_categories", $packageCategories);
			$this->tsmarty->assign("outlet", $outlet);
			$this->tsmarty->assign("table_id", $tableId);

			$sessionData = $this->M_Order->getOne([
				"outlet_id" => $outletId,
				"table_id" => $tableId,
				"brand" => $brandType,
				"deleted_at" => NULL
			]);

			if ($sessionData) {
				$this->tsmarty->assign("session", $sessionData);

				if ($sessionData['status'] == $this->M_Order::STATUS_RESERVED) {
					$cartItems = $this->M_Order_Detail->getAll([
						"where" => [
							"order_id" => $sessionData["id"],
							"deleted_at" => NULL
						]
					]);

					foreach ($cartItems as &$item) {
						if (!empty($item["parent_id"])) {
							$packageItems = $this->M_Order_Detail->getAll([
								"where" => [
									"parent_id" => $item["parent_id"],
									"deleted_at" => NULL
								]
							]);
							$item["package_items"] = $packageItems;
						}
					}

					$this->tsmarty->assign("cart_items", $cartItems);
				}
			}

			parent::display();
		} catch (Exception $e) {
			log_message('error', 'Error in list(): ' . $e->getMessage());
			$this->session->set_flashdata('error', 'Terjadi kesalahan sistem');
			redirect(base_url());
		}
	}

	private function getPackageDetails($packageId)
	{
		try {
			$package = $this->M_Package->getOne([
				"id" => $packageId,
				"deleted_at" => NULL
			]);

			if (!$package) {
				return null;
			}

			$categories = $this->M_Package->getAll([
				"product_id" => $package["product_id"],
				"deleted_at" => NULL
			]);

			$customProducts = $this->db
				->select('pcp.*, dp.product_name, dp.stock')
				->from('package_custom_products pcp')
				->join('data_product dp', 'pcp.product_id = dp.product_id')
				->where('pcp.package_id', $packageId)
				->where('pcp.deleted_at IS NULL')
				->get()
				->result_array();

			$excludedProducts = $this->db
				->select('pe.*, dp.product_name')
				->from('package_excludes pe')
				->join('data_product dp', 'pe.product_id = dp.product_id')
				->where('pe.package_id', $packageId)
				->where('pe.deleted_at IS NULL')
				->get()
				->result_array();

			return [
				'package' => $package,
				'categories' => $categories,
				'custom_products' => $customProducts,
				'excluded_products' => $excludedProducts
			];
		} catch (Exception $e) {
			log_message('error', 'Error in getPackageDetails(): ' . $e->getMessage());
			return null;
		}
	}

	public function cart()
	{
		try {
			$this->output->set_content_type('application/json');

			$params = [
				"outlet_id" => $this->input->get("outletId"),
				"brand" => $this->input->get("brand"),
				"table_id" => $this->input->get("tableId"),
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
						"code" => "002",
						"message" => "No active session found"
					]));
			}

			$cartItems = $this->M_Order_Detail->getAll([
				"where" => [
					"order_id" => $session["id"],
					"deleted_at" => NULL,
					"parent_id" => NULL
				]
			]);

			$formattedCart = [];
			$totalAmount = 0;

			foreach ($cartItems as $item) {
				$product = $this->MOC_Product->detail($item["product_id"]);
				if (!$product) continue;

				$packageDetails = $this->M_Package->getOne([
					"product_id" => $item["product_id"],
					"deleted_at" => NULL
				]);

				$cartItem = [
					"id" => $item["id"],
					"product_id" => $item["product_id"],
					"product_name" => $product["product_name"],
					"product_image" => $product["product_pict"],
					"quantity" => $item["quantity"],
					"notes" => $item["notes"] ?? "",
					"unit_price" => $product["product_price"],
					"stock" => $product["stock"],
					"subtotal" => $item["quantity"] * $product["product_price"]
				];

				if ($packageDetails) {
					$cartItem["is_package"] = true;

					$packageItems = $this->M_Order_Detail->getAll([
						"where" => [
							"order_id" => $session["id"],
							"parent_id" => $item["id"],
							"deleted_at" => NULL
						]
					]);

					$packageProducts = [];
					foreach ($packageItems as $packageItem) {
						$packageProduct = $this->MOC_Product->detail($packageItem["product_id"]);
						if (!$packageProduct) continue;

						$customPrice = $this->db
							->where('package_id', $packageDetails["id"])
							->where('product_id', $packageItem["product_id"])
							->where('deleted_at IS NULL')
							->get('package_custom_products')
							->row_array();

						$packageProducts[] = [
							"id" => $packageItem["id"],
							"product_id" => $packageItem["product_id"],
							"product_name" => $packageProduct["product_name"],
							"product_image" => $packageProduct["product_pict"],
							"quantity" => $packageItem["quantity"],
							"notes" => $packageItem["notes"] ?? "",
							"unit_price" => $customPrice ? $customPrice["sale_price"] : $packageProduct["product_price"],
							"stock" => $packageProduct["stock"]
						];
					}

					$cartItem["package_items"] = $packageProducts;

					$packageTotal = $this->calculatePackageTotal($packageDetails["id"], $packageProducts);
					$cartItem["package_total"] = $packageTotal;
					$totalAmount += ($packageTotal * $item["quantity"]);
				} else {
					$totalAmount += $cartItem["subtotal"];
				}

				$formattedCart[] = $cartItem;
			}

			$outlet = $this->MS_Outlet->get_detail_outlet([
				"outlet_id" => $params["outlet_id"]
			]);

			$response = [
				"success" => true,
				"code" => "000",
				"message" => "Cart data retrieved successfully",
				"data" => [
					"session" => [
						"id" => $session["id"],
						"customer_name" => $session["name"],
						"table_number" => $session["table_id"],
						"created_at" => $session["created_at"],
						"expire_at" => $session["expire_at"]
					],
					"outlet" => [
						"name" => $outlet["outlet_name"],
						"address" => $outlet["outlet_address"]
					],
					"cart" => [
						"items" => $formattedCart,
						"total_items" => count($formattedCart),
						"total_amount" => $totalAmount,
						"formatted_total" => $this->formatCurrency($totalAmount)
					]
				]
			];

			return $this->output->set_output(json_encode($response));
		} catch (Exception $e) {
			log_message('error', 'Error in cart(): ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"code" => "999",
					"message" => "Internal server error"
				]));
		}
	}

	private function calculatePackageTotal($packageId, $selectedProducts)
	{
		try {
			$packagePrice = $this->db
				->select('pc.sale_price')
				->from('packages p')
				->join('package_categories pc', 'p.package_category_id = pc.id')
				->where('p.id', $packageId)
				->where('p.deleted_at IS NULL')
				->get()
				->row_array();

			$total = $packagePrice ? floatval($packagePrice['sale_price']) : 0;

			foreach ($selectedProducts as $product) {
				$customPrice = $this->db
					->where('package_id', $packageId)
					->where('product_id', $product['product_id'])
					->where('deleted_at IS NULL')
					->get('package_custom_products')
					->row_array();

				if ($customPrice) {
					$total += (floatval($customPrice['sale_price']) * $product['quantity']);
				}
			}

			return $total;
		} catch (Exception $e) {
			log_message('error', 'Error in calculatePackageTotal(): ' . $e->getMessage());
			return 0;
		}
	}

	private function formatCurrency($amount)
	{
		return 'Rp ' . number_format($amount, 0, ',', '.') . ',-';
	}

	public function countCart()
	{
		try {
			$this->output->set_content_type('application/json');

			$params = [
				"outlet_id" => $this->input->get("outletId"),
				"brand" => $this->input->get("brand"),
				"table_id" => $this->input->get("tableId"),
			];

			foreach ($params as $key => $value) {
				if (empty($value)) {
					return $this->output
						->set_status_header(400)
						->set_output(json_encode([
							"success" => false,
							"code" => "001",
							"message" => "Missing required parameter: {$key}",
							"data" => [
								"total_items" => 0,
								"total_quantity" => 0,
								"breakdown" => [
									"regular_items" => 0,
									"package_items" => 0
								]
							]
						]));
				}
			}

			$session = $this->M_Order->getOne([
				"outlet_id" => $params["outlet_id"],
				"brand" => $params["brand"],
				"table_id" => $params["table_id"],
				"status" => $this->M_Order::STATUS_RESERVED,
				"deleted_at" => NULL
			]);

			if (!$session) {
				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						"success" => true,
						"code" => "002",
						"message" => "No active session",
						"data" => [
							"total_items" => 0,
							"total_quantity" => 0,
							"breakdown" => [
								"regular_items" => 0,
								"package_items" => 0
							]
						]
					]));
			}

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
				$isPackage = $this->M_Package->getOne([
					"product_id" => $item["product_id"],
					"deleted_at" => NULL
				]);

				if ($isPackage) {
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
					$product = $this->MOC_Product->detail($item["product_id"]);
					if ($product && $product["stock"] >= $item["quantity"]) {
						$totalRegularQuantity += $item["quantity"];
					}
				}
				$totalUniqueItems++;
			}

			$totalQuantity = $totalRegularQuantity + $totalPackageQuantity;

			$cartSummary = [
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
				],
				"session" => [
					"id" => $session["id"],
					"status" => $session["status"],
					"created_at" => $session["created_at"],
					"expire_at" => $session["expire_at"]
				]
			];

			$expireTime = new DateTime($session["expire_at"]);
			$currentTime = new DateTime();
			$timeLeft = $currentTime->diff($expireTime);

			if ($timeLeft->i <= 5) {
				$cartSummary["warning"] = [
					"type" => "session_expiring",
					"message" => "Sesi akan berakhir dalam " . $timeLeft->i . " menit",
					"time_left" => [
						"minutes" => $timeLeft->i,
						"seconds" => $timeLeft->s
					]
				];
			}

			$response = [
				"success" => true,
				"code" => "000",
				"message" => "Cart count retrieved successfully",
				"data" => $cartSummary
			];

			$cacheKey = "cart_count_{$session['id']}";
			$this->cache->save($cacheKey, $cartSummary, 300);

			return $this->output
				->set_status_header(200)
				->set_output(json_encode($response));
		} catch (Exception $e) {
			log_message('error', 'Error in countCart(): ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"code" => "999",
					"message" => "Internal server error",
					"data" => [
						"total_items" => 0,
						"total_quantity" => 0,
						"breakdown" => [
							"regular_items" => 0,
							"package_items" => 0
						]
					]
				]));
		}
	}

	public function add()
	{
		try {
			$res["success"] = false;
			$this->output->set_content_type("application/json");

			$payload = $this->input->raw_input_stream;
			$payload = $this->security->xss_clean($payload);
			$payload = json_decode($payload);

			if (!$this->validateAddPayload($payload)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"code" => "001",
						"message" => "Invalid request format"
					]));
			}

			$order = $this->M_Order->getOne([
				"id" => $payload->orderId
			]);

			if (!$order) {
				return $this->output
					->set_status_header(401)
					->set_output(json_encode([
						"success" => false,
						"code" => "002",
						"message" => "Session has not been set!"
					]));
			}

			if (intval($order["status"]) === $this->M_Order::STATUS_ORDERED) {
				return $this->output
					->set_status_header(422)
					->set_output(json_encode([
						"success" => false,
						"code" => "003",
						"message" => "Order has been placed"
					]));
			}

			$currentTime = new DateTime();
			$expireTime = new DateTime($order["expire_at"]);

			if ($currentTime > $expireTime) {
				return $this->output
					->set_status_header(401)
					->set_output(json_encode([
						"success" => false,
						"code" => "004",
						"message" => "Session has expired"
					]));
			}

			switch ($payload->action) {
				case 1: 
					return $this->handleAddNote($payload, $order);

				case 2:
					return $this->handleAddProduct($payload, $order);

				case 3:
					return $this->handleAddPackage($payload, $order);

				default:
					return $this->output
						->set_status_header(400)
						->set_output(json_encode([
							"success" => false,
							"code" => "005",
							"message" => "Invalid action"
						]));
			}
		} catch (Exception $e) {
			log_message('error', 'Error in add(): ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"code" => "999",
					"message" => "Internal server error"
				]));
		}
	}

	private function handleAddNote($payload, $order)
	{
		if (empty($payload->productId) || empty($payload->notes)) {
			return $this->output
				->set_status_header(400)
				->set_output(json_encode([
					"success" => false,
					"code" => "006",
					"message" => "Missing product ID or notes"
				]));
		}

		$this->M_Order_Detail->update(
			[
				"order_id" => $order["id"],
				"product_id" => $payload->productId
			],
			["notes" => $payload->notes]
		);

		$this->maintainSession($order);

		return $this->output->set_output(json_encode([
			"success" => true,
			"code" => "000",
			"message" => "Note has been added successfully"
		]));	
	}

	private function handleAddProduct($payload, $order)
	{
		if (empty($payload->data)) {
			return $this->output
				->set_status_header(400)
				->set_output(json_encode([
					"success" => false,
					"code" => "007",
					"message" => "Product data is required"
				]));
		}

		$currentTime = date("Y-m-d H:i:s");
		$totalAdded = 0;

		foreach ($payload->data as $item) {
			$product = $this->MOC_Product->detail($item->productId);
			if (!$product || $product["stock"] < $item->quantity) {
				continue;
			}

			$existingItem = $this->M_Order_Detail->getOne([
				"order_id" => $order["id"],
				"product_id" => $item->productId,
				"deleted_at" => NULL
			]);

			if ($existingItem) {
				$newQuantity = $existingItem["quantity"] + $item->quantity;
				if ($newQuantity <= $product["stock"]) {
					$this->M_Order_Detail->update(
						["id" => $existingItem["id"]],
						[
							"quantity" => $newQuantity,
							"updated_at" => $currentTime
						]
					);
					$totalAdded++;
				}
			} else {
				$this->M_Order_Detail->insertOne([
					"order_id" => $order["id"],
					"product_id" => $item->productId,
					"quantity" => $item->quantity,
					"created_at" => $currentTime,
					"updated_at" => $currentTime
				]);
				$totalAdded++;
			}
		}

		$this->maintainSession($order);

		return $this->output->set_output(json_encode([
			"success" => true,
			"code" => "000",
			"message" => "{$totalAdded} products added to cart successfully"
		]));
	}

	private function handleAddPackage($payload, $order)
	{
		if (empty($payload->packageId) || empty($payload->products)) {
			return $this->output
				->set_status_header(400)
				->set_output(json_encode([
					"success" => false,
					"code" => "008",
					"message" => "Package ID and products are required"
				]));
		}

		$validation = $this->validatePackageOrder(
			$payload->packageId,
			$payload->products
		);

		if (!$validation["valid"]) {
			return $this->output
				->set_status_header(422)
				->set_output(json_encode([
					"success" => false,
					"code" => "009",
					"message" => $validation["message"]
				]));
		}

		$currentTime = date("Y-m-d H:i:s");

		$packageId = $this->M_Order_Detail->insertOne([
			"order_id" => $order["id"],
			"product_id" => $payload->packageId,
			"quantity" => 1,
			"price" => $validation["price"]["base_price"],
			"created_at" => $currentTime,
			"updated_at" => $currentTime
		]);

		foreach ($payload->products as $product) {
			$this->M_Order_Detail->insertOne([
				"order_id" => $order["id"],
				"product_id" => $product->productId,
				"parent_id" => $packageId,
				"quantity" => $product->quantity,
				"price" => isset($validation["price"]["custom_prices"][$product->productId])
					? $validation["price"]["custom_prices"][$product->productId]
					: 0,
				"created_at" => $currentTime,
				"updated_at" => $currentTime
			]);
		}

		$this->maintainSession($order);

		return $this->output->set_output(json_encode([
			"success" => true,
			"code" => "000",
			"message" => "Package added to cart successfully",
			"data" => [
				"package_id" => $packageId,
				"total_price" => $validation["price"]["total_price"]
			]
		]));
	}

	private function validateAddPayload($payload)
	{
		if (!isset($payload->action) || !is_numeric($payload->action)) {
			return false;
		}

		if (!isset($payload->orderId)) {
			return false;
		}

		return true;
	}

	public function removeCartItem()
	{
		try {
			$this->output->set_content_type('application/json');

			$payload = $this->input->raw_input_stream;
			$payload = $this->security->xss_clean($payload);
			$payload = json_decode($payload);

			if (!$this->validateRemovePayload($payload)) {
				return $this->output
					->set_status_header(400)
					->set_output(json_encode([
						"success" => false,
						"code" => "001",
						"message" => "Invalid request format"
					]));
			}

			$params = [
				$payload->outletId,
				$payload->tableId,
				$payload->brand
			];

			$order = $this->M_Order->queryDB("getOneByIdentity", $params);

			if (empty($order)) {
				return $this->output
					->set_status_header(404)
					->set_output(json_encode([
						"success" => false,
						"code" => "002",
						"message" => "Order not found"
					]));
			}

			$this->db->trans_begin();

			try {
				$cartItem = $this->M_Order_Detail->getOne([
					"order_id" => $order[0]->id,
					"product_id" => $payload->productId,
					"deleted_at" => NULL
				]);

				if (!$cartItem) {
					$this->db->trans_rollback();
					return $this->output
						->set_status_header(404)
						->set_output(json_encode([
							"success" => false,
							"code" => "003",
							"message" => "Product not found in cart"
						]));
				}

				$isPackageItem = !empty($cartItem["parent_id"]);
				$isPackageHeader = $this->M_Package->getOne([
					"product_id" => $cartItem["product_id"],
					"deleted_at" => NULL
				]);

				if ($isPackageHeader) {
					$result = $this->removePackage($cartItem, $payload->count);
					if (!$result["success"]) {
						$this->db->trans_rollback();
						return $this->output
							->set_status_header(422)
							->set_output(json_encode($result));
					}
				}
				else if ($isPackageItem) {
					$result = $this->removePackageItem($cartItem, $payload->count);
					if (!$result["success"]) {
						$this->db->trans_rollback();
						return $this->output
							->set_status_header(422)
							->set_output(json_encode($result));
					}
				}
				else {
					$result = $this->removeRegularItem($cartItem, $payload->count);
					if (!$result["success"]) {
						$this->db->trans_rollback();
						return $this->output
							->set_status_header(422)
							->set_output(json_encode($result));
					}
				}

				$expiredAt = new DateTime($order[0]->expire_at);
				$currentAt = new DateTime();
				$timeAdd = 15 - $expiredAt->diff($currentAt)->format("%i");
				$newExpiredAt = $expiredAt->add(new DateInterval("PT{$timeAdd}M"));

				$this->M_Order->qBUpdate($order[0]->id, [
					"expire_at" => $newExpiredAt->format("Y-m-d H:i:s"),
					"updated_at" => $currentAt->format("Y-m-d H:i:s")
				]);

				$this->db->trans_commit();

				$updatedCart = $this->getCartSummary($order[0]->id);

				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						"success" => true,
						"code" => "000",
						"message" => "Item removed from cart successfully",
						"data" => $updatedCart
					]));
			} catch (Exception $e) {
				$this->db->trans_rollback();
				log_message('error', 'Error in removeCartItem transaction: ' . $e->getMessage());
				throw $e;
			}
		} catch (Exception $e) {
			log_message('error', 'Error in removeCartItem: ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"code" => "999",
					"message" => "Internal server error"
				]));
		}
	}

	private function removePackage($packageItem, $count)
	{
		$packageItems = $this->M_Order_Detail->getAll([
			"where" => [
				"parent_id" => $packageItem["id"],
				"deleted_at" => NULL
			]
		]);

		$newCount = $packageItem["quantity"] - $count;

		if ($newCount < 0) {
			return [
				"success" => false,
				"message" => "Invalid removal quantity"
			];
		}

		if ($newCount === 0) {
			$currentTime = date("Y-m-d H:i:s");

			$this->M_Order_Detail->update(
				["id" => $packageItem["id"]],
				["deleted_at" => $currentTime]
			);

			foreach ($packageItems as $item) {
				$this->M_Order_Detail->update(
					["id" => $item["id"]],
					["deleted_at" => $currentTime]
				);
			}
		} else {

			$this->M_Order_Detail->update(
				["id" => $packageItem["id"]],
				["quantity" => $newCount]
			);

			foreach ($packageItems as $item) {
				$this->M_Order_Detail->update(
					["id" => $item["id"]],
					["quantity" => $newCount]
				);
			}
		}

		return [
			"success" => true,
			"message" => "Package removed successfully"
		];
	}

	private function removePackageItem($cartItem, $count)
	{
		$packageHeader = $this->M_Order_Detail->getOne([
			"id" => $cartItem["parent_id"],
			"deleted_at" => NULL
		]);

		if (!$packageHeader) {
			return [
				"success" => false,
				"message" => "Parent package not found"
			];
		}

		$newCount = $cartItem["quantity"] - $count;

		if ($newCount < 0) {
			return [
				"success" => false,
				"message" => "Invalid removal quantity"
			];
		}

		if ($newCount === 0) {
			$packageRules = $this->M_Package->getAll([
				"product_id" => $packageHeader["product_id"],
				"deleted_at" => NULL
			]);

			$remainingItems = $this->M_Order_Detail->getAll([
				"where" => [
					"parent_id" => $packageHeader["id"],
					"id !=" => $cartItem["id"],
					"deleted_at" => NULL
				]
			]);

			foreach ($packageRules as $rule) {
				$categoryCount = 0;
				foreach ($remainingItems as $item) {
					$product = $this->MOC_Product->detail($item["product_id"]);
					if ($product["package_category_id"] == $rule["package_category_id"]) {
						$categoryCount += $item["quantity"];
					}
				}

				if ($categoryCount < $rule["quantity"]) {
					return [
						"success" => false,
						"message" => "Cannot remove item: package requirements not met"
					];
				}
			}

			$this->M_Order_Detail->update(
				["id" => $cartItem["id"]],
				["deleted_at" => date("Y-m-d H:i:s")]
			);
		} else {
			$this->M_Order_Detail->update(
				["id" => $cartItem["id"]],
				["quantity" => $newCount]
			);
		}

		return [
			"success" => true,
			"message" => "Package item removed successfully"
		];
	}

	private function removeRegularItem($cartItem, $count)
	{
		$newCount = $cartItem["quantity"] - $count;

		if ($newCount < 0) {
			return [
				"success" => false,
				"message" => "Invalid removal quantity"
			];
		}

		$data = [
			"quantity" => $newCount
		];

		if ($newCount === 0) {
			$data["deleted_at"] = date("Y-m-d H:i:s");
			$data["notes"] = "";
		}

		$this->M_Order_Detail->update(
			["id" => $cartItem["id"]],
			$data
		);

		return [
			"success" => true,
			"message" => "Item removed successfully"
		];
	}

	private function getCartSummary($orderId)
	{
		$cartItems = $this->M_Order_Detail->getAll([
			"where" => [
				"order_id" => $orderId,
				"deleted_at" => NULL
			]
		]);

		$summary = [
			"total_items" => 0,
			"total_quantity" => 0,
			"total_amount" => 0,
			"items" => []
		];

		foreach ($cartItems as $item) {
			$product = $this->MOC_Product->detail($item["product_id"]);
			if (!$product) continue;

			$summary["total_items"]++;
			$summary["total_quantity"] += $item["quantity"];
			$summary["total_amount"] += ($item["quantity"] * $product["product_price"]);

			$summary["items"][] = [
				"id" => $item["id"],
				"product_id" => $item["product_id"],
				"product_name" => $product["product_name"],
				"quantity" => $item["quantity"],
				"price" => $product["product_price"],
				"subtotal" => ($item["quantity"] * $product["product_price"])
			];
		}

		return $summary;
	}

	private function validateRemovePayload($payload)
	{
		$requiredFields = ['outletId', 'tableId', 'brand', 'productId', 'count'];
		foreach ($requiredFields as $field) {
			if (!isset($payload->$field)) {
				return false;
			}
		}

		if (!is_numeric($payload->count) || $payload->count <= 0) {
			return false;
		}

		return true;
	}

	public function doneOrder()
	{
		try {
			$this->output->set_content_type('application/json');

			$payload = $this->input->raw_input_stream;
			$payload = $this->security->xss_clean($payload);
			$payload = json_decode($payload);

			if (!$this->validateDoneOrderPayload($payload)) {
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

			$this->db->trans_begin();

			try {
				$order = $this->M_Order->getOne($params);

				if (!$order) {
					return $this->output
						->set_status_header(404)
						->set_output(json_encode([
							"success" => false,
							"code" => "002",
							"message" => "Order not found"
						]));
				}

				if ($order["status"] !== $this->M_Order::STATUS_RESERVED) {
					return $this->output
						->set_status_header(422)
						->set_output(json_encode([
							"success" => false,
							"code" => "003",
							"message" => "Order is not in reserved status"
						]));
				}

				$orderDetails = $this->processOrderDetails($order["id"]);

				$stockValidation = $this->validateAndUpdateStock($orderDetails);
				if (!$stockValidation["success"]) {
					$this->db->trans_rollback();
					return $this->output
						->set_status_header(422)
						->set_output(json_encode($stockValidation));
				}

				$orderSummary = $this->calculateOrderSummary($orderDetails);

				$currentTime = date('Y-m-d H:i:s');
				$updateData = [
					"status" => $this->M_Order::STATUS_ORDERED,
					"updated_at" => $currentTime,
					"total_amount" => $orderSummary["total_amount"],
					"total_items" => $orderSummary["total_items"]
				];

				$this->M_Order->qBUpdate($order["id"], $updateData);

				$receiptNumber = $this->generateReceiptNumber($order);

				$this->storeOrderSummary($order["id"], $orderSummary, $receiptNumber);

				$this->db->trans_commit();

				$response = [
					"success" => true,
					"code" => "000",
					"message" => "Order has been placed successfully",
					"data" => [
						"order_id" => $order["id"],
						"receipt_number" => $receiptNumber,
						"summary" => $orderSummary,
						"customer" => [
							"name" => $order["name"],
							"table" => $order["table_id"]
						],
						"timing" => [
							"order_time" => $currentTime,
							"session_duration" => $this->calculateSessionDuration($order)
						]
					]
				];

				$this->sendKitchenNotification($order["id"], $orderDetails);

				return $this->output
					->set_status_header(200)
					->set_output(json_encode($response));
			} catch (Exception $e) {
				$this->db->trans_rollback();
				log_message('error', 'Error in doneOrder transaction: ' . $e->getMessage());
				throw $e;
			}
		} catch (Exception $e) {
			log_message('error', 'Error in doneOrder: ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					"success" => false,
					"code" => "999",
					"message" => "Internal server error"
				]));
		}
	}

	private function processOrderDetails($orderId)
	{
		$orderDetails = $this->MOC_Order_Detail->getAll([
			"order_id" => $orderId
		]);

		$processedDetails = [];
		foreach ($orderDetails as $detail) {
			$product = $this->MOC_Product->detail($detail["product_id"]);
			if (!$product) continue;

			$detailData = [
				"detail" => $detail,
				"product" => $product
			];

			if (!empty($detail["parent_id"])) {
				$parent = $this->M_Order_Detail->getOne([
					"id" => $detail["parent_id"]
				]);
				$detailData["parent"] = $parent;

				$package = $this->M_Package->getOne([
					"product_id" => $parent["product_id"]
				]);
				if ($package) {
					$detailData["package"] = $package;
				}
			}

			$processedDetails[] = $detailData;
		}

		return $processedDetails;
	}

	private function validateAndUpdateStock($orderDetails)
	{
		$stockUpdates = [];

		foreach ($orderDetails as $detail) {
			$newStock = $detail["product"]["stock"] - $detail["detail"]["quantity"];

			if ($newStock < 0) {
				return [
					"success" => false,
					"code" => "004",
					"message" => "Insufficient stock for " . $detail["product"]["product_name"]
				];
			}

			$stockUpdates[] = [
				"product_id" => $detail["product"]["product_id"],
				"stock" => $newStock
			];
		}

		$this->MOC_Product->updateAll('product_id', $stockUpdates);

		return ["success" => true];
	}

	private function calculateOrderSummary($orderDetails)
	{
		$summary = [
			"total_amount" => 0,
			"total_items" => 0,
			"packages" => [],
			"regular_items" => [],
			"total_quantity" => 0
		];

		foreach ($orderDetails as $detail) {
			if (!empty($detail["parent_id"])) {
				continue;
			}

			$quantity = $detail["detail"]["quantity"];
			$price = isset($detail["detail"]["price"]) ?
				$detail["detail"]["price"] :
				$detail["product"]["product_price"];

			if (isset($detail["package"])) {
				$packageItems = array_filter($orderDetails, function ($item) use ($detail) {
					return isset($item["parent"]) && $item["parent"]["id"] == $detail["detail"]["id"];
				});

				$packageSummary = [
					"package_id" => $detail["package"]["id"],
					"quantity" => $quantity,
					"base_price" => $price,
					"items" => [],
					"total_price" => $price
				];

				foreach ($packageItems as $item) {
					$packageSummary["items"][] = [
						"product_id" => $item["product"]["product_id"],
						"product_name" => $item["product"]["product_name"],
						"quantity" => $item["detail"]["quantity"],
						"price" => $item["detail"]["price"] ?? 0
					];
					$packageSummary["total_price"] += ($item["detail"]["price"] ?? 0);
				}

				$summary["packages"][] = $packageSummary;
				$summary["total_amount"] += ($packageSummary["total_price"] * $quantity);
			} else {
				$summary["regular_items"][] = [
					"product_id" => $detail["product"]["product_id"],
					"product_name" => $detail["product"]["product_name"],
					"quantity" => $quantity,
					"price" => $price,
					"subtotal" => $price * $quantity
				];
				$summary["total_amount"] += ($price * $quantity);
			}

			$summary["total_items"]++;
			$summary["total_quantity"] += $quantity;
		}

		return $summary;
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
		$summaryData = [
			"order_id" => $orderId,
			"receipt_number" => $receiptNumber,
			"summary_data" => json_encode($summary),
			"created_at" => date('Y-m-d H:i:s')
		];

		$this->db->insert('order_summaries', $summaryData);
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

	private function maintainSession($order)
	{
		$expiredAt = new DateTime($order["expire_at"]);
		$currentAt = new DateTime();
		$timeAdd = 15 - $expiredAt->diff($currentAt)->format("%i");

		$newExpiredAt = $expiredAt->add(new DateInterval("PT{$timeAdd}M"));

		$data = [
			"expire_at" => $newExpiredAt->format("Y-m-d H:i:s"),
			"updated_at" => $currentAt->format("Y-m-d H:i:s")
		];

		$this->M_Order->qBUpdate($order["id"], $data);
	}

	private function validatePackageOrder($packageId, $selectedProducts)
	{
		$packageValidation = $this->M_Package->validatePackage($packageId, $selectedProducts);
		if (!$packageValidation['valid']) {
			return [
				'valid' => false,
				'message' => $packageValidation['message']
			];
		}

		$exclusionCheck = $this->M_Package->checkExclusions($packageId, $selectedProducts);
		if (!$exclusionCheck['valid']) {
			return [
				'valid' => false,
				'message' => $exclusionCheck['message']
			];
		}

		foreach ($selectedProducts as $product) {
			$productDetail = $this->MOC_Product->detail($product->productId);
			if (!$productDetail || $productDetail['stock'] < $product->quantity) {
				return [
					'valid' => false,
					'message' => "Stok tidak mencukupi untuk produk " .
						$productDetail['product_name']
				];
			}
		}

		$priceCalculation = $this->M_Package->calculatePackagePrice(
			$packageId,
			$selectedProducts
		);

		return [
			'valid' => true,
			'message' => 'Paket valid',
			'price' => $priceCalculation
		];
	}
}
