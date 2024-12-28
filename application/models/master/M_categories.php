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

    function get_list_category() {
        $sql = "SELECT * 
                FROM data_categories
                WHERE cat_st = '0'
                ORDER BY cat_name ";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get total data
    public function get_total_data($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_categories.cat_code LIKE '%" . $keyword ."%'
                OR data_categories.cat_name LIKE '%" . $keyword ."%'
                OR data_categories.cat_brand LIKE '%" . $keyword ."%'
                OR data_categories.cat_desc LIKE '%" . $keyword ."%'
                OR data_categories.cat_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_categories 
                WHERE data_categories.cat_parent = 0
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
                data_categories.cat_code LIKE '%" . $keyword ."%'
                OR data_categories.cat_name LIKE '%" . $keyword ."%'
                OR data_categories.cat_brand LIKE '%" . $keyword ."%'
                OR data_categories.cat_desc LIKE '%" . $keyword ."%'
                OR data_categories.cat_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    data_categories.* 
                FROM data_categories 
                WHERE data_categories.cat_parent = 0
                " . $conditions . "
                ORDER BY data_categories.cat_code
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

    // get total data sub_category
    public function get_total_data_sub_category($params, $keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_categories.cat_code LIKE '%" . $keyword ."%'
                OR data_categories.cat_name LIKE '%" . $keyword ."%'
                OR data_categories.cat_brand LIKE '%" . $keyword ."%'
                OR data_categories.cat_desc LIKE '%" . $keyword ."%'
                OR data_categories.cat_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_categories 
                WHERE data_categories.cat_parent = ?
                " . $conditions;
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['total'];
        } else {
            return 0;
        }
    }

    // get list data sub_category
    function get_list_data_sub_category($params, $keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_categories.cat_code LIKE '%" . $keyword ."%'
                OR data_categories.cat_name LIKE '%" . $keyword ."%'
                OR data_categories.cat_brand LIKE '%" . $keyword ."%'
                OR data_categories.cat_desc LIKE '%" . $keyword ."%'
                OR data_categories.cat_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    data_categories.* 
                FROM data_categories 
                WHERE data_categories.cat_parent = ?
                " . $conditions . "
                ORDER BY data_categories.cat_code
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

    // get detail category
    public function get_detail_category($params) {
        $sql = "SELECT 
                    data_categories.* 
                FROM data_categories 
                WHERE data_categories.cat_id = ?";
            $query = $this->db->query($sql, $params);
            if ($query->num_rows() > 0) {
                $result = $query->row_array();
                $query->free_result();
                return $result;
            } else {
                return array();
            }
    }

    // get total sub category
    public function get_total_sub_category($params) {
        $sql = "SELECT 
                    COUNT(*) AS `total` 
                FROM data_categories 
                WHERE data_categories.cat_parent = ?
                AND data_categories.cat_st = '0'
                AND data_categories.cat_highlight = '1'";
            $query = $this->db->query($sql, $params);
            if ($query->num_rows() > 0) {
                $result = $query->row_array();
                $query->free_result();
                return $result['total'];
            } else {
                return 0;
            }
    }

    // insert categories
    function add_category($params) {
        return $this->db->insert('data_categories', $params);
    }

    // update categories
    function update_category($cat_id, $params) {
        $this->db->where('cat_id', $cat_id);
        return $this->db->update('data_categories', $params);
    }

    // update categories
    function update_sub_category($cat_parent, $params) {
        $this->db->where('cat_parent', $cat_parent);
        return $this->db->update('data_categories', $params);
    }

    function update_cat_sub($cat_parent, $params) {
        $sql = "UPDATE data_categories 
        SET data_categories.cat_sub = '1' where data_categories.cat_id= $cat_parent";
        $query = $this->db->query($sql, $params);
        
        // if ($query->num_rows() > 0) {
        //     $result = $query->result_array();
        //     $query->free_result();
        //     return $result;
        // } else {
        //     return array();
        // }
    }

    // delete categories
    function delete_category($cat_id) {
        $this->db->where('cat_id', $cat_id);
        return $this->db->delete('data_categories');
    }

    // delete sub category
    function delete_sub_category($cat_parent) {
        $this->db->where('cat_parent', $cat_parent);
        return $this->db->delete('data_categories');
    }
    
    /** PUBLIC PAGE */

    // get list category highlight
    function get_list_cat_highlight($params) {
        $sql = "SELECT * 
                FROM data_categories 
                WHERE cat_highlight = '1'
                AND cat_parent = '0' 
                AND cat_st = '0' 
                AND cat_brand = ?
                AND seasonal_id = '0' 
                ORDER BY cat_no
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

}
