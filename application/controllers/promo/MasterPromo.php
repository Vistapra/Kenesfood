<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PrivateBase.php');

class MasterPromo extends ApplicationBase
{
	public function __construct()
	{
		// parent constructor
		parent::__construct();
		// load model
		$this->load->model('promo/M_Promo');
		$this->load->model('master/M_categories', 'm_categories');
		$this->load->model('master/M_products', 'm_products');
		$this->load->library('session');
		$this->load->library('pagination');
		$this->load->helper('form');
		$this->load->helper('url');
	}

	public function index()
	{
		// Set template content
		$this->tsmarty->assign("template_content", "promo/list.html");

		// Get filter parameters
		$brand = $this->input->get('brand') ?: 'all';
		$status = $this->input->get('status') ?: 'all';
		$search = $this->input->get('search') ?: '';
		$product_id = $this->input->get('product_id') ?: '';
		$category_id = $this->input->get('category_id') ?: '';
		$promo_type = $this->input->get('promo_type') ?: 'all';
		$voucher_type = $this->input->get('voucher_type') ?: 'all';

		// Setup pagination
		$page = $this->input->get('page') ?: 1;
		$limit = 10;
		$offset = ($page - 1) * $limit;

		// Determine current tab (untuk pagination)
		$current_tab = $this->input->get('tab') ?: 'all';

		// Prepare filter params for model - untuk promo internal
		$params_internal = [
			'limit' => $limit,
			'offset' => $offset,
			'promo_status' => $status === 'all' ? null : $status,
			'search' => $search,
			'is_mrp_voucher' => 0 // Hanya promo internal
		];

		// Prepare filter params for MRP vouchers
		$params_mrp = [
			'limit' => $limit,
			'offset' => $offset,
			'promo_status' => $status === 'all' ? null : $status,
			'search' => $search,
			'is_mrp_voucher' => 1 // Hanya voucher MRP
		];

		if ($brand !== 'all') {
			$params_internal['promo_brand'] = $brand;
			// Untuk MRP vouchers, tidak perlu filter brand karena mereka support multi-brand
		}

		// Filter berdasarkan produk dan kategori jika ada - hanya untuk internal
		if (!empty($product_id)) {
			$params_internal['product_id'] = $product_id;
		}
		if (!empty($category_id)) {
			$params_internal['category_id'] = $category_id;
		}

		// Filter promo_type hanya untuk promo internal berdasarkan tab yang aktif
		if ($current_tab == 'discount') {
			$params_internal['promo_type_in'] = ['percentage', 'nominal'];
		} elseif ($current_tab == 'bundling') {
			$params_internal['promo_type'] = 'bundling';
		} elseif ($current_tab == 'bogo') {
			$params_internal['promo_type'] = 'bogo';
		}

		// Filter voucher_type jika tab MRP aktif
		if ($voucher_type !== 'all' && $current_tab == 'mrp') {
			$params_mrp['promo_type'] = $voucher_type;
		}

		// Get promos based on filters
		$internal_promos = $this->M_Promo->getAllPromos($params_internal);
		$mrp_vouchers = $this->M_Promo->getAllPromos($params_mrp);

		// PERBAIKAN: Hitung total untuk setiap tipe promo INTERNAL (tanpa MRP)
		$discount_count = 0;
		$bundling_count = 0;
		$bogo_count = 0;

		// Count internal promos by type (excluding MRP vouchers)
		$count_params = ['is_mrp_voucher' => 0]; // Hanya hitung promo internal

		// Total untuk promo diskon (percentage dan nominal)
		$count_params['promo_type_in'] = ['percentage', 'nominal'];
		$discount_count = $this->M_Promo->countAllPromos($count_params);

		// Total untuk promo bundling
		$count_params['promo_type_in'] = ['bundling'];
		$bundling_count = $this->M_Promo->countAllPromos($count_params);

		// Total untuk promo BOGO
		$count_params['promo_type_in'] = ['bogo'];
		$bogo_count = $this->M_Promo->countAllPromos($count_params);

		// Count MRP vouchers secara terpisah
		$mrp_voucher_count = $this->M_Promo->countAllPromos(['is_mrp_voucher' => 1]);

		// PERBAIKAN: Total promo internal (tanpa MRP vouchers)
		$total_internal = $discount_count + $bundling_count + $bogo_count;

		// Determine which total to use for pagination based on active tab
		if ($current_tab === 'mrp') {
			$total = $mrp_voucher_count;
		} elseif ($current_tab === 'discount') {
			$total = $discount_count;
		} elseif ($current_tab === 'bundling') {
			$total = $bundling_count;
		} elseif ($current_tab === 'bogo') {
			$total = $bogo_count;
		} else {
			// Tab all - hanya menampilkan promo internal (bukan MRP)
			$total = $total_internal;
		}

		// Get promo stats - hanya menghitung promo internal, bukan MRP
		$stats = $this->M_Promo->getPromoStats();

		// Process internal promos
		foreach ($internal_promos as &$promo) {
			$this->processPromoDetails($promo);
		}

		// Process MRP vouchers with fixed type handling
		foreach ($mrp_vouchers as &$promo) {
			$this->processPromoDetails($promo);
		}

		// Setup pagination config
		$base_url = site_url('promo/MasterPromo/index') . '?tab=' . $current_tab;

		// Preserve all other query params for pagination URLs
		if (!empty($search)) {
			$base_url .= '&search=' . urlencode($search);
		}
		if ($brand != 'all') {
			$base_url .= '&brand=' . urlencode($brand);
		}
		if ($status != 'all') {
			$base_url .= '&status=' . urlencode($status);
		}
		if ($promo_type != 'all') {
			$base_url .= '&promo_type=' . urlencode($promo_type);
		}
		if ($voucher_type != 'all' && $current_tab == 'mrp') {
			$base_url .= '&voucher_type=' . urlencode($voucher_type);
		}

		$config = [
			'base_url' => $base_url,
			'total_rows' => $total,
			'per_page' => $limit,
			'use_page_numbers' => TRUE,
			'page_query_string' => TRUE,
			'query_string_segment' => 'page',
			'reuse_query_string' => FALSE,

			// Pagination styling
			'full_tag_open' => '<ul class="pagination pagination-sm mb-0">',
			'full_tag_close' => '</ul>',
			'num_tag_open' => '<li class="page-item">',
			'num_tag_close' => '</li>',
			'cur_tag_open' => '<li class="page-item active"><a class="page-link" href="#">',
			'cur_tag_close' => '</a></li>',
			'next_tag_open' => '<li class="page-item">',
			'next_tag_close' => '</li>',
			'prev_tag_open' => '<li class="page-item">',
			'prev_tag_close' => '</li>',
			'first_tag_open' => '<li class="page-item">',
			'first_tag_close' => '</li>',
			'last_tag_open' => '<li class="page-item">',
			'last_tag_close' => '</li>',
			'next_link' => '<i class="fas fa-chevron-right"></i>',
			'prev_link' => '<i class="fas fa-chevron-left"></i>',
			'first_link' => '<i class="fas fa-step-backward"></i>',
			'last_link' => '<i class="fas fa-step-forward"></i>',
			'attributes' => array('class' => 'page-link')
		];

		$this->pagination->initialize($config);

		// Calculate pagination display values
		$page_number = $this->input->get('page') ? (int)$this->input->get('page') : 1;
		$start_item = ($page_number - 1) * $limit + 1;
		$end_item = min($start_item + $limit - 1, $total);

		// Store pagination data for display
		$pagination_info = [
			'start' => $start_item,
			'end' => $end_item,
			'total' => $total
		];

		// Store pagination data for MRP display
		$pagination_info_mrp = [
			'start' => $start_item,
			'end' => min($start_item + $limit - 1, $mrp_voucher_count),
			'total' => $mrp_voucher_count
		];

		// Get products and categories for filter modal
		$bakery_params = [0, 500]; // offset, limit
		$bakery_products = $this->m_products->get_list_data($bakery_params, '', 'bakery');
		$kopitiam_params = [0, 500]; // offset, limit
		$kopitiam_products = $this->m_products->get_list_data($kopitiam_params, '', 'kopitiam');
		$resto_params = [0, 500]; // offset, limit
		$resto_products = $this->m_products->get_list_data($resto_params, '', 'resto');

		// Get categories for selection
		$bakery_categories = $this->m_categories->get_list_category_by_brand('bakery');
		$kopitiam_categories = $this->m_categories->get_list_category_by_brand('kopitiam');
		$resto_categories = $this->m_categories->get_list_category_by_brand('resto');

		// Assign variables to template
		$this->tsmarty->assign('discount_count', $discount_count);
		$this->tsmarty->assign('bundling_count', $bundling_count);
		$this->tsmarty->assign('bogo_count', $bogo_count);
		$this->tsmarty->assign('filter_promo_type', $promo_type);

		// Assign separated promo data
		$this->tsmarty->assign('internal_promos', $internal_promos);
		$this->tsmarty->assign('mrp_vouchers', $mrp_vouchers);
		$this->tsmarty->assign('total_internal', $total_internal);
		$this->tsmarty->assign('total_mrp', $mrp_voucher_count);
		$this->tsmarty->assign('current_tab', $current_tab);

		// Assign original promo data for backward compatibility - tapi hanya internal promos
		$this->tsmarty->assign('promos', $internal_promos); // Hanya internal promos yang ditampilkan di tab "Semua Promo"
		$this->tsmarty->assign('pagination', $this->pagination->create_links());

		// PERBAIKAN: Total promos untuk badge pada tab "Semua Promo" - hanya internal promos
		$this->tsmarty->assign('total_promos', $total_internal);

		$this->tsmarty->assign('filter_brand', $brand);
		$this->tsmarty->assign('filter_status', $status);
		$this->tsmarty->assign('search', $search);
		$this->tsmarty->assign('filter_product_id', $product_id);
		$this->tsmarty->assign('filter_category_id', $category_id);

		// Assign pagination info for all displays
		$this->tsmarty->assign('pagination_info', $pagination_info);
		$this->tsmarty->assign('pagination_info_mrp', $pagination_info_mrp);

		// Assign products and categories for filter
		$this->tsmarty->assign('bakery_products', $bakery_products);
		$this->tsmarty->assign('kopitiam_products', $kopitiam_products);
		$this->tsmarty->assign('resto_products', $resto_products);
		$this->tsmarty->assign('bakery_categories', $bakery_categories);
		$this->tsmarty->assign('kopitiam_categories', $kopitiam_categories);
		$this->tsmarty->assign('resto_categories', $resto_categories);

		// Assign stats
		$this->tsmarty->assign('active_count', $stats['active_count']);
		$this->tsmarty->assign('upcoming_count', $stats['upcoming_count']);
		$this->tsmarty->assign('expired_count', $stats['expired_count']);
		$this->tsmarty->assign('mrp_voucher_count', $mrp_voucher_count);
		$this->tsmarty->assign('filter_voucher_type', $voucher_type);

		// Get the latest sync time for MRP vouchers
		$this->db->select('MAX(last_sync) as last_sync');
		$this->db->where('is_mrp_voucher', 1);
		$last_sync = $this->db->get('promos')->row()->last_sync;
		$this->tsmarty->assign('last_mrp_sync', $last_sync);

		// Output
		parent::display();
	}

