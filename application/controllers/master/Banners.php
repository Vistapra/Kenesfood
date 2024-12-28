<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PrivateBase.php');

// --
class Banners extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('master/M_banners', 'm_banners');
        // $this->load->model('master/M_products', 'm_products');
       
    }

    public function index() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/banners/index.html");
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_product');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_product");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_product", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('master/banners/index/');
        $config['total_rows'] = $this->m_banners->get_total_data($keyword);
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
        $data = $this->m_banners->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    public function add() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/banners/add.html");

        // save data
        if($this->input->post()){

            $this->form_validation->set_rules('banner_name', 'Nama Banner', 'trim|required');
            $this->form_validation->set_rules('banner_st', 'Status', 'trim');
            $this->form_validation->set_rules('status_catalogue', 'Katalog', 'trim');
          
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'banner_name'      => $this->input->post('banner_name'),
                    'banner_st'        => $this->input->post('banner_st'),
                    'status_catalogue' => $this->input->post('status_catalogue'),
                    'created'          => date('Y-m-d H:i:s'),
                    'created_by'       => $this->user_data['user_id'],
                    'modified'         => date('Y-m-d H:i:s'),
                    'modified_by'      => $this->user_data['user_id'],
                ];

                $dir = "./resource/assets-frontend/dist/banner/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/banner/", 0755);
                }

                if($_FILES['banner_img']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['banner_img']['name']);
                    $ext = end($temp);
                    // upload image
                    $config2['upload_path']          = './resource/assets-frontend/dist/banner/';
                    $config2['allowed_types']        = 'svg|gif|jpg|png';
                    // $config1['file_name']            = str_replace(' ', '-', strtolower($this->input->post('season_name').'_bk')) . '.' . $ext;
                    $config2['overwrite']            = TRUE;

                    $this->load->library('upload', $config2);
                    if (!$this->upload->do_upload('banner_img')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/banners/add/');
                    }
                    $data_upload2 = $this->upload->data();
                    $data['banner_img'] = $data_upload2['file_name'];

                }
                else {
                    $this->session->set_flashdata('message', array('msg' => 'Foto Banner tidak boleh kosong.', 'status' => 'error'));
                    redirect('master/banners/add/');
                }

                if($this->m_banners->add_banner($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/banners/add/');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/banners/add/');
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/banners/add/');
            }
        }
        // output
        parent::display();
    }

    public function edit($banner_id) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/banners/edit.html");

        $detail = $this->m_banners->get_detail_banner($banner_id);
        $this->tsmarty->assign('detail', $detail);

        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('banner_name', 'Nama Banner', 'trim|required');
            $this->form_validation->set_rules('banner_st', 'Status', 'trim');
            $this->form_validation->set_rules('status_catalogue', 'Katalog', 'trim');

            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'banner_name'      => $this->input->post('banner_name'),
                    'banner_st'        => $this->input->post('banner_st'),
                    'status_catalogue' => $this->input->post('status_catalogue'),
                    'created'          => date('Y-m-d H:i:s'),
                    'created_by'       => $this->user_data['user_id'],
                    'modified'         => date('Y-m-d H:i:s'),
                    'modified_by'      => $this->user_data['user_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/banner/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/banner/", 0755);
                }

                if($_FILES['banner_img']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['banner_img']['name']);
                    $ext = end($temp);
                    // upload image
                    $config2['upload_path']          = './resource/assets-frontend/dist/banner/';
                    $config2['allowed_types']        = 'svg|gif|jpg|png';
                    // $config1['file_name']            = str_replace(' ', '-', strtolower($this->input->post('season_name').'_bk')) . '.' . $ext;
                    $config2['overwrite']            = TRUE;

                    $this->load->library('upload', $config2);
                    if (!$this->upload->do_upload('banner_img')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/banners/add/');
                    }
                    $data_upload2 = $this->upload->data();
                    $data['banner_img'] = $data_upload2['file_name'];
                }
                else {
                    $this->session->set_flashdata('message', array('msg' => 'Foto Banner tidak boleh kosong.', 'status' => 'error'));
                    redirect('master/banners/add/');
                }

                if($this->m_banners->add_banner($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/banners/add/');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/banners/add/');
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/banners/add/');
            }
        }
        // output
        parent::display();
    }


    public function delete($banner_id) {
        $detail = $this->m_banners->get_detail_banner($banner_id);

        if ($this->m_banners->delete_banner($banner_id)) {
            // delete image
            $file_path = FCPATH . './resource/assets-frontend/dist/banner/' .$detail['banner_img'];
            if (file_exists($file_path)) {
                unlink($file_path);
            } 
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        redirect('master/banners');
    }
}