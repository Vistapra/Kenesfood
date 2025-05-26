<?php

if (!defined('BASEPATH'))
exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );
 
class Outlet extends ApplicationBase {
	
	function __construct(){
		
		parent::__construct();
		
	}
 
	public function index(){
		$this->load->model('M_outlets', 'm_outlets');

		$outlets = [];
		//$data = $this->m_outlets->get_list_data_outlets();
		$outlets = $this->m_outlets->get_list_data_outlets_when_active();
		// $kota = $this->m_outlets->get_list_data();

		//$kota = array($kota);
			
		$this->tsmarty->assign("template_content", "public/store.html");
		$this->tsmarty->assign("outlets", $outlets);
		// $this->tsmarty->assign("kota", $kota);
		
		parent::display();
	}
	

}