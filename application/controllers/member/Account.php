<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/MemberBase.php');

/**
 * Account Controller
 * 
 * Controller untuk mengelola akun member
 * Menampilkan profil dan riwayat transaksi dari MRP API
 */
class Account extends ApplicationBase
{
	public function __construct()
	{
		// parent constructor
		parent::__construct();

		// Check if member is logged in
		if (!$this->session->userdata('member')) {
			redirect('member/auth/login');
		}

		// load library
		$this->load->library('mrp_api');
		$this->load->library('form_validation');
	}

	/**
	 * Account page
	 */
	public function index()
	{
		try {
			// Set template content
			$this->tsmarty->assign("template_content", "member/account.html");

			// Get member data from session
			$member_session = $this->session->userdata('member');
			$member_id = $member_session['user_id'];

			// Ambil data member dari API - gunakan getMemberByPhone jika phone tersedia
			$member = null;
			if (isset($member_session['user_phone']) && !empty($member_session['user_phone'])) {
				$member = $this->mrp_api->getMemberByPhone($member_session['user_phone']);
			}

			// Jika gagal dengan phone, coba dengan ID
			if (!$member) {
				$member = $this->mrp_api->getMemberById($member_id);
			}

			if (!$member) {
				// Handle case where member data doesn't exist
				$this->session->set_flashdata('message', [
					'msg' => 'Gagal mengambil data member dari server.',
					'status' => 'error'
				]);

				// Create fallback data structure
				$member = [
					'mrp_id' => $member_id,
					'fullname' => $member_session['user_name'],
					'user_email' => $member_session['user_email'],
					'phone' => $member_session['user_phone'] ?? '',
					'address' => '',
					'date_of_birth' => null,
					'user_photo' => null,
					'transactions' => [],
					'total_transactions' => 0,
					'point_amount' => 0,
					'points_earned' => 0,
					'points_used' => 0
				];
			}

			// Mendapatkan riwayat transaksi DARI API
			$transactions = $member['transactions'] ?? [];

			// Pisahkan transaksi berdasarkan status poin
			$earned_transactions = [];
			$used_transactions = [];

			foreach ($transactions as $transaction) {
				// Status 1 = point yang masuk/diperoleh
				if ($transaction['point_status'] == 1) {
					$earned_transactions[] = $transaction;
				}
				// Status 0 = point yang keluar/digunakan
				else if ($transaction['point_status'] == 0) {
					$used_transactions[] = $transaction;
				}
			}

			// Convert transactions to objects for smarty
			$transaction_objects = [];
			$earned_transaction_objects = [];
			$used_transaction_objects = [];

			foreach ($transactions as $transaction) {
				$transaction_objects[] = (object) $transaction;
			}

			foreach ($earned_transactions as $transaction) {
				$earned_transaction_objects[] = (object) $transaction;
			}

			foreach ($used_transactions as $transaction) {
				$used_transaction_objects[] = (object) $transaction;
			}

			// Menghitung total transaksi
			$total_transactions = $member['total_transactions'] ?? 0;
			$total_points_earned = $member['points_earned'] ?? 0;
			$total_points_used = $member['points_used'] ?? 0;

			// Assign data ke view
			$this->tsmarty->assign("member", $member);
			$this->tsmarty->assign("history", $transaction_objects);
			$this->tsmarty->assign("earned_transactions", $earned_transaction_objects);
			$this->tsmarty->assign("used_transactions", $used_transaction_objects);
			$this->tsmarty->assign("total_transactions", $total_transactions);
			$this->tsmarty->assign("total_points_earned", $total_points_earned);
			$this->tsmarty->assign("total_points_used", $total_points_used);

			// Display the template
			parent::display();
		} catch (Exception $e) {
			// Log exception
			log_message('error', 'Exception in Account index: ' . $e->getMessage());

			// Show error message to user
			$this->session->set_flashdata('message', [
				'msg' => 'Terjadi kesalahan: ' . $e->getMessage(),
				'status' => 'error'
			]);

			redirect('member/dashboard');
		}
	}

