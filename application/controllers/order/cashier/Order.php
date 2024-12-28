<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once( APPPATH . 'controllers/base/PrivateBase.php' );

class Order extends ApplicationBase
{
    function __construct()
	{
        parent::__construct();
        $this->load->model('settings/M_outlets', 'm_outlets');
		$this->load->model('order/cashier/M_Order', "M_Order");
		$this->load->model('order/cashier/M_Order_Detail', "M_Order_Detail");
    }

    function index()
	{
        $this->tsmarty->assign("template_content", "order/cashier/orderIndex.html");
        // search
        $keyword = '';
        $search = $this->session->userdata('search_outlet');
        if ($this->input->post())
		{
            if ($this->input->post('save') == "Reset")
			{
                $this->session->unset_userdata("search_outlet");
            }
			else
			{
                $keyword = $this->input->post('keyword');
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_outlet", $params);
            }
        }
		elseif (!empty($search))
		{
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('master/orders/index/');
        $config['total_rows'] = $this->m_outlets->get_total_data($keyword);
        $config['uri_segment'] = 4;
        $config['per_page'] = 10;
        $this->pagination->initialize($config);
        $pagination['data'] = $this->pagination->create_links();
        // pagination attribute
        $start = $this->uri->segment(4, 0) + 1;
        $end = $this->uri->segment(4, 0) + $config['per_page'];
        $end = (($end > $config['total_rows']) ? $config['total_rows'] : $end);
        $pagination['start'] = ($config['total_rows'] == 0) ? 0 : $start;
        $pagination['end'] = $end;
        $pagination['total'] = $config['total_rows'];
        // pagination assign value
        $this->tsmarty->assign("pagination", $pagination);
        $this->tsmarty->assign("no", $start);
        /* end of pagination ---------------------- */
        // get list data
        $params = array(($start - 1), $config['per_page']);
        $data = $this->m_outlets->get_list_data($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

	function detail($id)
	{
		$data = $this
			->m_outlets
			->get_detail_outlet($id);

		$this->tsmarty->assign(
			"template_content",
			"order/cashier/orderDetail.html"
		);
		$this
			->tsmarty
			->assign("datas", $data);

		parent::display();
	}

	function getData()
	{
		$action = $this->input->get("action");

		$data = $this->$action();

		$res = [
			"success" => true,
			"message" => "Data table has been retrieved!",
			"data" => $data
		];
		
		return $this->output->set_output(json_encode($res));
	}

	function download()
	{
		$action = $this->input->get("action");

		$data = $this->$action();

		return $data;
	}

	private function printReceipt()
	{
		$params = [
			"outlet_id" => $this->input->get("outletId"),
			"brand" => $this->input->get("brand"),
			"table_id" => $this->input->get("tableId")
		];

		$order = $this->M_Order->getOrderWithDetail($params);
		$outlet = $this->m_outlets->get_detail_outlet($params["outlet_id"]);
		$customer = $this->M_Order->getOne(
			array_merge($params, [
				"deleted_at" => NULL,
				"status" => 1,
			])
		);

		$orderDateTime = new DateTime($customer["created_at"]);

		$data = [
			"order" => $order,
			"outlet" => $outlet,
			"customer" => [
				"order" => $customer,
				"date" => $orderDateTime->format("d/m/Y"),
				"time" => $orderDateTime->format("H:i")
			]
		];

 		$this->tsmarty->assign("data", $data);
		$this->tsmarty->display("order/cashier/orderReceipt.html");
	}

	private function getStatusTable()
	{
		$params = [
			"outlet_id" => $this->input->get("outletId"),
			"brand" => $this->input->get("brand")
		];

		$outlet = $this
			->m_outlets
			->get_detail_outlet($params["outlet_id"]);

		$table = array_fill(0, $outlet["count_table"], NULL);

		$params['deleted_at'] = NULL;
 
		$orders = $this->M_Order->getOrderStatus($params);

		foreach($orders as $order)
		{
			$table[--$order["table_id"]] = $order["status"];
		}

		return $table;
	}

	private function getOrder()
	{
		$params = [
			"table_id" => $this->input->get("tableId"),
			"outlet_id" => $this->input->get("outletId"),
			"brand" => $this->input->get("brand")
		];

		$order = $this->M_Order->getOne(
			array_merge($params, [
				"deleted_at" => NULL,
				"status" => 1,
			])
		);

		$orderDetail = $this->M_Order->getDetailsByOrderId($order["id"]);

		$res = [
			"order" => $order,
			"orderDetails" => $orderDetail
		];
		
		return $res;
	}

	public function delete($id)
	{
		$params = [
			"deleted_at" => date("Y-m-d H:i:s")
		];

		$order = $this->M_Order->update($id, array_merge($params, [
			"cashier_id" => $this->user_data["user_id"]
		]));
		$orderDetail = $this->M_Order_Detail->updateByOrderId($id, $params);

		$res = [
			"success" => true,
			"message" => "Data has been deleted!"
		];

		return $this->output->set_output(json_encode($res));
	}
}