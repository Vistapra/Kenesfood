<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_settings extends CI_Model {

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    // get last inserted id
    function get_last_inserted_id() {
        return $this->db->insert_id();
    }

    // ------------------
    // <editor-fold defaultstate="collapsed" desc="PORTAL MANAGEMENT">
    // get last id
    function get_portal_last_id() {
        $sql = "SELECT LEFT(portal_id, 1)'last_number'
                FROM app_portal
                ORDER BY portal_id DESC
                LIMIT 1";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            // create next number
            $number = intval($result['last_number']) + 1;
            if ($number >= 10) {
                return false;
            }
            $zero = '';
            for ($i = strlen($number); $i < 2; $i++) {
                $zero .= '0';
            }
            return $number . $zero;
        } else {
            // create new number
            return '10';
        }
    }

    // get total data
    function get_total_data_portal() {
        $sql = "SELECT COUNT(*)'total' FROM app_portal";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['total'];
        } else {
            return 0;
        }
    }

    // get all portal
    function get_all_portal() {
        $sql = "SELECT * FROM app_portal ORDER BY portal_id ASC";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get detail data by id
    function get_portal_by_id($portal_id) {
        $sql = "SELECT * FROM app_portal WHERE portal_id = ?";
        $query = $this->db->query($sql, $portal_id);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert data portal
    function insert_portal($params) {
        return $this->db->insert('app_portal', $params);
    }

    // update data portal
    function update_portal($params, $where) {
        return $this->db->update('app_portal', $params, $where);
    }

    // delete data portal
    function delete_portal($params) {
        return $this->db->delete('app_portal', $params);
    }

    // </editor-fold>
    // ------------------
    // ------------------
    // <editor-fold defaultstate="collapsed" desc="GROUPS MANAGEMENT">
    // get last id
    function get_group_last_id() {
        $sql = "SELECT group_id'last_number' FROM app_group ORDER BY group_id DESC LIMIT 1";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            // create next number
            $number = intval($result['last_number']) + 1;
            if ($number >= 99) {
                return false;
            }
            $zero = '';
            for ($i = strlen($number); $i < 2; $i++) {
                $zero .= '0';
            }
            return $zero . $number;
        } else {
            // create new number
            return '01';
        }
    }

    // get total group
    function get_total_data_group() {
        $sql = "SELECT COUNT(*)'total' FROM app_group";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['total'];
        } else {
            return 0;
        }
    }

    // get all group
    function get_all_group() {
        $sql = "SELECT * FROM app_group ORDER BY group_id ASC";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get detail group by id
    function get_group_by_id($group_id) {
        $sql = "SELECT * FROM app_group WHERE group_id = ?";
        $query = $this->db->query($sql, $group_id);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert data group
    function insert_group($params) {
        return $this->db->insert('app_group', $params);
    }

    // update data group
    function update_group($params, $where) {
        return $this->db->update('app_group', $params, $where);
    }

    // delete data group
    function delete_group($params) {
        return $this->db->delete('app_group', $params);
    }

    // </editor-fold>
    // ------------------
    // ------------------
    // <editor-fold defaultstate="collapsed" desc="ROLE MANAGEMENT">
    // get last id
    function get_role_last_id($group_id) {
        $sql = "SELECT RIGHT(role_id, 3)'last_number'
                FROM app_role
                WHERE group_id = ?
                ORDER BY role_id DESC
                LIMIT 1";
        $query = $this->db->query($sql, $group_id);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            // create next number
            $number = intval($result['last_number']) + 1;
            if ($number > 999) {
                return false;
            }
            $zero = '';
            for ($i = strlen($number); $i < 3; $i++) {
                $zero .= '0';
            }
            return $group_id . $zero . $number;
        } else {
            // create new number
            return $group_id . '001';
        }
    }

    // get all roles
    function get_all_roles($params) {
        $sql = "SELECT b.group_name, a.*
                FROM app_role a
                INNER JOIN app_group b ON a.group_id = b.group_id
                WHERE a.role_nm LIKE ? AND b.group_id LIKE ?
                ORDER BY a.role_id ASC";
        $query = $this->db->query($sql, $params);
        // echo $this->db->last_query();
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get detail role by id
    function get_detail_role_by_id($id_role) {
        $sql = "SELECT b.group_name, a.*
                FROM app_role a
                INNER JOIN app_group b ON a.group_id = b.group_id
                WHERE role_id = ?";
        $query = $this->db->query($sql, $id_role);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert role
    function insert_role($params) {
        return $this->db->insert('app_role', $params);
    }

    // update role
    function update_role($params, $where) {
        return $this->db->update('app_role', $params, $where);
    }

    // delete role
    function delete_role($params) {
        return $this->db->delete('app_role', $params);
    }

    // insert role menu
    function insert_role_menu($params) {
        $sql = "INSERT INTO app_role_menu (role_id, nav_id, role_tp) VALUES (?, ?, ?)";
        return $this->db->query($sql, $params);
    }

    // delete role menu
    function delete_role_menu($params) {
        $sql = "DELETE a.* FROM app_role_menu a
                INNER JOIN app_menu b ON a.nav_id = b.nav_id
                WHERE role_id = ? AND b.portal_id = ?";
        return $this->db->query($sql, $params);
    }

    // delete all role menu not in portal
    function delete_all_role_menu_by_role($params) {
        $sql = "DELETE FROM app_role_menu
                WHERE role_id = ?";
        return $this->db->query($sql, $params);
    }

    // </editor-fold>
    // ------------------
    // ------------------
    // <editor-fold defaultstate="collapsed" desc="MENU MANAGEMENT">
    // // get last id
    function get_nav_last_id($portal_id) {
        $sql = "SELECT RIGHT(nav_id, 8) AS 'last_number'
                FROM app_menu
                ORDER BY RIGHT(nav_id, 8) DESC
                LIMIT 1";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            // create next number
            $number = intval($result['last_number']) + 1;
            if ($number > 99999999) {
                return false;
            }
            $zero = '';
            for ($i = strlen($number); $i < 8; $i++) {
                $zero .= '0';
            }
            return $portal_id . $zero . $number;
        } else {
            // create new number
            return $portal_id . '00000001';
        }
    }

    // get all portal with menu
    function get_all_portal_menu() {
        $sql = "SELECT a.*, COUNT(b.nav_id)'total_menu'
                FROM app_portal a
                LEFT JOIN app_menu b ON a.portal_id = b.portal_id
                GROUP BY a.portal_id
                ORDER BY a.portal_id ASC";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get all menu by parent
    function get_all_menu_by_parent($params) {
        $sql = "SELECT * FROM app_menu
                WHERE portal_id = ? AND parent_id = ?
                ORDER BY nav_no ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get all menu by parent
    function get_all_menu_selected_by_parent($params) {
        $sql = "SELECT a.*, b.role_id, b.role_privelege
                FROM app_menu a
                LEFT JOIN (SELECT * FROM app_role_menu WHERE role_id = ?) b ON a.menu_id = b.menu_id
                WHERE portal_id = ? AND parent_id = ?
                ORDER BY nav_no ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    //get_list_portal by role_id
    function get_list_portal_by_role_id($params) {
        $sql = "SELECT DISTINCT c.* FROM app_menu a
                LEFT JOIN (SELECT * FROM app_role_menu WHERE role_id = ?) b ON a.menu_id = b.menu_id
                INNER JOIN app_portal c ON a.portal_id = c.portal_id
                ORDER BY nav_no ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get detail menu by id
    function get_detail_menu_by_id($id_role) {
        $sql = "SELECT * FROM app_menu WHERE nav_id = ?";
        $query = $this->db->query($sql, $id_role);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert menu
    function insert_menu($params) {
        return $this->db->insert('app_menu', $params);
    }

    // update menu
    function update_menu($params, $where) {
        return $this->db->update('app_menu', $params, $where);
    }

    // delete menu
    function delete_menu($params) {
        return $this->db->delete('app_menu', $params);
    }

    // update parent
    function update_parent($params) {
        $sql = "UPDATE app_menu SET parent_id = ? WHERE parent_id = ?";
        return $this->db->query($sql, $params);
    }

    // </editor-fold>
    // ------------------
    // <editor-fold defaultstate="collapsed" desc="PREFERENCES MANAGEMENT">
    // // get last id
    // get all preferences
    function get_all_preferences() {
        $sql = "SELECT * FROM app_preferences
                WHERE preference_id > 10
                ORDER BY preference_group ASC, preference_name ASC";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get all preferences by range
    function get_all_preferences_by_range($params) {
        $sql = "SELECT * FROM app_preferences
                WHERE preference_id >= ? AND  preference_id <= ?
                ORDER BY preference_group ASC, preference_name ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get all preferences
    function get_all_preferences_by_params($params) {
        $sql = "SELECT * FROM app_preferences
                WHERE preference_nm LIKE ? AND preference_group LIKE ?
                AND preference_label IS NULL
                ORDER BY preference_name ASC";
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
    function get_all_preferences_group() {
        $sql = "SELECT * FROM app_preferences
                GROUP BY preference_group
                ORDER BY preference_group ASC";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get all preferences by range
    function get_all_preferences_by_group($params) {
        $sql = "SELECT * FROM app_preferences
                WHERE ppreference_group = ?
                ORDER BY preference_nm ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get all preferences by group name
    function get_value_preferences_by_group_name($params) {
        $sql = "SELECT * FROM app_preferences
                WHERE ppreference_group = ? AND preference_name = ?
                ORDER BY preference_name ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['preference_value'];
        } else {
            return '';
        }
    }

    //get detail preferences
    function get_preference_by_id($pref_id) {
        $sql = "SELECT * FROM app_preferences WHERE preference_id = ?";
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
    function get_preference_by_group_id($params) {
        $sql = "SELECT * FROM app_preferences WHERE preference_group = ? AND preference_id = ?";
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
    function insert_preferences($params) {
        return $this->db->insert('app_preferences', $params);
    }

    // update preferences
    function update_preferences($params, $where) {
        return $this->db->update('app_preferences', $params, $where);
    }

    // delete preferences
    function delete_preferences($where) {
        return $this->db->delete('app_preferences', $where);
    }

    // get all preferences by range
    function get_all_preferences_by_label($params) {
        $sql = "SELECT * FROM app_preferences
                WHERE preference_label = ?
                ORDER BY preference_name ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // </editor-fold>
}