	/**
	 * Detail riwayat transaksi
	 */
	public function detail_history($transaction_id)
	{
		if (!$transaction_id) {
			redirect('member/account');
		}

		try {
			$this->tsmarty->assign("template_content", "member/detail_history.html");

			// Get transaction details from API - PASTIKAN DATA DIAMBIL DARI API!
			$transaction_data = $this->mrp_api->getTransactionDetails($transaction_id);

			if (!$transaction_data) {
				$this->session->set_flashdata('message', [
					'msg' => 'Detail transaksi tidak ditemukan.',
					'status' => 'error'
				]);
				redirect('member/account');
			}

			// Convert items to objects for smarty
			$items = [];
			if (isset($transaction_data['items']) && is_array($transaction_data['items'])) {
				foreach ($transaction_data['items'] as $item) {
					$items[] = (object) $item;
				}
			}

			$this->tsmarty->assign("history", $items);

			if (isset($transaction_data['transaction'])) {
				$this->tsmarty->assign("transaction", (object) $transaction_data['transaction']);
			} else {
				$this->tsmarty->assign("transaction", (object) $transaction_data);
			}

			// Output the view
			parent::display();
		} catch (Exception $e) {
			// Log exception
			log_message('error', 'Exception in detail_history: ' . $e->getMessage());

			// Show error message to user
			$this->session->set_flashdata('message', [
				'msg' => 'Terjadi kesalahan: ' . $e->getMessage(),
				'status' => 'error'
			]);

			// Redirect to account page
			redirect('member/account');
		}
	}

	/**
	 * Delete purchase history - ini tidak lagi berfungsi karena data dari API
	 */
	public function delete_history($purchase_id)
	{
		$this->session->set_flashdata('message', [
			'msg' => 'Tidak dapat menghapus riwayat transaksi. Data diambil dari server pusat.',
			'status' => 'warning'
		]);
		redirect('member/account');
	}

	/**
	 * Update profile
	 */
	public function update_profile()
	{
		// Validate form input
		$this->form_validation->set_rules('fullname', 'Nama Lengkap', 'required|trim');
		$this->form_validation->set_rules('phone', 'Nomor Telepon', 'required|trim');
		$this->form_validation->set_rules('email', 'Email', 'trim|valid_email');

		if ($this->form_validation->run() == FALSE) {
			$this->session->set_flashdata('message', [
				'msg' => validation_errors(),
				'status' => 'error'
			]);
			redirect('member/account');
		}

		// Get member data from session
		$member_session = $this->session->userdata('member');
		$member_id = $member_session['user_id'];

		try {
			// Prepare data for API update
			$update_data = [
				'name' => $this->input->post('fullname'),
				'phone' => $this->input->post('phone'),
				'email' => $this->input->post('email'),
				'address' => $this->input->post('address'),
				'date_of_birth' => $this->input->post('tanggal')
			];

			// TODO: Implement API call to update member data
			// This would require additional API endpoint in MRP system
			// For now, just update session data

			$member_session['user_name'] = $update_data['name'];
			$member_session['user_email'] = $update_data['email'];
			$member_session['user_phone'] = $update_data['phone'];

			$this->session->set_userdata('member', $member_session);

			// Upload profile photo if provided
			if (isset($_FILES['user_photo']) && $_FILES['user_photo']['name']) {
				$config['upload_path'] = './resource/assets-frontend/dist/account/';
				$config['allowed_types'] = 'gif|jpg|png|jpeg';
				$config['max_size'] = 5120;
				$config['encrypt_name'] = TRUE;

				$this->load->library('upload', $config);

				if (!$this->upload->do_upload('user_photo')) {
					$error = $this->upload->display_errors();
					$this->session->set_flashdata('message', [
						'msg' => 'Gagal mengupload foto: ' . $error,
						'status' => 'error'
					]);
				} else {
					$upload_data = $this->upload->data();
					// TODO: Send profile photo URL to API
				}
			}

			// Refresh API cache
			$this->mrp_api->refreshMemberData($member_id);

			$this->session->set_flashdata('message', [
				'msg' => 'Profil berhasil diperbarui.',
				'status' => 'success'
			]);
		} catch (Exception $e) {
			log_message('error', 'Error in update_profile: ' . $e->getMessage());
			$this->session->set_flashdata('message', [
				'msg' => 'Gagal memperbarui profil: ' . $e->getMessage(),
				'status' => 'error'
			]);
		}

		redirect('member/account');
	}
}
