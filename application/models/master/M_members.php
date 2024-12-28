<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_members extends CI_Model {

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    // get last member code
    public function get_last_code() {
        $code = $this->config->item('member') . date('ym');
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
    public function get_total_data($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_member.fullname LIKE '%" . $keyword ."%'
                OR data_member.phone LIKE '%" . $keyword ."%'
                OR data_member.address LIKE '%" . $keyword ."%'
                OR app_user.user_name LIKE '%" . $keyword ."%'
                OR app_user.user_email LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_member 
                INNER JOIN app_user ON data_member.user_id = app_user.user_id
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
                data_member.fullname LIKE '%" . $keyword ."%'
                OR data_member.phone LIKE '%" . $keyword ."%'
                OR data_member.address LIKE '%" . $keyword ."%'
                OR app_user.user_name LIKE '%" . $keyword ."%'
                OR app_user.user_email LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    app_user.user_id,
                    app_user.user_alias,
                    app_user.user_name,
                    app_user.user_email,
                    app_user.user_st,
                    app_user.user_lock,
                    app_user.user_photo,
                    data_member.fullname,
                    data_member.phone,
                    data_member.address,
                    data_member.maps,
                    data_member.birthday
                FROM data_member 
                INNER JOIN app_user ON data_member.user_id = app_user.user_id
                WHERE 1=1
                " . $conditions . "
                ORDER BY app_user.user_name
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
                    app_user.user_lock,
                    app_user.user_photo,
                    data_member.fullname,
                    data_member.gender,
                    data_member.phone,
                    data_member.address,
                    data_member.maps,
                    data_member.birthday
                FROM data_member 
                INNER JOIN app_user ON data_member.user_id = app_user.user_id
                WHERE data_member.user_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert members
    function add_members($params) {
        return $this->db->insert('data_member', $params);
    }

    // update members
    function update_members($id, $params) {
        $this->db->where('user_id', $id);
        return $this->db->update('data_member', $params);
    }

    // delete members
    function delete_members($id) {
        $this->db->where('user_id', $id);
        return $this->db->delete('data_member');
    }
}