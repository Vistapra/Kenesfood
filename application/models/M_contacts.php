<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_contacts extends CI_Model
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
                contact.nama_user LIKE '%" . $keyword ."%'
                OR contact.email_user LIKE '%" . $keyword ."%'
                OR contact.telepon_user LIKE '%" . $keyword ."%'
                OR contact.pesan_user LIKE '%" . $keyword ."%'
                OR contact.status_contact LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM contact
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
                 contact.nama_user LIKE '%" . $keyword ."%'
                OR contact.email_user LIKE '%" . $keyword ."%'
                OR contact.telepon_user LIKE '%" . $keyword ."%'
                OR contact.pesan_user LIKE '%" . $keyword ."%'
                OR contact.status_contact LIKE '%" . $keyword ."%'
            )";
        }
            $sql = "SELECT 
            contact.* 
            FROM contact 
            WHERE 1=1
            " . $conditions . "
            ORDER BY contact.nama_user
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
    function get_detail_contact ($params) {
        $sql = "SELECT * FROM contact
                WHERE contact_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert contacts
    function add_contact($params) {
        return $this->db->insert('contact', $params);
    }

    // update contacts
    function update_contact($contact_id, $params) {
        $this->db->where('contact_id', $contact_id);
        return $this->db->update('contact', $params);
    }

    // delete contacts
    function delete_contact($contact_id) {
        $this->db->where('contact_id', $contact_id);
        return $this->db->delete('contact');
    }
    /** 
     * PUBLIC PAGE
     */

    // get detail contact aktif
    function get_detail_by_contact($params) {
        $sql = "SELECT * 
                FROM contact
                WHERE status_contact = '0' 
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
    function get_list_contact() {
        $sql = "SELECT * 
                FROM contact
                WHERE status_contact = '0'
                ORDER BY nama_user ";
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
