<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_outlets extends CI_Model {

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    // get last id
    function get_last_id() {
        $sql = "SELECT MAX(outlet_id) AS last_id
                FROM data_outlet";
        $query = $this->db->query($sql);
        if($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['last_id'] + 1;
        } else {
            return 1;
        }
    }

    function get_last_code() {
        $sql = "SELECT LEFT(outlet_code, 5) 'outlet_code', RIGHT(outlet_code, 2) 'last_number'
                FROM data_outlet
                ORDER BY RIGHT(outlet_code, 2) DESC
                LIMIT 1";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            // create next number
            $number = intval($result['last_number']) + 1;
            if ($number >= 99) {
                return false;
            }
            $zero = '';
            for ($i = strlen($number); $i < 2; $i++) {
                $zero .= '0';
            }
            return $result['outlet_code'] . $zero . $number;
        } else {
            // create new number
            return 'OUT1001';
        }
    }

    // get total data
    public function get_total_data($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_outlet.outlet_code LIKE '%" . $keyword ."%'
                OR data_outlet.outlet_name LIKE '%" . $keyword ."%'
                OR data_outlet.outlet_img LIKE '%" . $keyword ."%'
                OR data_outlet.outlet_address LIKE '%" . $keyword ."%'
                OR data_outlet.kota LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_outlet 
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
                data_outlet.outlet_code LIKE '%" . $keyword ."%'
                OR data_outlet.outlet_name LIKE '%" . $keyword ."%'
                OR data_outlet.outlet_img LIKE '%" . $keyword ."%'
                OR data_outlet.outlet_address LIKE '%" . $keyword ."%'
                OR data_outlet.kota LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    data_outlet.* 
                FROM data_outlet 
                WHERE 1=1
                " . $conditions . "
                ORDER BY data_outlet.outlet_code
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

    function get_detail_outlet ($params) {
        $sql = "SELECT * FROM data_outlet
                WHERE outlet_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert outlets
    function add_outlets($params) {
        return $this->db->insert('data_outlet', $params);
    }

    // update outlets
    function update_outlets($outlet_id, $params) {
        $this->db->where('outlet_id', $outlet_id);
        return $this->db->update('data_outlet', $params);
    }

    // delete outlets
    function delete_outlets($outlet_id) {
        $this->db->where('outlet_id', $outlet_id);
        return $this->db->delete('data_outlet');
    }

    /**
     *  PUBLIC PAGE
     */

    // get list data outlet
    function get_list_data_outlets_when_active() {
        $query = $this->db->get_where('data_outlet', array('outlet_status' => '0'));
        return $query->result_array();
    }
}