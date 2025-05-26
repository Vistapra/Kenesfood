<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PrivateBase.php');

// --
class Package extends ApplicationBase
{
	protected $allowed = [];

	public function __construct()
	{
		// parent constructor
		parent::__construct();
		$this->tsmarty->clearAllCache();
		$this->tsmarty->clearCompiledTemplate();

		// Tambahkan pengecekan login di sini
		// Ini akan dijalankan untuk SEMUA method dalam controller
		if (empty($this->user_data)) {
			redirect('administrator');
		}

		$this->tsmarty->assign("allowed", [
			"create" => true,
			"edit" => true,
			"delete" => true
		]);

		// load model
		$this->load->model('master/M_products', 'm_products');
		$this->load->model('master/M_categories', 'm_categories');
		$this->load->model('master/M_brands', 'm_brands');
		$this->load->model('master/M_packages', 'm_packages');
	}

	// index - tampilkan daftar package
	public function index()
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/index.html");

		// search
		$keyword = '';
		$search = $this->session->userdata('search_package');
		if ($this->input->post()) {
			if ($this->input->post('save') == "Reset") {
				// unset session
				$this->session->unset_userdata("search_package");
			} else {
				$keyword = $this->input->post('keyword');
				// set session
				$params = array(
					"keyword" => $keyword,
				);
				$this->session->set_userdata("search_package", $params);
			}
		} elseif (!empty($search)) {
			$keyword = $search['keyword'];
		}
		$this->tsmarty->assign("keyword", $keyword);

		// load library
		$this->load->library('pagination');
		// pagination
		$config['base_url'] = site_url('master/package/index/');
		$config['total_rows'] = $this->m_packages->get_total_data($keyword);
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
		$data = $this->m_packages->get_list_data($params, $keyword);
		$params = array(($start - 1), $config['per_page']);
		$data = $this->m_packages->get_list_data($params, $keyword);
		$this->tsmarty->assign("packages", $data);

