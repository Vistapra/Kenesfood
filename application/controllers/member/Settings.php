<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/MemberBase.php' );

// --
class Settings extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
    }

    // dashboard
    public function index() {
        // set template content
        $this->tsmarty->assign("template_content", "member/dashboard.html");
        
    }

}
