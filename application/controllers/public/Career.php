<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );

// --
class Career extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('M_jobs', 'm_jobs');
        $this->load->library('form_validation');
        $this->load->library('session');
    }

    // dashboard

    public function index() {
        $this->tsmarty->assign("template_content", "public/career.html");
        $this->load->model('M_jobs', 'm_jobs');
    
        $jobData = $this->m_jobs->get_list_data_jobs();
        $this->tsmarty->assign("job", $jobData);

        $jobDataActive = $this->m_jobs->get_list_data_jobs_active();
        $this->tsmarty->assign("job_active", $jobDataActive);
    
     
        parent::display();
    }

    public function pelamar(){
        if ($this->input->post()) {
            $this->load->library('form_validation');
            $this->form_validation->set_rules('job_id', 'Job', 'required');
            // $this->form_validation->set_rules('ktp', 'KTP', 'required');
            $this->form_validation->set_rules('nama_lengkap', 'Nama Lengkap', 'required');
            $this->form_validation->set_rules('tempat_lahir', 'Tempat Lahir', 'required');
            $this->form_validation->set_rules('tanggal_lahir', 'Tanggal Lahir', 'required');
            $this->form_validation->set_rules('jenis_kelamin', 'Jenis Kelamin', 'required');
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
            $this->form_validation->set_rules('phone', 'Phone', 'required');
    
            if ($this->form_validation->run()) {
                $phone_wa = $this->input->post('phone');
                if (substr($phone_wa, 0, 1) != '+') {
                    // Jika belum memiliki kode negara, tambahkan +62
                    $phone_wa = '+62' . substr($phone_wa, 1);
                }
                $data = array(
                    'nama_lengkap' => $this->input->post('nama_lengkap'),
                    'job_id' => $this->input->post('job_id'),
                    // 'ktp' => $this->input->post('ktp'),
                    'tempat_lahir' => $this->input->post('tempat_lahir'),
                    'tanggal_lahir' => $this->input->post('tanggal_lahir'),
                    'jenis_kelamin' => $this->input->post('jenis_kelamin'),
                    'email' => $this->input->post('email'),
                    'phone' => $phone_wa,
                    'created' => date('Y-m-d H:i:s'),
                );
    
                // Upload profile photo
                if ($_FILES['foto_pelamar']['tmp_name']) {
                    $config_foto['upload_path'] = './resource/assets-frontend/dist/pelamar/foto';
                    $config_foto['allowed_types'] = 'jpg|png|jpeg';
                    $config_foto['max_size'] = 2048;
                    $config_foto['encrypt_name'] = TRUE;
                    $config_foto['overwrite'] = TRUE;

                    $this->load->library('upload', $config_foto, 'foto_upload'); 
                    if ($this->foto_upload->do_upload('foto_pelamar')) {
                        $foto_pelamar_data = $this->foto_upload->data();
                        $foto_pelamar = $foto_pelamar_data['file_name'];
                        $data['foto_pelamar'] = $foto_pelamar;
                    } else {
                        // Display upload errors for debugging
                        echo $this->foto_upload->display_errors();
                    }
                }

                // Upload CV
                if ($_FILES['upload_cv']['tmp_name']) {
                    $config_cv['upload_path'] = './resource/assets-frontend/dist/pelamar/dokumen';
                    $config_cv['allowed_types'] = 'pdf';
                    $config_cv['max_size'] = 2048;
                    $config_cv['encrypt_name'] = FALSE;
                    $config_cv['overwrite'] = TRUE;

                    $this->load->library('upload', $config_cv, 'cv_upload');
                    if ($this->cv_upload->do_upload('upload_cv')) {
                        $upload_cv_data = $this->cv_upload->data();
                        $upload_cv = $upload_cv_data['file_name'];
                        $data['upload_cv'] = $upload_cv;
                    } else {
                        // Display upload errors for debugging
                        echo $this->cv_upload->display_errors();
                    }
                }

                
                $this->m_jobs->lamar_job($data);
                $this->session->set_flashdata('message', array('msg' => 'Berhasil Melamar Pekerjaan', 'status' => 'success'));
                
            }
        } 
        redirect('/career');
    }

}