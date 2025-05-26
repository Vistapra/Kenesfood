<?php
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/MemberBase.php');

class Profile extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('settings/M_users', 'm_users'); 
        $this->load->model('master/M_members', 'm_members'); 
        $this->load->model('apps/M_account', 'm_account');
    }

    public function index() {
        // set template content
        $this->tsmarty->assign("template_content", "member/account.html");
        // output
        parent::display();
    }

    public function profile_member() {
        // set template content
        $this->tsmarty->assign("template_content", "member/account.html");
        $id = $this->session->member['user_id'];
        $data = $this->m_members->get_detail_data($id);
        $old_user_photo = $data['user_photo'];
        // print_r($old_user_photo);exit();

         $this->tsmarty->assign("member", $data);

 
         if($this->input->post()) {
            //generate password
            $this->load->library('encryption');
            $user_key = $this->m_account->rand_key(8);
            $this->encryption->initialize(
                array(
                        'cipher' => 'aes-256',
                        'mode' => 'ctr',
                        'key' => $user_key
                )
            );
            $user_pass = $this->encryption->encrypt(md5
            ($this->input->post('password')));
             // end generate password
            
                $datamember = [
                    'fullname' => $this->input->post('fullname'),
                    'date_of_birth' => $this->input->post('tanggal'),
                    'email' => $this->input->post('email'),
                    'phone' => $this->input->post('phone'),
                    'address' => $this->input->post('address'),
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $id,
                     // 'user_photo' => $this->input->post('user_photo'),
                ];

                    $new_password = $this->input->post('password');
                        if (!empty($new_password)) {
                            $datamember['password'] = $user_pass;
                            $data_app['user_pass'] = $user_pass;
                            $data_app['user_key'] = $user_key;                           
                            }
                
                

                if ($_FILES['user_photo']['tmp_name']) {
                    $config['upload_path']          = './resource/assets-frontend/dist/account/';
                    $config['allowed_types']        = 'gif|jpg|png|jpeg';
                    $config['max_size']             = 5120;
                    $config['encrypt_name']         = TRUE;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    // $this->upload->initialize($config);
                    if ($this->upload->do_upload('user_photo')) {

                        $user_photo_data = $this->upload->data();
                        $user_photo = $user_photo_data['file_name'];

                        $datamember['user_photo'] = $user_photo;
                         //$old_user_photo = $user_photo;
                     
                        
                        
                        if (!empty($old_user_photo)) {
                            $old_file_path = './resource/assets-frontend/dist/account/' .$old_user_photo;
                            if (file_exists($old_file_path)) {
                                unlink($old_file_path);                               
                            }
                        }
                        
                        
                    } else {
                        $error = array('error' => $this->upload->display_errors());
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        
                        // redirect('member/account');
                    }
                      
                }

                $this->m_users->edit_data_member($id, $datamember, $data_app);
                
                redirect('/account');
                $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan.', 'status' => 'success'));
        }
        // output
        parent::display();
    }
}