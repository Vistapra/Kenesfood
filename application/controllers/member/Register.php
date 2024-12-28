<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

// load base
require_once(APPPATH . 'controllers/base/MemberBase.php');

// --
class Register extends ApplicationBase {

    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('apps/M_account', 'm_account');
        $this->load->model('settings/M_users', 'M_users');
        $this->load->library('form_validation'); // Load pustaka validasi
     
    }

    public function register_member() {
        $this->form_validation->set_rules('fullname', 'fullname', 'required');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[app_user.user_email]');//agar unique;

        $this->form_validation->set_rules('password', 'password', 'required');
    
        if ($this->form_validation->run() == FALSE) {
            // Validasi gagal, tampilkan kembali halaman register
            $this->session->set_flashdata('message', array('msg' => 'Email telah digunakan, silahkan gunakan email yang lain.', 'status' => 'error'));
            redirect('public/login');

        } else {
            // Validasi berhasil, simpan data ke database
            $this->load->library('encryption');
            $user_key = $this->m_account->rand_key(8);
            $this->encryption->initialize(
                array(
                        'cipher' => 'aes-256',
                        'mode' => 'ctr',
                        'key' => $user_key
                )
            );
            $user_pass = $this->encryption->encrypt(md5($this->input->post('password')));
            
            // Simpan data ke tabel app_user
            $data = array(
                'user_name' => $this->input->post('fullname'),
                'user_email' => $this->input->post('email'),
                'user_key' => $user_key,
                'user_pass' => $user_pass,
                'user_code' => '110'.$this->M_users->get_user_last_code(array('110'))
            );
            $this->M_users->saveUserMember($data);
            
            // Dapatkan ID dari baris yang baru saja diinsert
            $user_id = $this->M_users->get_last_inserted_id();
            
            // Simpan data ke tabel data_member
            $data_member = array(
                'user_id' => $user_id,
                'created_by' =>$user_id,
                'fullname' =>$this->input->post('fullname'),
                'phone'=>$this->input->post('phone'),
                'modified_by' =>$user_id                               
              
            );
            $this->M_users->saveAnotherUserMember($data_member);
            $this->session->set_flashdata('message', array('msg' => 'Registrasi berhasil, silahkan login', 'status' => 'success'));
            redirect('public/login'); // Redirect ke halaman login 
        }
    }
    private function is_email_unique($email) {
        // Cek apakah email sudah terdaftar di database
        $existing_user = $this->M_users->get_user_by_email($email);
    
        return empty($existing_user);
    }
    
}

