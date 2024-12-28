<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PrivateBase.php');


class Faqs extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('master/M_faqs', 'm_faqs');
       
    }

    public function index() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/faqs/index.html");
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_faq');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_faq");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_faq", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('master/faqs/index/');
        $config['total_rows'] = $this->m_faqs->get_total_data($keyword);
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
        $data = $this->m_faqs->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }
    public function add() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/faqs/add.html");
        $faq = $this->m_faqs->get_list_faq();
        $this->tsmarty->assign('faqs', $faq);
        
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('question', 'Pertanyaan', 'trim|required');
            $this->form_validation->set_rules('answer', 'Jawaban', 'trim');
            $this->form_validation->set_rules('faq_kategori', 'Kategori FAQ', 'trim|required');
            $this->form_validation->set_rules('deleted', 'Status', 'trim|required');
          
            if ($this->form_validation->run() !== FALSE) {
            

                $data = [
                    
                    'question' => $this->input->post('question'),
                    'answer' => $this->input->post('answer'),
                    'faq_kategori' => $this->input->post('faq_kategori'), 
                    'deleted' => $this->input->post('deleted'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/logo/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/logo/", 0755);
                }

                if($_FILES['faq_icon']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['faq_icon']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/logo/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    // $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('season_name').'_lg')) . '.' . $ext;
                    $config['overwrite']            = TRUE;
                    // $config['max_size']             =3000; //ukuran gambar dalam kb

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('faq_icon')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/faqs/add/');
                    }
                    $data_upload = $this->upload->data();
                    $data['faq_icon'] = $data_upload['file_name'];

                } 
             
                else {
                    $this->session->set_flashdata('message', array('msg' => 'Icon FAQ tidak boleh kosong.', 'status' => 'error'));
                    redirect('master/faqs/add/');
                }

                if($this->m_faqs->add_faq($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/faqs/add/');
                } else {
                    print_r($this->db->error());exit;
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/faqs/add/');
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/faqs/add/');
            }
        }
        // output
        parent::display();
    }

    public function edit($faq_id) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/faqs/edit.html");
        $faq = $this->m_faqs->get_list_faq();
        $this->tsmarty->assign('faqs', $faq);
        $detail = $this->m_faqs->get_detail_faq($faq_id);
        $this->tsmarty->assign('detail', $detail);
            // print_r($detail);exit();
        // save data
        if($this->input->post()){
          
            $this->form_validation->set_rules('question', 'Pertanyaan', 'trim|required');
            $this->form_validation->set_rules('answer', 'Jawaban', 'trim');
            $this->form_validation->set_rules('faq_kategori', 'Kategori FAQ', 'trim|required');
            $this->form_validation->set_rules('deleted', 'Status', 'trim|required');
          
            if ($this->form_validation->run() !== FALSE) {
            

                $data = [
                    'question' => $this->input->post('question'),
                    'answer' => $this->input->post('answer'),
                    'faq_kategori' => $this->input->post('faq_kategori'),
                    'deleted' => $this->input->post('deleted'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                ];
                
                $dir = "./resource/assets-frontend/dist/logo/";
                if (!file_exists($dir)) {
                    mkdir("./resource/assets-frontend/dist/logo/", 0755);
                }

                if($_FILES['faq_icon']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['faq_icon']['name']);
                    $ext = end($temp);
                    // upload image
                    $config['upload_path']          = './resource/assets-frontend/dist/logo/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    // $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('season_name').'_lg')) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('faq_icon')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/faqs/edit/');
                    }
                    $data_upload = $this->upload->data();
                    $data['faq_icon'] = $data_upload['file_name'];

                }

                if($this->m_faqs->update_faq($this->input->post('faq_id'),$data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/faqs/edit/'. $faq_id);
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/faqs/edit/'. $faq_id);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/faqs/edit/'. $faq_id);
            }
        }
        // output
        parent::display();
    }

    public function delete($faq_id) {
        // detail faq

        $detail = $this->m_faqs->get_detail_faq($faq_id);

        if ($this->m_faqs->delete_faq($faq_id)) {
            // delete image
            $file_path = FCPATH . './resource/assets-frontend/dist/logo/' .$detail['faq_icon'];
            if (file_exists($file_path)) {
                unlink($file_path);
            } 
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        redirect('master/faqs');
}
}