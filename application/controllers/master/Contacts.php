<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PrivateBase.php');

// --
class Contacts extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('master/M_contacts', 'm_contacts');
       
    }

    public function index() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/contacts/index.html");
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_contact');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_contact");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_contact", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('master/contacts/index/');
        $config['total_rows'] = $this->m_contacts->get_total_data($keyword);
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
        $data = $this->m_contacts->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    public function add() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/contacts/add.html");
        $contact = $this->m_contacts->get_list_contact();
        $this->tsmarty->assign('contact', $contact);
        
        // save data
        if($this->input->post()){
          
            $this->form_validation->set_rules('nama_user', 'Pertanyaan', 'trim|required');
            $this->form_validation->set_rules('email_user', 'Jawaban', 'trim');
            $this->form_validation->set_rules('telepon_user', 'Kategori contact', 'trim|required');
            $this->form_validation->set_rules('pesan_user', 'Pesan', 'trim|required');
            $this->form_validation->set_rules('status_contact', 'Status', 'trim|required');
            
         
          
            if ($this->form_validation->run() !== FALSE) {
            

                $data = [
                    
                    'nama_user' => $this->input->post('nama_user'),
                    'email_user' => $this->input->post('email_user'),
                    'telepon_user' => $this->input->post('telepon_user'),
                    'pesan_user' => $this->input->post('pesan_user'),
                    'tanggal_waktu' => date('Y-m-d H:i:s'),
                    'status_contact' => $this->input->post('status_contact'),
                    
                ];
                
                if($this->m_contacts->add_contact($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/contacts/add/');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/contacts/add/');
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/contacts/add/');
            }
        }
        // output
        parent::display();
    }

    public function edit($contact_id) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/contacts/edit.html");
        
        $detail = $this->m_contacts->get_detail_contact($contact_id);
        $this->tsmarty->assign('detail', $detail);
        // save data
        if($this->input->post()){
          
            $this->form_validation->set_rules('nama_user', 'Pertanyaan', 'trim|required');
            $this->form_validation->set_rules('email_user', 'Jawaban', 'trim');
            $this->form_validation->set_rules('telepon_user', 'Kategori contact', 'trim|required');
            $this->form_validation->set_rules('pesan_user', 'Pesan', 'trim|required');
            $this->form_validation->set_rules('status_contact', 'Status', 'trim|required');
          
            if ($this->form_validation->run() !== FALSE) {
            

                $data = [
                    'nama_user' => $this->input->post('nama_user'),
                    'email_user' => $this->input->post('email_user'),
                    'telepon_user' => $this->input->post('telepon_user'),
                    'pesan_user' => $this->input->post('pesan_user'),
                    'tanggal_waktu' => date('Y-m-d H:i:s'),
                    'status_contact' => $this->input->post('status_contact'),
                ];

                if($this->m_contacts->update_contact($this->input->post('contact_id'),$data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/contacts/edit/'. $contact_id);
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/contacts/edit/'. $contact_id);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/contacts/edit/'. $contact_id);
            }
        }
        // output
        parent::display();
    }

    public function delete($contact_id) {
        // detail contact

        $detail = $this->m_contacts->get_detail_contact($contact_id);

        if ($this->m_contacts->delete_contact($contact_id)) {
            // delete image
         
            if (file_exists($file_path)) {
                unlink($file_path);
            } 
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        redirect('master/contacts');
}
}