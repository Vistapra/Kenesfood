<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PublicBase.php');

// --
class products extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('M_products', 'm_products');
        $this->load->model('M_categories', 'm_categories');
        $this->load->model('M_seasonal', 'm_seasonal');
        $this->load->model('master/M_brands', 'm_brands');
        $this->load->library('session');
        $this->load->library('pagination');
    }

    private function rcv_search_item($arr, $item) {
        foreach ($arr as $key => $value) {
            // Check if the current key matches the item
            if ($key == $item) {
                return $key; // Return the key if found
            }
    
            // If there are child items, search them recursively
            if (!empty($value['child'])) {
                $result = $this->rcv_search_item($value['child'], $item);
                if ($result) {
                    return $result; // Return the result if found
                }
            }
        }
    
        return null; // Return null if not found
    }

    private function append_to_nested_categories(&$categories, $category) {
        // Check for the parent category recursively
        $parentKey = $this->rcv_search_item($categories, $category['cat_parent']);
        
        if ($parentKey !== null) {
            // Navigate to the correct parent and append the category
            $current = &$categories[$parentKey];
    
            // Traverse through parents if needed
            while ($current && !empty($current['child'])) {
                $parentKey = $this->rcv_search_item($current['child'], $category['cat_parent']);
                if ($parentKey !== null) {
                    $current = &$current['child'][$parentKey];
                } else {
                    break;
                }
            }
    
            // Append the category to the child array
            $current['child'][$category['cat_id']] = $category;
        }
    }

    // dashboard
    public function index()

    {
        $sort = $this->input->get('sort');
        $category = $this->input->get('category');


        // $total_row = $this->m_products->countAllProducts();
        // $config['base_url'] = base_url('products'); // Ganti dengan URL yang sesuai
        // $config['total_rows'] = $total_row; // Ganti dengan jumlah total data yang ingin Anda tampilkan
        // $config['per_page'] = 1;


        // $config['full_tag_open'] = '<nav><ul class="pagination">';
        // $config['full_tag_close'] = '</ul></nav>';

        // $config['first_link'] = 'First';
        // $config['first_tag_open'] = '<li class="page-item">';
        // $config['first_tag_close'] = '</li>';

        // $config['last_link'] = 'Last';
        // $config['last_tag_open'] = '<li class="page-item">';
        // $config['last_tag_close'] = '</li>';

        // $config['next_link'] = '&raquo';
        // $config['next_tag_open'] = '<li class="page-item">';
        // $config['next_tag_close'] = '</li>';

        // $config['prev_link'] = '&laquo';
        // $config['prev_tag_open'] = '<li class="page-item">';
        // $config['prev_tag_close'] = '</li>';

        // $config['cur_tag_open'] = '<li class="page-item-active"><a class="page-link" href="#">';
        // $config['cur_tag_close'] = '</a></li>';

        // $config['num_tag_open'] = '<li class="page-item">';
        // $config['num_tag_close'] = '</li>';
        // // Produces: class="myclass"
        // $config['attributes'] = array('class' => 'page-link');


        // $this->pagination->initialize($config);
        // $offset = $this->uri->segment(2, 0);
        // $order = '';

        $products = [];
        if ($sort === 'hargaMin') {
            $products = $this->m_products->getProductsByPrice('ASC');
            $productCategories = $this->m_products->getCategoriesWithProducts('ASC');
            //$pagination = $this->pagination->create_links();
        } elseif ($sort === 'hargaMax') {
            $products = $this->m_products->getProductsByPrice('DESC');
            $productCategories = $this->m_products->getCategoriesWithProducts('DESC');
            //$pagination = $this->pagination->create_links();
        } else {
            $products = $this->m_products->get_list_data_products();
            $productCategories = $this->m_products->getCategoriesWithProducts($sort);
            //$pagination = $this->pagination->create_links();
        }

        $categories = $this->m_categories->get_list_data_categories();

        // Inside the index method
        $transformedCategories = [];

        while ($categories) {
            foreach ($categories as $catKey => $category) {
                if ($category['cat_parent'] == 0) {
                    // Parent category
                    $category['child'] = [];
                    $transformedCategories[$category['cat_id']] = $category;
                    unset($categories[$catKey]);
                } else {
                    // Child category
                    $this->append_to_nested_categories($transformedCategories, $category);
                    unset($categories[$catKey]);
                }
            }
        }

        // print_r($transformedCategories); exit;

        // $this->tree->init('')
        $this->tsmarty->assign("template_content", "public/products.html");
        $this->tsmarty->assign("products", $products);
        //$this->tsmarty->assign("pagination", $pagination);
        $this->tsmarty->assign("productCategories", $productCategories);
        $this->tsmarty->assign("categories", $transformedCategories);

        if (isset($this->session->userdata['member'])) {
            $id_member =  $this->session->userdata['member'];
            $id = $id_member['user_id'];
            $this->tsmarty->assign("id_member", $id);
        } else {
            $id = '';
            $this->tsmarty->assign("id_member", $id);
        }


        // $products = $this->m_products->get_list_data_products($config['per_page'], $offset);
        // $productCategories = $this->m_products->getCategoriesWithProducts($sort, $order, $config['per_page'], $offset);
        // $pagination = $this->pagination->create_links();
        // output
        parent::display();
    }

