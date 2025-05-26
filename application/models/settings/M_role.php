<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_role extends CI_Model {

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    // get last nav id
    function get_last_role_id($params) {
        $sql = "SELECT LEFT(role_id, 2) 'role_id', RIGHT(role_id, 2) 'last_number'
                FROM app_role
                WHERE site_id = ? 
                ORDER BY RIGHT(role_id, 2) DESC
                LIMIT 1";
        $query = $this->db->query($sql, $params);
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
            return $result['role_id'] . $zero . $number;
        } else {
            // create new number
            return substr($params['role_id'], 0, 2) . '01';
        }
    }

    // get total data
    public function get_total_data($params, $keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                app_role.role_nm LIKE '%" . $keyword ."%'
                OR app_role.role_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM app_role 
                WHERE site_id = ?
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

    // get list data
    function get_list_data($params, $keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                app_role.role_nm LIKE '%" . $keyword ."%'
                OR app_role.role_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    app_role.* 
                FROM app_role 
                WHERE site_id = ?
                " . $conditions . "
                ORDER BY app_role.role_nm
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

    // get list data role user
    function get_list_data_role_user() {
        $site_id = $this->config->item('private');
        $sql = "SELECT app_role.role_id, app_role.role_nm FROM app_role 
                WHERE role_st = '0' AND site_id = ?
            ";
        $query = $this->db->query($sql, $site_id);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get detail role
    function get_detail_role($role_id) {
        $sql = "SELECT 
                    app_role.site_id, 
                    app_role.role_id, 
                    app_role.role_nm,
                    app_role.role_st
                FROM app_role 
                WHERE app_role.role_id = ?
            ";
        $query = $this->db->query($sql, $role_id);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert role
    function add_role($params) {
        return $this->db->insert('app_role', $params);
    }

    // update role
    function update_role($role_id, $params) {
        $this->db->where('role_id', $role_id);
        return $this->db->update('app_role', $params);
    }

    // delete role
    function delete_role($role_id) {
        $this->db->where('role_id', $role_id);
        return $this->db->delete('app_role');
    }

    /* APP ROLE MENU */

    // insert role menu
    function add_role_menu($params) {
        return $this->db->insert('app_role_menu', $params);
    }

    // delete role menu
    function delete_role_menu($role_id) {
        $this->db->where('role_id', $role_id);
        return $this->db->delete('app_role_menu');
    }

    /* APP ROLE USER */

    // insert role user
    function add_role_user($params) {
        return $this->db->insert('app_role_user', $params);
    }

    // delete role user
    function delete_role_user($user_id) {
        $this->db->where('user_id', $user_id);
        return $this->db->delete('app_role_user');
    }
}