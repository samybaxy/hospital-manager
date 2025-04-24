<?php
namespace HospitalManager\Controllers;

use WPMVC\MVC\Controller;

class AdminController extends Controller
{
    public function __construct()
    {
        // Pass empty string as MVC name parameter to parent constructor
        parent::__construct('HospitalManager');
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerAdminMenu()
    {
        add_menu_page(
            __('Hospital Manager', 'hospital-manager'),
            __('Hospital Manager', 'hospital-manager'),
            'manage_options',
            'hospital-manager',
            [$this, 'renderAdminPage'],
            'dashicons-hospital',
            30
        );
    }

    public function enqueueAssets($hook)
    {
        if ($hook !== 'toplevel_page_hospital-manager') {
            return;
        }

        wp_enqueue_style(
            'hospital-manager-admin',
            plugins_url('assets/css/src/admin.css', dirname(__DIR__))
        );

        wp_enqueue_script(
            'hospital-manager-app',
            plugins_url('assets/js/dist/bundle.js', dirname(__DIR__)),
            ['wp-element'],
            '1.0.0',
            true
        );

        wp_localize_script('hospital-manager-app', 'hospitalManagerData', [
            'nonce' => wp_create_nonce('hospital_manager_nonce'),
            'apiUrl' => rest_url('hospital-manager/v1')
        ]);
    }

    public function renderAdminPage()
    {
        $this->view->render('admin.index');
    }
}