		// output
		parent::display();
	}

	// detail package
	public function detail($package_id = '')
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/detail.html");

		// detail package
		$detail = $this->m_packages->get_detail_package($package_id);
		if (empty($detail)) {
			$this->session->set_flashdata('message', array('msg' => 'Paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		$this->tsmarty->assign('detail', $detail);

		// get package categories
		$categories = $this->m_packages->get_package_categories($package_id);
		$this->tsmarty->assign('categories', $categories);

		// get products in package by category
		$products_by_category = [];
		foreach ($categories as $category) {
			$products = $this->m_packages->get_package_products($package_id, $category['id']);
			$products_by_category[$category['id']] = $products;
		}
		$this->tsmarty->assign('products_by_category', $products_by_category);

		// Set allowed actions
		$this->tsmarty->assign("allowed", [
			"create" => true,
			"edit" => true,
			"delete" => true
		]);

		// output
		parent::display();
	}

	// add new package
	// add new package
	public function add()
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/add.html");

		// list category
		$cat = $this->m_categories->get_list_category();
		$this->tsmarty->assign('categories', $cat);

		// list brands
		$brands = $this->m_brands->get_list_brand();
		$this->tsmarty->assign('brands', $brands);

		$this->tsmarty->assign("allowed", $this->allowed);

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
			$this->form_validation->set_rules('package_type', 'Tipe Paket', 'trim|required');
			$this->form_validation->set_rules('initial_stock', 'Stok Awal', 'trim|required|numeric|greater_than[0]');

			if ($this->form_validation->run() !== FALSE) {
				// cek product code
				if ($this->m_products->is_exist_product_code([$this->input->post('product_code')])) {
					$this->session->set_flashdata('message', array('msg' => 'Kode Produk sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
					redirect('master/package/add/');
				}

				// cek product name
				if ($this->m_products->is_exist_product_name([$this->input->post('product_name')])) {
					$this->session->set_flashdata('message', array('msg' => 'Nama Produk sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
					redirect('master/package/add/');
				}

				$this->db->trans_begin();

				try {
					// Ambil nilai stok awal dari form
					$initial_stock = intval($this->input->post('initial_stock'));

					// Insert into data_product
					$data_product = [
						'product_parent' => '0',
						'cat_id' => $this->input->post('cat_id'),
						'product_brand' => $this->input->post('product_brand'),
						'product_code' => $this->input->post('product_code'),
						'product_name' => $this->input->post('product_name'),
						'product_desc' => $this->input->post('product_desc'),
						'product_price' => $this->input->post('product_price'),
						'product_komposisi' => $this->input->post('product_komposisi'),
						'product_no' => -1,
						'expired_date' => $this->input->post('expired_date'),
						'product_netto' => $this->input->post('product_netto'),
						'product_st' => $this->input->post('product_st'),
						'ek_marketing' => $this->input->post('ek_marketing'),
						'ek_customer' => $this->input->post('ek_customer'),
						'ek_outlet' => $this->input->post('ek_outlet'),
						'product_promote' => $this->input->post('product_promote'),
						'is_package' => '1',
						'package_type' => $this->input->post('package_type'),
						'stock' => $initial_stock, // PERBAIKAN: Gunakan $initial_stock yang sudah didefinisikan
						'created' => date('Y-m-d H:i:s'),
						'created_by' => $this->user_data['user_id'],
						'modified' => date('Y-m-d H:i:s'),
						'modified_by' => $this->user_data['user_id']
						// PERBAIKAN: Hapus duplikasi field 'stock' => 10
					];

					$dir = "./resource/assets-frontend/dist/product/";
					if (!file_exists($dir)) {
						mkdir("./resource/assets-frontend/dist/product/", 0755);
					}

					if ($_FILES['product_pict']['tmp_name'] !== '') {
						$temp = explode(".", $_FILES['product_pict']['name']);
						$ext = end($temp);
						// upload image
						$config['upload_path'] = './resource/assets-frontend/dist/product/';
						$config['allowed_types'] = 'svg|gif|jpg|png';
						$config['file_name'] = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
						$config['overwrite'] = TRUE;

						$this->load->library('upload', $config);
						if (!$this->upload->do_upload('product_pict')) {
							$error = array('error' => strip_tags($this->upload->display_errors()));
							$this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
							redirect('master/package/add/');
						}
						$data_upload = $this->upload->data();
						$data_product['product_pict'] = $data_upload['file_name'];
					} else {
						$this->session->set_flashdata('message', array('msg' => 'Foto Produk tidak boleh kosong.', 'status' => 'error'));
						redirect('master/package/add/');
					}

					// Insert product
					if ($this->m_products->add_product($data_product)) {
						// Get inserted product ID
						$product_id = $this->db->insert_id();

						// Insert into packages table
						$data_package = [
							'product_id' => $product_id,
							'name' => $this->input->post('product_name'),
							'description' => $this->input->post('product_desc'),
							'base_price' => $this->input->post('product_price'),
							'status' => ($this->input->post('product_st') == '0') ? 'active' : 'inactive',
							'type' => $this->input->post('package_type'),
							'created_at' => date('Y-m-d H:i:s'),
							'updated_at' => date('Y-m-d H:i:s')
						];

						if ($this->db->insert('packages', $data_package)) {
							$package_id = $this->db->insert_id();

							if ($this->input->post('package_type') == 'standard') {
								// Standard package - create default category
								$data_category = [
									'package_id' => $package_id,
									'name' => 'Standard Items',
									'selection_type' => 'multiple',
									'display_order' => 1,
									'created_at' => date('Y-m-d H:i:s'),
									'updated_at' => date('Y-m-d H:i:s')
								];

								if ($this->db->insert('package_categories', $data_category)) {
									$category_id = $this->db->insert_id();

									// Add category requirements
									$data_requirement = [
										'package_category_id' => $category_id,
										'min_items' => 1,
										'max_items' => 10,
										'created_at' => date('Y-m-d H:i:s'),
										'updated_at' => date('Y-m-d H:i:s')
									];

									$this->db->insert('package_category_requirements', $data_requirement);
								}
							}

							$this->db->trans_commit();
							$this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
							redirect('master/package/detail/' . $package_id);
						} else {
							$this->db->trans_rollback();
							$this->session->set_flashdata('message', array('msg' => 'Gagal menambahkan paket.', 'status' => 'error'));
							redirect('master/package/add/');
						}
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('message', array('msg' => 'Gagal menambahkan produk.', 'status' => 'error'));
						redirect('master/package/add/');
					}
				} catch (Exception $e) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array('msg' => 'Error: ' . $e->getMessage(), 'status' => 'error'));
					redirect('master/package/add/');
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/package/add/');
			}
		}

		// output
		parent::display();
	}

	// edit package
	public function edit($package_id)
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/edit.html");

		// get package detail
		$package = $this->m_packages->get_detail_package($package_id);
		if (empty($package)) {
			$this->session->set_flashdata('message', array('msg' => 'Paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		$this->tsmarty->assign('detail', $package);

		// list category
		$cat = $this->m_categories->get_list_category();
		$this->tsmarty->assign('categories', $cat);

		// list brands
		$brands = $this->m_brands->get_list_brand();
		$this->tsmarty->assign('brands', $brands);

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
			$this->form_validation->set_rules('package_type', 'Tipe Paket', 'trim|required');

			if ($this->form_validation->run() !== FALSE) {
				// cek product code
				if ($this->m_products->is_exist_product_code_by_id([$this->input->post('product_code'), $package['product_id']])) {
					$this->session->set_flashdata('message', array('msg' => 'Kode Produk sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
					redirect('master/package/edit/' . $package_id);
				}

				// cek product name
				if ($this->m_products->is_exist_product_name_by_id([$this->input->post('product_name'), $package['product_id']])) {
					$this->session->set_flashdata('message', array('msg' => 'Nama Produk sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
					redirect('master/package/edit/' . $package_id);
				}

				$this->db->trans_begin();

				try {
					// Update data_product
					$data_product = [
						'cat_id' => $this->input->post('cat_id'),
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
						'package_type' => $this->input->post('package_type'),
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
						$config['upload_path'] = './resource/assets-frontend/dist/product/';
						$config['allowed_types'] = 'svg|gif|jpg|png';
						$config['file_name'] = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
						$config['overwrite'] = TRUE;

						$this->load->library('upload', $config);
						if (!$this->upload->do_upload('product_pict')) {
							$error = array('error' => strip_tags($this->upload->display_errors()));
							$this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
							redirect('master/package/edit/' . $package_id);
						}
						$data_upload = $this->upload->data();
						$data_product['product_pict'] = $data_upload['file_name'];
					}

					// Update product
					if ($this->m_products->update_product($package['product_id'], $data_product)) {
						// Update packages table
						$data_package = [
							'name' => $this->input->post('product_name'),
							'description' => $this->input->post('product_desc'),
							'base_price' => $this->input->post('product_price'),
							'status' => ($this->input->post('product_st') == '0') ? 'active' : 'inactive',
							'type' => $this->input->post('package_type'),
							'updated_at' => date('Y-m-d H:i:s')
						];

						$this->db->where('id', $package_id);
						if ($this->db->update('packages', $data_package)) {
							$this->db->trans_commit();
							$this->session->set_flashdata('message', array('msg' => 'Data berhasil diperbarui', 'status' => 'success'));
							redirect('master/package/detail/' . $package_id);
						} else {
							$this->db->trans_rollback();
							$this->session->set_flashdata('message', array('msg' => 'Gagal memperbarui paket.', 'status' => 'error'));
							redirect('master/package/edit/' . $package_id);
						}
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('message', array('msg' => 'Gagal memperbarui produk.', 'status' => 'error'));
						redirect('master/package/edit/' . $package_id);
					}
				} catch (Exception $e) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array('msg' => 'Error: ' . $e->getMessage(), 'status' => 'error'));
					redirect('master/package/edit/' . $package_id);
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/package/edit/' . $package_id);
			}
		}

		// output
		parent::display();
	}

	public function delete($package_id)
	{
		// Mulai transaksi database
		$this->db->trans_start();

		try {
			// Ambil detail package
			$package = $this->m_packages->get_detail_package($package_id);
			if (empty($package)) {
				throw new Exception('Paket tidak ditemukan.');
			}

			// STEP 1: IDENTIFIKASI SEMUA REFERENSI DI order_details 
			// Ambil semua order_details yang menggunakan paket ini
			$order_details_using_package = $this->db
				->select('id, order_id')
				->from('order_details')
				->where('package_id', $package_id)
				->get()
				->result_array();

			// Ambil semua order_details yang menggunakan produk paket ini
			$order_details_using_product = $this->db
				->select('id, order_id')
				->from('order_details')
				->where('product_id', $package['product_id'])
				->get()
				->result_array();

			// Gabungkan semua order_detail_ids yang perlu dihapus
			$all_order_detail_ids = array_unique(
				array_merge(
					array_column($order_details_using_package, 'id'),
					array_column($order_details_using_product, 'id')
				)
			);

			// Ambil order IDs terkait untuk tracking
			$all_affected_order_ids = array_unique(
				array_merge(
					array_column($order_details_using_package, 'order_id'),
					array_column($order_details_using_product, 'order_id')
				)
			);

			// STEP 2: HAPUS REFERENSI DI order_details (untuk mengatasi constraint order_details_ibfk_3)
			if (!empty($all_order_detail_ids)) {
				// Tambahkan log penghapusan item di order history
				foreach ($all_affected_order_ids as $order_id) {
					$log_data = [
						'order_id' => $order_id,
						'status' => 99, // Kode untuk penghapusan item
						'created_at' => date('Y-m-d H:i:s'),
						'updated_by' => $this->user_data['user_id']
					];
					$this->db->insert('order_history', $log_data);
				}

				// Hapus order_details yang terkait dengan paket ini
				$this->db->where_in('id', $all_order_detail_ids);
				$this->db->delete('order_details');

				// Update totals di orders
				foreach ($all_affected_order_ids as $order_id) {
					$this->_recalculate_order_totals($order_id);
				}
			}

			// STEP 3: AMBIL DAFTAR KATEGORI PAKET DAN REQUIREMENTS
			$categories = $this->db
				->select('id')
				->from('package_categories')
				->where('package_id', $package_id)
				->get()
				->result_array();

			$category_ids = array_column($categories, 'id');

			// STEP 4: HAPUS SEMUA CUSTOM PRODUCTS TERLEBIH DAHULU
			if (!empty($category_ids)) {
				$this->db->where_in('package_category_id', $category_ids);
				$this->db->delete('package_custom_products');
			}

			// STEP 5: HAPUS PACKAGE CATEGORY REQUIREMENTS
			if (!empty($category_ids)) {
				$this->db->where_in('package_category_id', $category_ids);
				$this->db->delete('package_category_requirements');
			}

			// STEP 6: HAPUS PACKAGE CATEGORIES
			$this->db->where('package_id', $package_id);
			$this->db->delete('package_categories');

			// STEP 7: HAPUS PACKAGE EXCLUDES JIKA ADA
			$this->db->where('package_id', $package_id);
			$this->db->delete('package_excludes');

			// STEP 8: HAPUS PACKAGES (CATATAN: LAKUKAN SEBELUM MENGHAPUS PRODUCT)
			$product_id = $package['product_id']; // Simpan product_id untuk nanti

			$this->db->where('id', $package_id);
			$result = $this->db->delete('packages');

			if (!$result) {
				throw new Exception('Gagal menghapus paket: ' . $this->db->error()['message']);
			}

			// STEP 9: HAPUS PRODUCT PAKET
			$this->db->where('product_id', $product_id);
			$result = $this->db->delete('data_product');

			if (!$result) {
				throw new Exception('Gagal menghapus produk paket: ' . $this->db->error()['message']);
			}

			// Commit transaksi
			$this->db->trans_complete();

			// Cek status transaksi
			if ($this->db->trans_status() === FALSE) {
				throw new Exception('Gagal menghapus paket: ' . $this->db->error()['message']);
			}

			// Set pesan sukses
			$this->session->set_flashdata('message', [
				'msg' => 'Paket dan semua data terkait berhasil dihapus secara permanen.',
				'status' => 'success'
			]);

			// Redirect ke halaman daftar paket
			redirect('master/package');
		} catch (Exception $e) {
			// Rollback transaksi jika terjadi kesalahan
			$this->db->trans_rollback();

			// Set pesan error
			$this->session->set_flashdata('message', [
				'msg' => 'Error: ' . $e->getMessage(),
				'status' => 'error'
			]);

			// Log error untuk debugging
			log_message('error', 'Package delete error: ' . $e->getMessage());

			// Redirect kembali
			redirect('master/package');
		}
	}

	/**
	 * Method pribadi untuk menghitung ulang total pesanan
	 */
	private function _recalculate_order_totals($order_id)
	{
		// Hitung total_amount dari order_details yang tersisa
		$total_query = $this->db
			->select_sum('subtotal')
			->from('order_details')
			->where('order_id', $order_id)
			->get();

		$total_amount = $total_query->row()->subtotal ?: 0;

		// Hitung total_items dari order_details yang tersisa
		$items_query = $this->db
			->select_sum('quantity')
			->from('order_details')
			->where('order_id', $order_id)
			->get();

		$total_items = $items_query->row()->quantity ?: 0;

		// Update order dengan total baru
		$this->db->where('id', $order_id);
		$this->db->update('orders', [
			'total_amount' => $total_amount,
			'total_items' => $total_items,
			'updated_at' => date('Y-m-d H:i:s')
		]);

		// Update juga order_summaries jika perlu
		$this->db->where('order_id', $order_id);
		$summary_exists = $this->db->count_all_results('order_summaries') > 0;

		if ($summary_exists) {
			// Ambil data order terbaru
			$order = $this->db
				->select('*')
				->from('orders')
				->where('id', $order_id)
				->get()
				->row_array();

			// Ambil detail items terbaru
			$items = $this->db
				->select('*')
				->from('order_details')
				->where('order_id', $order_id)
				->get()
				->result_array();

			// Buat summary data baru (JSON)
			$summary_data = json_encode([
				'order' => $order,
				'items' => $items,
				'updated_reason' => 'package_deleted',
				'updated_at' => date('Y-m-d H:i:s')
			]);

			// Update order_summaries
			$this->db->where('order_id', $order_id);
			$this->db->update('order_summaries', [
				'summary_data' => $summary_data
			]);
		}
	}

	public function add_category($package_id)
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/add_category.html");

		// detail package
		$package = $this->m_packages->get_detail_package($package_id);
		if (empty($package)) {
			$this->session->set_flashdata('message', array('msg' => 'Paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		$this->tsmarty->assign('detail', $package);

		// save data
		if ($this->input->post()) {
			$this->form_validation->set_rules('category_name', 'Nama Kategori', 'trim|required');
			$this->form_validation->set_rules('selection_type', 'Tipe Seleksi', 'trim|required');
			$this->form_validation->set_rules('min_items', 'Minimum Item', 'trim|required|numeric|greater_than[0]');
			$this->form_validation->set_rules('max_items', 'Maksimum Item', 'trim|required|numeric|greater_than_equal_to[' . $this->input->post('min_items') . ']');
			$this->form_validation->set_rules('display_order', 'Urutan Tampil', 'trim|required|numeric');

			if ($this->form_validation->run() !== FALSE) {
				$this->db->trans_begin();

				try {
					// Insert into package_categories
					$data_category = [
						'package_id' => $package_id,
						'name' => $this->input->post('category_name'),
						'selection_type' => $this->input->post('selection_type'),
						'display_order' => $this->input->post('display_order'),
						'created_at' => date('Y-m-d H:i:s'),
						'updated_at' => date('Y-m-d H:i:s')
					];

					if ($this->db->insert('package_categories', $data_category)) {
						$category_id = $this->db->insert_id();

						// Add category requirements
						$data_requirement = [
							'package_category_id' => $category_id,
							'min_items' => $this->input->post('min_items'),
							'max_items' => $this->input->post('max_items'),
							'created_at' => date('Y-m-d H:i:s'),
							'updated_at' => date('Y-m-d H:i:s')
						];

						if ($this->db->insert('package_category_requirements', $data_requirement)) {
							$this->db->trans_commit();
							$this->session->set_flashdata('message', array('msg' => 'Kategori berhasil ditambahkan', 'status' => 'success'));
							redirect('master/package/detail/' . $package_id);
						} else {
							$this->db->trans_rollback();
							$this->session->set_flashdata('message', array('msg' => 'Gagal menambahkan persyaratan kategori.', 'status' => 'error'));
							redirect('master/package/add_category/' . $package_id);
						}
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('message', array('msg' => 'Gagal menambahkan kategori.', 'status' => 'error'));
						redirect('master/package/add_category/' . $package_id);
					}
				} catch (Exception $e) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array('msg' => 'Error: ' . $e->getMessage(), 'status' => 'error'));
					redirect('master/package/add_category/' . $package_id);
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/package/add_category/' . $package_id);
			}
		}

		// output
		parent::display();
	}

	public function edit_category($category_id)
	{
		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/edit_category.html");

		// get category detail
		$category = $this->m_packages->get_category_detail($category_id);
		if (empty($category)) {
			$this->session->set_flashdata('message', array('msg' => 'Kategori tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// get package detail
		$package = $this->m_packages->get_detail_package($category['package_id']);
		if (empty($package)) {
			$this->session->set_flashdata('message', array('msg' => 'Paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		$this->tsmarty->assign('detail', $package);
		$this->tsmarty->assign('category', $category);

		// save data
		if ($this->input->post()) {
			$this->form_validation->set_rules('category_name', 'Nama Kategori', 'trim|required');
			$this->form_validation->set_rules('selection_type', 'Tipe Seleksi', 'trim|required');
			$this->form_validation->set_rules('min_items', 'Minimum Item', 'trim|required|numeric');
			$this->form_validation->set_rules('max_items', 'Maksimum Item', 'trim|required|numeric');
			$this->form_validation->set_rules('display_order', 'Urutan Tampil', 'trim|required|numeric');

			if ($this->form_validation->run() !== FALSE) {
				$this->db->trans_begin();

				try {
					// Update package_categories
					$data_category = [
						'name' => $this->input->post('category_name'),
						'selection_type' => $this->input->post('selection_type'),
						'display_order' => $this->input->post('display_order'),
						'updated_at' => date('Y-m-d H:i:s')
					];

					$this->db->where('id', $category_id);
					if ($this->db->update('package_categories', $data_category)) {
						// Update category requirements
						$data_requirement = [
							'min_items' => $this->input->post('min_items'),
							'max_items' => $this->input->post('max_items'),
							'updated_at' => date('Y-m-d H:i:s')
						];

						$this->db->where('package_category_id', $category_id);
						if ($this->db->update('package_category_requirements', $data_requirement)) {
							$this->db->trans_commit();
							$this->session->set_flashdata('message', array('msg' => 'Kategori berhasil diperbarui', 'status' => 'success'));
							redirect('master/package/detail/' . $category['package_id']);
						} else {
							$this->db->trans_rollback();
							$this->session->set_flashdata('message', array('msg' => 'Gagal memperbarui persyaratan kategori.', 'status' => 'error'));
							redirect('master/package/edit_category/' . $category_id);
						}
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('message', array('msg' => 'Gagal memperbarui kategori.', 'status' => 'error'));
						redirect('master/package/edit_category/' . $category_id);
					}
				} catch (Exception $e) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array('msg' => 'Error: ' . $e->getMessage(), 'status' => 'error'));
					redirect('master/package/edit_category/' . $category_id);
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/package/edit_category/' . $category_id);
			}
		}

		// output
		parent::display();
	}


	public function delete_category($category_id)
	{
		// Mulai transaksi database
		$this->db->trans_start();

		try {
			// Ambil detail kategori
			$category = $this->m_packages->get_category_detail($category_id);
			if (empty($category)) {
				throw new Exception('Kategori tidak ditemukan.');
			}

			// Ambil semua produk custom dalam kategori ini
			$custom_products = $this->db
				->select('product_id')
				->from('package_custom_products')
				->where('package_category_id', $category_id)
				->get()
				->result_array();

			// Hapus produk custom products secara permanen
			$this->db->where('package_category_id', $category_id);
			$this->db->delete('package_custom_products');

			// Hapus persyaratan kategori secara permanen
			$this->db->where('package_category_id', $category_id);
			$this->db->delete('package_category_requirements');

			// Hapus kategori secara permanen
			$this->db->where('id', $category_id);
			$this->db->delete('package_categories');

			// Hapus produk terkait dari data_product secara permanen
			if (!empty($custom_products)) {
				$product_ids = array_column($custom_products, 'product_id');
				$this->db->where_in('product_id', $product_ids);
				$this->db->delete('data_product');
			}

			// Commit transaksi
			$this->db->trans_complete();

			// Cek status transaksi
			if ($this->db->trans_status() === FALSE) {
				throw new Exception('Gagal menghapus kategori.');
			}

			// Set pesan sukses
			$this->session->set_flashdata('message', [
				'msg' => 'Kategori dan produk terkait berhasil dihapus permanen.',
				'status' => 'success'
			]);

			// Redirect ke halaman detail paket
			redirect('master/package/detail/' . $category['package_id']);
		} catch (Exception $e) {
			// Rollback transaksi jika terjadi kesalahan
			$this->db->trans_rollback();

			// Set pesan error
			$this->session->set_flashdata('message', [
				'msg' => 'Error: ' . $e->getMessage(),
				'status' => 'error'
			]);

			// Redirect kembali
			redirect('master/package/detail/' . $category['package_id']);
		}
	}

	public function add_product($category_id)
	{
		// get category detail
		$category = $this->m_packages->get_category_detail($category_id);
		if (empty($category)) {
			$this->session->set_flashdata('message', array('msg' => 'Kategori tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// get package detail
		$package = $this->m_packages->get_detail_package($category['package_id']);
		if (empty($package)) {
			$this->session->set_flashdata('message', array('msg' => 'Paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/add_product.html");
		$this->tsmarty->assign('detail', $package);
		$this->tsmarty->assign('category', $category);

		// get available products
		$brand = $package['product_brand'];

		// Perbaikan: Tambahkan kondisi untuk mendapatkan produk yang tersedia
		$products = $this->m_packages->get_available_products($brand, $category['package_id'], $category_id);

		// Debug: Tambahkan logging atau pengecekan produk
		log_message('debug', 'Produk tersedia: ' . json_encode($products));

		$this->tsmarty->assign('products', $products);

		// save data
		if ($this->input->post()) {
			$this->form_validation->set_rules('product_id', 'Produk', 'trim|required');
			$this->form_validation->set_rules('custom_price', 'Harga Kustom', 'trim');
			$this->form_validation->set_rules('is_default', 'Default Item', 'trim');

			if ($this->form_validation->run() !== FALSE) {
				// Check if product already in this category
				$exists = $this->m_packages->check_product_in_category($category_id, $this->input->post('product_id'));
				if ($exists) {
					$this->session->set_flashdata('message', array('msg' => 'Produk sudah ada dalam kategori ini.', 'status' => 'error'));
					redirect('master/package/add_product/' . $category_id);
				}

				// Check if product has stock
				$product_id = $this->input->post('product_id');
				$product_stock = $this->m_packages->get_product_stock($product_id);

				if ($product_stock <= 0) {
					$this->session->set_flashdata('message', array('msg' => 'Produk tidak memiliki stok yang cukup.', 'status' => 'error'));
					redirect('master/package/add_product/' . $category_id);
				}

				$this->db->trans_begin();

				try {
					// If this is being set as default, remove default status from other products in this category
					if ($this->input->post('is_default')) {
						$this->db->where('package_category_id', $category_id);
						$this->db->update('package_custom_products', ['is_default' => 0]);
					}

					$data = [
						'package_category_id' => $category_id,
						'product_id' => $this->input->post('product_id'),
						'custom_price' => ($this->input->post('custom_price') !== '' && $this->input->post('custom_price') !== null) ?
							(float)$this->input->post('custom_price') : NULL,
						'is_default' => $this->input->post('is_default') ? 1 : 0,
						'created_at' => date('Y-m-d H:i:s'),
						'updated_at' => date('Y-m-d H:i:s')
					];

					if ($this->db->insert('package_custom_products', $data)) {
						$this->db->trans_commit();
						$this->session->set_flashdata('message', array('msg' => 'Produk berhasil ditambahkan', 'status' => 'success'));
						redirect('master/package/detail/' . $category['package_id']);
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('message', array('msg' => 'Gagal menambahkan produk.', 'status' => 'error'));
						redirect('master/package/add_product/' . $category_id);
					}
				} catch (Exception $e) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array('msg' => 'Error: ' . $e->getMessage(), 'status' => 'error'));
					redirect('master/package/add_product/' . $category_id);
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/package/add_product/' . $category_id);
			}
		}

		// output
		parent::display();
	}

	public function edit_product($custom_product_id)
	{
		if (empty($this->user_data)) {
			redirect('administrator');
			return;
		}

		// get product detail
		$custom_product = $this->m_packages->get_custom_product_detail($custom_product_id);
		if (empty($custom_product)) {
			$this->session->set_flashdata('message', array('msg' => 'Produk paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// get category detail
		$category = $this->m_packages->get_category_detail($custom_product['package_category_id']);
		if (empty($category)) {
			$this->session->set_flashdata('message', array('msg' => 'Kategori tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// get package detail
		$package = $this->m_packages->get_detail_package($category['package_id']);
		if (empty($package)) {
			$this->session->set_flashdata('message', array('msg' => 'Paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// get product details
		$product = $this->m_products->get_detail_product($custom_product['product_id']);

		// Dapatkan produk yang tersedia untuk dipilih
		$available_products = $this->m_packages->get_available_products(
			$package['product_brand'],
			$category['package_id'],
			$category['id']
		);

		// Tambahkan produk saat ini ke daftar produk yang tersedia
		$available_products[] = [
			'product_id' => $product['product_id'],
			'product_code' => $product['product_code'],
			'product_name' => $product['product_name'],
			'product_price' => $product['product_price']
		];

		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/edit_product.html");
		$this->tsmarty->assign('detail', $package);
		$this->tsmarty->assign('category', $category);
		$this->tsmarty->assign('custom_product', $custom_product);
		$this->tsmarty->assign('product', $product);
		$this->tsmarty->assign('products', $available_products);

		// save data
		if ($this->input->post()) {
			$this->form_validation->set_rules('product_id', 'Produk', 'trim|required');
			$this->form_validation->set_rules('custom_price', 'Harga Kustom', 'trim|numeric');
			$this->form_validation->set_rules('is_default', 'Default Item', 'trim');

			if ($this->form_validation->run() !== FALSE) {
				$this->db->trans_begin();

				try {
					// Cek apakah produk sudah ada di kategori ini
					$existing_product = $this->db
						->where('package_category_id', $category['id'])
						->where('product_id', $this->input->post('product_id'))
						->where('id !=', $custom_product_id)
						->where('deleted_at IS NULL')
						->get('package_custom_products')
						->row_array();

					if ($existing_product) {
						throw new Exception('Produk sudah ada dalam kategori ini.');
					}

					// Jika ingin set sebagai default, hapus default dari produk lain
					if ($this->input->post('is_default')) {
						$this->db->where('package_category_id', $category['id'])
							->update('package_custom_products', ['is_default' => 0]);
					}

					// Update produk
					$data = [
						'product_id' => $this->input->post('product_id'),
						'custom_price' => ($this->input->post('custom_price') !== '' && $this->input->post('custom_price') !== null) ?
							(float)$this->input->post('custom_price') : NULL,
						'is_default' => $this->input->post('is_default') ? 1 : 0,
						'updated_at' => date('Y-m-d H:i:s')
					];

					$this->db->where('id', $custom_product_id);
					$result = $this->db->update('package_custom_products', $data);

					if ($result) {
						$this->db->trans_commit();
						$this->session->set_flashdata('message', array(
							'msg' => 'Produk berhasil diperbarui',
							'status' => 'success'
						));
						redirect('master/package/detail/' . $category['package_id']);
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('message', array(
							'msg' => 'Gagal memperbarui produk.',
							'status' => 'error'
						));
					}
				} catch (Exception $e) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array(
						'msg' => 'Error: ' . $e->getMessage(),
						'status' => 'error'
					));
				}
			} else {
				$this->session->set_flashdata('message', array(
					'msg' => validation_errors(),
					'status' => 'error'
				));
			}

			redirect('master/package/edit_product/' . $custom_product_id);
		}

		// output
		parent::display();
	}

	public function delete_product($custom_product_id)
	{
		// Mulai transaksi database
		$this->db->trans_start();

		try {
			// Ambil detail produk custom
			$custom_product = $this->m_packages->get_custom_product_detail($custom_product_id);
			if (empty($custom_product)) {
				throw new Exception('Produk paket tidak ditemukan.');
			}

			// Ambil detail kategori
			$category = $this->m_packages->get_category_detail($custom_product['package_category_id']);
			if (empty($category)) {
				throw new Exception('Kategori tidak ditemukan.');
			}

			// Validasi minimal produk dalam kategori
			$produk_dalam_kategori = $this->db
				->where('package_category_id', $category['id'])
				->count_all_results('package_custom_products');

			if ($produk_dalam_kategori <= 1) {
				throw new Exception('Tidak dapat menghapus produk. Setiap kategori harus memiliki setidaknya satu produk.');
			}

			// Hapus produk custom dari package_custom_products secara permanen
			$this->db->where('id', $custom_product_id);
			$this->db->delete('package_custom_products');

			// Hapus produk dari data_product secara permanen
			$this->db->where('product_id', $custom_product['product_id']);
			$this->db->delete('data_product');

			// Commit transaksi
			$this->db->trans_complete();

			// Cek status transaksi
			if ($this->db->trans_status() === FALSE) {
				throw new Exception('Gagal menghapus produk.');
			}

			// Set pesan sukses
			$this->session->set_flashdata('message', [
				'msg' => 'Produk berhasil dihapus permanen.',
				'status' => 'success'
			]);

			// Redirect ke halaman detail paket
			redirect('master/package/detail/' . $category['package_id']);
		} catch (Exception $e) {
			// Rollback transaksi jika terjadi kesalahan
			$this->db->trans_rollback();

			// Set pesan error
			$this->session->set_flashdata('message', [
				'msg' => 'Error: ' . $e->getMessage(),
				'status' => 'error'
			]);

			// Redirect kembali
			redirect('master/package/detail/' . $category['package_id']);
		}
	}

	// Add product bulk to package category
	public function add_product_bulk($category_id)
	{
		// get category detail
		$category = $this->m_packages->get_category_detail($category_id);
		if (empty($category)) {
			$this->session->set_flashdata('message', array('msg' => 'Kategori tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// get package detail
		$package = $this->m_packages->get_detail_package($category['package_id']);
		if (empty($package)) {
			$this->session->set_flashdata('message', array('msg' => 'Paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/add_product_bulk.html");
		$this->tsmarty->assign('detail', $package);
		$this->tsmarty->assign('category', $category);

		// get all available products
		$brand = $package['product_brand'];
		$products = $this->m_packages->get_available_products($brand, $category['package_id'], $category_id);
		$this->tsmarty->assign('products', $products);

		// save data
		if ($this->input->post()) {
			$this->form_validation->set_rules('product_ids[]', 'Produk', 'trim|required');

			if ($this->form_validation->run() !== FALSE) {
				$product_ids = $this->input->post('product_ids');
				$use_default_price = $this->input->post('use_default_price') ? true : false;

				if (empty($product_ids)) {
					$this->session->set_flashdata('message', array('msg' => 'Tidak ada produk yang dipilih.', 'status' => 'error'));
					redirect('master/package/add_product_bulk/' . $category_id);
				}

				$this->db->trans_begin();

				try {
					$success_count = 0;
					$error_count = 0;

					foreach ($product_ids as $product_id) {
						// Check if product already in this category
						$exists = $this->m_packages->check_product_in_category($category_id, $product_id);
						if ($exists) {
							$error_count++;
							continue;
						}

						$data = [
							'package_category_id' => $category_id,
							'product_id' => $product_id,
							'custom_price' => $use_default_price ? NULL : 0, // NULL means use product price
							'is_default' => 0,
							'created_at' => date('Y-m-d H:i:s'),
							'updated_at' => date('Y-m-d H:i:s')
						];

						if ($this->db->insert('package_custom_products', $data)) {
							$success_count++;
						} else {
							$error_count++;
						}
					}

					if ($success_count > 0) {
						$this->db->trans_commit();
						$this->session->set_flashdata('message', array('msg' => $success_count . ' produk berhasil ditambahkan' . ($error_count > 0 ? ' dan ' . $error_count . ' produk gagal ditambahkan (sudah ada)' : ''), 'status' => 'success'));
						redirect('master/package/detail/' . $category['package_id']);
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('message', array('msg' => 'Gagal menambahkan produk.', 'status' => 'error'));
						redirect('master/package/add_product_bulk/' . $category_id);
					}
				} catch (Exception $e) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array('msg' => 'Error: ' . $e->getMessage(), 'status' => 'error'));
					redirect('master/package/add_product_bulk/' . $category_id);
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/package/add_product_bulk/' . $category_id);
			}
		}

		// output
		parent::display();
	}

	// Update order display of categories
	public function update_category_order($package_id)
	{
		// get package detail
		$package = $this->m_packages->get_detail_package($package_id);
		if (empty($package)) {
			$this->output->set_content_type('application/json');
			echo json_encode(['success' => false, 'message' => 'Paket tidak ditemukan.']);
			return;
		}

		// Read JSON input
		$raw_input = $this->input->raw_input_stream;
		$order_data = json_decode($raw_input, true);

		if (empty($order_data) || !isset($order_data['categories'])) {
			$this->output->set_content_type('application/json');
			echo json_encode(['success' => false, 'message' => 'Data urutan tidak valid.']);
			return;
		}

		$this->db->trans_begin();

		try {
			$categories = $order_data['categories'];

			foreach ($categories as $index => $category_id) {
				$display_order = $index + 1;
				$this->db->where('id', $category_id);
				$this->db->where('package_id', $package_id);
				$this->db->update('package_categories', ['display_order' => $display_order]);
			}

			$this->db->trans_commit();
			$this->output->set_content_type('application/json');
			echo json_encode(['success' => true, 'message' => 'Urutan kategori berhasil diperbarui.']);
		} catch (Exception $e) {
			$this->db->trans_rollback();
			$this->output->set_content_type('application/json');
			echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
		}
	}

	public function update_product_pricing($custom_product_id)
	{
		// Validasi akses
		if (empty($this->user_data)) {
			redirect('administrator');
			return;
		}

		// get product detail
		$custom_product = $this->m_packages->get_custom_product_detail($custom_product_id);
		if (empty($custom_product)) {
			$this->session->set_flashdata('message', array('msg' => 'Produk paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// get category detail
		$category = $this->m_packages->get_category_detail($custom_product['package_category_id']);
		if (empty($category)) {
			$this->session->set_flashdata('message', array('msg' => 'Kategori tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// get package detail
		$package = $this->m_packages->get_detail_package($category['package_id']);
		if (empty($package)) {
			$this->session->set_flashdata('message', array('msg' => 'Paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// get product details
		$product = $this->m_products->get_detail_product($custom_product['product_id']);

		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/update_product_pricing.html");
		$this->tsmarty->assign('detail', $package);
		$this->tsmarty->assign('category', $category);
		$this->tsmarty->assign('custom_product', $custom_product);
		$this->tsmarty->assign('product', $product);

		// save data
		if ($this->input->post()) {
			// Tambahkan validasi yang lebih ketat
			$this->form_validation->set_rules('custom_price', 'Harga Kustom', 'trim|numeric|greater_than_equal_to[0]');

			if ($this->form_validation->run() !== FALSE) {
				$this->db->trans_begin();

				$custom_price_input = $this->input->post('custom_price');
				if ($custom_price_input !== '' && $custom_price_input !== null) {
					if (!is_numeric($custom_price_input) || (float)$custom_price_input < 0) {
						$this->session->set_flashdata('message', array(
							'msg' => 'Harga kustom harus berupa angka dan tidak boleh negatif.',
							'status' => 'error'
						));
						redirect('master/package/update_product_pricing/' . $custom_product_id);
						return;
					}
				}

				try {
					// Persiapkan data yang akan diupdate
					$custom_price_input = $this->input->post('custom_price');
					$update_data = [
						'custom_price' => ($custom_price_input !== '' && $custom_price_input !== null && is_numeric($custom_price_input)) ?
							(float)$custom_price_input : NULL,
						'updated_at' => date('Y-m-d H:i:s')
					];

					// Update di tabel package_custom_products
					$this->db->where('id', $custom_product_id);
					$result = $this->db->update('package_custom_products', $update_data);

					if ($result) {
						$this->db->trans_commit();
						$this->session->set_flashdata('message', array(
							'msg' => 'Harga produk paket berhasil diperbarui',
							'status' => 'success'
						));
						redirect('master/package/detail/' . $category['package_id']);
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('message', array(
							'msg' => 'Gagal memperbarui harga produk paket.',
							'status' => 'error'
						));
					}
				} catch (Exception $e) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array(
						'msg' => 'Error: ' . $e->getMessage(),
						'status' => 'error'
					));
				}
			} else {
				// Jika validasi gagal
				$this->session->set_flashdata('message', array(
					'msg' => validation_errors(),
					'status' => 'error'
				));
			}

			// Redirect kembali ke halaman update
			redirect('master/package/update_product_pricing/' . $custom_product_id);
		}

		// output
		parent::display();
	}

	public function toggle_product_status($custom_product_id)
	{
		// get product detail
		$custom_product = $this->m_packages->get_custom_product_detail($custom_product_id);
		if (empty($custom_product)) {
			$this->output->set_content_type('application/json');
			echo json_encode(['success' => false, 'message' => 'Produk paket tidak ditemukan.']);
			return;
		}

		// get category detail to get package id
		$category = $this->m_packages->get_category_detail($custom_product['package_category_id']);
		if (empty($category)) {
			$this->output->set_content_type('application/json');
			echo json_encode(['success' => false, 'message' => 'Kategori tidak ditemukan.']);
			return;
		}

		try {
			// Toggle active status (using is_active field)
			$current_status = $custom_product['is_active'] ?? 1;
			$new_status = $current_status == 1 ? 0 : 1;

			$this->db->where('id', $custom_product_id);
			$this->db->update('package_custom_products', [
				'is_active' => $new_status,
				'updated_at' => date('Y-m-d H:i:s')
			]);

			$this->output->set_content_type('application/json');
			echo json_encode([
				'success' => true,
				'message' => 'Status produk berhasil diubah.',
				'new_status' => $new_status,
				'product_id' => $custom_product_id
			]);
		} catch (Exception $e) {
			$this->output->set_content_type('application/json');
			echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
		}
	}

	// Clone package
	public function clone_package($package_id)
	{
		// get package detail
		$package = $this->m_packages->get_detail_package($package_id);
		if (empty($package)) {
			$this->session->set_flashdata('message', array('msg' => 'Paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/clone_package.html");
		$this->tsmarty->assign('detail', $package);

		// list category
		$cat = $this->m_categories->get_list_category();
		$this->tsmarty->assign('categories', $cat);

		// list brands
		$brands = $this->m_brands->get_list_brand();
		$this->tsmarty->assign('brands', $brands);

		// save data
		if ($this->input->post()) {
			$this->form_validation->set_rules('product_code', 'Kode Produk', 'trim|required');
			$this->form_validation->set_rules('product_name', 'Nama Produk', 'trim|required');
			$this->form_validation->set_rules('product_st', 'Status Produk', 'trim|required');

			if ($this->form_validation->run() !== FALSE) {
				// cek product code
				if ($this->m_products->is_exist_product_code([$this->input->post('product_code')])) {
					$this->session->set_flashdata('message', array('msg' => 'Kode Produk sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
					redirect('master/package/clone_package/' . $package_id);
				}

				// cek product name
				if ($this->m_products->is_exist_product_name([$this->input->post('product_name')])) {
					$this->session->set_flashdata('message', array('msg' => 'Nama Produk sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
					redirect('master/package/clone_package/' . $package_id);
				}

				$this->db->trans_begin();

				try {
					// Create new product from existing
					$data_product = [
						'product_parent' => '0',
						'cat_id' => $this->input->post('cat_id') ?: $package['cat_id'],
						'product_brand' => $this->input->post('product_brand') ?: $package['product_brand'],
						'product_code' => $this->input->post('product_code'),
						'product_name' => $this->input->post('product_name'),
						'product_desc' => $this->input->post('product_desc') ?: $package['product_desc'],
						'product_price' => $this->input->post('product_price') ?: $package['product_price'],
						'product_komposisi' => $package['product_komposisi'],
						'product_no' => $this->input->post('product_no') ?: $package['product_no'],
						'expired_date' => $package['expired_date'],
						'product_netto' => $package['product_netto'],
						'product_st' => $this->input->post('product_st'),
						'ek_marketing' => $package['ek_marketing'],
						'ek_customer' => $package['ek_customer'],
						'ek_outlet' => $package['ek_outlet'],
						'product_promote' => $this->input->post('product_promote') ?: $package['product_promote'],
						'is_package' => '1',
						'package_type' => $package['package_type'],
						'created' => date('Y-m-d H:i:s'),
						'created_by' => $this->user_data['user_id'],
						'modified' => date('Y-m-d H:i:s'),
						'modified_by' => $this->user_data['user_id'],
					];

					// Clone product image
					if ($package['product_pict']) {
						$source_path = './resource/assets-frontend/dist/product/' . $package['product_pict'];
						$new_image_name = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . pathinfo($package['product_pict'], PATHINFO_EXTENSION);
						$target_path = './resource/assets-frontend/dist/product/' . $new_image_name;

						if (file_exists($source_path)) {
							copy($source_path, $target_path);
							$data_product['product_pict'] = $new_image_name;
						}
					} else if ($_FILES['product_pict']['tmp_name'] !== '') {
						// If new image uploaded
						$temp = explode(".", $_FILES['product_pict']['name']);
						$ext = end($temp);
						// upload image
						$config['upload_path'] = './resource/assets-frontend/dist/product/';
						$config['allowed_types'] = 'svg|gif|jpg|png';
						$config['file_name'] = str_replace(' ', '-', strtolower($this->input->post('product_name'))) . '.' . $ext;
						$config['overwrite'] = TRUE;

						$this->load->library('upload', $config);
						if (!$this->upload->do_upload('product_pict')) {
							$error = array('error' => strip_tags($this->upload->display_errors()));
							$this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
							redirect('master/package/clone_package/' . $package_id);
						}
						$data_upload = $this->upload->data();
						$data_product['product_pict'] = $data_upload['file_name'];
					}

					// Insert new product
					if ($this->m_products->add_product($data_product)) {
						// Get new product ID
						$new_product_id = $this->db->insert_id();

						// Create new package
						$data_package = [
							'product_id' => $new_product_id,
							'name' => $this->input->post('product_name'),
							'description' => $this->input->post('product_desc') ?: $package['product_desc'],
							'base_price' => $this->input->post('product_price') ?: $package['product_price'],
							'status' => ($this->input->post('product_st') == '0') ? 'active' : 'inactive',
							'type' => $package['type'],
							'created_at' => date('Y-m-d H:i:s'),
							'updated_at' => date('Y-m-d H:i:s')
						];

						if ($this->db->insert('packages', $data_package)) {
							$new_package_id = $this->db->insert_id();

							// Clone categories and products
							$categories = $this->m_packages->get_package_categories($package_id);

							foreach ($categories as $category) {
								// Create new category
								$data_category = [
									'package_id' => $new_package_id,
									'name' => $category['name'],
									'selection_type' => $category['selection_type'],
									'display_order' => $category['display_order'],
									'created_at' => date('Y-m-d H:i:s'),
									'updated_at' => date('Y-m-d H:i:s')
								];

								if ($this->db->insert('package_categories', $data_category)) {
									$new_category_id = $this->db->insert_id();

									// Create category requirements
									$data_requirement = [
										'package_category_id' => $new_category_id,
										'min_items' => $category['min_items'],
										'max_items' => $category['max_items'],
										'created_at' => date('Y-m-d H:i:s'),
										'updated_at' => date('Y-m-d H:i:s')
									];

									$this->db->insert('package_category_requirements', $data_requirement);

									// Clone products in this category
									$products = $this->m_packages->get_package_products($package_id, $category['id']);

									foreach ($products as $product) {
										$data_product = [
											'package_category_id' => $new_category_id,
											'product_id' => $product['product_id'],
											'custom_price' => $product['custom_price'],
											'is_default' => $product['is_default'],
											'created_at' => date('Y-m-d H:i:s'),
											'updated_at' => date('Y-m-d H:i:s')
										];

										$this->db->insert('package_custom_products', $data_product);
									}
								}
							}

							$this->db->trans_commit();
							$this->session->set_flashdata('message', array('msg' => 'Paket berhasil diduplikasi', 'status' => 'success'));
							redirect('master/package/detail/' . $new_package_id);
						} else {
							$this->db->trans_rollback();
							$this->session->set_flashdata('message', array('msg' => 'Gagal membuat paket baru.', 'status' => 'error'));
							redirect('master/package/clone_package/' . $package_id);
						}
					} else {
						$this->db->trans_rollback();
						$this->session->set_flashdata('message', array('msg' => 'Gagal membuat produk baru.', 'status' => 'error'));
						redirect('master/package/clone_package/' . $package_id);
					}
				} catch (Exception $e) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array('msg' => 'Error: ' . $e->getMessage(), 'status' => 'error'));
					redirect('master/package/clone_package/' . $package_id);
				}
			} else {
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
				redirect('master/package/clone_package/' . $package_id);
			}
		}

		// output
		parent::display();
	}


	public function manage_stock($package_id)
	{
		// detail package
		$package = $this->m_packages->get_detail_package($package_id);
		if (empty($package)) {
			$this->session->set_flashdata('message', array('msg' => 'Paket tidak ditemukan.', 'status' => 'error'));
			redirect('master/package');
		}

		// Set template content
		$this->tsmarty->assign("template_content", "master/packages/manage_stock.html");
		$this->tsmarty->assign('detail', $package);

		// get categories and products
		$categories = $this->m_packages->get_package_categories($package_id);
		$this->tsmarty->assign('categories', $categories);

		$products_by_category = [];
		foreach ($categories as $category) {
			$products = $this->m_packages->get_package_products($package_id, $category['id']);
			$products_by_category[$category['id']] = $products;
		}
		$this->tsmarty->assign('products_by_category', $products_by_category);

		// Handle form submission untuk update stok
		if ($this->input->post()) {
			$this->db->trans_begin();

			try {
				$product_ids = $this->input->post('product_id');
				$stock_changes = $this->input->post('stock_change');
				$is_increments = $this->input->post('is_increment');

				$success_count = 0;
				$error_messages = [];

				foreach ($product_ids as $index => $product_id) {
					$stock_change = isset($stock_changes[$index]) ? intval($stock_changes[$index]) : 0;
					$is_increment = isset($is_increments[$index]) && $is_increments[$index] == 1;

					// Skip jika tidak ada perubahan stok
					if ($stock_change == 0) {
						continue;
					}

					// Update stok
					$result = $this->m_packages->update_product_stock($product_id, $stock_change, $is_increment);

					if ($result) {
						$success_count++;
					} else {
						$product_info = $this->db->select('product_name')
							->from('data_product')
							->where('product_id', $product_id)
							->get()
							->row_array();

						$product_name = $product_info ? $product_info['product_name'] : "Produk #$product_id";
						$error_messages[] = "Gagal memperbarui stok untuk $product_name";
					}
				}

				if ($success_count > 0) {
					$this->db->trans_commit();
					$msg = "Berhasil memperbarui stok untuk $success_count produk";
					if (!empty($error_messages)) {
						$msg .= ". " . count($error_messages) . " produk gagal diperbarui.";
					}
					$this->session->set_flashdata('message', array('msg' => $msg, 'status' => 'success'));
				} else if (!empty($error_messages)) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array(
						'msg' => 'Gagal memperbarui stok: ' . implode(', ', $error_messages),
						'status' => 'error'
					));
				} else {
					$this->db->trans_rollback();
					$this->session->set_flashdata('message', array(
						'msg' => 'Tidak ada perubahan stok yang dilakukan',
						'status' => 'info'
					));
				}

				redirect('master/package/manage_stock/' . $package_id);
			} catch (Exception $e) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('message', array(
					'msg' => 'Error: ' . $e->getMessage(),
					'status' => 'error'
				));
				redirect('master/package/manage_stock/' . $package_id);
			}
		}

		// output
		parent::display();
	}

	// Update stock for a single product
	public function update_product_stock($product_id)
	{
		// Get product info
		$product = $this->db->select('product_id, product_name, stock')
			->from('data_product')
			->where('product_id', $product_id)
			->get()
			->row_array();

		if (empty($product)) {
			$this->output->set_content_type('application/json');
			return $this->output->set_output(json_encode([
				'success' => false,
				'message' => 'Produk tidak ditemukan'
			]));
		}

		// Get request data
		$stock_change = $this->input->post('stock_change');
		$is_increment = $this->input->post('is_increment') == '1';

		if (!is_numeric($stock_change)) {
			$this->output->set_content_type('application/json');
			return $this->output->set_output(json_encode([
				'success' => false,
				'message' => 'Perubahan stok harus berupa angka'
			]));
		}

		// Update stock
		$result = $this->m_packages->update_product_stock($product_id, $stock_change, $is_increment);

		// Get new stock
		$new_stock = $this->m_packages->get_product_stock($product_id);

		$this->output->set_content_type('application/json');
		return $this->output->set_output(json_encode([
			'success' => $result,
			'message' => $result ? 'Stok berhasil diperbarui' : 'Gagal memperbarui stok',
			'new_stock' => $new_stock
		]));
	}
}
