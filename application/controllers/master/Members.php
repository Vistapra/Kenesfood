<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

// --
class Members extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('master/M_members', 'm_members');
        $this->load->model('settings/M_users', 'm_users');
        $this->load->model('settings/M_role', 'm_role');
        $this->load->model('apps/M_account', 'm_account');
    }

    // index
    public function index() {
        // set template content
        $this->tsmarty->assign("template_content", "master/members/index.html");
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_member');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_member");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_member", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('master/member/detail/');
        $config['total_rows'] = $this->m_members->get_total_data($keyword);
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
        $params = array(($start - 1), $config['per_page']);
        $data = $this->m_members->get_list_data($params, $keyword); 
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
        $this->tsmarty->assign("template_content", "master/members/detail.html");
        // detail member
        $detail = $this->m_members->get_detail_data($id);
        $this->tsmarty->assign('detail', $detail);
        // output
        parent::display();
    }

    // add
    public function add() {
        // set template content
        $this->tsmarty->assign("template_content", "master/members/add.html");
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('user_name', 'Username', 'trim|required');
            $this->form_validation->set_rules('user_pass', 'Password', 'trim|required');
            $this->form_validation->set_rules('user_pass_verif', 'Verifikasi Password', 'trim|required');
            $this->form_validation->set_rules('user_email', 'Email', 'trim|required');
            $this->form_validation->set_rules('user_alias', 'Nama Lengkap', 'trim|required');
            $this->form_validation->set_rules('gender', 'Jenis Kelamin', 'trim');
            $this->form_validation->set_rules('birthday', 'Tanggal Lahir', 'trim');
            $this->form_validation->set_rules('address', 'Alamat', 'trim');
            $this->form_validation->set_rules('maps', 'Google Maps Link', 'trim');
            $this->form_validation->set_rules('phone', 'Nomor Telepon', 'trim|required');
            if ($this->form_validation->run() !== FALSE) {
                // cek username
                if($this->m_account->is_exist_username([strtolower(str_replace(' ', '-', $this->input->post('user_name')))])){
                    $this->session->set_flashdata('message', array('msg' => 'Username sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
                    redirect(site_url('master/members/add'));
                }
                // cek email
                if($this->m_account->is_exist_email([$this->input->post('user_email')])){
                    $this->session->set_flashdata('message', array('msg' => 'Email sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
                    redirect(site_url('master/members/add'));
                }
                $user_pass = $user_key = NULL;
                if($this->input->post('user_pass') != $this->input->post('user_pass_verif')) {
                    $this->session->set_flashdata('message', array('msg' => 'Verifikasi password berbeda dengan password baru.', 'status' => 'error'));
                    redirect(site_url('master/members/add'));
                } else {
                    $user_key = $this->m_account->rand_key(8);
                    $this->encryption->initialize(
                        array(
                                'cipher' => 'aes-256',
                                'mode' => 'ctr',
                                'key' => $user_key
                        )
                    );
                    $user_pass = $this->encryption->encrypt(md5($this->input->post('user_pass')));
                }
                //data
                $user_code = $this->m_members->get_last_code();
                $data = [
                    'user_code' => $user_code,
                    'user_alias' => $this->input->post('user_alias'),
                    'user_name' => strtolower(str_replace(' ', '-', $this->input->post('user_name'))),
                    'user_email' => $this->input->post('user_email'),
                    'user_key' => $user_key,
                    'user_pass' => $user_pass,
                    'created_by' => $this->user_data['user_id'],
                    'created' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s')
                ];
                if($_FILES['user_photo']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['user_photo']['name']);
                    $ext = end($temp);
                    $dir = "./resource/assets/default/images/uploads/users/";
                    if (!file_exists($dir)) {
                        mkdir("./resource/assets/default/images/uploads/users/", 0755);
                    }
                    // upload image
                    $config['upload_path']          = './resource/assets/default/images/uploads/users/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('user_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('user_photo')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/categories/add/');
                    }
                    $data_upload = $this->upload->data();
                    $data['user_photo'] = $data_upload['file_name'];

                    // thumbnails
                    // $dir_thumb = "./resource/assets/default/images/uploads/users/thumbnail/";
                    // if (!file_exists($dir_thumb)) {
                    //     mkdir("./resource/assets/default/images/uploads/users/thumbnail/", 0755);
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
                // add user
                if($this->m_users->add_users($data)) {
                    // member
                    $user_id = $this->m_users->get_last_inserted_id();
                    $data = [
                        'user_id' => $user_id,
                        'fullname' => $this->input->post('user_alias'),
                        'gender' => $this->input->post('gender'),
                        'birthday' => $this->input->post('birthday'),
                        'phone' => $this->input->post('phone'),
                        'address' => $this->input->post('address'),
                        'maps' => $this->input->post('maps'),
                        'created_by' => $this->user_data['user_id'],
                        'created' => date('Y-m-d H:i:s'),
                        'modified_by' => $this->user_data['user_id'],
                        'modified' => date('Y-m-d H:i:s')
                    ];
                    // add member
                    if($this->m_members->add_members($data)) {
                        // add member role
                        $data = [
                            'user_id' => $user_id,
                            'role_id' => $this->config->item('member_role'),
                            'role_default' => $this->config->item('member_role'),
                        ];
                        if($this->m_role->add_role_user($data)) {
                            $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                            redirect('master/members/add/');
                        } else {
                            $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                            redirect('master/members/add/');
                        }
                    } else {
                        $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                        redirect('master/members/add/');
                    }
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/members/add/');
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/members/add/');
            }
        }
        // output
        parent::display();
    }

    // edit
    public function edit($user_id) {
        // set template content
        $this->tsmarty->assign("template_content", "master/members/edit.html");
        // detail member
        $detail = $this->m_members->get_detail_data($user_id);
        $this->tsmarty->assign('detail', $detail);
        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('user_name', 'Username', 'trim|required');
            $this->form_validation->set_rules('user_pass', 'Password', 'trim');
            $this->form_validation->set_rules('user_st', 'Status', 'trim|required');
            $this->form_validation->set_rules('user_pass_verif', 'Verifikasi Password', 'trim');
            $this->form_validation->set_rules('user_email', 'Email', 'trim|required');
            $this->form_validation->set_rules('user_alias', 'Nama Lengkap', 'trim|required');
            $this->form_validation->set_rules('gender', 'Jenis Kelamin', 'trim');
            $this->form_validation->set_rules('birthday', 'Tanggal Lahir', 'trim');
            $this->form_validation->set_rules('address', 'Alamat', 'trim');
            $this->form_validation->set_rules('maps', 'Google Maps Link', 'trim');
            $this->form_validation->set_rules('phone', 'Nomor Telepon', 'trim|required');
            if ($this->form_validation->run() !== FALSE) {
                // cek username
                if($this->m_account->is_exist_username_by_user_id([strtolower(str_replace(' ', '-', $this->input->post('user_name'))), $user_id])){
                    $this->session->set_flashdata('message', array('msg' => 'Username sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
                    redirect('master/members/edit/' . $user_id);
                }
                // cek email
                if($this->m_account->is_exist_email_by_user_id([$this->input->post('user_email'), $user_id])){
                    $this->session->set_flashdata('message', array('msg' => 'Email sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
                    redirect('master/members/edit/' . $user_id);
                }

                $data = [
                    'user_alias' => $this->input->post('user_alias'),
                    'user_name' => strtolower(str_replace(' ', '-', $this->input->post('user_name'))),
                    'user_email' => $this->input->post('user_email'),
                    'user_st' => $this->input->post('user_st'),
                    'modified_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s')
                ];

                $user_pass = $user_key = NULL;
                if(!empty($this->input->post('user_pass')) || !empty($this->input->post('user_pass_verif'))) {
                    if($this->input->post('user_pass') != $this->input->post('user_pass_verif')) {
                        $this->session->set_flashdata('message', array('msg' => 'Verifikasi password berbeda dengan password baru.', 'status' => 'error'));
                        redirect('master/members/edit/' . $user_id);
                    } else {
                        $user_key = $this->m_account->rand_key(8);
                        $this->encryption->initialize(
                            array(
                                    'cipher' => 'aes-256',
                                    'mode' => 'ctr',
                                    'key' => $user_key
                            )
                        );
                        $user_pass = $this->encryption->encrypt(md5($this->input->post('user_pass')));
                        $data['user_key'] = $user_key;
                        $data['user_pass'] = $user_pass;
                    }
                }
                //data
                if($_FILES['user_photo']['tmp_name'] !== ''){
                    $temp = explode(".", $_FILES['user_photo']['name']);
                    $ext = end($temp);
                    $dir = "./resource/assets/default/images/uploads/users/";
                    if (!file_exists($dir)) {
                        mkdir("./resource/assets/default/images/uploads/users/", 0755);
                    }
                    // upload image
                    $config['upload_path']          = './resource/assets/default/images/uploads/users/';
                    $config['allowed_types']        = 'svg|gif|jpg|png';
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('user_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('user_photo')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('master/members/edit/' . $user_id);
                    }
                    $data_upload = $this->upload->data();
                    $data['user_photo'] = $data_upload['file_name'];

                    // thumbnails
                    // $dir_thumb = "./resource/assets/default/images/uploads/users/thumbnail/";
                    // if (!file_exists($dir_thumb)) {
                    //     mkdir("./resource/assets/default/images/uploads/users/thumbnail/", 0755);
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
                // edit user
                if($this->m_users->update_users($user_id, $data)) {
                    // member
                    $data = [
                        'fullname' => $this->input->post('user_alias'),
                        'gender' => $this->input->post('gender'),
                        'birthday' => $this->input->post('birthday'),
                        'phone' => $this->input->post('phone'),
                        'address' => $this->input->post('address'),
                        'maps' => $this->input->post('maps'),
                        'modified_by' => $this->user_data['user_id'],
                        'modified' => date('Y-m-d H:i:s')
                    ];
                    // add member
                    if($this->m_members->update_members($user_id, $data)) {
                        $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                        redirect('master/members/');
                    } else {
                        $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                        redirect('master/members/edit/' . $user_id);
                    }
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/members/edit/' . $user_id);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/members/edit/' . $user_id);
            }
        }
        // output
        parent::display();
    }

    // delete
    public function delete($id) {
        // detail member
        $detail = $this->m_members->get_detail_data($id);
        $this->tsmarty->assign('detail', $detail);
        // delete
        if ($this->m_members->delete_members($id)) {
            if ($this->m_users->delete_users($id)) {
                $file_path = FCPATH . 'resource/assets/default/images/uploads/users/' . $detail['user_photo'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                } 
                if($this->m_role->delete_role_user($id)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus', 'status' => 'success'));
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
            }
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        redirect('master/members');
    }
}
