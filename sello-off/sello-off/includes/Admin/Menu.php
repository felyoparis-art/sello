<?php
/**
 * Admin Menu (Top-level "SELLO")
 * Compat PHP 7.0+
 */
namespace Sello\Admin;

use Sello\Core\Capabilities;

defined('ABSPATH') || exit;

class Menu
{
    public static function register()
    {
        $cap = Capabilities::CAP;

        // Top-level menu "SELLO"
        add_menu_page(
            'SELLO — Dashboard',                 // page_title
            'SELLO',                             // menu_title
            $cap,                                // capability
            'sello-hub',                         // menu_slug
            array(\Sello\Admin\DashboardPage::class, 'render'), // callback
            'dashicons-filter',                  // icon
            58                                   // position
        );

        // Submenus
        add_submenu_page(
            'sello-hub',
            'SELLO — Dashboard',
            __('Dashboard','sello'),
            $cap,
            'sello-dashboard',
            array(\Sello\Admin\DashboardPage::class, 'render')
        );

        // CPT lists (WordPress natives)
        add_submenu_page(
            'sello-hub',
            'SELLO — Facets',
            __('Facets','sello'),
            $cap,
            'edit.php?post_type=sello_facet',
            '__return_empty_string'
        );

        add_submenu_page(
            'sello-hub',
            'SELLO — Presets',
            __('Presets','sello'),
            $cap,
            'edit.php?post_type=sello_preset',
            '__return_empty_string'
        );

        add_submenu_page(
            'sello-hub',
            'SELLO — Filters',
            __('Filters','sello'),
            $cap,
            'edit.php?post_type=sello_filter',
            '__return_empty_string'
        );

        // Static pages (placeholders fonctionnels)
        add_submenu_page(
            'sello-hub',
            'SELLO — Design',
            __('Design','sello'),
            $cap,
            'sello-design',
            array(\Sello\Admin\DesignPage::class, 'render')
        );

        add_submenu_page(
            'sello-hub',
            'SELLO — Groups',
            __('Groups','sello'),
            $cap,
            'sello-groups',
            array(\Sello\Admin\GroupsPage::class, 'render')
        );

        add_submenu_page(
            'sello-hub',
            'SELLO — Settings',
            __('Settings','sello'),
            $cap,
            'sello-settings',
            array(\Sello\Admin\SettingsPage::class, 'render')
        );

        // Quand on clique sur le top-level "SELLO", ouvrir Dashboard
        add_action('load-toplevel_page_sello-hub', function () {
            if (!isset($_GET['page']) || $_GET['page'] === 'sello-hub') {
                $_GET['page'] = 'sello-dashboard';
            }
        });
    }
}
