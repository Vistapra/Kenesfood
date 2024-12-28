<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_Order_Detail extends Ci_model
{
	public $table = "order_details";
	public $primaryKey = "id";

	/**
	 * get all data from database
	 *
	 * @param mixed $params
	 * @param array|null $select
	 * @return array|bool
	 */
	public function getAll($params, $select = NULL)
	{
		$selected = "*";
		if(!is_null($select)) {
			$selected = implode(",", $select);
		}

		$query = $this->db->select($selected);
		if(array_key_exists("where", $params))
		{
			$query = $query->where($params["where"]);
		}
		if(array_key_exists("where_in", $params))
		{
			foreach($params["where_in"] as $key => $value)
			{
				$query = $query->where_in($key, $value);
			}
		}
		$query = $query->get($this->table);
		return $query->result_array();
	}

	public function getList($params, $select = NULL, $offset = NULL, $limit = 10) : ?array
	{
		$selected = "*";
		if(!is_null($select)) {
			$selected = implode(",", $select);
		}
		
		$query = $this->db->select($selected);

		if(!is_null($offset)) {
			$query = $query->limit($limit, $offset);
		}

		$query = $query
			->where($params)
			->get($this->table);

		return $query->result_array();
	}

	public function getOne($params, $select = NULL) : ?array
	{
		$selected = "*";
		if(!is_null($select)) {
			$selected = implode(",", $select);
		}

		$query = $this->db->select($selected);
		$query = $query->where($params)->get($this->table);
		
		return $query->row_array();
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

	public function updateAll($data, $primaryKey = NULL)
	{
		if(is_null($primaryKey))
		{
			$primaryKey = $this->primaryKey;
		}

		$affected_rows = $this->db->update_batch($this->table, $data, $primaryKey);

		$error = $this->db->error();

		if ($error['code'] !== 0) {
			return $error;
		}

		return $affected_rows;
	}

	public function updateByOrderId($id, $data)
	{
		$this->db->where('order_id', $id);
		$query = $this->db->update($this->table, $data);

		return $data;
	}

	/**
	 * Insert multiple records into the database.
	 *
	 * @param array $data
	 * @return int|bool
	 */
	public function insertAll($data)
	{
		return $this->db->insert_batch($this->table, $data);
	}

	/**
	 * Insert single record into the database.
	 * 
	 * @param array $data
	 * @return int|bool
	 */
	public function insertOne($data)
	{
		$insert = $this->db->insert($this->table, $data);

		if(!$insert)
		{
			return $this->db->error();
		}

		return  $this->db->insert_id();
	}
}