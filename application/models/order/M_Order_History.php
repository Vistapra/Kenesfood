<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class M_Order_History extends Ci_model
{
	private $table = "order_history";

	public function getOrderHistory($params = [], $limit = 50, $offset = 0)
	{
		$this->db->select('oh.*, o.outlet_id, o.table_id, o.brand, o.name as customer_name, 
        o.total_amount, o.discount_code, o.discount_amount');
		$this->db->from($this->table . ' as oh');
		$this->db->join('orders as o', 'oh.order_id = o.id', 'left');

		// PERBAIKAN: Pastikan hanya menampilkan order dengan status 4 (Selesai)
		// Prioritaskan status dari history, jika tidak ada gunakan status dari order
		$this->db->where('(oh.status = 4 OR (oh.status IS NULL AND o.status = 4))');

		// Apply filters if not empty but TIDAK MENGUBAH filter status yang sudah diterapkan
		if (!empty($params)) {
			foreach ($params as $key => $value) {
				// Skip filter status untuk memastikan hanya status=4 yang ditampilkan
				if ($key === 'status') {
					continue;
				}

				if ($key === 'date_range') {
					if (!empty($value['start'])) {
						$this->db->where('oh.created_at >=', $value['start'] . ' 00:00:00');
					}
					if (!empty($value['end'])) {
						$this->db->where('oh.created_at <=', $value['end'] . ' 23:59:59');
					}
				} else {
					$this->db->where('oh.' . $key, $value);
				}
			}
		}

		// Mengelompokkan berdasarkan order_id untuk hanya menampilkan satu entri per order
		$this->db->group_by('oh.order_id');

		// Default ordering
		$this->db->order_by('oh.created_at', 'DESC');

		// Pagination if limit is provided
		if ($limit > 0) {
			$this->db->limit($limit, $offset);
		}

		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			return $query->result_array();
		}

		return [];
	}

	public function countOrderHistory($params = [])
	{
		$this->db->select('COUNT(DISTINCT oh.order_id) as total'); // Menghitung order unik
		$this->db->from($this->table . ' as oh');
		$this->db->join('orders as o', 'oh.order_id = o.id', 'left');

		// PERBAIKAN: Pastikan hanya menghitung order dengan status 4 (Selesai)
		$this->db->where('(oh.status = 4 OR (oh.status IS NULL AND o.status = 4))');

		if (!empty($params)) {
			foreach ($params as $key => $value) {
				// Skip filter status untuk memastikan hanya status=4 yang dihitung
				if ($key === 'status') {
					continue;
				}

				if ($key === 'date_range') {
					if (!empty($value['start'])) {
						$this->db->where('oh.created_at >=', $value['start'] . ' 00:00:00');
					}
					if (!empty($value['end'])) {
						$this->db->where('oh.created_at <=', $value['end'] . ' 23:59:59');
					}
				} else {
					$this->db->where('oh.' . $key, $value);
				}
			}
		}

		$result = $this->db->get()->row();
		return $result->total;
	}

	public function insertOrderHistory($data)
	{
		$this->db->insert($this->table, $data);

		if ($this->db->affected_rows() > 0) {
			return [
				"success" => true,
				"id" => $this->db->insert_id()
			];
		}

		return [
			"success" => false,
			"message" => $this->db->error()
		];
	}

	public function getOrderHistoryDetail($historyId)
	{
		// PERBAIKAN: Log untuk debugging
		log_message('debug', 'Fetching order history detail for ID: ' . $historyId);

		// Select with additional discount fields
		$this->db->select('oh.*, o.outlet_id, o.table_id, o.brand, o.name as customer_name, 
        o.total_amount, o.total_items, o.discount_code, o.discount_amount, 
        o.status as current_status, o.updated_at, o.completed_at, o.id as order_id');
		$this->db->from($this->table . ' as oh');
		$this->db->join('orders as o', 'oh.order_id = o.id', 'left');
		$this->db->where('oh.id', $historyId);
		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			$history = $query->row_array();

			// Log order ID untuk debugging
			log_message('debug', 'Found order history with order_id: ' . $history['order_id']);

			// PERBAIKAN: Pastikan order_id selalu ada untuk query berikutnya
			if (empty($history['order_id'])) {
				log_message('error', 'Order ID is missing in history record ID: ' . $historyId);
				return null;
			}

			// Get detailed items with promo awareness - PERBAIKAN: Pass current order_id
			$history['items'] = $this->getOrderItemsWithPromo($history['order_id']);
			log_message('debug', 'Retrieved ' . count($history['items']) . ' items for order');

			// Get outlet information
			$this->db->select('outlet_name, outlet_address, outlet_phone');
			$this->db->from('data_outlet');
			$this->db->where('outlet_id', $history['outlet_id']);
			$outletQuery = $this->db->get();

			if ($outletQuery->num_rows() > 0) {
				$history['outlet'] = $outletQuery->row_array();
			}

			// Get promo information if available
			if (!empty($history['discount_code'])) {
				$this->db->select('*');
				$this->db->from('promos');
				$this->db->where('promo_code', $history['discount_code']);
				$promoQuery = $this->db->get();

				if ($promoQuery->num_rows() > 0) {
					$history['promo'] = $promoQuery->row_array();
					log_message('debug', 'Found promo info for code: ' . $history['discount_code']);
				}
			}

			return $history;
		}

		log_message('error', 'No order history found for ID: ' . $historyId);
		return null;
	}

	public function getOrderItemsWithPromo($orderId)
	{
		// Log untuk debugging
		log_message('debug', 'Fetching order items with promo for order ID: ' . $orderId);

		// Get order info first to check discount_code
		$order = $this->db->select('id, discount_code, discount_amount')
			->from('orders')
			->where('id', $orderId)
			->get()
			->row_array();

		$discountCode = $order['discount_code'] ?? null;
		$discountAmount = $order['discount_amount'] ?? 0;

		// Get promo type if discount_code exists
		$promoType = null;
		if ($discountCode) {
			if (!isset($this->M_Promo)) {
				$this->load->model('promo/M_Promo');
			}
			$promo = $this->M_Promo->getPromoByCode($discountCode);
			if ($promo) {
				$promoType = $promo['promo_type'];
				log_message('debug', 'Found promo: ' . $discountCode . ', Type: ' . $promoType);
			}
		}

		// Gunakan query yang lebih lengkap untuk mendapatkan semua data
		$query = $this->db->query("
        SELECT od.*, dp.product_name, dp.product_pict, dp.product_price as original_price
        FROM order_details od
        LEFT JOIN data_product dp ON od.product_id = dp.product_id
        WHERE od.order_id = ? AND od.deleted_at IS NULL
    ", [$orderId]);

		log_message('debug', 'Retrieved ' . $query->num_rows() . ' raw order details');

		$allItems = [];
		$packageHeaderIds = [];
		$packageItemsMap = [];

		if ($query->num_rows() > 0) {
			$allItems = $query->result_array();

			// Add order discount info to all items
			foreach ($allItems as &$item) {
				$item['discount_code'] = $discountCode;
				$item['order_discount_amount'] = $discountAmount;
				$item['promo_type'] = $promoType;
			}

			// First pass: identify package headers and items
			foreach ($allItems as $item) {
				// If item has parent_id, add to packageItemsMap
				if (!empty($item['parent_id'])) {
					if (!isset($packageItemsMap[$item['parent_id']])) {
						$packageItemsMap[$item['parent_id']] = [];
					}
					$packageItemsMap[$item['parent_id']][] = $item;
				}
				// If item has package_id, add to packageHeaderIds
				if (!empty($item['package_id'])) {
					$packageHeaderIds[] = $item['id'];
				}
			}
		} else {
			// Jika tidak ada item, coba ambil dari order_summaries untuk mendapatkan detail
			log_message('debug', 'No items found in order_details, trying order_summaries');
			$summaryQuery = $this->db->query("
            SELECT summary_data
            FROM order_summaries
            WHERE order_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ", [$orderId]);

			if ($summaryQuery->num_rows() > 0) {
				$summary = $summaryQuery->row_array();
				if (isset($summary['summary_data'])) {
					$summaryData = json_decode($summary['summary_data'], true);
					if (isset($summaryData['items']) && is_array($summaryData['items'])) {
						// Add discount info to each item
						foreach ($summaryData['items'] as &$item) {
							$item['discount_code'] = $discountCode;
							$item['order_discount_amount'] = $discountAmount;
							$item['promo_type'] = $promoType;
						}
						log_message('debug', 'Found ' . count($summaryData['items']) . ' items in order_summaries');
						return $summaryData['items'];
					}
				}
			}
		}

		// Debug info tentang package info
		log_message('debug', 'Found ' . count($packageHeaderIds) . ' package headers');
		log_message('debug', 'Found ' . count($packageItemsMap) . ' package item groups');

		// Second pass: organize items into proper structure
		$processedItems = [];
		foreach ($allItems as $item) {
			// Skip package items, they will be added to their packages
			if (!empty($item['parent_id'])) {
				continue;
			}

			// Check if this is a package
			if (in_array($item['id'], $packageHeaderIds)) {
				$item['is_package'] = true;

				// Add package items if any
				if (isset($packageItemsMap[$item['id']])) {
					// Process each package item for promo information
					$packageItems = [];
					foreach ($packageItemsMap[$item['id']] as $packageItem) {
						// Enhanced promo detection for package items
						$isPromoItem = $this->detectPromoItem($packageItem);
						$packageItem['is_promo_item'] = $isPromoItem ? 1 : 0;

						// If it's a promo item with zero price, set original price
						if ($isPromoItem && floatval($packageItem['unit_price']) == 0) {
							$packageItem['original_price'] = floatval($packageItem['original_price']);
						}

						$packageItems[] = $packageItem;
					}

					$item['items'] = $packageItems;
				} else {
					$item['items'] = [];
				}
			} else {
				$item['is_package'] = false;
			}

			// PERBAIKAN: Untuk tipe promo percentage/nominal, jangan tandai sebagai promo item
			$isPromoItem = false;
			if ($promoType && in_array($promoType, ['bundling', 'bogo'])) {
				$isPromoItem = $this->detectPromoItem($item);
			} else {
				// Untuk percentage/nominal, item adalah reguler (bukan promo item)
				$isPromoItem = false;
			}

			$item['is_promo_item'] = $isPromoItem ? 1 : 0;

			// For discounted items that are not promo items
			if (!$isPromoItem && $promoType && in_array($promoType, ['percentage', 'nominal'])) {
				// Add discount info to item
				$item['has_discount'] = true;
				// Store original price for display
				$item['before_discount_price'] = floatval($item['unit_price']);

				// For percentage promo
				if ($promoType == 'percentage' && $promo) {
					$discountPercentage = floatval($promo['promo_value'] ?? 0);
					$item['discount_percentage'] = $discountPercentage;
				}
			}

			// If it's a promo item with zero price, set original price
			if ($isPromoItem && floatval($item['unit_price']) == 0) {
				$item['original_price'] = floatval($item['original_price']);
			}

			// Pastikan data unit_price, quantity dan subtotal selalu terisi
			if (!isset($item['unit_price']) || $item['unit_price'] === null) {
				$item['unit_price'] = isset($item['price']) ? $item['price'] : 0;
			}

			if (!isset($item['quantity']) || $item['quantity'] === null) {
				$item['quantity'] = 1;
			}

			if (!isset($item['subtotal']) || $item['subtotal'] === null) {
				$item['subtotal'] = floatval($item['unit_price']) * intval($item['quantity']);
			}

			$processedItems[] = $item;
		}

		log_message('debug', 'Returning ' . count($processedItems) . ' processed items');
		return $processedItems;
	}
	private function detectPromoItem($item)
	{
		// Check for explicit promo flags
		if (isset($item['is_promo_item']) && $item['is_promo_item'] == 1) {
			// PERBAIKAN: Periksa juga discount_code jika ada
			if (isset($item['discount_code'])) {
				// Ambil info promo untuk memastikan tipe promo
				$CI = &get_instance();
				if (!isset($CI->M_Promo)) {
					$CI->load->model('promo/M_Promo');
				}
				$promo = $CI->M_Promo->getPromoByCode($item['discount_code']);

				// Jika promo bukan bundling/bogo, maka ini bukan item promo (hanya item dengan diskon)
				if ($promo && !in_array($promo['promo_type'], ['bundling', 'bogo'])) {
					return false;
				}
			}
			return true;
		}

		// Check if has promo_type field
		if (!empty($item['promo_type']) && in_array($item['promo_type'], ['bogo', 'bundling'])) {
			return true;
		}

		// PERBAIKAN: Periksa juga jika ada discount_code tetapi bukan bundling/bogo
		if (!empty($item['discount_code'])) {
			$CI = &get_instance();
			if (!isset($CI->M_Promo)) {
				$CI->load->model('promo/M_Promo');
			}
			$promo = $CI->M_Promo->getPromoByCode($item['discount_code']);

			// Item dengan diskon percentage/nominal BUKAN item promo
			if ($promo && in_array($promo['promo_type'], ['percentage', 'nominal'])) {
				return false;
			}
		}

		// Check for zero price and promo notes - HANYA jika tidak ada discount_code
		if (floatval($item['unit_price']) == 0 && !empty($item['notes'])) {
			$notes = strtolower($item['notes']);
			if (
				strpos($notes, 'promo') !== false ||
				strpos($notes, 'gratis') !== false ||
				strpos($notes, 'free') !== false
			) {
				// PERBAIKAN: Periksa juga jika ada discount_code
				if (!empty($item['discount_code'])) {
					// Cek jenis promo
					$CI = &get_instance();
					if (!isset($CI->M_Promo)) {
						$CI->load->model('promo/M_Promo');
					}
					$promo = $CI->M_Promo->getPromoByCode($item['discount_code']);

					// Jika percentage/nominal, bukan item promo
					if ($promo && in_array($promo['promo_type'], ['percentage', 'nominal'])) {
						return false;
					}
				}
				return true;
			}
		}

		return false;
	}

	/**
	 * Get order status history for tracking order progression
	 */
	public function getOrderStatusHistory($orderId)
	{
		$this->db->select('*');
		$this->db->from($this->table);
		$this->db->where('order_id', $orderId);
		$this->db->order_by('created_at', 'ASC');
		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			return $query->result_array();
		}

		return [];
	}

	/**
	 * Get a summary of order items with categorization and promo awareness
	 * 
	 * @param int $orderId The order ID to summarize
	 * @return array The summarized order data
	 */
	public function getOrderSummary($orderId)
	{
		// Get the order details
		$this->db->select('o.*, o.discount_code, o.discount_amount');
		$this->db->from('orders as o');
		$this->db->where('o.id', $orderId);
		$orderQuery = $this->db->get();

		if ($orderQuery->num_rows() == 0) {
			return [
				'total_items' => 0,
				'subtotal' => 0,
				'discount' => 0,
				'tax' => 0,
				'total' => 0
			];
		}

		$order = $orderQuery->row_array();

		// Get all items
		$items = $this->getOrderItemsWithPromo($orderId);

		// Initialize totals
		$regularTotal = 0;
		$packageTotal = 0;
		$bundleBogoDiscount = 0;
		$itemCount = 0;

		// Process items
		foreach ($items as $item) {
			$quantity = intval($item['quantity']);
			$itemCount += $quantity;

			// Handle package vs regular items
			if (!empty($item['is_package']) && $item['is_package']) {
				// Skip promo packages from subtotal
				if (empty($item['is_promo_item']) || $item['is_promo_item'] != 1) {
					$packageTotal += floatval($item['subtotal']);
				} else {
					// Track value of promo packages
					$originalPrice = floatval($item['original_price'] ?? $item['unit_price'] ?? 0);
					$bundleBogoDiscount += $originalPrice * $quantity;
				}
			} else {
				// Skip promo items from subtotal
				if (empty($item['is_promo_item']) || $item['is_promo_item'] != 1) {
					$regularTotal += floatval($item['subtotal']);
				} else {
					// Track value of promo items
					$originalPrice = floatval($item['original_price'] ?? $item['unit_price'] ?? 0);
					$bundleBogoDiscount += $originalPrice * $quantity;
				}
			}
		}

		$subtotal = $regularTotal + $packageTotal;

		// Handle promo discount
		$discountAmount = 0;
		$promoType = null;

		if (!empty($order['discount_code'])) {
			$this->db->select('*');
			$this->db->from('promos');
			$this->db->where('promo_code', $order['discount_code']);
			$promoQuery = $this->db->get();

			if ($promoQuery->num_rows() > 0) {
				$promo = $promoQuery->row_array();
				$promoType = $promo['promo_type'];

				if ($promoType == 'bundling' || $promoType == 'bogo') {
					// For bundling/BOGO, discount doesn't reduce subtotal
					$discountAmount = 0;
				} else {
					// For percentage/nominal, use discount_amount
					$discountAmount = floatval($order['discount_amount'] ?? 0);
				}
			} else {
				// If promo not found but discount_amount exists
				$discountAmount = floatval($order['discount_amount'] ?? 0);
			}
		}

		// Calculate total with discount
		$discountedTotal = max(0, $subtotal - $discountAmount);
		$tax = $discountedTotal * 0.1; // 10% tax
		$total = $discountedTotal + $tax;

		return [
			'total_items' => $itemCount,
			'regular_total' => $regularTotal,
			'package_total' => $packageTotal,
			'subtotal' => $subtotal,
			'discount_amount' => $discountAmount,
			'bundle_bogo_discount' => $bundleBogoDiscount,
			'promo_type' => $promoType,
			'tax' => $tax,
			'total' => $total
		];
	}
}
