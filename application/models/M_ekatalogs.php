<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_ekatalogs extends CI_Model
{

    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

    public function get_list_ekatalog() {
        $sql = "SELECT data_katalog.*
                FROM data_katalog
                WHERE data_katalog.katalog_st = '0'";
        
        
    $query = $this->db->query($sql);
    if ($query->num_rows() > 0) {
    $result = $query->row_array();
   
    return $result;
    } else {
    return array();
    }
}



public function get_list_product_ekatalog() {
    $sql = "SELECT * FROM data_product
    LEFT JOIN data_categories ON data_categories.cat_id = data_product.cat_id
    LEFT JOIN data_katalog ON data_categories.katalog_id = data_katalog.katalog_id
    WHERE data_product.product_st = '0'";

    $query = $this->db->query($sql);
    if ($query->num_rows() > 0) {
        $result = $query->result_array(); // Mengambil semua data dalam bentuk array
        return $result;
    } else {
        return array();
    }
}

    
}