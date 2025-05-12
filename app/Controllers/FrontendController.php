<?php
/**
 * FrontendController.php
 * 
 * This file is part of the Hospital Manager plugin.
 * 
 * @package HospitalManager
 * @version 1.0.0
 */
namespace HospitalManager\Controllers;

use WPMVC\MVC\Controller;

class FrontendController extends Controller
{
    public function __construct()
    {
        // Pass empty string as MVC name parameter to parent constructor
        parent::__construct('HospitalManager');
        
        // Register shortcode
        add_shortcode('hospital_manager', [$this, 'renderFrontend']);
        
        // Register scripts and styles for frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets()
    {
        // Only enqueue on pages that have our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'hospital_manager')) {
            wp_enqueue_style(
                'hospital-manager-frontend',
                plugins_url('assets/css/dist/frontend.css', dirname(__DIR__))
            );
            
            // Add inline CSS to hide the WordPress default page title
            wp_add_inline_style('hospital-manager-frontend', '
                .wp-block-post-title, 
                .entry-title, 
                .page-title { 
                    display: none !important; 
                }
            ');

            wp_enqueue_script(
                'hospital-manager-app',
                plugins_url('assets/js/dist/bundle.js', dirname(__DIR__)),
                ['wp-element'],
                '1.0.0',
                true
            );

            wp_localize_script('hospital-manager-app', 'hospitalManagerData', [
                'nonce' => wp_create_nonce('wp_rest'),
                'apiUrl' => rest_url('hospital-manager/v1'),
                'isFrontend' => true
            ]);
        }
    }

    public function renderFrontend($atts)
    {
        // Process any attributes
        $atts = shortcode_atts(array(
            'class' => '',
        ), $atts);
        
        // Start output buffering
        ob_start();
        
        // Render the container for React
        echo '<div class="hospital-manager-container ' . esc_attr($atts['class']) . '">';
        echo '<div id="hospital-manager-root"></div>';
        echo '</div>';
        
        // Return the buffered content
        return ob_get_clean();
    }
}