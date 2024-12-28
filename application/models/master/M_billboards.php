<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_billboards extends CI_Model
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
                data_billboards.nama_billboard LIKE '%" . $keyword ."%'
                OR data_billboards.img_billboard LIKE '%" . $keyword ."%'
                OR data_billboards.billboard_status LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_billboards 
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
                data_billboards.nama_billboard LIKE '%" . $keyword ."%'
                OR data_billboards.img_billboard LIKE '%" . $keyword ."%'
                OR data_billboards.billboard_status LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    data_billboards.billboard_id,
                    data_billboards.nama_billboard,
                    data_billboards.img_billboard,
                    data_billboards.billboard_status,
                    data_brands.brand_type
                FROM data_billboards 
                INNER JOIN data_brands on data_billboards.brand_id=data_brands.brand_id
                " . $conditions . "
                ORDER BY data_billboards.nama_billboard
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

    function get_detail_billboard ($params) {
        $sql = "SELECT * FROM data_billboards
                WHERE billboard_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert billboards
    function add_billboards($params) {
        return $this->db->insert('data_billboards', $params);
    }

    // update billboards
    function update_billboards($billboard_id, $params) {
        $this->db->where('billboard_id', $billboard_id);
        return $this->db->update('data_billboards', $params);
    }

    // delete billboards
    function delete_billboards($billboard_id) {
        $this->db->where('billboard_id', $billboard_id);
        return $this->db->delete('data_billboards');
    }

    /** 
     * PUBLIC PAGE
     */

    // get list contact by billboard
    function get_list_contact_by_billboard($params) {
        $sql = "SELECT * 
                FROM billboard_contact 
                WHERE contact_st = '0' 
                AND contact_billboard = ?
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

    // get detail billboard aktif
    function get_detail_by_billboard($params) {
        $sql = "SELECT * 
                FROM data_billboards
                WHERE billboard_st = '0' 
                AND billboard_type = ?
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

    // get list billboard aktif
    function get_list_billboard() {
        $sql = "SELECT * 
                FROM data_billboards
                WHERE billboard_st = '0' 
                ORDER BY billboard_name
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
