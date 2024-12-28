<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class M_forgot_password extends CI_Model
{
    public function __construct()
    {
        // Call the Model constructor
        parent::__construct();
    }


    public function email($user_email)
    {
        $sql = "SELECT user_email
        FROM app_user
        WHERE user_email = ?";
        $query = $this->db->query($sql, $user_email);
        if ($query->num_rows() > 0) {
            return $query->result();
        } else {
            return array();
        }
    }

    public function updateUserByEmail($user_email, $update_data)
    {

        $this->db->where('user_email', $user_email);
        $this->db->update('app_user', $update_data);
    }

    public function confirmation_password($user_pass, $user_email)
    {
        $this->db->set('user_pass', $user_pass);
        $this->db->where('user_email', $user_email);
        $this->db->update('app_user');
    }

    public function available_token($token, $user_email)
    {

        $sql = "SELECT token, time_token, user_email
        FROM app_user
        WHERE user_email = '$user_email' AND token = '$token'";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->row_array();
            return $result;
        } else {
            return array();
        }
    }
}