<?php
/**
 * WooCommerce Integration — auto-attach Filter panel (+ Hero) on archives
 * Compat PHP 7.0+
 */
namespace Sello\Integrations;

defined('ABSPATH') || exit;

class WooCommerce
{
    /** Flag interne pour éviter les appels imbriqués */
    private static $resolving = false;

    /** Brancher les hooks WooCommerce */
    public static function boot()
    {
        if (!function_exists('is_woocommerce')) return;

        // Injecte Hero + Panel avant la boucle (archive boutique / catégories)
        add_action('woocommerce_before_shop_loop', array(self::class,'render_auto'), 5);

        // Si “Takeover” est actif sur le filtre correspondant, masque des blocs thème
        add_action('wp_head', array(self::class,'maybe_takeover_css'));
    }

    /**
     * Rend automatiquement le Hero + Filters si un Filter SELLO correspond
     * au contexte courant (catégorie produit, archive produits…).
     */
    public static function render_auto()
    {
        $fid = self::find_matching_filter_id();
        if ($fid <= 0) return;

        // Hero / Slider si activé
        $slider_on = (int) get_post_meta($fid, 'slider_on', true);
        if ($slider_on) {
            echo do_shortcode('[sello_hero id="'.(int)$fid.'"]');
        }

        // Panneau de filtres
        echo do_shortcode('[sello_filters id="'.(int)$fid.'"]');
    }

    /**
     * Si le filtre correspondant a “Takeover” = ON, on insère un petit CSS
     * pour cacher les filtres par défaut de certains thèmes (placeholder).
     */
    public static function maybe_takeover_css()
    {
        if (is_admin()) return;

        $fid = self::find_matching_filter_id();
        if ($fid <= 0) return;

        $takeover = (int) get_post_meta($fid,'takeover',true);
        if (!$takeover) return;

        // CSS minimal et non destructif (ajuste selon le thème si besoin)
        echo "<style id='sello-takeover'>
/* Hide common theme filter sidebars/widgets (safe defaults) */
.widget_layered_nav, .widget_product_categories, .widget_price_filter,
.woocommerce-widget-layered-nav,
.woocommerce-widget-layered-nav-filters {
    display:none !important;
}
</style>";
    }

    /**
     * Trouve l’ID du premier Filter SELLO qui matche le contexte.
     * Règles MVP :
     *  - scope_type=category : match sur taxonomy 'product_cat'
     *  - scope_targets vide → “partout” dans ce scope
     *  - hierarchic=ON → match si une cible est un parent de la catégorie courante
     */
    private static function find_matching_filter_id()
    {
        if (self::$resolving) return 0;
        self::$resolving = true;

        $filters = get_posts(array(
            'post_type'              => 'sello_filter',
            'post_status'            => 'publish',
            'numberposts'            => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ));

        $match_id = 0;

        // Contexte : catégorie produit
        if (is_tax('product_cat')) {
            $term = get_queried_object();
            if ($term && !empty($term->term_id)) {
                foreach ($filters as $fid) {
                    $scope_type   = get_post_meta($fid,'scope_type',true); if (!$scope_type) $scope_type = 'category';
                    $auto_attach  = (int) get_post_meta($fid,'auto_attach',true);
                    if (!$auto_attach || $scope_type !== 'category') continue;

                    $targets   = get_post_meta($fid,'scope_targets',true);
                    $targets   = is_array($targets) ? $targets : array();
                    $hierarchic= (int) get_post_meta($fid,'hierarchic',true);

                    // Sans cibles → valable partout (dans ce scope)
                    if (empty($targets)) { $match_id = (int)$fid; break; }

                    // Match direct id/slug
                    $matched = false;
                    foreach ($targets as $t) {
                        $t = (string)$t;
                        if ($t === (string)$term->term_id || $t === (string)$term->slug) { $matched = true; break; }
                    }
                    if ($matched) { $match_id = (int)$fid; break; }

                    // Match parent si hierarchic ON
                    if ($hierarchic) {
                        $parents = get_ancestors((int)$term->term_id, 'product_cat');
                        if ($parents) {
                            foreach ($targets as $t) {
                                $tid = is_numeric($t) ? (int)$t : 0;
                                if (!$tid && $t) {
                                    $obj = get_term_by('slug', sanitize_title($t), 'product_cat');
                                    if ($obj && !is_wp_error($obj)) $tid = (int)$obj->term_id;
                                }
                                if ($tid && in_array($tid, $parents, true)) { $match_id = (int)$fid; break 2; }
                            }
                        }
                    }
                }
            }
        }
        // Contexte : archive produits (boutique) — prendre le 1er auto_attach scope=category
        elseif (is_post_type_archive('product')) {
            foreach ($filters as $fid) {
                $scope_type  = get_post_meta($fid,'scope_type',true); if (!$scope_type) $scope_type = 'category';
                $auto_attach = (int) get_post_meta($fid,'auto_attach',true);
                if ($auto_attach && $scope_type === 'category') { $match_id = (int)$fid; break; }
            }
        }
        // Contexte : recherche produit — même logique simple
        elseif (is_search()) {
            foreach ($filters as $fid) {
                $auto_attach = (int) get_post_meta($fid,'auto_attach',true);
                if ($auto_attach) { $match_id = (int)$fid; break; }
            }
        }

        self::$resolving = false;
        return $match_id;
    }
}
