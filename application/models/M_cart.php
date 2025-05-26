<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_cart extends CI_Model
{
    function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }

    public function countAllProducts($purchase_member)
    {
        $this->db->select('td.purchase_id');
        $this->db->from('temp_cart_details td'); // Gunakan alias "t" untuk tabel "temp_cart_detail"
        $this->db->join('temp_cart c', 'td.purchase_id = c.purchase_id', 'left');
        $this->db->join('data_product p', 'td.product_id = p.product_id', 'left');
        $this->db->where('c.purchase_member', $purchase_member);
        return $this->db->count_all_results();
    }

    function get_list_cart_products($limit, $offset, $purchase_member)
    {
        $this->db->select('td.product_id, td.purchase_id,  td.qty, p.product_name, p.product_price, p.product_pict');
        $this->db->from('temp_cart_details td'); // Gunakan alias "td" untuk tabel "temp_cart_detail"
        $this->db->join('temp_cart c', 'td.purchase_id = c.purchase_id', 'left');
        $this->db->join('data_product p', 'td.product_id = p.product_id', 'left'); // Menggunakan kunci primer product_id
        $this->db->where('c.purchase_member', $purchase_member);
        $this->db->limit($limit, $offset);

        $query = $this->db->get();

        $cart_products = $query->result_array();

        // Menghitung subtotal untuk setiap produk dalam keranjang
        foreach ($cart_products as &$product) {
            $product['subtotal'] = $product['product_price'] * $product['qty'];
        }

        return $cart_products;
    }

    function get_recommend_products()
    {
        
        $this->db->select('p.product_id, p.product_name, p.product_price, p.product_st, p.product_pict')
            ->from('data_product p')
            ->join('product_categories pc', 'p.product_id = pc.product_id', 'left')
            ->join('data_categories c', 'pc.cat_id = c.cat_id', 'left')
            ->get()
            ->result_array();

        $query = $this->db->get();
        $query->result_array();
    }

    function get_total_products($purchase_member)
    {
        $this->db->select('td.product_id, td.purchase_id,  td.qty, p.product_name, p.product_price, p.product_pict');
        $this->db->from('temp_cart_details td'); // Gunakan alias "t" untuk tabel "temp_cart_detail"
        $this->db->join('temp_cart c', 'td.purchase_id = c.purchase_id', 'left');
        $this->db->join('data_product p', 'td.product_id = p.product_id', 'left'); // Menggunakan kunci primer product_id
        $this->db->where('c.purchase_member', $purchase_member);

        $query = $this->db->get();

        $cart_products = $query->result_array();

        // Menghitung subtotal untuk setiap produk dalam keranjang
		$total = 0;
        foreach ($cart_products as &$product) {
            $product['subtotal'] = $product['product_price'] * $product['qty'];
			$total += $product['subtotal'];
			$purchase_id = $product['purchase_id'];

				if($purchase_id){
				$where = array(
					'purchase_id' => $purchase_id,
				);
				$this->db->set('purchase_total_amount', $total);
				$this->db->where($where);
		
				$this->db->update('temp_cart');
			}
			
        }
		//update total di tabel cart
        return $cart_products;
    }

	public function get_data_cart_per_id ($purchase_member){
		$query = $this->db->get_where('temp_cart', array('purchase_member' => $purchase_member));
		$row = $query->row();
		return $row->purchase_id;
	}
    public function remove_product($product_id, $purchase_id)
    {
       
        $where = "product_id=$product_id AND purchase_id =$purchase_id";
        $this->db->where($where);
        $this->db->delete('temp_cart_details'); // Gantilah 'nama_tabel_produk' dengan nama tabel yang sesuai
    }

    public function remove_product_all($purchase_id_cart)
    {
       
        $where = "purchase_id =$purchase_id_cart";
		$tables = array('temp_cart', 'temp_cart_details');
        $this->db->where($where);
        $this->db->delete($tables); // Gantilah 'nama_tabel_produk' dengan nama tabel yang sesuai
    }


    function add_cart_data($data_cart)
    {

        $this->db->insert('temp_cart', $data_cart);
    }

    function add_cart_detail($data_cart_detail)
    {
        $this->db->insert('temp_cart_details', $data_cart_detail);
    }

    function update_qty($purchase_member, $product_id,  $qty)
    {

        $where = "purchase_member=$purchase_member";
        $result1 = $this->db->select('purchase_member, purchase_id')
            ->from('temp_cart')
            ->where($where)
            ->get()
            ->row_array();

        if ($result1) {

            $where = array(
                'purchase_id' => $result1['purchase_id'],
                'product_id' => $product_id,
            );
            $this->db->set('qty', $qty);
            $this->db->where($where);

            $this->db->update('temp_cart_details');
        }
    }

    function check_data_member($purchase_member, $product_id, $product_price)
    {
        $where = "purchase_member=$purchase_member";
        $result_member_id = $this->db->select('*')
            ->from('temp_cart')
            ->where($where)
            ->get()
            ->result_array();
        if ($result_member_id) {
		
            $where = "a.purchase_member=$purchase_member AND b.product_id=$product_id";
            $result1 = $this->db->select('a.purchase_member, b.product_id, b.qty, b.purchase_id')
                ->from('temp_cart a')
                ->join('temp_cart_details b', 'a.purchase_id = b.purchase_id', 'inner')
                ->where($where)
                ->get()
                ->row_array();

            if ($result1) {

                $where = array(
                    'purchase_id' => $result1['purchase_id'],
                    'product_id' =>  $product_id,
					
                );
                $this->db->set('qty', $result1['qty'] + 1);
				$this->db->set('product_price', $product_price);
                $this->db->where($where);

                $this->db->update('temp_cart_details');

                return TRUE;
            } else {
                $where = "a.purchase_member=$purchase_member";
                $result = $this->db->select('a.purchase_member, a.purchase_id')
                    ->from('temp_cart a')
                    ->where($where)
                    ->get()
                    ->row_array();
                if ($result) {
                    $data = array(
                        'purchase_id' => $result['purchase_id'],
                        'product_id' => $product_id,
						'product_price' =>  $product_price,
                        'qty' => 1
                    );
                    $this->db->insert('temp_cart_details', $data);
                }
            }
        } else {
            $data = array(
                'purchase_member' => $purchase_member,
				'created' => date('Y-m-d H:i:s'),
				'created_by' => $purchase_member,

            );
            $this->db->insert('temp_cart', $data);

            $data = array(
                'purchase_id' => $this->db->insert_id(),
                'product_id' => $product_id,
				'product_price' =>  $product_price,
                'qty' => 1
            );
            $this->db->insert('temp_cart_details', $data);
        }
        return FALSE;
    }

 


}
