<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

class M_users extends CI_Model
{
	private $_table = "app_user";

	function __construct()
	{
		// Call the Model constructor
		parent::__construct();
	}

	// get last inserted id
	function get_last_inserted_id()
	{
		return $this->db->insert_id();
	}

	// get last member code
	public function get_last_code()
	{
		$code = $this->config->item('private') . date('ym');
		$sql = "SELECT RIGHT(user_code, 3) 'last_number'
                FROM app_user
                WHERE LEFT(user_code, 6) = ?
                ORDER BY RIGHT(user_code, 3) DESC
                LIMIT 1";
		$query = $this->db->query($sql, $code);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			$number = intval($result['last_number']) + 1;
			if ($number >= 999) {
				return false;
			}
			$zero = '';
			for ($i = strlen($number); $i < 3; $i++) {
				$zero .= '0';
			}
			return $code . $number;
		} else {
			return $code . '001';
		}
	}

	/**
	 * Mendapatkan kode user terakhir berdasarkan prefix
	 * 
	 * @param array $prefixes Array dari prefixes yang ingin dicari
	 * @return string Kode user berikutnya
	 */
	function get_user_last_code($prefixes)
	{
		$this->db->select('MAX(CAST(SUBSTR(user_code, 4) AS UNSIGNED)) as last_number');

		// Buat kondisi untuk prefix
		$where = "user_code LIKE '" . $prefixes[0] . "%'";
		for ($i = 1; $i < count($prefixes); $i++) {
			$where .= " OR user_code LIKE '" . $prefixes[$i] . "%'";
		}

		$this->db->where($where);
		$query = $this->db->get('app_user');

		if ($query->num_rows() > 0) {
			$row = $query->row();
			return $row->last_number + 1;
		} else {
			return "1001"; // Kode awal
		}
	}

	// get total data
	public function get_total_data($params, $keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
                OR a.user_name LIKE '%" . $keyword . "%'
                OR a.user_alias LIKE '%" . $keyword . "%'
                OR a.user_email LIKE '%" . $keyword . "%'
                OR c.role_nm LIKE '%" . $keyword . "%'
            )";
		}
		$sql = "SELECT COUNT(*)'total' FROM app_user a
                INNER JOIN app_role_user b ON a.user_id = b.user_id
                INNER JOIN app_role c ON b.role_id = c.role_id
                WHERE c.site_id = ?
                " . $conditions;
		$query = $this->db->query($sql, $params);
		if ($query->num_rows() > 0) {
			$result = $query->row_array();
			$query->free_result();
			return $result['total'];
		} else {
			return 0;
		}
	}

	// get list data
	function get_list_data($params, $keyword)
	{
		// conditions
		$conditions = '';
		if ($keyword != NULL) {
			$conditions .= " AND (
                OR a.user_name LIKE '%" . $keyword . "%'
                OR a.user_alias LIKE '%" . $keyword . "%'
                OR a.user_email LIKE '%" . $keyword . "%'
                OR c.role_nm LIKE '%" . $keyword . "%'
            )";
		}
		$sql = "SELECT 
                    a.user_id,
                    a.user_alias,
                    a.user_name,
                    a.user_email,
                    a.user_st,
                    c.role_nm
                FROM app_user a
                INNER JOIN app_role_user b ON a.user_id = b.user_id
                INNER JOIN app_role c ON b.role_id = c.role_id
                WHERE c.site_id = ?
                " . $conditions . "
                ORDER BY a.user_name
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

	// get detail data
	function get_detail_data($params)
	{
		// conditions
		$sql = "SELECT 
                    app_user.user_id,
                    app_user.user_code,
                    app_user.user_alias,
                    app_user.user_name,
                    app_user.user_email,
                    app_user.user_st,
                    app_user.user_photo,
                    app_user.user_lock
                FROM app_user 
                WHERE app_user.user_id = ?";
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
	 * Mendapatkan user berdasarkan email
	 */
	function get_user_by_email($email)
	{
		$this->db->where('user_email', $email);
		$query = $this->db->get('app_user');

		if ($query->num_rows() > 0) {
			return $query->row_array();
		} else {
			return null;
		}
	}

	/**
	 * Cek apakah email sudah terdaftar
	 */
	function is_email_exists($email)
	{
		$this->db->where('user_email', $email);
		$query = $this->db->get('app_user');

		return ($query->num_rows() > 0);
	}

	/**
	 * Mendapatkan member berdasarkan nomor telepon
	 */
	function get_member_by_phone($phone)
	{
		$this->db->select('app_user.user_id, app_user.user_name, app_user.user_email, data_member.*');
		$this->db->from('app_user');
		$this->db->join('data_member', 'app_user.user_id = data_member.user_id');
		$this->db->where('data_member.phone', $phone);

		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			return $query->row_array();
		} else {
			return null;
		}
	}

	// insert users
	function add_users($params)
	{
		return $this->db->insert('app_user', $params);
	}

	/**
	 * Menyimpan data user baru
	 */
	function save_user($data)
	{
		$this->db->insert('app_user', $data);

		if ($this->db->affected_rows() > 0) {
			return $this->db->insert_id();
		} else {
			return false;
		}
	}

	// update users
	function update_users($id, $params)
	{
		$this->db->where('user_id', $id);
		return $this->db->update('app_user', $params);
	}

	// delete users
	function delete_users($id)
	{
		$this->db->where('user_id', $id);
		return $this->db->delete('app_user');
	}

	function get_detail_data_member($id)
	{
		try {
			// Check if user exists
			$this->db->select('u.user_id, u.user_name, u.user_alias, u.user_email, u.user_st, u.created, u.modified, 
                            dm.fullname, dm.date_of_birth, dm.phone, dm.address, dm.user_photo');
			$this->db->from('app_user u');
			$this->db->join('data_member dm', 'u.user_id = dm.user_id', 'left');
			$this->db->where('u.user_id', $id);

			$query = $this->db->get();

			// Check if query was successful
			if ($query === FALSE) {
				log_message('error', 'Database error in get_detail_data_member: ' . $this->db->error()['message']);
				return null;
			}

			if ($query->num_rows() > 0) {
				return $query->row_array();
			}

			return null;
		} catch (Exception $e) {
			log_message('error', 'Exception in get_detail_data_member: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Check login by phone number
	 * 
	 * @param string $phone Phone number
	 * @return array|null User data or null if not found
	 */
	function check_login_by_phone($phone)
	{
		try {
			// Pertama, coba ambil data dari MRP
			$mrp_member = $this->mrp_api->getMemberByPhone($phone);

			if ($mrp_member) {
				// Cek apakah member sudah ada di database lokal
				$this->db->select('u.user_id, u.user_name, u.user_email');
				$this->db->from('app_user u');
				$this->db->join('data_member m', 'u.user_id = m.user_id', 'left');
				$this->db->where('m.mrp_member_id', $mrp_member['id']);

				$query = $this->db->get();

				if ($query->num_rows() == 0) {
					// Jika belum ada, buat user baru
					$user_id = $this->create_local_user_from_mrp($mrp_member);

					if ($user_id) {
						// Ambil ulang data user yang baru dibuat
						$this->db->select('user_id, user_name, user_email');
						$this->db->where('user_id', $user_id);
						$query = $this->db->get('app_user');
					}
				}

				return $query->row_array();
			}
		} catch (Exception $e) {
			log_message('error', 'Error in check_login_by_phone: ' . $e->getMessage());
		}

		// Fallback ke pencarian di database lokal
		$this->db->select('u.user_id, u.user_name, u.user_email');
		$this->db->from('app_user u');
		$this->db->join('data_member m', 'u.user_id = m.user_id', 'left');
		$this->db->where('m.phone', $phone);
		$this->db->where('u.user_st', '0'); // Active users only

		$query = $this->db->get();

		return $query->row_array();
	}

	/**
	 * Memeriksa login dengan email dan password
	 */
	function check_login($email, $password)
	{
		// Cari user berdasarkan email
		$this->db->where('user_email', $email);
		$this->db->where('user_st', '0'); // status aktif
		$query = $this->db->get('app_user');

		if ($query->num_rows() > 0) {
			$user = $query->row_array();

			// Cek password
			$this->load->library('encryption');
			$this->encryption->initialize(
				array(
					'cipher' => 'aes-256',
					'mode' => 'ctr',
					'key' => $user['user_key']
				)
			);

			$decrypted_password = $this->encryption->decrypt($user['user_pass']);

			if (md5($password) === $decrypted_password) {
				// Password benar, ambil data member
				$this->db->where('user_id', $user['user_id']);
				$query_member = $this->db->get('data_member');

				if ($query_member->num_rows() > 0) {
					$member = $query_member->row_array();

					// Buat data session
					$session_data = array(
						'user_id' => $user['user_id'],
						'user_name' => $user['user_name'],
						'user_email' => $user['user_email'],
						'user_phone' => $member['phone'],
						'is_logged_in' => true,
						'login_time' => date('Y-m-d H:i:s')
					);

					return $session_data;
				}
			}
		}

		return false;
	}

	private function create_local_user_from_mrp($member)
	{
		// Gunakan transaksi database untuk memastikan konsistensi
		$this->db->trans_start();

		// Generate username dari nama member
		$username = strtolower(str_replace(' ', '-', $member['name']));

		// Cek apakah username sudah ada
		$existing_username = $this->db->get_where('app_user', ['user_name' => $username])->num_rows();
		if ($existing_username > 0) {
			$username .= '-' . rand(100, 999);
		}

		// Generate password dan kunci enkripsi
		$password = bin2hex(random_bytes(8)); // Generate password acak
		$user_key = bin2hex(random_bytes(4)); // Generate kunci enkripsi

		$this->load->library('encryption');
		$this->encryption->initialize([
			'cipher' => 'aes-256',
			'mode' => 'ctr',
			'key' => $user_key
		]);

		$user_pass = $this->encryption->encrypt(md5($password));

		// Data untuk app_user
		$user_data = [
			'user_name' => $username,
			'user_alias' => $member['name'],
			'user_email' => $member['email'] ?? '',
			'user_key' => $user_key,
			'user_pass' => $user_pass,
			'user_st' => '0', // Aktif
			'created' => date('Y-m-d H:i:s'),
			'modified' => date('Y-m-d H:i:s')
		];

		$this->db->insert('app_user', $user_data);
		$user_id = $this->db->insert_id();

		// Data untuk data_member
		$member_data = [
			'user_id' => $user_id,
			'fullname' => $member['name'],
			'phone' => $member['phone'],
			'email' => $member['email'] ?? '',
			'address' => $member['address'] ?? '',
			'mrp_member_id' => $member['id'],
			'created' => date('Y-m-d H:i:s'),
			'modified' => date('Y-m-d H:i:s')
		];

		$this->db->insert('data_member', $member_data);

		// Data untuk user_points
		$points_data = [
			'user_id' => $user_id,
			'points' => $member['point_amount'] ?? 0,
			'last_updated' => date('Y-m-d H:i:s')
		];

		$this->db->insert('user_points', $points_data);

		// Selesaikan transaksi
		$this->db->trans_complete();

		return $user_id;
	}

	/**
	 * Login with OTP
	 * 
	 * @param int $user_id User ID
	 * @return array|null User session data or null if not found
	 */
	function login_with_otp($user_id)
	{
		try {
			$this->db->select('u.user_id, u.user_name, u.user_email, r.role_id, r.role_default');
			$this->db->from('app_user u');
			$this->db->join('app_role_user r', 'u.user_id = r.user_id', 'left');
			$this->db->where('u.user_id', $user_id);
			$this->db->where('u.user_st', '0'); // Active users only

			$query = $this->db->get();

			// Check if query was successful
			if ($query === FALSE) {
				log_message('error', 'Database error in login_with_otp: ' . $this->db->error()['message']);
				return null;
			}

			if ($query->num_rows() > 0) {
				// Update last login time
				$this->db->where('user_id', $user_id);
				$this->db->update('app_user', ['last_login_otp' => date('Y-m-d H:i:s')]);

				return $query->row_array();
			}

			return null;
		} catch (Exception $e) {
			log_message('error', 'Exception in login_with_otp: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Menyimpan data user member baru
	 */
	function saveUserMember($data)
	{
		$this->db->insert('app_user', $data);
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Menyimpan data member
	 */
	function saveAnotherUserMember($data)
	{
		$this->db->insert('data_member', $data);
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Get purchase history for a member
	 * 
	 * @param int $user_id User ID
	 * @return array Purchase history
	 */
	function get_list_history($user_id)
	{
		try {
			$this->db->select('*');
			$this->db->from('purchase_histories');
			$this->db->where('purchase_member', $user_id);
			$this->db->order_by('created', 'DESC');

			$query = $this->db->get();

			// Check if query was successful
			if ($query === FALSE) {
				log_message('error', 'Database error in get_list_history: ' . $this->db->error()['message']);
				return [];
			}

			return $query->result();
		} catch (Exception $e) {
			log_message('error', 'Exception in get_list_history: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * Get purchase history details
	 * 
	 * @param int $purchase_id Purchase ID
	 * @return array Purchase history details
	 */
	function get_detail_purchase_history($purchase_id)
	{
		try {
			$this->db->select('phd.*, dp.product_name, dp.product_pict');
			$this->db->from('purchase_history_details phd');
			$this->db->join('data_product dp', 'phd.product_id = dp.product_id', 'left');
			$this->db->where('phd.purchase_id', $purchase_id);

			$query = $this->db->get();

			// Check if query was successful
			if ($query === FALSE) {
				log_message('error', 'Database error in get_detail_purchase_history: ' . $this->db->error()['message']);
				return [];
			}

			return $query->result();
		} catch (Exception $e) {
			log_message('error', 'Exception in get_detail_purchase_history: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * Delete purchase history
	 * 
	 * @param int $purchase_id Purchase ID
	 * @return bool Success status
	 */
	function delete_purchase_history($purchase_id)
	{
		try {
			// Delete purchase history details first due to foreign key constraints
			$this->db->where('purchase_id', $purchase_id);
			$this->db->delete('purchase_history_details');

			// Delete purchase history
			$this->db->where('purchase_id', $purchase_id);
			$this->db->delete('purchase_histories');

			return ($this->db->affected_rows() > 0);
		} catch (Exception $e) {
			log_message('error', 'Exception in delete_purchase_history: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Edit data member
	 */
	function edit_data_member($id, $datamember, $data_app = [])
	{
		// Update data member
		$this->db->where('user_id', $id);
		$result1 = $this->db->update('data_member', $datamember);

		// Update data app_user jika ada
		$result2 = true;
		if (!empty($data_app)) {
			$this->db->where('user_id', $id);
			$result2 = $this->db->update('app_user', $data_app);
		}

		return ($result1 && $result2);
	}
}
