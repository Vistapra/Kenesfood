<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PrivateBase.php');

// --
class Products extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('master/M_products', 'm_products');
        $this->load->model('master/M_categories', 'm_categories');
        $this->load->model('master/M_brands', 'm_brands');
        $this->load->model('master/M_marketings', 'm_marketings');
    }

    // index
    public function index() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/products/index.html");
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
        $config['base_url'] = site_url('master/products/index/');
        $config['total_rows'] = $this->m_products->get_total_data($keyword);
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
        $data = $this->m_products->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    // DETAIL, ADD, EDIT, DELETE

    //detail
    public function detail($product_id = '') {
        // Set template content
        $this->tsmarty->assign("template_content", "master/products/detail.html");
        // detail product
        $detail = $this->m_products->get_detail_product($product_id);
        $this->tsmarty->assign('detail', $detail);
  
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
        $config['base_url'] = site_url('master/products/detail/' . $product_id . '/');
        $config['total_rows'] = $this->m_products->get_total_data_varian($product_id, $keyword);
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
        $params = array($product_id, ($start - 1), $config['per_page']);
        $data = $this->m_products->get_list_data_varian($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    // add
    public function add() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/products/add.html");
        // list category
        $cat = $this->m_categories->get_list_category();
        $this->tsmarty->assign('categories', $cat);
        // list brands
        $brands = $this->m_brands->get_list_brand();
        $this->tsmarty->assign('brands', $brands);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('cat_id', 'Kategori Produk', 'trim|required');
            $this->form_validation->set_rules('product_brand', 'Brand Produk', 'trim|required');
            // $this->form_validation->set_rules('product_type', 'Jenis Produk', 'trim|required');
            $this->form_validation->set_rules('product_code', 'Kode Produk', 'trim|required');
            $this->form_validation->set_rules('product_name', 'Nama Produk', 'trim|required');
            $this->form_validation->set_rules('product_desc', 'Deskripsi Produk', 'trim');
            $this->form_validation->set_rules('product_st', 'Status Produk', 'trim|required');
            $this->form_validation->set_rules('product_promote', 'Promosi Produk', 'trim|required');
            $this->form_validation->set_rules('product_price', 'Harga Produk', 'trim|required');
            $this->form_validation->set_rules('product_komposisi', 'Komposisi', 'trim|required');
            $this->form_validation->set_rules('product_no', 'Urutan Tampil', 'trim');
            $this->form_validation->set_rules('expired_date', 'Expired Date', 'trim|required');
            $this->form_validation->set_rules('product_netto', 'Netto', 'trim|required');
            $this->form_validation->set_rules('ek_marketing', 'Status Marketing', 'trim|required');
            $this->form_validation->set_rules('ek_customer', 'Status Customer', 'trim|required');
            $this->form_validation->set_rules('ek_outlet', 'Status Outlet', 'trim|required');
           
            if ($this->form_validation->run() !== FALSE) {
                // cek product code
                if($this->m_products->is_exist_product_code([$this->input->post('product_code')])){
                    $this->session->set_flashdata('message', array('msg' => 'Kode Produk sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
                    redirect('master/products/add/');
                }
                // cek product name
                if($this->m_products->is_exist_product_name([$this->input->post('product_name')])){
                    $this->session->set_flashdata('message', array('msg' => 'Nama Produk sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
                    redirect('master/products/add/');
                }

                $data = [
                    'product_parent' => '0',
                    'cat_id' => $this->input->post('cat_id'),
                    'product_brand' => $this->input->post('product_brand'),
                    // 'product_type' => $this->input->post('product_type'),
                    'product_code' => $this->input->post('product_code'),
                    'product_name' => $this->input->post('product_name'),
                    'product_desc' => $this->input->post('product_desc'),
                    'product_price' => $this->input->post('product_price'),
                    'product_komposisi' => $this->input->post('product_komposisi'),
                    'product_no' => $this->input->post('product_no'),
                    'expired_date' => $this->input->post('expired_date'),
                    'product_netto' => $this->input->post('product_netto'),
                    'product_st' => $this->input->post('product_st'),
                    'ek_marketing' => $this->input->post('ek_marketing'),
                    'ek_customer' => $this->input->post('ek_customer'),
                    'ek_outlet' => $this->input->post('ek_outlet'),
                    'product_promote' => $this->input->post('product_promote'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/product/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/product/", 0755);
                }

                if($_FILES['product_pict']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['product_pict']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/product/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('product_pict')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/products/add/');
                    }
                    $data_upload = $this->upload->data();
                    $data['product_pict'] = $data_upload['file_name'];

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
                    //         redirect('master/products/edit/' . $product_id);
                    //     }
                    // }
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Foto Produk tidak boleh kosong.', 'status' => 'error'));
                    redirect('master/products/add/');
                }

                if($this->m_products->add_product($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/products/add/');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/products/add/');
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/products/add/');
            }
        }
        // output
        parent::display();
    }

    // edit
    public function edit($product_id) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/products/edit.html");
        // detail product
        $detail = $this->m_products->get_detail_product($product_id);
        $this->tsmarty->assign('detail', $detail);
        // print_r($detail);exit();
        // list category
        $cat = $this->m_categories->get_list_category();
        $this->tsmarty->assign('categories', $cat);
        // list brands
        $brands = $this->m_brands->get_list_brand();
        $this->tsmarty->assign('brands', $brands);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('cat_id', 'Kategori Produk', 'trim|required');
            $this->form_validation->set_rules('product_brand', 'Brand Produk', 'trim|required');
            // $this->form_validation->set_rules('product_type', 'Jenis Produk', 'trim|required');
            $this->form_validation->set_rules('product_code', 'Kode Produk', 'trim|required');
            $this->form_validation->set_rules('product_name', 'Nama Produk', 'trim|required');
            $this->form_validation->set_rules('product_desc', 'Deskripsi Produk', 'trim');
            $this->form_validation->set_rules('product_st', 'Status Produk', 'trim|required');
            $this->form_validation->set_rules('product_promote', 'Promosi Produk', 'trim|required');
            $this->form_validation->set_rules('product_price', 'Harga Produk', 'trim|required');
            $this->form_validation->set_rules('product_komposisi', 'Komposisi', 'trim|required');
            $this->form_validation->set_rules('product_no', 'Urutan Tampil', 'trim');
            $this->form_validation->set_rules('expired_date', 'Expired Date', 'trim|required');
            $this->form_validation->set_rules('product_netto', 'Netto', 'trim|required');
            $this->form_validation->set_rules('ek_marketing', 'Status Marketing', 'trim|required');
            $this->form_validation->set_rules('ek_customer', 'Status Customer', 'trim|required');
            $this->form_validation->set_rules('ek_outlet', 'Status Outlet', 'trim|required');
            if ($this->form_validation->run() !== FALSE) {
                // cek product code
                if($this->m_products->is_exist_product_code_by_id([$this->input->post('product_code'), $product_id])){
                    $this->session->set_flashdata('message', array('msg' => 'Kode Produk sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
                    redirect('master/products/edit/' . $product_id);
                }
                // cek product name
                if($this->m_products->is_exist_product_name_by_id([$this->input->post('product_name'), $product_id])){
                    $this->session->set_flashdata('message', array('msg' => 'Nama Produk sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
                    redirect('master/products/edit/' . $product_id);
                }
                $data = [
                    'product_parent' => '0',
                    'cat_id' => $this->input->post('cat_id'),
                    'product_brand' => $this->input->post('product_brand'),
                    'product_type' => $this->input->post('product_type'),
                    'product_code' => $this->input->post('product_code'),
                    'product_name' => $this->input->post('product_name'),
                    'product_desc' => $this->input->post('product_desc'),
                    'product_price' => $this->input->post('product_price'),
                    'product_komposisi' => $this->input->post('product_komposisi'),
                    'product_no' => $this->input->post('product_no'),
                    'expired_date' => $this->input->post('expired_date'),
                    'product_netto' => $this->input->post('product_netto'),
                    'product_st' => $this->input->post('product_st'),
                    'ek_marketing' => $this->input->post('ek_marketing'),
                    'ek_customer' => $this->input->post('ek_customer'),
                    'ek_outlet' => $this->input->post('ek_outlet'),
                    'product_promote' => $this->input->post('product_promote'),
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/product/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/product/", 0755);
                }

                if($_FILES['product_pict']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['product_pict']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/product/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('product_pict')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/products/edit/' . $product_id);
                    }
                    $data_upload = $this->upload->data();
                    $data['product_pict'] = $data_upload['file_name'];

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
                    //         redirect('master/products/edit/' . $product_id);
                    //     }
                    // }
                }

                if($this->m_products->update_product($this->input->post('product_id'), $data)) {
                    // update variant
                    $data = [
                        'cat_id' => $this->input->post('cat_id'),
                        'product_brand' => $this->input->post('product_brand'),
                        'product_type' => $this->input->post('product_type'),
                        'modified' => date('Y-m-d H:i:s'),
                        'modified_by' => $this->user_data['user_id'],
                    ];
                    if($this->m_products->update_variant_product($this->input->post('product_id'), $data)) {
                        $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                        redirect('master/products/detail/' . $product_id);
                    } else {
                        $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                        redirect('master/products/edit/' . $product_id);
                    }
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/products/edit/' . $product_id);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/products/edit/' . $product_id);
            }
        }
        // output
        parent::display();
    }

    // delete 
    public function delete($product_id) {
        // detail product
        $detail = $this->m_products->get_detail_product($product_id);
        // list varian
        $variant = $this->m_products->get_list_varian_product($product_id);
        // delete image
        if(!empty($variant)) {
            foreach($variant as $var) {
                $file_path = FCPATH . './resource/assets-frontend/dist/product/' . $var['product_pict'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                } 
            }
        }
        // delete
        if ($this->m_products->delete_variant_product($product_id)) {
            if ($this->m_products->delete_product($product_id)) {
                // delete image
                $file_path = FCPATH . './resource/assets-frontend/dist/product/' . $detail['product_pict'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                } 
                $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
            } else {
                $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
            }
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        // redirect
        redirect('master/products');
    }

    // VARIANT

    // add varian
    public function add_variant($product_parent = 0) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/products/add_variant.html");
        $this->tsmarty->assign('title', 'Tambah Varian');
        // detail product
        $detail = $this->m_products->get_detail_product($product_parent);
        $this->tsmarty->assign('detail', $detail);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('product_code', 'Kode Produk', 'trim|required');
            $this->form_validation->set_rules('product_name', 'Nama Produk', 'trim|required');
            $this->form_validation->set_rules('product_desc', 'Deskripsi Produk', 'trim');
            $this->form_validation->set_rules('product_st', 'Status Produk', 'trim|required');
            $this->form_validation->set_rules('product_promote', 'Promosi Produk', 'trim|required');
            $this->form_validation->set_rules('product_price', 'Harga Produk', 'trim|required');
            $this->form_validation->set_rules('product_komposisi', 'Komposisi', 'trim|required');
            $this->form_validation->set_rules('product_no', 'Urutan Tampil', 'trim');
            $this->form_validation->set_rules('expired_date', 'Expired Date', 'trim|required');
            $this->form_validation->set_rules('product_netto', 'Netto', 'trim|required');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'product_parent' => $detail['product_id'],
                    'cat_id' => $detail['cat_id'],
                    'product_brand' => $detail['product_brand'],
                    'product_type' => $detail['product_type'],
                    'product_code' => $this->input->post('product_code'),
                    'product_name' => $this->input->post('product_name'),
                    'parent_name'  => $detail['product_name'],
                    'product_desc' => $this->input->post('product_desc'),
                    'product_price' => $this->input->post('product_price'),
                    'product_komposisi' => $this->input->post('product_komposisi'),
                    'product_no' => $this->input->post('product_no'),
                    'expired_date' => $this->input->post('expired_date'),
                    'product_netto' => $this->input->post('product_netto'),
                    'product_st' => $this->input->post('product_st'),
                    'product_promote' => $this->input->post('product_promote'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/product/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/product/", 0755);
                }

                if($_FILES['product_pict']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['product_pict']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/product/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('product_pict')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/products/add_variant/' . $product_parent);
                    }
                    $data_upload = $this->upload->data();
                    $data['product_pict'] = $data_upload['file_name'];

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
                    //         redirect('master/products/add_variant/' . $product_parent);
                    //     }
                    // }
                }

                if($this->m_products->add_product($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/products/detail/' . $product_parent);
                } else {
                    $this->session->set_flashdata('message', array('msg' => $this->db->error()['message'], 'status' => 'error'));
                    redirect('master/products/add_variant/' . $product_parent);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/products/add_variant/' . $product_parent);
            }
        }
        // output
        parent::display();
    }

    // edit varian
    public function edit_variant($product_id) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/products/edit_variant.html");
        $this->tsmarty->assign('title', 'Ubah Varian');
        // detail product
        $detail = $this->m_products->get_detail_product($product_id);
        $this->tsmarty->assign('detail', $detail);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('product_code', 'Kode Produk', 'trim|required');
            $this->form_validation->set_rules('product_name', 'Nama Produk', 'trim|required');
            $this->form_validation->set_rules('product_desc', 'Deskripsi Produk', 'trim');
            $this->form_validation->set_rules('product_st', 'Status Produk', 'trim|required');
            $this->form_validation->set_rules('product_promote', 'Promosi Produk', 'trim|required');
            $this->form_validation->set_rules('product_price', 'Harga Produk', 'trim|required');
            $this->form_validation->set_rules('product_komposisi', 'Komposisi', 'trim|required');
            $this->form_validation->set_rules('product_no', 'Urutan Tampil', 'trim');
            $this->form_validation->set_rules('expired_date', 'Expired Date', 'trim|required');
            $this->form_validation->set_rules('product_netto', 'Netto', 'trim|required');
            if ($this->form_validation->run() !== FALSE) {
                $data = [
                    'product_parent' => $detail['product_parent'],
                    'cat_id' => $detail['cat_id'],
                    'product_brand' => $detail['product_brand'],
                    'product_type' => $detail['product_type'],
                    'product_code' => $this->input->post('product_code'),
                    'product_name' => $this->input->post('product_name'),
                    'product_desc' => $this->input->post('product_desc'),
                    'product_price' => $this->input->post('product_price'),
                    'product_komposisi' => $this->input->post('product_komposisi'),
                    'product_no' => $this->input->post('product_no'),
                    'expired_date' => $this->input->post('expired_date'),
                    'product_netto' => $this->input->post('product_netto'),
                    'product_st' => $this->input->post('product_st'),
                    'product_promote' => $this->input->post('product_promote'),
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/product/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/product/", 0755);
                }

                if($_FILES['product_pict']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['product_pict']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/product/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('product_pict')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/products/edit_variant/' . $product_id);
                    }
                    $data_upload = $this->upload->data();
                    $data['product_pict'] = $data_upload['file_name'];

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
                    //         redirect('master/products/add_variant/' . $product_parent);
                    //     }
                    // }
                }

                if($this->m_products->update_product($this->input->post('product_id'), $data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/products/detail/' . $detail['product_parent']);
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/products/edit_variant/' . $product_id);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/products/edit_variant/' . $product_id);
            }
        }
        // output
        parent::display();
    }

    // delete varian
    public function delete_varian($product_id) {
        // detail product
        $detail = $this->m_products->get_detail_product($product_id);
        $this->tsmarty->assign('detail', $detail);
        // delete
        if ($this->m_products->delete_product($product_id)) {
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        // redirect
        redirect('master/products/detail/' . $detail['product_parent']);
    }

	public function sync()
	{
		
	}
}
