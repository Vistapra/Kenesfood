<?php

class ApplicationBase extends CI_Controller
{

    // init base variable
    protected $portal_id;
    protected $portal_member;
    protected $member_role;
    protected $member_data;
    protected $app_portal;
    protected $app_settings;
    protected $marketplace;
    protected $socmed;

    public function __construct()
    {
        // load basic controller
        parent::__construct();
        // load app data
        $this->base_load_app();
        // view app data
        $this->base_view_app();
        
    }

    /*
     * Method pengolah base load
     * diperbolehkan untuk dioverride pada class anaknya
     */

    protected function base_load_app()
    {
    }

    /*
     * Method pengolah base view
     * diperbolehkan untuk dioverride pada class anaknya
     */

    protected function base_view_app()
    {
        // assign config
        $this->tsmarty->assign("config", $this->config);
        // default portal for all user
        $this->portal_id = $this->config->item('public');
        $this->portal_member = $this->config->item('member');
        $this->member_role = $this->config->item('member_role');
        // display site title
        self::_display_site_title();
        // get session
        self::_get_user_session();
        // get visitor log
        self::_visitor_log();
        // get session
        // print_r($this->session->flashdata('message')); exit();
        $this->tsmarty->assign('notification', $this->session->flashdata('message'));
        // list social media
        $sosmed = $this->m_brands->get_list_contact_by_brand(['bakery', 'social-media']);
        $this->tsmarty->assign("sosmed", $sosmed);
        $market = $this->m_brands->get_list_contact_by_brand(['bakery', 'marketplace']);
        $this->tsmarty->assign("market", $market);  
        // $this->load->model('M_ekatalogs', 'm_ekatalogs');
        // $ekatalogs = $this->m_ekatalogs->get_list_ekatalog();
        // $this->tsmarty->assign("ekatalogs", $ekatalogs);

    }

    /*
     * Method layouting base document
     * diperbolehkan untuk dioverride pada class anaknya
     */

    protected function display($tmpl_name = 'base/document-public-new.html')
    {
        $this->load->model('master/M_marketings', 'm_marketings');
        $marketing = $this->m_marketings->get_list_marketing();
        $this->tsmarty->assign('marketing', $marketing);
        // set template
        $this->tsmarty->display($tmpl_name);
    }

    //
    // base private method here
    // prefix ( _ )
    // site title
    
    private function _display_site_title()
    {
        // load model
        $this->load->model('apps/M_preferences', 'pref');
        // site data
        $this->app_portal = $this->site->get_site_data_by_id($this->portal_id);
        $this->app_settings = $this->pref->get_all_preference_by_group_label([$this->app_portal['site_title'], 'site_setting']);
        if (!empty($this->app_portal)) {
            $this->tsmarty->assign("site", $this->app_portal);
            $this->tsmarty->assign("settings", $this->app_settings);
        }
    }

    private function _get_user_session()
    {
        $this->load->library('session');
        $this->member_data = $this->session->userdata('member');
        $this->tsmarty->assign('member', $this->member_data);
    }

    private function _visitor_log() 
    {
        // $ipv4 = $_SERVER['REMOTE_ADDR'];
        $ip_address = $this->getUserIP();
        if(strpos(uri_string(), 'administrator') === false) {
            if($ip_address != '127.0.0.1' && substr($ip_address, 0, 8) != '192.168.') {
                $info = 'http://ip-api.com/json/'.$ip_address.'?fields=20180991';
                $details = json_decode(file_get_contents($info));
                
                if($details->status != 'fail') {
                    $data = [
                        'ip_address' => $ip_address,
                        'access_page' => uri_string() ?: 'home',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                        'country' => $details->country,
                        'region' => $details->regionName,
                        'city' => $details->city,
                        'latitude' => $details->lat,
                        'longitude' => $details->lon,
                        'mobile' => $details->mobile,
                        '_data' => serialize($_SERVER),
                        'created' => date('Y-m-d H:i:s'),
                    ];
                } else {
                    $data = [
                        'ip_address' => $ip_address,
                        'access_page' => uri_string() ?: 'home',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                        '_data' => serialize($_SERVER),
                        'created' => date('Y-m-d H:i:s'),
                    ];
                }
                $this->site->insert_visitor_log($data);
            }
        }
    }

    function getUserIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
}
