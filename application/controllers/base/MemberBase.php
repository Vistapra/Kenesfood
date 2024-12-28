<?php

class ApplicationBase extends CI_Controller {

    // init base variable
    protected $portal_id;
    protected $app_portal;
    protected $app_settings;

    public function __construct() {
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

    protected function base_load_app() {
        // load themes (themes default : default)
        // $this->tsmarty->load_themes("default");
        // load javascript
        // $this->tsmarty->load_javascript("resource/themes/default/js/vendors/jquery-3.2.1.min.js");
        // $this->tsmarty->load_javascript("resource/themes/default/js/vendors/bootstrap.bundle.min.js");
    }

    /*
     * Method pengolah base view
     * diperbolehkan untuk dioverride pada class anaknya
     */

    protected function base_view_app() {
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
        // self::_visitor_log();
        // get session
        // print_r($this->session->flashdata('message')); exit();
        $this->tsmarty->assign('notification', $this->session->flashdata('message'));
    }

    /*
     * Method layouting base document
     * diperbolehkan untuk dioverride pada class anaknya
     */

    protected function display($tmpl_name = 'base/document-member.html') {
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
        $this->tsmarty->assign('member', $this->session->userdata('member'));
    }
}
