<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_Order_Detail extends Ci_model
{
	public $table = "order_details";
	public $primaryKey = "id";

	public function getAll($params)
	{
		$query = $this->db
			->where($params)
			->get($this->table);

		return $query->result_array();
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
}