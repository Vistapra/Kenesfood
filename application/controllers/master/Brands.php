<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

class Brands extends ApplicationBase {

    // constructor
    function __construct() {
        // parent constructor
        parent::__construct();
        $this->load->model('master/M_brands', 'm_brands');
    }

    // index
    function index() {
        $this->tsmarty->assign("template_content", "master/brands/index.html");
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_brand');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_brand");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_brand", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('master/brands/index/');
        $config['total_rows'] = $this->m_brands->get_total_data($keyword);
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
        $data = $this->m_brands->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    /**
     *  DETAIL, ADD, EDIT, DELETE
     */

    // detail
    public function detail($id) {
        $this->tsmarty->assign("template_content", "master/brands/detail.html");
        // get data
        $brand = $this->m_brands->get_detail_brand($id);
        $this->tsmarty->assign("brand", $brand);
        // output
        parent::display();
    }
    
    // edit
    public function edit($id) {
        $this->tsmarty->assign("template_content", "master/brands/edit.html");
        // get data
        $brand = $this->m_brands->get_detail_brand($id);
        $this->tsmarty->assign("brand", $brand);
        // add process
        if  ($this->input->post()) {
            // $this->form_validation->set_rules('brand_type', 'Jenis Brand', 'trim');
            $this->form_validation->set_rules('brand_name', 'Nama Brand', 'trim');
            $this->form_validation->set_rules('brand_desc', 'Deskripsi Brand', 'trim');
            // $this->form_validation->set_rules('brand_status', 'Status', 'trim');
            $this->form_validation->set_rules('brand_tagline', 'Brand Tagline', 'trim');

            if ($this->form_validation->run() == FALSE) {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/brands/edit/' . $id);
            } else {
                $data = array(
                    // 'brand_type' => $this->input->post('brand_type'),
                    'brand_name' => $this->input->post('brand_name'),
                    'brand_desc' => $this->input->post('brand_desc'),
                    // 'brand_st' => $this->input->post('brand_st'),
                    'brand_tagline' => $this->input->post('brand_tagline'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                );
                //jika video di upload
                if ($_FILES['brand_banner']['tmp_name']) {
                    $temp = explode(".", $_FILES['brand_banner']['name']);
                    $ext = end($temp);
                    $config['upload_path']          = './resource/assets-frontend/dist/banner/';
                    $config['allowed_types']        = 'mp4|mpeg|mpg|mpe';
                    $config['max_size']             = 6000;
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('brand_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('brand_banner')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/brands/edit/' . $id);
                    }
                    $data_logo = $this->upload->data();
                    $data['brand_banner'] = $data_logo['file_name'];
                } 
                // $this->m_brands->update_brands($id, $data);
                // $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan.', 'status' => 'success'));
                // redirect('master/brands');

                //tidak upload foto  
                if($this->m_brands->update_brands($id, $data)){
                $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan.', 'status' => 'success'));
                 } else{
                    print_r($this->db->error());
                }
            }
        }
        // output
        parent::display();
    }
    
    // delete
    public function delete($id) {
        // get data
        $brand = $this->m_brands->get_detail_brand($id);
        $this->tsmarty->assign("brand", $brand);
        // delete
        if ($this->m_brands->delete_brands($id)) {
            $file_path = FCPATH . './resource/assets-frontend/dist/brand/' . $brand['brand_banner'];
            if (file_exists($file_path)) {
                unlink($file_path);
            } 
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        redirect('master/brands');
    }
}