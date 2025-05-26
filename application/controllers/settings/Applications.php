<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

// --
class Applications extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
    }

    // index
    public function index() {
        // set template content
        $this->tsmarty->assign("template_content", "settings/applications/index.html");
        // save data
        if($this->input->post()){
            $this->load->library('form_validation');
            $this->form_validation->set_rules('site_title', 'Website Title', 'trim|required');
            $this->form_validation->set_rules('site_desc', 'Website Description', 'trim');
            $this->form_validation->set_rules('site_icon', 'Website Logo', 'trim');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'site_title' => $this->input->post('site_title'),
                    'site_desc' => $this->input->post('site_desc'),
                ];
                
                $dir = "./resource/assets-frontend/dist/logo/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/logo/", 0755);
                }

                // if($_FILES['site_icon']['tmp_name'] !== ''){
                //     // upload image
                //     $config['upload_path']          = './resource/assets-frontend/dist/logo/system/';
                //     $config['allowed_types']        = 'gif|jpg|png';
                //     $config['file_name']            = 'site-icon.png';
                //     $config['overwrite']            = TRUE;

                //     $this->load->library('upload', $config);
                //     if (!$this->upload->do_upload('site_icon')){
                //         $error = array('error' => $this->upload->display_errors());
                //         $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                //     }
                //     $data_icon = $this->upload->data();
                //     $data['icon'] = $data_icon['file_name'];
                // }
            
                if($_FILES['site_logo']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['site_logo']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/logo/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = 'site-logo.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('site_logo')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect(site_url('settings/applications'));
                    }
                    $data_logo = $this->upload->data();
                    $data['site_logo'] = $data_logo['file_name'];

                    // thumbnails
                    // $dir_thumb = "./resource/assets-frontend/dist/logo/thumbnail/";
                    // if (!file_exists($dir_thumb)) {
                    //     mkdir("./resource/assets-frontend/dist/logo/thumbnail/", 0755);
                    // }

                    // if($ext != 'svg') {
                    //     $this->load->library('image_lib');
                    //     $config['image_library']  = 'gd2';
                    //     $config['source_image']   = $dir. $data_logo['file_name'];       
                    //     $config['create_thumb']   = TRUE;
                    //     $config['maintain_ratio'] = TRUE;
                    //     $config['width']          = 50;
                    //     $config['height']         = 50;
                    //     $config['new_image']      = $dir_thumb. $data_logo['file_name'];               
                    //     $this->image_lib->initialize($config);
                    //     if (!$this->image_lib->resize()){
                    //         $error = array('error' => strip_tags($this->image_lib->display_errors()));
                    //         $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                    //         redirect(site_url('settings/applications'));
                    //     }
                    //     $data['site_icon'] = 'thumbnail/site-logo_thumb.' . $ext;
                    // } else {
                    //     $data['site_icon'] = $data_logo['file_name'];
                    // }
                }

                if($this->site->update_site_data($this->input->post('site_id'), $data)) {
                    $this->site->update_site_data($this->web_portal['site_id'], $data);
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect(site_url('settings/applications'));
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect(site_url('settings/applications'));
            }
        }
        // output
        parent::display();
    }

    // role
    public function preferences() {
        // set template content
        $this->tsmarty->assign("template_content", "settings/preferences/index.html");
        // output
        parent::display();
    }
}