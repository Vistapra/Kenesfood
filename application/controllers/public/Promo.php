<?php

if (!defined('BASEPATH'))
exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );
 
class Promo extends ApplicationBase {
	
	function __construct(){
		parent::__construct();
		$this->load->model('M_promotions', 'm_promotions');
		$this->load->model('master/M_brands', 'm_brands');
		
	}
 
	public function index($type = 'bakery'){
		$this->tsmarty->assign("template_content", "public/promo.html");
		$data['promotions'] = $this->m_promotions->get_list_data_promotion($type);
        $this->tsmarty->assign("promotions", $data['promotions']);
		
        $brands = $this->m_brands->get_detail_by_brand($type);
        $this->tsmarty->assign("brands", $brands);
        parent::display();
	}

}