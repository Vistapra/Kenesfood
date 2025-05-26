<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

// --
class Roles extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        $this->load->model('settings/M_role', 'm_role');
    }

    // index
    public function index() {
        // set template content
        $this->tsmarty->assign("template_content", "settings/roles/index.html");
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_role');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_role");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_role", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('settings/roles/index/');
        $config['total_rows'] = $this->m_role->get_total_data($this->app_portal['site_id'], $keyword);
        $config['uri_segment'] = 4;
        $config['per_page'] = 10;
        $this->pagination->initialize($config);
        $pagination['data'] = $this->pagination->create_links();
        // pagination attribute
        $start = $this->uri->segment(4, 0) + 1;
        $end = $this->uri->segment(4, 0) + $config['per_page'];
        $end = (($end > $config['total_rows']) ? $config['total_rows'] : $end);
        $pagination['start'] = ($config['total_rows'] == 0) ? 0 : $start;
        $pagination['end'] = $end;
        $pagination['total'] = $config['total_rows'];
        // pagination assign value
        $this->tsmarty->assign("pagination", $pagination);
        $this->tsmarty->assign("no", $start);
        /* end of pagination ---------------------- */
        // get list data
        $params = array($this->app_portal['site_id'], ($start - 1), $config['per_page']);
        $data = $this->m_role->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    // DETAIL, ADD, EDIT, UPDATE STATUS

    // detail
    public function detail($role_id) {
        $this->tsmarty->assign("template_content", "settings/roles/detail.html");
        // list menu
        $this->tsmarty->assign('list', $this->site->get_list_menu_by_role_id($role_id, $this->app_settings['menu_default']));
        // detail role
        $this->tsmarty->assign('role', $this->m_role->get_detail_role($role_id));
        // save data
        if($this->input->post()) {
            $this->form_validation->set_rules('nav_id[]', 'Menu', 'trim|required');
            $this->form_validation->set_rules('read[]', 'Read', 'trim');
            $this->form_validation->set_rules('create[]', 'Create', 'trim');
            $this->form_validation->set_rules('edit[]', 'Edit', 'trim');
            $this->form_validation->set_rules('delete[]', 'Delete', 'trim');
            if ($this->form_validation->run() !== FALSE) {
                // clear app role menu
                if(!$this->m_role->delete_role_menu($role_id)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect(site_url('settings/roles/detail/'.$role_id));
                }
                // insert menu default
                $menu_default = explode(',', $this->app_settings['menu_default']); 
                if(!empty($menu_default)) {
                    foreach($menu_default as $nav_id) {
                        $data = [
                            'nav_id' => $nav_id,
                            'role_id' => $role_id,
                            'read' => '1',
                            'create' => '1',
                            'edit' => '1',
                            'delete' => '1',
                        ];
                        if(!$this->m_role->add_role_menu($data)) {
                            $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                            redirect(site_url('settings/roles/detail/'.$role_id));
                        }
                    }
                } 
                // insert setting menu   
                $nav_ids = $this->input->post('nav_id', TRUE);
                $read = $this->input->post('read', TRUE);
                $create = $this->input->post('create', TRUE);
                $edit = $this->input->post('edit', TRUE);
                $delete = $this->input->post('delete', TRUE);
                foreach ($nav_ids as $nav_id) {
                    if(isset($read[$nav_id]) || isset($create[$nav_id]) || isset($edit[$nav_id]) || isset($delete[$nav_id])) {
                        $data = [
                            'role_id' => $role_id,
                            'nav_id' => $nav_id,
                            'read' => isset($read[$nav_id]) ? $read[$nav_id] : 0,
                            'create' => isset($create[$nav_id]) ? $create[$nav_id] : 0,
                            'edit' => isset($edit[$nav_id]) ? $edit[$nav_id] : 0,
                            'delete' => isset($delete[$nav_id]) ? $delete[$nav_id] : 0,
                        ];
                        // insert role menu
                        if(!$this->m_role->add_role_menu($data)) {
                            $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                            redirect(site_url('settings/roles/detail/'.$role_id));
                        }
                    }
                }
                // --
                $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
            }
            redirect(site_url('settings/roles/detail/' . $role_id));
        }
        parent::display();
    }

    // add
    public function add(){
        // set template content
        $this->tsmarty->assign("template_content", "settings/roles/add.html");
        $role_id = $this->generate_kode();
        $this->tsmarty->assign('role_id', $role_id);
        // --
        if($this->input->post()){
            $this->load->library('form_validation');
            $this->form_validation->set_rules('site_id', 'Site ID', 'required');
            $this->form_validation->set_rules('role_id', 'Role ID', 'required');
            $this->form_validation->set_rules('role_nm', 'Role Name', 'required');
            $this->form_validation->set_rules('role_st', 'Role Status', 'required');
            if($this->form_validation->run() !== FALSE){
                $data = array(
                    'site_id' => $this->input->post('site_id'),
                    'role_id' => $this->input->post('role_id'),
                    'role_nm' => $this->input->post('role_nm'),
                    'role_st' => $this->input->post('role_st'),
                );
                // save data
                if($this->m_role->add_role($data)) {
                    // insert menu default
                    $menu_default = explode(',', $this->app_settings['menu_default']); 
                    if(!empty($menu_default)) {
                        foreach($menu_default as $nav_id) {
                            $data = [
                                'nav_id' => $nav_id,
                                'role_id' => $role_id,
                                'read' => '1',
                                'create' => '1',
                                'edit' => '1',
                                'delete' => '1',
                            ];
                            $this->m_role->add_role_menu($data);
                        }
                    } 
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan.', 'status' => 'success'));
                    redirect('settings/roles/detail/' . $this->input->post('role_id'));
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
            }  
            redirect('settings/roles/add');
        }
        // output
        parent::display();
    }

    // edit
    public function edit($role_id) {
        // set template content
        $this->tsmarty->assign("template_content", "settings/roles/edit.html");
        // detail role
        $this->tsmarty->assign('role', $this->m_role->get_detail_role($role_id));
        // save data
        if($this->input->post()){
            $this->load->library('form_validation');
            $this->form_validation->set_rules('site_id', 'Site ID', 'required');
            $this->form_validation->set_rules('role_id', 'Role ID', 'required');
            $this->form_validation->set_rules('role_nm', 'Role Name', 'required');
            $this->form_validation->set_rules('role_st', 'Role Status', 'required');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'site_id' => $this->input->post('site_id'),
                    'role_nm' => $this->input->post('role_nm'),
                    'role_st' => $this->input->post('role_st'),
                ];
                if($this->m_role->update_role($this->input->post('role_id'), $data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil diubah', 'status' => 'success'));
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal diubah.', 'status' => 'error'));
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
            }
            redirect(site_url('settings/roles/edit/'.$role_id));
        }
        // output
        parent::display();
    }

    public function delete($id) {
        if ($this->m_role->delete_role($id)) {
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        
        redirect('settings/roles/');
    }
       
    private function generate_kode(){
        // Get the maximum code from your function
        $code = $this->m_role->get_last_role_id($this->app_portal['site_id']);
        return $code;
    }
}