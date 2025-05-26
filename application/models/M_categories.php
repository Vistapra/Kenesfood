<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_categories extends CI_Model
{

	function __construct()
	{
		// Call the Model constructor
		parent::__construct();
	}


	function get_list_data_categories()
	{
		$query = $this->db->get('data_categories');
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}

	function add_categories_data($params)
	{
		$params['created'] = date('Y-m-d H:i:s');
		$params['modified'] = date('Y-m-d H:i:s');
		return $this->db->insert('data_categories', $params);
	}

	function delete_categories_data($id)
	{
		// Assuming 'categories' is the name of your database table
		$query = $this->db->get_where('data_categories', array('cat_id' => $id));
		if ($query->num_rows() > 0) {
			$this->db->where('cat_id', $id);
			$this->db->delete('data_categories');
			return true; // Jika berhasil menghapus
		} else {
			return false; // Jika data tidak ditemukan
		}
	}

	public function get_last_categories_sequence()
	{
		$this->db->select_max('cat_code');
		$query = $this->db->get('data_categories');
		$row = $query->row();
		return $row->cat_code;
	}

	public function get_detail_categories($id)
	{
		$query = $this->db->get_where('data_categories', array('cat_id' => $id));
		return $query->row_array(); // Mengembalikan satu baris hasil
	}
	public function get_detail_categories_seasonal()
	{
		$sql = "SELECT 
        data_categories.* 
    FROM data_categories INNER JOIN data_seasonal ON data_seasonal.season_id = data_categories.seasonal_id 
    WHERE data_seasonal.season_st = '0'
    AND data_categories.cat_highlight = '1'
    ORDER BY data_categories.cat_no ASC ";
		$query = $this->db->query($sql);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
	}


	public function get_sub_categories($id)
	{
		$sql = "SELECT 
        data_categories.* 
    FROM data_categories 
    WHERE data_categories.cat_parent = $id
    AND data_categories.cat_highlight = '1'
    ORDER BY data_categories.cat_no ASC ";

		$query = $this->db->query($sql);
		if ($query->num_rows() > 0) {
			$result = $query->result_array();
			$query->free_result();
			return $result;
		} else {
			return array();
		}
		// $query = $this->db->get_where('data_categories', array('cat_parent' => $id));
		// return $query->result_array(); // Mengembalikan satu baris hasil
	}

	public function edit_data_categories($id, $data)
	{
		$this->db->where('cat_id', $id);
		$data['modified'] = date('Y-m-d H:i:s');
		$this->db->update('data_categories', $data);
	}

	public function product_categories($data_categories)
	{

		$data_categories['created'] = date('Y-m-d H:i:s');
		return $this->db->insert('product_categories', $data_categories);
	}

	public function edit_product_categories($data_categories)
	{

		$data_categories['modified'] = date('Y-m-d H:i:s');
		return $this->db->update('product_categories', $data_categories);
	}
	public function get_catalogue_categories($brand)
	{
		$sql = "WITH RECURSIVE CategoryTree AS (
        SELECT 
            c.cat_id, 
            c.cat_name, 
            c.cat_brand,
            c.cat_st,
            c.cat_highlight,
            c.cat_no,
            c.cat_desc,
            c.cat_img,
            c.cat_parent,
            0 AS depth
        FROM data_categories c
        WHERE c.cat_brand = ? 
          AND c.cat_st = '0'

        UNION ALL

        SELECT 
            c.cat_id, 
            c.cat_name, 
            c.cat_brand,
            c.cat_st,
            c.cat_highlight,
            c.cat_no,
            c.cat_desc,
            c.cat_img,
            c.cat_parent,
            ct.depth + 1
        FROM data_categories c
        INNER JOIN CategoryTree ct ON c.cat_parent = ct.cat_id
        WHERE c.cat_brand = ?
    )
    SELECT 
        ct.cat_id, 
        ct.cat_name, 
        ct.cat_brand,
        ct.cat_st,
        ct.cat_highlight,
        ct.cat_no,
        ct.cat_desc,
        ct.cat_img,
        COUNT(p.product_id) as product_count
    FROM CategoryTree ct
    LEFT JOIN data_product p ON 
        ct.cat_id = p.cat_id 
        AND p.product_st = '0' 
        AND p.product_parent = '0'
        AND p.product_brand = ?
    WHERE ct.depth = 0
    GROUP BY 
        ct.cat_id, 
        ct.cat_name, 
        ct.cat_brand,
        ct.cat_st,
        ct.cat_highlight,
        ct.cat_no,
        ct.cat_desc,
        ct.cat_img
    HAVING product_count > 0
    ORDER BY ct.cat_no ASC";

		$query = $this->db->query($sql, [$brand, $brand, $brand]);

		return $query->result_array();
	}
}
