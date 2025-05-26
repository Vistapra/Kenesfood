<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

// load base
require_once(APPPATH . 'controllers/base/PrivateBase.php');

// --
class Pelamar extends ApplicationBase {

    // constructor
    public function __construct()
    {
        // parent constructor
        parent::__construct();
        // load model
        $this->load->model('settings/M_jobs', 'm_jobs');
    }

    //index
    function index() {
        // set template content
        $this->tsmarty->assign("template_content", "settings/pelamar/index.html");
        // search
        $keyword = '';
        $search = $this->session->userdata('search_pelamar');
        if ($this->input->post()) {
            if ($this->input->post('save') == "Reset") {
                // unset session
                $this->session->unset_userdata("search_pelamar");
            } else {
                $keyword = $this->input->post('keyword', TRUE);
                // set session
                $params = array(
                    "keyword" => $keyword,
                );
                $this->session->set_userdata("search_pelamar", $params);
            }
        } elseif (!empty($search)) {
            $keyword = $search['keyword'];
        }
        $this->tsmarty->assign("keyword", $keyword);
        // load library
        $this->load->library('pagination');
        // pagination
        $config['base_url'] = site_url('jobs/pelamar/index/');
        $config['total_rows'] = $this->m_jobs->get_total_data_pelamar($keyword);
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
        $data = $this->m_jobs->get_list_data_pelamar($params, $keyword); 
        $this->tsmarty->assign("datas", $data);
        // output
        parent::display();
    }

    // detail
    public function detail($id) {
        // set template content
        $this->tsmarty->assign("template_content", "settings/pelamar/detail.html");
        // get data
        $detail = $this->m_jobs->get_detail_pelamar($id);
        $this->tsmarty->assign("detail", $detail);
        // output
        parent::display();
    }

    public function whatsapp($id)
    {
        $pelamar = $this->m_jobs->get_detail_pelamar($id);
        // var_dump($latestName['nama_lengkap']);
        $tanggal_test = $pelamar['job_date_test'];
        // update test schedule
        $this->m_jobs->update_pelamar($id, ['test_schedule' => $pelamar['job_date_test']]);
        // mengambil hari
        $day = date('l', strtotime($tanggal_test));
        
        // Mengambil tanggal dalam format "dd mm yyyy"
        $date = date('d-m-Y', strtotime($tanggal_test));

        // Mengambil jam dalam format "h:i:s"
        $time = date('H:i:s', strtotime($tanggal_test));
        $message = 'Hi.. ' . $pelamar['nama_lengkap'] . ', 
Kami dari HRD Kenes Bakery & Resto menyampaikan bahwa lamaran anda telah kami terima dan anda LOLOS seleksi administrasi.

Berikut jadwal tes untuk posisi ' . $pelamar['job_name'] . ',
Hari : '. $day .'
Tanggal : '. $date .'
Jam : '. $time . '
Tempat : Jl. Wijayakusuma No. 301, Sinduadi, Mlati, Sleman (Sebelah utara Aster Homestay)
Maps : https://goo.gl/maps/UV3eLgVbWDmfFdig8

Harap isi link berikut untuk konfirmasi kehadiran.
https://forms.gle/QPLbJ39npxkVv7Hd8';

        // Buat URL WhatsApp
        $whatsappUrl = 'https://api.whatsapp.com/send?phone=' . urlencode($pelamar['phone']) . '&text=' . rawurlencode($message);
        header("Location: " . $whatsappUrl);
    }


    public function download($id)
    {
        $file_info = $this->m_jobs->get_detail_pelamar($id);
        if (!$file_info) {
            echo "File not found.";
            return;
        }

        $file_path = FCPATH . './resource/assets-frontend/dist/pelamar/dokumen/' . $file_info['upload_cv'];

        // Periksa apakah file ada
        if (file_exists($file_path)) {
            // Mengatur header HTTP untuk proses download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
        } else {
            // Jika file tidak ditemukan, tampilkan pesan error
            echo "File not found.";
        }
    }

    public function add()
    {
        $this->tsmarty->assign("template_content", "settings/pelamar/add.html");

        parent::display();
    }

    public function edit()
    {
        $this->tsmarty->assign("template_content", "settings/pelamar/edit.html");

        parent::display();
    }

    public function delete($id)
    {
        $pelamar = $this->m_jobs->detail_pelamar($id);

        if (!$pelamar) {
            echo "Pelamar dengan ID $id tidak ditemukan.";
        } else {
            if (isset($pelamar['foto_pelamar'])) {
                $photo_path = FCPATH . './resource/assets-frontend/dist/pelamar/foto/' . $pelamar['foto_pelamar'];
                if (file_exists($photo_path)) {
                    if (unlink($photo_path)) {
                        echo "File foto {$pelamar['foto_pelamar']} berhasil dihapus.";
                    } else {
                        echo "Gagal menghapus file foto {$pelamar['foto_pelamar']}.";
                    }
                } else {
                    echo "File foto {$pelamar['foto_pelamar']} tidak ditemukan.";
                }
            }

            if (isset($pelamar['upload_cv'])) {
                $cv_path = FCPATH . './resource/assets-frontend/dist/pelamar/dokumen/' . $pelamar['upload_cv'];
                if (file_exists($cv_path)) {
                    if (unlink($cv_path)) {
                        echo "File CV {$pelamar['upload_cv']} berhasil dihapus.";
                    } else {
                        echo "Gagal menghapus file CV {$pelamar['upload_cv']}.";
                    }
                } else {
                    echo "File CV {$pelamar['upload_cv']} tidak ditemukan.";
                }
            }

            $this->m_jobs->delete_pelamar($id);

            $this->session->set_flashdata('message', array('msg' => 'Data berhasil dihapus.', 'status' => 'success'));
            redirect('jobs/pelamar');
        }
    }
}