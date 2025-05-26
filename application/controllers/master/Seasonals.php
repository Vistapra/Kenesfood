<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PrivateBase.php');

// --
class Seasonals extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('master/M_seasonals', 'm_seasonals');
        // $this->load->model('master/M_products', 'm_products');
       
    }

    public function index() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/seasonals/index.html");
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
        $config['base_url'] = site_url('master/seasonals/index/');
        $config['total_rows'] = $this->m_seasonals->get_total_data($keyword);
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
        $data = $this->m_seasonals->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    public function add() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/seasonals/add.html");
        
        // save data
        if($this->input->post()){
          
            $this->form_validation->set_rules('season_name', 'Nama Season', 'trim|required');
            $this->form_validation->set_rules('season_st', 'Status', 'trim');
            $this->form_validation->set_rules('season_desc', 'Keterangan', 'trim|required');
          
            if ($this->form_validation->run() !== FALSE) {
            

                $data = [
                    
                    'season_name' => $this->input->post('season_name'),
                    'season_desc' => $this->input->post('season_desc'),
                    'season_st' => $this->input->post('season_st'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/season/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/season/", 0755);
                }

                if($_FILES['season_logo']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['season_logo']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/season/';
                    $config['allowed_types']        = 'mp4|mpeg|mpg|mpe';
                    $config['max_size']             = 6000;
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('season_name').'_lg')) . '.' . $ext;
                    $config['overwrite']            = TRUE;
             

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('season_logo')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/seasonals/add/');
                    }
                    $data_upload = $this->upload->data();
                    $data['season_logo'] = $data_upload['file_name'];

                } 
                if($_FILES['season_background']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['season_background']['name']);
                    $ext = end($temp);
                    // upload image
                    $config1['upload_path']          = './resource/assets-frontend/dist/season/';
                    $config1['allowed_types']        = 'svg|gif|jpg|png';
                    // $config1['file_name']            = str_replace(' ', '-', strtolower($this->input->post('season_name').'_bk')) . '.' . $ext;
                    $config1['overwrite']            = TRUE;

                    $this->load->library('upload', $config1);
                    if (!$this->upload->do_upload('season_background')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/seasonals/add/');
                    }
                    $data_upload1 = $this->upload->data();
                    $data['season_background'] = $data_upload1['file_name'];

                }
                if($_FILES['season_banner']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['season_banner']['name']);
                    $ext = end($temp);
                    // upload image
                    $config2['upload_path']          = './resource/assets-frontend/dist/season/';
                    $config2['allowed_types']        = 'svg|gif|jpg|png';
                    // $config1['file_name']            = str_replace(' ', '-', strtolower($this->input->post('season_name').'_bk')) . '.' . $ext;
                    $config2['overwrite']            = TRUE;

                    $this->load->library('upload', $config2);
                    if (!$this->upload->do_upload('season_banner')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/seasonals/add/');
                    }
                    $data_upload2 = $this->upload->data();
                    $data['season_banner'] = $data_upload2['file_name'];

                }
                else {
                    $this->session->set_flashdata('message', array('msg' => 'Foto Produk tidak boleh kosong.', 'status' => 'error'));
                    redirect('master/seasonals/add/');
                }

                if($this->m_seasonals->add_season($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/seasonals/add/');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/seasonals/add/');
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/seasonals/add/');
            }
        }
        // output
        parent::display();
    }

    public function edit($season_id) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/seasonals/edit.html");
        
        $detail = $this->m_seasonals->get_detail_product($season_id);
        $this->tsmarty->assign('detail', $detail);
        // save data
        if($this->input->post()){
          
            $this->form_validation->set_rules('season_name', 'Nama Season', 'trim|required');
            $this->form_validation->set_rules('season_st', 'Status', 'trim');
            $this->form_validation->set_rules('season_desc', 'Keterangan', 'trim|required');
          
            if ($this->form_validation->run() !== FALSE) {
            

                $data = [
                    
                    'season_name' => $this->input->post('season_name'),
                    'season_desc' => $this->input->post('season_desc'),
                    'season_st' => $this->input->post('season_st'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/season/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/season/", 0755);
                }

                if($_FILES['season_logo']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['season_logo']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/season/';
                    $config['allowed_types']        = 'mp4|mpeg|mpg|mpe';
                    $config['max_size']             = 6000;
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('season_name').'_lg')) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('season_logo')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/seasonals/edit/');
                    }
                    $data_upload = $this->upload->data();
                    $data['season_logo'] = $data_upload['file_name'];

                } 
                if($_FILES['season_background']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['season_background']['name']);
                    $ext = end($temp);
                    // upload image
                    $config1['upload_path']          = './resource/assets-frontend/dist/season/';
                    $config1['allowed_types']        = 'svg|gif|jpg|png';
                    // $config1['file_name']            = str_replace(' ', '-', strtolower($this->input->post('season_name').'_bk')) . '.' . $ext;
                    $config1['overwrite']            = TRUE;

                    $this->load->library('upload', $config1);
                    if (!$this->upload->do_upload('season_background')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/seasonals/edit/');
                    }
                    $data_upload1 = $this->upload->data();
                    $data['season_background'] = $data_upload1['file_name'];

                }
                if($_FILES['season_banner']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['season_banner']['name']);
                    $ext = end($temp);
                    // upload image
                    $config2['upload_path']          = './resource/assets-frontend/dist/season/';
                    $config2['allowed_types']        = 'svg|gif|jpg|png';
                    // $config1['file_name']            = str_replace(' ', '-', strtolower($this->input->post('season_name').'_bk')) . '.' . $ext;
                    $config2['overwrite']            = TRUE;

                    $this->load->library('upload', $config2);
                    if (!$this->upload->do_upload('season_banner')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/seasonals/edit/');
                    }
                    $data_upload2 = $this->upload->data();
                    $data['season_banner'] = $data_upload2['file_name'];

                }
                // else {
                //     $this->session->set_flashdata('message', array('msg' => 'Foto Produk tidak boleh kosong.', 'status' => 'error'));
                //     redirect('master/seasonals/edit/'. $season_id);
                // }

                if($this->m_seasonals->update_season($this->input->post('season_id'),$data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/seasonals/edit/'. $season_id);
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/seasonals/edit/'. $season_id);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/seasonals/edit/'. $season_id);
            }
        }
        // output
        parent::display();
    }

    public function delete($season_id) {
        // detail product

        $detail = $this->m_seasonals->get_detail_product($season_id);

        if ($this->m_seasonals->delete_product($season_id)) {
            // delete image
            $file_path = FCPATH . './resource/assets-frontend/dist/season/' .$detail['season_background'];
            if (file_exists($file_path)) {
                unlink($file_path);
            } 
            $file_path2 = FCPATH . './resource/assets-frontend/dist/season/' .$detail['season_logo'];
            if (file_exists($file_path2)) {
                unlink($file_path2);
            } 
            $file_path3 = FCPATH . './resource/assets-frontend/dist/season/' .$detail['season_banner'];
            if (file_exists($file_path3)) {
                unlink($file_path3);
            } 
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        redirect('master/seasonals');
}
}