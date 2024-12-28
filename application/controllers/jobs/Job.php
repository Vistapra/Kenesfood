<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once( APPPATH . 'controllers/base/PrivateBase.php' );

class Job extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('settings/M_jobs', 'm_jobs');
    }

    //index
    function index() {
        // set template content
        $this->tsmarty->assign("template_content", "settings/job/index.html");
        // search
        $keyword = '';
        $search = $this->session->userdata('search_job');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_job");
            } else {
                $keyword = $this->input->post('keyword', TRUE);
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_job", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('jobs/job/index/');
        $config['total_rows'] = $this->m_jobs->get_total_data($keyword);
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
        $data = $this->m_jobs->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    /** 
     * DETAIL, ADD, EDIT, DELETE
     */

    // detail
    public function detail($id) {
        // set template content
        $this->tsmarty->assign("template_content", "settings/job/detail.html");
        // get data
        $job = $this->m_jobs->get_detail_job($id);
        $this->tsmarty->assign('job', $job);
        // output
        parent::display();
    }

    // add
    public function add() {
        // set template content
        $this->tsmarty->assign("template_content", "settings/job/add.html");
        // process
        if ($this->input->post()) {
            $this->form_validation->set_rules('job_name', 'Pekerjaan', 'trim|required');
            $this->form_validation->set_rules('job_description', 'Deskripsi', 'trim|required');
            $this->form_validation->set_rules('job_date', 'Tanggal Valid', 'trim|required');
            $this->form_validation->set_rules('job_date_test', 'Tanggal Test', 'trim');
            if ($this->form_validation->run() == FALSE) {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('jobs/job/add');
            } else {
                // data
                $data = array(
                    'job_name' => $this->input->post('job_name'),
                    'job_description' => $this->input->post('job_description'),
                    'job_date' => $this->input->post('job_date'),
                    'job_date_test' => $this->input->post('job_date_test'),
                );
                // upload image
                if ($_FILES['job_img']['tmp_name']) {
                    $temp = explode(".", $_FILES['job_img']['name']);
                    $ext = end($temp);
                    $config['upload_path']          = './resource/assets-frontend/dist/loker/';
                    $config['allowed_types']        = 'svg|gif|jpg|png|jpeg';
                    $config['max_size']             = 2048;
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('job_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if ($this->upload->do_upload('job_img')) {
                        $job_pict_data = $this->upload->data();
                        $data['job_img'] = $job_pict_data['file_name'];
                    } 
                }
                // insert data
                if($this->m_jobs->add_job($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('jobs/job/add');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('jobs/job/add');
                }
            } 
        }
        // output
        parent::display();
    }

    // edit
    public function edit($id) {
        // set template content
        $this->tsmarty->assign("template_content", "settings/job/edit.html");
        // get data
        $job = $this->m_jobs->get_detail_job($id);
        $this->tsmarty->assign('job', $job);
        // process
        if ($this->input->post()) {
            $this->form_validation->set_rules('job_name', 'Pekerjaan', 'trim|required');
            $this->form_validation->set_rules('job_description', 'Deskripsi', 'trim|required');
            $this->form_validation->set_rules('job_date', 'Tanggal Valid', 'trim|required');
            $this->form_validation->set_rules('job_date_test', 'Tanggal Test', 'trim');
            if ($this->form_validation->run() == FALSE) {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('jobs/job/edit/' . $id);
            } else {
                // data
                $data = array(
                    'job_name' => $this->input->post('job_name'),
                    'job_description' => $this->input->post('job_description'),
                    'job_date' => $this->input->post('job_date'),
                    'job_date_test' => $this->input->post('job_date_test'),
                );
                // upload image
                if ($_FILES['job_img']['tmp_name']) {
                    $temp = explode(".", $_FILES['job_img']['name']);
                    $ext = end($temp);
                    $config['upload_path']          = './resource/assets-frontend/dist/loker/';
                    $config['allowed_types']        = 'svg|gif|jpg|png|jpeg';
                    $config['max_size']             = 2048;
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('job_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if ($this->upload->do_upload('job_img')) {
                        $job_pict_data = $this->upload->data();
                        $data['job_img'] = $job_pict_data['file_name'];
                    } 
                }
                // insert data
                if($this->m_jobs->update_job($id, $data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('jobs/job/edit/' . $id);
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('jobs/job/edit/' . $id);
                }
            } 
        }
        // output
        parent::display();
    }

    // delete
    public function delete($id) {
        // get data
        $job = $this->m_jobs->get_detail_job($id);
        $this->tsmarty->assign('job', $job);
        // delete
        if ($this->m_jobs->delete_job($id)) {
            $file_path = FCPATH . './resource/assets-frontend/dist/loker/' . $job['job_img'];
            if (file_exists($file_path)) {
                unlink($file_path);
            } 
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        redirect('jobs/job');
    }
}