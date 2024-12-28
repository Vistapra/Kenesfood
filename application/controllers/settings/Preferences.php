<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

class Preferences extends ApplicationBase {

    // constructor
    function __construct() {
        // parent constructor
        parent::__construct();
        $this->load->model('apps/M_preferences', 'm_preferences');
    }

    // index
    function index() {
        $this->tsmarty->assign("template_content", "settings/preferences/index.html");
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_preference');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_preference");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_preference", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('settings/preferences/index/');
        $config['total_rows'] = $this->m_preferences->get_total_data($keyword);
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
        $params = array(($start - 1), $config['per_page']);
        $data = $this->m_preferences->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    /**
     *  DETAIL, ADD, EDIT, DELETE
     */

    // detail
    public function detail($id) {
        $this->tsmarty->assign("template_content", "settings/preferences/detail.html");
        // get data
        $preference = $this->m_preferences->get_detail_preference($id);
        $this->tsmarty->assign("preference", $preference);
        // output
        parent::display();
    }
    
    // add
    public function add() {
        // set template content
        $this->tsmarty->assign("template_content", "settings/preferences/add.html");
        // list portal
        $portals = $this->site->get_list_portal();
        $this->tsmarty->assign('portals', $portals);
        // add process
        if  ($this->input->post()) {
            $this->form_validation->set_rules('site_id', 'Portal', 'trim|required');
            $this->form_validation->set_rules('pref_group', 'Group', 'trim|required');
            $this->form_validation->set_rules('pref_label', 'Label', 'trim|required');
            $this->form_validation->set_rules('pref_name', 'Name', 'trim|required');
            $this->form_validation->set_rules('pref_value', 'Value', 'trim|required');

            if ($this->form_validation->run() == FALSE) {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('settings/preferences/add');
            } else {
                $data = array(
                    'site_id' => $this->input->post('site_id'),
                    'pref_group' => $this->input->post('pref_group'),
                    'pref_label' => $this->input->post('pref_label'),
                    'pref_name' => $this->input->post('pref_name'),
                    'pref_value' => $this->input->post('pref_value'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                );
                // simpan data
                if($this->m_preferences->add_preferences($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan.', 'status' => 'success'));
                    redirect('settings/preferences');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('settings/preferences/add');
                }
            }
        }
        // output
        parent::display();
    }
    
    // edit
    public function edit($id) {
        $this->tsmarty->assign("template_content", "settings/preferences/edit.html");
        // list portal
        $portals = $this->site->get_list_portal();
        $this->tsmarty->assign('portals', $portals);
        // get data
        $preference = $this->m_preferences->get_detail_preference($id);
        $this->tsmarty->assign("preference", $preference);
        // add process
        if  ($this->input->post()) {
            $this->form_validation->set_rules('site_id', 'Portal', 'trim|required');
            $this->form_validation->set_rules('pref_group', 'Group', 'trim|required');
            $this->form_validation->set_rules('pref_label', 'Label', 'trim|required');
            $this->form_validation->set_rules('pref_name', 'Name', 'trim|required');
            $this->form_validation->set_rules('pref_value', 'Value', 'trim|required');

            if ($this->form_validation->run() == FALSE) {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('settings/preferences/edit/' . $id);
            } else {
                $data = array(
                    'site_id' => $this->input->post('site_id'),
                    'pref_group' => $this->input->post('pref_group'),
                    'pref_label' => $this->input->post('pref_label'),
                    'pref_name' => $this->input->post('pref_name'),
                    'pref_value' => $this->input->post('pref_value'),
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                );
                // simpan data
                if($this->m_preferences->update_preferences($id, $data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan.', 'status' => 'success'));
                    redirect('settings/preferences/edit/' . $id);
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('settings/preferences/edit/' . $id);
                }
            }
        }
        // output
        parent::display();
    }
    
    // delete
    public function delete($id) {
        // get data
        $preference = $this->m_preferences->get_detail_preference($id);
        $this->tsmarty->assign("preference", $preference);
        // delete
        if ($this->m_preferences->delete_preferences($id)) {
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        redirect('settings/preferences');
    }
}