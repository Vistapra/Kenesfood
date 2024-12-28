<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_ekatalogs extends CI_Model
{
    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

       // is exist product code
       function is_exist_ekatalogs_code($params)
       {
           $sql = "SELECT * FROM data_product WHERE product_code = ?";
           $query = $this->db->query($sql, $params);
           if ($query->num_rows() > 0) {
               $query->free_result();
               return TRUE;
           } else {
               return FALSE;
           }
       }
    // get total data
    public function get_total_data($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                sub_katalog.katalog_brand LIKE '%" . $keyword ."%'
                OR sub_katalog.sub_katalog_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM sub_katalog 
                WHERE sub_katalog.sub_katalog_st = '0'
                " . $conditions;
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['total'];
        } else {
            return 0;
        }
    }

    // get list data
    function get_list_data($params, $keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                sub_katalog.katalog_brand LIKE '%" . $keyword ."%'
                OR sub_katalog.sub_katalog_name LIKE '%" . $keyword ."%'
                OR product_name LIKE '%" . $keyword ."%'
                OR cat_name LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
        sub_katalog.* 
    FROM sub_katalog 
    LEFT JOIN data_product ON sub_katalog.product_id = data_product.product_id
    LEFT JOIN data_categories ON data_categories.cat_id = data_product.cat_id
    WHERE sub_katalog.sub_katalog_st = '0'
    AND data_product.product_st = '0'
    " . $conditions . "
    LIMIT ?, ?";

            $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }


    // get detail katalog
 
    function get_detail_katalog ($params) {
        $sql = "SELECT * FROM sub_katalog
                WHERE sub_katalog_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert katalog
    function add_ekatalogs($params) {
        return $this->db->insert('sub_katalog', $params);
    }

    // update katalog
    function update_ekatalogs($sub_katalog_id, $params) {
        $this->db->where('sub_katalog_id', $sub_katalog_id);
        return $this->db->update('sub_katalog', $params);
    }

    // delete katalog
    function delete_ekatalogs($sub_katalog_id) {
        $this->db->where('sub_katalog_id', $sub_katalog_id);
        return $this->db->delete('sub_katalog');
    }

    function get_list_file_by_file_id($params) {
        $sql = "SELECT * FROM `sub_katalog` WHERE sub_katalog_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }
}