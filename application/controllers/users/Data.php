<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

// --
class Data extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('settings/M_users', 'm_users');
        $this->load->model('settings/M_dashboard', 'dashboard');
        $this->load->model('apps/M_account', 'm_account');
    }

    // index
    public function index() {
        // set template content
        $this->tsmarty->assign("template_content", "users/profile.html");
        // user id
        $user_id = $this->user_data['user_id'];
        // detail users
        $user = $this->m_users->get_detail_data($user_id);
        $this->tsmarty->assign("detail", $user);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('user_id', 'User ID', 'trim|required');
            $this->form_validation->set_rules('user_alias', 'Fullname', 'trim|required');
            $this->form_validation->set_rules('user_name', 'Username', 'trim|required');
            $this->form_validation->set_rules('user_email', 'Email', 'trim|required|valid_email');
            $this->form_validation->set_rules('user_pass', 'Password', 'trim');
            $this->form_validation->set_rules('user_pass_verif', 'Password', 'trim');
            $this->form_validation->set_rules('user_photo', 'photo', 'trim');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'user_alias' => $this->input->post('user_alias'),
                    'user_name' => $this->input->post('user_name'),
                    'user_email' => $this->input->post('user_email'),
                ];
                $user_pass = $user_key = NULL;
                if(!empty($this->input->post('user_pass'))){
                    if($this->input->post('user_pass') != $this->input->post('user_pass_verif')) {
                        $this->session->set_flashdata('message', array('msg' => 'Verifikasi password berbeda dengan password baru.', 'status' => 'error'));
                        redirect(site_url('administrator/profile'));
                    } else {
                        // load encrypt
                        $this->load->library('encrypt');
                        $user_key = $this->m_account->rand_key(8);
                        $this->encryption->initialize(
                            array(
                                    'cipher' => 'aes-256',
                                    'mode' => 'ctr',
                                    'key' => $user_key
                            )
                        );
                        $user_pass = $this->encryption->encrypt(md5($this->input->post('user_pass')));
                        // -- encode($msg, $key);
                        $data['user_key'] = $user_key;
                        $data['user_pass'] = $user_pass;
                    }
                }

                if($_FILES['user_photo']['tmp_name'] !== '') {
                    $temp = explode(".", $_FILES['user_photo']['name']);
                    $ext = end($temp);
                    // upload image
                    $config2['upload_path']          = './resource/assets/default/images/uploads/users/';
                    $config2['allowed_types']        = 'svg|gif|jpg|png';
                    $config2['file_name']            = $user_id;
                    $config2['overwrite']            = TRUE;

                    $this->load->library('upload');
                    $this->upload->initialize($config2);
                    if (!$this->upload->do_upload('user_photo')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                    }
                    $upload_data = $this->upload->data();
                    // resize
                    $this->load->library('image_lib');
                    $config2['image_library']  = 'gd2';
                    $config2['source_image']   = './resource/assets/default/images/uploads/users/'. $upload_data['file_name'];       
                    $config2['create_thumb']   = FALSE;
                    $config2['maintain_ratio'] = TRUE;
                    $config2['width']          = 70;
                    $config2['height']         = 70;          
                    $this->image_lib->initialize($config2);
                    if (!$this->image_lib->resize()){
                        $error = array('error' => strip_tags($this->image_lib->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect(site_url('administrator/profile'));
                    }
                    $data['user_photo'] = $upload_data['file_name'];
                } 

                if($this->m_users->update_users($this->input->post('user_id'), $data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
            }
            redirect(site_url('administrator/profile'));
        }
        // output
        parent::display();
    }

    // user dashboard
    public function dashboard() {
        // set template content
        $this->tsmarty->assign("template_content", "users/dashboard.html");
        // get data product
        $product = $this->dashboard->get_total_product();
        $this->tsmarty->assign('product', $product);
        // get data visitor log
        $visitor = $this->dashboard->get_visitor_log();
        $this->tsmarty->assign('visitor', $visitor);
        // output
        parent::display();
    }
}