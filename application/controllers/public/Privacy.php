<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );

// --
class Privacy extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
    }

    // dashboard
    public function index() {
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
        $this->tsmarty->assign("template_content", "public/privacy_policy.html");
        // output
        parent::display();
    }

}

