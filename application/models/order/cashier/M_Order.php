<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_Order extends Ci_model
{
	public $table = "orders";
	public $primaryKey = "id";

	const STATUS_RESERVED = 0;
	const STATUS_ORDERED = 1;

	public function getOrderStatus($params)
	{
		$query = $this->db->select("*")
			->from($this->table)
			->where($params)
			->get();

		if(!($query->num_rows() > 0))
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
		$query = $this->db->select("product_name, qty, notes")
			->from($this->table)
			->join(
				'order_details',
				"{$this->table}.id = order_details.order_id"
			)
			->join(
				'data_product',
				"order_details.product_id = data_product.product_id"
			)
			->where($params)
			->where("{$this->table}.deleted_at", NULL)
	 		->get();

		if(!($query->num_rows() > 0))
			return [];
		
		return $query->result_array();
	}

	public function getDetailsByOrderId($id)
	{
		$query = $this
			->db
			->from('order_details')
			->join(
				"data_product",
				"order_details.product_id = data_product.product_id"
			)
			->where('order_id', $id)
			->get();

		if(!($query->num_rows() > 0))
			return [];

		return $query->result_array();
	}

	public function update($id, $data) : array
	{
		$this->db->where($this->primaryKey, $id);
		$query = $this->db->update($this->table, $data);

		// $data = self::detail($id);

		return $data;
	}
}