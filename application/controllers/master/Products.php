<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PrivateBase.php');

// --
class Products extends ApplicationBase
{

	// constructor
	public function __construct()
	{
		// parent constructor
		parent::__construct();
		// load model
		$this->load->model('master/M_products', 'm_products');
		$this->load->model('master/M_categories', 'm_categories');
		$this->load->model('master/M_brands', 'm_brands');
		$this->load->model('master/M_marketings', 'm_marketings');
		// load product API client
		$this->load->library('Products_api');
	}

	// index
	public function index()
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/products/index.html");
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
		$this->tsmarty->assign("keyword", $keyword);
		// load library
		$this->load->library('pagination');
		// pagination
		$config['base_url'] = site_url('master/products/index/');
		$config['total_rows'] = $this->m_products->get_total_data($keyword);
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
		$data = $this->m_products->get_list_data($params, $keyword);
		$this->tsmarty->assign("datas", $data);
		// output
		parent::display();
	}

	// DETAIL, ADD, EDIT, DELETE

	//detail
	public function detail($product_id = '')
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/products/detail.html");
		// detail product
		$detail = $this->m_products->get_detail_product($product_id);
		$this->tsmarty->assign('detail', $detail);

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
		$this->tsmarty->assign("keyword", $keyword);
		// load library
		$this->load->library('pagination');
		// pagination
		$config['base_url'] = site_url('master/products/detail/' . $product_id . '/');
		$config['total_rows'] = $this->m_products->get_total_data_varian($product_id, $keyword);
		$config['uri_segment'] = 5;
		$config['per_page'] = 10;
		$this->pagination->initialize($config);
		$pagination['data'] = $this->pagination->create_links();
		// pagination attribute
		$start = $this->uri->segment(5, 0) + 1;
		$end = $this->uri->segment(5, 0) + $config['per_page'];
		$end = (($end > $config['total_rows']) ? $config['total_rows'] : $end);
		$pagination['start'] = ($config['total_rows'] == 0) ? 0 : $start;
		$pagination['end'] = $end;
		$pagination['total'] = $config['total_rows'];
		// pagination assign value
		$this->tsmarty->assign("pagination", $pagination);
		$this->tsmarty->assign("no", $start);
		/* end of pagination ---------------------- */
		// get list data
		$params = array($product_id, ($start - 1), $config['per_page']);
		$data = $this->m_products->get_list_data_varian($params, $keyword);
		$this->tsmarty->assign("datas", $data);
		// output
		parent::display();
	}

	// add
	public function add()
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/products/add.html");

		// Get flash message
		$flash_message = $this->session->flashdata('message');
		$this->tsmarty->assign("flash_message", $flash_message);

		// Get brands
		$brands = $this->m_brands->get_list_brand();
		$this->tsmarty->assign('brands', $brands);

		// Step 1: Get selected brand from URL parameter
		$selected_brand = $this->input->get('brand');

		if ($selected_brand) {
			// Set selected brand for template
			$this->tsmarty->assign('selected_brand', $selected_brand);

			// Get brand name for display
			$brand_name = '';
			foreach ($brands as $brand) {
				if ($brand['brand_type'] == $selected_brand) {
					$brand_name = $brand['brand_name'];
					break;
				}
			}
			$this->tsmarty->assign('selected_brand_name', $brand_name);

			// Get categories for the selected brand from local database
			$local_categories = $this->m_categories->get_list_category_by_brand($selected_brand);
			$this->tsmarty->assign('categories', $local_categories);
			log_message('debug', "Local categories for brand {$selected_brand}: " . count($local_categories));

			// Get API products for reference only
			$api_products = $this->products_api->getProductsByBrand($selected_brand);
			log_message('debug', "API products count: " . count($api_products));
			$this->tsmarty->assign('api_products_debug', $api_products);
		}

		// Step 2: Form submission untuk menambahkan produk
		if ($this->input->post()) {
			$this->form_validation->set_rules('cat_id', 'Kategori Produk', 'trim|required');
			$this->form_validation->set_rules('product_brand', 'Brand Produk', 'trim|required');
			$this->form_validation->set_rules('product_code', 'Kode Produk', 'trim|required');
			$this->form_validation->set_rules('product_name', 'Nama Produk', 'trim|required');
			$this->form_validation->set_rules('product_desc', 'Deskripsi Produk', 'trim');
			$this->form_validation->set_rules('product_st', 'Status Produk', 'trim|required');
			$this->form_validation->set_rules('product_promote', 'Promosi Produk', 'trim|required');
			$this->form_validation->set_rules('product_price', 'Harga Produk', 'trim|required');
			$this->form_validation->set_rules('product_komposisi', 'Komposisi', 'trim|required');
			$this->form_validation->set_rules('product_no', 'Urutan Tampil', 'trim');
			$this->form_validation->set_rules('expired_date', 'Expired Date', 'trim|required');
			$this->form_validation->set_rules('product_netto', 'Netto', 'trim|required');
			$this->form_validation->set_rules('ek_marketing', 'Status Marketing', 'trim|required');
			$this->form_validation->set_rules('ek_customer', 'Status Customer', 'trim|required');
			$this->form_validation->set_rules('ek_outlet', 'Status Outlet', 'trim|required');

			if ($this->form_validation->run() !== FALSE) {
				// Get the API ID if available
				$api_id = $this->input->post('api_id');
				$product_brand = $this->input->post('product_brand');

				// Check if product already exists by API ID
				if (!empty($api_id)) {
					$existing_product = $this->m_products->find_by_api_id_and_brand($api_id, $product_brand);
					if ($existing_product) {
						// Product exists, just redirect to edit page instead of showing error
						$this->session->set_flashdata('message', array(
							'msg' => 'Produk dengan ID API ini sudah ada. Redirecting ke halaman edit.',
							'status' => 'info'
						));
						redirect('master/products/edit/' . $existing_product['product_id']);
						return;
					}
				} else {
					// Only do standard product code check if not an API product
					// Periksa apakah kode produk sudah ada
					if ($this->m_products->is_exist_product_code([$this->input->post('product_code')])) {
						$this->session->set_flashdata('message', array(
							'msg' => 'Kode Produk sudah dipakai. Silakan ganti dengan yang lain.',
							'status' => 'error'
						));
						redirect('master/products/add/?brand=' . $this->input->post('product_brand'));
						return;
					}

					// Periksa apakah nama produk sudah ada
					if ($this->m_products->is_exist_product_name([$this->input->post('product_name')])) {
						$this->session->set_flashdata('message', array(
							'msg' => 'Nama Produk sudah dipakai. Silakan ganti dengan yang lain.',
							'status' => 'error'
						));
						redirect('master/products/add/?brand=' . $this->input->post('product_brand'));
						return;
					}
				}

				// Get category ID - Using local category directly
				$cat_id = $this->input->post('cat_id');

				// Validate that the category exists and belongs to the selected brand
				$category = $this->m_categories->get_detail_category($cat_id);
				if (empty($category) || $category['cat_brand'] != $product_brand) {
					$this->session->set_flashdata('message', array(
						'msg' => 'Kategori tidak valid atau tidak sesuai dengan brand yang dipilih.',
						'status' => 'error'
					));
					redirect('master/products/add/?brand=' . $product_brand);
					return;
				}

				// Siapkan data produk
				$data = [
					'product_parent' => '0',
					'cat_id' => $cat_id,
					'product_brand' => $this->input->post('product_brand'),
					'product_code' => $this->input->post('product_code'),
					'product_name' => $this->input->post('product_name'),
					'product_desc' => $this->input->post('product_desc'),
					'product_price' => $this->input->post('product_price'),
					'product_komposisi' => $this->input->post('product_komposisi'),
					'product_no' => $this->input->post('product_no'),
					'expired_date' => $this->input->post('expired_date'),
					'product_netto' => $this->input->post('product_netto'),
					'product_st' => $this->input->post('product_st'),
					'ek_marketing' => $this->input->post('ek_marketing'),
					'ek_customer' => $this->input->post('ek_customer'),
					'ek_outlet' => $this->input->post('ek_outlet'),
					'product_promote' => $this->input->post('product_promote'),
					'created' => date('Y-m-d H:i:s'),
					'created_by' => $this->user_data['user_id'],
					'modified' => date('Y-m-d H:i:s'),
					'modified_by' => $this->user_data['user_id'],
				];

				// Periksa API product ID
				if (!empty($api_id)) {
					$data['api_id'] = $api_id;
				}

				// Handle gambar produk
				$dir = "./resource/assets-frontend/dist/product/";
				if (!file_exists($dir)) {
					mkdir("./resource/assets-frontend/dist/product/", 0755);
				}

				if ($_FILES['product_pict']['tmp_name'] !== '') {
					$temp = explode(".", $_FILES['product_pict']['name']);
					$ext = end($temp);
					// upload image
					$config['upload_path']          = './resource/assets-frontend/dist/product/';
					$config['allowed_types']        = 'svg|gif|jpg|png';
					$config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
					$config['overwrite']            = TRUE;

					$this->load->library('upload', $config);
					if (!$this->upload->do_upload('product_pict')) {
						$error = array('error' => strip_tags($this->upload->display_errors()));
						$this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
						redirect('master/products/add/?brand=' . $this->input->post('product_brand'));
					}
					$data_upload = $this->upload->data();
					$data['product_pict'] = $data_upload['file_name'];
				} else {
					// Gunakan gambar default jika tidak ada gambar yang diupload
					$data['product_pict'] = 'default-product.jpg';
				}

				// Simpan produk ke database
				if ($this->m_products->add_product($data)) {
					$this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
					redirect('master/products');
				} else {
					$this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
					redirect('master/products/add/?brand=' . $this->input->post('product_brand'));
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/products/add/?brand=' . $this->input->post('product_brand'));
			}
		}

		// output
		parent::display();
	}

	// edit
	public function edit($product_id)
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/products/edit.html");

		// detail product
		$detail = $this->m_products->get_detail_product($product_id);
		$this->tsmarty->assign('detail', $detail);

		// Get flash message
		$flash_message = $this->session->flashdata('message');
		$this->tsmarty->assign("flash_message", $flash_message);

		// Get brands
		$brands = $this->m_brands->get_list_brand();
		$this->tsmarty->assign('brands', $brands);

		// PERBAIKAN: Ambil API ID dari product_api_id dengan logging untuk debugging
		$api_id = null;
		if (isset($detail['product_api_id']) && !empty($detail['product_api_id'])) {
			$api_id = $detail['product_api_id'];
			log_message('debug', "Edit product - Using product_api_id: {$api_id}");
		} else {
			log_message('debug', "Edit product - No API ID found for product {$product_id}");
		}

		$this->tsmarty->assign('api_id', $api_id);
		$this->tsmarty->assign('is_legacy_product', empty($api_id));

		// Ambil informasi brand
		$product_brand = $detail['product_brand'] ?? '';
		$this->tsmarty->assign('product_brand', $product_brand);

		// Validasi API ID jika ada
		$api_id_valid = false;
		$current_api_product = null;

		if (!empty($api_id) && !empty($product_brand)) {
			// Check if API ID is still valid
			$api_id_valid = $this->m_products->is_valid_api_id($api_id, $product_brand);

			log_message('debug', "API ID validation - ID: {$api_id}, Brand: {$product_brand}, Valid: " . ($api_id_valid ? 'true' : 'false'));

			if ($api_id_valid) {
				$current_api_product = $this->m_products->get_api_product_by_id($api_id, $product_brand);
				if ($current_api_product) {
					log_message('debug', "Current API product found: " . $current_api_product['product_name']);
				}
			} else {
				// API ID tidak valid, tampilkan peringatan
				log_message('warning', "Product ID {$product_id} has invalid API ID {$api_id}");
			}
		}

		$this->tsmarty->assign('api_id_valid', $api_id_valid);
		$this->tsmarty->assign('current_api_product', $current_api_product);

		// Tambahkan log untuk debugging - tampilkan detail lengkap
		log_message('debug', "Editing product with ID: {$product_id}, Brand: " . ($product_brand ?? 'NULL') .
			", Product API ID: " . ($api_id ?? 'NULL') . " (valid: " . ($api_id_valid ? 'true' : 'false') . ")");
		// Jika produk memiliki brand, ambil data dari API
		if (!empty($product_brand)) {
			// Ambil produk dari API berdasarkan brand untuk API search
			$api_products = $this->products_api->getProductsByBrand($product_brand);

			// Log jumlah produk yang ditemukan
			log_message('debug', "API products found: " . count($api_products));

			// Tambahkan flag untuk tahu apakah ada produk API tersedia
			$this->tsmarty->assign('has_api_products', count($api_products) > 0);
			$this->tsmarty->assign('api_products_debug', $api_products);

			// Untuk produk lama, cari produk yang mungkin cocok berdasarkan nama/kode
			if (empty($api_id) && (!empty($detail['product_name']) || !empty($detail['product_code']))) {
				$similar_api_products = [];
				$product_name = strtolower($detail['product_name'] ?? '');
				$product_code = strtolower($detail['product_code'] ?? '');

				foreach ($api_products as $api_product) {
					$api_name = strtolower($api_product['product_name'] ?? '');
					$api_code = strtolower($api_product['product_code'] ?? '');

					// Cari kecocokan berdasarkan nama atau kode produk
					if (!empty($product_name) && strpos($api_name, $product_name) !== false) {
						$similar_api_products[] = $api_product;
					} else if (!empty($product_code) && strpos($api_code, $product_code) !== false) {
						$similar_api_products[] = $api_product;
					}
				}

				// Ambil 5 produk yang serupa untuk ditampilkan sebagai saran
				$similar_api_products = array_slice($similar_api_products, 0, 5);
				$this->tsmarty->assign('similar_api_products', $similar_api_products);
				log_message('debug', "Found " . count($similar_api_products) . " similar API products");
			}

			// Get categories for the selected brand from local database
			$local_categories = $this->m_categories->get_list_category_by_brand($product_brand);
			$this->tsmarty->assign('categories', $local_categories);
			log_message('debug', "Local categories for brand {$product_brand}: " . count($local_categories));
		} else {
			// Default kategori dari database
			$cat = $this->m_categories->get_list_category();
			$this->tsmarty->assign('categories', $cat);
		}

		// save data
		if ($this->input->post()) {
			$this->form_validation->set_rules('cat_id', 'Kategori Produk', 'trim|required');
			$this->form_validation->set_rules('product_brand', 'Brand Produk', 'trim|required');
			$this->form_validation->set_rules('product_code', 'Kode Produk', 'trim|required');
			$this->form_validation->set_rules('product_name', 'Nama Produk', 'trim|required');
			$this->form_validation->set_rules('product_desc', 'Deskripsi Produk', 'trim');
			$this->form_validation->set_rules('product_st', 'Status Produk', 'trim|required');
			$this->form_validation->set_rules('product_promote', 'Promosi Produk', 'trim|required');
			$this->form_validation->set_rules('product_price', 'Harga Produk', 'trim|required');
			$this->form_validation->set_rules('product_komposisi', 'Komposisi', 'trim|required');
			$this->form_validation->set_rules('product_no', 'Urutan Tampil', 'trim');
			$this->form_validation->set_rules('expired_date', 'Expired Date', 'trim|required');
			$this->form_validation->set_rules('product_netto', 'Netto', 'trim|required');
			$this->form_validation->set_rules('ek_marketing', 'Status Marketing', 'trim|required');
			$this->form_validation->set_rules('ek_customer', 'Status Customer', 'trim|required');
			$this->form_validation->set_rules('ek_outlet', 'Status Outlet', 'trim|required');

			if ($this->form_validation->run() !== FALSE) {
				// Cek apakah ini produk lama yang akan dihubungkan dengan API
				$original_api_id = $detail['product_api_id'] ?? null;  // PERBAIKAN: gunakan product_api_id
				$new_api_id = $this->input->post('api_id');
				$is_connecting_to_api = empty($original_api_id) && !empty($new_api_id);

				// Jika menghubungkan produk lama dengan API, perlu penanganan khusus
				if ($is_connecting_to_api) {
					log_message('debug', "Connecting legacy product ID {$product_id} to API ID {$new_api_id}");

					// Pastikan API ID yang diinput valid
					if (!$this->m_products->is_valid_api_id($new_api_id, $this->input->post('product_brand'))) {
						$this->session->set_flashdata('message', array(
							'msg' => 'API ID tidak valid. Silakan pilih produk dari daftar yang tersedia.',
							'status' => 'error'
						));
						redirect('master/products/edit/' . $product_id);
						return;
					}
				} else {
					// Untuk produk yang sudah memiliki API ID, kita tetap cek duplikat seperti biasa
					// cek product code
					if ($this->m_products->is_exist_product_code_by_id([$this->input->post('product_code'), $product_id])) {
						$this->session->set_flashdata('message', array(
							'msg' => 'Kode Produk sudah dipakai. Silakan ganti dengan yang lain.',
							'status' => 'error'
						));
						redirect('master/products/edit/' . $product_id);
					}
					// cek product name
					if ($this->m_products->is_exist_product_name_by_id([$this->input->post('product_name'), $product_id])) {
						$this->session->set_flashdata('message', array(
							'msg' => 'Nama Produk sudah dipakai. Silakan ganti dengan yang lain.',
							'status' => 'error'
						));
						redirect('master/products/edit/' . $product_id);
					}
				}

				// Simpan nilai asli sebelum update untuk dibandingkan
				$original_product_name = $detail['product_name'];
				$original_product_price = $detail['product_price'];

				// Get the selected category ID
				$cat_id = $this->input->post('cat_id');

				// Verify the category exists
				$category = $this->m_categories->get_detail_category($cat_id);
				if (empty($category)) {
					$this->session->set_flashdata('message', array(
						'msg' => 'Kategori yang dipilih tidak valid.',
						'status' => 'error'
					));
					redirect('master/products/edit/' . $product_id);
					return;
				}

				// Siapkan data untuk update
				$data = [
					'product_parent' => '0',
					'cat_id' => $cat_id,
					'product_brand' => $this->input->post('product_brand'),
					'product_type' => $this->input->post('product_type'),
					'product_code' => $this->input->post('product_code'),
					'product_name' => $this->input->post('product_name'),
					'product_desc' => $this->input->post('product_desc'),
					'product_price' => $this->input->post('product_price'),
					'product_komposisi' => $this->input->post('product_komposisi'),
					'product_no' => $this->input->post('product_no'),
					'expired_date' => $this->input->post('expired_date'),
					'product_netto' => $this->input->post('product_netto'),
					'product_st' => $this->input->post('product_st'),
					'ek_marketing' => $this->input->post('ek_marketing'),
					'ek_customer' => $this->input->post('ek_customer'),
					'ek_outlet' => $this->input->post('ek_outlet'),
					'product_promote' => $this->input->post('product_promote'),
					'modified' => date('Y-m-d H:i:s'),
					'modified_by' => $this->user_data['user_id'],
				];

				// Tambahkan API ID jika ada
				if ($new_api_id) {
					$data['api_id'] = $new_api_id;
				}

				// Handle image upload
				$dir = "./resource/assets-frontend/dist/product/";
				if (!file_exists($dir)) {
					mkdir("./resource/assets-frontend/dist/product/", 0755);
				}

				if ($_FILES['product_pict']['tmp_name'] !== '') {
					$temp = explode(".", $_FILES['product_pict']['name']);
					$ext = end($temp);
					// upload image
					$config['upload_path']          = './resource/assets-frontend/dist/product/';
					$config['allowed_types']        = 'svg|gif|jpg|png';
					$config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
					$config['overwrite']            = TRUE;

					$this->load->library('upload', $config);
					if (!$this->upload->do_upload('product_pict')) {
						$error = array('error' => strip_tags($this->upload->display_errors()));
						$this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
						redirect('master/products/edit/' . $product_id);
					}
					$data_upload = $this->upload->data();
					$data['product_pict'] = $data_upload['file_name'];
				}

				if ($this->m_products->update_product($this->input->post('product_id'), $data)) {
					// Tentukan pesan berdasarkan operasi
					$success_message = 'Data berhasil disimpan';
					if ($is_connecting_to_api) {
						$success_message = 'Produk berhasil dihubungkan dengan data API';

						// Log tindakan ini untuk audit
						log_message('info', "Product ID {$product_id} connected to API ID {$new_api_id} by user ID {$this->user_data['user_id']}");
					}

					// Check if product name or price has changed
					$name_changed = ($original_product_name != $this->input->post('product_name'));
					$price_changed = ($original_product_price != $this->input->post('product_price'));

					// Data untuk update varian produk
					$variant_data = [
						'cat_id' => $cat_id,
						'product_brand' => $this->input->post('product_brand'),
						'product_type' => $this->input->post('product_type'),
						'modified' => date('Y-m-d H:i:s'),
						'modified_by' => $this->user_data['user_id'],
					];

					// Jika nama produk berubah, update parent_name di varian
					if ($name_changed) {
						$variant_data['parent_name'] = $this->input->post('product_name');
					}

					// Jika harga produk berubah, update harga di varian juga
					if ($price_changed) {
						$variant_data['product_price'] = $this->input->post('product_price');
					}

					// Update varian produk
					if ($this->m_products->update_variant_product($this->input->post('product_id'), $variant_data)) {
						// Coba update data di MRP jika ada API ID dan ini bukan proses linking
						if (!$is_connecting_to_api && !empty($data['api_id'])) {
							$update_success = $this->_sync_product_to_mrp($data['api_id'], [
								'product_name' => $this->input->post('product_name'),
								'product_price' => $this->input->post('product_price')
							]);

							if ($update_success) {
								$this->session->set_flashdata('message', array('msg' => $success_message . ' dan diperbarui di MRP.', 'status' => 'success'));
							} else {
								$this->session->set_flashdata('message', array('msg' => $success_message . ' tetapi gagal memperbarui MRP.', 'status' => 'warning'));
							}
						} else {
							$this->session->set_flashdata('message', array('msg' => $success_message, 'status' => 'success'));
						}

						if ($is_connecting_to_api) {
							// Jika baru saja menghubungkan produk, arahkan ke halaman edit lagi untuk melihat hasil
							redirect('master/products/edit/' . $product_id);
						} else {
							redirect('master/products/detail/' . $product_id);
						}
					} else {
						$this->session->set_flashdata('message', array('msg' => $success_message . ' tetapi gagal memperbarui varian.', 'status' => 'warning'));
						redirect('master/products/edit/' . $product_id);
					}
				} else {
					$this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
					redirect('master/products/edit/' . $product_id);
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/products/edit/' . $product_id);
			}
		}
		// output
		parent::display();
	}

	/**
	 * Unlink product from API
	 */
	public function unlinkFromApi($product_id)
	{
		// Get product details first
		$detail = $this->m_products->get_detail_product($product_id);

		if (empty($detail)) {
			$this->session->set_flashdata('message', array(
				'msg' => 'Produk tidak ditemukan.',
				'status' => 'error'
			));
			redirect('master/products');
			return;
		}

		// Check if product has API ID
		if (empty($detail['product_api_id'])) {
			$this->session->set_flashdata('message', array(
				'msg' => 'Produk ini tidak terhubung dengan API.',
				'status' => 'warning'
			));
			redirect('master/products/edit/' . $product_id);
			return;
		}

		// Unlink from API
		if ($this->m_products->unlink_product_from_api($product_id)) {
			$this->session->set_flashdata('message', array(
				'msg' => 'Produk berhasil diputus hubungannya dari API. Sekarang Anda dapat mengedit data produk secara manual.',
				'status' => 'success'
			));

			// Log the action
			log_message('info', "Product ID {$product_id} unlinked from API ID {$detail['product_api_id']} by user ID {$this->user_data['user_id']}");
		} else {
			$this->session->set_flashdata('message', array(
				'msg' => 'Gagal memutus hubungan produk dari API.',
				'status' => 'error'
			));
		}

		redirect('master/products/edit/' . $product_id);
	}

	/**
	 * Sync products from API
	 * 
	 * @param array $api_products Products from API
	 * @param string $brand Brand type
	 * @return int Number of synced products
	 */
	private function syncProducts($api_products, $brand)
	{
		$synced_count = 0;

		foreach ($api_products as $product) {
			// Map product category to a local category
			$local_category = $this->_mapApiCategoryToLocal($product['category_id'], $product['category_name'] ?? '', $brand);

			if (!$local_category) {
				// If no suitable category found, skip product
				log_message('debug', "No suitable local category found for API product: {$product['product_name']}");
				continue;
			}

			// Check if product exists by API ID
			$existing_product = $this->m_products->find_by_api_id($product['product_id']);

			if (!$existing_product) {
				// Product doesn't exist, create new one
				$data = [
					'product_parent' => '0',
					'cat_id' => $local_category['cat_id'],
					'product_brand' => $brand,
					'product_code' => $product['product_code'],
					'product_name' => $product['product_name'],
					'product_desc' => 'Produk dari ' . $brand,
					'product_price' => $product['product_price'],
					'product_komposisi' => 'Disinkronkan dari API',
					'product_no' => $synced_count + 1,
					'expired_date' => 30, // Default 30 days
					'product_netto' => 0, // Default 0 grams
					'product_st' => '0', // Active
					'ek_marketing' => '0', // Active
					'ek_customer' => '0', // Active
					'ek_outlet' => '0', // Active
					'product_promote' => 'none',
					'api_id' => $product['product_id'], // Save API ID for reference
					'created' => date('Y-m-d H:i:s'),
					'created_by' => $this->user_data['user_id'],
					'modified' => date('Y-m-d H:i:s'),
					'modified_by' => $this->user_data['user_id']
				];

				// Use default image for synced products
				$data['product_pict'] = 'default-product.jpg';

				if ($this->m_products->add_product($data)) {
					$synced_count++;
				}
			} else {
				// Product exists, update price and other details
				$data = [
					'product_price' => $product['product_price'],
					'product_name' => $product['product_name'],
					'cat_id' => $local_category['cat_id'],
					'modified' => date('Y-m-d H:i:s'),
					'modified_by' => $this->user_data['user_id']
				];

				if ($this->m_products->update_product($existing_product['product_id'], $data)) {
					$synced_count++;
				}
			}
		}

		return $synced_count;
	}

	/**
	 * Find a matching local category for an API category
	 * 
	 * @param int $api_category_id Category ID from API
	 * @param string $api_category_name Category name from API
	 * @param string $brand Brand type
	 * @return array|bool Local category info or false if not found
	 */
	private function _mapApiCategoryToLocal($api_category_id, $api_category_name, $brand)
	{
		log_message('debug', "Finding local category match for API category ID: {$api_category_id}, Name: {$api_category_name}, Brand: {$brand}");

		// First try to find by API ID
		$category = $this->m_categories->find_by_api_id_and_brand($api_category_id, $brand);
		if ($category) {
			log_message('debug', "Found category by API ID: {$category['cat_id']}");
			return $category;
		}

		// If not found by API ID, try to find by name similarity
		$local_categories = $this->m_categories->get_list_category_by_brand($brand);

		// Normalize the API category name for comparison
		$normalized_api_name = $this->_normalizeString($api_category_name);

		$best_match = null;
		$highest_similarity = 0;

		foreach ($local_categories as $local_category) {
			// Skip non-active categories
			if ($local_category['cat_st'] !== '0') {
				continue;
			}

			$normalized_local_name = $this->_normalizeString($local_category['cat_name']);

			// Calculate string similarity
			$similarity = 0;
			similar_text($normalized_api_name, $normalized_local_name, $similarity);

			// If names are very similar (90% or higher match)
			if ($similarity > 90 && $similarity > $highest_similarity) {
				$best_match = $local_category;
				$highest_similarity = $similarity;
			}
		}

		if ($best_match) {
			log_message('debug', "Found category by name similarity ({$highest_similarity}%): {$best_match['cat_id']}");

			// Update the category to store the API ID for future reference
			$this->m_categories->update_category($best_match['cat_id'], [
				'api_id' => $api_category_id,
				'modified' => date('Y-m-d H:i:s'),
				'modified_by' => $this->user_data['user_id']
			]);

			return $best_match;
		}

		// If no match found, use the first active category for this brand
		foreach ($local_categories as $local_category) {
			if ($local_category['cat_st'] === '0') {
				log_message('debug', "No match found, using default active category: {$local_category['cat_id']}");
				return $local_category;
			}
		}

		// If still no category found, return false
		log_message('debug', "No suitable category found for this brand");
		return false;
	}

	/**
	 * Normalize string for comparison (lowercase, remove spaces)
	 */
	private function _normalizeString($string)
	{
		// Convert to lowercase
		$string = strtolower($string);

		// Remove special characters and spaces
		$string = preg_replace('/[^a-z0-9]/', '', $string);

		return $string;
	}

	/**
	 * Function to sync product data to MRP API
	 * 
	 * @param int $api_id Product API ID
	 * @param array $data Data to sync
	 * @return bool Success status
	 */
	private function _sync_product_to_mrp($api_id, $data)
	{
		try {
			// Menggunakan library Products_api yang sudah ada
			$endpoint = '/data/updateProduct';
			$params = [
				'product_id' => $api_id,
				'product_name' => $data['product_name'],
				'product_price' => $data['product_price']
			];

			// Lakukan request ke API
			$response = $this->products_api->request($endpoint, $params, 'POST', true);

			// Log hasil request
			log_message('debug', 'Sync to MRP Response: ' . print_r($response, true));

			// Cek status response
			if (isset($response['status']) && $response['status'] == 'OK') {
				return true;
			}

			return false;
		} catch (Exception $e) {
			log_message('error', 'Error syncing to MRP: ' . $e->getMessage());
			return false;
		}
	}

	// delete 
	public function delete($product_id)
	{
		// detail product
		$detail = $this->m_products->get_detail_product($product_id);
		// list varian
		$variant = $this->m_products->get_list_varian_product($product_id);
		// delete image
		if (!empty($variant)) {
			foreach ($variant as $var) {
				$file_path = FCPATH . './resource/assets-frontend/dist/product/' . $var['product_pict'];
				if (file_exists($file_path)) {
					unlink($file_path);
				}
			}
		}
		// delete
		if ($this->m_products->delete_variant_product($product_id)) {
			if ($this->m_products->delete_product($product_id)) {
				// delete image
				$file_path = FCPATH . './resource/assets-frontend/dist/product/' . $detail['product_pict'];
				if (file_exists($file_path)) {
					unlink($file_path);
				}
				$this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
			} else {
				$this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
			}
		} else {
			$this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
		}
		// redirect
		redirect('master/products');
	}


	/**
	 * AJAX endpoint untuk pencarian produk dari API
	 */
	public function apiSearchProducts()
	{
		header('Content-Type: application/json'); // Set header content type

		// Ambil parameter dari request
		$brand = $this->input->get('brand');
		$keyword = $this->input->get('keyword');
		$page = $this->input->get('page') ? (int)$this->input->get('page') : 1;
		$limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 10;

		// Validasi parameter minimum
		if (empty($brand)) {
			echo json_encode([
				'status' => 'error',
				'message' => 'Parameter brand harus diisi',
				'data' => [],
				'pagination' => [
					'total' => 0,
					'pages' => 0,
					'current' => $page
				]
			]);
			return;
		}

		try {
			// Dapatkan semua produk dari brand tersebut
			$products = $this->products_api->getProductsByBrand($brand);

			// Log untuk debugging
			log_message('debug', "API Search - Brand: {$brand}, Keyword: '{$keyword}', Total products: " . count($products));

			// Filter berdasarkan keyword jika ada
			if (!empty($keyword)) {
				$keyword = strtolower(trim($keyword));
				$filtered = [];

				foreach ($products as $product) {
					$match = false;

					// Search by product code
					if (strpos(strtolower($product['product_code']), $keyword) !== false) {
						$match = true;
					}

					// Search by product name
					if (!$match && strpos(strtolower($product['product_name']), $keyword) !== false) {
						$match = true;
					}

					// Search by category name
					if (!$match && strpos(strtolower($product['category_name'] ?? ''), $keyword) !== false) {
						$match = true;
					}

					// TAMBAHAN: Search by API ID (product_id)
					if (!$match && strpos((string)$product['product_id'], $keyword) !== false) {
						$match = true;
						log_message('debug', "Found product by API ID match: {$product['product_name']} (ID: {$product['product_id']})");
					}

					// TAMBAHAN: Exact match untuk API ID (prioritas tinggi)
					if ((string)$product['product_id'] === $keyword) {
						// Jika exact match dengan API ID, taruh di urutan pertama
						array_unshift($filtered, $product);
						log_message('debug', "Exact API ID match found: {$product['product_name']} (ID: {$product['product_id']})");
						continue;
					}

					if ($match) {
						$filtered[] = $product;
					}
				}

				$products = $filtered;
				log_message('debug', "After filtering with keyword '{$keyword}': " . count($products) . " products");
			}

			// Implementasi pagination sederhana
			$total = count($products);
			$total_pages = ceil($total / $limit);

			// Batasi page
			if ($page < 1) $page = 1;
			if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

			// Slice data untuk pagination
			$offset = ($page - 1) * $limit;
			$products = array_slice($products, $offset, $limit);

			// Cek kategori lokal untuk setiap produk
			if (!empty($products)) {
				foreach ($products as &$product) {
					if (isset($product['category_id'])) {
						$local_category = $this->m_categories->find_by_api_id($product['category_id']);
						if ($local_category) {
							$product['local_category_id'] = $local_category['cat_id'];
							$product['local_category_name'] = $local_category['cat_name'];
						}
					}
				}
			}

			// Log hasil akhir
			log_message('debug', "Final API search results - Page {$page}/{$total_pages}, Products: " . count($products));

			// Kirim respons
			echo json_encode([
				'status' => 'success',
				'data' => $products,
				'pagination' => [
					'total' => $total,
					'pages' => $total_pages,
					'current' => $page
				]
			]);
		} catch (Exception $e) {
			log_message('error', 'Error in apiSearchProducts: ' . $e->getMessage());
			echo json_encode([
				'status' => 'error',
				'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
				'data' => [],
				'pagination' => [
					'total' => 0,
					'pages' => 0,
					'current' => $page
				]
			]);
		}
	}
	public function syncProductsAndCategories()
	{
		$brand = $this->input->post('brand');

		// Sinkronisasi Kategori
		$apiCategories = $this->products_api->syncCategoriesWithProducts($brand);
		$this->_syncCategories($apiCategories);

		// Sinkronisasi Produk
		$apiProducts = $this->products_api->searchProducts($brand);
		$syncStats = $this->m_products->syncProductsFromAPI($apiProducts['products']);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'status' => 'success',
				'sync_stats' => $syncStats
			]));
	}

	/**
	 * Metode Pribadi untuk Sinkronisasi Kategori
	 */
	private function _syncCategories($apiCategories)
	{
		foreach ($apiCategories as $category) {
			$existingCategory = $this->m_categories->find_by_api_id($category['id']);

			if (!$existingCategory) {
				$this->m_categories->add_category([
					'cat_name' => $category['name'],
					'cat_brand' => $category['brand'],
					'api_id' => $category['id']
				]);
			}
		}
	}

	// VARIANT

	// Sync products from API
	public function sync()
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/products/sync.html");

		// Choose brand to sync
		$selected_brand = $this->input->post('brand');
		$this->tsmarty->assign('selected_brand', $selected_brand);

		// Get brands
		$brands = $this->m_brands->get_list_brand();
		$this->tsmarty->assign('brands', $brands);

		// If form submitted for sync
		if ($this->input->post('sync') && $selected_brand) {
			// Get products from API
			$api_products = $this->products_api->getProductsByBrand($selected_brand);

			if (!empty($api_products)) {
				// Make products data available to the view
				$this->tsmarty->assign('products', $api_products);

				// Get categories from products
				$api_categories = $this->products_api->getCategoriesFromProducts($api_products);

				// Sync categories first
				$synced_categories = $this->syncCategories($api_categories, $selected_brand);

				// Then sync products
				$synced_products = $this->syncProducts($api_products, $selected_brand);

				$this->session->set_flashdata('message', array(
					'msg' => 'Sinkronisasi berhasil. ' . $synced_categories . ' kategori dan ' . $synced_products . ' produk berhasil disinkronkan.',
					'status' => 'success'
				));
				redirect('master/products/sync');
			} else {
				$this->session->set_flashdata('message', array(
					'msg' => 'Tidak ada data produk yang ditemukan untuk brand ' . $selected_brand . '.',
					'status' => 'error'
				));
				redirect('master/products/sync');
			}
		} else if ($selected_brand) {
			// Just show the products without syncing
			$api_products = $this->products_api->getProductsByBrand($selected_brand);
			$this->tsmarty->assign('products', $api_products);
		}

		// output
		parent::display();
	}

	/**
	 * Sync categories from API
	 * 
	 * @param array $api_categories Categories from API
	 * @param string $brand Brand type
	 * @return int Number of synced categories
	 */
	private function syncCategories($api_categories, $brand)
	{
		$synced_count = 0;

		foreach ($api_categories as $category) {
			// Check if category exists
			$existing_category = $this->m_categories->find_by_api_id($category['cat_id']);

			if (!$existing_category) {
				// Category doesn't exist, create new one
				$data = [
					'cat_parent' => '0',
					'cat_brand' => $brand,
					'cat_code' => 'CAT' . $category['cat_id'],
					'cat_name' => $category['cat_name'],
					'cat_desc' => 'Kategori dari ' . $brand,
					'cat_st' => '0', // Active
					'cat_highlight' => '1', // Highlighted
					'cat_no' => $synced_count + 1,
					'cat_harga' => 0,
					'api_id' => $category['cat_id'], // Save API ID for reference
					'created' => date('Y-m-d H:i:s'),
					'created_by' => $this->user_data['user_id'],
					'modified' => date('Y-m-d H:i:s'),
					'modified_by' => $this->user_data['user_id'],
					'seasonal_id' => 0
				];

				if ($this->m_categories->add_category($data)) {
					$synced_count++;
				}
			} else {
				// Category exists, update if needed
				$data = [
					'cat_name' => $category['cat_name'],
					'modified' => date('Y-m-d H:i:s'),
					'modified_by' => $this->user_data['user_id']
				];

				if ($this->m_categories->update_category($existing_category['cat_id'], $data)) {
					$synced_count++;
				}
			}
		}

		return $synced_count;
	}

	// add varian
	public function add_variant($product_parent = 0)
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/products/add_variant.html");
		$this->tsmarty->assign('title', 'Tambah Varian');
		// detail product
		$detail = $this->m_products->get_detail_product($product_parent);
		$this->tsmarty->assign('detail', $detail);
		// save data
		if ($this->input->post()) {
			$this->form_validation->set_rules('product_code', 'Kode Produk', 'trim|required');
			$this->form_validation->set_rules('product_name', 'Nama Produk', 'trim|required');
			$this->form_validation->set_rules('product_desc', 'Deskripsi Produk', 'trim');
			$this->form_validation->set_rules('product_st', 'Status Produk', 'trim|required');
			$this->form_validation->set_rules('product_promote', 'Promosi Produk', 'trim|required');
			$this->form_validation->set_rules('product_price', 'Harga Produk', 'trim|required');
			$this->form_validation->set_rules('product_komposisi', 'Komposisi', 'trim|required');
			$this->form_validation->set_rules('product_no', 'Urutan Tampil', 'trim');
			$this->form_validation->set_rules('expired_date', 'Expired Date', 'trim|required');
			$this->form_validation->set_rules('product_netto', 'Netto', 'trim|required');
			if ($this->form_validation->run() !== FALSE) {
				$data = [
					'product_parent' => $detail['product_id'],
					'cat_id' => $detail['cat_id'],
					'product_brand' => $detail['product_brand'],
					'product_type' => $detail['product_type'],
					'product_code' => $this->input->post('product_code'),
					'product_name' => $this->input->post('product_name'),
					'parent_name'  => $detail['product_name'],
					'product_desc' => $this->input->post('product_desc'),
					'product_price' => $this->input->post('product_price'),
					'product_komposisi' => $this->input->post('product_komposisi'),
					'product_no' => $this->input->post('product_no'),
					'expired_date' => $this->input->post('expired_date'),
					'product_netto' => $this->input->post('product_netto'),
					'product_st' => $this->input->post('product_st'),
					'product_promote' => $this->input->post('product_promote'),
					'created' => date('Y-m-d H:i:s'),
					'created_by' => $this->user_data['user_id'],
					'modified' => date('Y-m-d H:i:s'),
					'modified_by' => $this->user_data['user_id'],
				];

				$dir = "./resource/assets-frontend/dist/product/";
				if (!file_exists($dir)) {
					mkdir("./resource/assets-frontend/dist/product/", 0755);
				}

				if ($_FILES['product_pict']['tmp_name'] !== '') {
					$temp = explode(".", $_FILES['product_pict']['name']);
					$ext = end($temp);
					// upload image
					$config['upload_path']          = './resource/assets-frontend/dist/product/';
					$config['allowed_types']        = 'svg|gif|jpg|png';
					$config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
					$config['overwrite']            = TRUE;

					$this->load->library('upload', $config);
					if (!$this->upload->do_upload('product_pict')) {
						$error = array('error' => strip_tags($this->upload->display_errors()));
						$this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
						redirect('master/products/add_variant/' . $product_parent);
					}
					$data_upload = $this->upload->data();
					$data['product_pict'] = $data_upload['file_name'];
				}

				if ($this->m_products->add_product($data)) {
					$this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
					redirect('master/products/detail/' . $product_parent);
				} else {
					$this->session->set_flashdata('message', array('msg' => $this->db->error()['message'], 'status' => 'error'));
					redirect('master/products/add_variant/' . $product_parent);
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/products/add_variant/' . $product_parent);
			}
		}
		// output
		parent::display();
	}

	// edit varian
	public function edit_variant($product_id)
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/products/edit_variant.html");
		$this->tsmarty->assign('title', 'Ubah Varian');
		// detail product
		$detail = $this->m_products->get_detail_product($product_id);
		$this->tsmarty->assign('detail', $detail);
		// save data
		if ($this->input->post()) {
			$this->form_validation->set_rules('product_code', 'Kode Produk', 'trim|required');
			$this->form_validation->set_rules('product_name', 'Nama Produk', 'trim|required');
			$this->form_validation->set_rules('product_desc', 'Deskripsi Produk', 'trim');
			$this->form_validation->set_rules('product_st', 'Status Produk', 'trim|required');
			$this->form_validation->set_rules('product_promote', 'Promosi Produk', 'trim|required');
			$this->form_validation->set_rules('product_price', 'Harga Produk', 'trim|required');
			$this->form_validation->set_rules('product_komposisi', 'Komposisi', 'trim|required');
			$this->form_validation->set_rules('product_no', 'Urutan Tampil', 'trim');
			$this->form_validation->set_rules('expired_date', 'Expired Date', 'trim|required');
			$this->form_validation->set_rules('product_netto', 'Netto', 'trim|required');
			if ($this->form_validation->run() !== FALSE) {
				$data = [
					'product_parent' => $detail['product_parent'],
					'cat_id' => $detail['cat_id'],
					'product_brand' => $detail['product_brand'],
					'product_type' => $detail['product_type'],
					'product_code' => $this->input->post('product_code'),
					'product_name' => $this->input->post('product_name'),
					'product_desc' => $this->input->post('product_desc'),
					'product_price' => $this->input->post('product_price'),
					'product_komposisi' => $this->input->post('product_komposisi'),
					'product_no' => $this->input->post('product_no'),
					'expired_date' => $this->input->post('expired_date'),
					'product_netto' => $this->input->post('product_netto'),
					'product_st' => $this->input->post('product_st'),
					'product_promote' => $this->input->post('product_promote'),
					'modified' => date('Y-m-d H:i:s'),
					'modified_by' => $this->user_data['user_id'],
				];

				$dir = "./resource/assets-frontend/dist/product/";
				if (!file_exists($dir)) {
					mkdir("./resource/assets-frontend/dist/product/", 0755);
				}

				if ($_FILES['product_pict']['tmp_name'] !== '') {
					$temp = explode(".", $_FILES['product_pict']['name']);
					$ext = end($temp);
					// upload image
					$config['upload_path']          = './resource/assets-frontend/dist/product/';
					$config['allowed_types']        = 'svg|gif|jpg|png';
					$config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
					$config['overwrite']            = TRUE;

					$this->load->library('upload', $config);
					if (!$this->upload->do_upload('product_pict')) {
						$error = array('error' => strip_tags($this->upload->display_errors()));
						$this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
						redirect('master/products/edit_variant/' . $product_id);
					}
					$data_upload = $this->upload->data();
					$data['product_pict'] = $data_upload['file_name'];
				}

				if ($this->m_products->update_product($this->input->post('product_id'), $data)) {
					$this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
					redirect('master/products/detail/' . $detail['product_parent']);
				} else {
					$this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
					redirect('master/products/edit_variant/' . $product_id);
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/products/edit_variant/' . $product_id);
			}
		}
		// output
		parent::display();
	}

	// delete varian
	public function delete_varian($product_id)
	{
		// detail product
		$detail = $this->m_products->get_detail_product($product_id);
		$this->tsmarty->assign('detail', $detail);
		// delete
		if ($this->m_products->delete_product($product_id)) {
			$this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
		} else {
			$this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
		}
		// redirect
		redirect('master/products/detail/' . $detail['product_parent']);
	}
}
