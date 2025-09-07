<?php
/**
 * Plugin Name: SELLO — Facets, Presets, Filters, Slider & Pinned
 * Description: SELLO centralise la création de Facets, Presets, Filters, Slider & Pinned pour WooCommerce (admin + front).
 * Version: 0.1.0
 * Author: IDRISS CHANAOUI
 * Requires at least: 5.8
 * Requires PHP: 7.0
 * Text Domain: sello
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

/* -----------------------------------------------------------------------
 * Constants
 * -------------------------------------------------------------------- */
if (!defined('SELLO_VERSION'))  define('SELLO_VERSION', '0.1.0');
if (!defined('SELLO_FILE'))     define('SELLO_FILE', __FILE__);
if (!defined('SELLO_BASENAME')) define('SELLO_BASENAME', plugin_basename(__FILE__));
if (!defined('SELLO_PATH'))     define('SELLO_PATH', plugin_dir_path(__FILE__));
if (!defined('SELLO_URL'))      define('SELLO_URL', plugin_dir_url(__FILE__));

/* -----------------------------------------------------------------------
 * PHP version guard (compat 7.0+)
 * -------------------------------------------------------------------- */
if (version_compare(PHP_VERSION, '7.0', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>SELLO</strong> nécessite PHP 7.0 ou plus.</p></div>';
    });
    return;
}

/* -----------------------------------------------------------------------
 * Load core & boot
 * -------------------------------------------------------------------- */
require_once SELLO_PATH . 'includes/Core/Plugin.php';

register_activation_hook(__FILE__, ['Sello\Core\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Sello\Core\Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
    Sello\Core\Plugin::instance()->boot();
});
