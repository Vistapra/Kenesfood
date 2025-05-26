<?php

class M_points extends CI_Model
{

	function __construct()
	{
		parent::__construct();
		$this->load->library('mrp_api');
	}

	function get_user_points($user_id)
	{
		// Pertama, coba ambil dari database lokal
		$this->db->where('user_id', $user_id);
		$query = $this->db->get('user_points');

		if ($query->num_rows() > 0) {
			$local_points = $query->row()->points;

			// Jika points di lokal ada, kembalikan
			if ($local_points > 0) {
				return $local_points;
			}
		}

		// Jika tidak ada points di lokal, coba ambil dari MRP
		try {
			// Ambil MRP Member ID dari data_member
			$this->db->select('mrp_member_id');
			$this->db->where('user_id', $user_id);
			$member_query = $this->db->get('data_member');

			if ($member_query->num_rows() > 0) {
				$mrp_member_id = $member_query->row()->mrp_member_id;

				if ($mrp_member_id) {
					// Ambil data member dari MRP API
					$member = $this->mrp_api->getMemberById($mrp_member_id);

					if ($member && isset($member['point_amount'])) {
						// Update poin lokal
						$this->update_user_points($user_id, $member['point_amount']);
						return $member['point_amount'];
					}
				}
			}
		} catch (Exception $e) {
			log_message('error', 'Error getting points from MRP: ' . $e->getMessage());
		}

		// Jika tidak ada data sama sekali, kembalikan 0
		return 0;
	}

	function update_user_points($user_id, $points)
	{
		try {
			// Cek apakah sudah ada record point untuk user
			$this->db->where('user_id', $user_id);
			$query = $this->db->get('user_points');

			$point_data = [
				'points' => $points,
				'last_updated' => date('Y-m-d H:i:s')
			];

			if ($query->num_rows() > 0) {
				// Update existing record
				$this->db->where('user_id', $user_id);
				$this->db->update('user_points', $point_data);
			} else {
				// Insert new record
				$point_data['user_id'] = $user_id;
				$this->db->insert('user_points', $point_data);
			}

			return $points;
		} catch (Exception $e) {
			log_message('error', 'Error updating user points: ' . $e->getMessage());
			return 0;
		}
	}

	function add_points($user_id, $points)
	{
		try {
			$current_points = $this->get_user_points($user_id);
			$new_points = $current_points + $points;

			return $this->update_user_points($user_id, $new_points);
		} catch (Exception $e) {
			log_message('error', 'Error adding points: ' . $e->getMessage());
			return 0;
		}
	}

	function sync_member_points($user_id)
	{
		// Ambil data member dari database lokal untuk mendapatkan ID MRP
		$this->db->select('mrp_member_id');
		$this->db->from('data_member');
		$this->db->where('user_id', $user_id);
		$query = $this->db->get();

		// Check if query was successful
		if ($query === FALSE) {
			log_message('error', 'Database error in sync_member_points: ' . $this->db->error()['message']);
			return null;
		}

		if ($query->num_rows() > 0) {
			$mrp_member_id = $query->row()->mrp_member_id;

			if ($mrp_member_id) {
				// Ambil data member dari MRP API
				try {
					$member = $this->mrp_api->getMemberById($mrp_member_id);

					if ($member) {
						return $member;
					}
				} catch (Exception $e) {
					log_message('error', 'Error getting member from MRP API: ' . $e->getMessage());
				}
			}
		}

		return null;
	}
}
