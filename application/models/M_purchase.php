<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_purchase extends CI_Model
{
    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

    public function get_last_cart_sequence()
    {
        $this->db->select_max('purchase_code');
        $query = $this->db->get('purchase_histories');
        $row = $query->row();
        return $row->purchase_code;
    }

    function add_cart_checkout($data_cart_checkout)
    {
        $this->db->insert('purchase_histories', $data_cart_checkout);
    }

    function add_cart_checkout_details($saveData)
    {
        $this->db->insert('purchase_history_details', $saveData);
    }

    // get last inserted id
    function get_last_inserted_id()
    {
        return $this->db->insert_id();
    }

    function get_list_cart_products($purchase_member)
    {
        $this->db->select('td.product_id, td.purchase_id,  td.qty, p.product_name, td.product_price, p.product_pict');
        $this->db->from('temp_cart_details td'); // Gunakan alias "td" untuk tabel "temp_cart_detail"
        $this->db->join('temp_cart c', 'td.purchase_id = c.purchase_id', 'left');
        $this->db->join('data_product p', 'td.product_id = p.product_id', 'left'); // Menggunakan kunci primer product_id
        $this->db->where('c.purchase_member', $purchase_member);

        $query = $this->db->get();

        return $query->result_array();

        // Menghitung subtotal untuk setiap produk dalam keranjang
    }

    public function getWhatsAppNumber($user_id)
    {
        // Gantilah 'app_user' dengan nama tabel yang sesuai dalam database Anda
        $this->db->select('phone');
        $this->db->where('user_id', $user_id);
        $query = $this->db->get('data_member');

        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->phone;
        } else {
            return null; // Nomor WhatsApp tidak ditemukan
        }
    }

    public function getLatestPurchaseCode($purchase_id)
    {
        $query = $this->db->get_where('purchase_histories', array('purchase_id' => $purchase_id));
        $row = $query->row();
        return $row->purchase_code;
    }
    public function checkPromoCode($promoCode) {
        // Query database untuk mencari kode promo
        $query = $this->db->get_where('promotion', array('promotion_code' => $promoCode, 'promotion_st' => '0'));
        $result = $query->row();
       
        if ($result) {
            return $result;

        } else {

            return false;
        }
    }
}
