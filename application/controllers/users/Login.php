<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );

// --
class Login extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load library
        $this->load->library('session');
        // load model
        $this->load->model('apps/M_account', 'm_account');
    }

    // login
    public function index() {
        // set template content
        $this->tsmarty->assign("template_content", "users/login.html");
        // output
        parent::display('base/document-login.html');
    }

    // login process
    public function login_process() {
        $username = $this->input->post('username');
		$password = $this->input->post('password');

		$data = $this->m_account->get_user_login_by_account($username, $password);
		if($data){
			$this->session->set_userdata('user', $data);
            $this->session->set_flashdata('message', array('msg' => 'Login berhasil', 'status' => 'success'));
			redirect(site_url('administrator/dashboard'));
		}else{
            $this->session->set_flashdata('message', array('msg' => 'Login gagal.', 'status' => 'error'));
			redirect(site_url('administrator'));
		}
    }

    // logout process
    public function logout() {
        $this->session->unset_userdata('user');
        $this->session->set_flashdata('message', array('msg' => 'Logout berhasil', 'status' => 'success'));
        redirect(site_url('administrator'));
    }
}
