<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_jobs extends CI_Model
{

    function __construct()
    {
        parent::__construct();
    }

    // get total data
    public function get_total_data($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                job.job_name LIKE '%" . $keyword ."%'
                OR job.job_description LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  FROM job 
                WHERE 1=1
                " . $conditions;
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['total'];
        } else {
            return 0;
        }
    }

    // get list data
    function get_list_data($params, $keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                job.job_name LIKE '%" . $keyword ."%'
                OR job.job_description LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    job.* 
                FROM job 
                WHERE 1=1
                " . $conditions . "
                ORDER BY job.job_name
                LIMIT ?, ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get detail job
    public function get_detail_job($params)
    {
        $sql = "SELECT * FROM job
                WHERE job_id = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // insert job
    function add_job($params) {
        return $this->db->insert('job', $params);
    }

    // update job
    function update_job($job_id, $params) {
        $this->db->where('job_id', $job_id);
        return $this->db->update('job', $params);
    }

    // delete job
    function delete_job($job_id) {
        $this->db->where('job_id', $job_id);
        return $this->db->delete('job');
    }

    /**
     *  Pelamar
     */

    // get total data pelamar
    public function get_total_data_pelamar($keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                job.job_name LIKE '%" . $keyword ."%'
                OR job.job_description LIKE '%" . $keyword ."%'
                OR member_job.ktp LIKE '%" . $keyword ."%'
                OR member_job.nama_lengkap LIKE '%" . $keyword ."%'
                OR member_job.tempat_lahir LIKE '%" . $keyword ."%'
                OR member_job.jenis_kelamin LIKE '%" . $keyword ."%'
                OR member_job.email LIKE '%" . $keyword ."%'
                OR member_job.phone LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT COUNT(*)'total'  
                FROM member_job 
                INNER JOIN job ON member_job.job_id = job.job_id
                WHERE member_job.status = '0'
                " . $conditions;
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result['total'];
        } else {
            return 0;
        }
    }

    // get list data pelamar
    function get_list_data_pelamar($params, $keyword) {
        // conditions
        $conditions = '';
        if($keyword != NULL) {
            $conditions .= " AND (
                job.job_name LIKE '%" . $keyword ."%'
                OR job.job_description LIKE '%" . $keyword ."%'
                OR member_job.ktp LIKE '%" . $keyword ."%'
                OR member_job.nama_lengkap LIKE '%" . $keyword ."%'
                OR member_job.tempat_lahir LIKE '%" . $keyword ."%'
                OR member_job.jenis_kelamin LIKE '%" . $keyword ."%'
                OR member_job.email LIKE '%" . $keyword ."%'
                OR member_job.phone LIKE '%" . $keyword ."%'
            )";
        }
        $sql = "SELECT 
                    member_job.*,
                    job.job_name,
                    job.job_date_test
                FROM member_job 
                INNER JOIN job ON member_job.job_id = job.job_id
                WHERE member_job.status = '0'
                " . $conditions . "
                ORDER BY member_job.created_by DESC
                LIMIT ?, ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // get detail pelamar
    public function get_detail_pelamar($params)
    {
        $sql = "SELECT 
                    member_job.*,
                    job.job_name,
                    job.job_date_test
                FROM member_job 
                INNER JOIN job ON member_job.job_id = job.job_id
                WHERE member_job.id_pelamar = ?";
        $query = $this->db->query($sql, $params);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // update tanggal test
    function update_pelamar($id_pelamar, $params) {
        $this->db->where('id_pelamar', $id_pelamar);
        return $this->db->update('member_job', $params);
    }

    public function detail_pelamar($id)
    {
        $query = $this->db->get_where('member_job', array('id_pelamar' => $id));
        return $query->row_array();
    }
    public function list_pelamar()
    {
        $this->db->select('member_job.nama_lengkap, member_job.id_pelamar, member_job.job_id, job.job_name, member_job.phone');
        $this->db->from('member_job');
        $this->db->join('job', 'member_job.job_id = job.job_id', 'left');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    // function delete_pelamar($id)
    // {
    //     $this->db->where('id_pelamar', $id);
    //     $this->db->delete('member_job');
    // }

    function delete_pelamar($id)
    {
        $sql = "UPDATE member_job
        SET member_job.status = '1' where member_job.id_pelamar= ?";
        $query = $this->db->query($sql, $id);
    }

    public function getPelamar($id)
    {
        $this->db->select('*');
        $this->db->from('member_job');
        $this->db->join('job', 'member_job.job_id = job.job_id', 'left');
        $this->db->where('id_pelamar', $id);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row;
        } else {
            return null; // Jika tidak ada data yang sesuai
        }
    }

    public function getWhatsAppNumber($id)
    {

        $this->db->select('phone');
        $this->db->where('id_pelamar', $id);
        $query = $this->db->get('member_job');

        if ($query->num_rows() > 0) {
            $row = $query->row();
            return $row->phone;
        } else {
            return null; // Nomor WhatsApp tidak ditemukan
        }
    }

    /**
     *  PUBLIC PAGE
     */

    function get_list_data_jobs()
    {
        $query = $this->db->get('job');
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    function get_list_data_jobs_active()
    {
        $today = date("Y-m-d H:i:s");

        $this->db->where('job_date >=', $today);
        $query = $this->db->get('job');

        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            return $result;
        } else {
            return array();
        }
    }

    public function lamar_job($data)
    {
        return $this->db->insert('member_job', $data);
    }

}