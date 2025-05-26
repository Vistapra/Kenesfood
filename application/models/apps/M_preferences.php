<?php

class M_preferences extends CI_Model {

    public function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    // get all preferences by range
    public function get_all_preference_by_group($params) {
        $sql = "SELECT * FROM app_preferences
                WHERE pref_group = ?
                ORDER BY pref_name ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get all preferences by range
    public function get_all_preference_by_group_label($params) {
        $sql = "SELECT * FROM app_preferences
                WHERE site_id = ? AND pref_group = ?
                ORDER BY pref_name ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $array = [];
            if(!empty($result)){
                foreach($result as $res){
                    $array[$res['pref_name']] = $res['pref_value'];
                }
            }
            $query->free_result();
            return $array;
        } else {
            return array();
        }
    }

    // get all preferences by group name
    public function get_value_preference_by_group_name($params) {
        $sql = "SELECT * FROM app_preferences
                WHERE pref_group = ? AND pref_name = ?
                ORDER BY pref_name ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['preference_value'];
        } else {
            return '';
        }
    }

    // get all preferences by portalid
    public function get_value_preference_by_portal_id($params) {
        $sql = "SELECT * FROM app_preferences
                WHERE site_id = ? AND pref_group = ?
                ORDER BY pref_name ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $array = [];
            if(!empty($result)){
                foreach($result as $res){
                    $array[$res['pref_label']][$res['pref_name']] = $res['pref_value'];
                }
            }
            $query->free_result();
            return $array;
        } else {
            return '';
        }
    }

    //get detail preferences
    public function get_preference_by_id($pref_id) {
        $sql = "SELECT * FROM app_preferences WHERE pref_id = ?";
        $query = $this->db->query($sql, $pref_id);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get detail preferences
    public function get_preference_by_group_id($params) {
        $sql = "SELECT * FROM app_preferences WHERE pref_group = ? AND pref_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    /** 
     *  INDEX, DETAIL, CREATE, EDIT, DELETE
     */

    // get total data
    public function get_total_data($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                app_preferences.pref_group LIKE '%" . $keyword ."%'
                OR app_preferences.pref_label LIKE '%" . $keyword ."%'
                OR app_preferences.pref_name LIKE '%" . $keyword ."%'
                OR app_preferences.pref_value LIKE '%" . $keyword ."%'
                OR app_portal.site_title LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  
                FROM app_preferences 
                INNER JOIN app_portal ON app_portal.site_id = app_preferences.site_id
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
                app_preferences.pref_group LIKE '%" . $keyword ."%'
                OR app_preferences.pref_label LIKE '%" . $keyword ."%'
                OR app_preferences.pref_name LIKE '%" . $keyword ."%'
                OR app_preferences.pref_value LIKE '%" . $keyword ."%'
                OR app_portal.site_title LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    app_preferences.*,
                    app_portal.site_id,
                    app_portal.site_type,
                    app_portal.site_title
                FROM app_preferences 
                INNER JOIN app_portal ON app_portal.site_id = app_preferences.site_id
                WHERE 1=1
                " . $conditions . "
                ORDER BY app_preferences.pref_group
                LIMIT ?, ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    function get_detail_preference ($params) {
        $sql = "SELECT 
                    app_preferences.*,
                    app_portal.site_id,
                    app_portal.site_type,
                    app_portal.site_title
                FROM app_preferences
                INNER JOIN app_portal ON app_portal.site_id = app_preferences.site_id
                WHERE pref_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert preferences
    function add_preferences($params) {
        return $this->db->insert('app_preferences', $params);
    }

    // update preferences
    function update_preferences($preference_id, $params) {
        $this->db->where('pref_id', $preference_id);
        return $this->db->update('app_preferences', $params);
    }

    // delete preferences
    function delete_preferences($preference_id) {
        $this->db->where('pref_id', $preference_id);
        return $this->db->delete('app_preferences');
    }
}
