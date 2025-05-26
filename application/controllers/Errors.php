<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

// --
class Errors extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
    }

    // error 404
    public function index() {
        // set template content
        $this->tsmarty->assign("title", "Not Found");
        $this->tsmarty->assign("template_content", "base/templates/error_template.html");
        // output
        parent::display('base/document.html');
    }
}