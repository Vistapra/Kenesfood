<?php

class M_mrp_api extends CI_Model {
    
    private $api_url = 'api';
    private $api_key = '0a6a2c9d1b10d26ef70f773c68c0f58e5eb85a6624015f12d70c635a23376c1f'; 
    
    function __construct() {
        parent::__construct();
    }
    
    function get_transactions($user_id) {
        $endpoint = '/transactions';
        $params = [
            'user_id' => $user_id,
            'limit' => 20 // Ambil 20 transaksi terakhir
        ];
        
        $response = $this->send_request('GET', $endpoint, $params);
        
        if ($response && isset($response->status) && $response->status === 'success') {
            return $response->data;
        }
        
        return [];
    }
    
    function get_point_history($user_id) {
        $endpoint = '/points/history';
        $params = [
            'user_id' => $user_id
        ];
        
        $response = $this->send_request('GET', $endpoint, $params);
        
        if ($response && isset($response->status) && $response->status === 'success') {
            return $response->data;
        }
        
        return [];
    }
    
    function sync_transactions($user_id) {
        // Ambil transaksi dari API MRP
        $transactions = $this->get_transactions($user_id);
        
        if (empty($transactions)) {
            return false;
        }
        
        // Simpan ke database lokal
        foreach ($transactions as $transaction) {
            // Cek apakah transaksi sudah ada
            $this->db->where('transaction_id', $transaction->id);
            $exists = $this->db->get('purchase_histories')->num_rows() > 0;
            
            if (!$exists) {
                // Simpan ke tabel purchase_histories
                $purchase_data = [
                    'purchase_code' => $transaction->code,
                    'purchase_member' => $user_id,
                    'purchase_date' => $transaction->date,
                    'purchase_status' => $transaction->status,
                    'purchase_total_amount' => $transaction->total_amount,
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $user_id
                ];
                
                $this->db->insert('purchase_histories', $purchase_data);
                $purchase_id = $this->db->insert_id();
                
                // Simpan detail transaksi
                foreach ($transaction->items as $item) {
                    $detail_data = [
                        'purchase_id' => $purchase_id,
                        'product_id' => $item->product_id,
                        'product_price' => $item->price,
                        'product_qty' => $item->quantity,
                        'product_discount' => $item->discount,
                        'created' => date('Y-m-d H:i:s'),
                        'created_by' => $user_id
                    ];
                    
                    $this->db->insert('purchase_history_details', $detail_data);
                }
            }
        }
        
        return true;
    }
    
    private function send_request($method, $endpoint, $params = []) {
        $url = $this->api_url . $endpoint;
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->api_key
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response);
    }
}