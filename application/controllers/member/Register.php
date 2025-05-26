<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require_once(APPPATH . 'controllers/base/MemberBase.php');

class Register extends ApplicationBase {

    public function __construct() {
        // parent constructor
        parent::__construct();
        
        // load library yang diperlukan
        $this->load->library('form_validation');
        $this->load->library('fonte');
        $this->load->library('mrp_api');
        $this->load->library('session');
    }

    /**
     * Halaman registrasi member
     */
    public function index() {
        // set template content
        $this->tsmarty->assign("template_content", "member/register.html");
        
        // output
        parent::display();
    }

    /**
     * Mengirim OTP untuk verifikasi nomor telepon
     */
    public function send_otp() {
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

        $phone = $this->input->post('phone');
        $otp_type = $this->input->post('otp_type');

        // Cek apakah nomor telepon sudah terdaftar di MRP
        try {
            $existing_member = $this->mrp_api->getMemberByPhone($phone);
            if ($existing_member) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => 'error',
                        'message' => 'Nomor telepon sudah terdaftar sebagai member.'
                    ]));
                return;
            }
        } catch (Exception $e) {
            log_message('error', 'Error checking phone in MRP: ' . $e->getMessage());
            // Lanjutkan jika terjadi error saat pengecekan (mungkin API sedang down)
        }

        // Simpan data registrasi sementara ke session
        $registration_data = [
            'phone' => $phone,
            'verification_step' => 'otp_sent', // Status: otp dikirim
            'reg_time' => date('Y-m-d H:i:s')
        ];
        $this->session->set_userdata('registration_data', $registration_data);

        // Kirim OTP
        $response = null;
        try {
            if ($otp_type == 'wa') {
                $response = $this->fonte->send_otp_whatsapp($phone);
            } elseif ($otp_type == 'sms') {
                $response = $this->fonte->send_otp_sms($phone);
            } elseif ($otp_type == 'misscall') {
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
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'success',
                    'message' => 'OTP berhasil dikirim ke nomor ' . $phone
                ]));
        } else {
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
     * Verifikasi OTP untuk registrasi
     */
    public function verify_otp() {
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
        $registration_data = $this->session->userdata('registration_data');

        if (!$registration_data || $registration_data['verification_step'] != 'otp_sent') {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Sesi registrasi tidak valid. Silakan mulai dari awal.'
                ]));
            return;
        }

        // Verifikasi OTP
        try {
            $verified_phone = $this->fonte->verify_otp($otp);

            if ($verified_phone) {
                // Update status verifikasi di session
                $registration_data['verification_step'] = 'otp_verified'; // Status: otp terverifikasi
                $this->session->set_userdata('registration_data', $registration_data);

                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => 'success',
                        'message' => 'Verifikasi berhasil. Silakan lengkapi data diri Anda.',
                        'phone' => $verified_phone
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
     * Proses registrasi setelah verifikasi OTP
     * Langsung kirim ke API tanpa menyimpan di database lokal
     */
    public function submit_registration() {
        // Validasi input
        $this->form_validation->set_rules('fullname', 'Nama Lengkap', 'required|trim');
        $this->form_validation->set_rules('email', 'Email', 'trim|valid_email');
        $this->form_validation->set_rules('gender', 'Jenis Kelamin', 'trim');
        $this->form_validation->set_rules('work', 'Pekerjaan', 'trim');
        $this->form_validation->set_rules('birthPlace', 'Tempat Lahir', 'trim');
        $this->form_validation->set_rules('dob', 'Tanggal Lahir', 'trim');
        $this->form_validation->set_rules('address', 'Alamat', 'trim');
        $this->form_validation->set_rules('recentAddress', 'Alamat Saat Ini', 'trim');

        if ($this->form_validation->run() == FALSE) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => validation_errors()
                ]));
            return;
        }

        // Ambil data registrasi dari session
        $registration_data = $this->session->userdata('registration_data');
        
        if (!$registration_data || $registration_data['verification_step'] != 'otp_verified') {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Verifikasi OTP belum dilakukan. Silakan verifikasi nomor telepon Anda terlebih dahulu.'
                ]));
            return;
        }
        
        // Ambil input form
        $fullname = $this->input->post('fullname');
        $email = $this->input->post('email');
        $gender = $this->input->post('gender') ?? 'L'; // Default: Laki-laki
        $work = $this->input->post('work') ?? '';
        $birthPlace = $this->input->post('birthPlace') ?? '';
        $dob = $this->input->post('dob');
        $address = $this->input->post('address');
        $recentAddress = $this->input->post('recentAddress') ?? $address; // Default: sama dengan alamat
        $phone = $registration_data['phone'];
        
        // Generate password yang akan ditampilkan ke user
        $password = $this->generate_random_password(8);
        
        // Kirim data ke MRP API untuk registrasi
        try {
            $mrp_response = $this->mrp_api->registerMember([
                'name' => $fullname,
                'phone' => $phone,
                'email' => $email,
                'gender' => ($gender == 'L') ? 'Laki-laki' : 'Perempuan', // Konversi format gender
                'work' => $work,
                'birthPlace' => $birthPlace,
                'dateBirth' => $dob,
                'address' => $address,
                'recentAddress' => $recentAddress,
                'password' => $password // Optional, jika API MRP menerima password
            ]);
            
            if (!$mrp_response || !isset($mrp_response['status']) || $mrp_response['status'] != 'OK') {
                // Registrasi ke MRP gagal
                $error_message = 'Gagal mendaftarkan member ke sistem. ';
                if (isset($mrp_response['message'])) {
                    $error_message .= $mrp_response['message'];
                }
                
                log_message('error', 'MRP Registration Failed: ' . json_encode($mrp_response));
                
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'status' => 'error',
                        'message' => $error_message
                    ]));
                return;
            }
            
            // Registrasi berhasil
            // Bersihkan data registrasi di session
            $this->session->unset_userdata('registration_data');
            
            // Dapatkan member ID dari response MRP
            $mrp_member_id = isset($mrp_response['result']['id']) ? $mrp_response['result']['id'] : null;
            
            // Buat session login member langsung dari data MRP
            $member_session = [
                'user_id' => $mrp_member_id,
                'user_name' => $fullname,
                'user_email' => $email,
                'user_phone' => $phone,
                'is_logged_in' => true,
                'login_time' => date('Y-m-d H:i:s'),
                'is_mrp_member' => true
            ];
            $this->session->set_userdata('member', $member_session);
            
            // Response sukses
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'success',
                    'message' => 'Registrasi berhasil. Anda akan dialihkan ke halaman member.',
                    'redirect' => site_url('member/dashboard'),
                    'password' => $password // Tampilkan password untuk pengguna
                ]));
                
        } catch (Exception $e) {
            log_message('error', 'Exception in MRP Registration: ' . $e->getMessage());
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Terjadi kesalahan saat pendaftaran: ' . $e->getMessage()
                ]));
        }
    }
    
    /**
     * Generate random password
     */
    private function generate_random_password($length = 8) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $password;
    }
}