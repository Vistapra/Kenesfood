<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PublicBase.php');

// --
class Purchase extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('M_purchase', 'm_purchase');
        $this->load->model('M_cart', 'm_cart');
        $this->load->library('form_validation');
        $this->load->library('session');
    }

    // dashboard
    public function index()
    {
        $data['kode'] = $this->generate_kode();
        $this->tsmarty->assign('purchase_code', $data['kode']);
        $user_id = $this->session->member['user_id'];
        // $whatsappNumber = $this->m_purchase->getWhatsAppNumber($user_id);
        


        //  echo print_r($['']);
        // exit();
        $data_cart_checkout = array(
            'purchase_code' => $data['kode'],
            'purchase_member' => $this->input->post('purchase_member'),
            'purchase_date' => date('Y-m-d H:i:s'),
            'purchase_status' => 'pending',
            'purchase_total_amount' => $this->input->post('purchase_total_amount'),
            'purchase_promo_redeem_id'=>$this->input->post('promoID'),
            'created_by' => $this->input->post('purchase_member'),
            'created' => date('Y-m-d H:i:s'),
        );
        if ($promoAmount) {
            // Jika promoAmount ada, tambahkan promotion_promo_redeem_id ke dalam array
            $data_cart_checkout['promotion_promo_redeem_id'] = $promoAmount->promotion_id;
            // print_r($promoAmount);exit();
        }
        else {
            // Debug: Tampilkan pesan jika promoAmount kosong
            echo "Promo Amount is empty.";
        }

        $this->m_purchase->add_cart_checkout($data_cart_checkout);
        $purchase_member = $this->session->member['user_id'];

        $purchase_id = $this->m_purchase->get_last_inserted_id();

        $data = $this->m_purchase->get_list_cart_products($purchase_member);
        // $this->output->set_content_type('application/json')->set_output(json_encode($response));
       
        
        
        foreach ($data as $key => $value) {
            
            
            // $promoId = $response['promoId'];
            $saveData = array(
                'purchase_id' => $purchase_id,
                'product_id' => $value['product_id'],
                'product_price' => $value['product_price'],
                'product_qty' => $value['qty'],
                'created' => date('Y-m-d H:i:s'),
                'created_by' => $purchase_member,
            );

            $this->m_purchase->add_cart_checkout_details($saveData);
        }

        //untuk mendapatkan kode pembelian
        $latestPurchaseCode['purchase_code'] = $this->m_purchase->getLatestPurchaseCode($purchase_id);
        // var_dump($latestPurchaseCode['purchase_code']);
        // exit();

        if ($latestPurchaseCode) {
            // Jika kode pembelian terbaru ditemukan, Anda dapat menggunakannya
            $message = 'Silahkan melakukan pembayaran di outlet Kenes terdekat dengan menunjukan kode berikut: ' . $latestPurchaseCode['purchase_code'];
        } else {
            // Jika tidak ada kode pembelian sebelumnya, Anda dapat menginisialisasi dengan nilai awal atau menangani kasus ini sesuai kebutuhan Anda
            $message = 'Silahkan melakukan pembayaran di outlet Kenes terdekat dengan menunjukan kode pembelian.';
        }

        // Kemudian, Anda dapat mengambil nomor WhatsApp dari database seperti yang telah dijelaskan sebelumnya
        $whatsappNumber = '6287811121888';

        // Buat URL WhatsApp
        $whatsappUrl = 'https://api.whatsapp.com/send?phone=' . urlencode($whatsappNumber) . '&text=' . urlencode($message);
        header("Location: " . $whatsappUrl);

        //hapus semua data keranjang
        $purhase_cart['purchase_id'] = $this->m_cart->get_data_cart_per_id($purchase_member);
        $this->m_cart->remove_product_all($purhase_cart['purchase_id']);

        //  redirect('/');
    }

    public function generate_kode()
    {
        $max_code = $this->m_purchase->get_last_cart_sequence();

        // Calculate the new sequence
        if ($max_code) {
            $next_number = intval(substr($max_code, -4)) + 1;
        } else {
            $next_number = 1000;
        }


        // Generate the product code
        $prefix = 'PRC';
        $kode = $prefix . str_pad($next_number, 4, '0', STR_PAD_LEFT);
        return $kode;
    }
    public function redeem() {
        $data = json_decode(file_get_contents('php://input'), true);
        //print_r($data);exit();
        $promoCode = $data['promoCode'];
        $promoAmount = $this->m_purchase->checkPromoCode($promoCode);
        if ($promoAmount){
            $response = [
                'valid' => true,
                'promoAmount' => $promoAmount->promotion_price,
                'promoId' => $promoAmount->promotion_id
            ];

            $this->output->set_content_type('application/json')->set_output(json_encode($response));
        } else{
            $response = [
                'valid' => false,
            ];

            $this->output->set_content_type('application/json')->set_output(json_encode($response));
        }
     

        // Set header sebagai JSON
      
    }
}
