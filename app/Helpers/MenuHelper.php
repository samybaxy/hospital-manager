<?php
/**
 * MenuHelper.php
 * 
 * This file is part of the Hospital Manager plugin.
 * 
 * @package HospitalManager
 * @version 1.0.0
 */

namespace HospitalManager\Helpers;

class MenuHelper
{
    /**
     * Creates a page with the Hospital Manager shortcode if it doesn't exist
     * and adds a menu item linking to it.
     */
    public static function createHospitalManagerMenuItem()
    {
        // Check if the page already exists
        $page_title = 'Hospital Manager';
        $page = self::getPageByTitle($page_title);
        
        // If the page doesn't exist, create it
        if (!$page) {
            $page_id = wp_insert_post(
                array(
                    'post_title'    => $page_title,
                    'post_content'  => '[hospital_manager]',
                    'post_status'   => 'publish',
                    'post_type'     => 'page',
                )
            );
        } else {
            $page_id = $page->ID;
        }
        
        // Check if the menu already exists
        $menu_name = 'Main Menu';
        $menu = wp_get_nav_menu_object($menu_name);
        
        // If it doesn't exist, create it
        if (!$menu) {
            $menu_id = wp_create_nav_menu($menu_name);
        } else {
            $menu_id = $menu->term_id;
        }
        
        // Check if our menu item already exists
        $menu_items = wp_get_nav_menu_items($menu_id);
        $menu_item_exists = false;
        
        if ($menu_items) {
            foreach ($menu_items as $menu_item) {
                if ($menu_item->object_id == $page_id) {
                    $menu_item_exists = true;
                    break;
                }
            }
        }
        
        // If menu item doesn't exist, add it
        if (!$menu_item_exists) {
            wp_update_nav_menu_item($menu_id, 0, array(
                'menu-item-title'   => $page_title,
                'menu-item-object'  => 'page',
                'menu-item-object-id' => $page_id,
                'menu-item-type'    => 'post_type',
                'menu-item-status'  => 'publish'
            ));
        }
        
        return $page_id;
    }

    /**
     * Get a page by title using WP_Query (replacement for deprecated get_page_by_title)
     * 
     * @param string $page_title The title of the page to find
     * @param string $post_type The post type to search (default: 'page')
     * @return WP_Post|null The page object if found, null otherwise
     */
    private static function getPageByTitle($page_title, $post_type = 'page')
    {
        $query = new \WP_Query(array(
            'post_type'              => $post_type,
            'title'                  => $page_title,
            'post_status'            => 'all',
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ));

        if (!empty($query->posts)) {
            return $query->posts[0];
        }

        return null;
    }
}