<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_Package_Category extends Ci_model
{
	public $table = "package_categories";
	public $primaryKey = "id";

	function __construct()
	{
		// Call the Model constructor
		parent::__construct();
	}

	public function getAll($params = [])
	{
		$query = $this->db;

		if (isset($params["where"])) {
			foreach ($params["where"] as $key => $value) {
				if (is_null($value)) {
					$query = $query->where("$key IS NULL");
				} else {
					$query = $query->where($key, $value);
				}
			}
		}

		if (isset($params["order_by"])) {
			$query = $query->order_by($params["order_by"]);
		} else {
			$query = $query->order_by("display_order", "ASC");
		}

		$result = $query->get($this->table)->result_array();

		if (!empty($result)) {
			// Get requirements for each category
			foreach ($result as &$category) {
				$req = $this->db
					->where('package_category_id', $category['id'])
					->where('deleted_at IS NULL')
					->get('package_category_requirements')
					->row_array();

				if ($req) {
					$category['min_items'] = $req['min_items'];
					$category['max_items'] = $req['max_items'];
				}
			}
		}

		return $result;
	}

	public function getOne($where)
	{
		$query = $this->db->get_where($this->table, $where);
		return $query->row_array();
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

	/**
	 * Add a new category
	 */
	public function add_category($data)
	{
		$this->db->insert($this->table, $data);
		return $this->db->insert_id();
	}

	/**
	 * Update category
	 */
	public function update_category($category_id, $data)
	{
		$this->db->where('id', $category_id);
		return $this->db->update($this->table, $data);
	}

	/**
	 * Get category details
	 */
	public function get_detail($category_id)
	{
		return $this->db->where('id', $category_id)
			->where('deleted_at IS NULL')
			->get($this->table)
			->row_array();
	}

	/**
	 * Get categories by package id
	 */
	public function get_by_package_id($package_id)
	{
		$this->db->select('pc.*, pcr.min_items, pcr.max_items')
			->from($this->table . ' pc')
			->join('package_category_requirements pcr', 'pc.id = pcr.package_category_id', 'left')
			->where('pc.package_id', $package_id)
			->where('pc.deleted_at IS NULL')
			->order_by('pc.display_order', 'ASC');

		return $this->db->get()->result_array();
	}

	/**
	 * Delete category (soft delete)
	 */
	public function delete_category($category_id)
	{
		$this->db->where('id', $category_id)
			->update($this->table, ['deleted_at' => date('Y-m-d H:i:s')]);
	}

	/**
	 * Delete categories by package id (soft delete)
	 */
	public function delete_by_package_id($package_id)
	{
		$this->db->where('package_id', $package_id)
			->update($this->table, ['deleted_at' => date('Y-m-d H:i:s')]);
	}
}
