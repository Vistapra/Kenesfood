<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PublicBase.php');

// --
class Cart extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->library('tsmarty');
        $this->load->model('M_cart', 'm_cart');
        $this->load->model('M_promotions', 'm_promotions');
        $this->load->model('M_products', 'm_products');
        $this->load->library('form_validation');
        $this->load->library('session'); // Load the session library

        // Load library pagination
        $this->load->library('pagination');
    }




    // dashboard
    public function index()
    {


        $this->tsmarty->assign("template_content", "public/cart.html");

        if (isset($this->session->member['user_id'])) {

            $purchase_member = $this->session->member['user_id'];
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Harap login untuk mengakses halaman tersebut.', 'status' => 'error'));
            redirect('login');
        }
        //$cart = $this->m_cart->get_list_cart_products($purchase_member);
        $total_row = $this->m_cart->countAllProducts($purchase_member);
        $config['base_url'] = base_url('cart'); // Ganti dengan URL yang sesuai
        $config['total_rows'] = $total_row; // Ganti dengan jumlah total data yang ingin Anda tampilkan
        $config['per_page'] = 4;


        $config['full_tag_open'] = '<nav><ul class="pagination">';
        $config['full_tag_close'] = '</ul></nav>';

        $config['first_link'] = 'First';
        $config['first_tag_open'] = '<li class="page-item">';
        $config['first_tag_close'] = '</li>';

        $config['last_link'] = 'Last';
        $config['last_tag_open'] = '<li class="page-item">';
        $config['last_tag_close'] = '</li>';

        $config['next_link'] = '&raquo';
        $config['next_tag_open'] = '<li class="page-item">';
        $config['next_tag_close'] = '</li>';

        $config['prev_link'] = '&laquo';
        $config['prev_tag_open'] = '<li class="page-item">';
        $config['prev_tag_close'] = '</li>';

        $config['cur_tag_open'] = '<li class="page-item-active"><a class="page-link" href="#">';
        $config['cur_tag_close'] = '</a></li>';

        $config['num_tag_open'] = '<li class="page-item">';
        $config['num_tag_close'] = '</li>';
        // Produces: class="myclass"
        $config['attributes'] = array('class' => 'page-link');


        $this->pagination->initialize($config);
        $offset = $this->uri->segment(2, 0);
        //untuk pagination dan list product di cart
        $data['cart'] = $this->m_cart->get_list_cart_products($config['per_page'], $offset, $purchase_member);

        //tampilan sub total
        $data['total'] = $this->m_cart->get_total_products($purchase_member);


        // echo print_r($data['product']);
        // exit();
        // Menghitung subtotal untuk setiap item dalam keranjang belanja


        $total_subtotal = 0;
        foreach ($data['total'] as &$product) {
            $product['subtotal'] = $product['product_price'] * $product['qty'];
            $total_subtotal += $product['subtotal'];
        }

        $this->tsmarty->assign("total_subtotal", $total_subtotal);

        $pagination = $this->pagination->create_links();

        //id member add to cart di js
        if (isset($this->session->userdata['member'])) {
            $id_member =  $this->session->userdata['member'];
            $id = $id_member['user_id'];
            $this->tsmarty->assign("id_member", $id);
        } else {
            $id = '';
            $this->tsmarty->assign("id_member", $id);
        }


        //tampilan recommend3
        $data['product'] = $this->m_products->get_list_recommend();
        $this->tsmarty->assign("cart", $data['cart']);
        $this->tsmarty->assign("total", $data['total']);
        $this->tsmarty->assign("product", $data['product']);
        $this->tsmarty->assign("pagination", $pagination);
        $this->tsmarty->assign("purchase_member", $purchase_member);
        $this->tsmarty->assign("total_subtotal", $total_subtotal); // Menambahkan total subtotal ke Smarty

        parent::display();
    }
    public function countCartProducts()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $purchase_member = $data['purchase_member'];
        $total_product = $this->m_cart->countAllProducts($purchase_member);

        // Mengirim jumlah produk sebagai JSON
        $response = [
            'total_product' => $total_product,
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function remove_product()
    {
        $data = json_decode(file_get_contents('php://input'), true);


        $purchase_id = $data['purchase_id'];
        $product_id = $data['product_id'];
        // Panggil model untuk menghapus produk dari database
        $this->m_cart->remove_product($product_id, $purchase_id);
    }


    function save_cart()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $purchase_member = $data['user_id'];
        $product_id = $data['product_id'];
        $product_price = $data['product_price'];


        if ($this->m_cart->check_data_member($purchase_member, $product_id, $product_price)) {
            // if success

        } else {
            // failed

        }
    }


    function update_cart()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $purchase_member = $data['purchase_member'];
        $product_id = $data['product_id'];
        $qty = $data['qty'];


        if ($this->m_cart->update_qty($purchase_member, $product_id,  $qty)) {
            // if success

        } else {
            // failed

        }
    }


    public function detail_produk()
    {
        $id = $this->input->get('id');
        $this->tsmarty->assign("template_content", "public/detail_produk.html");



        $products['products'] = $this->m_products->get_detail_data_products($id);

        // // Mengambil data varian
        // $varian['varian'] = $this->m_products->get_all_varian_from_parent($id);
        $this->tsmarty->assign("products", $products['products']);
        // $this->tsmarty->assign("varian", $varian['varian']);

        // Output the view
        parent::display();
    }
}
