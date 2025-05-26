<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_Order extends Ci_model
{
	public $table = "orders";
	public $primaryKey = "id";

	const STATUS_RESERVED = 0;  // Sesi dimulai  
	const STATUS_ORDERED = 1;   // Order sudah dibuat
	const STATUS_PROCESSING = 2; // Order sedang diproses
	const STATUS_SERVED = 3;    // Order sudah diantar
	const STATUS_COMPLETED = 4; // Order selesai
	const STATUS_CANCELLED = 5; // Order dibatalkan

	public function getOrderStatus($params)
	{
		$query = $this->db->select("*")
			->from($this->table)
			->where($params)
			->get();

		if (!($query->num_rows() > 0))
			return [];

		return $query->result_array();
	}

	public function getOne($params)
	{

		$query = $this->db->where($params)->limit(1)->get($this->table);

		return $query->row_array();
	}

	public function getOrderWithDetail($params)
	{
		// Debug info
		log_message('debug', 'Getting order with detail for params: ' . json_encode($params));

		try {
			// Get active order first
			$order = $this->getOne(
				array_merge($params, [
					"deleted_at" => NULL,
					"status" => 1,
				])
			);

			if (!$order) {
				log_message('debug', 'No active order found for params');
				return NULL;
			}

			log_message('debug', 'Found order with ID: ' . $order['id']);

			$this->db->select('order_details.*, data_product.product_name, order_details.quantity, order_details.notes, order_details.unit_price, order_details.subtotal');
			$this->db->from('orders');
			$this->db->join('order_details', 'orders.id = order_details.order_id');
			$this->db->join('data_product', 'order_details.product_id = data_product.product_id');
			$this->db->where([
				'outlet_id' => $params['outlet_id'],
				'brand' => $params['brand'],
				'table_id' => $params['table_id'],
				'orders.deleted_at' => NULL
			]);

			$query = $this->db->get();

			if ($query === FALSE) {
				log_message('error', 'Query execution failed: ' . $this->db->error()['message']);
				return NULL;
			}

			$result = $query->result_array();
			log_message('debug', 'Found ' . count($result) . ' order details');

			// Map 'quantity' to 'qty' for backward compatibility if needed
			$mappedResult = array_map(function ($item) {
				// Add 'qty' field that references 'quantity' for backward compatibility
				$item['qty'] = $item['quantity'];
				// Add 'price' field that references 'unit_price' for backward compatibility
				$item['price'] = $item['unit_price'];
				return $item;
			}, $result);

			return $mappedResult;
		} catch (Exception $e) {
			log_message('error', 'Error in getOrderWithDetail: ' . $e->getMessage());
			return NULL;
		}
	}

	public function getDetailsByOrderId($id)
	{
		try {
			if (empty($id)) {
				log_message('error', 'Empty order ID passed to getDetailsByOrderId');
				return [];
			}

			// Log untuk debugging
			log_message('debug', 'Fetching order details for ID: ' . $id);

			$query = $this
				->db
				->select('od.*, dp.product_name, dp.product_pict')
				->from('order_details od')
				->join(
					"data_product dp",
					"od.product_id = dp.product_id",
					"left"
				)
				->where('od.order_id', $id)
				->where('od.deleted_at IS NULL')
				->get();

			// Log SQL query
			log_message('debug', 'SQL Query: ' . $this->db->last_query());

			if (!($query->num_rows() > 0)) {
				log_message('debug', 'No order details found for order ID: ' . $id);
				return [];
			}

			$result = $query->result_array();
			log_message('debug', 'Found ' . count($result) . ' order details');

			return $result;
		} catch (Exception $e) {
			log_message('error', 'Error in getDetailsByOrderId: ' . $e->getMessage());
			return [];
		}
	}

	public function update($id, $data): array
	{
		$this->db->where($this->primaryKey, $id);
		$query = $this->db->update($this->table, $data);

		// $data = self::detail($id);

		return $data;
	}

	public function updateOrderTimestamps($orderId, $timestamps)
	{
		$this->db->where('id', $orderId);
		return $this->db->update('orders', $timestamps);
	}
}
