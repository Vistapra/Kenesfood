<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once( APPPATH . 'controllers/base/PrivateBase.php' );

// --
class Download extends ApplicationBase {

    // constructor
    public function __construct() {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('employee/M_employee', 'm_employee');
        $this->load->model('employee/M_contract', 'm_contract');
        $this->load->model('employee/M_leave', 'm_leave');
        $this->load->model('employee/M_permit', 'm_permit');
        $this->load->model('employee/M_overtime', 'm_overtime');
        $this->load->model('employee/M_placement', 'm_placement');
        $this->load->model('employee/M_warning_letter', 'm_warning_letter');
        $this->load->model('employee/M_employee_assessments', 'm_employee_assessments');
        $this->load->model('master/M_assessments', 'm_assessments');
        $this->load->model('inventories/M_employee_inventories', 'm_employee_inventories');
        $this->load->model('inventories/M_inventory_locate', 'm_inventory_locate');
    }

public function employee_assessment($employee_assessment_id, $employee_id) {
        if (empty($employee_id)) {
            $this->session->set_flashdata('message', array('msg' => 'Terjadi kesalahan. Silakan coba lagi', 'status' => 'error'));
            redirect(site_url('employee/assessment'));
        }

        if (empty($employee_assessment_id)) {
            $this->session->set_flashdata('message', array('msg' => 'Terjadi kesalahan. Silakan coba lagi', 'status' => 'error'));
            redirect(site_url('employee/assessment/list/' . $employee_id));
        }

        // Ambil data detail penilaian karyawan
        $data = $this->m_employee_assessments->employee_assessment_detail($employee_assessment_id); 
        $rata2 = $data['rata2'];
        $data = $data['result'];
        $this->tsmarty->assign("data", $data);
        $this->tsmarty->assign("rata2", $rata2);

        //data assessment yang bernilai tidak
        $data_tidak = $this->m_employee_assessments->assessment_detail_no($employee_assessment_id);

        // Ambil detail karyawan
        $detail = $this->m_employee_assessments->get_detail_data($employee_assessment_id);
        $this->tsmarty->assign('detail', $detail);

        if ($detail['assessment_st'] != '3') {
            $hrd = $this->m_employee->get_detail_manager_hrd();
            if (!empty($hrd)) {
                $dh['hrd_name'] = $hrd['fullname'];
                $dh['pos_hrd'] = $hrd['position_name'];
                $dh['hrd_code'] = $hrd['position_code'];
            }
        }

        if (empty($data)) {
            $this->session->set_flashdata('message', array('msg' => 'Data tidak ditemukan. Silakan coba lagi', 'status' => 'error'));
            redirect(site_url('employee/assessment/list/' . $employee_id));
        }

        // Menghitung rowspan untuk setiap assessment_group_id
        $rowspan_data = [];
        foreach ($data as $row) {
            if (!isset($rowspan_data[$row['assessment_group_id']])) {
                $rowspan_data[$row['assessment_group_id']] = 0;
            }
            $rowspan_data[$row['assessment_group_id']]++;
        }

        // Setup TCPDF
        $pdf = new TCPDF();
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetMargins(30, 10, 30, true);
        $pdf->SetFooterMargin(15);
        $pdf->SetAuthor('Kenes HRIS');
        $pdf->SetFont('times');
        $pdf->AddPage('L', 'A4'); // Tambahkan halaman pertama

        // Header PDF
        $header = $this->pref->get_value_preference_by_group_name(['export', 'header_landscape']);
        $image_path = base_url() . 'resource/assets/default/images/' . $this->app_settings['icon'];
        $header = str_replace('[LOGO]', $image_path, $header);
        $header = str_replace('[COMPANY]', $this->app_settings['company_name'], $header);
        $header = str_replace('[ADDRESS]', $this->app_settings['company_address'], $header);
        $header = str_replace('[PHONE]', $this->app_settings['company_phone'], $header);
        $header = str_replace('[EMAIL]', $this->app_settings['company_email'], $header);

        // Generate HTML untuk halaman pertama
        $html = '';
        $html .= $header;
        $html .= '<div style="line-height:20em">&nbsp;</div>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">
        <thead>
        <tr>
        <th rowspan="4" style="border: 1px solid #ddd; width:5%; padding: 8px; text-align: center; vertical-align: middle;">No</th>
        <th rowspan="4" style="border: 1px solid #ddd; width:20%; padding: 8px; text-align: center; vertical-align: middle;">Faktor Kerja Utama</th>
        <th rowspan="4" style="border: 1px solid #ddd; width:20%; padding: 8px; text-align: center; vertical-align: middle;">Key Performance Indicator</th>
        <th colspan="3" style="border: 1px solid #ddd; width:17%; padding: 8px; text-align: center;">POIN</th>
        <th rowspan="4" style="border: 1px solid #ddd; width:20%; padding: 8px; text-align: center; vertical-align: middle;">Alasan Tidak</th>
        <th rowspan="4" style="border: 1px solid #ddd; width:18%; padding: 8px; text-align: center; vertical-align: middle;">Skor</th>
        </tr>
        <tr>
        <th colspan="3" style="border: 1px solid #ddd; padding: 8px; text-align: center;">REALISASI</th>
        </tr>
        <tr>
        <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">2</th>
        <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">1</th>
        <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">0</th>
        </tr>
        <tr>
        <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Ya</th>
        <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">N/A</th>
        <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Tidak</th>
        </tr>
        </thead>
        <tbody>';

        $no = 1;
        $current_group_id = null;
        foreach ($data as $row) {
            $group_data_count = $rowspan_data[$row['assessment_group_id']];
            if ($row['assessment_group_id'] !== $current_group_id) {
                $current_group_id = $row['assessment_group_id'];
                $html .= '<tr>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:5%; text-align: center; vertical-align: middle;" rowspan="' . $group_data_count . '">' . $no . '</td>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:20%; text-align: center; vertical-align: middle;" rowspan="' . $group_data_count . '">' . $row['assessment_group_name'] . '</td>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:20%; text-align: center;">' . $row['assessment_name'] . '</td>';
                
                // Nilai "Ya"
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:5.7%; text-align: center;">';
                if ($row['assessment_score'] == 2) {
                    $html .= "Ya";
                }
                $html .= '</td>';

                // Nilai "N/A"
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:5.7%; text-align: center;">';
                if ($row['assessment_score'] == 1) {
                    $html .= "N/A";
                }
                $html .= '</td>';

                // Nilai "Tidak"
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:5.6%; text-align: center;">';
                if ($row['assessment_score'] == 0) {
                    $html .= "Tidak";
                }
                $html .= '</td>';

                // Kolom alasan tidak
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:20%; text-align: center; vertical-align: middle;">';
                if ($row['assessment_score'] == 0 && isset($row['reason_for_not'])) {
                    $html .= $row['reason_for_not'];
                } else {
                    $html .= '-';
                }
                $html .= '</td>';

                // Skor
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:18%; text-align: center; vertical-align: middle;" rowspan="' . $group_data_count . '"><b>' . $row['percentage'] . ' %</b></td>';
                $html .= '</tr>';
                $no++;
            } else {
                $html .= '<tr>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:20%; text-align: center;">' . $row['assessment_name'] . '</td>';
                
                // Nilai "Ya"
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:5.7%; text-align: center;">';
                if ($row['assessment_score'] == 2) {
                    $html .= "Ya";
                }
                $html .= '</td>';

                // Nilai "N/A"
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:5.7%; text-align: center;">';
                if ($row['assessment_score'] == 1) {
                    $html .= "N/A";
                }
                $html .= '</td>';

                // Nilai "Tidak"
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:5.6%; text-align: center;">';
                if ($row['assessment_score'] == 0) {
                    $html .= "Tidak";
                }
                $html .= '</td>';

                // Kolom alasan tidak
                $html .= '<td style="border: 1px solid #ddd; padding: 8px; width:20%; text-align: center; vertical-align: middle;">';
                if ($row['assessment_score'] == 0 && isset($row['reason_for_not'])) {
                    $html .= $row['reason_for_not'];
                } else {
                    $html .= '-';
                }
                $html .= '</td>';

                $html .= '</tr>';
            }
        }
        $html .= '<tr>';
        $html .= '<td colspan="7" style="border: 1px solid #ddd; padding: 8px; text-align: center;"><b> AVG ALL </b></td>';
        $html .= '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><b>'.$rata2.' %</b></td>';
        $html .= '</tr>';
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('employee_assessment.pdf', 'I');
    }
}