<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

require_once(APPPATH . 'controllers/base/PrivateBase.php');

class Order extends ApplicationBase
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('settings/M_outlets', 'm_outlets');
		$this->load->model('order/cashier/M_Order', "M_Order");
		$this->load->model('order/cashier/M_Order_Detail', "M_Order_Detail");
	}

	function index()
	{
		$this->tsmarty->assign("template_content", "order/cashier/orderIndex.html");
		// search
		$keyword = '';
		$search = $this->session->userdata('search_outlet');
		if ($this->input->post()) {
			if ($this->input->post('save') == "Reset") {
				$this->session->unset_userdata("search_outlet");
			} else {
				$keyword = $this->input->post('keyword');
				$params = array(
					"keyword" => $keyword,
				);
				$this->session->set_userdata("search_outlet", $params);
			}
		} elseif (!empty($search)) {
			$keyword = $search['keyword'];
		}
		$this->tsmarty->assign("keyword", $keyword);
		// load library
		$this->load->library('pagination');
		// pagination
		$config['base_url'] = site_url('master/orders/index/');
		$config['total_rows'] = $this->m_outlets->get_total_data($keyword);
		$config['uri_segment'] = 4;
		$config['per_page'] = 10;
		$this->pagination->initialize($config);
		$pagination['data'] = $this->pagination->create_links();
		// pagination attribute
		$start = $this->uri->segment(4, 0) + 1;
		$end = $this->uri->segment(4, 0) + $config['per_page'];
		$end = (($end > $config['total_rows']) ? $config['total_rows'] : $end);
		$pagination['start'] = ($config['total_rows'] == 0) ? 0 : $start;
		$pagination['end'] = $end;
		$pagination['total'] = $config['total_rows'];
		// pagination assign value
		$this->tsmarty->assign("pagination", $pagination);
		$this->tsmarty->assign("no", $start);
		/* end of pagination ---------------------- */
		// get list data
		$params = array(($start - 1), $config['per_page']);
		$data = $this->m_outlets->get_list_data($params, $keyword);
		$this->tsmarty->assign("datas", $data);
		// output
		parent::display();
	}

	function detail($id)
	{
		$data = $this
			->m_outlets
			->get_detail_outlet($id);

		$this->tsmarty->assign(
			"template_content",
			"order/cashier/orderDetail.html"
		);
		$this
			->tsmarty
			->assign("datas", $data);

		parent::display();
	}

	public function getData()
	{

		// Set content type JSON untuk semua respons
		$this->output->set_content_type('application/json');

		$action = $this->input->get("action");

		try {

			// Validasi action ada dan method yang sesuai ada
			if (empty($action) || !method_exists($this, $action)) {
				throw new Exception("Invalid action requested");
			}

			$data = $this->$action();

			// Pastikan struktur respons konsisten dan force tipe content ke JSON
			$this->output->set_content_type('application/json');

			$res = [
				"success" => isset($data['success']) ? $data['success'] : true,
				"message" => isset($data['message']) ? $data['message'] : "Data berhasil diambil",
				"data" => $data
			];

			return $this->output->set_output(json_encode($res));
		} catch (Exception $e) {

			// Force tipe content ke JSON untuk mencegah output HTML
			$this->output->set_content_type('application/json');

			return $this->output->set_output(json_encode([
				"success" => false,
				"message" => "Terjadi kesalahan: " . $e->getMessage()
			]));
		}
	}

	function download()
	{
		$action = $this->input->get("action");

		$data = $this->$action();

		return $data;
	}

	private function printReceipt()
	{
		// Log untuk debugging
		log_message('debug', '=== Start Print Receipt ===');
		log_message('debug', 'Params: ' . json_encode($_GET));
		$params = [
			"outlet_id" => $this->input->get("outletId"),
			"brand" => $this->input->get("brand"),
			"table_id" => $this->input->get("tableId")
		];

		// Tambahkan parameter sessionId untuk mendukung cetak dari riwayat
		$sessionId = $this->input->get("sessionId");

		try {
			// Get order and outlet data - PERBAIKAN: Gunakan sessionId jika tersedia
			if ($sessionId) {
				// Get order by sessionId/orderId
				$order = $this->M_Order_Detail->getAll([
					"where" => [
						"order_id" => $sessionId,
						"deleted_at" => NULL
					]
				]);

				// Get session/order data
				$customer = $this->M_Order->getOne([
					"id" => $sessionId,
					"deleted_at" => NULL
				]);
			} else {
				// Use regular method (by table params)
				$order = $this->M_Order->getOrderWithDetail($params);
				$customer = $this->M_Order->getOne(
					array_merge($params, [
						"deleted_at" => NULL,
						"status" => 1,
					])
				);
			}

			$outlet = $this->m_outlets->get_detail_outlet($params["outlet_id"]);

			// Perbaikan: Tambahkan penanganan kesalahan jika data tidak ditemukan
			if (!$order) {
				log_message('error', 'Order not found for params: ' . json_encode($params));
				$errorMessage = "Data order tidak ditemukan. Silakan coba lagi.";
				$this->session->set_flashdata('error', $errorMessage);
				redirect('order/cashier');
				return;
			}

			if (!$outlet) {
				log_message('error', 'Outlet not found for id: ' . $params["outlet_id"]);
				$errorMessage = "Data outlet tidak ditemukan. Silakan coba lagi.";
				$this->session->set_flashdata('error', $errorMessage);
				redirect('order/cashier');
				return;
			}

			if (!$customer) {
				log_message('error', 'Customer not found for params: ' . json_encode($params));
				$errorMessage = "Data pelanggan tidak ditemukan. Silakan coba lagi.";
				$this->session->set_flashdata('error', $errorMessage);
				redirect('order/cashier');
				return;
			}

			// Log data untuk debugging
			log_message('debug', 'Found Order: ' . json_encode([
				'id' => $customer['id'],
				'items_count' => count($order),
				'outlet_id' => $outlet['outlet_id']
			]));

			$orderDateTime = new DateTime($customer["created_at"]);

			// PERBAIKAN UTAMA: Hitung total dengan konsisten
			$regularItems = [];
			$packageItems = [];
			$packageHeaders = [];
			$totalAmount = 0;
			$packageTotals = [];

			// Kategorikan items ke regular dan package
			foreach ($order as $item) {
				// Log debug per item
				log_message('debug', 'Processing Item: ' . json_encode([
					'id' => $item['id'],
					'product_name' => $item['product_name'],
					'parent_id' => $item['parent_id'] ?? null,
					'unit_price' => $item['unit_price'] ?? null,
					'quantity' => $item['quantity'] ?? null
				]));

				if (empty($item['parent_id'])) {
					// Ini adalah regular item atau package header
					$unitPrice = (float)($item['unit_price'] ?? $item['price'] ?? 0);
					$quantity = (int)($item['quantity'] ?? $item['qty'] ?? 0);
					$itemSubtotal = $unitPrice * $quantity;

					// Cek apakah ini package header dengan memeriksa package_id
					if (!empty($item['package_id'])) {
						log_message('debug', 'Found Package Header: ' . json_encode([
							'id' => $item['id'],
							'package_id' => $item['package_id'],
							'subtotal' => $itemSubtotal
						]));

						$packageHeaders[$item['id']] = [
							'id' => $item['id'],
							'product_name' => $item['product_name'],
							'unit_price' => $unitPrice,
							'quantity' => $quantity,
							'subtotal' => $itemSubtotal,
							'package_id' => $item['package_id'],
							'notes' => $item['notes'] ?? '',
							'is_package' => true,
							'items' => []
						];

						$packageTotals[$item['id']] = $itemSubtotal;
					} else {
						log_message('debug', 'Found Regular Item: ' . json_encode([
							'id' => $item['id'],
							'subtotal' => $itemSubtotal
						]));

						$regularItems[] = [
							'id' => $item['id'],
							'product_name' => $item['product_name'],
							'unit_price' => $unitPrice,
							'quantity' => $quantity,
							'subtotal' => $itemSubtotal,
							'notes' => $item['notes'] ?? ''
						];

						$totalAmount += $itemSubtotal;
					}
				} else {
					// Ini adalah package item
					log_message('debug', 'Found Package Item: ' . json_encode([
						'id' => $item['id'],
						'parent_id' => $item['parent_id']
					]));

					if (!isset($packageItems[$item['parent_id']])) {
						$packageItems[$item['parent_id']] = [];
					}

					$unitPrice = (float)($item['unit_price'] ?? $item['price'] ?? 0);
					$quantity = (int)($item['quantity'] ?? $item['qty'] ?? 0);
					$itemSubtotal = $unitPrice * $quantity;

					$packageItems[$item['parent_id']][] = [
						'id' => $item['id'],
						'product_name' => $item['product_name'],
						'unit_price' => $unitPrice,
						'quantity' => $quantity,
						'subtotal' => $itemSubtotal,
						'notes' => $item['notes'] ?? ''
					];
				}
			}

			// Proses package headers dan tambahkan ke order
			$finalItems = $regularItems;
			foreach ($packageHeaders as $headerId => $header) {
				$packageItemsList = $packageItems[$headerId] ?? [];

				// Calculate the total package price including items
				$packageTotal = $header['unit_price'] * $header['quantity']; // Start with base price

				// Add package items to the package header
				$header['items'] = $packageItemsList;

				// Calculate total including items
				$itemsTotal = 0;
				foreach ($packageItemsList as $pkgItem) {
					$itemsTotal += $pkgItem['subtotal'];
				}

				// Set new total that includes all items
				$header['subtotal'] = $packageTotal + $itemsTotal;

				// Add to final items
				$finalItems[] = $header;
				$totalAmount += $header['subtotal'];
			}

			// Hitung pajak
			$tax = $totalAmount * 0.1; // 10% pajak
			$grandTotal = $totalAmount + $tax;

			// Generate nomor receipt unik
			$receiptNumber = strtoupper(substr($params["brand"], 0, 3)) .
				date('Ymd', strtotime($customer["created_at"])) .
				str_pad($customer["id"], 4, '0', STR_PAD_LEFT);

			// Prepare complete data structure for receipt
			$data = [
				"order" => $finalItems, // Include processed items with proper subtotals
				"outlet" => $outlet,
				"customer" => [
					"order" => $customer,
					"date" => $orderDateTime->format("d/m/Y"),
					"time" => $orderDateTime->format("H:i")
				],
				// Use consistent calculations for financial data
				"financial" => [
					"subtotal" => $totalAmount,
					"tax" => $tax,
					"grand_total" => $grandTotal,
					"receipt_number" => $receiptNumber
				]
			];

			// Log complete data for debugging
			log_message('debug', 'Receipt Data Summary: ' . json_encode([
				'subtotal' => $totalAmount,
				'tax' => $tax,
				'grand_total' => $grandTotal,
				'item_count' => count($finalItems)
			]));

			log_message('debug', '=== End Print Receipt ===');

			$this->tsmarty->assign("data", $data);
			$this->tsmarty->display("order/cashier/orderReceipt.html");
		} catch (Exception $e) {
			log_message('error', 'Error in printReceipt: ' . $e->getMessage());
			log_message('error', 'Stack trace: ' . $e->getTraceAsString());
			$this->session->set_flashdata('error', 'Error mencetak struk: ' . $e->getMessage());
			redirect('order/cashier');
		}
	}

	private function getStatusTable() {
    $params = [
        "outlet_id" => $this->input->get("outletId"),
        "brand" => $this->input->get("brand")
    ];
    
    $outlet = $this->m_outlets->get_detail_outlet($params["outlet_id"]);
    
    // Inisialisasi array status meja dengan null
    $table = array_fill(0, $outlet["count_table"], NULL);
    
    // PERBAIKAN: Pastikan hanya mengambil order yang tidak dihapus
    $params['deleted_at'] = NULL;
    
    // PERBAIKAN: Ambil order dengan semua status, termasuk completed
    $orders = $this->M_Order->getOrderStatus($params);
    
    // PERBAIKAN: Tambahkan data untuk informasi pelanggan dan konversi status ke numerik
    $customerNames = [];
    $orderTimes = [];
    $orderTotals = [];
    
    foreach ($orders as $order) {
        $tableIdx = $order["table_id"] - 1; // Adjust to zero-based index
        
        // PERBAIKAN: Convert status to integer value for consistency
        // PERBAIKAN: TETAP SIMPAN STATUS BAHKAN JIKA COMPLETED ATAU CANCELLED
        $table[$tableIdx] = (int)$order["status"];
        
        // Add customer info
        $customerNames[$order["table_id"]] = $order["name"];
        $orderTimes[$order["table_id"]] = $order["created_at"];
        
        // Get total amount if available
        if (isset($order["total_amount"])) {
            $orderTotals[$order["table_id"]] = $order["total_amount"];
        } else {
            // Calculate total if not present in order record
            $orderDetails = $this->M_Order->getDetailsByOrderId($order["id"]);
            $total = 0;
            foreach ($orderDetails as $detail) {
                $total += ($detail["qty"] * ($detail["price"] ?? 0));
            }
            $orderTotals[$order["table_id"]] = $total;
        }
    }
    
    // PERBAIKAN: notifikasi handling sama seperti sebelumnya
    $newSessions = [];
    $newOrders = [];

		try {
			// Cek notifikasi sama seperti implementasi sebelumnya
			$sessionTableExists = $this->db
				->query("SHOW TABLES LIKE 'session_notifications'")
				->num_rows() > 0;

			if ($sessionTableExists) {
				$fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));

				$newSessions = $this->db
					->select('*')
					->from('session_notifications')
					->where('status', 'new_session')
					->where('outlet_id', $params["outlet_id"])
					->where('brand', $params["brand"])
					->where('created_at >', $fifteenMinutesAgo)
					->get()
					->result_array();

				log_message('debug', 'Found ' . count($newSessions) . ' new sessions notifications');
			}

			$orderTableExists = $this->db
				->query("SHOW TABLES LIKE 'order_notifications'")
				->num_rows() > 0;

			if ($orderTableExists) {
				$fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));

				$newOrders = $this->db
					->select('*')
					->from('order_notifications')
					->where('status', 'new')
					->where('outlet_id', $params["outlet_id"])
					->where('brand', $params["brand"])
					->where('created_at >', $fifteenMinutesAgo)
					->get()
					->result_array();

				log_message('debug', 'Found ' . count($newOrders) . ' new order notifications');
			}
		} catch (Exception $e) {
			log_message('ERROR', 'Error checking notifications: ' . $e->getMessage());
		}

		return [
			"success" => true,
			"data" => [
				"statuses" => $table,
				"customers" => $customerNames,
				"orderTimes" => $orderTimes,
				"totals" => $orderTotals,
				"newSessions" => $newSessions,
				"newOrders" => $newOrders
			]
		];
	}

	public function delete($id)
	{
		$params = [
			"deleted_at" => date("Y-m-d H:i:s")
		];

		$order = $this->M_Order->update($id, array_merge($params, [
			"cashier_id" => $this->user_data["user_id"]
		]));
		$orderDetail = $this->M_Order_Detail->updateByOrderId($id, $params);

		$res = [
			"success" => true,
			"message" => "Data has been deleted!"
		];

		return $this->output->set_output(json_encode($res));
	}
}
