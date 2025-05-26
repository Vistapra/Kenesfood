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
        // load model
        $this->load->model('apps/M_account', 'm_account');
    }

    // dashboard
    public function index() {
        $this->tsmarty->assign("template_content", "public/login.html");   
        if(!empty($this->member_data)) {
            redirect('profile');
        }
        // output
        parent::display();
    }

    // login process
    public function login_process() {
        $username = $this->input->post('username');
		$password = $this->input->post('password');

		$data = $this->m_account->get_user_login_by_role($username, $password, $this->member_role, $this->portal_member);
		if($data){
			$this->session->set_userdata('member', $data);
            $this->session->set_flashdata('message', array('msg' => 'Login berhasil', 'status' => 'success'));
			redirect(site_url('home'));
		}else{
            $this->session->set_flashdata('message', array('msg' => 'Login gagal.', 'status' => 'error'));
			redirect(site_url('login'));
		}
    }

    // logout process
    public function logout() {
        $this->session->unset_userdata('member');
        $this->session->set_flashdata('message', array('msg' => 'Logout berhasil', 'status' => 'success'));
        redirect(site_url('home'));
    }
}