<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );

// --
class Banner extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('M_banners', 'm_banners');
    }

    // dashboard

    public function index() {

        $billboard = $this->m_banners->get_list_data();
        $this->tsmarty->assign('banners', $banner);

        $this->tsmarty->assign("template_content", "public/billboard.html");
    
    parent::display();
}

}