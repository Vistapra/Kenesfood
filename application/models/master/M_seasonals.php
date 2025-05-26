<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_seasonals extends CI_Model
{
    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

// insert product
    function add_season($params) {
        return $this->db->insert('data_seasonal', $params);
    }

    function get_list_data($params, $keyword) {
        
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_seasonal.season_name LIKE '%" . $keyword ."%'
               
            )";
        }
        $sql = "SELECT 
                    data_seasonal.* 
                FROM data_seasonal 
                WHERE data_seasonal.season_st = '0'
                " . $conditions . "
                ORDER BY data_seasonal.season_name
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

    public function get_total_data($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_seasonal.season_name LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_seasonal 
                WHERE data_seasonal.season_st = 0
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

    // update product
    function update_season($season_id, $params) {
        $this->db->where('season_id', $season_id);
        return $this->db->update('data_seasonal', $params);
    }

    // // update product
    // function update_variant_product($product_parent, $params) {
    //     $this->db->where('product_parent', $product_parent);
    //     return $this->db->update('data_seasonal', $params);
    // }

    // delete product
    function delete_product($season_id) {
        $this->db->where('season_id', $season_id);
        return $this->db->delete('data_seasonal');
    }

    public function get_detail_product($params) {
        $sql = "SELECT 
                    data_seasonal.* 
                FROM data_seasonal 
                WHERE data_seasonal.season_id = ?";
            $query = $this->db->query($sql, $params);
            if ($query->num_rows() > 0) {
                $result = $query->row_array();
                $query->free_result();
                return $result;
            } else {
                return array();
            }
    }
    public function get_list_product_seasonal()
    {
        $sql = "SELECT 
        data_seasonal.* 
    FROM data_seasonal 
   
     ";

    $query = $this->db->query($sql);
    if ($query->num_rows() > 0) {
    $result = $query->result_array();
   
    return $result;
    } else {
    return array();
    }
    }

    // // delete varian product
    // function delete_variant_product($product_parent) {
    //     $this->db->where('product_parent', $product_parent);
    //     return $this->db->delete('data_seasonal');
    // }
}