<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PublicBase.php' );

// --
class Favorite extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        $this->load->model('M_favorite', 'm_favorite');
        $this->load->library('session');
        // load model
    }

    // dashboard
    public function index() {
      
        $this->tsmarty->assign("template_content", "public/favorite.html");
         if (isset($this->session->member['user_id'])) {
            $user_id = $this->session->member['user_id'];
        } else {
        $this->session->set_flashdata('message', array('msg' => 'Harap login untuk mengakses halaman tersebut.', 'status' => 'error'));
           redirect('public/login');
            }

        
        
        
        // Dapatkan daftar produk favorit dari model
        $favorite_products = $this->m_favorite->get_user_favorite_products($user_id);

        $this->tsmarty->assign("favorite_products", $favorite_products);
		$this->tsmarty->assign("user_id", $user_id);
        parent::display();
    }

    function save_favorite (){
        $data = json_decode(file_get_contents('php://input'), true);


        $data_favorite = array (
            'user_id' => $data['user_id'],
            'product_id' => $data['product_id'],
        );

        $this->m_favorite->add_favorite_data($data_favorite );

    }

    public function checkProductFavorite() {

        $data = json_decode(file_get_contents('php://input'), true);
        
            // Access POST data
            $user_id = $this->session->member['user_id'];
            $product_id = $data['product_id'];

            $isFavorite = $this->m_favorite->isProductFavorite($user_id, $product_id);
        
            if ($isFavorite) {
                $response = [
                    "message" => "Found",
                    "product_id" => $product_id // Sertakan product_id dalam respons
                ];
                echo json_encode($response);
            } else {
                $response = [
                    "message" => "Not Found",
                ];
                echo json_encode($response);
            }
  
    }


    public function removeProductFavorite() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Access POST data
        $user_id = $this->session->member['user_id'];
        $product_id = $data['product_id'];
    
        $isFavorite = $this->m_favorite->isProductFavorite($user_id, $product_id);
    
        if ($isFavorite) {
            // Hapus produk dari daftar favorit pengguna
            $this->m_favorite->removeProductFromFavorite($user_id, $product_id);
    
            $response = [
                "message" => "Removed",
                "product_id" => $product_id // Sertakan product_id dalam respons
            ];
            echo json_encode($response);
        } else {
            // Produk bukan favorit pengguna
            show_404();
        }
    }
    
}
