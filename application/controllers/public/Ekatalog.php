<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );

// --
class Ekatalog extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('M_products', 'm_products');
        $this->load->model('M_categories', 'm_categories');
        $this->load->model('master/M_brands', 'm_brands');
        $this->load->model('M_banners', 'm_banners');
        $this->load->model('M_ekatalogs', 'm_ekatalogs');
        $this->load->model('master/M_marketings', 'm_marketings');
    }

    // dashboard
    public function index() {
        $category      = $this->input->get('category');
        $brandType     = $this->input->get('brand') ?? "bakery";
        $marketingId   = $this->input->get('marketingId');
        $catalogueType = $this->input->get('type');

        {$this->tsmarty->assign("template_content", "public/ekatalog.html");}

        // $products = $this->m_products->get_list_product_banner($type);
        // $this->tsmarty->assign("products", $products);

        // $banner = $this->m_brands->get_detail_by_brand($type);
        // $this->tsmarty->assign("banner", $banner);

        $banners = $this->m_banners->get_catalogues();
 
        // $ekatalogs = $this->m_ekatalogs->get_list_ekatalog();
        // $this->tsmarty->assign("ekatalogs", $ekatalogs);
       
        $categories = $this->m_categories->get_catalogue_categories($brandType);

        switch ($catalogueType)
		{
            case "marketing":
                if($marketingId) {
                    $marketing_detail = $this->m_marketings->get_detail_marketing($marketingId);
                    $this->tsmarty->assign('marketing_detail', $marketing_detail);
                }
				if ($category)
				{
					$product_mb = $this->m_products->get_catalogues('catalogue_marketing_filter_category', [$category, $brandType]);
				}
				else
				{
					$product_mb = $this->m_products->get_catalogues('get_list_product_marketing', [$brandType]);
				}
                break;
            case "outlet":
				if ($category)
				{
					$product_mb = $this->m_products->get_catalogues('catalogue_outlet_filter_category', [$category, $brandType]);
				}
				else
				{
					$product_mb = $this->m_products->get_catalogues('get_list_product_outlet', [$brandType]);
				}
                break;
            case "customer":
				if ($category)
				{
					$product_mb = $this->m_products->get_catalogues('catalogue_customer_filter_category', [$category, $brandType]);
				}
				else
				{
					$product_mb = $this->m_products->get_catalogues('get_list_product_customer', [$brandType]);
				}
                break;
        }

        // reformatting style for catalogue
        foreach($product_mb as $key => $value) {
            $temp = floor($value['price_catalogue']);
            // check if this is not whole number
            if ($temp != $value['price_catalogue']) {
                $product_mb[$key]['price_catalogue'] = str_replace('.',',',strval($value['price_catalogue']));
            } else {
                $product_mb[$key]['price_catalogue'] = $temp;
            }
        }

        $this->tsmarty->assign("banners", $banners);
        $this->tsmarty->assign('product_mb', $product_mb);
        $this->tsmarty->assign("catalogueCategories", $categories);

        // $market = $this->m_brands->get_list_contact_by_brand([$type, 'marketplace']);
        // $this->tsmarty->assign("market", $market);
  
        // output
        parent::display();
     }

    public function products() {
        $this->tsmarty->assign("template_content", "public/products.html"); 

        parent::display();
    }

}