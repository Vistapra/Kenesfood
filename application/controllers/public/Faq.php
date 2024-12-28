<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );


class Faq extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('M_faqs', 'm_faqs');
    }

    // dashboard

    public function index() {

        $faq = $this->m_faqs->get_list_faq();
        $this->tsmarty->assign('faqs', $faq);

        $this->tsmarty->assign("template_content", "public/faq.html");
    
    parent::display();
}

}