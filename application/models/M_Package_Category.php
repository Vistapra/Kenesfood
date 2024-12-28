<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_Package_Category extends Ci_model
{
	public $table = "package_categories";
	public $primaryKey = "id";

	public function getAll($params = [], $select = NULL): ?array
	{
		$selected = "*";
		if (!is_null($select)) {
			$selected = implode(",", $select);
		}

		$query = $this->db->select($selected);


		if (is_array($params)) {
			if (isset($params['where']) && is_array($params['where'])) {
				$query = $query->where($params['where']);
			}
		}

		$result = $query->get($this->table);

		if ($result) {
			return $result->result_array();
		}

		return [];
	}

	public function rcvSearch($id, $select = NULL)
	{
		$selected = "*";
		if (!is_null($select)) {
			$selected = implode(",", $select);
		}

		$sql = "WITH RECURSIVE category_hierarchy AS (
			-- Base case: start from the descendant category
			SELECT id, parent_id, name, sale_price
			FROM {$this->table}
			WHERE id = ?
			UNION ALL

			-- Recursive case: find the parent category
			SELECT c.id, c.parent_id, c.name, c.sale_price
			FROM {$this->table} c
			INNER JOIN category_hierarchy ch ON ch.parent_id = c.id
		)
		-- Select all the results from the recursive CTE (ascending order from child to root)
		SELECT id, name
		FROM category_hierarchy
		ORDER BY id DESC";

		$query = $this->db->query($sql, $id);

		return $query->result_array();
	}
}
