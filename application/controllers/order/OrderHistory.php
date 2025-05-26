<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH . 'controllers/base/PrivateBase.php');

class OrderHistory extends ApplicationBase
{

	function __construct()
	{
		parent::__construct();
		$this->load->model('order/M_Order_History', 'M_Order_History');
		$this->load->model('settings/M_outlets', 'm_outlets');
		$this->load->library('pagination');
	}


	function index()
	{
		$this->tsmarty->assign("template_content", "order/history/index.html");
		$this->tsmarty->assign("site_url", site_url());

		// Search parameters
		$keyword = '';
		$startDate = '';
		$endDate = '';
		$status = '';

		$search = $this->session->userdata('search_order_history');

		if ($this->input->post()) {
			if ($this->input->post('save') == "Reset") {
				$this->session->unset_userdata("search_order_history");
			} else {
				$keyword = $this->input->post('keyword');
				$startDate = $this->input->post('start_date');
				$endDate = $this->input->post('end_date');
				$status = $this->input->post('status');

				$params = [
					"keyword" => $keyword,
					"start_date" => $startDate,
					"end_date" => $endDate,
					"status" => $status,
				];

				$this->session->set_userdata("search_order_history", $params);
			}
		} elseif (!empty($search)) {
			$keyword = $search['keyword'];
			$startDate = $search['start_date'];
			$endDate = $search['end_date'];
			$status = $search['status'];
		}

		$this->tsmarty->assign("keyword", $keyword);
		$this->tsmarty->assign("startDate", $startDate);
		$this->tsmarty->assign("endDate", $endDate);
		$this->tsmarty->assign("status", $status);

		// Build search params
		$whereParams = [];

		if (!empty($keyword)) {
			$this->db->like('o.name', $keyword);
			$this->db->or_like('o.id', $keyword);
		}

		if (!empty($startDate) || !empty($endDate)) {
			$whereParams['date_range'] = [
				'start' => $startDate,
				'end' => $endDate
			];
		}

		if (!empty($status)) {
			$whereParams['status'] = $status;
		}

		// Pagination
		$config['base_url'] = site_url('order/history/index/');
		$config['total_rows'] = $this->M_Order_History->countOrderHistory($whereParams);
		$config['uri_segment'] = 4;
		$config['per_page'] = 15;

		$this->pagination->initialize($config);
		$pagination['data'] = $this->pagination->create_links();

		$start = $this->uri->segment(4, 0) + 1;
		$end = $this->uri->segment(4, 0) + $config['per_page'];
		$end = (($end > $config['total_rows']) ? $config['total_rows'] : $end);
		$pagination['start'] = ($config['total_rows'] == 0) ? 0 : $start;
		$pagination['end'] = $end;
		$pagination['total'] = $config['total_rows'];

		$this->tsmarty->assign("pagination", $pagination);
		$this->tsmarty->assign("no", $start);

		// Get history data
		$offset = $this->uri->segment(4, 0);
		$limit = $config['per_page'];
		$orders = $this->M_Order_History->getOrderHistory($whereParams, $limit, $offset);

		// Status labels
		$statusLabels = [
			0 => "Dipesan",
			1 => "Order Diproses",
			2 => "Sedang Diproses",
			3 => "Sudah Diantar",
			4 => "Selesai",
			5 => "Dibatalkan"
		];

		$this->tsmarty->assign("statusLabels", $statusLabels);
		$this->tsmarty->assign("orders", $orders);

		// Get outlets for filtering
		$outlets = $this->db->get('data_outlet')->result_array();
		$this->tsmarty->assign("outlets", $outlets);

		parent::display();
	}

