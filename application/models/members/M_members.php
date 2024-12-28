<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_members extends CI_Model {

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    function get_list_data_members_with_app_user() {
        $this->db->select('app_user.user_id, app_user.user_code, app_user.user_name, app_user.user_email, data_member.fullname');
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

    function get_detail_data_member($id) {
        $this->db->select('*'); 
        $this->db->from('app_user');
        $this->db->join('data_member', 'app_user.user_id = data_member.user_id');
        $this->db->where('app_user.user_id', $id);
       

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return $query->row(); // Mengambil satu baris data
        } else {
            return null;
        }
    }
    

   
    public function edit_data_member($id, $datamember) {
        $this->db->where('user_id', $id);
        $this->db->update('data_member', $datamember);
       
    }

    public function delete_data_member($id) {
        // Menghapus data dari tabel anak (data_member)
        $this->db->where("user_id IN (SELECT user_id FROM app_user WHERE user_id='$id')");
        $this->db->delete('data_member');

        // Menghapus data dari tabel induk (app_user)
        $this->db->where('user_id', $id);
        $this->db->delete('app_user');
    }


    public function edit_app_user($id, $appuser) {
        $this->db->where('user_id', $id);
        $this->db->update('app_user', $appuser);
    }

    public function add_data_member() {
        
    }
}