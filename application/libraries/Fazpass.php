<?php
// application/libraries/Fazpass.php

class Fazpass
{
    protected $ci;
    private $base_url = 'https://api.fazpass.com/v1';
    private $merchant_key;
    private $gateway_keys = [
        'wa' => 'a2b76ff6-e76e-42aa-a2d4-3615d82d74ed', // Fazpass Internasional 2 (WA)
        'sms' => '7be209fc-9625-497a-94f9-9008d06ee9e4', // GenX1 (SMS)
        'misscall' => '4fa81232-6150-4e5f-a742-08d5306a54a1' // CitCall (Misscall)
    ];

    public function __construct()
    {
        $this->ci = &get_instance();
        $this->merchant_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZGVudGlmaWVyIjoxMDAyNn0.JE3xJnpTKxpSOmQQw-RHVlnaF2OefcO1GSSQ2XXO8xg';
        $this->ci->load->database();
        $this->ci->load->library('session');
    }

    /**
     * Mengirim OTP melalui WhatsApp
     * 
     * @param string $phone Nomor telepon
     * @return bool|array Respons dari API atau false jika gagal
     */
    public function send_otp_whatsapp($phone)
    {
        return $this->send_otp($phone, 'wa');
    }

    /**
     * Mengirim OTP melalui SMS
     * 
     * @param string $phone Nomor telepon
     * @return bool|array Respons dari API atau false jika gagal
     */
    public function send_otp_sms($phone)
    {
        return $this->send_otp($phone, 'sms');
    }

    /**
     * Mengirim OTP melalui Misscall
     * 
     * @param string $phone Nomor telepon
     * @return bool|array Respons dari API atau false jika gagal
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
     * @return bool|array Respons dari API atau false jika gagal
     */
    private function send_otp($phone, $type)
    {
        // Format nomor telepon
        $phone = $this->format_phone_number($phone);

        // Siapkan data untuk API request
        $data = [
            'phone' => $phone,
            'gateway_key' => $this->gateway_keys[$type]
        ];

        // Kirim request ke API Fazpass
        $response = $this->request('otp/request', $data);

        // Log untuk debugging
        log_message('debug', 'OTP Response: ' . json_encode($response));

        if ($response && isset($response['status']) && $response['status'] === true) {
            // Simpan informasi OTP ke session dan database
            $otp_id = $response['data']['id'];
            $channel = $response['data']['channel'];
            $provider = $response['data']['provider'];

            // Simpan OTP ID ke session untuk verifikasi nanti
            $this->ci->session->set_userdata('otp_id', $otp_id);

            // Simpan detail ke database untuk log
            $this->save_otp_request($phone, $otp_id, $type, $channel, $provider);

            return $response;
        }

        return false;
    }

    /**
     * Verifikasi kode OTP
     * 
     * @param string $otp Kode OTP
     * @return bool True jika OTP valid
     */
    public function verify_otp($otp)
    {
        // Ambil OTP ID dari session
        $otp_id = $this->ci->session->userdata('otp_id');

        if (!$otp_id) {
            return false;
        }

        // Siapkan data untuk API request
        $data = [
            'otp_id' => $otp_id,
            'otp' => $otp
        ];

        // Kirim request ke API Fazpass
        $response = $this->request('otp/verify', $data);

        if ($response && isset($response['status']) && $response['status'] === true) {
            // Update status OTP di database
            $this->update_otp_status($otp_id, true);

            // Hapus OTP ID dari session setelah verifikasi berhasil
            $this->ci->session->unset_userdata('otp_id');

            return true;
        }

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
        }

        return $phone;
    }

    /**
     * Simpan request OTP ke database
     * 
     * @param string $phone Nomor telepon
     * @param string $otp_id ID OTP dari Fazpass
     * @param string $type Tipe OTP (wa, sms, misscall)
     * @param string $channel Channel OTP
     * @param string $provider Provider OTP
     * @return int|bool ID dari record baru atau false jika gagal
     */
    private function save_otp_request($phone, $otp_id, $type, $channel, $provider)
    {
        // Cari user berdasarkan nomor telepon
        $this->ci->db->select('user_id');
        $this->ci->db->from('data_member');
        $this->ci->db->where('phone', $phone);
        $query = $this->ci->db->get();

        $user_id = null;
        if ($query->num_rows() > 0) {
            $user_id = $query->row()->user_id;
        }

        // Simpan data OTP ke database
        $data = [
            'user_id' => $user_id,
            'phone' => $phone,
            'otp_id' => $otp_id,
            'verification_type' => $type,
            'channel' => $channel,
            'provider' => $provider,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
            'verified' => '0'
        ];

        $this->ci->db->insert('otp_verification', $data);
        return $this->ci->db->insert_id();
    }

    /**
     * Update status OTP di database
     * 
     * @param string $otp_id ID OTP dari Fazpass
     * @param bool $verified Status verifikasi
     * @return bool True jika berhasil diupdate
     */
    private function update_otp_status($otp_id, $verified)
    {
        $this->ci->db->where('otp_id', $otp_id);
        $this->ci->db->update('otp_verification', [
            'verified' => $verified ? '1' : '0',
            'verified_at' => date('Y-m-d H:i:s')
        ]);

        // Update user jika OTP terverifikasi
        if ($verified) {
            $this->ci->db->select('user_id, phone');
            $this->ci->db->from('otp_verification');
            $this->ci->db->where('otp_id', $otp_id);
            $query = $this->ci->db->get();

            if ($query->num_rows() > 0) {
                $row = $query->row();

                if ($row->user_id) {
                    $this->ci->db->where('user_id', $row->user_id);
                    $this->ci->db->update('app_user', [
                        'phone_verified' => '1',
                        'last_login_otp' => date('Y-m-d H:i:s')
                    ]);
                }

                // Return phone number for further processing
                return $row->phone;
            }
        }

        return false;
    }

    /**
     * Melakukan HTTP request ke API Fazpass
     * 
     * @param string $endpoint Endpoint API
     * @param array $data Data yang akan dikirim
     * @param string $method HTTP method (POST/GET)
     * @return array|bool Hasil decode dari response JSON atau false jika gagal
     */
    private function request($endpoint, $data, $method = 'POST')
    {
        $url = $this->base_url . '/' . $endpoint;

        // Log request
        log_message('debug', 'Fazpass API Request to: ' . $url);
        log_message('debug', 'Fazpass API Request data: ' . json_encode($data));

        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->merchant_key
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Untuk development
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 detik timeout

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        // Log response
        log_message('debug', 'Fazpass API Response code: ' . $http_code);
        log_message('debug', 'Fazpass API Response: ' . $response);

        if ($err) {
            log_message('error', 'Fazpass API Error: ' . $err);
            return false;
        }

        $decoded_response = json_decode($response, true);

        if (!$decoded_response) {
            log_message('error', 'Failed to decode Fazpass API response: ' . $response);
            return false;
        }

        return $decoded_response;
    }
}
