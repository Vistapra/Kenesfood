<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PrivateBase.php');

// --
class Marketings extends ApplicationBase
{

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('master/M_marketings', 'm_marketings');
        $this->load->helper('file');
    }

    public function download() {
        $infoColor     = array(244,239,217);
        $marketingId   = $this->input->get('marketingId');
        $pageDimension = [89, 51];

        $data = $this->m_marketings->get_detail_marketing($marketingId);
        $pdf  = new TCPDF('L', 'mm', $pageDimension, true, 'UTF-8', false);
        $html = '<h1 style="color:white; margin-bottom: 0px;">'.strtoupper($data['nama_marketing']).'</h1>
        <h4 style="color:white;font-size:5px; margin-top: 0px">Marketing</h4>';

        // set style for barcode
        $style = array(
            'border'        => 0,
            'vpadding'      => 'auto',
            'hpadding'      => 'auto',
            'fgcolor'       => $infoColor,
            'bgcolor'       => array(147, 108, 77),
            'module_width'  => 1,
            'module_height' => 1
        );

        $infoUrl    = site_url('ekatalog/')."?type=marketing&marketingId=".$marketingId;
        $pathBgCard = './resource/assets-frontend/dist/marketing/bg_business_card.png';

        $pdf->Setmargins(0, 0, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(TRUE, 0);

        $pdfWidth = $pdf->getPageWidth();
        $pdfHeight = $pdf->getPageHeight();

        // Set information background
        $dimBgInformation = [$pdfWidth, 30];
        $posBgInformation = [0, $pdfHeight/2-$dimBgInformation[1]/2];

        // Set information background separator
        $dimSeparatorBg = [$pdfWidth, 1.5];

        // Set QR
        $qrDimension   = [20, 20];
        $startWriteQr  = [
            ($pageDimension[0]-$qrDimension[0]-5),
            $posBgInformation[1]+5
        ];

        // Set image background
        $pdf->Rect(0, 0, $pdfWidth, $pdfHeight, 'DF', "",  array(255, 254, 244));
        $pdf->SetAlpha(0.3);
        $pdf->Image($pathBgCard, 0, 0, $pdfWidth, $pdfHeight, 'PNG');
        $pdf->SetAlpha(1);

        // Set layout
        $pdf->Rect($posBgInformation[0], $posBgInformation[1], $dimBgInformation[0], $dimBgInformation[1], 'DF', "", array(147, 108, 77));
        $pdf->Rect(0, $posBgInformation[1]-$dimSeparatorBg[1], $dimSeparatorBg[0], $dimSeparatorBg[1], 'DF', '', array(71, 45, 27));
        $pdf->Rect(0, $posBgInformation[1]+$dimBgInformation[1], $dimSeparatorBg[0], $dimSeparatorBg[1], 'DF', '', array(71, 45, 27));
        

        // Set information content

        // Set marketing name
        $pdf->setXY(5, $posBgInformation[1]+5);
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor($infoColor[0], $infoColor[1], $infoColor[2]);
        $pdf->Write(5, strtoupper($data['nama_marketing']), '', false);
        $pdf->setXY(5, $pdf->GetY()+6);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Write(5, $data['phone_marketing'], '', false);
        $pdf->SetXY(5, $pdf->GetY()+8);
        $linkHtml = '<a href="'.$infoUrl.'" style="color: #F4EFD9; text-decoration: underline;">kenesfood.com</a>';
        $pdf->writeHTML($linkHtml, true, false, true, false, '');

        $qr = $pdf->write2DBarcode($infoUrl, 'QRCODE,H', $startWriteQr[0], $startWriteQr[1], $qrDimension[0], $qrDimension[1], $style, 'N');
        $pdf->Output('marketing-'.$data['marketing_id'].'-'.$data['nama_marketing'].'.pdf', 'I');
    }

    // index
    public function index() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/marketings/index.html");

        // search
        $keyword = '';
        $search = $this->session->userdata('search');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search");
            } else {
                $keyword = $this->input->post('keyword');
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('master/marketings/index/');
        $config['total_rows'] = $this->m_marketings->get_total_data($keyword);
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
        $data = $this->m_marketings->get_list_data($params, $keyword);
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    // add
    public function add() {
        // Set template content
        $this->tsmarty->assign("template_content", "master/marketings/add.html");
        // save data
        if($this->input->post()) {
            $this->form_validation->set_rules('nama_marketing', 'Nama Marketing', 'required|trim');
            $this->form_validation->set_rules('phone_marketing', "Phone Marketing", 'required|trim');
            $this->form_validation->set_rules('marketing_st', 'Marketing Status', 'trim|required');
           
            if ($this->form_validation->run() !== FALSE) {
                if($this->m_marketings->is_exist_by_name($this->input->post('nama_marketing'))) {
                    $this->session->set_flashdata('message', array('msg' => 'Nama marketing sudah ada. Silahkan periksa kembali', 'status' => 'error'));
                    redirect('master/marketings/add');
                }

                if($this->m_marketings->is_exist_by_field([
                    [
                        "field" => 'phone_marketing',
                        "query" => ['=', $this->input->post('phone_marketing')]
                    ]
                ])
                ) {
                    $this->session->set_flashdata('message', array('msg' => 'Nomor telepon sudah terpakai. Silahkan periksa kembali', 'status' => 'error'));
                    redirect('master/marketings/add');
                }

                $data = [
                    'nama_marketing' => $this->input->post('nama_marketing'),
                    'phone_marketing' => $this->input->post('phone_marketing'),
                    'marketing_st' => $this->input->post('marketing_st'),
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $this->user_data['user_id']
                ];

                $savedData = $this->m_marketings->add_marketings($data);

                if($savedData) {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/marketings/add/');
                } else {
                    $this->session->set_flashdata('message', array('msg' => 'Data gagal disimpan.', 'status' => 'error'));
                    redirect('master/marketings/add/');
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/marketings/add');
            }
        }
        // output
        parent::display();
    }

    // edit
    public function edit($id) {
        // Set template content
        $this->tsmarty->assign("template_content", "master/marketings/edit.html");

        $detail = $this->m_marketings->get_detail_marketing($id);

        $this->tsmarty->assign('detail', $detail);

        // save data
        if($this->input->post()){
            $this->form_validation->set_rules('nama_marketing', 'Nama', 'trim|required');
            $this->form_validation->set_rules('phone_marketing', 'Phone', 'trim|required');
            $this->form_validation->set_rules('marketing_st', 'Status', 'trim|required');
            if ($this->form_validation->run() !== FALSE) {
                // cek nama
                if($this->m_marketings->is_exist_by_name_id([$id, $this->input->post('nama_marketing')])) {
                    $this->session->set_flashdata('message', array('msg' => 'Nama sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
                    redirect('master/marketings/edit/' . $id);
                }
                // cek phone
                if($this->m_marketings->is_exist_by_field_id([
                        [
                            "field" => 'phone_marketing',
                            "query" => ['=', $this->input->post('phone_marketing')]
                        ]
                    ], $id)
                ) {
                    $this->session->set_flashdata('message', array('msg' => 'Phone sudah dipakai. Silakan ganti dengan yang lain.', 'status' => 'error'));
                    redirect('master/marketings/edit/' . $id);
                }
                $data = [
                    'nama_marketing' => $this->input->post('nama_marketing'),
                    'phone_marketing' => $this->input->post('phone_marketing'),
                    'marketing_st' => $this->input->post('marketing_st')
                ];

                $updated = $this->m_marketings->update_marketings($id, $data);

                if($updated['success'])
                {
                    $this->session->set_flashdata('message', array('msg' => 'Data berhasil disimpan', 'status' => 'success'));
                    redirect('master/marketings/edit/' . $id);
                }
                else
                {
                    $this->session->set_flashdata('message', array('msg' => "Data gagal disimpan", 'status' => 'error'));
                    redirect('master/marketings/edit/' . $id);
                }
            } else {
                $this->session->set_flashdata('message', array('msg' => validation_errors(), 'status' => 'error'));
                redirect('master/marketings/edit/' . $id);
            }
        }
        // output
        parent::display();
    }

    // delete 
    public function delete($id) {
        if($this->m_marketings->delete_marketings($id))
        {
            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
        }
        else
        {
            $this->session->set_flashdata('message', array('msg' => 'Data gagal dihapus.', 'status' => 'error'));
        }

        redirect("master/marketings");
    }
}