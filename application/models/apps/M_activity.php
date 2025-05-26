<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class M_activity extends CI_Model {

    function __construct() {
        // constructor
        parent::__construct();
    }

    // get data id
    public function get_microtime() {
        $time = microtime(true);
        $id = str_replace('.', '', $time);
        return $id;
    }

    public function get_last_query() {
        return $this->db->last_query();
    }

    public function insert_activity($params) {
        return $this->db->insert('app_user_activity', $params);
    }

}