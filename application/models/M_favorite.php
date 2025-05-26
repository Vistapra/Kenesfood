<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_favorite extends CI_Model
{
    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

    // get list data
    // function get_list_data_products($product_id)
    // {
    //     $query = $this->db->get_where('data_product', array('product_id' => $product_id,'product_parent' => '0', 'product_st' => '0'));
    //      return $query->result();
    //     // return $query->row(); 
    // }

    function get_list_data_products()
    {
        $query = $this->db->get_where('data_product', array('product_parent' => '0', 'product_st' => '0'));
        return $query->result();
        // return $query->row(); 
    }


    function add_favorite_data($data_favorite)
    {
        return $this->db->insert('tmp_favorite', $data_favorite);
    }


    public function get_user_favorite_products($user_id) {
        $this->db->select('*'); // Menggunakan semua kolom dari data_product
        $this->db->from('tmp_favorite');
        $this->db->join('data_product', 'data_product.product_id = tmp_favorite.product_id');
        $this->db->where('tmp_favorite.user_id', $user_id);
        $this->db->distinct('tmp_favorite.product_id'); // Menggunakan DISTINCT pada kolom product_id dari tmp_favorite
    
        $query = $this->db->get();
    
        return $query->result_array();
    }

    public function isProductFavorite($user_id, $product_id) {
        $this->db->select('product_id');
        $this->db->from('tmp_favorite');
        $this->db->where('user_id', $user_id);
        $this->db->where('product_id', $product_id);

        $hasil = $this->db->get();
        
        return $hasil->result_array();
    }

    public function removeProductFromFavorite($user_id, $product_id){       
            $this->db->where('user_id', $user_id);
            $this->db->where('product_id', $product_id);
            $this->db->delete('tmp_favorite');
        
    }
    
}
