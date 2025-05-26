<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_promotions extends CI_Model
{
    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }


    function get_list_data_promotion($brands)
    {
        $this->db->where('brands', $brands);
        $query = $this->db->get('promotion');
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    public function get_promotion_by_code($promo_code) {
        // Query ke database untuk mengambil data promosi berdasarkan promo_code
        $query = $this->db->where('promotion_code', $promo_code)
            ->get('promotion');

        // Periksa apakah data ditemukan
        if ($query->num_rows() > 0) {
            return $query->row_array(); // Mengembalikan data promosi sebagai array asosiatif
        } else {
            return false; // Jika tidak ada data yang sesuai, mengembalikan false
        }
    }

    function get_detail_data_promotion($id)
    {
        //=== $asd = $this->get_list_data_products();
        $query = $this->db->get_where('promotion', array('promotion_id' => $id));
        return $query->row();

        //  return $query->result();
    }
    public function edit_data_promotion($id, $data)
    {
        $this->db->where('promotion_id', $id);
        $data['modified'] = date('Y-m-d H:i:s');
        $this->db->update('promotion', $data);
    }
    function delete_promotion_data($id)
    
    {
        
        
        $query = $this->db->get_where('promotion', array('promotion_id' => $id));
        if ($query->num_rows() > 0) {
            $this->db->where('promotion_id', $id);
            $this->db->delete('promotion');
            return true; // Jika berhasil menghapus
        } else {
            return false; // Jika data tidak ditemukan
        }
    }

    function add_promotion_data($data)
    {
        // $params['modified'] = date('Y-m-d H:i:s');
        return $this->db->insert('promotion', $data);
    }

    
}