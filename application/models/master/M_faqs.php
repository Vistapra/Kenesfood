<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_faqs extends CI_Model
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
                faq.faq_kategori LIKE '%" . $keyword ."%'
                OR faq.faq_name LIKE '%" . $keyword ."%'
                OR faq.question LIKE '%" . $keyword ."%'
                OR faq.answer LIKE '%" . $keyword ."%'
                OR faq.deleted LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM faq
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
                faq.faq_kategori LIKE '%" . $keyword ."%'
                OR faq.faq_name LIKE '%" . $keyword ."%'
                OR faq.question LIKE '%" . $keyword ."%'
                OR faq.answer LIKE '%" . $keyword ."%'
                OR faq.deleted LIKE '%" . $keyword ."%'
            )";
        }
            $sql = "SELECT 
            faq.* 
            FROM faq 
            WHERE 1=1
            " . $conditions . "
            ORDER BY faq.faq_name
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
    function get_detail_faq ($params) {
        $sql = "SELECT * FROM faq
                WHERE faq_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert faqs
    function add_faq($params) {
        return $this->db->insert('faq', $params);
    }

    // update faqs
    function update_faq($faq_id, $params) {
        $this->db->where('faq_id', $faq_id);
        return $this->db->update('faq', $params);
    }

    // delete faqs
    function delete_faq($faq_id) {
        $this->db->where('faq_id', $faq_id);
        return $this->db->delete('faq');
    }

    // get detail faq aktif
    function get_detail_by_faq($params) {
        $sql = "SELECT * 
                FROM faq
                WHERE deleted = '0' 
                AND faq_kategori = ?
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
    function get_list_faq() {
        $sql = "SELECT * 
                FROM faq
                WHERE deleted = '0'
                ORDER BY faq_name ";
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
