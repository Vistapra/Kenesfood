<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once( APPPATH . 'controllers/base/BackgroundTaskBase.php' );

class Synchronizer extends ApplicationBase
{
    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
		$this->output->set_content_type("application/json");
        // load model
        $this->load->model('settings/M_jobs', 'm_jobs');
		$this->load->model('master/M_categories', 'm_categories');
    }

	private function store_log_error()
	{

	}

	private function fetch_resource(string $endpoint)
	{
		$url      = CUSTOM_URL_MRP. $endpoint;
        $username = API_MRP_IDENTIFIER;
        $password = API_MRP_PASSWORD;

        $fetch = curl_init();
        curl_setopt($fetch, CURLOPT_URL, $url);
        curl_setopt($fetch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($fetch, CURLOPT_USERPWD, $username.":".$password);
		curl_setopt($fetch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($fetch, CURLOPT_HEADER, true);
		try
		{
        	$response = curl_exec($fetch);
			$httpCode = curl_getinfo($fetch, CURLINFO_HTTP_CODE);
		}
		catch(\Exception $e)
		{
			$this->store_log_error();
			exit();
		}

        curl_close($fetch);

		if ($httpCode !== 200)
		{
				
		}
		list($headers, $body) = explode("\r\n\r\n", $response, 2);

		$data = json_decode($body, true);

		$this->data = $data["data"];
		
		return $this->output->set_output(json_encode(["data"=> $data["data"]]));
	}

    public function sync_categories()
	{
		$start = time();
		$data  = $this->fetch_categories('/categories/getAll');
		$end   = time();
		
		$start = time();
		if (!$data)
		{
			return 1;
		}

		// $data = 
		$end = time();
    }

    public function sync_products() {

        // var_dump()
        // {
        //     "id": 1,
        //     "code": "KNS001",
        //     "name": "ROTI PREMIUM",
        //     "description": "ROTI PREMIUM",
        //     "category_type": "produk",
        //     "deleted": 0,
        //     "created": "2018-09-14T12:38:37+07:00",
        //     "modified": "2019-04-02T03:19:10+07:00"
        // },
    }
}