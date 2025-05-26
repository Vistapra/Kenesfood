<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

class Outlets extends ApplicationBase
{
    function __construct()
	{
        // parent constructor
        parent::__construct();
        $this->load->model('settings/M_outlets', 'm_outlets');
    }

    // index
    function index() {
        $this->tsmarty->assign("template_content", "settings/outlets/index.html");
        // search
        $keyword = '';        
        $search = $this->session->userdata('search_outlet');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_outlet");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_outlet", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('settings/outlets/index/');
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

    /**
     *  DETAIL, ADD, EDIT, DELETE
     */

    // detail
    public function detail($id) {
        $this->tsmarty->assign("template_content", "settings/outlets/detail.html");
        // get data
        $outlet = $this->m_outlets->get_detail_outlet($id);
        $this->tsmarty->assign("outlet", $outlet);
        // output
        parent::display();
    }
    
    // add
    public function add() {
        // set template content
        $this->tsmarty->assign("template_content", "settings/outlets/add.html");
        // generate new code
        $outlet_code = $this->m_outlets->get_last_code();
        $this->tsmarty->assign('outlet_code', $outlet_code);
        // add process
        if  ($this->input->post()) {
            $this->form_validation->set_rules('outlet_code', 'Kode Outlet', 'trim|required');
            $this->form_validation->set_rules('outlet_name', 'Nama Outlet', 'trim|required');
            $this->form_validation->set_rules('outlet_address', 'Alamat', 'trim');
            $this->form_validation->set_rules('kota', 'Kota', 'trim');
            $this->form_validation->set_rules('latitude', 'Latitude', 'trim');
            $this->form_validation->set_rules('longitude', 'Longitude', 'trim');
            $this->form_validation->set_rules('maps', 'Google Maps Link', 'trim');
            $this->form_validation->set_rules('outlet_phone', 'Telepon', 'trim');
            $this->form_validation->set_rules('outlet_status', 'Status', 'trim|required');
            $this->form_validation->set_rules('outlet_highlight', 'Status', 'trim|required');
			$this->form_validation->set_rules('hour_open', 'Open Hour', 'required');
			$this->form_validation->set_rules('hour_close', 'Close Hour', 'required');

			if(!$this->isValidTime($this->input->post('hour_open')) || !$this->isValidTime($this->input->post('hour_close')))
			{
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('settings/outlets/add');
			}

            if ($this->form_validation->run() == FALSE) {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('settings/outlets/add');
            } else {
                $id = $this->m_outlets->get_last_id();
                $data = array(
                    'outlet_id' =>$id,
                    'outlet_code' =>$this->input->post('outlet_code'),
                    'outlet_name' => $this->input->post('outlet_name'),
                   
                    'outlet_address' => $this->input->post('outlet_address'),
                    'kota' => $this->input->post('kota'),
                    'latitude' => $this->input->post('latitude'),
                    'longitude' => $this->input->post('longitude'),
                    'maps' => $this->input->post('maps'),
                    'outlet_phone' => $this->input->post('outlet_phone'),
                    'outlet_status' => $this->input->post('outlet_status'),
                    'outlet_highlight' => $this->input->post('outlet_highlight'),
					'hour_open' => $this->input->post('hour_open'),
					'hour_close' => $this->input->post('hour_close'),
					'count_table' => $this->input->post('count_table'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id'],
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                );
                //jika foto di upload
                if ($_FILES['outlet_img']['tmp_name']) {
                    $temp = explode(".", $_FILES['outlet_img']['name']);
                    $ext = end($temp);
                    $config['upload_path']          = './resource/assets-frontend/dist/outlet/';
                    $config['allowed_types']        = 'gif|jpg|png|PNG|jpeg';
                    $config['max_size']             = 3000;
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('outlet_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('outlet_img')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect(site_url('settings/outlets/add'));
                    }
                    $data_logo = $this->upload->data();
                    $data['outlet_img'] = $data_logo['file_name'];
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Foto Outlet tidak boleh kosong', 'status' => 'error'));
                    redirect('settings/outlets/add');
                }
                // simpan data
                if($this->m_outlets->add_outlets($data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan.', 'status' => 'success'));
                    redirect('settings/outlets');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('settings/outlets/add');
                }
            }
        }
        // output
        parent::display();
    }
    
    // edit
    public function edit($id) {
        $this->tsmarty->assign("template_content", "settings/outlets/edit.html");
        // get data
        $outlet = $this->m_outlets->get_detail_outlet($id);
        $this->tsmarty->assign("outlet", $outlet);
        // add process
        if  ($this->input->post()) {
            $this->form_validation->set_rules('outlet_code', 'Kode Outlet', 'trim|required');
            $this->form_validation->set_rules('outlet_name', 'Nama Outlet', 'trim|required');
            $this->form_validation->set_rules('outlet_address', 'Alamat', 'trim');
            $this->form_validation->set_rules('kota', 'Kota', 'trim');
            $this->form_validation->set_rules('latitude', 'Latitude', 'trim');
            $this->form_validation->set_rules('longitude', 'Longitude', 'trim');
            $this->form_validation->set_rules('maps', 'Google Maps Link', 'trim');
            $this->form_validation->set_rules('outlet_phone', 'Telepon', 'trim');
            $this->form_validation->set_rules('outlet_status', 'Status', 'trim|required');
            $this->form_validation->set_rules('outlet_highlight', 'Status', 'trim|required');

			if(!$this->isValidTime($this->input->post('hour_open')) || !$this->isValidTime($this->input->post('hour_close')))
			{
				$this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('settings/outlets/add');
			}

            if ($this->form_validation->run() == FALSE) {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('settings/outlets/edit/' . $id);
            } else {
                $data = array(
                    'outlet_code' =>$this->input->post('outlet_code'),
                    'outlet_name' => $this->input->post('outlet_name'),
                    'outlet_address' => $this->input->post('outlet_address'),
                    'kota' => $this->input->post('kota'),
                    'latitude' => $this->input->post('latitude'),
                    'longitude' => $this->input->post('longitude'),
                    'maps' => $this->input->post('maps'),
                    'outlet_phone' => $this->input->post('outlet_phone'),
                    'outlet_status' => $this->input->post('outlet_status'),
                    'outlet_highlight' => $this->input->post('outlet_highlight'),
					'hour_open' => $this->input->post('hour_open'),
					'hour_close' => $this->input->post('hour_close'),
					'count_table' => $this->input->post('count_table'),
                    'modified' => date('Y-m-d H:i:s'),
                    'modified_by' => $this->user_data['user_id'],
                );
                //jika foto di upload
                if ($_FILES['outlet_img']['tmp_name']) {
                    $temp = explode(".", $_FILES['outlet_img']['name']);
                    $ext = end($temp);
                    $config['upload_path']          = './resource/assets-frontend/dist/outlet/';
                    $config['allowed_types']        = 'gif|jpg|png|PNG|jpeg';
                    $config['max_size']             = 3000;
                    $config['file_name']            = str_replace(' ', '-', strtolower($this->input->post('outlet_name'))) . '.' . $ext;
                    $config['overwrite']            = TRUE;

                    $this->load->library('upload', $config);
                    if (!$this->upload->do_upload('outlet_img')){
                        $error = array('error' => strip_tags($this->upload->display_errors()));
                        $this->session->set_flashdata('message', array('msg' => $error['error'], 'status' => 'error'));
                        redirect('settings/outlets/edit/' . $id);
                    }
                    $data_logo = $this->upload->data();
                    $data['outlet_img'] = $data_logo['file_name'];
                } 
                // simpan data
                if($this->m_outlets->update_outlets($id, $data)) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan.', 'status' => 'success'));
                    redirect('settings/outlets/edit/' . $id);
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('settings/outlets/edit/' . $id);
                }
            }
        }
        // output
        parent::display();
    }
    
    // delete
    public function delete($id) {
        // get data
        $outlet = $this->m_outlets->get_detail_outlet($id);
        $this->tsmarty->assign("outlet", $outlet);
        // delete
        if ($this->m_outlets->delete_outlets($id)) {
            $file_path = FCPATH . './resource/assets-frontend/dist/outlet/' . $outlet['outlet_img'];
            if (file_exists($file_path)) {
                unlink($file_path);
            } 
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        } else {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }
        redirect('settings/outlets');
    }

	public function download()
	{
		switch ($this->input->get("action"))
		{
			case "QRCODETABLE":
				$this->printQrCodeTable();
				break;
			default:
				return redirect('settings/outlet');
		}
    }

	private function isValidTime($time)
	{
		return preg_match('/^(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])$/', $time);
    }

	private function printQrCodeTable()
	{
        $pageDimension  = [150, 150];

        $data = $this->m_outlets->get_detail_outlet($this->input->get('outletId'));
        $pdf  = new TCPDF('L', 'mm', $pageDimension, true, 'UTF-8', false);

        $pdf->Setmargins(0, 0, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(TRUE, 0);

		// Set QR dimension and position on pdf
		$qrDimension   = [80, 80];
		$startWriteQr  = [
			$pageDimension[0]/2-$qrDimension[0]/2,
			$pageDimension[1]/2-$qrDimension[1]/2,
		];

		// set style for barcode
        $style = [
            'border'        => 0,
            'vpadding'      => 0,
            'hpadding'      => 0,
            'fgcolor'       => array(244,239,217),
            'bgcolor'       => array(147, 108, 77),
            'module_width'  => 1,
            'module_height' => 1,
		];

		for ($i = 1; $i <= $data["count_table"]; $i++)
		{
			$pdf->AddPage();
			$pdf->Rect(0,0,$pageDimension[0],$pageDimension[1], "DF", "", array(147, 108, 77));
			$pdf->setXY($startWriteQr[0], $startWriteQr[1]-20);
			$pdf->SetFont('Times', '', 16);
			$pdf->SetTextColor(244, 239, 217);
			$pdf->Write(0, "MENU ISTIMEWA MENANTI", '', false);
			$pdf->setXY($startWriteQr[0], $startWriteQr[1]-10);
			$pdf->Write(0, "SCAN UNTUK MEMILIH:", '', false);
			$pdf->write2DBarcode(
				site_url('order')."?outletId={$data["outlet_id"]}&tableId={$i}",
				'QRCODE,H',
				$startWriteQr[0],
				$startWriteQr[1],
				$qrDimension[0],
				$qrDimension[1],
				$style,
				'N'
			);
			$pdf->SetFont('Times', '', 20);
			$pdf->setXY($pageDimension[1]/2-10, $startWriteQr[1]+$qrDimension[1]+5);
			$pdf->Write(0,  "MEJA {$i}", '', false);
		}

        $pdf->Output("Outlet {$data["outlet_name"]} - Kode QR Meja.pdf", 'I');
	}
}