<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PublicBase.php');

class Forgot_password extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('M_forgot_password', 'm_forgot_password');
        $this->load->model('apps/M_account', 'm_account');
        $this->load->library('form_validation');
        $this->load->library('encryption');
        $this->load->library('session');

        // Load pustaka validasi
    }


    // dashboard
    public function index()
    {

        $this->tsmarty->assign("template_content", "public/forgot_password.html");


        // output
        parent::display();
    }

    private function _sendEmail($token, $user_email)
    {
      
        $link_email = base_url() . 'confirmation_password?email=' . $user_email . '&token=' . urlencode($token);

        // $config = Array(
        //     'protocol' => 'smtp',
        //     'smtp_host' => 'kenesfood.com',
        //     'smtp_port' => 26,
        //     'smtp_user' => 'cs@kenesfood.com',
        //     'smtp_pass' => 'ham&cheese17lover7A', // Gunakan kata sandi akun email Anda di sin
        //     'charset' => 'utf-8',
        //     'newline' => "\r\n",
        //     'mailType' => 'html',
        //     'wordwrap' => true,
        //     //'smtp_timeout' => '5'
        // );
        
        // $this->load->library('email', $config);
        // $this->email->from('cs@kenesfood.com', 'Admin');
        // $this->email->to($user_email);
        // $this->email->subject('Reset Password');
        // $this->email->message('Ini adalah pesan reset password Anda. Silakan klik link di bawah ini untuk mereset password Anda:' .$link_email);
        
       

        $from = "cs@kenesfood.com";
        $to = "$user_email";
        $subject = "Reset Password";
        $message = "Ini adalah pesan reset password Anda. Silakan klik link di bawah ini untuk mereset password Anda:" .$link_email;
        $headers = "From:" . $from;
        $sendEmail = mail($to,$subject,$message, $headers);
        //echo "Email Berhasil Terkirim";

        if ($sendEmail) {
            
            echo 'Email sent.';
            return TRUE;
        } else {
            return FALSE;
            
        }
        
        
    }


    public function email_verification()
    {
        if ($this->input->post()) {

            $user_email = $this->input->post('user_email');
            $cek_email = $this->m_forgot_password->email($user_email);
            // print_r($cek_email);
            // exit();
            if ($cek_email) {

                //Generate a token
                $timestamp = time();
                $random_value = bin2hex(random_bytes(16)); // Misalnya, 32 karakter acak
                $token = $timestamp . $random_value;
                // $timestamp = time();
                $update_data = array(
                    // 'user_email' => $user_email,
                    'token' => $token,
                    'time_token' => date('Y-m-d H:i:s')

                );

                $this->m_forgot_password->updateUserByEmail($user_email, $update_data);


                //Kirim email dengan token
                $email_send =  $this->_sendEmail($token, $user_email);

                if ($email_send == 'TRUE') {

                    $this->session->set_flashdata('message', array('msg' => 'Silahkan Cek Email Anda', 'status' => 'success'));
                    redirect('/login');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Silahkan Verifikasi Email Kembali', 'status' => 'error'));
                    redirect('/login');
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => 'Email yang Anda Masukkan Belum Terdaftar', 'status' => 'error'));
                redirect('/login');
            }
        }
    }


    public function confirmation_password()
    {
        $this->tsmarty->assign("template_content", "public/confirmation_password.html");

        $token  = $this->input->get('token');
        $user_email = $this->input->get('email');
       
        $this->tsmarty->assign("token", $token);
        $this->tsmarty->assign("email", $user_email);


        if ($this->input->post()) {
            
            $token  = $this->input->post('token');
            $user_email = $this->input->post('email');
            $cek_token = $this->m_forgot_password->available_token($token, $user_email);
            // print_r($cek_token);exit();
            $link_email = base_url() . 'confirmation_password?email=' . $user_email . '&token=' . urlencode($token);

            if($cek_token){

                $waktu_token = new DateTime($cek_token['time_token']);
                $waktu_token->add(new DateInterval('PT30M'));
                $now = new DateTime(); // Waktu saat ini

                if ($now >= $waktu_token) {

                    $this->session->set_flashdata('message', array('msg' => 'Waktu Token Telah Habis, Silahkan Verifikasi Kembali', 'status' => 'error'));
                    redirect('/forgot_password');
                }else{

                    $this->form_validation->set_rules('user_pass1', 'Kata sandi baru', 'required');
                    $this->form_validation->set_rules('user_pass2', 'Konfirmasi kata sandi baru', 'matches[user_pass1]');
                    if ($this->form_validation->run() == FALSE) {
                        $this->session->set_flashdata('message', array('msg' => 'Password Belum Sesuai', 'status' => 'error'));
                        redirect($link_email);
                    } else {

                        //$user_pass2 = $this->input->post('user_pass2');
                        $user_key = $this->m_account->rand_key(8);
                        $this->encryption->initialize(
                            array(
                                'cipher' => 'aes-256',
                                'mode' => 'ctr',
                                'key' => $user_key
                            )
                        );
                        $user_pass = $this->encryption->encrypt(md5($this->input->post('user_pass1')));

                        
                        $this->db->set('user_pass', $user_pass);
                        $this->db->set('user_key', $user_key);
                        $this->db->set('token', NULL);
                        $this->db->set('time_token', NULL);
                        $this->db->where('user_email', $user_email);
                        $this->db->update('app_user');

                        $this->session->set_flashdata('message', array('msg' => 'Silahkan Login Kembali', 'status' => 'success'));
                        redirect('/login');
                    }
                    
                }
                
            }else{
                 $this->session->set_flashdata('message', array('msg' => 'Token sudah tidak sesuai, Silahkan Verifikasi Kembali', 'status' => 'error'));
                 redirect('/forgot_password');
            }



           
        }

        parent::display();
    }
}