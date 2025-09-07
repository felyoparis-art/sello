<?php
/**
 * Core bootstrap for SELLO (compat PHP 7.0+)
 */
namespace Sello\Core;

defined('ABSPATH') || exit;

class Plugin {
    /** @var self|null */
    private static $instance = null;

    /** Singleton */
    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    /** Boot the plugin (hooks only; no heavy work here) */
    public function boot() {
        $this->register_autoloader();

        // i18n
        add_action('init', array($this, 'load_textdomain'));

        // Data (CPTs)
        add_action('init', array('\Sello\Data\CPT', 'register'), 5);

        // Caps/roles
        add_action('admin_init', array('\Sello\Core\Capabilities', 'ensure_caps'));

        // Admin menu (top-level "SELLO")
        add_action('admin_menu', array('\Sello\Admin\Menu', 'register'), 9);

        // Admin assets (only on SELLO screens)
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Metaboxes + Save (Facets / Presets / Filters)
        add_action('add_meta_boxes', array('\Sello\Admin\Metabox\FacetMetabox',  'register'));
        add_action('add_meta_boxes', array('\Sello\Admin\Metabox\PresetMetabox', 'register'));
        add_action('add_meta_boxes', array('\Sello\Admin\Metabox\FilterMetabox', 'register'));
        add_action('save_post',       array('\Sello\Admin\Metabox\FacetMetabox',  'save'), 10, 2);
        add_action('save_post',       array('\Sello\Admin\Metabox\PresetMetabox', 'save'), 10, 2);
        add_action('save_post',       array('\Sello\Admin\Metabox\FilterMetabox', 'save'), 10, 2);

        // Front assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_front_assets'));

        // Shortcodes
        add_action('init', array('\Sello\Core\Shortcodes', 'register'));

        // REST API
        add_action('rest_api_init', array('\Sello\REST\Routes', 'register'));

        // Integrations
        add_action('init', array('\Sello\Integrations\WooCommerce', 'boot'));
        add_action('init', array('\Sello\Integrations\Elementor\Register', 'boot'));
    }

    /** Simple PSR-4 autoloader (Sello\ → includes/) */
    private function register_autoloader() {
        spl_autoload_register(function ($class) {
            $prefix = 'Sello\\';
            if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;

            $base_dir = trailingslashit(dirname(dirname(__DIR__))) . 'includes/';
            $relative = substr($class, strlen($prefix));
            $file     = $base_dir . str_replace('\\', '/', $relative) . '.php';

            if (file_exists($file)) require_once $file;
        });
    }

    /** Load translations */
    public function load_textdomain() {
        load_plugin_textdomain('sello', false, dirname(SELLO_BASENAME) . '/languages/');
    }

    /** Admin CSS/JS on SELLO pages only */
    public function enqueue_admin_assets($hook) {
        $is_sello_screen =
            (strpos((string)$hook, 'sello') !== false) ||
            (strpos((string)$hook, 'sello_') !== false) ||
            (strpos((string)$hook, 'sello-') !== false);

        if (!$is_sello_screen) return;

        wp_enqueue_style('sello-admin', SELLO_URL . 'assets/css/admin.css', array(), SELLO_VERSION);
        wp_enqueue_script('sello-admin', SELLO_URL . 'assets/js/admin.js', array('jquery'), SELLO_VERSION, true);
    }

    /** Front CSS/JS */
    public function enqueue_front_assets() {
        wp_enqueue_style('sello-frontend', SELLO_URL . 'assets/css/frontend.css', array(), SELLO_VERSION);
        wp_enqueue_script('sello-frontend', SELLO_URL . 'assets/js/frontend.js', array('jquery'), SELLO_VERSION, true);

        wp_localize_script('sello-frontend', 'SelloCfg', array(
            'rest' => array(
                'url'   => esc_url_raw(rest_url('sello/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
            ),
        ));
    }

    /** Activation: load minimal deps (no autoloader yet in WP) */
    public static function activate() {
        // Hard-require classes referenced here
        if (file_exists(SELLO_PATH . 'includes/Data/CPT.php')) {
            require_once SELLO_PATH . 'includes/Data/CPT.php';
        }
        if (file_exists(SELLO_PATH . 'includes/Core/Capabilities.php')) {
            require_once SELLO_PATH . 'includes/Core/Capabilities.php';
        }

        if (class_exists('\Sello\Data\CPT')) {
            \Sello\Data\CPT::register();
        }
        flush_rewrite_rules(false);

        if (class_exists('\Sello\Core\Capabilities')) {
            \Sello\Core\Capabilities::ensure_caps();
        }
    }

    /** Deactivation */
    public static function deactivate() {
        flush_rewrite_rules(false);
    }
}
