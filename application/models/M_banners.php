<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_banners extends CI_Model
{

    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

    public function get_catalogues() {
        $sql = "SELECT banner_id, banner_name, banner_img
        FROM data_banners
        WHERE status_catalogue = '0'";

        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

   public function get_list_banner() {
    $sql = "SELECT data_banners.*
            FROM data_banners
            WHERE data_banners.banner_st = '0'";
    
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