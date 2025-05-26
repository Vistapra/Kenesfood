<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Product extends CI_Controller
{
    public function __construct()
	{
		parent::__construct();

		$this->load->model('M_products', 'm_products');
	}

	public function detail($id)
	{
		$product = $this->m_products->get_detail_data_products($id);

		$data = 
		[
			"data" =>
				[
					"detail" => $product,
				],
		];

		return $this
			->output
			->set_content_type("application/json")
			->set_status_header(200)
			->set_output(json_encode($data));
	}
}