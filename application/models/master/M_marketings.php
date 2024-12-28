<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_marketings extends CI_Model
{

    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

    // get total data
    public function get_total_data($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                data_marketing.nama_marketing LIKE '%" . $keyword ."%'
                OR data_marketing.phone_marketing LIKE '%" . $keyword ."%'
                OR data_marketing.marketing_st LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM data_marketing 
                WHERE 1=1
                " . $conditions;
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['total'];
        } else {
            return 0;
        }
    }

  // get list data
  function get_list_data($params, $keyword) {
    // conditions
    $conditions = '';
    if($keyword != NULL) {
        $conditions .= " AND (
            data_marketing.nama_marketing LIKE '%" . $keyword ."%'
            OR data_marketing.phone_marketing LIKE '%" . $keyword ."%'
            OR data_marketing.marketing_st LIKE '%" . $keyword ."%'
        )";
    }
        $sql = "SELECT 
        data_marketing.* 
        FROM data_marketing WHERE marketing_st = '0'
        " . $conditions . "
        LIMIT ?, ?";
        $query = $this->db->query($sql, $params);
    if ($query->num_rows() > 0) {
        $result = $query->result_array();
        $query->free_result();
        // echo "<pre>";
        // print_r($result); 
        // echo "</pre>";
        // exit();
        return $result;
    } else {
        return array();
    }
}

function is_exist_by_field ($params) {
    $sql = "SELECT COUNT(*) AS count FROM data_marketing WHERE ";
    $paramsValue = [];
    $countParams = count($params);

    $sql = $sql .$params[0]["field"] . $params[0]["query"][0]."?";
    array_push($paramsValue, $params[0]["query"][1]);

    if(count($params) > 1) {
        for($i = 1; $i < $countParams; $i++) {
            $sql = $sql . " AND ".$params[$i]["field"].$params[$i]["query"][0]."?";
            array_push($paramsValue, $params[$i]["query"][1]);
        }
    }

    $query = $this->db->query($sql, $paramsValue);

    if (intval($query->row()->count) > 0) {
        return true;
    }

    return false;
    
}

function is_exist_by_name($params) {
    $sql = "SELECT COUNT(*) AS count FROM data_marketing 
        WHERE LOWER(nama_marketing) = LOWER(?)
        LIMIT 1";

    $query = $this->db->query($sql, $params);

    if(intval($query->row()->count) > 0) {
        return true;
    }
    
    return false;
}

function is_exist_by_name_id($params) {
    $sql = "SELECT COUNT(*) AS count FROM data_marketing 
        WHERE NOT marketing_id = ?
        AND LOWER(nama_marketing) = LOWER(?)
        LIMIT 1";

    $query = $this->db->query($sql, $params);

    if(intval($query->row()->count) > 0) {
        return true;
    }
    
    return false;
}

function is_exist_by_field_id ($params, $id) {
    $sql = "SELECT COUNT(*) AS count FROM data_marketing
    WHERE NOT marketing_id=".$id;
    $paramsValue = [];

    foreach($params as $value) {
        $sql = $sql . " AND ".$value["field"].$value["query"][0]."?";
        array_push($paramsValue, $value["query"][1]);
    }

    $query = $this->db->query($sql, $paramsValue);

    if (intval($query->row()->count) > 0) {
        return true;
    }

    return false;
    
}

function get_detail_marketing ($params) {
    $sql = "SELECT * FROM data_marketing
            WHERE marketing_id = ?";
    $query = $this->db->query($sql, $params);
    if ($query->num_rows() > 0) {
        $result = $query->row_array();
        $query->free_result();
        return $result;
    } else {
        return array();
    }
}

    // insert marketings
    function add_marketings($params) {
        $query = $this->db->insert('data_marketing', $params);

        if(!$query) {
            return $this->db->error();
        }

        return $query;
    }

    // update marketings
    function update_marketings($marketing_id, $params) {
        $this->db->where('marketing_id', $marketing_id);

        if($this->db->update('data_marketing', $params)) {
            return [
                'success' => true,
                'data' => $this->db->affected_rows()
            ];
        } else {
            return [
                'success' => false,
                'data' => $this->db->error()
            ];
        }
        
    }

    // delete maerketings
    function delete_marketings($marketing_id) {
        $this->db->where('marketing_id', $marketing_id);
        return $this->db->delete('data_marketing');
    }

    /** 
     * PUBLIC PAGE
     */

    // get detail katalog aktif
    function get_detail_by_marketing($params) {
        $sql = "SELECT * 
                FROM data_marketing
                WHERE marketing_st = '0' 
                LIMIT 1
            ";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    function get_list_marketing() {
        $sql = "SELECT * 
                FROM data_marketing
                WHERE marketing_st = '0'
                ORDER BY nama_marketing ";
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