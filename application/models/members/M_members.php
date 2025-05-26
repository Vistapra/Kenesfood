<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_members extends CI_Model {

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    /**
     * Mendapatkan semua data member dengan data app_user
     */
    function get_list_data_members_with_app_user() {
        $this->db->select('app_user.user_id, app_user.user_code, app_user.user_name, app_user.user_email, data_member.fullname, data_member.phone');
        $this->db->from('app_user');
        $this->db->join('data_member', 'app_user.user_id = data_member.user_id', 'left');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    /**
     * Mendapatkan detail data member berdasarkan ID
     */
    function get_detail_data($id) {
        $this->db->select('*'); 
        $this->db->from('app_user');
        $this->db->join('data_member', 'app_user.user_id = data_member.user_id');
        $this->db->where('app_user.user_id', $id);
       
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return $query->row_array(); // Mengambil satu baris data sebagai array
        } else {
            return null;
        }
    }
    
    /**
     * Mendapatkan detail data member berdasarkan nomor telepon
     */
    function get_member_by_phone($phone) {
        $this->db->select('app_user.user_id, app_user.user_name, app_user.user_email, data_member.*');
        $this->db->from('data_member');
        $this->db->join('app_user', 'app_user.user_id = data_member.user_id');
        $this->db->where('data_member.phone', $phone);
        
        $query = $this->db->get();
        
        if ($query->num_rows() > 0) {
            return $query->row_array();
        } else {
            return null;
        }
    }
    
    /**
     * Menyimpan data member baru
     */
    function save_member($data) {
        $this->db->insert('data_member', $data);
        
        if ($this->db->affected_rows() > 0) {
            return $this->db->insert_id();
        } else {
            return false;
        }
    }
    
    /**
     * Mengupdate data member
     */
    function edit_data_member($id, $datamember) {
        $this->db->where('user_id', $id);
        return $this->db->update('data_member', $datamember);
    }

    /**
     * Menghapus data member dan user
     */
    function delete_data_member($id) {
        // Menghapus data dari tabel anak (data_member)
        $this->db->where("user_id IN (SELECT user_id FROM app_user WHERE user_id='$id')");
        $result1 = $this->db->delete('data_member');

        // Menghapus data dari tabel induk (app_user)
        $this->db->where('user_id', $id);
        $result2 = $this->db->delete('app_user');
        
        return ($result1 && $result2);
    }

    /**
     * Mengupdate ID MRP
     */
    function update_mrp_id($member_id, $mrp_id) {
        $this->db->where('id', $member_id);
        return $this->db->update('data_member', ['mrp_id' => $mrp_id]);
    }

    /**
     * Mendapatkan total member
     */
    function get_total_members() {
        return $this->db->count_all_results('data_member');
    }
    
    /**
     * Mendapatkan member baru dalam periode tertentu
     */
    function get_new_members($days = 30) {
        $this->db->where('created >=', date('Y-m-d H:i:s', strtotime("-$days days")));
        return $this->db->count_all_results('data_member');
    }
}