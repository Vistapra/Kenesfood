<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

// --
class Promotions extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('master/M_promotions', 'm_promotions');
        $this->load->model('master/M_brands', 'm_brands');
    }

    // index
    public function index() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/promotions/index.html");
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_promotion');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_promotion");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_promotion", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('master/promotions/index/');
        $config['total_rows'] = $this->m_promotions->get_total_data($keyword);
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
        $data = $this->m_promotions->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }
    
    // DETAIL, ADD, EDIT, DELETE

    // detail
    public function detail($id){
        // Set template content
        $this->tsmarty->assign("template_content", "master/promotions/detail.html");
        // get data
        $detail = $this->m_promotions->get_detail_promotion($id);
        $this->tsmarty->assign("detail", $detail);
        // output
        parent::display();
    }

    // add
    public function add(){
        // Set template content
        $this->tsmarty->assign("template_content", "master/promotions/add.html");
        $brands = $this->m_brands->get_list_brand();
        $this->tsmarty->assign('brands', $brands);
        // process
        if($this->input->post()){
            $this->form_validation->set_rules('promotion_code', 'Kode Promo', 'trim');
            $this->form_validation->set_rules('promotion_name', 'Nama Promo', 'trim');
            $this->form_validation->set_rules('brands', 'Brands', 'trim');
            $this->form_validation->set_rules('promotion_date_start', 'Tanggal Mulai', 'trim');
            $this->form_validation->set_rules('promotion_date_end', 'Tanggal Selesai', 'trim');
            $this->form_validation->set_rules('promotion_st', 'Status', 'trim');
            $this->form_validation->set_rules('promotion_desc', 'Deskripsi', 'trim');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'promotion_code' => $this->input->post('promotion_code'),
                    'promotion_name' => $this->input->post('promotion_name'),
                    'brands' => $this->input->post('brands'),
                    'promotion_date_start' => $this->input->post('promotion_date_start'),
                    'promotion_date_end' => $this->input->post('promotion_date_end'),
                    'promotion_st' => $this->input->post('promotion_st'),
                    'promotion_desc' => $this->input->post('promotion_desc'),
                    'created_by' => $this->user_data['user_id'],
                    'created' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s')
                ];

                $dir = "./resource/assets-frontend/dist/promotion/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/promotion/", 0755);
                }

                if($_FILES['promotion_photo_portrait']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['promotion_photo_portrait']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = $dir;
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('promotion_name'))) . '-portrait.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload');
                    $this->upload->initialize($config);
                    if (!$this->upload->do_upload('promotion_photo_portrait')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect(site_url('master/promotions/edit/' . $id));
                    }
                    $upload_data = $this->upload->data();
                    // resize
                    $this->load->library('image_lib');
                    $config['image_library']  = 'gd2';
                    $config['source_image']   = $dir. $upload_data['file_name'];       
                    $config['create_thumb']   = FALSE;
                    $config['maintain_ratio'] = TRUE;
                    $config['width']          = 1080;
                    $config['height']         = 1350;          
                    $this->image_lib->initialize($config);
                    if (!$this->image_lib->resize()){
                        $error = array('error' => strip_tags($this->image_lib->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect(site_url('master/promotions/edit/' . $id));
                    }
                    $data['promotion_photo_portrait'] = $upload_data['file_name'];
                }
                
                if($_FILES['promotion_photo_landscape']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['promotion_photo_landscape']['name']);
                    $ext = end($temp);
                    // upload image
                    $config2['upload_path']          = $dir;
                    $config2['allowed_types']        = 'svg|gif|jpg|png';
                    $config2['file_name']            = str_replace(' ', '-', strtolower($this->input->post('promotion_name'))) . '-landscape.' . $ext;
                    $config2['overwrite']            = TRUE;

                    $this->load->library('upload');
                    $this->upload->initialize($config2);
                    if (!$this->upload->do_upload('promotion_photo_landscape')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                    }
                    $upload_data = $this->upload->data();
                    // resize
                    $this->load->library('image_lib');
                    $config2['image_library']  = 'gd2';
                    $config2['source_image']   = $dir. $upload_data['file_name'];       
                    $config2['create_thumb']   = FALSE;
                    $config2['maintain_ratio'] = TRUE;
                    $config2['width']          = 940;
                    $config2['height']         = 788;          
                    $this->image_lib->initialize($config2);
                    if (!$this->image_lib->resize()){
                        $error = array('error' => strip_tags($this->image_lib->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect(site_url('master/promotions/edit/' . $id));
                    }
                    $data['promotion_photo_landscape'] = $upload_data['file_name'];
                }

                if($this->m_promotions->add_promotions($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect(site_url('master/promotions/add'));
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect(site_url('master/promotions/add'));
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect(site_url('master/promotions/add'));
            }
        }
        // output
        parent::display();
    }

    // edit
    public function edit($id){
        // Set template content
        $this->tsmarty->assign("template_content", "master/promotions/edit.html");
        $brands = $this->m_brands->get_list_brand();
        $this->tsmarty->assign('brands', $brands);
        // get data
        $detail = $this->m_promotions->get_detail_promotion($id);
        $this->tsmarty->assign("detail", $detail);
        // process
        if($this->input->post()){
            $this->form_validation->set_rules('promotion_code', 'Kode Promo', 'trim');
            $this->form_validation->set_rules('promotion_name', 'Nama Promo', 'trim');
            $this->form_validation->set_rules('brands', 'Brands', 'trim');
            $this->form_validation->set_rules('promotion_date_start', 'Tanggal Mulai', 'trim');
            $this->form_validation->set_rules('promotion_date_end', 'Tanggal Selesai', 'trim');
            $this->form_validation->set_rules('promotion_st', 'Status', 'trim');
            $this->form_validation->set_rules('promotion_desc', 'Deskripsi', 'trim');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'promotion_code' => $this->input->post('promotion_code'),
                    'promotion_name' => $this->input->post('promotion_name'),
                    'promotion_date_start' => $this->input->post('promotion_date_start'),
                    'promotion_date_end' => $this->input->post('promotion_date_end'),
                    'promotion_st' => $this->input->post('promotion_st'),
                    'promotion_desc' => $this->input->post('promotion_desc'),
                    'brands' => $this->input->post('brands'),
                    'created_by' => $this->user_data['user_id'],
                    'created' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s')
                ];

                $dir = "./resource/assets-frontend/dist/promotion/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/promotion/", 0755);
                }

                if($_FILES['promotion_photo_portrait']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['promotion_photo_portrait']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = $dir;
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('promotion_name'))) . '-portrait.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload');
                    $this->upload->initialize($config);
                    if (!$this->upload->do_upload('promotion_photo_portrait')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect(site_url('master/promotions/edit/' . $id));
                    }
                    $upload_data = $this->upload->data();
                    // resize
                    $this->load->library('image_lib');
                    $config['image_library']  = 'gd2';
                    $config['source_image']   = $dir. $upload_data['file_name'];       
                    $config['create_thumb']   = FALSE;
                    $config['maintain_ratio'] = TRUE;
                    $config['width']          = 1080;
                    $config['height']         = 1350;          
                    $this->image_lib->initialize($config);
                    if (!$this->image_lib->resize()){
                        $error = array('error' => strip_tags($this->image_lib->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect(site_url('master/promotions/edit/' . $id));
                    }
                    $data['promotion_photo_portrait'] = $upload_data['file_name'];
                }
                
                if($_FILES['promotion_photo_landscape']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['promotion_photo_landscape']['name']);
                    $ext = end($temp);
                    // upload image
                    $config2['upload_path']          = $dir;
                    $config2['allowed_types']        = 'svg|gif|jpg|png';
                    $config2['file_name']            = str_replace(' ', '-', strtolower($this->input->post('promotion_name'))) . '-landscape.' . $ext;
                    $config2['overwrite']            = TRUE;

                    $this->load->library('upload');
                    $this->upload->initialize($config2);
                    if (!$this->upload->do_upload('promotion_photo_landscape')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                    }
                    $upload_data = $this->upload->data();
                    // resize
                    $this->load->library('image_lib');
                    $config2['image_library']  = 'gd2';
                    $config2['source_image']   = $dir. $upload_data['file_name'];       
                    $config2['create_thumb']   = FALSE;
                    $config2['maintain_ratio'] = TRUE;
                    $config2['width']          = 940;
                    $config2['height']         = 788;          
                    $this->image_lib->initialize($config2);
                    if (!$this->image_lib->resize()){
                        $error = array('error' => strip_tags($this->image_lib->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect(site_url('master/promotions/edit/' . $id));
                    }
                    $data['promotion_photo_landscape'] = $upload_data['file_name'];
                }

                if($this->m_promotions->update_promotions($id, $data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect(site_url('master/promotions/edit/' . $id));
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect(site_url('master/promotions/edit/' . $id));
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect(site_url('master/promotions/edit/' . $id));
            }
        }
        // output
        parent::display();
    }
    
    public function delete($id) {
        // get data
        $detail = $this->m_promotions->get_detail_promotion($id);
        $this->tsmarty->assign("detail", $detail);
        // delete
        if ($this->m_promotions->delete_promotions($id)) {
            // delete image portrait
            if(!empty($detail['promotion_photo_portrait'])) {
                $file_path = FCPATH . './resource/assets-frontend/dist/promotion/' . $detail['promotion_photo_portrait'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                } 
            }
            // delete image landscape
            if(!empty($detail['promotion_photo_landscape'])) {
                $file_path = FCPATH . './resource/assets-frontend/dist/promotion/' . $detail['promotion_photo_landscape'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                } 
            }
            // Jika penghapusan berhasil, set pesan sukses
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            // Jika penghapusan gagal, set pesan error
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        // Redirect ke halaman yang sesuai
        redirect('master/promotions');
    }

   
    
    
}





                          