	private function processPromoDetails(&$promo)
	{
		$promo['remaining_quota'] = $promo['quota'] !== null ? $promo['quota'] - $promo['usage_count'] : 'Unlimited';
		// Format dates for display
		$promo['formatted_start_date'] = date('d M Y H:i', strtotime($promo['start_date']));
		$promo['formatted_end_date'] = date('d M Y H:i', strtotime($promo['end_date']));
		// Check if promo is active based on date
		$now = time();
		$start = strtotime($promo['start_date']);
		$end = strtotime($promo['end_date']);
		$promo['date_status'] = ($now >= $start && $now <= $end) ? 'active' : ($now < $start ? 'upcoming' : 'expired');
		// Check if quota is exhausted
		if ($promo['quota'] !== null && $promo['usage_count'] >= $promo['quota']) {
			$promo['quota_status'] = 'exhausted';
		} else {
			$promo['quota_status'] = 'available';
		}
		// Overall status combines status, date and quota
		$promo['overall_status'] = $promo['promo_status'] === 'active' && $promo['date_status'] === 'active' && $promo['quota_status'] === 'available' ? 'active' : 'inactive';
		// Dapatkan informasi produk dan kategori yang terkait
		$promo['has_product_specific'] = false;
		$promo['has_category_specific'] = false;
		if (!$promo['is_mrp_voucher']) {
			$promo_products = $this->M_Promo->getPromoProducts($promo['promo_id']);
			if (!empty($promo_products)) {
				$promo['has_product_specific'] = true;
				$promo['product_count'] = count($promo_products);
			}
			$promo_categories = $this->M_Promo->getPromoCategories($promo['promo_id']);
			if (!empty($promo_categories)) {
				$promo['has_category_specific'] = true;
				$promo['category_count'] = count($promo_categories);
			}
		}
		// Tambahkan flag untuk MRP voucher
		$promo['is_mrp_voucher_display'] = $promo['is_mrp_voucher'] == 1 ? true : false;
	}
	public function bundling($promo_id = null)
	{
		// Check if promo_id is provided
		if ($promo_id === null) {
			$this->session->set_flashdata('error', 'Promo ID tidak valid');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Get promo details
		$promo = $this->M_Promo->getPromoById($promo_id);

		if (!$promo) {
			$this->session->set_flashdata('error', 'Promo tidak ditemukan');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Set template content
		$this->tsmarty->assign("template_content", "promo/bundling_form.html");

		// Get existing bundle data
		$bundles = $this->M_Promo->getPromoBundles($promo_id);

		// Get products for selection
		$bakery_params = [0, 1000]; // offset, limit
		$bakery_products = $this->m_products->get_list_data($bakery_params, '', 'bakery');

		$kopitiam_params = [0, 1000]; // offset, limit
		$kopitiam_products = $this->m_products->get_list_data($kopitiam_params, '', 'kopitiam');

		$resto_params = [0, 1000]; // offset, limit
		$resto_products = $this->m_products->get_list_data($resto_params, '', 'resto');

		// Assign variables to template
		$this->tsmarty->assign('has_bundles', !empty($bundles));
		$this->tsmarty->assign('promo', $promo);
		$this->tsmarty->assign('bundles', $bundles);
		$this->tsmarty->assign('bakery_products', $bakery_products);
		$this->tsmarty->assign('kopitiam_products', $kopitiam_products);
		$this->tsmarty->assign('resto_products', $resto_products);
		$this->tsmarty->assign('form_action', site_url('promo/MasterPromo/saveBundling/' . $promo_id));

		// Output
		parent::display();
	}

	/**
	 * Menyimpan bundling promo
	 */
	public function saveBundling($promo_id)
	{
		// Check if request is POST
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			redirect('promo/MasterPromo/index');
		}

		// Check if promo exists
		$promo = $this->M_Promo->getPromoById($promo_id);
		if (!$promo) {
			$this->session->set_flashdata('error', 'Promo tidak ditemukan');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Get bundle data from POST
		$required_product_ids1 = $this->input->post('required_product_id1');
		$required_product_ids2 = $this->input->post('required_product_id2');
		$free_product_ids = $this->input->post('free_product_id');
		$min_quantities1 = $this->input->post('min_quantity1');
		$min_quantities2 = $this->input->post('min_quantity2');
		$free_quantities = $this->input->post('free_quantity');

		// Validate inputs
		if (empty($required_product_ids1) || empty($required_product_ids2) || empty($free_product_ids)) {
			$this->session->set_flashdata('error', 'Data bundle tidak lengkap');
			redirect('promo/MasterPromo/bundling/' . $promo_id);
			return;
		}

		// Prepare bundle data
		$bundle_data = [];
		for ($i = 0; $i < count($required_product_ids1); $i++) {
			if (empty($required_product_ids1[$i]) || empty($required_product_ids2[$i]) || empty($free_product_ids[$i])) {
				continue; // Skip empty entries
			}

			$bundle_data[] = [
				'required_product_id1' => $required_product_ids1[$i],
				'required_product_id2' => $required_product_ids2[$i],
				'free_product_id' => $free_product_ids[$i],
				'min_quantity1' => !empty($min_quantities1[$i]) ? $min_quantities1[$i] : 1,
				'min_quantity2' => !empty($min_quantities2[$i]) ? $min_quantities2[$i] : 1,
				'free_quantity' => !empty($free_quantities[$i]) ? $free_quantities[$i] : 1
			];
		}

		// Update promo type to bundling if not already
		$this->db->where('promo_id', $promo_id);
		$this->db->update('promos', ['promo_type' => 'bundling']);

		// Save bundles
		$result = $this->M_Promo->addPromoBundles($promo_id, $bundle_data);

		if ($result['success']) {
			$this->session->set_flashdata('success', 'Bundle promo berhasil disimpan');
			redirect('promo/MasterPromo/index');
		} else {
			$this->session->set_flashdata('error', $result['message']);
			redirect('promo/MasterPromo/bundling/' . $promo_id);
		}
	}

	/**
	 * Menampilkan form BOGO promo
	 */
	public function bogo($promo_id = null)
	{
		// Check if promo_id is provided
		if ($promo_id === null) {
			$this->session->set_flashdata('error', 'Promo ID tidak valid');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Get promo details
		$promo = $this->M_Promo->getPromoById($promo_id);

		if (!$promo) {
			$this->session->set_flashdata('error', 'Promo tidak ditemukan');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Set template content
		$this->tsmarty->assign("template_content", "promo/bogo_form.html");

		// Get existing BOGO data
		$bogos = $this->M_Promo->getPromoBogo($promo_id);

		// Get existing bundle data - penting untuk menghindari error "undefined index: has_bundles"
		$bundles = $this->M_Promo->getPromoBundles($promo_id);

		// Get products for selection
		$bakery_params = [0, 1000]; // offset, limit
		$bakery_products = $this->m_products->get_list_data($bakery_params, '', 'bakery');

		$kopitiam_params = [0, 1000]; // offset, limit
		$kopitiam_products = $this->m_products->get_list_data($kopitiam_params, '', 'kopitiam');

		$resto_params = [0, 1000]; // offset, limit
		$resto_products = $this->m_products->get_list_data($resto_params, '', 'resto');

		// Get categories for selection - penting untuk menghindari error kategori yang tidak terdefinisi
		$bakery_categories = $this->m_categories->get_list_category_by_brand('bakery');
		$kopitiam_categories = $this->m_categories->get_list_category_by_brand('kopitiam');
		$resto_categories = $this->m_categories->get_list_category_by_brand('resto');

		// Assign semua variabel yang diperlukan ke template
		$this->tsmarty->assign('promo', $promo);
		$this->tsmarty->assign('is_edit', true);
		$this->tsmarty->assign('bogos', $bogos ?: []);
		$this->tsmarty->assign('has_bogos', !empty($bogos));
		$this->tsmarty->assign('bundles', $bundles ?: []);
		$this->tsmarty->assign('has_bundles', !empty($bundles));
		$this->tsmarty->assign('bakery_products', $bakery_products);
		$this->tsmarty->assign('kopitiam_products', $kopitiam_products);
		$this->tsmarty->assign('resto_products', $resto_products);
		$this->tsmarty->assign('bakery_categories', $bakery_categories);
		$this->tsmarty->assign('kopitiam_categories', $kopitiam_categories);
		$this->tsmarty->assign('resto_categories', $resto_categories);
		$this->tsmarty->assign('form_action', site_url('promo/MasterPromo/saveBogo/' . $promo_id));
		$this->tsmarty->assign('bogo_url', site_url('promo/MasterPromo/bogo/' . $promo_id));
		$this->tsmarty->assign('bundling_url', site_url('promo/MasterPromo/bundling/' . $promo_id));

		// Output
		parent::display();
	}

	/**
	 * Menyimpan BOGO promo
	 */
	public function saveBogo($promo_id)
	{
		// Check if request is POST
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			redirect('promo/MasterPromo/index');
		}

		// Check if promo exists
		$promo = $this->M_Promo->getPromoById($promo_id);
		if (!$promo) {
			$this->session->set_flashdata('error', 'Promo tidak ditemukan');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Get BOGO data from POST
		$product_ids = $this->input->post('product_id');
		$buy_quantities = $this->input->post('buy_quantity');
		$free_quantities = $this->input->post('free_quantity');
		$free_product_ids = $this->input->post('free_product_id');
		$max_apply_counts = $this->input->post('max_apply_count');

		// Validate inputs
		if (empty($product_ids)) {
			$this->session->set_flashdata('error', 'Data BOGO tidak lengkap');
			redirect('promo/MasterPromo/bogo/' . $promo_id);
			return;
		}

		// Prepare BOGO data
		$bogo_data = [];
		for ($i = 0; $i < count($product_ids); $i++) {
			if (empty($product_ids[$i])) {
				continue; // Skip empty entries
			}

			// Pastikan free_product_ids[i] terdefinisi
			$free_product_id = isset($free_product_ids[$i]) && !empty($free_product_ids[$i]) ?
				$free_product_ids[$i] : $product_ids[$i];

			$bogo_data[] = [
				'product_id' => $product_ids[$i],
				'buy_quantity' => !empty($buy_quantities[$i]) ? $buy_quantities[$i] : 1,
				'free_quantity' => !empty($free_quantities[$i]) ? $free_quantities[$i] : 1,
				'free_product_id' => $free_product_id,
				'max_apply_count' => !empty($max_apply_counts[$i]) ? $max_apply_counts[$i] : null
			];
		}

		// Update promo type to BOGO if not already
		$this->db->where('promo_id', $promo_id);
		$this->db->update('promos', ['promo_type' => 'bogo']);

		// Save BOGOs
		$result = $this->M_Promo->addPromoBogo($promo_id, $bogo_data);

		if ($result['success']) {
			$this->session->set_flashdata('success', 'Promo BOGO berhasil disimpan');
			redirect('promo/MasterPromo/index');
		} else {
			$this->session->set_flashdata('error', $result['message']);
			redirect('promo/MasterPromo/bogo/' . $promo_id);
		}
	}

	/**
	 * Modifikasi edit() untuk menambahkan tab BOGO
	 */
	public function edit($promo_id = null)
	{
		// Check if promo_id is provided
		if ($promo_id === null) {
			$this->session->set_flashdata('error', 'Promo ID tidak valid');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Get promo details
		$promo = $this->M_Promo->getPromoById($promo_id);

		if (!$promo) {
			$this->session->set_flashdata('error', 'Promo tidak ditemukan');
			redirect('promo/MasterPromo/index');
			return;
		}

		$brand = $promo['promo_brand'];

		// Set template content
		$this->tsmarty->assign("template_content", "promo/form.html");

		// Format dates for the datetime-local inputs
		$promo['start_date_formatted'] = date('Y-m-d\TH:i', strtotime($promo['start_date']));
		$promo['end_date_formatted'] = date('Y-m-d\TH:i', strtotime($promo['end_date']));

		// Get associated products
		$promo_products = $this->M_Promo->getPromoProducts($promo_id);
		$product_ids = array_column($promo_products, 'product_id');
		$this->tsmarty->assign('product_ids', $product_ids);
		$this->tsmarty->assign('product_specific', empty($product_ids) ? 'no' : 'yes');

		// Get associated categories
		$promo_categories = $this->M_Promo->getPromoCategories($promo_id);
		$category_ids = array_column($promo_categories, 'cat_id');
		$this->tsmarty->assign('category_ids', $category_ids);
		$this->tsmarty->assign('category_specific', empty($category_ids) ? 'no' : 'yes');

		// Get bundles for this promo
		$bundles = $this->M_Promo->getPromoBundles($promo_id);
		$this->tsmarty->assign('bundles', $bundles);
		$this->tsmarty->assign('has_bundles', !empty($bundles)); // Tambahkan ini
		$this->tsmarty->assign('bundling_url', site_url('promo/MasterPromo/bundling/' . $promo_id));

		// Get BOGOs for this promo
		$bogos = $this->M_Promo->getPromoBogo($promo_id);
		$this->tsmarty->assign('bogos', $bogos);
		$this->tsmarty->assign('has_bogos', !empty($bogos)); // Tambahkan ini
		$this->tsmarty->assign('bogo_url', site_url('promo/MasterPromo/bogo/' . $promo_id));

		// Get products for selection - menggunakan get_list_data bukan getAll
		$bakery_params = [0, 1000]; // offset, limit
		$bakery_products = $this->m_products->get_list_data($bakery_params, '', 'bakery');

		$kopitiam_params = [0, 1000]; // offset, limit
		$kopitiam_products = $this->m_products->get_list_data($kopitiam_params, '', 'kopitiam');

		$resto_params = [0, 1000]; // offset, limit
		$resto_products = $this->m_products->get_list_data($resto_params, '', 'resto');

		$products = $this->getProductsByBrand($brand);
		$categories = $this->getCategoriesByBrand($brand);

		// Get categories for selection
		$bakery_categories = $this->m_categories->get_list_category_by_brand('bakery');
		$kopitiam_categories = $this->m_categories->get_list_category_by_brand('kopitiam');
		$resto_categories = $this->m_categories->get_list_category_by_brand('resto');

		// Get usage count for display
		$usage_count = $this->M_Promo->getPromoUsageCount($promo_id);
		$remaining_quota = $promo['quota'] !== null ? $promo['quota'] - $promo['usage_count'] : null;

		// Assign variables to template
		$this->tsmarty->assign('promo', $promo);
		$this->tsmarty->assign('is_edit', true);
		$this->tsmarty->assign('usage_count', $usage_count);
		$this->tsmarty->assign('remaining_quota', $remaining_quota);

		$this->tsmarty->assign('bakery_products', $products['bakery_products']);
		$this->tsmarty->assign('kopitiam_products', $products['kopitiam_products']);
		$this->tsmarty->assign('resto_products', $products['resto_products']);

		$this->tsmarty->assign('bakery_categories', $categories['bakery_categories']);
		$this->tsmarty->assign('kopitiam_categories', $categories['kopitiam_categories']);
		$this->tsmarty->assign('resto_categories', $categories['resto_categories']);

		$this->tsmarty->assign('form_action', site_url('promo/MasterPromo/update/' . $promo_id));
		$this->tsmarty->assign('bundling_url', site_url('promo/MasterPromo/bundling/' . $promo_id));
		$this->tsmarty->assign('bogo_url', site_url('promo/MasterPromo/bogo/' . $promo_id));

		// Output
		parent::display();
	}

	public function getProductsAndCategories()
	{
		// Set content type ke JSON
		$this->output->set_content_type('application/json');

		// Ambil brand dari input
		$brand = $this->input->post('brand');

		if (!$brand) {
			return $this->output->set_output(json_encode([
				'success' => false,
				'message' => 'Brand tidak valid'
			]));
		}

		// Dapatkan produk dan kategori
		$products = $this->getProductsByBrand($brand);
		$categories = $this->getCategoriesByBrand($brand);

		// Gabungkan hasil
		$result = [
			'success' => true,
			'bakery_products' => $products['bakery_products'],
			'kopitiam_products' => $products['kopitiam_products'],
			'resto_products' => $products['resto_products'],
			'bakery_categories' => $categories['bakery_categories'],
			'kopitiam_categories' => $categories['kopitiam_categories'],
			'resto_categories' => $categories['resto_categories']
		];

		// Kembalikan dalam format JSON
		return $this->output->set_output(json_encode($result));
	}

	/**
	 * API untuk pencarian produk (AJAX)
	 */
	public function searchProducts()
	{
		$term = $this->input->get('term');
		$brand = $this->input->get('brand');
		$limit = 20;

		if (empty($term)) {
			echo json_encode([]);
			return;
		}

		$params = [
			'search' => $term,
			'limit' => $limit
		];

		if (!empty($brand) && $brand != 'all') {
			$params['brand'] = $brand;
		}

		$products = $this->m_products->search_products($params);

		$result = [];
		foreach ($products as $product) {
			$result[] = [
				'id' => $product['product_id'],
				'text' => $product['product_name'],
				'brand' => $product['product_brand']
			];
		}

		echo json_encode(['results' => $result]);
	}

	/**
	 * API untuk pencarian kategori (AJAX)
	 */
	public function searchCategories()
	{
		$term = $this->input->get('term');
		$brand = $this->input->get('brand');
		$limit = 20;

		if (empty($term)) {
			echo json_encode([]);
			return;
		}

		$params = [
			'search' => $term,
			'limit' => $limit
		];

		if (!empty($brand) && $brand != 'all') {
			$params['brand'] = $brand;
		}

		$categories = $this->m_categories->search_categories($params);

		$result = [];
		foreach ($categories as $category) {
			$result[] = [
				'id' => $category['cat_id'],
				'text' => $category['cat_name'],
				'brand' => $category['cat_brand']
			];
		}

		echo json_encode(['results' => $result]);
	}

	private function getProductsByBrand($brand = null)
	{
		$bakery_params = [0, 1000];
		$kopitiam_params = [0, 1000];
		$resto_params = [0, 1000];

		if ($brand) {
			switch ($brand) {
				case 'bakery':
					return [
						'bakery_products' => $this->m_products->get_list_data($bakery_params, '', 'bakery'),
						'kopitiam_products' => [],
						'resto_products' => []
					];
				case 'kopitiam':
					return [
						'bakery_products' => [],
						'kopitiam_products' => $this->m_products->get_list_data($kopitiam_params, '', 'kopitiam'),
						'resto_products' => []
					];
				case 'resto':
					return [
						'bakery_products' => [],
						'kopitiam_products' => [],
						'resto_products' => $this->m_products->get_list_data($resto_params, '', 'resto')
					];
			}
		}

		// Jika tidak ada brand spesifik, kembalikan semua produk
		return [
			'bakery_products' => $this->m_products->get_list_data($bakery_params, '', 'bakery'),
			'kopitiam_products' => $this->m_products->get_list_data($kopitiam_params, '', 'kopitiam'),
			'resto_products' => $this->m_products->get_list_data($resto_params, '', 'resto')
		];
	}
	private function getCategoriesByBrand($brand = null)
	{
		if ($brand) {
			switch ($brand) {
				case 'bakery':
					return [
						'bakery_categories' => $this->m_categories->get_list_category_by_brand('bakery'),
						'kopitiam_categories' => [],
						'resto_categories' => []
					];
				case 'kopitiam':
					return [
						'bakery_categories' => [],
						'kopitiam_categories' => $this->m_categories->get_list_category_by_brand('kopitiam'),
						'resto_categories' => []
					];
				case 'resto':
					return [
						'bakery_categories' => [],
						'kopitiam_categories' => [],
						'resto_categories' => $this->m_categories->get_list_category_by_brand('resto')
					];
			}
		}

		return [
			'bakery_categories' => $this->m_categories->get_list_category_by_brand('bakery'),
			'kopitiam_categories' => $this->m_categories->get_list_category_by_brand('kopitiam'),
			'resto_categories' => $this->m_categories->get_list_category_by_brand('resto')
		];
	}


	/**
	 * Show create promo form
	 */
	public function create()
	{
		// Set template content
		$this->tsmarty->assign("template_content", "promo/form.html");

		// Set page title
		$this->tsmarty->assign('page_title', 'Tambah Promo Baru');

		$this->tsmarty->assign('is_edit', true);

		$brand = $this->input->post('promo_brand') ?: null;
		// Get products for selection - menggunakan get_list_data bukan getAll
		$bakery_params = [0, 1000]; // offset, limit
		$bakery_products = $this->m_products->get_list_data($bakery_params, '', 'bakery');

		$kopitiam_params = [0, 1000]; // offset, limit
		$kopitiam_products = $this->m_products->get_list_data($kopitiam_params, '', 'kopitiam');

		$resto_params = [0, 1000]; // offset, limit
		$resto_products = $this->m_products->get_list_data($resto_params, '', 'resto');

		$products = $this->getProductsByBrand($brand);
		$categories = $this->getCategoriesByBrand($brand);

		// Get categories for selection
		$bakery_categories = $this->m_categories->get_list_category_by_brand('bakery');
		$kopitiam_categories = $this->m_categories->get_list_category_by_brand('kopitiam');
		$resto_categories = $this->m_categories->get_list_category_by_brand('resto');

		$this->tsmarty->assign('bakery_products', $products['bakery_products']);
		$this->tsmarty->assign('kopitiam_products', $products['kopitiam_products']);
		$this->tsmarty->assign('resto_products', $products['resto_products']);

		$this->tsmarty->assign('bakery_categories', $categories['bakery_categories']);
		$this->tsmarty->assign('kopitiam_categories', $categories['kopitiam_categories']);
		$this->tsmarty->assign('resto_categories', $categories['resto_categories']);

		$this->tsmarty->assign('has_bundles', false);
		$this->tsmarty->assign('has_bogos', false);
		$this->tsmarty->assign('bundles', []);
		$this->tsmarty->assign('bogos', []);

		$this->tsmarty->assign('form_action', site_url('promo/MasterPromo/store'));

		// Output
		parent::display();
	}

	public function store()
	{
		// Check if request is POST
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			redirect('promo/MasterPromo/index');
		}

		// Tambahkan logging untuk debugging
		log_message('info', 'Store Promo - POST Data: ' . json_encode($this->input->post()));

		// Validasi form inputs
		$this->form_validation->set_rules('promo_code', 'Kode Promo', 'required|alpha_numeric|min_length[3]|max_length[20]');
		$this->form_validation->set_rules('promo_name', 'Nama Promo', 'required|min_length[3]|max_length[100]');
		$this->form_validation->set_rules('promo_brand', 'Brand', 'required|in_list[kopitiam,bakery,resto]');
		$this->form_validation->set_rules('start_date', 'Tanggal Mulai', 'required');
		$this->form_validation->set_rules('end_date', 'Tanggal Selesai', 'required|callback_check_end_date');
		$this->form_validation->set_rules('quota', 'Kuota', 'integer|greater_than[0]');
		$this->form_validation->set_rules('minimum_order', 'Minimum Order', 'numeric|greater_than_equal_to[0]');

		// Ambil tipe promo dari input dengan pengecekan tambahan
		$promo_type = $this->input->post('promo_type') ?: 'percentage';
		log_message('info', 'Promo Type: ' . $promo_type);

		// Untuk promo tipe diskon (percentage atau nominal), validasi nilai diskon
		if ($promo_type == 'percentage' || $promo_type == 'nominal') {
			$this->form_validation->set_rules('promo_value', 'Nilai Promo', 'required|numeric|greater_than[0]', [
				'required' => 'Kolom nilai ' . ($promo_type == 'percentage' ? 'persentase' : 'nominal') . ' tidak boleh kosong.',
				'numeric' => 'Kolom nilai ' . ($promo_type == 'percentage' ? 'persentase' : 'nominal') . ' harus berupa angka.',
				'greater_than' => 'Kolom nilai ' . ($promo_type == 'percentage' ? 'persentase' : 'nominal') . ' harus lebih besar dari 0.'
			]);

			// Validasi tambahan untuk persentase
			if ($promo_type == 'percentage') {
				$this->form_validation->set_rules('promo_value', 'Nilai Persentase', 'less_than_equal_to[100]', [
					'less_than_equal_to' => 'Kolom nilai persentase maksimal 100.'
				]);
				$this->form_validation->set_rules('maximum_discount', 'Maximum Discount', 'numeric|greater_than[0]');
			}

			$this->form_validation->set_rules('minimum_order', 'Minimum Order', 'numeric|greater_than_equal_to[0]');
		}

		if ($this->form_validation->run() === FALSE) {
			// Logging error validasi
			log_message('error', 'Promo Validation Error: ' . validation_errors());

			$this->session->set_flashdata('error', validation_errors());
			redirect('promo/MasterPromo/create');
		}

		log_message('debug', 'Store Promo - POST Data: ' . json_encode($this->input->post()));
		log_message('debug', 'Minimum Order Raw: ' . $this->input->post('minimum_order'));

		// Prepare data for insertion
		$data = [
			'promo_code' => strtoupper($this->input->post('promo_code')),
			'promo_name' => $this->input->post('promo_name'),
			'promo_brand' => $this->input->post('promo_brand'),
			'promo_type' => $promo_type,
			'start_date' => date('Y-m-d H:i:s', strtotime($this->input->post('start_date'))),
			'end_date' => date('Y-m-d H:i:s', strtotime($this->input->post('end_date'))),
			'quota' => $this->input->post('quota') ?: null,
			'description' => $this->input->post('description'),
			'promo_status' => $this->input->post('promo_status') ?: 'active',
			'minimum_order' => $this->input->post('minimum_order') !== '' && $this->input->post('minimum_order') !== null
				? max(0, floatval($this->input->post('minimum_order')))
				: 0
		];

		// Tambahkan logika khusus untuk tipe promo
		if ($promo_type == 'percentage' || $promo_type == 'nominal') {
			// Gunakan nilai dari promo_value yang dikirim melalui hidden input
			$data['promo_value'] = $this->input->post('promo_value');
			$data['minimum_order'] = $this->input->post('minimum_order') ?: 0;

			if ($promo_type == 'percentage') {
				$data['maximum_discount'] = $this->input->post('maximum_discount') ?: null;
			} else {
				$data['maximum_discount'] = null;
			}
		} else {
			// Default values untuk bundling dan bogo
			$data['promo_value'] = 0;
			$data['minimum_order'] = 0;
			$data['maximum_discount'] = null;
		}

		// Log data sebelum insert untuk debugging
		log_message('info', 'Promo Data to Insert: ' . json_encode($data));

		// Create promo
		$result = $this->M_Promo->createPromo($data);

		if ($result['success']) {
			log_message('info', 'Promo created successfully: ' . $result['promo_id']);
			$this->session->set_flashdata('success', 'Promo berhasil dibuat');

			// Redirect berdasarkan tipe promo
			if ($promo_type == 'bundling') {
				redirect('promo/MasterPromo/bundling/' . $result['promo_id']);
			} else if ($promo_type == 'bogo') {
				redirect('promo/MasterPromo/bogo/' . $result['promo_id']);
			} else {
				redirect('promo/MasterPromo/index');
			}
		} else {
			log_message('error', 'Promo creation failed: ' . $result['message']);
			$this->session->set_flashdata('error', $result['message']);
			redirect('promo/MasterPromo/create');
		}
	}

	/**
	 * Modifikasi update() untuk mendukung promo bundling dan BOGO
	 */
	public function update($promo_id)
	{
		// Check if request is POST
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			redirect('promo/MasterPromo/index');
		}

		$minimum_order = $this->input->post('minimum_order', TRUE);

		// Tambahkan logging untuk debugging
		log_message('info', 'Update Promo - POST Data: ' . json_encode($this->input->post()));

		// Get existing promo to check type
		$existing_promo = $this->M_Promo->getPromoById($promo_id);
		if (!$existing_promo) {
			$this->session->set_flashdata('error', 'Promo tidak ditemukan');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Validasi form inputs
		$this->form_validation->set_rules('promo_name', 'Nama Promo', 'required|min_length[3]|max_length[100]');
		$this->form_validation->set_rules('promo_brand', 'Brand', 'required|in_list[kopitiam,bakery,resto]');
		$this->form_validation->set_rules('start_date', 'Tanggal Mulai', 'required');
		$this->form_validation->set_rules('end_date', 'Tanggal Selesai', 'required|callback_check_end_date');
		$this->form_validation->set_rules('quota', 'Kuota', 'integer|greater_than[0]');
		$this->form_validation->set_rules('minimum_order', 'Minimum Order', 'numeric|greater_than_equal_to[0]');

		// Ambil tipe promo dari input (jika ada perubahan tipe)
		$promo_type = $this->input->post('promo_type');
		if (!$promo_type) {
			$promo_type = $existing_promo['promo_type'];
		}

		// Jika tipe promo berubah
		$type_changed = ($promo_type && $promo_type != $existing_promo['promo_type']);

		// Validasi khusus untuk tipe diskon (percentage atau nominal)
		if ($promo_type == 'percentage' || $promo_type == 'nominal') {
			// Hanya validasi promo_value jika tipe berubah atau jika nilai dikirimkan
			if ($type_changed || $this->input->post('promo_value') !== null) {
				$this->form_validation->set_rules('promo_value', 'Nilai Promo', 'required|numeric|greater_than[0]', [
					'required' => 'Kolom nilai ' . ($promo_type == 'percentage' ? 'persentase' : 'nominal') . ' tidak boleh kosong.',
					'numeric' => 'Kolom nilai ' . ($promo_type == 'percentage' ? 'persentase' : 'nominal') . ' harus berupa angka.',
					'greater_than' => 'Kolom nilai ' . ($promo_type == 'percentage' ? 'persentase' : 'nominal') . ' harus lebih besar dari 0.'
				]);

				// Validasi tambahan untuk persentase
				if ($promo_type == 'percentage') {
					$this->form_validation->set_rules('promo_value', 'Nilai Persentase', 'less_than_equal_to[100]', [
						'less_than_equal_to' => 'Kolom nilai persentase maksimal 100.'
					]);
					$this->form_validation->set_rules('maximum_discount', 'Maximum Discount', 'numeric|greater_than[0]');
				}
			}

			$this->form_validation->set_rules('minimum_order', 'Minimum Order', 'numeric|greater_than_equal_to[0]');
		}

		if ($this->form_validation->run() === FALSE) {
			// Validation failed, show form with errors
			log_message('error', 'Promo Update Validation Error: ' . validation_errors());
			$this->session->set_flashdata('error', validation_errors());
			redirect('promo/MasterPromo/edit/' . $promo_id);
		}

		log_message('debug', 'Update Promo - POST Data: ' . json_encode($this->input->post()));
		log_message('debug', 'Minimum Order Raw: ' . $this->input->post('minimum_order'));

		$data = [
			'promo_code' => $this->input->post('promo_code', TRUE),
			'promo_name' => $this->input->post('promo_name', TRUE),
			'promo_brand' => $this->input->post('promo_brand', TRUE),
			'description' => $this->input->post('description', TRUE),
			'start_date' => date('Y-m-d H:i:s', strtotime($this->input->post('start_date', TRUE))),
			'end_date' => date('Y-m-d H:i:s', strtotime($this->input->post('end_date', TRUE))),
			'quota' => $this->input->post('quota', TRUE) ?: null,
			'promo_status' => $this->input->post('promo_status', TRUE),
			'promo_value' => $this->input->post('promo_value', TRUE),
			'promo_type' => $this->input->post('promo_type', TRUE),
			// Perbaikan penanganan minimum_order
			'minimum_order' => $minimum_order !== '' && $minimum_order !== null
				? max(0, floatval($minimum_order))
				: 0
		];

		log_message('debug', 'Processed Minimum Order: ' . $data['minimum_order']);

		// Update tipe promo jika berubah
		if ($type_changed) {
			$data['promo_type'] = $promo_type;

			// Jika berubah menjadi bundling atau bogo, reset nilai diskon
			if ($promo_type == 'bundling' || $promo_type == 'bogo') {
				$data['promo_value'] = 0;
				$data['minimum_order'] = 0;
				$data['maximum_discount'] = null;
			}
			// Jika berubah dari bundling/bogo ke diskon, tambahkan nilai diskon
			else if ($existing_promo['promo_type'] == 'bundling' || $existing_promo['promo_type'] == 'bogo') {
				// Gunakan nilai dari promo_value yang dikirim melalui hidden input
				$data['promo_value'] = $this->input->post('promo_value');
				$data['minimum_order'] = $this->input->post('minimum_order') ?: 0;

				if ($promo_type == 'percentage') {
					$data['maximum_discount'] = $this->input->post('maximum_discount') ?: null;
				} else {
					$data['maximum_discount'] = null;
				}
			}
		}
		// Jika tipe tidak berubah dan bukan bundling/bogo, update nilai diskon
		else if ($existing_promo['promo_type'] != 'bundling' && $existing_promo['promo_type'] != 'bogo') {
			// Gunakan nilai dari promo_value yang dikirim melalui hidden input
			if ($this->input->post('promo_value') !== null) {
				$data['promo_value'] = $this->input->post('promo_value');
			}

			$data['minimum_order'] = $this->input->post('minimum_order') ?: 0;

			if ($existing_promo['promo_type'] == 'percentage') {
				$data['maximum_discount'] = $this->input->post('maximum_discount') ?: null;
			} else {
				$data['maximum_discount'] = null;
			}
		}

		// Handle product specific selections
		if ($this->input->post('product_specific') === 'yes') {
			$data['product_ids'] = $this->input->post('product_ids');
		} else {
			$data['product_ids'] = [];
		}

		// Handle category specific selections
		if ($this->input->post('category_specific') === 'yes') {
			$data['category_ids'] = $this->input->post('category_ids');
		} else {
			$data['category_ids'] = [];
		}

		// Log data sebelum update untuk debugging
		log_message('info', 'Promo Data to Update: ' . json_encode($data));

		// Update promo
		$result = $this->M_Promo->updatePromo($promo_id, $data);

		if ($result['success']) {
			$this->session->set_flashdata('success', 'Promo berhasil diperbarui');

			// Redirect berdasarkan tipe promo jika tipe berubah
			if ($type_changed) {
				if ($promo_type == 'bundling') {
					redirect('promo/MasterPromo/bundling/' . $promo_id);
				} else if ($promo_type == 'bogo') {
					redirect('promo/MasterPromo/bogo/' . $promo_id);
				} else {
					redirect('promo/MasterPromo/index');
				}
			} else {
				redirect('promo/MasterPromo/index');
			}
		} else {
			$this->session->set_flashdata('error', $result['message']);
			redirect('promo/MasterPromo/edit/' . $promo_id);
		}
	}

	/**
	 * Show promo usage
	 */
	public function usage($promo_id = null)
	{
		// Jika promo_id tidak diberikan, redirect atau tampilkan error
		if ($promo_id === null) {
			$this->session->set_flashdata('error', 'Promo ID tidak valid');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Set page title
		$this->tsmarty->assign('page_title', 'Riwayat Penggunaan Promo');

		// Get promo details
		$promo = $this->M_Promo->getPromoById($promo_id);

		if (!$promo) {
			$this->session->set_flashdata('error', 'Promo tidak ditemukan');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Get BOGO configuration if promo type is BOGO
		$bogos = [];
		if ($promo['promo_type'] == 'bogo') {
			$bogos = $this->M_Promo->getPromoBogo($promo_id);

			// Tambahkan pengecekan dan default values untuk bogos
			$bogos = array_map(function ($bogo) {
				$bogo['product_brand'] = $bogo['product_brand'] ?? 'tidak diketahui';
				$bogo['free_product_brand'] = $bogo['free_product_brand'] ?? 'tidak diketahui';
				return $bogo;
			}, $bogos);
		}

		// Get bundle configuration if promo type is bundling
		$bundles = [];
		if ($promo['promo_type'] == 'bundling') {
			$bundles = $this->M_Promo->getPromoBundles($promo_id);

			// Tambahkan pengecekan dan default values untuk bundles
			$bundles = array_map(function ($bundle) {
				$bundle['required_product_brand1'] = $bundle['required_product_brand1'] ?? 'tidak diketahui';
				$bundle['required_product_brand2'] = $bundle['required_product_brand2'] ?? 'tidak diketahui';
				$bundle['free_product_brand'] = $bundle['free_product_brand'] ?? 'tidak diketahui';
				return $bundle;
			}, $bundles);
		}

		// Get promo products with safe brand handling
		$promo_products = $this->M_Promo->getPromoProducts($promo_id);
		$promo_products = array_map(function ($product) {
			$product['product_brand'] = $product['product_brand'] ?? 'tidak diketahui';
			return $product;
		}, $promo_products);

		// Get promo categories with safe brand handling
		$promo_categories = $this->M_Promo->getPromoCategories($promo_id);
		$promo_categories = array_map(function ($category) {
			$category['cat_brand'] = $category['cat_brand'] ?? 'tidak diketahui';
			return $category;
		}, $promo_categories);

		// Format dates for display
		$promo['formatted_start_date'] = date('d M Y H:i', strtotime($promo['start_date']));
		$promo['formatted_end_date'] = date('d M Y H:i', strtotime($promo['end_date']));

		// Calculate remaining quota
		$promo['remaining_quota'] = $promo['quota'] !== null
			? max(0, $promo['quota'] - $promo['usage_count'])
			: 'Unlimited';

		// Setup pagination
		$page = $this->input->get('page') ?: 1;
		$limit = 20;
		$offset = ($page - 1) * $limit;

		// Get usage history
		$usage_history = $this->M_Promo->getPromoUsage($promo_id, [
			'limit' => $limit,
			'offset' => $offset
		]);

		// Tambahkan penanganan untuk usage_history
		$usage_history = array_map(function ($usage) {
			// Pastikan semua kunci yang dibutuhkan ada
			$usage['customer_name'] = $usage['customer_name'] ?? 'Tidak Diketahui';
			$usage['table_id'] = $usage['table_id'] ?? 'N/A';
			return $usage;
		}, $usage_history);

		$total_usage = $this->M_Promo->getPromoUsageCount($promo_id);

		// Get usage statistics
		$stats = $this->M_Promo->getPromoUsageStats($promo_id);

		// Setup pagination config
		$config = [
			'base_url' => site_url('promo/MasterPromo/usage/' . $promo_id),
			'total_rows' => $total_usage,
			'per_page' => $limit,
			'use_page_numbers' => TRUE,
			'page_query_string' => TRUE,
			'query_string_segment' => 'page',
			'full_tag_open' => '<ul class="pagination pagination-sm mb-0">',
			'full_tag_close' => '</ul>',
			'num_tag_open' => '<li class="page-item">',
			'num_tag_close' => '</li>',
			'cur_tag_open' => '<li class="page-item active"><a class="page-link" href="#">',
			'cur_tag_close' => '</a></li>',
			'next_tag_open' => '<li class="page-item">',
			'next_tag_close' => '</li>',
			'prev_tag_open' => '<li class="page-item">',
			'prev_tag_close' => '</li>',
			'first_tag_open' => '<li class="page-item">',
			'first_tag_close' => '</li>',
			'last_tag_open' => '<li class="page-item">',
			'last_tag_close' => '</li>',
			'next_link' => '<i class="fas fa-chevron-right"></i>',
			'prev_link' => '<i class="fas fa-chevron-left"></i>',
			'first_link' => '<i class="fas fa-step-backward"></i>',
			'last_link' => '<i class="fas fa-step-forward"></i>',
			'attributes' => ['class' => 'page-link']
		];

		$this->pagination->initialize($config);

		// Add order URLs to usage history
		$usage_history = array_map(function ($item) {
			$item['order_url'] = site_url('orders/detail/' . $item['order_id']);
			return $item;
		}, $usage_history);

		// Assign variables to template dengan pengecekan tambahan
		$this->tsmarty->assign('promo', $promo);
		$this->tsmarty->assign('usage_history', $usage_history);
		$this->tsmarty->assign('total_usage', $total_usage);
		$this->tsmarty->assign('pagination', $this->pagination->create_links());
		$this->tsmarty->assign('total_discount', $stats['total_discount'] ?? 0);
		$this->tsmarty->assign('avg_discount', $stats['avg_discount'] ?? 0);
		$this->tsmarty->assign('promo_products', $promo_products);
		$this->tsmarty->assign('promo_categories', $promo_categories);
		$this->tsmarty->assign('bundles', $bundles);
		$this->tsmarty->assign('bogos', $bogos);

		// Render template
		$this->tsmarty->assign('template_content', 'promo/usage.html');
		parent::display();
	}

	/**
	 * Get bundling details for ajax request
	 */
	public function getBundlingDetails($promo_id)
	{
		// Set content type to JSON
		$this->output->set_content_type('application/json');

		if (!$promo_id) {
			return $this->output->set_output(json_encode([
				'success' => false,
				'message' => 'Promo ID tidak valid'
			]));
		}

		// Get promo
		$promo = $this->M_Promo->getPromoById($promo_id);
		if (!$promo) {
			return $this->output->set_output(json_encode([
				'success' => false,
				'message' => 'Promo tidak ditemukan'
			]));
		}

		// Get bundles
		$bundles = $this->M_Promo->getPromoBundles($promo_id);

		// Return JSON response
		return $this->output->set_output(json_encode([
			'success' => true,
			'promo' => $promo,
			'bundles' => $bundles
		]));
	}

	/**
	 * Get BOGO details for ajax request
	 */
	public function getBogoDetails($promo_id)
	{
		// Set content type to JSON
		$this->output->set_content_type('application/json');

		if (!$promo_id) {
			return $this->output->set_output(json_encode([
				'success' => false,
				'message' => 'Promo ID tidak valid'
			]));
		}

		// Get promo
		$promo = $this->M_Promo->getPromoById($promo_id);
		if (!$promo) {
			return $this->output->set_output(json_encode([
				'success' => false,
				'message' => 'Promo tidak ditemukan'
			]));
		}

		// Get BOGO rules
		$bogos = $this->M_Promo->getPromoBogo($promo_id);

		// Return JSON response
		return $this->output->set_output(json_encode([
			'success' => true,
			'promo' => $promo,
			'bogos' => $bogos
		]));
	}

	/**
	 * Custom validation callback to check end date is after start date
	 */
	public function check_end_date($end_date)
	{
		$start_date = $this->input->post('start_date');

		if (strtotime($end_date) <= strtotime($start_date)) {
			$this->form_validation->set_message('check_end_date', 'Tanggal Selesai harus setelah Tanggal Mulai');
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * AJAX endpoint to validate promo code
	 */
	public function validatePromoCode()
	{
		// Set content type to JSON
		$this->output->set_content_type('application/json');

		// Get parameters
		$promo_code = $this->input->post('promo_code');
		$order_total = $this->input->post('order_total');
		$brand = $this->input->post('brand');

		if (!$promo_code || !$order_total || !$brand) {
			return $this->output->set_output(json_encode([
				'success' => false,
				'message' => 'Parameter yang diperlukan tidak lengkap'
			]));
		}

		// Validate promo
		$result = $this->M_Promo->validatePromo($promo_code, $brand, $order_total);

		// Return result
		return $this->output->set_output(json_encode($result));
	}

	/**
	 * Export promos to CSV
	 */
	public function export()
	{
		// Get filter parameters
		$brand = $this->input->get('brand') ?: 'all';
		$status = $this->input->get('status') ?: 'all';
		$search = $this->input->get('search') ?: '';

		// Prepare filter params for model
		$params = [
			'search' => $search
		];

		if ($status !== 'all') {
			$params['promo_status'] = $status;
		}

		if ($brand !== 'all') {
			$params['promo_brand'] = $brand;
		}

		// Get all promos based on filters
		$promos = $this->M_Promo->getAllPromos($params);

		// Set content type for CSV download
		$this->output->set_content_type('application/csv');
		$this->output->set_header('Content-Disposition: attachment; filename="promos_export_' . date('Y-m-d') . '.csv"');

		// Create CSV data
		$output = fopen('php://output', 'w');

		// Add CSV headers
		fputcsv($output, [
			'Kode Promo',
			'Nama Promo',
			'Brand',
			'Tipe',
			'Nilai',
			'Minimum Order',
			'Maximum Discount',
			'Tanggal Mulai',
			'Tanggal Selesai',
			'Kuota',
			'Jumlah Penggunaan',
			'Status',
			'Deskripsi'
		]);

		// Add promo data rows
		foreach ($promos as $promo) {
			fputcsv($output, [
				$promo['promo_code'],
				$promo['promo_name'],
				$promo['promo_brand'],
				$promo['promo_type'],
				$promo['promo_value'],
				$promo['minimum_order'],
				$promo['maximum_discount'] ?: 'Tidak Ada',
				$promo['start_date'],
				$promo['end_date'],
				$promo['quota'] ?: 'Tidak Terbatas',
				$promo['usage_count'],
				$promo['promo_status'] == 'active' ? 'Aktif' : 'Non-aktif',
				$promo['description']
			]);
		}

		fclose($output);
	}

	/**
	 * Bulk action for promos
	 */
	public function bulkAction()
	{
		// Check if request is POST
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			redirect('promo/MasterPromo/index');
		}

		$action = $this->input->post('bulk_action');
		$promo_ids = $this->input->post('promo_ids');

		if (empty($promo_ids) || !is_array($promo_ids)) {
			$this->session->set_flashdata('error', 'Tidak ada promo yang dipilih');
			redirect('promo/MasterPromo/index');
		}

		$success_count = 0;
		$failed_count = 0;

		// Process bulk action
		foreach ($promo_ids as $promo_id) {
			if ($action === 'activate') {
				$result = $this->M_Promo->updatePromo($promo_id, ['promo_status' => 'active']);
				if ($result['success']) {
					$success_count++;
				} else {
					$failed_count++;
				}
			} else if ($action === 'deactivate') {
				$result = $this->M_Promo->updatePromo($promo_id, ['promo_status' => 'inactive']);
				if ($result['success']) {
					$success_count++;
				} else {
					$failed_count++;
				}
			} else if ($action === 'delete') {
				$result = $this->M_Promo->deletePromo($promo_id);
				if ($result['success']) {
					$success_count++;
				} else {
					$failed_count++;
				}
			}
		}

		// Set flash message
		if ($success_count > 0) {
			$action_text = '';
			if ($action === 'activate') {
				$action_text = 'diaktifkan';
			} else if ($action === 'deactivate') {
				$action_text = 'dinonaktifkan';
			} else if ($action === 'delete') {
				$action_text = 'dihapus';
			}

			$this->session->set_flashdata('success', "{$success_count} promo berhasil {$action_text}");
		}

		if ($failed_count > 0) {
			$this->session->set_flashdata('error', "{$failed_count} promo gagal diproses");
		}

		redirect('promo/MasterPromo/index');
	}

	/**
	 * Clone an existing promo
	 */
	public function clonePromo($promo_id)
	{
		// Get promo details
		$promo = $this->M_Promo->getPromoById($promo_id);

		if (!$promo) {
			$this->session->set_flashdata('error', 'Promo tidak ditemukan');
			redirect('promo/MasterPromo/index');
		}

		// Get associated products
		$promo_products = $this->M_Promo->getPromoProducts($promo_id);
		$product_ids = array_column($promo_products, 'product_id');

		// Get associated categories
		$promo_categories = $this->M_Promo->getPromoCategories($promo_id);
		$category_ids = array_column($promo_categories, 'cat_id');

		// Prepare new promo data
		$new_promo = [
			'promo_code' => $promo['promo_code'] . '_COPY',
			'promo_name' => $promo['promo_name'] . ' (Copy)',
			'promo_brand' => $promo['promo_brand'],
			'promo_type' => $promo['promo_type'],
			'promo_value' => $promo['promo_value'],
			'minimum_order' => $promo['minimum_order'],
			'maximum_discount' => $promo['maximum_discount'],
			'start_date' => date('Y-m-d H:i:s'), // Set to current date
			'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')), // Set end date to 30 days from now
			'quota' => $promo['quota'],
			'description' => $promo['description'],
			'promo_status' => 'inactive', // Default to inactive
		];

		// Add product and category IDs if any
		if (!empty($product_ids)) {
			$new_promo['product_ids'] = $product_ids;
		}

		if (!empty($category_ids)) {
			$new_promo['category_ids'] = $category_ids;
		}

		// Create the new promo
		$result = $this->M_Promo->createPromo($new_promo);

		if ($result['success']) {
			$this->session->set_flashdata('success', 'Promo berhasil diduplikasi. Silakan edit detail promo baru.');
			redirect('promo/MasterPromo/edit/' . $result['promo_id']);
		} else {
			$this->session->set_flashdata('error', 'Gagal menduplikasi promo: ' . $result['message']);
			redirect('promo/MasterPromo/index');
		}
	}
	public function delete($promo_id = null)
	{
		// Check if promo_id is provided
		if ($promo_id === null) {
			$this->session->set_flashdata('error', 'Promo ID tidak valid');
			redirect('promo/MasterPromo/index');
			return;
		}

		// Delete promo using model
		$result = $this->M_Promo->deletePromo($promo_id);

		if ($result['success']) {
			$this->session->set_flashdata('success', $result['message']);
		} else {
			$this->session->set_flashdata('error', $result['message']);
		}

		redirect('promo/MasterPromo/index');
	}

	/**
	 * Modifikasi fungsi testPromo untuk mendukung pengujian bundling dan BOGO
	 */
	public function testPromo()
	{
		// Set page title
		$this->tsmarty->assign('page_title', 'Uji Kalkulasi Promo');

		// Get promos for selection
		$params = [
			'promo_status' => 'active',
			'active_now' => true
		];

		// Get brand specific promos
		$bakery_promos = $this->M_Promo->getAllPromos(array_merge($params, ['promo_brand' => 'bakery']));
		$kopitiam_promos = $this->M_Promo->getAllPromos(array_merge($params, ['promo_brand' => 'kopitiam']));
		$resto_promos = $this->M_Promo->getAllPromos(array_merge($params, ['promo_brand' => 'resto']));

		// Count promo by types
		$bakery_discount_count = 0;
		$resto_discount_count = 0;
		$kopitiam_discount_count = 0;
		$bakery_bundling_count = 0;
		$resto_bundling_count = 0;
		$kopitiam_bundling_count = 0;
		$bakery_bogo_count = 0;
		$resto_bogo_count = 0;
		$kopitiam_bogo_count = 0;

		// Count promos by type
		foreach ($bakery_promos as $promo) {
			if ($promo['promo_type'] == 'bundling') {
				$bakery_bundling_count++;
			} else if ($promo['promo_type'] == 'bogo') {
				$bakery_bogo_count++;
			} else {
				$bakery_discount_count++;
			}
		}

		foreach ($resto_promos as $promo) {
			if ($promo['promo_type'] == 'bundling') {
				$resto_bundling_count++;
			} else if ($promo['promo_type'] == 'bogo') {
				$resto_bogo_count++;
			} else {
				$resto_discount_count++;
			}
		}

		foreach ($kopitiam_promos as $promo) {
			if ($promo['promo_type'] == 'bundling') {
				$kopitiam_bundling_count++;
			} else if ($promo['promo_type'] == 'bogo') {
				$kopitiam_bogo_count++;
			} else {
				$kopitiam_discount_count++;
			}
		}

		// Get calculation result if form submitted
		$result = null;
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$promo_code = $this->input->post('promo_code');
			$brand = $this->input->post('brand');
			$promo_type = $this->input->post('promo_type');

			if ($promo_code && $brand) {
				// Get promo details
				$promo = $this->M_Promo->getPromoByCode($promo_code);

				if (!$promo) {
					$result = [
						'success' => false,
						'message' => 'Promo tidak ditemukan'
					];
				} else {
					// Check promo type
					if ($promo_type == 'discount' || !in_array($promo['promo_type'], ['bundling', 'bogo'])) {
						// Test discount promo
						$order_total = $this->input->post('order_total');
						$result = $this->M_Promo->calculateDiscount($promo_code, $order_total, $brand);
					} else if ($promo_type == 'bundling') {
						// Test bundling promo
						$cart_items = $this->input->post('cart_items');

						if (empty($cart_items)) {
							$result = [
								'eligible' => false,
								'message' => 'Keranjang belanja kosong',
								'promo_code' => $promo_code
							];
						} else {
							// Format cart items for model
							$formatted_cart = [];
							foreach ($cart_items as $item) {
								$formatted_cart[] = [
									'product_id' => $item['product_id'],
									'quantity' => $item['quantity']
								];
							}

							// Check bundle eligibility
							$result = $this->M_Promo->checkBundleEligibility($promo['promo_id'], $formatted_cart);
							$result['promo_code'] = $promo_code;
						}
					} else if ($promo_type == 'bogo') {
						// Test BOGO promo
						$cart_items = $this->input->post('bogo_cart_items');

						if (empty($cart_items)) {
							$result = [
								'eligible' => false,
								'message' => 'Keranjang belanja kosong',
								'promo_code' => $promo_code
							];
						} else {
							// Format cart items for model
							$formatted_cart = [];
							foreach ($cart_items as $item) {
								$formatted_cart[] = [
									'product_id' => $item['product_id'],
									'quantity' => $item['quantity']
								];
							}

							// Check BOGO eligibility
							$result = $this->M_Promo->checkBogoEligibility($promo['promo_id'], $formatted_cart);
							$result['promo_code'] = $promo_code;
						}
					}
				}
			}
		}

		// Get products for cart simulation
		$bakery_params = [0, 1000]; // offset, limit
		$bakery_products = $this->m_products->get_list_data($bakery_params, '', 'bakery');

		$kopitiam_params = [0, 1000]; // offset, limit
		$kopitiam_products = $this->m_products->get_list_data($kopitiam_params, '', 'kopitiam');

		$resto_params = [0, 1000]; // offset, limit
		$resto_products = $this->m_products->get_list_data($resto_params, '', 'resto');

		// Assign variables to template
		$this->tsmarty->assign('bakery_promos', $bakery_promos);
		$this->tsmarty->assign('kopitiam_promos', $kopitiam_promos);
		$this->tsmarty->assign('resto_promos', $resto_promos);
		$this->tsmarty->assign('bakery_products', $bakery_products);
		$this->tsmarty->assign('kopitiam_products', $kopitiam_products);
		$this->tsmarty->assign('resto_products', $resto_products);

		// Assign promo type counts
		$this->tsmarty->assign('bakery_discount_count', $bakery_discount_count);
		$this->tsmarty->assign('resto_discount_count', $resto_discount_count);
		$this->tsmarty->assign('kopitiam_discount_count', $kopitiam_discount_count);
		$this->tsmarty->assign('bakery_bundling_count', $bakery_bundling_count);
		$this->tsmarty->assign('resto_bundling_count', $resto_bundling_count);
		$this->tsmarty->assign('kopitiam_bundling_count', $kopitiam_bundling_count);
		$this->tsmarty->assign('bakery_bogo_count', $bakery_bogo_count);
		$this->tsmarty->assign('resto_bogo_count', $resto_bogo_count);
		$this->tsmarty->assign('kopitiam_bogo_count', $kopitiam_bogo_count);

		$this->tsmarty->assign('result', $result);
		$this->tsmarty->assign('post_data', $_POST);

		// Render template
		$this->tsmarty->assign('template_content', 'promo/test.html');
		parent::display();
	}
}
