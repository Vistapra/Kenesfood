<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_katalogs extends CI_Model
{

    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

    // get total data katalog
    public function get_total_data($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_katalog.foto_katalog LIKE '%" . $keyword ."%'
                OR data_katalog.katalog_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_katalog
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
 // get list data
 function get_list_data($params, $keyword) {
    // conditions
    $conditions = '';
    if($keyword != NULL) {
        $conditions .= " AND (
            data_katalog.foto_katalog LIKE '%" . $keyword ."%'
            OR data_katalog.katalog_st LIKE '%" . $keyword ."%'
        )";
    }
        $sql = "SELECT 
        data_katalog.* 
        FROM data_katalog WHERE katalog_st = '0'
        " . $conditions . "
        LIMIT ?, ?";
        $query = $this->db->query($sql, $params);
    if ($query->num_rows() > 0) {
        $result = $query->result_array();
        $query->free_result();
        // echo "<pre>";
        // print_r($result); 
        // echo "</pre>";
        // exit();
        return $result;
    } else {
        return array();
    }
}
    function get_detail_katalog ($params) {
        $sql = "SELECT * FROM data_katalog
                WHERE katalog_id = ?";
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
    function add_katalogs($params) {
        return $this->db->insert('data_katalog', $params);
    }

    function update_katalogs($params,$katalog_id) {
        $this->db->where('katalog_id', $katalog_id);
        return $this->db->update('data_katalog', $params);
    }

    function delete_katalogs($katalog_id) {
        $this->db->where('katalog_id', $katalog_id);
        return $this->db->delete('data_katalog');
    }

    /** 
     * PUBLIC PAGE
     */

    // get detail katalog aktif
    function get_detail_by_katalog($params) {
        $sql = "SELECT * 
                FROM data_katalog
                WHERE katalog_st = '0' 
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

    function get_list_katalog() {
        $sql = "SELECT * 
                FROM data_katalog
                WHERE katalog_st = '0' 
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
