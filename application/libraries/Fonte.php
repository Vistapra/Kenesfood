<?php
// application/libraries/Fonte.php

/**
 * Fonte OTP Integration
 * 
 * Class untuk mengintegrasikan website dengan Fonte API untuk layanan OTP
 */
class Fonte
{
	protected $ci;
	private $base_url = 'https://api.fonnte.com';
	private $token = 'hzMyu16QU9tyszkUj8Mr';

	public function __construct()
	{
		$this->ci = &get_instance();
		$this->ci->load->library('session');
	}

	/**
	 * Mengirim OTP melalui WhatsApp
	 * 
	 * @param string $phone Nomor telepon
	 * @return array Respons dari API
	 */
	public function send_otp_whatsapp($phone)
	{
		return $this->send_otp($phone, 'wa');
	}

	/**
	 * Mengirim OTP melalui SMS
	 * 
	 * @param string $phone Nomor telepon
	 * @return array Respons dari API
	 */
	public function send_otp_sms($phone)
	{
		return $this->send_otp($phone, 'sms');
	}

	/**
	 * Mengirim OTP melalui Misscall
	 * 
	 * @param string $phone Nomor telepon
	 * @return array Respons dari API
	 */
	public function send_otp_misscall($phone)
	{
		return $this->send_otp($phone, 'misscall');
	}

	/**
	 * Mengirim OTP
	 * 
	 * @param string $phone Nomor telepon
	 * @param string $type Tipe OTP (wa, sms, misscall)
	 * @return array Respons dari API
	 */
	private function send_otp($phone, $type)
	{
		// Format nomor telepon
		$phone = $this->format_phone_number($phone);

		// Generate OTP code
		$otp_code = rand(100000, 999999);

		// Menyiapkan data OTP untuk disimpan di session
		$otp_data = [
			'phone' => $phone,
			'otp_id' => md5($phone . time()),
			'otp_code' => $otp_code,
			'type' => $type,
			'created_at' => date('Y-m-d H:i:s'),
			'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
			'verified' => false
		];

		// Simpan ke session
		$this->ci->session->set_userdata('otp_data', $otp_data);
		$this->ci->session->set_userdata('otp_id', $otp_data['otp_id']);

		// Message based on type
		$message = "Kode OTPmu adalah : *{$otp_code}* (You can copy this code) . Gunakan kode ini untuk akses login member";

		// Prepare data for API request
		$data = [
			'target' => $phone,
			'message' => $message
		];

		// Send request to Fonte API
		$response = $this->request($data);

		// Log for debugging
		log_message('debug', 'OTP Response: ' . json_encode($response));

		if ($response && isset($response['status']) && $response['status'] === true) {
			return [
				'status' => true,
				'message' => 'OTP berhasil dikirim ke nomor ' . $phone,
				'data' => [
					'phone' => $phone,
					'type' => $type,
					'expires_at' => $otp_data['expires_at'],
				]
			];
		}

		return [
			'status' => false,
			'message' => 'Gagal mengirim OTP. ' . ($response['message'] ?? 'Silakan coba lagi.')
		];
	}

	/**
	 * Verifikasi kode OTP
	 * 
	 * @param string $otp Kode OTP
	 * @return bool True jika OTP valid
	 */
	public function verify_otp($otp)
	{
		// Get OTP data from session
		$otp_data = $this->ci->session->userdata('otp_data');

		if (!$otp_data) {
			log_message('error', 'OTP verification failed: No OTP data in session');
			return false;
		}

		// Check if OTP is expired (5 minutes)
		$expire_time = strtotime($otp_data['expires_at']);
		if (time() > $expire_time) {
			log_message('error', 'OTP verification failed: OTP expired');
			return false;
		}

		// Verify OTP code
		if ($otp_data['otp_code'] == $otp) {
			// Mark as verified in session
			$otp_data['verified'] = true;
			$this->ci->session->set_userdata('otp_data', $otp_data);

			// Return verified phone
			return $otp_data['phone'];
		}

		log_message('error', 'OTP verification failed: Invalid OTP code');
		return false;
	}

	/**
	 * Format nomor telepon untuk standar internasional
	 * 
	 * @param string $phone Nomor telepon
	 * @return string Nomor telepon yang sudah diformat
	 */
	private function format_phone_number($phone)
	{
		// Format nomor telepon untuk standar internasional
		$phone = preg_replace('/[^0-9]/', '', $phone);
		if (substr($phone, 0, 1) === '0') {
			$phone = '62' . substr($phone, 1);
		} else if (substr($phone, 0, 2) !== '62' && substr($phone, 0, 3) !== '+62') {
			$phone = '62' . $phone;
		}

		// Remove + if present
		$phone = str_replace('+', '', $phone);

		return $phone;
	}

	/**
	 * Melakukan HTTP request ke API Fonte
	 * 
	 * @param array $data Data yang akan dikirim
	 * @return array|bool Hasil decode dari response JSON atau false jika gagal
	 */
	private function request($data)
	{
		$curl = curl_init();

		curl_setopt_array($curl, [
			CURLOPT_URL => $this->base_url . '/send',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_HTTPHEADER => [
				'Authorization: ' . $this->token
			],
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_SSL_VERIFYPEER => 0
		]);

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		// Log response
		log_message('debug', 'Fonte API Response: ' . $response);

		if ($err) {
			log_message('error', 'Fonte API Error: ' . $err);
			return [
				'status' => false,
				'message' => 'Error connecting to OTP service: ' . $err
			];
		}

		return json_decode($response, true);
	}
}
