<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_site extends CI_Model {

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    // get last nav id
    function get_nav_id_by_parent($params) {
        $conditions = '';
        if($params['parent_id'] == '0') {
            $conditions = ' AND nav_id <> 2099000';
        }
        $sql = "SELECT LEFT(nav_id, 4) 'parent_id', RIGHT(nav_id, 3) 'last_number'
                FROM app_menu
                WHERE parent_id = ? " . $conditions . "
                ORDER BY nav_id DESC
                LIMIT 1";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            if($params['parent_id'] == '0') {
                $result['parent_id'] = $result['parent_id'] + 1;
                $number = '0';
                $zero = '00';
            } else {
                // create next number
                $number = intval($result['last_number']) + 1;
                if ($number >= 999) {
                    return false;
                }
                $zero = '';
                for ($i = strlen($number); $i < 3; $i++) {
                    $zero .= '0';
                }
            }
            return $result['parent_id'] . $zero . $number;
        } else {
            // create new number
            return substr($params['parent_id'], 0, 4) . '001';
        }
    }

    // get last nav number
    function get_nav_no_by_parent($params) {
        $conditions = '';
        if($params['parent_id'] == '0') {
            $conditions = ' AND nav_id <> 2099000';
        }
        $sql = "SELECT SUBSTR(nav_id, 4, 1) 'parent_no', RIGHT(nav_no, 2) 'last_number'
                FROM app_menu
                WHERE parent_id = ? " . $conditions . "
                ORDER BY nav_id DESC
                LIMIT 1";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            if($params['parent_id'] == '0') {
                $result['parent_no'] = $result['parent_no'] + 1;
                $number = '0';
                $zero = '0';
            } else {
                // create next number
                $number = intval($result['last_number']) + 1;
                if ($number >= 99) {
                    return false;
                }
                $zero = '';
                for ($i = strlen($number); $i < 2; $i++) {
                    $zero .= '0';
                }
            }
            return $result['parent_no'] . $zero . $number;
        } else {
            // create new number
            return substr($params['parent_id'], 3, 1) . '01';
        }
    }

    // get list portal
    function get_list_portal() {
        $sql = "SELECT * FROM app_portal WHERE site_status = '0'";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get site data
    function get_site_data_by_id($site_id) {
        $sql = "SELECT * FROM app_portal WHERE site_id = ?";
        $query = $this->db->query($sql, $site_id);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return false;
        }
    }

    // get current page
    function get_current_page($params) {
        $sql = "SELECT * FROM app_menu 
                WHERE nav_url = ? AND site_id = ?
                ORDER BY nav_no DESC 
                LIMIT 0, 1";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return false;
        }
    }

    // get current page by group portal
    function get_current_page_by_group_portal($params) {
        $sql = "SELECT * FROM app_menu 
                WHERE nav_url = ? AND site_id LIKE ?
                ORDER BY nav_no DESC 
                LIMIT 0, 1";
        $query = $this->db->query($sql, $params);
        // echo $this->db->last_query();
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get menu by id
    function get_menu_by_id($params) {
        $sql = "SELECT 
                    app_menu.*, 
                    parent.nav_title AS parent_menu, 
                    parent.nav_icon AS parent_icon,
                    created.user_alias AS created_by,
                    modified.user_alias AS modified_by
                FROM app_menu 
                LEFT JOIN app_menu parent ON app_menu.parent_id = parent.nav_id
                LEFT JOIN app_user created ON app_menu.created_by = created.user_id
                LEFT JOIN app_user modified ON app_menu.modified_by = modified.user_id
                WHERE app_menu.nav_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return false;
        }
    }

    // get menu by url
    function get_menu_by_url($params) {
        $sql = "SELECT * FROM app_menu WHERE nav_url = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return false;
        }
    }

    // get parent menu by url
    function get_parent_menu_by_url($params) {
        $sql = "SELECT * FROM app_menu WHERE nav_url = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            if($result['parent_id'] != 0) {
                $result = $this->get_parent_menu_by_id(array($result['parent_id']));
            }
            $query->free_result();
            return $result;
        } else {
            return false;
        }
    }

    // get parent menu by id
    function get_parent_menu_by_id($params) {
        $sql = "SELECT * FROM app_menu WHERE nav_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            if($result['parent_id'] != 0) {
                $result = $this->get_parent_menu_by_id(array($result['parent_id']));
            }
            $query->free_result();
            return $result;
        } else {
            return false;
        }
    }

    // get list menu
    function get_menu_by_user($params) {
        $sql = "SELECT a.*, CONCAT(b.`read`, b.`create`, b.`edit`, b.`delete`) AS permission
                FROM app_menu a
                INNER JOIN app_role_menu b ON a.nav_id = b.nav_id
                INNER JOIN app_role_user c ON b.role_id = c.role_id
                WHERE a.site_id = ? AND c.user_id = ?
                AND nav_st = '0' AND nav_display = '0'
                AND CONCAT(b.`read`, b.`create`, b.`edit`, b.`delete`) >= '1000'
                AND nav_loc = 'left'
                GROUP BY a.nav_id
                ORDER BY nav_no ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $results = $query->result_array();
            $menus = array();
            if(!empty($results)) {
                foreach($results as $result) {
                    $parent = $result['parent_id'];
                    if($parent == 0) {
                        $menus[$result['nav_id']] = $result;
                    } else {
                        $menus[$result['parent_id']]['child'][] = $result;
                    }
                }
            }
            $query->free_result();
            return $menus;
        } else {
            return false;
        }
    }

    // get user authority
    function get_user_authority($user_id, $id_group) {
        $sql = "SELECT a.user_id FROM app_user a
                INNER JOIN app_role_user b ON a.user_id = b.user_id
                INNER JOIN app_role c ON b.role_id = c.role_id
                WHERE a.user_id = ? AND c.site_id = ?";
        $query = $this->db->query($sql, array($user_id, $id_group));
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['user_id'];
        } else {
            return false;
        }
    }

    // get user authority by navigation
    function get_user_authority_by_nav($params) {
        $sql = "SELECT DISTINCT b.*, CONCAT(b.`read`, b.`create`, b.`edit`, b.`delete`) AS permission FROM app_menu a
                INNER JOIN app_role_menu b ON a.nav_id = b.nav_id
                INNER JOIN app_role c ON b.role_id = c.role_id
                INNER JOIN app_role_user d ON c.role_id = d.role_id
                WHERE d.user_id = ? AND a.nav_url = ? AND a.site_id = ? AND nav_st = '0'";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return false;
        }
    }

    //function get reset password
    function get_reset_passwords($params) {
        $sql = "SELECT a.*
                FROM app_reset_pass a 
                ORDER BY a.request_date DESC
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

    // get list authority 
    function get_list_user_roles($params) {
        $sql = "SELECT b.*, role_display
                FROM app_role_user a 
                INNER JOIN app_role b ON a.role_id = b.role_id
                INNER JOIN app_role_menu c ON b.role_id = c.role_id
                INNER JOIN app_menu d ON c.nav_id = d.nav_id
                WHERE d.site_id = ? AND a.user_id = ?
                GROUP BY b.role_id
                ORDER BY b.role_id ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // update
    function update_role_display($params, $where) {
        return $this->db->update('app_role_user', $params, $where);
    }

    function get_app_reference_by_pref_nm($params) {
        $sql = "SELECT pref_value FROM app_preferences WHERE pref_group = ? AND pref_nm = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['pref_value'];
        } else {
            return '-';
        }
    }

    function get_app_reference_by_pref_nm_label($params) {
        $sql = "SELECT pref_value FROM app_preferences WHERE pref_group = ? AND pref_nm = ? AND pref_label = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['pref_value'];
        } else {
            return '-';
        }
    }

    // get list menu
    function get_list_menu($keyword, $menu_default) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                    a.nav_title LIKE '%" . $keyword ."%'
                    OR parent.nav_title LIKE '%" . $keyword ."%'
                    OR a.nav_icon LIKE '%" . $keyword ."%'
                    OR a.nav_url LIKE '%" . $keyword ."%'
                    OR a.nav_no LIKE '%" . $keyword ."%'
                )";
        }
        $sql = "SELECT a.*
                FROM app_menu a
                LEFT JOIN app_menu parent ON a.parent_id = parent.nav_id
                WHERE a.nav_loc = 'left' AND a.nav_id NOT IN (" . $menu_default . ")
                " . $conditions . "
                GROUP BY a.nav_id
                ORDER BY a.nav_no ASC";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $results = $query->result_array();
            $menus = array();
            if(!empty($results)) {
                foreach($results as $result) {
                    $parent = $result['parent_id'];
                    if($parent == 0) {
                        $menus[$result['nav_id']] = $result;
                        $menus[$result['nav_id']]['level'] = 1;
                    } else {
                        if(isset($menus[$result['parent_id']])) {
                            $result['level'] = $menus[$result['parent_id']]['level'] + 1;
                            $menus[$result['parent_id']]['child'][] = $result;
                        } else {
                            $menus[$result['nav_id']] = $result;
                            $menus[$result['nav_id']]['level'] = 1;
                        }
                    }
                }
            }
            $query->free_result();
            return $menus;
        } else {
            return false;
        }
    }
    
    // get list parent menu
    function get_list_parent_menu() {
        $sql = "SELECT a.*
                FROM app_menu a
                WHERE nav_loc = 'left' and parent_id = '0'
                GROUP BY a.nav_id
                ORDER BY nav_no ASC";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $results = $query->result_array();
            $query->free_result();
            return $results;
        } else {
            return false;
        }
    }

    // get list menu by role_id
    function get_list_menu_by_role_id($role_id, $menu_default) {
        $sql = "SELECT 
                    app_menu.*, 
                    parent.`nav_title` AS parent_menu, 
                    app_role_menu.`read`, 
                    app_role_menu.`create`, 
                    app_role_menu.`edit`, 
                    app_role_menu.`delete` 
                FROM app_menu
                LEFT JOIN app_role_menu ON app_menu.`nav_id` = app_role_menu.`nav_id` AND app_role_menu.`role_id` = " . $role_id . "
                LEFT JOIN app_menu parent ON app_menu.parent_id = parent.nav_id
                WHERE app_menu.nav_loc = 'left' 
                AND app_menu.nav_id NOT IN (" . $menu_default . ")
                ORDER BY app_menu.nav_no";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $results = $query->result_array();
            $menus = array();
            if(!empty($results)) {
                foreach($results as $result) {
                    $parent = $result['parent_id'];
                    if($parent == 0) {
                        $menus[$result['nav_id']] = $result;
                        $menus[$result['nav_id']]['level'] = 1;
                    } else {
                        if(isset($menus[$result['parent_id']])) {
                            $result['level'] = $menus[$result['parent_id']]['level'] + 1;
                            $menus[$result['parent_id']]['child'][] = $result;
                        }
                    }
                }
            }
            $query->free_result();
            return $menus;
        } else {
            return false;
        }
    }

    // update site detail
    function update_site_data($id, $params) {
        $this->db->where('site_id', $id);
        return $this->db->update('app_portal', $params);
    }

    // update menu detail
    function add_menu($params) {
        return $this->db->insert('app_menu', $params);
    }

    // update menu detail
    function update_menu($id, $params) {
        $this->db->where('nav_id', $id);
        return $this->db->update('app_menu', $params);
    }

    // delete menu detail
    function delete_menu($nav_id) {
        $this->db->where('nav_id', $nav_id);
        return $this->db->delete('app_menu');
    }

    // // get notification user
    // function get_notif_by_user($params) {
    //     $sql = "SELECT a.*, TIMEDIFF(NOW(), a.created) AS time_ago, m.`nav_icon`
    //             FROM notifications a
    //             LEFT JOIN app_menu m ON a.`notif_link` = m.`nav_url`
    //             WHERE a.user_id = ?
    //             ORDER BY created ASC";
    //     $query = $this->db->query($sql, $params);
    //     if ($query->num_rows() > 0) {
    //         $results = $query->result_array();
    //         $query->free_result();
    //         return $results;
    //     } else {
    //         return array();
    //     }
    // }

    // // get notification user
    // function get_notif_by_id($params) {
    //     $sql = "SELECT a.*, TIMEDIFF(NOW(), a.created) AS time_ago, m.`nav_icon`
    //             FROM notifications a
    //             LEFT JOIN app_menu m ON a.`notif_link` = m.`nav_url`
    //             WHERE a.notif_id = ?
    //             ORDER BY created ASC";
    //     $query = $this->db->query($sql, $params);
    //     if ($query->num_rows() > 0) {
    //         $results = $query->row_array();
    //         $query->free_result();
    //         return $results;
    //     } else {
    //         return array();
    //     }
    // }

    // // delete notification user
    // function delete_notif($notif_id) {
    //     $this->db->where('notif_id', $notif_id);
    //     return $this->db->delete('notifications');
    // }

    /** 
     *  VISITOR LOG
     */

    // get data visitor
    function get_data_visitor($params){
        $sql = "SELECT * FROM visitor_log WHERE ip_address = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $results = $query->row_array();
            $query->free_result();
            return $results;
        } else {
            return array();
        }
    }

    // insert visitor log
    function insert_visitor_log($params) {
        return $this->db->insert('visitor_log', $params);
    }
}