public function detail_kategori($id = ''){
    $this->tsmarty->assign("template_content", "public/detail_kategori.html");
    $products = $this->m_categories->get_sub_categories($id);
    $this->tsmarty->assign('categories', $products);

    $products = $this->m_categories->get_detail_categories($id);
    $this->tsmarty->assign('detail', $products);
    // list market place
    $market = $this->m_brands->get_list_contact_by_brand([$products['cat_brand'], 'marketplace']);
    $this->tsmarty->assign("market", $market);        
    // list social media
    $sosmed = $this->m_brands->get_list_contact_by_brand([$products['cat_brand'], 'social-media']);
    $this->tsmarty->assign("sosmed", $sosmed); 
    // output
    parent::display();
}


public function detail_sub_kategori($id = ''){
    $this->tsmarty->assign("template_content", "public/detail_sub_kategori.html");
    $products = $this->m_products->getProductByCategory($id);
    $this->tsmarty->assign('products', $products);
    // print_r($products);exit();
    $products = $this->m_categories->get_detail_categories($id);
    $this->tsmarty->assign('detail', $products);
    // list market place
    $market = $this->m_brands->get_list_contact_by_brand([$products['cat_brand'], 'marketplace']);
    $this->tsmarty->assign("market", $market);        
    // list social media
    $sosmed = $this->m_brands->get_list_contact_by_brand([$products['cat_brand'], 'social-media']);
    $this->tsmarty->assign("sosmed", $sosmed); 
    // output
    parent::display();
}

public function detail_kategori_product($id = ''){
    $this->tsmarty->assign("template_content", "public/detail_kategori_product.html");
    $products = $this->m_products->getProduct($id);
    $this->tsmarty->assign('products', $products);

    $products = $this->m_products->getProductVarian($id);
    $this->tsmarty->assign('varians', $products);
    // print_r($products);exit();
    $products = $this->m_products->get_detail_product($id);
    $this->tsmarty->assign('detail_product', $products);
    // output
    parent::display();
}

// public function showProductImages($id = '') {
//     $this->tsmarty->assign("template_content", "public/detail_kategori_product.html");
//     $products = $this->m_products->getProductBy1Category($id);
//     $this->tsmarty->assign('categories', $products);
//     // print_r($id);exit();
//     $products = $this->m_categories->get_detail_categories($id);
//     $this->tsmarty->assign('detail_subkategori', $products);
//     // output
//     parent::display();
// }



    public function detail_produk()
    {
        $id = $this->input->get('id');
        $this->tsmarty->assign("template_content", "public/detail_produk.html");



        $products['products'] = $this->m_products->get_detail_data_products($id);
        $button_cart = $this->m_products->show_button_cart($id);

        if ($button_cart) {
            $this->tsmarty->assign("button_cart", $button_cart);
        }else{
            $this->tsmarty->assign("button_cart", $button_cart);
        }
        // Mengambil data varian
        $varian['varian'] = $this->m_products->get_all_varian_from_parent($id);


        $this->tsmarty->assign("products", $products['products']);
        $this->tsmarty->assign("varian", $varian['varian']);

        if (isset($this->session->userdata['member'])) {
            $id_member =  $this->session->userdata['member'];
            $id = $id_member['user_id'];
            $this->tsmarty->assign("id_member", $id);
        } else {
            $id = '';
            $this->tsmarty->assign("id_member", $id);
        }
        // Output the view
        parent::display();
    }


    
        public function seasonal(){
        $this->tsmarty->assign("template_content", "public/seasonalcollection.html");
        $products = $this->m_seasonal->get_list_product_seasonal();
        $this->tsmarty->assign('seasonals', $products);
        $products = $this->m_categories->get_detail_categories_seasonal();
        $this->tsmarty->assign('cat_season', $products);

        // $products = $this->m_products->getProductVarian($id);
        // $this->tsmarty->assign('varians', $products);
        // $products = $this->m_products->getProductSeason();
        // $this->tsmarty->assign('products', $products);
        // print_r($products);exit();

        // $products = $this->m_seasonal->get_detail_seasonal($id);
        // $this->tsmarty->assign('detail', $products);
        // list market place
        // $market = $this->m_brands->get_list_contact_by_brand([$products['cat_brand'], 'marketplace']);
        // $this->tsmarty->assign("market", $market);        
        // list social media
        // $sosmed = $this->m_brands->get_list_contact_by_brand([$products['cat_brand'], 'social-media']);
        // $this->tsmarty->assign("sosmed", $sosmed); 
        // output
        parent::display();
    }

    public function detail_kategori_product_seasonal($id){
        $this->tsmarty->assign("template_content", "public/detail_kategori_product_seasonal.html");
        $products = $this->m_products->getProductDetailSeason($id); 
        $this->tsmarty->assign('products', $products);
        $products = $this->m_categories->get_detail_categories($id);
        $this->tsmarty->assign('detail_product', $products);
        // $products = $this->m_products->getProductVarian($id);
        // $this->tsmarty->assign('varians', $products);
        // print_r($products);exit();
        parent::display();
    }


    

    public function get_detail_product(){
        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }
        $id = $this->input->get('product_id', TRUE);
        $products = $this->m_products->get_detail_product($id);
        echo json_encode($products);
    }


    public function get_data_from_db($page)
    {
    }
}
