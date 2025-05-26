<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

require 'vendor/autoload.php';

class tsmarty extends Smarty
{

	private $javascript = "";
	private $style = "";

	// constructor
	function __construct()
	{
		parent::__construct();

		// default cache and view directories
		$this->compile_dir = "cache";
		$this->template_dir = APPPATH . "views";

		// Register previously mentioned modifiers
		$this->registerPlugin('modifier', 'site_url', 'site_url');
		$this->registerPlugin('modifier', 'ucfirst', 'ucfirst');
		$this->registerPlugin('modifier', 'file_exists', 'file_exists');
		$this->registerPlugin('modifier', 'print_r', 'print_r');
		$this->registerPlugin('modifier', 'date', 'date');

		// Register new modifiers
		$this->registerPlugin('modifier', 'number_format', 'number_format');
		$this->registerPlugin('modifier', 'strtoupper', 'strtoupper');
		$this->registerPlugin('modifier', 'strtotime', 'strtotime');
		$this->registerPlugin('modifier', 'base64_encode', 'base64_encode');
		$this->registerPlugin('modifier', 'trim', 'trim');
		$this->registerPlugin('modifier', 'ceil', 'ceil');
		

		// Register the min function
		$this->registerPlugin('modifier', 'min', 'min');

		// Assign base variables
		$this->assign('BASEURL', base_url());
		$this->assign('LOAD_STYLE', "");
		$this->assign('LOAD_JAVASCRIPT', "");
	}

	// load main themes
	public function load_themes($name = "default", $css = "load-style.css")
	{
		// load CI
		$CI = &get_instance();
		// get css file
		$css_file = $CI->config->item('themes_path') . '/themes/' . $name . '/' . $css;
		// assign
		if (is_file($css_file)) {
			$this->assign('THEMESPATH', base_url($css_file));
		} else {
			$msg = "File berikut ini tidak ditemukan : " . base_url($css_file);
			show_error($msg, 404);
		}
	}

	// load javascript
	public function load_javascript($path)
	{
		if (is_file($path)) {
			$this->javascript .= '<script type="text/javascript" src="' . base_url($path) . '"></script>';
			$this->javascript .= "\n";
			// assign
			$this->assign('LOAD_JAVASCRIPT', $this->javascript);
		} else {
			$msg = "File berikut ini tidak ditemukan : " . base_url($path);
			show_error($msg, 404);
		}
	}

	// load style
	public function load_style($path, $media = "all")
	{
		// load CI
		$CI = &get_instance();
		// get css file
		$css_file = $CI->config->item('themes_path') . '/themes/' . $path;
		// assign
		if (is_file($css_file)) {
			$this->style .= '<link rel="stylesheet" type="text/css" href="' . base_url($css_file) . '" media="' . $media . '" />';
			$this->style .= "\n";
			$this->assign('LOAD_STYLE', $this->style);
		} else {
			$msg = "File berikut ini tidak ditemukan : " . base_url($css_file);
			show_error($msg, 404);
		}
	}
}

// END Smarty Class
