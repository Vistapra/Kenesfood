<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_Order_Detail extends Ci_model
{
	public $table = "order_details";
	public $primaryKey = "id";

	public function getAll($params)
	{
		// Validasi params sebelum digunakan
		if (!isset($params['where']) || !is_array($params['where'])) {
			log_message('ERROR', 'Invalid params in getAll: ' . json_encode($params));
			return [];
		}

		// Mulai query builder dengan kondisi aman
		$this->db->select('order_details.*, data_product.product_name');
		$this->db->from('order_details');
		$this->db->join('data_product', 'order_details.product_id = data_product.product_id', 'left');

		// Tambahkan where conditions dengan aman
		foreach ($params['where'] as $key => $value) {
			if ($value === NULL) {
				$this->db->where($key . ' IS NULL');
			} else {
				$this->db->where($key, $value);
			}
		}

		try {
			$query = $this->db->get();

			// Periksa apakah query berhasil
			if ($query === FALSE) {
				log_message('ERROR', 'Query failed: ' . $this->db->last_query());
				return [];
			}

			// Kembalikan hasil
			return $query->result_array();
		} catch (Exception $e) {
			log_message('ERROR', 'Error in getAll: ' . $e->getMessage());
			log_message('ERROR', 'Last Query: ' . $this->db->last_query());
			return [];
		}
	}

	public function getAllByOrderId($id)
	{
		$query = $this->db
			->where("order_id", $id)
			->get($this->table);

		return $query->result_array();
	}

	public function update($params, $data)
	{
		$this->db->where($params);
		$query = $this->db->update($this->table, $data);

		return $data;
	}

	public function updateByOrderId($id, $data)
	{
		$this->db->where('order_id', $id);
		$query = $this->db->update($this->table, $data);

		return $data;
	}
	public function insertPackageOrder($cartData)
	{
		try {
			$currentTime = date('Y-m-d H:i:s');

			// Insert package header
			$packageHeader = [
				'order_id' => $cartData['order_id'],
				'product_id' => $cartData['package_id'],
				'quantity' => 1,
				'price' => $cartData['base_price'],
				'created_at' => $currentTime,
				'updated_at' => $currentTime
			];

			$this->db->insert('order_details', $packageHeader);
			$packageDetailId = $this->db->insert_id();

			// Insert package items
			foreach ($cartData['products'] as $product) {
				$productDetail = [
					'order_id' => $cartData['order_id'],
					'parent_id' => $packageDetailId,
					'product_id' => $product['product_id'],
					'quantity' => $product['quantity'],
					'price' => $product['price'],
					'created_at' => $currentTime,
					'updated_at' => $currentTime
				];

				$this->db->insert('order_details', $productDetail);
			}

			return [
				'success' => true,
				'cart_id' => $packageDetailId
			];
		} catch (Exception $e) {
			log_message('error', 'Error inserting package order: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => $e->getMessage()
			];
		}
	}

	/**
	 * Delete package order and its items
	 */
	public function deletePackageOrder($cartItemId)
	{
		try {
			$currentTime = date('Y-m-d H:i:s');

			// Soft delete package header
			$this->db->where('id', $cartItemId)
				->update('order_details', [
					'deleted_at' => $currentTime
				]);

			// Soft delete package items
			$this->db->where('parent_id', $cartItemId)
				->update('order_details', [
					'deleted_at' => $currentTime
				]);

			return true;
		} catch (Exception $e) {
			log_message('error', 'Error deleting package order: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get package order details
	 */
	public function getPackageOrderDetails($cartItemId)
	{
		try {
			// Get package header
			$package = $this->db
				->select('od.*, dp.product_name, dp.product_pict')
				->from('order_details od')
				->join('data_product dp', 'od.product_id = dp.product_id')
				->where([
					'od.id' => $cartItemId,
					'od.deleted_at' => NULL
				])
				->get()
				->row_array();

			if (!$package) {
				return null;
			}

			// Get package items
			$items = $this->db
				->select('od.*, dp.product_name, dp.product_pict')
				->from('order_details od')
				->join('data_product dp', 'od.product_id = dp.product_id')
				->where([
					'od.parent_id' => $cartItemId,
					'od.deleted_at' => NULL
				])
				->get()
				->result_array();

			return [
				'package' => $package,
				'items' => $items
			];
		} catch (Exception $e) {
			log_message('error', 'Error getting package order details: ' . $e->getMessage());
			return null;
		}
	}
}
