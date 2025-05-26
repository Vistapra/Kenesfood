<?php

class BaseController extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();

		$check  = $this->authenticate();
		if (!$check[0])
		{
			throw new Exception($check[2], $check[1]);
		}
	}

	protected function send_response($data)
	{
		return $this->output->set_output(json_encode($data));
	}

	protected function send_error_response($statusCode, $message)
	{
		// var_dump($statusCode); exit();
		$this->output->set_status_header($statusCode);
		$this->output->set_content_type("application/json");
		$data = ["message" => $message];
		// var_dump($data); exit();
		return $this->output->set_output(json_encode($data));
	}

	private function authenticate()
	{
        $auth = $this->input->get_request_header('Authorization');

		if (!$auth)
		{
			return [false, 401, "Content-Type not satisfy the requirement"];
			$this->send_error_response(422, "Authorization header not found");
		}

		if (!preg_match('/Basic\s+(.*)$/i', $auth, $matches))
		{
			$this->send_error_response(401, "Invalid authorization scheme");
		}

		$credentials = base64_decode($matches[1]);
        list($username, $password) = explode(':', $credentials, 2);
		return [true];
		// echo 'Username: ' . htmlspecialchars($username) . '<br>';
        // echo 'Password: ' . htmlspecialchars($password);
	}

	private function validate_content_type()
	{
		$contentType = $this->input->get_request_header("Content-Type");

		if (!$contentType)
		{
			$this->send_error_response(422, "Malformed HTTP Request");
		}

		$contentType = str_replace(' ', '', $contentType);
		$contentType = explode(';', $contentType);

		if (!in_array("application/json", $contentType))
		{
			// return "fail";
			return [false, 422, "Content-Type not satisfy the requirement"];
			// throw new Exception("Content-Type not satisfy the requirement", 422);
			// $this->send_error_response(422, "Content-Type not satisfy the requirement");
		}
	}
}
