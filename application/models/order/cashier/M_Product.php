<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_Product extends Ci_model
{
	private $table = "data_product";
	private $primaryKey = "product_id";

	public function getAll($params, $select = NULL) : ?array
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

	public function countAll($keyword)
	{

		$this->db->where([
			"product_brand" => 'kopitiam'
		]);

		if(!is_null($keyword))
		{
			$this->db->like('product_name', $keyword);
		}

		$this->db->from($this->table);

		return $this->db->count_all_results();
	}

	public function getList($keyword = NULL, $limit = 10, $offset = 0)
	{
		$this->db->select("
			product_id AS id,
			product_code AS code,
			product_name AS name,
			stock,
			product_st AS status"
		);

		$this->db->where([
			"product_brand" => "kopitiam"
		]);

		if(!is_null($keyword))
		{
			$this->db->like('product_name', $keyword);
		}

		$query = $this->db->get($this->table, $limit, $offset);

        if ($query->num_rows() > 0) {
            return $query->result_array();
        }

        return array();
	}

	public function detail($id)
	{
		$this->db->where($this->primaryKey, $id);
		$query = $this->db->get($this->table);

		$data = $query->row_array();
		
		if(!isset($data))
			return FALSE;

		return $data;
	}

	public function update($id, $data) : array
	{
		$this->db->where($this->primaryKey, $id);
		$query = $this->db->update($this->table, $data);

		$data = self::detail($id);

		return $data;
	}

	public function updateAll($params, $data)
	{
		$this->db->update_batch($this->table, $data, $params);
	}
}