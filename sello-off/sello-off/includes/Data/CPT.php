<?php
/**
 * Register Custom Post Types for SELLO (Facets / Presets / Filters)
 * Compat PHP 7.0+
 */
namespace Sello\Data;

defined('ABSPATH') || exit;

class CPT
{
    /**
     * Enregistre les 3 CPT : sello_facet, sello_preset, sello_filter
     * - Non publics
     * - UI WordPress activée
     * - Pas de menu propre (on les ouvre via le menu SELLO)
     * - Toutes les actions protégées par la capacité "manage_sello"
     */
    public static function register()
    {
        // Capabilities mappées sur manage_sello
        $caps = array(
            'edit_post'          => 'manage_sello',
            'read_post'          => 'manage_sello',
            'delete_post'        => 'manage_sello',
            'edit_posts'         => 'manage_sello',
            'edit_others_posts'  => 'manage_sello',
            'publish_posts'      => 'manage_sello',
            'read_private_posts' => 'manage_sello',
            'delete_posts'       => 'manage_sello',
            'delete_private_posts' => 'manage_sello',
            'delete_published_posts' => 'manage_sello',
            'delete_others_posts' => 'manage_sello',
            'edit_private_posts' => 'manage_sello',
            'edit_published_posts' => 'manage_sello',
            'create_posts'       => 'manage_sello',
        );

        // ---------- Facet ----------
        register_post_type('sello_facet', array(
            'labels' => array(
                'name'               => __('Facets', 'sello'),
                'singular_name'      => __('Facet', 'sello'),
                'menu_name'          => __('Facets', 'sello'),
                'name_admin_bar'     => __('Facet', 'sello'),
                'add_new'            => __('Add New', 'sello'),
                'add_new_item'       => __('Add New Facet', 'sello'),
                'new_item'           => __('New Facet', 'sello'),
                'edit_item'          => __('Edit Facet', 'sello'),
                'view_item'          => __('View Facet', 'sello'),
                'all_items'          => __('All Facets', 'sello'),
                'search_items'       => __('Search Facets', 'sello'),
                'not_found'          => __('No facets found.', 'sello'),
                'not_found_in_trash' => __('No facets found in Trash.', 'sello'),
            ),
            'description'        => 'SELLO Facet entity',
            'public'             => false,
            'exclude_from_search'=> true,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // géré via le menu SELLO
            'show_in_admin_bar'  => false,
            'show_in_nav_menus'  => false,
            'show_in_rest'       => false,
            'hierarchical'       => false,
            'supports'           => array('title'),
            'has_archive'        => false,
            'rewrite'            => false,
            'query_var'          => false,
            'capability_type'    => 'post',
            'map_meta_cap'       => false,
            'capabilities'       => $caps,
        ));

        // ---------- Preset ----------
        register_post_type('sello_preset', array(
            'labels' => array(
                'name'               => __('Presets', 'sello'),
                'singular_name'      => __('Preset', 'sello'),
                'menu_name'          => __('Presets', 'sello'),
                'name_admin_bar'     => __('Preset', 'sello'),
                'add_new'            => __('Add New', 'sello'),
                'add_new_item'       => __('Add New Preset', 'sello'),
                'new_item'           => __('New Preset', 'sello'),
                'edit_item'          => __('Edit Preset', 'sello'),
                'view_item'          => __('View Preset', 'sello'),
                'all_items'          => __('All Presets', 'sello'),
                'search_items'       => __('Search Presets', 'sello'),
                'not_found'          => __('No presets found.', 'sello'),
                'not_found_in_trash' => __('No presets found in Trash.', 'sello'),
            ),
            'description'        => 'SELLO Preset (group of facets)',
            'public'             => false,
            'exclude_from_search'=> true,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'show_in_admin_bar'  => false,
            'show_in_nav_menus'  => false,
            'show_in_rest'       => false,
            'hierarchical'       => false,
            'supports'           => array('title'),
            'has_archive'        => false,
            'rewrite'            => false,
            'query_var'          => false,
            'capability_type'    => 'post',
            'map_meta_cap'       => false,
            'capabilities'       => $caps,
        ));

        // ---------- Filter ----------
        register_post_type('sello_filter', array(
            'labels' => array(
                'name'               => __('Filters', 'sello'),
                'singular_name'      => __('Filter', 'sello'),
                'menu_name'          => __('Filters', 'sello'),
                'name_admin_bar'     => __('Filter', 'sello'),
                'add_new'            => __('Add New', 'sello'),
                'add_new_item'       => __('Add New Filter', 'sello'),
                'new_item'           => __('New Filter', 'sello'),
                'edit_item'          => __('Edit Filter', 'sello'),
                'view_item'          => __('View Filter', 'sello'),
                'all_items'          => __('All Filters', 'sello'),
                'search_items'       => __('Search Filters', 'sello'),
                'not_found'          => __('No filters found.', 'sello'),
                'not_found_in_trash' => __('No filters found in Trash.', 'sello'),
            ),
            'description'        => 'SELLO Filter (scope + design + pinned + slider)',
            'public'             => false,
            'exclude_from_search'=> true,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'show_in_admin_bar'  => false,
            'show_in_nav_menus'  => false,
            'show_in_rest'       => false,
            'hierarchical'       => false,
            'supports'           => array('title'),
            'has_archive'        => false,
            'rewrite'            => false,
            'query_var'          => false,
            'capability_type'    => 'post',
            'map_meta_cap'       => false,
            'capabilities'       => $caps,
        ));
    }
}
