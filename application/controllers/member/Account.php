<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/MemberBase.php');

// --
class Account extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        $this->load->model('settings/M_users', 'M_users');
        // $this->load->model('M_purchase', 'M_purchase');
        $this->load->library('session');
    }

    // dashboard
    public function index()
    {
        $purchase_member = $this->session->member['user_id'];
        $this->tsmarty->assign("template_content", "member/account.html");
        $id_member = $this->session->userdata['member'];
        $id = $id_member['user_id'];
        $data['members'] = $this->M_users->get_detail_data_member($id);
        $data['history'] = $this->M_users->get_list_history($purchase_member);
        // var_dump($data['members']);
        // exit();
        $this->tsmarty->assign("member", $data['members']);
        $this->tsmarty->assign("history", $data['history']);
        $this->tsmarty->assign("purchase_member", $purchase_member);


        parent::display();
		
		
    }

    public function detail_history($purchase_id)
    {

        $this->tsmarty->assign("template_content", "member/detail_history.html");
       

        $data['history'] = $this->M_users->get_detail_purchase_history($purchase_id);
        // var_dump($data['history']);
        // exit();
        $this->tsmarty->assign("history", $data['history']);
        // redirect('member/detail');
        // Output the view
        parent::display();
    }

    public function delete_history($purchase_id)
    {

        if ($this->M_users->delete_purchase_history($purchase_id)) {
            // $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
            redirect('member/account');
            // Product deleted successfully
        } else {
            redirect('member/account');
        }

        // $data['message'] = $message;

    }

    // public function history()
    // {
    //     // Set template content
    //     $this->tsmarty->assign("template_content", "member/account.html");

    //     $data['history'] = $this->M_users->get_list_history();
    //     // var_dump($data['history']);
    //     // exit();
    //     // Assign data to Smarty
    //     $this->tsmarty->assign("history", $data['history']);

    //     // output
    //     parent::display();
    // }
}
