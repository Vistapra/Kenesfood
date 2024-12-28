<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_promotions extends CI_Model
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
                promotion.promotion_type LIKE '%" . $keyword ."%'
                OR promotion.promotion_name LIKE '%" . $keyword ."%'
                OR promotion.promotion_desc LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM promotion 
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
                promotion.promotion_type LIKE '%" . $keyword ."%'
                OR promotion.promotion_name LIKE '%" . $keyword ."%'
                OR promotion.promotion_desc LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    promotion.* 
                FROM promotion 
                WHERE 1=1
                " . $conditions . "
                ORDER BY promotion.promotion_name
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

    function get_detail_promotion($params) {
        $sql = "SELECT * FROM promotion
                WHERE promotion_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert promotions
    function add_promotions($params) {
        return $this->db->insert('promotion', $params);
    }

    // update promotions
    function update_promotions($product_id, $params) {
        $this->db->where('promotion_id', $product_id);
        return $this->db->update('promotion', $params);
    }

    // delete promotions
    function delete_promotions($product_id) {
        $this->db->where('promotion_id', $product_id);
        return $this->db->delete('promotion');
    }
}