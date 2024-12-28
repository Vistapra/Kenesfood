<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );

// --
class Contact extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('M_contacts', 'm_contacts');
    }

    // dashboard

    public function index() {

        $contact = $this->m_contacts->get_list_contact();
        $this->tsmarty->assign('contacts', $contact);

        $this->tsmarty->assign("template_content", "public/contact.html");
    
    parent::display();
}

}