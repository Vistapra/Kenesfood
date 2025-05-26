<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

require_once(APPPATH . 'controllers/base/MemberBase.php');

class Dashboard extends ApplicationBase
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
	}

	/**
	 * Dashboard member
	 */
	public function index()
	{
		// set template content
		$this->tsmarty->assign("template_content", "member/dashboard.html");

		// Ambil data member dari session
		$member_session = $this->session->userdata('member');
		$member_id = $member_session['user_id'];

		try {
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
				// Jika data tidak ditemukan di API
				$this->session->set_flashdata('message', [
					'msg' => 'Gagal mengambil data member dari server.',
					'status' => 'error'
				]);
				$member = [
					'mrp_id' => $member_id,
					'fullname' => $member_session['user_name'],
					'email' => $member_session['user_email'],
					'phone' => $member_session['user_phone'] ?? '',
					'point_amount' => 0,
					'points_earned' => 0,
					'points_used' => 0,
					'transactions' => [],
					'total_transactions' => 0
				];
			}

			// Convert to object for smarty template compatibility
			$member_obj = (object) $member;

			// Ambil poin member
			$points = $member['point_amount'] ?? 0;

			// Ambil level member berdasarkan poin
			$level = $this->mrp_api->getMemberLevel($points);

			// Ambil riwayat transaksi
			$transactions = $member['transactions'] ?? [];

			// Filter dan format transaksi
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

			// Convert transactions to objects for smarty template compatibility
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

			// Ambil total transaksi
			$total_transactions = $member['total_transactions'] ?? 0;
			$points_earned = $member['points_earned'] ?? 0;
			$points_used = $member['points_used'] ?? 0;

			// Assign data ke view
			$this->tsmarty->assign("member", $member_obj);
			$this->tsmarty->assign("points", $points);
			$this->tsmarty->assign("level", $level);
			$this->tsmarty->assign("history", $transaction_objects);
			$this->tsmarty->assign("earned_transactions", $earned_transaction_objects);
			$this->tsmarty->assign("used_transactions", $used_transaction_objects);
			$this->tsmarty->assign("total_transactions", $total_transactions);
			$this->tsmarty->assign("points_earned", $points_earned);
			$this->tsmarty->assign("points_used", $points_used);
		} catch (Exception $e) {
			log_message('error', 'Error in Dashboard index: ' . $e->getMessage());
			$this->session->set_flashdata('message', [
				'msg' => 'Terjadi kesalahan: ' . $e->getMessage(),
				'status' => 'error'
			]);
		}

		// output
		parent::display();
	}

	/**
	 * Detail riwayat transaksi
	 */
	public function detail_history($transaction_id)
	{
		if (!$transaction_id) {
			redirect('member/dashboard');
		}

		// set template content
		$this->tsmarty->assign("template_content", "member/detail_history.html");

		try {
			// Get transaction details from API
			$transaction_data = $this->mrp_api->getTransactionDetails($transaction_id);

			if (!$transaction_data) {
				$this->session->set_flashdata('message', [
					'msg' => 'Detail transaksi tidak ditemukan.',
					'status' => 'error'
				]);
				redirect('member/dashboard');
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
		} catch (Exception $e) {
			log_message('error', 'Error in detail_history: ' . $e->getMessage());
			$this->session->set_flashdata('message', [
				'msg' => 'Terjadi kesalahan: ' . $e->getMessage(),
				'status' => 'error'
			]);
			redirect('member/dashboard');
		}

		// output
		parent::display();
	}

	/**
	 * Refresh data member
	 */
	public function refresh_data()
	{
		$member_session = $this->session->userdata('member');
		$member_id = $member_session['user_id'];

		try {
			// Refresh data from API
			$success = $this->mrp_api->refreshMemberData($member_id);

			if ($success) {
				$this->session->set_flashdata('message', [
					'msg' => 'Data berhasil diperbarui.',
					'status' => 'success'
				]);
			} else {
				$this->session->set_flashdata('message', [
					'msg' => 'Gagal memperbarui data dari server.',
					'status' => 'error'
				]);
			}
		} catch (Exception $e) {
			log_message('error', 'Error in refresh_data: ' . $e->getMessage());
			$this->session->set_flashdata('message', [
				'msg' => 'Terjadi kesalahan: ' . $e->getMessage(),
				'status' => 'error'
			]);
		}

		redirect('member/dashboard');
	}
}
