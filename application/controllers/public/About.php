<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );

// --
class About extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        $this->load->model('M_outlets', 'm_outlets');
        // load model
    }

    // dashboard
    public function index() {
        $outlets = $this->m_outlets->get_list_data_outlets_when_active();
        $this->tsmarty->assign("outlets", $outlets);
        // load library
        // $this->load->library('encryption');
        // // encrypt
        // $password = md5('w3nn13');
        // $mail = 'wennie.mail@gmail.com';
        // $name = 'Wenny Wardyaningsih';
        // $encrypted_string = $this->encryption->encrypt($name);
        // // decrypt
        // $decrypted_string = $this->encryption->decrypt($encrypted_string);
        // // assign
        // $this->tsmarty->assign('encrypt', $encrypted_string);
        // $this->tsmarty->assign('decrypt', $decrypted_string);
        // set template content
        $this->tsmarty->assign("template_content", "public/about.html");
        // output
        
        parent::display();
    }

}

