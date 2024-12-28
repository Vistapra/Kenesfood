<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_users extends CI_Model {
    private $_table = "app_user";
    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    // get last inserted id
    function get_last_inserted_id() {
        return $this->db->insert_id();
    }

    
    // get last member code
    public function get_last_code() {
        $code = $this->config->item('private') . date('ym');
        $sql = "SELECT RIGHT(user_code, 3) 'last_number'
                FROM app_user
                WHERE LEFT(user_code, 6) = ?
                ORDER BY RIGHT(user_code, 3) DESC
                LIMIT 1";
        $query = $this->db->query($sql, $code);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            $number = intval($result['last_number']) + 1;
            if ($number >= 999) {
                return false;
            }
            $zero = '';
            for ($i = strlen($number); $i < 3; $i++) {
                $zero .= '0';
            }
            return $code . $number;
        } else {
            return $code . '001';
        }
    }
    

    // get total data
    public function get_total_data($params, $keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                OR a.user_name LIKE '%" . $keyword ."%'
                OR a.user_alias LIKE '%" . $keyword ."%'
                OR a.user_email LIKE '%" . $keyword ."%'
                OR c.role_nm LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total' FROM app_user a
                INNER JOIN app_role_user b ON a.user_id = b.user_id
                INNER JOIN app_role c ON b.role_id = c.role_id
                WHERE c.site_id = ?
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
                OR a.user_name LIKE '%" . $keyword ."%'
                OR a.user_alias LIKE '%" . $keyword ."%'
                OR a.user_email LIKE '%" . $keyword ."%'
                OR c.role_nm LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    a.user_id,
                    a.user_alias,
                    a.user_name,
                    a.user_email,
                    a.user_st,
                    c.role_nm
                FROM app_user a
                INNER JOIN app_role_user b ON a.user_id = b.user_id
                INNER JOIN app_role c ON b.role_id = c.role_id
                WHERE c.site_id = ?
                " . $conditions . "
                ORDER BY a.user_name
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

    // get detail data
    function get_detail_data($params) {
        // conditions
        $sql = "SELECT 
                    app_user.user_id,
                    app_user.user_code,
                    app_user.user_alias,
                    app_user.user_name,
                    app_user.user_email,
                    app_user.user_st,
                    app_user.user_photo,
                    app_user.user_lock
                FROM app_user 
                WHERE app_user.user_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert users
    function add_users($params) {
        return $this->db->insert('app_user', $params);
    }

    // update users
    function update_users($id, $params) {
        $this->db->where('user_id', $id);
        return $this->db->update('app_user', $params);
    }

    // delete users
    function delete_users($id) {
        $this->db->where('user_id', $id);
        return $this->db->delete('app_user');
    }
}
    