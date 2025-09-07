<?php
/**
 * Elementor Integration — Register category & widgets
 * Compat Elementor 3.5+ / PHP 7.0+
 *
 * - Ajoute une catégorie "SELLO"
 * - Enregistre 2 widgets : Filters & Slider (si Elementor actif)
 * - Dégrade proprement si Elementor absent
 */

namespace Sello\Integrations\Elementor;

defined('ABSPATH') || exit;

class Register
{
    /** Hook bootstrap (appelé depuis Core\Plugin::boot()) */
    public static function boot()
    {
        // Vérifier Elementor (on hook quand même tard pour éviter fatal si inactif)
        add_action('plugins_loaded', array(__CLASS__, 'maybe_hook_elementor'), 20);
    }

    /** Accroche les hooks Elementor si présent */
    public static function maybe_hook_elementor()
    {
        if (!self::is_elementor_active()) {
            // Optionnel : admin notice légère (seulement aux admins)
            add_action('admin_notices', array(__CLASS__, 'admin_notice_missing_elementor'));
            return;
        }

        // Catégorie personnalisée "SELLO"
        add_action('elementor/elements/categories_registered', array(__CLASS__, 'register_category'));

        // Enregistrement des widgets (API Elementor 3.5+)
        add_action('elementor/widgets/register', array(__CLASS__, 'register_widgets'));

        // Compat ancien hook (3.4 et moins) – inoffensif si non déclenché
        add_action('elementor/widgets/widgets_registered', array(__CLASS__, 'register_widgets_legacy'));
    }

    /** Détecte si Elementor est actif et initialisé */
    private static function is_elementor_active()
    {
        if (!did_action('elementor/loaded')) {
            // Elementor pas encore chargé → essayer via function_exists
            return class_exists('\Elementor\Plugin');
        }
        return true;
    }

    /** Ajoute la catégorie "SELLO" dans le panneau Elementor */
    public static function register_category($elements_manager)
    {
        // Depuis Elementor 3.x : $elements_manager est \Elementor\Elements_Manager
        if (method_exists($elements_manager, 'add_category')) {
            $elements_manager->add_category('sello', array(
                'title' => __('SELLO', 'sello'),
                'icon'  => 'fa fa-filter',
            ));
        }
    }

    /** Enregistre les widgets (API moderne) */
    public static function register_widgets($widgets_manager)
    {
        // $widgets_manager est \Elementor\Widgets_Manager
        // Enregistrer le widget "Filters"
        if (class_exists('\Sello\Integrations\Elementor\WidgetFilters')) {
            try {
                $widgets_manager->register(new \Sello\Integrations\Elementor\WidgetFilters());
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SELLO] Elementor register WidgetFilters failed: '.$e->getMessage());
                }
            }
        }

        // Enregistrer le widget "Slider"
        if (class_exists('\Sello\Integrations\Elementor\WidgetSlider')) {
            try {
                $widgets_manager->register(new \Sello\Integrations\Elementor\WidgetSlider());
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SELLO] Elementor register WidgetSlider failed: '.$e->getMessage());
                }
            }
        }
    }

    /** Compat ancien hook (avant Elementor 3.5) */
    public static function register_widgets_legacy()
    {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }
        $manager = \Elementor\Plugin::instance()->widgets_manager;
        if (!$manager) return;

        // Filters
        if (class_exists('\Sello\Integrations\Elementor\WidgetFilters')) {
            try {
                $manager->register_widget_type(new \Sello\Integrations\Elementor\WidgetFilters());
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SELLO] Elementor (legacy) register WidgetFilters failed: '.$e->getMessage());
                }
            }
        }

        // Slider
        if (class_exists('\Sello\Integrations\Elementor\WidgetSlider')) {
            try {
                $manager->register_widget_type(new \Sello\Integrations\Elementor\WidgetSlider());
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SELLO] Elementor (legacy) register WidgetSlider failed: '.$e->getMessage());
                }
            }
        }
    }

    /** Notice admin si Elementor est manquant/inactif (facultatif, discret) */
    public static function admin_notice_missing_elementor()
    {
        if (!current_user_can('activate_plugins')) return;

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        // Limiter l'affichage aux écrans SELLO pour ne pas spammer
        $show = true;
        if ($screen && is_object($screen) && isset($screen->id)) {
            $show = (strpos($screen->id, 'sello') !== false || $screen->id === 'toplevel_page_sello');
        }
        if (!$show) return;

        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo esc_html__('SELLO: Elementor is not active. Elementor widgets (Filters/Slider) will be unavailable until Elementor is activated.', 'sello');
        echo '</p></div>';
    }
}
