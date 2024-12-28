<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_account extends CI_Model
{

    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
        // load encrypt
    }

    // rand password
    public function rand_password()
    {
        $pool = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';
        $str = '';
        for ($i = 0; $i < 6; $i++) {
            $str .= substr($pool, mt_rand(0, strlen($pool) - 1), 1);
        }
        return $str;
    }

    // rand password
    public function rand_key($length = 10)
    {
        $pool = '1234567890';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($pool, mt_rand(0, strlen($pool) - 1), 1);
        }
        return $str;
    }

    /*
     * NEW LOGIN
     */

    // get user login
    function get_user_login_by_account($username, $password)
    {

        // process
        // get hash key
        $result = $this->get_user_account_by_username(array($username));
        if (!empty($result)) {
            $this->encryption->initialize(
                array(
                    'cipher' => 'aes-256',
                    'mode' => 'ctr',
                    'key' => $result['user_key']
                )
            );
            $password_decode = $this->encryption->decrypt($result['user_pass']);

            // get user
            if ($password_decode === md5($password)) {
                // cek authority then return id
                return $result;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    // get user detail by account
    function get_user_account_by_username($params)
    {
        $sql = "SELECT
                a.*,
                b.role_id, c.role_nm, 
                e.site_id, a.user_id
                FROM app_user a
                INNER JOIN app_role_user b ON a.user_id = b.user_id
                INNER JOIN app_role c ON b.role_id = c.role_id
                INNER JOIN app_role_menu d ON c.role_id = d.role_id
                INNER JOIN app_menu e ON d.nav_id = e.nav_id
                WHERE user_name = ? 
                ORDER BY b.role_default ASC
                LIMIT 0, 1";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get user detail by portal
    function get_user_account_by_portal($params)
    {
        $sql = "SELECT
                a.*,
                b.role_id, b.role_default, c.role_nm, c.default_page,
                e.site_id
                FROM app_user a
                INNER JOIN app_role_user b ON a.user_id = b.user_id
                INNER JOIN app_role c ON b.role_id = c.role_id
                INNER JOIN app_role_menu d ON c.role_id = d.role_id
                INNER JOIN app_menu e ON d.nav_id = e.nav_id
                WHERE a.user_id = ? AND e.site_id = ?
                ORDER BY b.role_default ASC
                LIMIT 0, 1";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get list portal by user
    function get_list_portal_user_by_id($params)
    {
        $sql = "SELECT e.site_id, e.portal_nm, e.portal_title, e.portal_icon, e.portal_brand, e.portal_logo
                FROM app_role_user a
                INNER JOIN app_role b ON a.role_id = b.role_id
                INNER JOIN app_role_menu c ON b.role_id = c.role_id
                INNER JOIN app_menu d ON c.nav_id = d.nav_id
                INNER JOIN app_portal e ON d.site_id = e.site_id
                WHERE a.user_id = ?
                GROUP BY e.site_id
                ORDER BY e.site_id ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return '';
        }
    }

    /*
     * LOGIN BY ROLES
     */

    // get user detail by role
    function get_user_detail_by_role($params)
    {
        $sql = "SELECT a.*, c.role_id, c.role_nm
                FROM app_user a
                LEFT JOIN app_role_user b ON a.user_id = b.user_id
                LEFT JOIN app_role c ON b.role_id = c.role_id
                WHERE user_name = ? AND c.site_id = ? AND c.role_id = ?
                LIMIT 1";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get login
    function get_user_login_by_role($username, $password, $role_id, $portal)
    {
        // get hash key
        $result = $this->get_user_detail_by_role(array($username, $portal, $role_id));
        if (!empty($result)) {
            if (!empty($result)) {
                $this->encryption->initialize(
                    array(
                        'cipher' => 'aes-256',
                        'mode' => 'ctr',
                        'key' => $result['user_key']
                    )
                );
                $password_decode = $this->encryption->decrypt($result['user_pass']);
    
                // get user
                if ($password_decode === md5($password)) {
                    // cek authority then return id
                    return $result;
                } else {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    /*
     * LOGIN WITH ALL ROLES
     */

    // get user detail with auto role
    function get_user_detail_with_all_portal($params)
    {
        $sql = "SELECT a.*, c.default_page, b.role_default, b.role_id
                FROM app_user a
                INNER JOIN app_role_user b ON a.user_id = b.user_id
                INNER JOIN app_role c ON b.role_id = c.role_id
                INNER JOIN app_role_menu d ON c.role_id = d.role_id
                INNER JOIN app_menu e ON d.nav_id = e.nav_id
                WHERE user_name = ?
                ORDER BY b.role_default ASC
                LIMIT 0, 1";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get user detail with auto role
    function get_user_detail_with_all_roles($params)
    {
        $sql = "SELECT a.*, c.default_page, b.role_default, b.role_id
                FROM app_user a
                INNER JOIN app_role_user b ON a.user_id = b.user_id
                INNER JOIN app_role c ON b.role_id = c.role_id
                INNER JOIN app_role_menu d ON c.role_id = d.role_id
                INNER JOIN app_menu e ON d.nav_id = e.nav_id
                WHERE user_name = ? AND e.site_id = ?
                ORDER BY b.role_default ASC
                LIMIT 0, 1";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get login auto role
    function get_user_login_by_user_pass($username, $password)
    {
        // load encrypt
        // $this->load->library('encrypt');
        // process
        // get hash key
        $result = $this->get_user_detail_with_all_portal(array($username));
        if (!empty($result)) {
            $password_decode = $this->encrypt->decode($result['user_pass'], $result['user_key']);
            // get user
            if ($password_decode === md5($password)) {
                // cek authority then return id
                return $result;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    // get login auto role
    function get_user_login_all_roles($username, $password, $portal)
    {
        // load encrypt
        // $this->load->library('encrypt');
        // process
        // get hash key
        $result = $this->get_user_detail_with_all_roles(array($username, $portal));
        if (!empty($result)) {
            $password_decode = $this->encrypt->decode($result['user_pass'], $result['user_key']);
            // get user
            if ($password_decode === md5($password)) {
                // cek authority then return id
                return $result;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    // get login attempt by username
    function get_login_attempt_by_username($params)
    {
        $sql = "SELECT COUNT(log_id) AS 'total_attempt'
                FROM app_login_attempt a
                WHERE a.log_ip_address = ? AND a.user_name = ?
                AND log_date_time BETWEEN DATE_ADD(NOW(), INTERVAL -1 HOUR) AND NOW()
                GROUP BY a.log_ip_address, a.user_name";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['total_attempt'];
        } else {
            return 0;
        }
    }

    // insert user login
    function save_user_login($params)
    {
        return $this->db->insert('app_user_login', $params);
    }

    // insert login attempt
    function save_login_attempt($params)
    {
        return $this->db->insert('app_login_attempt', $params);
    }

    // get list roles
    function get_list_user_roles($params)
    {
        $sql = "SELECT e.portal_nm, e.site_id, b.*, role_display
                FROM app_role_user a
                INNER JOIN app_role b ON a.role_id = b.role_id
                INNER JOIN app_role_menu c ON b.role_id = c.role_id
                INNER JOIN app_menu d ON c.nav_id = d.nav_id
                INNER JOIN app_portal e ON d.site_id = e.site_id
                WHERE a.user_id = ?
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

    // get login history
    function get_login_history($params)
    {
        $sql = "SELECT a.*, b.nama_lengkap
                FROM app_user_login a
                INNER JOIN app_user b ON a.user_id = b.user_id
                WHERE a.user_id = ? AND a.login_date LIKE ?
                ORDER BY a.login_date DESC
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

    // save user logout
    function update_user_logout($user_id)
    {
        // update by this date
        $sql = "UPDATE app_user_login SET logout_date = NOW() WHERE user_id = ? AND DATE(login_date) = CURRENT_DATE";
        return $this->db->query($sql, $user_id);
    }

    /*
     * RESET PASSWORD
     */

    // get username by email
    function get_username_by_email($params)
    {
        $sql = "SELECT * FROM app_user WHERE user_mail = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return FALSE;
        }
    }

    // get user by email
    function get_user_by_email($params)
    {
        $sql = "SELECT user_id, UPPER(user_alias) AS 'user_alias', user_mail
                FROM app_user a
                WHERE a.user_mail = ? AND a.user_completed = 'yes'";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return FALSE;
        }
    }

    /*
     * CHECK ACCOUNT
     */

    // check username
    function is_exist_username($params)
    {
        $sql = "SELECT * FROM app_user WHERE user_name = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $query->free_result();
            return TRUE;
        } else {
            return FALSE;
        }
    }

    // check mail
    function is_exist_email($params)
    {
        $sql = "SELECT * FROM app_user WHERE user_email = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $query->free_result();
            return TRUE;
        } else {
            return FALSE;
        }
    }

    // check username
    function is_exist_username_by_user_id($params)
    {
        $sql = "SELECT * FROM app_user WHERE user_name = ? AND user_id <> ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $query->free_result();
            return TRUE;
        } else {
            return FALSE;
        }
    }

    // check mail
    function is_exist_email_by_user_id($params)
    {
        $sql = "SELECT * FROM app_user WHERE user_email = ? AND user_id <> ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $query->free_result();
            return TRUE;
        } else {
            return FALSE;
        }
    }

    // check password
    function is_exist_password($user_id, $password)
    {
        $sql = "SELECT * FROM app_user WHERE user_id = ?";
        $query = $this->db->query($sql, $user_id);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
        } else {
            return FALSE;
        }
        // --
        $password_decode = $this->encrypt->decode($result['user_pass'], $result['user_key']);
        if ($password_decode == $password) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    // get user account
    function get_user_account_by_id($params)
    {
        $sql = "SELECT a.*
                FROM app_user a
                WHERE a.user_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // update data account
    function update_data_account($params, $where)
    {
        $sql = "SELECT * FROM app_user WHERE user_id = ?";
        $query = $this->db->query($sql, $where);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
        } else {
            return FALSE;
        }
        // encode password
        $params[1] = $this->encrypt->encode($params[1], $result['user_key']);
        // update
        $sql = "UPDATE app_user SET user_name = ?, user_pass = ? WHERE user_id = ?";
        return $this->db->query($sql, $params);
    }

    // roles
    function get_all_roles_by_portal($site_id)
    {
        $sql = "SELECT * FROM app_role WHERE site_id = ?";
        $query = $this->db->query($sql, $site_id);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // detail roles
    function get_detail_roles_by_id($params)
    {
        $sql = "SELECT * FROM app_role WHERE role_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // update permissions
    function update_permissions($params)
    {
        // delete by user & portal
        $sql = "DELETE a.* FROM app_role_user a
                INNER JOIN app_role b ON a.role_id = b.role_id
                WHERE a.user_id = ? AND b.site_id = 2";
        $this->db->query($sql, $params);
        // insert
        $sql = "INSERT INTO app_role_user (user_id, role_id) VALUES (?, ?)";
        return $this->db->query($sql, $params);
    }

    // roles
    function get_all_roles_by_users($params)
    {
        $sql = "SELECT * FROM app_role a
                INNER JOIN app_role_user b ON a.role_id = b.role_id
                WHERE site_id = ? AND b.user_id = ?
                ORDER BY a.role_nm ASC";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // check mail
    function get_detail_user_by_email($params)
    {
        $sql = "SELECT * FROM app_user WHERE user_mail = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // check user reset password
    function is_reset_password($params)
    {
        $sql = "SELECT * FROM app_user a
                INNER JOIN app_user_reset b ON a.user_id = b.user_id
                WHERE b.user_id = ? AND b.reset_st = '0'";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // save user request
    function save_request_password($params)
    {
        // execute
        return $this->db->insert('request_password', $params);
    }

    // save user reset
    function save_user_reset($params)
    {
        // execute
        return $this->db->insert('app_user_reset', $params);
    }

    // get data id
    function get_token()
    {
        $time = microtime(true);
        $token = str_replace('.', '', $time);
        return md5(md5($token));
    }

    // update data account
    function is_exist_token($params)
    {
        $sql = "SELECT * FROM app_user a
                INNER JOIN app_user_reset b ON a.user_id = b.user_id
                WHERE b.token = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return FALSE;
        }
    }

    // save user logout
    function update_user_password($params)
    {
        // update by this date
        $sql = "UPDATE app_user SET user_pass = ?, user_key = ? WHERE user_id = ?";
        if ($this->db->query($sql, $params)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    // reset password
    function update_user_reset($params)
    {
        // update by this date
        $sql = "UPDATE app_user_reset SET reset_st = '1', reset_date = NOW() WHERE user_id = ? AND token = ?";
        return $this->db->query($sql, $params);
    }

    /*
     * ACCOUNT SETTINGS
     */

    // get detail users
    function get_detail_user_by_id($params)
    {
        $sql = "SELECT *
                FROM app_user
                WHERE user_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // update
    function update_user($params, $where)
    {
        // update
        return $this->db->update('app_user', $params, $where);
    }

    // insert
    function insert_reset($params)
    {
        return $this->db->insert('app_reset_pass', $params);
    }

    // update reset
    function update_reset($params, $where)
    {
        return $this->db->update('app_reset_pass', $params, $where);
    }

    // get data by id
    function get_reset_data_by_id($params)
    {
        $sql = "SELECT *
                FROM app_reset_pass
                WHERE md5(request_key) = ? AND request_st = 'waiting'";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get user alias
    function get_user_alias_by_id($params)
    {
        $sql = "SELECT user_alias
                FROM app_user
                WHERE user_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['user_alias'];
        } else {
            return '';
        }
    }

    // get user alias
    function get_detail_app_user_instansi($params)
    {
        $sql = "SELECT a.*
                FROM app_user a
                INNER JOIN app_user_instansi b ON a.user_id = b.user_id
                WHERE b.instansi_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return '';
        }
    }

    /*
     * user
     */

    // get user login
    function get_user_login_by_id($params)
    {
        $sql = "SELECT
                a.user_id, a.user_alias, a.user_name, a.user_pass, a.user_key,
                a.user_mail, a.user_img_name, a.user_img_path
                FROM app_user a
                WHERE a.user_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // portal
    function get_detail_portal_by_id($site_id)
    {
        $sql = "SELECT *,IFNULL(portal_logo,'default.png')'portal_logo' FROM app_portal WHERE site_id = ?";
        $query = $this->db->query($sql, $site_id);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    /*
     * Convert Password
     */

    // get list user internal
    function get_user_internal()
    {
        /*
          $sql = "SELECT a.user_id, a.user_name, a.user_pass, a.user_key
          FROM apms_soetta_2015_db.com_user a
          INNER JOIN apms_soetta_2015_db.com_user_login b ON a.user_id = b.user_id
          WHERE a.user_st IN ('otoritas', 'member') AND YEAR(b.login_date) = '2020'
          GROUP BY a.user_id";
         */
        $sql = "SELECT a.user_id, b.user_pass, b.user_key
		FROM app_user a
		INNER JOIN apms_soetta_2015_db.com_user b ON a.user_id = b.user_id
		WHERE a.`user_group` = 'otban'";

        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get list user perusahaan
    function get_user_perusahaan()
    {
        $sql = "SELECT
		a.user_id, UPPER(a.operator_name) AS 'user_alias', a.operator_gender AS 'user_gender', UPPER(a.operator_birth_place) AS 'user_birth_place',
		a.operator_birth_day AS 'user_birth_date', UPPER(a.operator_address) AS 'user_address', a.operator_phone AS 'user_phone',
		a.operator_nip AS 'user_nip', UPPER(a.operator_jabatan) AS 'user_jabatan', a.operator_identitas AS 'user_identitas',
		a.user_name, a.user_pass, a.user_key, a.user_mail, 'no' AS 'user_img_status', NULL AS 'user_img_name', NULL AS 'user_img_path', NULL AS 'user_img_mdd',
		IF(a.lock_st = '1', 'locked', 'open') AS 'user_locked_status', 'yes' AS 'user_completed', 'otban' AS 'user_group',
		a.mdb AS 'mdb', NULL AS 'mdb_name', a.mdd AS 'mdd'
		FROM apms_soetta_2015_db.com_user a
                LEFT JOIN
		(
			SELECT b.user_id
			FROM soetta_apms_2020_db.app_user b
			WHERE b.user_group = 'perusahaan'
		) c ON a.user_id = c.user_id
		WHERE a.user_st = 'member'";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get list user perusahaan
    function get_user_perusahaan_convert()
    {
        $sql = "SELECT a.* FROM apms_soetta_2015_db.com_user a
		INNER JOIN soetta_apms_2020_db.app_user b ON a.user_id = b.user_id
		WHERE a.user_st = 'member'";
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
