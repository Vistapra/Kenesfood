<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/MemberBase.php');

/**
 * Auth Controller
 * 
 * Controller untuk otentikasi member menggunakan OTP
 * Sepenuhnya menggunakan API untuk validasi dan data member
 */
class Auth extends ApplicationBase
{
	public function __construct()
	{
		// parent constructor
		parent::__construct();

		// load library
		$this->load->library('form_validation');
		$this->load->library('session');
		$this->load->library('fonte');
		$this->load->library('mrp_api');
	}

	/**
	 * Login page
	 */
	public function login()
	{
		// set template content
		$this->tsmarty->assign("template_content", "member/login_otp.html");

		// output
		parent::display();
	}

	/**
	 * Mengirim OTP ke nomor telepon
	 */
	public function send_otp()
	{
		log_message('debug', 'Send OTP request received');
		log_message('debug', 'POST data: ' . json_encode($this->input->post()));

		// Validasi input
		$this->form_validation->set_rules('phone', 'Phone Number', 'required');
		$this->form_validation->set_rules('otp_type', 'OTP Method', 'required|in_list[wa,sms,misscall]');

		if ($this->form_validation->run() == FALSE) {
			$errors = validation_errors();
			log_message('debug', 'Validation errors: ' . $errors);
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'status' => 'error',
					'message' => $errors
				]));
			return;
		}

		log_message('debug', 'Form validation passed');

		$phone = $this->input->post('phone');
		$otp_type = $this->input->post('otp_type');

		// Cari member berdasarkan nomor telepon via API
		try {
			$member = $this->mrp_api->getMemberByPhone($phone);

			if (!$member) {
				$this->output
					->set_content_type('application/json')
					->set_output(json_encode([
						'status' => 'error',
						'message' => 'Nomor telepon tidak terdaftar sebagai member.'
					]));
				return;
			}

			// Simpan ID MRP ke session untuk sinkronisasi lebih lanjut
			$this->session->set_userdata('mrp_member', $member);
			log_message('debug', 'Member found, data saved to session');
		} catch (Exception $e) {
			log_message('error', 'MRP API Error: ' . $e->getMessage());
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'status' => 'error',
					'message' => 'Gagal terhubung dengan sistem MRP: ' . $e->getMessage()
				]));
			return;
		}

		log_message('debug', 'Sending OTP to: ' . $phone . ' via ' . $otp_type);

		// Kirim OTP berdasarkan tipe
		$response = null;
		try {
			if ($otp_type == 'wa') {
				log_message('debug', 'Calling fonte->send_otp_whatsapp');
				$response = $this->fonte->send_otp_whatsapp($phone);
			} elseif ($otp_type == 'sms') {
				log_message('debug', 'Calling fonte->send_otp_sms');
				$response = $this->fonte->send_otp_sms($phone);
			} elseif ($otp_type == 'misscall') {
				log_message('debug', 'Calling fonte->send_otp_misscall');
				$response = $this->fonte->send_otp_misscall($phone);
			}
			log_message('debug', 'OTP Response from Fonte: ' . json_encode($response));
		} catch (Exception $e) {
			log_message('error', 'Exception in send_otp: ' . $e->getMessage());
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'status' => 'error',
					'message' => 'Error during OTP request: ' . $e->getMessage()
				]));
			return;
		}

		// Proses response
		if ($response && isset($response['status']) && $response['status'] === true) {
			log_message('debug', 'OTP sent successfully');
			// Simpan nomor telepon ke session
			$this->session->set_userdata('auth_phone', $phone);
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'status' => 'success',
					'message' => 'OTP berhasil dikirim ke nomor ' . $phone
				]));
		} else {
			log_message('debug', 'OTP sending failed');
			$error_message = 'Gagal mengirim OTP. ';
			if ($response && isset($response['message'])) {
				$error_message .= $response['message'];
			} else {
				$error_message .= 'Silakan coba lagi.';
			}
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'status' => 'error',
					'message' => $error_message
				]));
		}
	}

	/**
	 * Verifikasi OTP
	 */
	public function verify_otp()
	{
		// Validasi input
		$this->form_validation->set_rules('otp', 'OTP', 'required|numeric|min_length[6]|max_length[6]');

		if ($this->form_validation->run() == FALSE) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'status' => 'error',
					'message' => validation_errors()
				]));
			return;
		}

		$otp = $this->input->post('otp');
		$phone = $this->session->userdata('auth_phone');
		$mrp_member = $this->session->userdata('mrp_member');

		if (!$phone || !$mrp_member) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'status' => 'error',
					'message' => 'Sesi tidak valid. Silakan coba lagi.'
				]));
			return;
		}

		// Verifikasi OTP
		try {
			$verified_phone = $this->fonte->verify_otp($otp);

			if ($verified_phone) {
				// Create a member session
				$member_session = [
					'user_id' => $mrp_member['mrp_id'],
					'user_name' => $mrp_member['fullname'],
					'user_email' => $mrp_member['email'],
					'user_phone' => $mrp_member['phone'],
					'is_logged_in' => true,
					'login_time' => date('Y-m-d H:i:s'),
					'is_mrp_member' => true
				];

				// Set session data
				$this->session->set_userdata('member', $member_session);

				// Cleanup temporary session data
				$this->session->unset_userdata('auth_phone');

				// Keep the mrp_member data for later use

				$this->output
					->set_content_type('application/json')
					->set_output(json_encode([
						'status' => 'success',
						'message' => 'Login berhasil.',
						'redirect' => site_url('member/dashboard')
					]));
				return;
			}
		} catch (Exception $e) {
			log_message('error', 'OTP Verification Error: ' . $e->getMessage());

			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'status' => 'error',
					'message' => $e->getMessage()
				]));
			return;
		}

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'status' => 'error',
				'message' => 'OTP tidak valid atau sudah kadaluarsa.'
			]));
	}

	/**
	 * Logout
	 */
	public function logout()
	{
		$this->session->unset_userdata('member');
		$this->session->unset_userdata('mrp_member');

		$this->session->set_flashdata('message', [
			'msg' => 'Anda telah berhasil logout.',
			'status' => 'success'
		]);

		redirect('member/auth/login');
	}
}
