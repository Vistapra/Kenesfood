<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

// --
class Menus extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
    }

    // index
    public function index() {
        // set template content
        $this->tsmarty->assign("template_content", "settings/menus/index.html");
        // search
        $keyword = '';
        $search = $this->session->userdata('search_menu');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_menu");
            } else {
                $keyword = $this->input->post('keyword', TRUE);
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_menu", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // get data
        $data = $this->site->get_list_menu($keyword, $this->app_settings['menu_default']);
        $this->tsmarty->assign("data", $data);
        // output
        parent::display();
    }

    // DETAIL, ADD, EDIT, DELETE
    //detail
    public function detail($nav_id) {
        // set template content
        $this->tsmarty->assign("template_content", "settings/menus/detail.html");
        // check empty user_id
        if(empty($nav_id)) {
            $this->session->set_flashdata('message', array('msg' => 'Data menu tidak ditemukan. Silakan coba lagi.', 'status' => 'error'));
            redirect(site_url('settings/menus'));
        }
        // get data
        $this->tsmarty->assign('menu', $this->site->get_menu_by_id($nav_id));
        // output
        parent::display();
    }

    // add
    public function add() {
        // set template content
        $this->tsmarty->assign("template_content", "settings/menus/add.html");
        // get data parent
        $data = $this->site->get_list_parent_menu();
        $this->tsmarty->assign("parents", $data);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('nav_id', 'ID Menu', 'trim|required');
            $this->form_validation->set_rules('parent_id', 'Parent Menu', 'trim|required');
            $this->form_validation->set_rules('nav_title', 'Nama Menu', 'trim|required');
            $this->form_validation->set_rules('nav_icon', 'Icon Menu', 'trim|required');
            $this->form_validation->set_rules('nav_url', 'URL', 'trim|required');
            $this->form_validation->set_rules('nav_no', 'Urutan Menu', 'trim|required');
            $this->form_validation->set_rules('nav_st', 'Status', 'trim|required');
            $this->form_validation->set_rules('nav_display', 'Display', 'trim|required');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'nav_id' => $this->input->post('nav_id'),
                    'parent_id' => $this->input->post('parent_id'),
                    'site_id' => $this->app_portal['site_id'],
                    'nav_title' => $this->input->post('nav_title'),
                    'nav_desc' => NULL,
                    'nav_icon' => $this->input->post('nav_icon'),
                    'nav_url' => $this->input->post('nav_url'),
                    'nav_no' => $this->input->post('nav_no'),
                    'nav_st' => $this->input->post('nav_st'),
                    'nav_display' => $this->input->post('nav_display'),
                    'nav_loc' => 'left',
                    'created_by' => $this->user_data['user_id'],
                    'created' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s')
                ];
                if($this->site->add_menu($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                }
            }  else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
            }
            redirect(site_url('settings/menus/add'));
        }
        // output
        parent::display();
    }

    // edit
    public function edit($nav_id) {
        // set template content
        $this->tsmarty->assign("template_content", "settings/menus/edit.html");
        // get data parent
        $data = $this->site->get_list_parent_menu();
        $this->tsmarty->assign("parents", $data);
        // get data
        $this->tsmarty->assign('menu', $this->site->get_menu_by_id($nav_id));
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('nav_id', 'ID Menu', 'trim|required');
            $this->form_validation->set_rules('parent_id', 'Parent Menu', 'trim|required');
            $this->form_validation->set_rules('nav_title', 'Nama Menu', 'trim|required');
            $this->form_validation->set_rules('nav_icon', 'Icon Menu', 'trim|required');
            $this->form_validation->set_rules('nav_url', 'URL', 'trim|required');
            $this->form_validation->set_rules('nav_no', 'Urutan Menu', 'trim|required');
            $this->form_validation->set_rules('nav_st', 'Status', 'trim|required');
            $this->form_validation->set_rules('nav_display', 'Display', 'trim|required');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'nav_id' => $this->input->post('nav_id'),
                    'parent_id' => $this->input->post('parent_id'),
                    'site_id' => $this->app_portal['site_id'],
                    'nav_title' => $this->input->post('nav_title'),
                    'nav_desc' => NULL,
                    'nav_icon' => $this->input->post('nav_icon'),
                    'nav_url' => $this->input->post('nav_url'),
                    'nav_no' => $this->input->post('nav_no'),
                    'nav_st' => $this->input->post('nav_st'),
                    'nav_display' => $this->input->post('nav_display'),
                    'nav_loc' => 'left',
                    'created_by' => $this->user_data['user_id'],
                    'created' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s')
                ];
                if($this->site->update_menu($nav_id, $data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                }
            }  else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
            }
            redirect(site_url('settings/menus/edit/' . $this->input->post('nav_id')));
        }
        // output
        parent::display();
    }

    // delete
    public function delete($nav_id) {
        // check empty user_id
        if(empty($nav_id)) {
            $this->session->set_flashdata('message', array('msg' => 'Data menu tidak ditemukan. Silakan coba lagi.', 'status' => 'error'));
            redirect(site_url('settings/menus'));
        }
        // hapus preference
        if($this->site->delete_menu($nav_id)) { 
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        // redirect
        redirect(site_url('settings/menus'));
    }

    // ajax 
    public function get_nav_id(){
        if ($this->input->is_ajax_request()) {
            $json = [];
            if ($this->input->get(['parent_id'], TRUE)) {

                $parent_id = $this->input->get(['parent_id'], TRUE);
                $json['nav_id'] = $this->site->get_nav_id_by_parent($parent_id);
                $json['nav_no'] = $this->site->get_nav_no_by_parent($parent_id);
            }
            echo json_encode($json);
        }
        die();
    }
}