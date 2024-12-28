<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_History extends CI_Model
{
    function __construct() {
        parent::__construct();
    }

    function get_list_data_history() {
        $this->db->select('p.purchase_id, p.purchase_code, m.fullname AS purchase_member, p.purchase_date, p.purchase_status, p.purchase_total_amount, p.purchase_promo_redeem_id, p.created, p.created_by, p.modified, p.modified_by');
        $this->db->from('purchase_histories p');
        $this->db->join('data_member m', 'p.purchase_member = m.user_id', 'inner');
        $query = $this->db->get();
    
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }
function get_detail_data_history($id)
{
    $this->db->select('
        pd.purchase_detail_id,
        pd.purchase_id,
        pd.product_id,
        pd.product_price,
        pd.product_qty,
        pd.product_discount,
        pd.product_discount_amount,
        pd.product_discount_type,
        ph.purchase_code,
        ph.purchase_date,
        ph.purchase_status,
        ph.purchase_total_amount,
        ph.purchase_promo_redeem_id,
        ph.created,
        ph.created_by,
        ph.modified,
        ph.modified_by,
        dp.product_name,
        dp.product_type,
        dp.product_price AS product_original_price
    ');
    $this->db->from('purchase_history_details pd');
    $this->db->join('purchase_histories ph', 'pd.purchase_id = ph.purchase_id');
    $this->db->join('data_product dp', 'pd.product_id = dp.product_id', 'left');
    $this->db->where('pd.purchase_id', $id);
    $query = $this->db->get();

    return $query->result();
}

public function delete_data_history($id) {
    // Menghapus data dari tabel anak (data_member)
    $this->db->where('purchase_id', $id);
        $this->db->delete('purchase_histories');
        
        // Menghapus data dari tabel induk (purchase_history_details)
        $this->db->where('purchase_id', $id);
        $this->db->delete('purchase_history_details');

        if ($this->db->affected_rows() > 0) {
            return true; // Operasi penghapusan berhasil
        } else {
            return false; // Operasi penghapusan gagal
        }
   
}


}