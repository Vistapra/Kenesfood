<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

// --
class Categories extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('master/M_categories', 'm_categories');
        $this->load->model('master/M_brands', 'm_brands');
        $this->load->model('master/M_seasonals', 'm_seasonals');
    }

    // index
    public function index() {
        // set template content
        $this->tsmarty->assign("template_content", "master/categories/index.html");
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_category');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_category");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_category", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('master/categories/index/');
        $config['total_rows'] = $this->m_categories->get_total_data($keyword);
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
        $data = $this->m_categories->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    // DETAIL, ADD, EDIT, DELETE

    //detail
    public function detail($cat_id = '') {
        // Set template content
        $this->tsmarty->assign("template_content", "master/categories/detail.html");
        // detail category
        $detail = $this->m_categories->get_detail_category($cat_id);
        $this->tsmarty->assign('detail', $detail);
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_category');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_category");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_category", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('master/categories/detail/' . $cat_id . '/');
        $config['total_rows'] = $this->m_categories->get_total_data_sub_category($cat_id, $keyword);
        $config['uri_segment'] = 5;
        $config['per_page'] = 10;
        $this->pagination->initialize($config);
        $pagination['data'] = $this->pagination->create_links();
        // pagination attribute
        $start = $this->uri->segment(5, 0) + 1;
        $end = $this->uri->segment(5, 0) + $config['per_page'];
        $end = (($end > $config['total_rows']) ? $config['total_rows'] : $end);
        $pagination['start'] = ($config['total_rows'] == 0) ? 0 : $start;
        $pagination['end'] = $end;
        $pagination['total'] = $config['total_rows'];
        // pagination assign value
        $this->tsmarty->assign("pagination", $pagination);
        $this->tsmarty->assign("no", $start);
        /* end of pagination ---------------------- */
        // get list data
        $params = array($cat_id, ($start - 1), $config['per_page']);
        $data = $this->m_categories->get_list_data_sub_category($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    // add
    public function add() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/categories/add.html");
        // list category
        $cat = $this->m_categories->get_list_category();
        $this->tsmarty->assign('categories', $cat);
        // list brands
        $brands = $this->m_brands->get_list_brand();
        $this->tsmarty->assign('brands', $brands);

        $seasonal = $this->m_seasonals->get_list_product_seasonal();
        $this->tsmarty->assign('seasonal', $seasonal);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('cat_id', 'Kategori Id', 'trim|required');
            $this->form_validation->set_rules('cat_brand', 'Brand Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_code', 'Kode Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_name', 'Nama Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_desc', 'Deskripsi Kategori', 'trim');
            $this->form_validation->set_rules('cat_st', 'Status Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_highlight', 'Tampil', 'trim|required');
            $this->form_validation->set_rules('cat_no', 'Urutan Tampil', 'trim');
            $this->form_validation->set_rules('cat_harga', 'Harga', 'trim|required');
            $this->form_validation->set_rules('seasonal_id', 'Seasonal ID', 'trim');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'cat_parent' => '0',
                    'cat_brand' => $this->input->post('cat_brand'),
                    'cat_code' => $this->input->post('cat_code'),
                    'cat_name' => $this->input->post('cat_name'),
                    'cat_desc' => $this->input->post('cat_desc'),
                    'cat_st' => $this->input->post('cat_st'),
                    'cat_highlight' => $this->input->post('cat_highlight'),
                    'cat_no' => $this->input->post('cat_no'),
                    'cat_harga' => $this->input->post('cat_harga'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                    'seasonal_id' => $this->input->post('seasonal_id'),

                ];
                
                $dir = "./resource/assets-frontend/dist/product/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/product/", 0755);
                }

                if($_FILES['cat_img']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['cat_img']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/product/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('cat_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('cat_img')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/categories/add/');
                    }
                    $data_upload = $this->upload->data();
                    $data['cat_img'] = $data_upload['file_name'];

                    // thumbnails
                    // $dir_thumb = "./resource/assets-frontend/dist/product/thumbnail/";
                    // if (!file_exists($dir_thumb)) {
                    //     mkdir("./resource/assets-frontend/dist/product/thumbnail/", 0755);
                    // }

                    // if($ext != 'svg') {
                    //     $this->load->library('image_lib');
                    //     $config['image_library']  = 'gd2';
                    //     $config['source_image']   = $dir. $data_upload['file_name'];       
                    //     $config['create_thumb']   = TRUE;
                    //     $config['maintain_ratio'] = TRUE;
                    //     $config['width']          = 50;
                    //     $config['height']         = 50;
                    //     $config['new_image']      = $dir_thumb. $data_upload['file_name'];               
                    //     $this->image_lib->initialize($config);
                    //     if (!$this->image_lib->resize()){
                    //         $error = array('error' => strip_tags($this->image_lib->display_errors()));
                    //         $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                    //         redirect('master/categories/edit/' . $cat_id);
                    //     }
                    // }
                }

                if($this->m_categories->add_category($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/categories/add/');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/categories/add/');
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/categories/add/');
            }
        }
        // output
        parent::display();
    }

    // edit
    public function edit($cat_id) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/categories/edit.html");
        // detail product
        $detail = $this->m_categories->get_detail_category($cat_id);
        $this->tsmarty->assign('detail', $detail);
        // list category
        $cat = $this->m_categories->get_list_category();
        $this->tsmarty->assign('categories', $cat);
        // list brands
        $brands = $this->m_brands->get_list_brand();
        $this->tsmarty->assign('brands', $brands);
        $seasonal = $this->m_seasonals->get_list_product_seasonal();
        $this->tsmarty->assign('seasonal', $seasonal);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('cat_id', 'Kategori Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_brand', 'Brand Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_code', 'Kode Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_name', 'Nama Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_desc', 'Deskripsi Kategori', 'trim');
            $this->form_validation->set_rules('cat_st', 'Status Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_highlight', 'Tampil', 'trim|required');
            $this->form_validation->set_rules('cat_no', 'Urutan Tampil', 'trim');
            $this->form_validation->set_rules('cat_harga', 'Harga', 'trim|required');
            $this->form_validation->set_rules('seasonal_id', 'Seasonal ID', 'trim');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'cat_parent' => '0',
                    'cat_brand' => $this->input->post('cat_brand'),
                    'cat_code' => $this->input->post('cat_code'),
                    'cat_name' => $this->input->post('cat_name'),
                    'cat_desc' => $this->input->post('cat_desc'),
                    'cat_st' => $this->input->post('cat_st'),
                    'cat_highlight' => $this->input->post('cat_highlight'),
                    'cat_no' => $this->input->post('cat_no'),
                    'cat_harga' => $this->input->post('cat_harga'),
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                    'seasonal_id' => $this->input->post('seasonal_id'),
                ];
                
                $dir = "./resource/assets-frontend/dist/product/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/product/", 0755);
                }

                if($_FILES['cat_img']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['cat_img']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/product/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('cat_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('cat_img')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/categories/edit/' . $cat_id);
                    }
                    $data_upload = $this->upload->data();
                    $data['cat_img'] = $data_upload['file_name'];

                    // thumbnails
                    // $dir_thumb = "./resource/assets-frontend/dist/product/thumbnail/";
                    // if (!file_exists($dir_thumb)) {
                    //     mkdir("./resource/assets-frontend/dist/product/thumbnail/", 0755);
                    // }

                    // if($ext != 'svg') {
                    //     $this->load->library('image_lib');
                    //     $config['image_library']  = 'gd2';
                    //     $config['source_image']   = $dir. $data_upload['file_name'];       
                    //     $config['create_thumb']   = TRUE;
                    //     $config['maintain_ratio'] = TRUE;
                    //     $config['width']          = 50;
                    //     $config['height']         = 50;
                    //     $config['new_image']      = $dir_thumb. $data_upload['file_name'];               
                    //     $this->image_lib->initialize($config);
                    //     if (!$this->image_lib->resize()){
                    //         $error = array('error' => strip_tags($this->image_lib->display_errors()));
                    //         $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                    //         redirect('master/categories/edit/' . $cat_id);
                    //     }
                    // }
                }

                if($this->m_categories->update_category($this->input->post('cat_id'), $data)) {
                    // update sub kategorit
                    $data = [
                        'cat_brand' => $this->input->post('cat_brand'),
                        'modified' => date('Y-m-d H:i:s'),
                        'modified_by' => $this->user_data['user_id'],
                    ];
                    if($this->m_categories->update_sub_category($this->input->post('cat_id'), $data)) {
                        $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                        redirect('master/categories/detail/' . $cat_id);
                    } else {
                        $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                        redirect('master/categories/edit/' . $cat_id);
                    }
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/categories/edit/' . $cat_id);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/categories/edit/' . $cat_id);
            }
        }
        // output
        parent::display();
    }

    // delete 
    public function delete($cat_id) {
        // delete
        if ($this->m_categories->delete_sub_category($cat_id)) {
            if ($this->m_categories->delete_category($cat_id)) {
                $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
            } else {
                $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
            }
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        // redirect
        redirect('master/categories');
    }

    // SUB Category

    // add sub kategori
    public function add_sub_cat($cat_parent = 0) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/categories/add_sub_cat.html");
        $this->tsmarty->assign('title', 'Tambah sub kategori');
        // detail product
        $detail = $this->m_categories->get_detail_category($cat_parent);
        $this->tsmarty->assign('detail', $detail);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('cat_code', 'Kode Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_name', 'Nama Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_desc', 'Deskripsi Kategori', 'trim');
            $this->form_validation->set_rules('cat_st', 'Status Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_highlight', 'Tampil', 'trim|required');
            $this->form_validation->set_rules('cat_no', 'Urutan Tampil', 'trim');
            $this->form_validation->set_rules('cat_harga', 'Harga', 'trim|required');

            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'cat_parent' => $detail['cat_id'],
                    'cat_brand' => $detail['cat_brand'],
                    'cat_code' => $this->input->post('cat_code'),
                    'cat_name' => $this->input->post('cat_name'),
                    'parent_name_cat' => $detail['cat_name'],
                    'cat_desc' => $this->input->post('cat_desc'),
                    'cat_st' => $this->input->post('cat_st'),
                    'cat_highlight' => $this->input->post('cat_highlight'),
                    'cat_no' => $this->input->post('cat_no'),
                    'cat_harga' => $this->input->post('cat_harga'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                    'seasonal_id' => $detail['seasonal_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/product/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/product/", 0755);
                }

                if($_FILES['cat_img']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['cat_img']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/product/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('cat_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('cat_img')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/categories/add_sub_cat/' . $cat_parent);
                    }
                    $data_upload = $this->upload->data();
                    $data['cat_img'] = $data_upload['file_name'];

                    // thumbnails
                    // $dir_thumb = "./resource/assets-frontend/dist/product/thumbnail/";
                    // if (!file_exists($dir_thumb)) {
                    //     mkdir("./resource/assets-frontend/dist/product/thumbnail/", 0755);
                    // }

                    // if($ext != 'svg') {
                    //     $this->load->library('image_lib');
                    //     $config['image_library']  = 'gd2';
                    //     $config['source_image']   = $dir. $data_upload['file_name'];       
                    //     $config['create_thumb']   = TRUE;
                    //     $config['maintain_ratio'] = TRUE;
                    //     $config['width']          = 50;
                    //     $config['height']         = 50;
                    //     $config['new_image']      = $dir_thumb. $data_upload['file_name'];               
                    //     $this->image_lib->initialize($config);
                    //     if (!$this->image_lib->resize()){
                    //         $error = array('error' => strip_tags($this->image_lib->display_errors()));
                    //         $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                    //         redirect('master/categories/add_sub_cat/' . $cat_parent);
                    //     }
                    // }
                }

                // $this->m_categories->update_cat_sub($detail['cat_id'], $data);
                if($this->m_categories->add_category($data)) {
                    // edit status sub category
                    $data = [
                        'cat_sub' => '1',
                        'modified' => date('Y-m-d H:i:s'),
                        'modified_by' => $this->user_data['user_id'],
                    ];
                    if($this->m_categories->update_category($cat_parent, $data)) {
                        $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                        redirect('master/categories/detail/' . $cat_parent);
                    } else {
                        $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                        redirect('master/categories/add_sub_cat/' . $cat_parent);
                    }
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/categories/add_sub_cat/' . $cat_parent);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/categories/add_sub_cat/' . $cat_parent);
            }
        }
        // output
        parent::display();
    }

    // edit sub kategori
    public function edit_sub_cat($cat_id) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/categories/edit_sub_cat.html");
        $this->tsmarty->assign('title', 'Ubah sub kategori');
        // detail product
        $detail = $this->m_categories->get_detail_category($cat_id);
        $this->tsmarty->assign('detail', $detail);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('cat_code', 'Kode Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_name', 'Nama Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_desc', 'Deskripsi Kategori', 'trim');
            $this->form_validation->set_rules('cat_st', 'Status Kategori', 'trim|required');
            $this->form_validation->set_rules('cat_highlight', 'Tampil', 'trim|required');
            $this->form_validation->set_rules('cat_no', 'Urutan Tampil', 'trim');
            $this->form_validation->set_rules('cat_harga', 'Harga', 'trim|required');
            
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'cat_code' => $this->input->post('cat_code'),
                    'cat_name' => $this->input->post('cat_name'),
                    'cat_desc' => $this->input->post('cat_desc'),
                    'cat_st' => $this->input->post('cat_st'),
                    'cat_highlight' => $this->input->post('cat_highlight'),
                    'cat_no' => $this->input->post('cat_no'),
                    'cat_harga' => $this->input->post('cat_harga'),
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                    'seasonal_id' => $detail['seasonal_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/product/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/product/", 0755);
                }

                if($_FILES['cat_img']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['cat_img']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/product/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('cat_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('cat_img')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/categories/edit_sub_cat/' . $cat_id);
                    }
                    $data_upload = $this->upload->data();
                    $data['cat_img'] = $data_upload['file_name'];

                    // thumbnails
                    // $dir_thumb = "./resource/assets-frontend/dist/product/thumbnail/";
                    // if (!file_exists($dir_thumb)) {
                    //     mkdir("./resource/assets-frontend/dist/product/thumbnail/", 0755);
                    // }

                    // if($ext != 'svg') {
                    //     $this->load->library('image_lib');
                    //     $config['image_library']  = 'gd2';
                    //     $config['source_image']   = $dir. $data_upload['file_name'];       
                    //     $config['create_thumb']   = TRUE;
                    //     $config['maintain_ratio'] = TRUE;
                    //     $config['width']          = 50;
                    //     $config['height']         = 50;
                    //     $config['new_image']      = $dir_thumb. $data_upload['file_name'];               
                    //     $this->image_lib->initialize($config);
                    //     if (!$this->image_lib->resize()){
                    //         $error = array('error' => strip_tags($this->image_lib->display_errors()));
                    //         $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                    //         redirect('master/categories/add_sub_cat/' . $cat_parent);
                    //     }
                    // }
                }

                if($this->m_categories->update_category($this->input->post('cat_id'), $data)) {
                    // cek status sub category
                    $total = $this->m_categories->get_total_sub_category($detail['cat_parent']);
                    if($total == 0) {
                        $data = [
                            'cat_sub' => '0',
                            'modified' => date('Y-m-d H:i:s'),
                            'modified_by' => $this->user_data['user_id'],
                        ];
                    } else {
                        $data = [
                            'cat_sub' => '1',
                            'modified' => date('Y-m-d H:i:s'),
                            'modified_by' => $this->user_data['user_id'],
                        ];
                    }
                    if($this->m_categories->update_category($detail['cat_parent'], $data)) {
                        $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                        redirect('master/categories/detail/' . $detail['cat_parent']);
                    } else {
                        $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                        redirect('master/categories/edit_sub_cat/' . $cat_id);
                    }
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/categories/edit_sub_cat/' . $cat_id);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/categories/edit_sub_cat/' . $cat_id);
            }
        }
        // output
        parent::display();
    }

    // delete sub kategori
    public function delete_sub_cat($cat_id) {
        // detail product
        $detail = $this->m_categories->get_detail_category($cat_id);
        $this->tsmarty->assign('detail', $detail);
        // delete
        if ($this->m_categories->delete_category($cat_id)) {
            // cek status sub category
            $total = $this->m_categories->get_total_sub_category($detail['cat_parent']);
            if($total == 0) {
                $data = [
                    'cat_sub' => '0',
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                ];
            } else {
                $data = [
                    'cat_sub' => '1',
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                ];
            }
            if($this->m_categories->update_category($detail['cat_parent'], $data)) {
                $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus', 'status' => 'success'));
            } else {
                $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
            }
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        // redirect
        redirect('master/categories/detail/' . $detail['cat_parent']);
    }
    
}
