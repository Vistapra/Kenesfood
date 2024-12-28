<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_products extends CI_Model
{
    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

    // is exist product code
    function is_exist_product_code($params)
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

    // is exist product name
    function is_exist_product_name($params)
    {
        $sql = "SELECT * FROM data_product WHERE product_name = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $query->free_result();
            return TRUE;
        } else {
            return FALSE;
        }
    }


    // is exist product code
    function is_exist_product_code_by_id($params)
    {
        $sql = "SELECT * FROM data_product WHERE product_code = ? AND product_id <> ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $query->free_result();
            return TRUE;
        } else {
            return FALSE;
        }
    }

    // is exist product name
    function is_exist_product_name_by_id($params)
    {
        $sql = "SELECT * FROM data_product WHERE product_name = ? AND product_id <> ?";
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
                data_product.product_code LIKE '%" . $keyword ."%'
                OR data_product.product_name LIKE '%" . $keyword ."%'
                OR data_product.product_brand LIKE '%" . $keyword ."%'
                OR data_product.product_desc LIKE '%" . $keyword ."%'
                OR data_product.product_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_product 
                WHERE data_product.product_parent = 0
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
                data_product.product_code LIKE '%" . $keyword ."%'
                OR data_product.product_name LIKE '%" . $keyword ."%'
                OR data_product.product_brand LIKE '%" . $keyword ."%'
                OR data_product.product_desc LIKE '%" . $keyword ."%'
                OR data_product.product_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    data_product.product_id,
                    data_product.product_code,
                    data_product.product_name,
                    data_product.parent_name,
                    data_product.product_price,
                    data_product.product_no,
                    data_product.product_brand,
                    data_product.product_promote,
                    data_product.product_st,
                    data_categories.cat_name,
                    data_categories.parent_name_cat
                FROM data_product 
                INNER JOIN data_categories on data_product.cat_id=data_categories.cat_id
                WHERE data_product.product_parent = 0
                " . $conditions . "
                ORDER BY data_product.product_no, data_product.product_code
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

    // get total data varian
    public function get_total_data_varian($params, $keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_product.product_code LIKE '%" . $keyword ."%'
                OR data_product.product_name LIKE '%" . $keyword ."%'
                OR data_product.product_brand LIKE '%" . $keyword ."%'
                OR data_product.product_desc LIKE '%" . $keyword ."%'
                OR data_product.product_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_product 
                WHERE data_product.product_parent = ?
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

    public function get_list_product_katalog() {
        $sql = "SELECT 
                    data_product.* 
                FROM data_product 
                ORDER BY data_product.product_code";
                 $query = $this->db->query($sql);
                 if ($query->num_rows() > 0) {
                     $result = $query->result_array();
                     $query->free_result();
                     return $result;
                 } else {
                     return array();
                 }
    }

    // get list data varian
    function get_list_data_varian($params, $keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_product.product_code LIKE '%" . $keyword ."%'
                OR data_product.product_name LIKE '%" . $keyword ."%'
                OR data_product.product_brand LIKE '%" . $keyword ."%'
                OR data_product.product_desc LIKE '%" . $keyword ."%'
                OR data_product.product_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    data_product.* 
                FROM data_product 
                WHERE data_product.product_parent = ?
                " . $conditions . "
                ORDER BY data_product.product_code
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

    // get detail product
    public function get_detail_product($params) {
        $sql = "SELECT 
                    data_product.*,
                    data_categories.*
                FROM data_product 
                INNER JOIN data_categories on data_product.cat_id=data_categories.cat_id
                WHERE data_product.product_id = ?";
            $query = $this->db->query($sql, $params);
            if ($query->num_rows() > 0) {
                $result = $query->row_array();
                $query->free_result();
                return $result;
            } else {
                return array();
            }
    }

    // get list varian product
    public function get_list_varian_product($params) {
        $sql = "SELECT 
                    data_product.* 
                FROM data_product 
                WHERE data_product.product_parent = ?";
            $query = $this->db->query($sql, $params);
            if ($query->num_rows() > 0) {
                $result = $query->result_array();
                $query->free_result();
                return $result;
            } else {
                return array();
            }
    }

    // insert product
    function add_product($params) {
        return $this->db->insert('data_product', $params);
    }

    // update product
    function update_product($product_id, $params) {
        $this->db->where('product_id', $product_id);
        return $this->db->update('data_product', $params);
    }

    // update product
    function update_variant_product($product_parent, $params) {
        $this->db->where('product_parent', $product_parent);
        return $this->db->update('data_product', $params);
    }

    // delete product
    function delete_product($product_id) {
        $this->db->where('product_id', $product_id);
        return $this->db->delete('data_product');
    }

    // delete varian product
    function delete_variant_product($product_parent) {
        $this->db->where('product_parent', $product_parent);
        return $this->db->delete('data_product');
    }

    /**
     *  PUBLIC PAGE
     */

    // get list product banner
    function get_list_product_banner($params) {
        $sql = "SELECT 
                    prod.*, cat.seasonal_id
                FROM `data_product` prod
                INNER JOIN data_categories cat ON prod.cat_id = cat.cat_id
                WHERE prod.`product_promote` IN ('arrival','prelaunch') 
                AND prod.`product_st` = '0'
                AND prod.product_brand = ?
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