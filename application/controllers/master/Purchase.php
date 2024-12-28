<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once( APPPATH . 'controllers/base/PrivateBase.php' );

class Purchase extends ApplicationBase {


    function __construct() {
        parent::__construct();
        $this->load->model('M_History', 'm_history');
        $this->load->library('form_validation');
        $this->load->library('session');
    }

    public function index() {
        $this->tsmarty->assign("template_content", "master/purchase/index.html");
        // get data
        $data = $this->m_history->get_list_data_history();
        $this->tsmarty->assign("purchase", $data);
        // print_r($data);exit();
        parent::display();
    }

    public function detail($id)
    {

            $this->tsmarty->assign("template_content", "master/purchase/detail.html");
            $data['purchase'] = $this->m_history->get_detail_data_history($id);
            $this->tsmarty->assign("purchase", $data['purchase']);
 
        parent::display();
        
    }
    public function delete($id) {
        if ($this->m_history->delete_data_history($id)) {
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        
        redirect('master/purchase');
    }
    
    
}