<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_seasonal extends CI_Model
{

    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }


    // function get_list_product_seasonal($params) {
    //     $sql = "SELECT 
    //                 prod.*
    //             FROM `data_seasonal` prod
    //             WHERE prod.`seasonal_st` = '0'
    //         ";
    //     $query = $this->db->query($sql);
     
    //     if ($query->num_rows() > 0) {
    //         $result = $query->result_array();
    //         $query->free_result();
    //         return $result;
    //     } else {
    //         return array();
    //     }
    // }
    // public function get_list_product_seasonal($id) {
    //     $query = $this->db->get_where('data_seasonal', array('season_id' => 1));
    //     return $query->row_array(); // Mengembalikan satu baris hasil
    // }
    public function get_list_product_seasonal()
    {
        $sql = "SELECT 
        data_seasonal.* 
    FROM data_seasonal 
    WHERE data_seasonal.season_st = '0'
   
     ";

    $query = $this->db->query($sql);
    if ($query->num_rows() > 0) {
    $result = $query->row_array();
   
    return $result;
    } else {
    return array();
    }
    }
    
    public function getProductSeasonalByCategory($id)
    {
        $sql = "SELECT 
        data_product.* 
    FROM data_product 
    WHERE data_product.cat_id = $id 
    AND data_product.product_parent = '0'
    -- AND data_product.status_product = 'Product'
    ORDER BY data_product.product_name ASC ";

    $query = $this->db->query($sql);
    if ($query->num_rows() > 0) {
    $result = $query->result_array();
   
    return $result;
    } else {
    return array();
    }
    }
  
}