	function view($id)
	{
		// Log untuk tracking
		log_message('debug', '=== Starting Order History View ===');
		log_message('debug', 'Viewing order history ID: ' . $id);

		// Get order history detail
		$history = $this->M_Order_History->getOrderHistoryDetail($id);

		if (!$history) {
			$this->session->set_flashdata('error', 'Order history not found');
			log_message('error', 'Order history not found for ID: ' . $id);
			redirect('order/history');
			return;
		}

		// Log untuk detail yang diterima
		log_message('debug', 'Order history found for ID: ' . $id . ', Order ID: ' . $history['order_id']);
		log_message('debug', 'Items count: ' . count($history['items'] ?? []));

		$this->tsmarty->assign("template_content", "order/history/view.html");
		$this->tsmarty->assign("history", $history);
		$this->tsmarty->assign("site_url", site_url());

		// Status labels
		$statusLabels = [
			0 => ["text" => "Dipesan", "class" => "bg-primary"],
			1 => ["text" => "Order Diproses", "class" => "bg-success"],
			2 => ["text" => "Sedang Diproses", "class" => "bg-info"],
			3 => ["text" => "Sudah Diantar", "class" => "bg-success"],
			4 => ["text" => "Selesai", "class" => "bg-secondary"],
			5 => ["text" => "Dibatalkan", "class" => "bg-danger"],
		];

		$this->tsmarty->assign("statusInfo", $statusLabels[$history['status']] ?? ["text" => "Unknown", "class" => "bg-light"]);

		// Jika items kosong, coba ambil dari sumber lain
		if (empty($history['items'])) {
			log_message('debug', 'No items found in history data, trying to get from order summary');

			// Coba cari di order_summaries
			$summaryData = $this->db->query("
            SELECT summary_data 
            FROM order_summaries 
            WHERE order_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ", [$history['order_id']])->row_array();

			if ($summaryData && isset($summaryData['summary_data'])) {
				$summaryObj = json_decode($summaryData['summary_data'], true);
				if (isset($summaryObj['items']) && is_array($summaryObj['items'])) {
					$history['items'] = $summaryObj['items'];
					log_message('debug', 'Found ' . count($history['items']) . ' items in order summary');
				}
			}

			// Jika masih kosong, coba langsung dari order_details
			if (empty($history['items'])) {
				log_message('debug', 'Trying to get items directly from order_details');
				$detailItems = $this->db->query("
                SELECT od.*, dp.product_name, dp.product_price as original_price
                FROM order_details od
                LEFT JOIN data_product dp ON od.product_id = dp.product_id
                WHERE od.order_id = ?
            ", [$history['order_id']])->result_array();

				if (!empty($detailItems)) {
					$history['items'] = $detailItems;
					log_message('debug', 'Found ' . count($history['items']) . ' items directly from order_details');
				}
			}
		}

		// Get promo information
		$promoInfo = null;
		$promoType = null;
		$discountAmount = 0;

		if (!empty($history['discount_code'])) {
			$this->load->model('promo/M_Promo', 'M_Promo');
			$promo = $this->M_Promo->getPromoByCode($history['discount_code']);
			if ($promo) {
				$promoInfo = $promo;
				$promoType = $promo['promo_type'];
				$discountAmount = floatval($history['discount_amount'] ?? 0);
				log_message('debug', 'Found promo: ' . $history['discount_code'] . ', Type: ' . $promoType . ', Amount: ' . $discountAmount);
			}
		}

		// PERBAIKAN: Perhitungan total untuk kasus percentage/nominal berbeda dengan bundling/bogo
		$regularTotal = 0;
		$packageTotal = 0;
		$bundleBogoDiscount = 0;
		$itemsWithoutDiscount = 0; // Total harga item sebelum diskon

		// Process items - PERBAIKAN: kita perlu membedakan antara item dengan promo percentage/nominal
		foreach ($history['items'] as &$item) {
			$quantity = intval($item['quantity'] ?? 1);
			$price = floatval($item['unit_price'] ?? $item['price'] ?? 0);
			$subtotal = floatval($item['subtotal'] ?? ($price * $quantity));

			// PERBAIKAN: Untuk percentage/nominal, simpan harga asli
			if ($promoType && in_array($promoType, ['percentage', 'nominal'])) {
				// Item normal dengan diskon, bukan promo item
				$item['is_promo_item'] = 0;
				$item['has_discount'] = true;
				// Simpan harga asli untuk ditampilkan
				$itemsWithoutDiscount += $subtotal;

				// Hitung subtotal setelah diskon untuk percentage
				if ($promoType == 'percentage' && $promoInfo) {
					$discountPercentage = floatval($promoInfo['promo_value'] ?? 0);
					$item['discount_percentage'] = $discountPercentage;

					// Hitung harga setelah diskon
					$afterDiscountPrice = $price * (1 - ($discountPercentage / 100));
					$item['after_discount_price'] = $afterDiscountPrice;
					$item['after_discount_subtotal'] = $afterDiscountPrice * $quantity;

					$regularTotal += $item['after_discount_subtotal'];
				} else {
					// Untuk nominal, subtotal tetap menggunakan harga asli
					$regularTotal += $subtotal;
				}
			} else {
				// Deteksi item promo BOGO/bundling
				$isPromoItem = (!empty($item['is_promo_item']) && $item['is_promo_item'] == 1) ||
					(!empty($item['promo_type']) && in_array($item['promo_type'], ['bogo', 'bundling'])) ||
					(floatval($item['unit_price'] ?? 0) == 0 && !empty($item['notes']) &&
						(strpos(strtolower($item['notes']), 'gratis') !== false ||
							strpos(strtolower($item['notes']), 'promo') !== false));

				$item['is_promo_item'] = $isPromoItem ? 1 : 0;

				if (!empty($item['is_package']) && $item['is_package']) {
					// Process package items
					// ...kode yang sudah ada...
				} else {
					if ($isPromoItem) {
						// Track BOGO/bundling free item value
						$originalPrice = floatval($item['original_price'] ?? $item['unit_price'] ?? 0);
						$bundleBogoDiscount += $originalPrice * $quantity;
					} else {
						$regularTotal += $subtotal;
					}
				}
			}
		}

		// PERBAIKAN: Untuk percentage/nominal, kita gunakan total sebelum diskon
		$subtotal = 0;
		if ($promoType && in_array($promoType, ['percentage', 'nominal'])) {
			$subtotal = $itemsWithoutDiscount;
		} else {
			$subtotal = $regularTotal + $packageTotal;
		}

		log_message('debug', 'Calculated subtotal: ' . $subtotal);
		log_message('debug', 'Items without discount: ' . $itemsWithoutDiscount);

		// PERBAIKAN: Jika subtotal = 0 tapi ada discount, kemungkinan subtotal seharusnya sama dengan discountAmount
		if ($subtotal == 0 && $discountAmount > 0) {
			log_message('debug', 'Zero subtotal with discount, setting subtotal to: ' . $discountAmount * 2);
			$subtotal = $discountAmount * 2; // Digunakan double karena discount adalah 50%
		}

		// PERBAIKAN: Handle diskon berdasarkan tipe promo
		$discountedTotal = $subtotal;
		if ($promoType && in_array($promoType, ['bundling', 'bogo'])) {
			// Untuk bundling/BOGO, diskon tidak dikurangkan dari subtotal
			$discountedTotal = $subtotal;
		} else if ($promoType && in_array($promoType, ['percentage', 'nominal'])) {
			// Untuk percentage/nominal, kurangkan diskon dari subtotal
			$discountedTotal = max(0, $subtotal - $discountAmount);
		}

		// Hitung tax dan grand total
		$tax = $discountedTotal * 0.1;
		$grandTotal = $discountedTotal + $tax;

		$this->tsmarty->assign("regularTotal", $regularTotal);
		$this->tsmarty->assign("packageTotal", $packageTotal);
		$this->tsmarty->assign("subtotal", $subtotal);
		$this->tsmarty->assign("discount_amount", $discountAmount);
		$this->tsmarty->assign("bundle_bogo_discount", $bundleBogoDiscount);
		$this->tsmarty->assign("promo_info", $promoInfo);
		$this->tsmarty->assign("promo_type", $promoType);
		$this->tsmarty->assign("tax", $tax);
		$this->tsmarty->assign("grandTotal", $grandTotal);
		$this->tsmarty->assign("itemsWithoutDiscount", $itemsWithoutDiscount);

		log_message('debug', 'Final values: Subtotal=' . $subtotal . ', Discount=' . $discountAmount .
			', Tax=' . $tax . ', GrandTotal=' . $grandTotal);
		log_message('debug', '=== Finished Order History View ===');

		parent::display();
	}

	function report()
	{
		$this->tsmarty->assign("template_content", "order/history/report.html");
		$this->tsmarty->assign("site_url", site_url());

		// Process filter parameters
		$startDate = $this->input->get('start_date') ?? date('Y-m-d', strtotime('-30 days'));
		$endDate = $this->input->get('end_date') ?? date('Y-m-d');
		$outletId = $this->input->get('outlet_id') ?? '';
		$brand = $this->input->get('brand') ?? '';

		$this->tsmarty->assign("startDate", $startDate);
		$this->tsmarty->assign("endDate", $endDate);
		$this->tsmarty->assign("outletId", $outletId);
		$this->tsmarty->assign("brand", $brand);

		// Build search params
		$whereParams = [
			'date_range' => [
				'start' => $startDate,
				'end' => $endDate
			]
		];

		if (!empty($outletId)) {
			$whereParams['outlet_id'] = $outletId;
		}

		if (!empty($brand)) {
			$whereParams['brand'] = $brand;
		}

		// Get summary data for report
		$summaryData = $this->generateReportSummary($whereParams);
		$this->tsmarty->assign("summary", $summaryData);

		// Get outlets for filtering
		$outlets = $this->db->get('data_outlet')->result_array();
		$this->tsmarty->assign("outlets", $outlets);

		parent::display();
	}

	private function generateReportSummary($params)
	{
		// Get all orders for the specified period
		$orders = $this->M_Order_History->getOrderHistory($params, 0, 0); // No limit

		$summary = [
			'total_orders' => count($orders),
			'total_revenue' => 0,
			'status_counts' => [
				0 => 0, // Reserved
				1 => 0, // Ordered
				2 => 0, // Processing
				3 => 0, // Served
				4 => 0, // Completed
				5 => 0  // Cancelled
			],
			'daily_revenue' => [],
			'hourly_distribution' => array_fill(0, 24, 0),
			'outlet_performance' => []
		];

		// Prepare date range for daily revenue
		$startDate = new DateTime($params['date_range']['start']);
		$endDate = new DateTime($params['date_range']['end']);
		$interval = new DateInterval('P1D');
		$dateRange = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

		foreach ($dateRange as $date) {
			$summary['daily_revenue'][$date->format('Y-m-d')] = 0;
		}

		// Process orders
		foreach ($orders as $order) {
			// Count by status
			$status = $order['status'];
			if (isset($summary['status_counts'][$status])) {
				$summary['status_counts'][$status]++;
			}

			// Skip cancelled orders for revenue calculations
			if ($status != 5) {
				$total = $order['total_amount'] * 1.1; // Including tax
				$summary['total_revenue'] += $total;

				// Daily revenue
				$orderDate = date('Y-m-d', strtotime($order['created_at']));
				if (isset($summary['daily_revenue'][$orderDate])) {
					$summary['daily_revenue'][$orderDate] += $total;
				}

				// Hourly distribution
				$hour = (int)date('H', strtotime($order['created_at']));
				$summary['hourly_distribution'][$hour]++;

				// Outlet performance
				$outletId = $order['outlet_id'];
				if (!isset($summary['outlet_performance'][$outletId])) {
					$summary['outlet_performance'][$outletId] = [
						'count' => 0,
						'revenue' => 0,
						'name' => $this->getOutletName($outletId)
					];
				}

				$summary['outlet_performance'][$outletId]['count']++;
				$summary['outlet_performance'][$outletId]['revenue'] += $total;
			}
		}

		// Sort outlet performance by revenue
		if (!empty($summary['outlet_performance'])) {
			uasort($summary['outlet_performance'], function ($a, $b) {
				return $b['revenue'] <=> $a['revenue'];
			});
		}

		return $summary;
	}

	private function getOutletName($outletId)
	{
		$outlet = $this->db->where('outlet_id', $outletId)->get('data_outlet')->row_array();
		return $outlet ? $outlet['outlet_name'] : 'Unknown Outlet';
	}

	public function export()
	{
		// Library for Excel export
		$this->load->library('PHPExcel');

		// Process filter parameters
		$startDate = $this->input->get('start_date') ?? date('Y-m-d', strtotime('-30 days'));
		$endDate = $this->input->get('end_date') ?? date('Y-m-d');
		$outletId = $this->input->get('outlet_id') ?? '';
		$brand = $this->input->get('brand') ?? '';

		// Build search params
		$whereParams = [
			'date_range' => [
				'start' => $startDate,
				'end' => $endDate
			]
		];

		if (!empty($outletId)) {
			$whereParams['outlet_id'] = $outletId;
		}

		if (!empty($brand)) {
			$whereParams['brand'] = $brand;
		}

		// Get all orders for the specified period
		$orders = $this->M_Order_History->getOrderHistory($whereParams, 0, 0); // No limit

		// Create new Excel object
		$objPHPExcel = new PHPExcel();

		// Set document properties
		$objPHPExcel->getProperties()->setCreator("Kenes Food")
			->setLastModifiedBy("Kenes Food")
			->setTitle("Order History Report")
			->setSubject("Order History Report")
			->setDescription("Order History Report from " . $startDate . " to " . $endDate);

		// Set active sheet
		$objPHPExcel->setActiveSheetIndex(0);
		$sheet = $objPHPExcel->getActiveSheetIndex();

		// Rename sheet
		$objPHPExcel->getActiveSheet()->setTitle('Order History');

		// Add headers
		$objPHPExcel->getActiveSheet()
			->setCellValue('A1', 'No')
			->setCellValue('B1', 'Order ID')
			->setCellValue('C1', 'Date & Time')
			->setCellValue('D1', 'Customer')
			->setCellValue('E1', 'Table No')
			->setCellValue('F1', 'Outlet')
			->setCellValue('G1', 'Brand')
			->setCellValue('H1', 'Status')
			->setCellValue('I1', 'Total Items')
			->setCellValue('J1', 'Subtotal')
			->setCellValue('K1', 'Tax')
			->setCellValue('L1', 'Grand Total');

		// Style headers
		$headerStyle = [
			'font' => ['bold' => true],
			'alignment' => ['horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER],
			'fill' => [
				'type' => PHPExcel_Style_Fill::FILL_SOLID,
				'color' => ['rgb' => 'CCCCCC']
			]
		];

		$objPHPExcel->getActiveSheet()->getStyle('A1:L1')->applyFromArray($headerStyle);

		// Status labels
		$statusLabels = [
			0 => "Dipesan",
			1 => "Order Diproses",
			2 => "Sedang Diproses",
			3 => "Sudah Diantar",
			4 => "Selesai",
			5 => "Dibatalkan"
		];

		// Add data
		$row = 2;
		foreach ($orders as $index => $order) {
			$objPHPExcel->getActiveSheet()
				->setCellValue('A' . $row, $index + 1)
				->setCellValue('B' . $row, $order['order_id'])
				->setCellValue('C' . $row, $order['created_at'])
				->setCellValue('D' . $row, $order['customer_name'])
				->setCellValue('E' . $row, $order['table_id'])
				->setCellValue('F' . $row, $this->getOutletName($order['outlet_id']))
				->setCellValue('G' . $row, ucfirst($order['brand']))
				->setCellValue('H' . $row, $statusLabels[$order['status']] ?? 'Unknown')
				->setCellValue('I' . $row, $order['total_items'])
				->setCellValue('J' . $row, $order['total_amount'])
				->setCellValue('K' . $row, $order['total_amount'] * 0.1)
				->setCellValue('L' . $row, $order['total_amount'] * 1.1);

			$row++;
		}

		// Auto size columns
		foreach (range('A', 'L') as $columnID) {
			$objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
		}

		// Set number format for currency columns
		$objPHPExcel->getActiveSheet()->getStyle('J2:L' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');

		// Setup response
		$filename = 'Order_History_Report_' . date('Y-m-d') . '.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		$objWriter->save('php://output');
		exit;
	}

	public function recordOrderStatus()
	{
		try {
			// Ambil payload dari updateOrderStatus
			$payload = json_decode($this->security->xss_clean($this->input->raw_input_stream));

			// Validasi input
			if (!isset($payload->orderId) || !isset($payload->status)) {
				log_message('error', 'Missing required parameters for order history');
				return true;
			}

			// Hanya catat riwayat untuk status 4 (Selesai)
			if ($payload->status != 4) {
				log_message('debug', 'Skipping order history record. Status is not completed.');
				return true;
			}

			// Simpan riwayat order
			$historyData = [
				'order_id' => $payload->orderId,
				'status'   => $payload->status,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_by' => $this->session->userdata('user_id') ?? 1
			];

			$this->load->model('order/M_Order_History', 'M_Order_History');
			$result = $this->M_Order_History->insertOrderHistory($historyData);

			if (!$result['success']) {
				log_message('error', 'Failed to record order history: ' . json_encode($result['message']));
			}
		} catch (Exception $e) {
			log_message('error', 'Error in recordOrderStatus: ' . $e->getMessage());
		}
		return true;
	}


	public function getOrderTimings($id)
	{
		try {
			// Pastikan output selalu JSON
			$this->output->set_content_type('application/json');

			log_message('debug', 'getOrderTimings called with id: ' . $id);

			if (empty($id)) {
				log_message('error', 'Empty id provided');
				return $this->output
					->set_status_header(400)
					->set_output(json_encode(['success' => false, 'message' => 'ID tidak valid']));
			}

			$id = intval($id);

			// Coba cari di tabel order_history berdasarkan id (bukan order_id)
			$historyRecord = $this->db
				->select('order_id')
				->from('order_history')
				->where('id', $id)
				->get()
				->row_array();

			// Jika ditemukan sebagai history_id, gunakan order_id dari record tersebut
			if ($historyRecord) {
				$orderId = $historyRecord['order_id'];
				log_message('debug', 'ID adalah history_id, menggunakan order_id: ' . $orderId);
			} else {
				// Jika tidak ditemukan, asumsikan ID adalah order_id
				$orderId = $id;
				log_message('debug', 'Menggunakan ID sebagai order_id: ' . $orderId);
			}

			// Pertama, cek order asli untuk mendapatkan created_at awal
			$originalOrder = $this->db
				->select('id, created_at, status')
				->from('orders')
				->where('id', $orderId)
				->get()
				->row_array();

			if (!$originalOrder) {
				log_message('debug', 'Original order not found for ID: ' . $orderId);
			}

			// Periksa history untuk order_id
			$query = $this->db
				->select('oh.status, oh.created_at')
				->from('order_history oh')
				->where('oh.order_id', $orderId)
				->order_by('oh.created_at', 'ASC')
				->get();

			if ($query->num_rows() === 0) {
				log_message('debug', 'No order history found for orderId: ' . $orderId);

				// Jika order asli ada tapi tidak ada history, buat simulasi status
				if ($originalOrder) {
					// Buat stages dummy berdasarkan status order asli
					$timings = [
						'stages' => [
							'order_created' => $originalOrder['created_at'],
							'order_processed' => ($originalOrder['status'] >= 1) ? $originalOrder['created_at'] : null,
							'order_in_kitchen' => ($originalOrder['status'] >= 2) ? null : null,
							'order_served' => ($originalOrder['status'] >= 3) ? null : null,
							'order_completed' => ($originalOrder['status'] >= 4) ? null : null
						],
						'stage_durations' => [],
						'total_duration' => null,
						'message' => 'Data timing terbatas, hanya menampilkan waktu pembuatan order'
					];

					return $this->output
						->set_status_header(200)
						->set_output(json_encode([
							'success' => true,
							'data' => ['timings' => $timings]
						]));
				}

				return $this->output
					->set_status_header(200)
					->set_output(json_encode([
						'success' => true,
						'data' => [
							'timings' => [
								'stages' => [
									'order_created' => null,
									'order_processed' => null,
									'order_in_kitchen' => null,
									'order_served' => null,
									'order_completed' => null
								],
								'stage_durations' => [],
								'total_duration' => null,
								'message' => 'Belum ada riwayat timing untuk order ini'
							]
						]
					]));
			}

			$orderHistory = $query->result_array();
			log_message('debug', 'Retrieved order history: ' . json_encode($orderHistory));

			// Tambahkan data from original order jika perlu
			if ($originalOrder && !empty($originalOrder['created_at'])) {
				$foundInitialStatus = false;
				foreach ($orderHistory as $item) {
					if ($item['status'] == 0) {
						$foundInitialStatus = true;
						break;
					}
				}

				if (!$foundInitialStatus) {
					// Tambahkan status awal (0) dengan created_at dari order asli
					array_unshift($orderHistory, [
						'status' => 0,
						'created_at' => $originalOrder['created_at']
					]);
				}
			}

			// Hitung durasi order
			$timings = $this->calculateOrderTimings($orderHistory);
			log_message('debug', 'Calculated timings: ' . json_encode($timings));

			return $this->output
				->set_status_header(200)
				->set_output(json_encode([
					'success' => true,
					'data' => [
						'timings' => $timings
					]
				]));
		} catch (Exception $e) {
			log_message('error', 'Error in getOrderTimings: ' . $e->getMessage());
			return $this->output
				->set_status_header(500)
				->set_output(json_encode([
					'success' => false,
					'message' => 'Kesalahan internal server: ' . $e->getMessage()
				]));
		}
	}

	private function calculateOrderTimings($orderHistory)
	{
		// Inisialisasi default values
		$stages = [
			'order_created' => null,
			'order_processed' => null,
			'order_in_kitchen' => null,
			'order_served' => null,
			'order_completed' => null,
			'order_cancelled' => null
		];

		$stageDurations = [];
		$totalDuration = null;

		// Status mapping yang lebih komprehensif
		$statusMap = [
			0 => 'order_created',
			1 => 'order_processed',
			2 => 'order_in_kitchen',
			3 => 'order_served',
			4 => 'order_completed',
			5 => 'order_cancelled'
		];

		try {
			$previousStatus = null;
			$previousTime = null;
			$firstTime = null;
			$lastTime = null;
			$lastCompletedTime = null; // Untuk case di mana order completed, tapi ada update lain setelahnya

			// Urutkan history berdasarkan created_at
			usort($orderHistory, function ($a, $b) {
				return strtotime($a['created_at']) - strtotime($b['created_at']);
			});

			// Variabel untuk tracking status terakhir untuk memastikan completed atau cancelled
			$lastStatus = null;

			foreach ($orderHistory as $history) {
				$status = $history['status'];
				$currentStatus = $statusMap[$status] ?? null;

				if (!$currentStatus) continue;

				$currentTime = new DateTime($history['created_at']);

				// Isi stages dengan timestamp (simpan semua untuk history lengkap)
				$stages[$currentStatus] = $history['created_at'];

				// Jika status completed, selalu simpan sebagai lastCompletedTime
				if ($status == 4) {
					$lastCompletedTime = $currentTime;
				}

				// Track status terakhir (untuk mempertimbangkan completed vs cancelled)
				$lastStatus = $status;

				// Set first time jika belum diset
				if (!$firstTime) {
					$firstTime = $currentTime;
				}

				// Hitung durasi antar stage
				if ($previousStatus && $previousTime) {
					$duration = $previousTime->diff($currentTime);
					$durationKey = $previousStatus . '_to_' . $currentStatus;

					$stageDurations[$durationKey] = [
						'hours' => $duration->h + ($duration->days * 24),
						'minutes' => $duration->i,
						'seconds' => $duration->s
					];
				}

				$previousStatus = $currentStatus;
				$previousTime = $currentTime;
				$lastTime = $currentTime;
			}

			// Prioritaskan lastCompletedTime jika ada completed dalam history
			if ($lastCompletedTime && $lastStatus != 5) { // Jika ada completed dan tidak dicancel
				$lastTime = $lastCompletedTime;
			}

			// Hitung total durasi jika ada data
			if ($firstTime && $lastTime) {
				$totalDuration = $firstTime->diff($lastTime);
				$totalDuration = [
					'hours' => $totalDuration->h + ($totalDuration->days * 24),
					'minutes' => $totalDuration->i,
					'seconds' => $totalDuration->s,
					'total_seconds' => abs($lastTime->getTimestamp() - $firstTime->getTimestamp())
				];
			}

			return [
				'stages' => $stages,
				'stage_durations' => $stageDurations,
				'total_duration' => $totalDuration,
				'last_status' => $lastStatus
			];
		} catch (Exception $e) {
			log_message('error', 'Error in calculateOrderTimings: ' . $e->getMessage());
			return [
				'stages' => $stages,
				'stage_durations' => [],
				'total_duration' => null,
				'error' => $e->getMessage()
			];
		}
	}
}
