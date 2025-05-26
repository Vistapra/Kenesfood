<?php

defined('BASEPATH') or exit('No direct script access allowed');

class M_email extends CI_Model {

    public $email_settings;
    public $mail_to = '';
    public $mail_cc = array();
    public $mail_bcc = array();
    public $mail_subject = '';
    public $mail_message = array();
    public $mail_attachments = array();

    // constructor
    public function __construct() {
        // load parent constructor
        parent::__construct();
        // load library
        $this->load->library('email');
        // get email settings
        $this->email_settings = $this->get_email_preferences();
    }

    /*
     * EMAIL UTILITY
     */

    // get email preferences
    public function get_email_preferences() {
        $sql = "SELECT * FROM app_email";
        $query = $this->db->query($sql);
        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            $query->free_result();
            $data = array();
            foreach ($result as $rec) {
                $data[$rec['email_id']] = $rec;
            }
            return $data;
        } else {
            return array();
        }
    }

    // send mail
    public function send_mail($email_id = '01') {
        // return TRUE;
        // check email
        if (!isset($this->email_settings[$email_id])) {
            return FALSE;
        }
        // clear email
        $this->email->clear(TRUE);
        // config
        $config['mailtype'] = 'html';
        $config['charset'] = 'utf-8';
        $config['newline'] = "\r\n";
        $config['protocol'] = 'smtp';
        $config['smtp_host'] = $this->email_settings[$email_id]['smtp_host'];
        $config['smtp_port'] = $this->email_settings[$email_id]['smtp_port'];
        $config['smtp_user'] = $this->email_settings[$email_id]['smtp_username'];
        $config['smtp_pass'] = $this->email_settings[$email_id]['smtp_password'];
        $this->email->initialize($config);
        $this->email->from($this->email_settings[$email_id]['email_address'], $this->email_settings[$email_id]['email_name']);
        $this->email->to($this->mail_to);
        $this->email->cc($this->mail_cc);
        $this->email->subject($this->mail_subject);
        $this->email->message($this->mail_message);
        if (!empty($this->mail_attachments)) {
            foreach ($this->mail_attachments as $attachment) {
                $this->email->attach($attachment);
            }
        }
        if ($this->email->send()) {
            $return = TRUE;
        } else {
            $return = FALSE;
            // echo $this->email->print_debugger();
        }
        return $return;
    }

    // html
    public function set_mail($email) {
        // get parameters
        $this->mail_to = !empty($email['to']) ? $email['to'] : '';
        $this->mail_cc = !empty($email['cc']) ? $email['cc'] : array();
        $this->mail_subject = !empty($email['subject']) ? $email['subject'] : '';
        $this->mail_attachments = !empty($email['attachments']) ? $email['attachments'] : array();
        // set message
        $title = !empty($email['message']['title']) ? $email['message']['title'] : 'Belum ada judul';
        $greetings = !empty($email['message']['greetings']) ? $email['message']['greetings'] : 'Pesan email tidak sempurna!';
        $intro = !empty($email['message']['intro']) ? $email['message']['intro'] : '';
        $details = !empty($email['message']['details']) ? $email['message']['details'] : '';
        $footer = !empty($email['message']['footer']) ? $email['message']['footer'] : 'Best Regards, <br /> The Airport Authority Region I IT Team';
        $disclaimer = !empty($email['message']['disclaimer']) ? $email['message']['disclaimer'] : 'Anda mendapatan email ini karena menggunakan Sistem Informasi Manajemen Terpadu Otoritas Bandara Wilayah I <br />[ Jangan Dibalas ]';
        $copyright = !empty($email['message']['copyright']) ? $email['message']['copyright'] : '© ' . date('Y') . ' IT Team. Airport Authority Region I.';
        // parse message
        $this->mail_message = '<table style="min-width:348px" width="100%" cellspacing="0" cellpadding="0" border="0" height="100%">';
        $this->mail_message .= '<tbody>';
        $this->mail_message .= '<tr height="32px"></tr>';
        $this->mail_message .= '<tr>'
                . '<tr align="center">'
                . '<td width="32px"></td>'
                . '<td>'
                . '<table style="max-width:600px" cellspacing="0" cellpadding="0" border="0">'
                . '<tbody>'
                . '<tr>'
                . '<td>'
                . '<table width="100%" cellspacing="0" cellpadding="0" border="0">'
                . '<tbody>'
                . '<tr>'
                . '<td align="left">'
                . '<img src="' . base_url() . 'resource/doc/images/logo/perhub.png' . '" style="display:block; width:48px; height:48px" width="48" height="48" />'
                . '</td>'
                . '<td align="right">'
                . '<b style="font-family:Roboto-Regular,Helvetica,Arial,sans-serif; font-size:24px;">Otoritas Bandara Wilayah I</b>'
                . '</td>'
                . '</tr>'
                . '</tbody>'
                . '</table>'
                . '</td>'
                . '</tr>'
                . '<tr height="16"></tr>'
                . '<tr>'
                . '<td>'
                . '<table style="min-width:332px; max-width:600px; border:1px solid #e0e0e0; border-bottom:0; border-top-left-radius:3px; border-top-right-radius:3px" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#113d84">'
                . '<tbody>'
                . '<tr><td colspan="3" height="72px"></td></tr>'
                . '<tr>'
                . '<td width="32px"></td>'
                . '<td style="font-family:Roboto-Regular,Helvetica,Arial,sans-serif; font-size:24px; color:#ffffff; line-height:1.25">'
                . $title
                . '</td>'
                . '<td width="32px"></td>'
                . '</tr>'
                . '<tr><td colspan="3" height="18px"></td></tr>'
                . '</tbody>'
                . '</table>'
                . '</td>'
                . '</tr>'
                . '<tr>'
                . '<td>'
                . '<table style="min-width:332px;max-width:600px;border:1px solid #f0f0f0;border-bottom:1px solid #c0c0c0;border-top:0;border-bottom-left-radius:3px;border-bottom-right-radius:3px" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#FAFAFA">'
                . '<tbody>'
                . '<tr height="16px">'
                . '<td rowspan="3" width="32px"></td>'
                . '<td></td>'
                . '<td rowspan="3" width="32px"></td>'
                . '</tr>'
                . '<tr>'
                . '<td>'
                . '<table style="min-width:300px" cellspacing="0" cellpadding="0" border="0">'
                . '<tbody>'
                . '<tr>'
                . '<td style="font-family:Roboto-Regular,Helvetica,Arial,sans-serif; font-size:13px; color:#202020; line-height:1.5">'
                . $greetings
                . '</td>'
                . '</tr>'
                . '<tr>'
                . '<td style="font-family:Roboto-Regular,Helvetica,Arial,sans-serif; font-size:13px; color:#202020; line-height:1.5">'
                . $intro
                . '<table style="margin-top:16px;margin-bottom:16px" cellspacing="0" cellpadding="0" border="0">'
                . '<tbody>'
                . '<tr valign="middle">'
                . '<td width="16px"></td>'
                . '<td style="line-height:1.2">'
                . $details
                . '</td>'
                . '</tr>'
                . '</tbody>'
                . '</table>'
                . '</td>'
                . '</tr>'
                . '<tr height="32px"></tr>'
                . '<tr>'
                . '<td style="font-family:Roboto-Regular,Helvetica,Arial,sans-serif; font-size:13px; color:#202020; line-height:1.5">'
                . $footer
                . '</td>'
                . '</tr>'
                . '<tr height="16px"></tr>'
                . '</tbody>'
                . '</table>'
                . '</td>'
                . '</tr>'
                . '<tr height="32px"></tr>'
                . '</tbody>'
                . '</table>'
                . '</td>'
                . '</tr>'
                . '<tr height="16"></tr>'
                . '<tr><td style="max-width:600px;font-family:Roboto-Regular,Helvetica,Arial,sans-serif; font-size:10px; color:#bcbcbc; line-height:1.5"></td></tr>'
                . '<tr>'
                . '<td>'
                . '<table style="font-family:Roboto-Regular,Helvetica,Arial,sans-serif;font-size:10px;color:#666666;line-height:18px;padding-bottom:10px">'
                . '<tbody>'
                . '<tr>'
                . '<td>'
                . $disclaimer
                . '</td>'
                . '</tr>'
                . '<tr>'
                . '<td>'
                . '<div style="direction:ltr;text-align:left">'
                . $copyright
                . '</div>'
                . '</td>'
                . '</tr>'
                . '</tbody>'
                . '</table>'
                . '</td>'
                . '</tr>'
                . '</tbody>'
                . '</table>'
                . '</td>'
                . '<td width="32px"></td>'
                . '</tr>'
                . '</tr>';
        $this->mail_message .= '<tr height="32px"></tr>';
        $this->mail_message .= '</tbody>';
        $this->mail_message .= '</table>';
    }

}
