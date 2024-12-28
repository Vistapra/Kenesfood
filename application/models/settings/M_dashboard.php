<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_dashboard extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    // get total product
    function get_total_product() {
        $sql = "SELECT 
                    COUNT(IF(`product_promote` = 'arrival', 1, NULL)) AS arrival,
                    COUNT(IF(`product_promote` = 'prelaunch', 1, NULL)) AS prelaunch,
                    COUNT(IF(`product_st` = '0', 1, NULL)) AS aktif,
                    COUNT(IF(`created` BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 10 DAY) AND CURRENT_DATE, 1, NULL)) AS new_added,
                    COUNT(IF(`product_brand` = 'bakery', 1, NULL)) AS bakery,
                    COUNT(IF(`product_brand` = 'kopitiam', 1, NULL)) AS kopitiam,
                    COUNT(IF(`product_brand` = 'resto', 1, NULL)) AS resto,
                    COUNT(*) AS total_product
                FROM data_product";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get visitor log
    function get_visitor_log() {
        $sql = "SELECT 
                    COUNT(IF(DATE(`created`) = CURRENT_DATE, 1, NULL)) AS today,
                    COUNT(IF(DATE(`created`) BETWEEN SUBDATE(CURRENT_DATE, WEEKDAY(CURRENT_DATE)) AND DATE(CURRENT_DATE + INTERVAL (6 - WEEKDAY(CURRENT_DATE)) DAY), 1, NULL)) AS weekly,
                    COUNT(IF(DATE_FORMAT(`created`, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m'), 1, NULL)) AS monthly
                FROM visitor_log";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }
}