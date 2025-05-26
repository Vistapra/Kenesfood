<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_Order extends Ci_model
{
	private $table = "orders";

	const STATUS_RESERVED = 0;  // Sesi dimulai  
	const STATUS_ORDERED = 1;   // Order sudah dibuat
	const STATUS_PROCESSING = 2; // Order sedang diproses
	const STATUS_SERVED = 3;    // Order sudah diantar
	const STATUS_COMPLETED = 4; // Order selesai
	const STATUS_CANCELLED = 5; // Order dibatalkan

	private function updateOrderDetail()
	{
		return "UPDATE
				order_details
			SET
				qty = ?
			WHERE
				order_id = ?
				AND product_id = ?
		";
	}

	private function getProductInCart(): string
	{
		return "SELECT *
			FROM
				order_details AS od
			WHERE
				od.order_id = ?
				AND od.product_id = ?
			LIMIT 1
		";
	}

	public function addToCart($data)
	{
		$query = $this->db->insert('order_details', $data);

		if (!$query)
			return ["success" => false, "message" => $this->db->error()];

		$data = [
			"success" => true,
			"message" => "Data was created",
			"data" => $data
		];

		return $data;
	}

	public function getCart()
	{
		return "SELECT 
			o.outlet_id,
			o.table_id,
			od.product_id,
			od.notes,
			dp.product_name,
			dp.product_pict,
			dp.stock AS product_stock,
			od.qty AS product_count
			FROM
				orders AS o
			INNER JOIN
				order_details as od ON o.id = od.order_id
			INNER JOIN
				data_product AS dp ON od.product_id = dp.product_id
			WHERE
				o.status = 0
				AND o.outlet_id = ?
				AND o.brand = ?
				AND o.table_id = ?
				AND o.deleted_at IS NULL
				AND od.deleted_at IS NULL
			GROUP BY 
				od.product_id
		";
	}

	public function getCartByProduct()
	{
		return "SELECT

			FROM 
				data_orders
			WHERE
				outlet_id = ?
				AND brand = ?
				AND table_id = ?
				AND product_id = ?
		";
	}

	public function getCountCart()
	{
		return "SELECT
        SUM(od.quantity) AS count
        FROM 
            {$this->table} AS o
        INNER JOIN
            order_details AS od ON o.id = od.order_id
        WHERE
            o.outlet_id = ?
            AND o.brand = ?
            AND o.table_id = ?
            AND o.status = 0
            AND od.deleted_at IS NULL
            AND o.deleted_at IS NULL
    ";
	}

	public function getPlacedOrder()
	{
		return "SELECT
			SUM(od.qty) AS count
			FROM 
				{$this->table} AS o
			INNER JOIN
				order_details AS od ON o.id = od.order_id
			WHERE
				o.outlet_id = ?
				AND o.table_id = ?
				AND o.brand = ?
				AND o.status = 1
		";
	}

	public function removeCartItem($data, $count)
	{
		$orders = $this->queryDB('getCartByProduct', $data);

		$deletion = array_slice($orders, 0, $count);
		$deletion = array_map(function ($item) {
			return $item->order_code;
		}, $deletion);

		$this->db->where_in('order_code', $deletion)->delete('data_orders');

		$data = [
			"success" => true,
			"message" => "Data was deleted",
			"data" => $data
		];

		return $data;
	}

	public function updateDoneOrder()
	{
		return "UPDATE {$this->table}
			SET status=1
			WHERE
				outlet_id = ?
				AND table_id = ?
				AND brand = ?
		";
	}

	public function updateOrder($action, $params)
	{
		if (!method_exists($this, $action))
			return [];

		$sql   = $this->$action();
		$query = $this->db->query($sql, $params);

		if (!$query) {
			return [
				"success" => false,
				"message" => $this->db->error(),
			];
		}

		return [
			"success" => true,
			"message" => "Order has been placed!"
		];
	}

	public function getOneByIdentity(): string
	{
		return "SELECT *
			FROM
				{$this->table}
			WHERE
				outlet_id = ?
			AND
				table_id = ?
			AND
				brand = ?
			AND
				deleted_at IS NULL
			LIMIT 1
		";
	}

	public function restoreProductStock($orderId)
	{
		try {
			// Get all order details
			$orderDetails = $this->M_Order_Detail->getAll([
				"where" => [
					"order_id" => $orderId,
					"deleted_at" => NULL
				]
			]);

			foreach ($orderDetails as $detail) {
				// Get current stock
				$product = $this->MOC_Product->detail($detail["product_id"]);

				if ($product) {
					// Restore stock
					$newStock = $product["stock"] + $detail["quantity"];

					$this->MOC_Product->update($product["product_id"], [
						"stock" => $newStock
					]);
				}
			}

			return true;
		} catch (Exception $e) {
			log_message('error', 'Error restoring stock: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * @param int $id id on orders table
	 */
	public function deleteById($id)
	{
		$this->db->trans_begin();

		try {
			// Restore stock first
			$this->restoreProductStock($id);

			// Soft delete order
			$this->db->where('id', $id)
				->set('deleted_at', date('Y-m-d H:i:s'))
				->update($this->table);

			// Soft delete details
			$this->db->where('order_id', $id)
				->set('deleted_at', date('Y-m-d H:i:s'))
				->update('order_details');

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return false;
			}

			$this->db->trans_commit();
			return true;
		} catch (Exception $e) {
			$this->db->trans_rollback();
			log_message('error', 'Error in deleteById: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * @param array $data data contains outlet_id, table_id, brand and name
	 */
	public function insertOrder($data)
	{
		$query = $this->db->insert($this->table, $data);

		if (!$query) {
			return [
				"success" => false,
				"message" => $this->db->error(),
			];
		}

		return [
			"success" => true,
			"id" => $this->db->insert_id()
		];
	}

	public function	queryDB($action, $params)
	{
		if (!method_exists($this, $action))
			return [];

		$sql   = $this->$action();
		$query = $this->db->query($sql, $params);

		return $query->result();
	}

	public function qBUpdate($id, $data)
	{
		$this->db->where('id', $id);
		$this->db->update($this->table, $data);
	}

	public function qBOrderDetailUpdate($orderId, $productId, $data)
	{
		$this->db->where('order_id', $orderId)->where("product_id", $productId);
		$this->db->update("order_details", $data);
	}

	public function getOne($params, $select = NULL)
	{
		// Log params untuk debugging
		log_message('DEBUG', 'Get One Params: ' . json_encode($params));

		// Jika params berisi array status
		if (isset($params['status']) && is_array($params['status'])) {
			// Tambahkan penanganan untuk status kosong atau NULL
			$this->db->group_start();
			$this->db->where_in('status', array_filter($params['status'], function ($status) {
				return $status !== '';
			}));
			$this->db->or_where('status', '');
			$this->db->or_where('status IS NULL');
			$this->db->group_end();
			unset($params['status']);
		}

		$selected = "*";
		if (!is_null($select)) {
			$selected = implode(",", $select);
		}

		$query = $this->db->select($selected);
		$query = $query->where($params)->order_by('id', 'DESC')->limit(1);
		$result = $query->get($this->table);

		// Log query terakhir untuk debugging
		log_message('DEBUG', 'Last Query: ' . $this->db->last_query());

		return $result->row_array();
	}

	public function updateOrderTimestamps($orderId, $timestamps)
	{
		$this->db->where('id', $orderId);
		return $this->db->update('orders', $timestamps);
	}
}
