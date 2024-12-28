<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );

// --
class Home extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('master/M_products', 'm_products');
        $this->load->model('master/M_categories', 'm_categories');
        $this->load->model('master/M_brands', 'm_brands');
        $this->load->model('M_seasonal', 'm_seasonal');
        $this->load->model('M_banners', 'm_banners');
        $this->load->model('M_ekatalogs', 'm_ekatalogs');
    }

    // dashboard
    public function index($type = 'bakery') {     
        if($type == 'resto') 
        {
           $this->tsmarty->assign("template_content", "public/resto.html");
        }  else {$this->tsmarty->assign("template_content", "public/home.html");}
        
        // list highlight banner promo
        $products = $this->m_products->get_list_product_banner($type);
        $this->tsmarty->assign("products", $products);
        // list brand banner
        $banner = $this->m_brands->get_detail_by_brand($type);
        $this->tsmarty->assign("banner", $banner);

        $banners = $this->m_banners->get_list_banner();
        $this->tsmarty->assign("banners", $banners);

 
        $ekatalogs = $this->m_ekatalogs->get_list_ekatalog();
        $this->tsmarty->assign("ekatalogs", $ekatalogs);

        // list categories highlight
        $categories = $this->m_categories->get_list_cat_highlight($type);
        $this->tsmarty->assign("categories", $categories);
        // list market place
        $market = $this->m_brands->get_list_contact_by_brand([$type, 'marketplace']);
        $this->tsmarty->assign("market", $market);        
        // list social media
        $sosmed = $this->m_brands->get_list_contact_by_brand([$type, 'social-media']);
        $this->tsmarty->assign("sosmed", $sosmed);
        $products = $this->m_seasonal->get_list_product_seasonal();
        $this->tsmarty->assign('seasonals', $products);
        // output
        parent::display();
        
     }
    
   

    public function products() {
        $this->tsmarty->assign("template_content", "public/products.html"); 

        parent::display();
    }

}