<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_banners extends CI_Model
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
                data_banners.banner_name LIKE '%" . $keyword ."%'
                OR data_banners.banner_img LIKE '%" . $keyword ."%'
                OR data_banners.banner_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_banners
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
                data_banners.banner_name LIKE '%" . $keyword ."%'
                OR data_banners.banner_img LIKE '%" . $keyword ."%'
                OR data_banners.banner_st LIKE '%" . $keyword ."%'
            )";
        }
            $sql = "SELECT 
            data_banners.* 
            FROM data_banners 
            WHERE 1=1
            " . $conditions . "
            ORDER BY data_banners.banner_name
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
    function get_detail_banner ($params) {
        $sql = "SELECT * FROM data_banners
        WHERE banner_id = ? LIMIT 1";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    function get_banners () {
        $sql = "SELECT * FROM data_banners";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();print_r($result);exit();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }
    // insert banners
    function add_banner($params) {
        return $this->db->insert('data_banners', $params);
    }

    // update banners
    function update_banner($banner_id, $params) {
        $this->db->where('banner_id', $banner_id);
        return $this->db->update('data_banners', $params);
    }

    // delete banners
    function delete_banner($banner_id) {
        $this->db->where('banner_id', $banner_id);
        return $this->db->delete('data_banners');
    }

    // get detail banner aktif
    function get_detail_by_banner($params) {
        $sql = "SELECT * 
                FROM data_banners
                WHERE banner_st = '0' 
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
    function get_list_banner() {
        $sql = "SELECT * 
                FROM data_banners
                WHERE banner_st = '0'
                ORDER BY banner_name ";
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
