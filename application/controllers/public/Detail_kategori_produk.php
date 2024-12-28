<?php

if (!defined('BASEPATH'))
exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );
 
class Kategori extends ApplicationBase {
	
	function __construct(){
		parent::__construct();
        $this->load->model('M_products', 'm_products');
		$this->load->model('models/M_categories', 'm_categories');
		
	}
 
	public function index(){
		$this->tsmarty->assign("template_content", "public/kategori.html");
        parent::display();
	}
	public function detail(){
		$this->tsmarty->assign("template_content", "public/detail_kategori.html");
		parent::display();
	}

	public function contact() {       
        $this->tsmarty->assign("template_content", "public/detail_kategori.html");
        // list market place
        $market = $this->m_brands->get_list_contact_by_brand([$type, 'marketplace']);
        $this->tsmarty->assign("market", $market);        
        // list social media
        $sosmed = $this->m_brands->get_list_contact_by_brand([$type, 'social-media']);
        $this->tsmarty->assign("sosmed", $sosmed);
        // output
        parent::display();
     }

}