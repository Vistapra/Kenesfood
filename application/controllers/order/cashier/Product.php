<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once( APPPATH . 'controllers/base/PrivateBase.php' );

class Product extends ApplicationBase
{
	 function __construct()
	{
        parent::__construct();
        // $this->load->model('settings/M_outlets', 'm_outlets');
		// $this->load->model('master/M_orders', "M_Orders");
		$this->load->model('order/cashier/M_Product', "M_Product");
    }

    function index()
	{
		$limit = 10;

        // search
        $keyword = '';
        $search = $this->session->userdata('search_product');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_product");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_product", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }

		// load library
        $this->load->library('pagination');
		// pagination
		$config['base_url'] = site_url('order/cashier/product/index');
        $config['total_rows'] = $this->M_Product->countAll($keyword);
        $config['uri_segment'] = 5;
        $config['per_page'] = $limit;
        $this->pagination->initialize($config);
		$pagination['data'] = $this->pagination->create_links();
		// pagination attribute
        $start = $this->uri->segment(5, 0) + 1;
        $end = $this->uri->segment(5, 0) + $config['per_page'];
        $end = (($end > $config['total_rows']) ? $config['total_rows'] : $end);
        $pagination['start'] = ($config['total_rows'] == 0) ? 0 : $start;
        $pagination['end'] = $end;
        $pagination['total'] = $config['total_rows'];

		$data = $this->M_Product->getList($keyword, $limit, ($start-1));

		// Set template content
        $this->tsmarty->assign("template_content", "order/cashier/productIndex.html");
        $this->tsmarty->assign("keyword", $keyword);
        // pagination assign value
        $this->tsmarty->assign("pagination", $pagination);
        $this->tsmarty->assign("no", $start);
        $this->tsmarty->assign("data", $data);
        // output
        parent::display();
	}

	public function update($id)
	{
		$payload = $this->input->raw_input_stream;
		$payload = $this->security->xss_clean($payload);
		$payload = json_decode($payload, TRUE);

		$data = $payload;

		$updated = $this->M_Product->update($id, $data);

		$res = [
			"success" => true,
			"message" => "Data has been updated!",
			"data" => $updated
		];

		return $this->output->set_output(json_encode($res));
	}
}