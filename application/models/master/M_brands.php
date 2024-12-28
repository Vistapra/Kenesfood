<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_brands extends CI_Model
{

    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

    // get total data
    public function get_total_data($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_brands.brand_type LIKE '%" . $keyword ."%'
                OR data_brands.brand_name LIKE '%" . $keyword ."%'
                OR data_brands.brand_desc LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_brands 
                WHERE 1=1
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
                data_brands.brand_type LIKE '%" . $keyword ."%'
                OR data_brands.brand_name LIKE '%" . $keyword ."%'
                OR data_brands.brand_desc LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    data_brands.* 
                FROM data_brands 
                WHERE 1=1
                " . $conditions . "
                ORDER BY data_brands.brand_name
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

    function get_detail_brand ($params) {
        $sql = "SELECT * FROM data_brands
                WHERE brand_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert brands
    function add_brands($params) {
        return $this->db->insert('data_brands', $params);
    }

    // update brands
    function update_brands($brand_id, $params) {
        $this->db->where('brand_id', $brand_id);
        return $this->db->update('data_brands', $params);
    }

    // delete brands
    function delete_brands($brand_id) {
        $this->db->where('brand_id', $brand_id);
        return $this->db->delete('data_brands');
    }

    /** 
     * PUBLIC PAGE
     */

    // get list contact by brand
    function get_list_contact_by_brand($params) {
        $sql = "SELECT * 
                FROM brand_contact 
                WHERE contact_st = '0' 
                AND contact_brand = ?
                AND contact_type = ?
                ORDER BY contact_no
            ";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get detail brand aktif
    function get_detail_by_brand($params) {
        $sql = "SELECT * 
                FROM data_brands
                WHERE brand_st = '0' 
                AND brand_type = ?
                LIMIT 1
            ";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get list brand aktif
    function get_list_brand() {
        $sql = "SELECT * 
                FROM data_brands
                WHERE brand_st = '0' 
                ORDER BY brand_name
            ";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }
}
