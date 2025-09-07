<?php
/**
 * Domain — Design (sidebar & responsive behavior)
 * Compat PHP 7.0+
 *
 * Rôle :
 *  - Lire les réglages globaux (Admin\DesignPage) + ceux d’un Filter
 *  - Fusionner et normaliser pour le rendu frontend
 *  - Fournir helpers (data-attrs, classes, takeover CSS)
 *
 * Clés retournées par get_for_filter():
 *  [
 *    'desktop_pos' => 'left|right',   // global
 *    'tablet_pos'  => 'left|right',   // global
 *    'mobile_pos'  => 'left|right',   // global
 *    'anim'        => 'slide|fade',   // global (pour évolution)
 *    'overlay_mobile' => 0|1,         // global
 *    'close_outside'  => 0|1,         // global
 *    'bp_tablet'      => int,         // px
 *    'bp_mobile'      => int,         // px
 *    'w_total'        => int,         // largeur totale du conteneur
 *    'w_facets'       => int,         // partie filtres
 *    'w_pinned'       => int,         // partie pinned
 *    'position'       => 'sidebar_left|sidebar_right', // override par Filter
 *    'chips_on'       => 0|1,         // depuis Filter
 *    'takeover'       => 0|1          // depuis Filter
 *  ]
 */

namespace Sello\Domain;

use Sello\Admin\DesignPage;

defined('ABSPATH') || exit;

class Design
{
    /** Défauts globaux si option absente (doit refléter Migrations::ensure_default_options) */
    private static function defaults_global()
    {
        return array(
            'desktop_pos'    => 'right',
            'tablet_pos'     => 'right',
            'mobile_pos'     => 'left',
            'anim'           => 'slide',
            'overlay_mobile' => 1,
            'close_outside'  => 1,
            'bp_tablet'      => 1024,
            'bp_mobile'      => 767,
            'w_total'        => 700,
            'w_facets'       => 500,
            'w_pinned'       => 200,
        );
    }

    /** Lit et normalise les options globales (DesignPage::OPTION_KEY) */
    public static function get_global()
    {
        $opt = get_option(DesignPage::OPTION_KEY, array());
        if (!is_array($opt)) $opt = array();

        $d = array_merge(self::defaults_global(), $opt);

        $out = array();
        $out['desktop_pos']    = self::pos_lr(isset($d['desktop_pos']) ? $d['desktop_pos'] : 'right');
        $out['tablet_pos']     = self::pos_lr(isset($d['tablet_pos'])  ? $d['tablet_pos']  : 'right');
        $out['mobile_pos']     = self::pos_lr(isset($d['mobile_pos'])  ? $d['mobile_pos']  : 'left');
        $out['anim']           = self::pick(isset($d['anim']) ? $d['anim'] : 'slide', array('slide','fade'), 'slide');
        $out['overlay_mobile'] = self::bool($d, 'overlay_mobile', 1);
        $out['close_outside']  = self::bool($d, 'close_outside', 1);
        $out['bp_tablet']      = self::int($d, 'bp_tablet', 600, 2000, 1024);
        $out['bp_mobile']      = self::int($d, 'bp_mobile', 320,  1024,  767);
        $out['w_total']        = self::int($d, 'w_total',  300,  2400,  700);
        $out['w_facets']       = self::int($d, 'w_facets', 200,  2000,  500);
        $out['w_pinned']       = self::int($d, 'w_pinned',   0,  2000,  200);

        return $out;
    }

    /**
     * Fusion complète pour un Filter
     * - Override des largeurs & position par les métas du Filter
     * - Ajoute chips_on & takeover
     */
    public static function get_for_filter($filter_id)
    {
        $filter_id = (int)$filter_id;
        $g = self::get_global();

        // Valeurs Filter
        $position = get_post_meta($filter_id, 'design_position', true) ?: 'sidebar_right';
        $w_total  = (int)(get_post_meta($filter_id, 'design_width_total',  true) ?: $g['w_total']);
        $w_facets = (int)(get_post_meta($filter_id, 'design_width_facets', true) ?: $g['w_facets']);
        $w_pinned = (int)(get_post_meta($filter_id, 'design_width_pinned', true) ?: $g['w_pinned']);
        $chips_on = (int)(get_post_meta($filter_id, 'chips_on', true) ?: 1);
        $takeover = (int)(get_post_meta($filter_id, 'takeover', true) ?: 0);

        // Normalisation & cohérence
        $w_total  = self::bound($w_total, 300, 2400);
        $w_facets = self::bound($w_facets, 200, 2000);
        $w_pinned = self::bound($w_pinned,   0, 2000);

        // Si facets + pinned dépassent total, on rogne pinned en priorité
        if (($w_facets + $w_pinned) > $w_total) {
            $overflow = ($w_facets + $w_pinned) - $w_total;
            $w_pinned = max(0, $w_pinned - $overflow);
            if (($w_facets + $w_pinned) > $w_total) {
                // En dernier recours, ajuster facets
                $w_facets = max(200, $w_total - $w_pinned);
            }
        }

        return array(
            'desktop_pos'    => $g['desktop_pos'],
            'tablet_pos'     => $g['tablet_pos'],
            'mobile_pos'     => $g['mobile_pos'],
            'anim'           => $g['anim'],
            'overlay_mobile' => $g['overlay_mobile'],
            'close_outside'  => $g['close_outside'],
            'bp_tablet'      => $g['bp_tablet'],
            'bp_mobile'      => $g['bp_mobile'],
            'w_total'        => $w_total,
            'w_facets'       => $w_facets,
            'w_pinned'       => $w_pinned,
            'position'       => self::pos_sidebar($position),
            'chips_on'       => $chips_on,
            'takeover'       => $takeover,
        );
    }

    /**
     * Génère des data-attributes pour le conteneur HTML (facile à consommer côté JS/CSS).
     * Exemple d’usage :
     *   echo '<div class="sello-wrapper" '.\Sello\Domain\Design::data_attributes($filter_id).'>…</div>';
     */
    public static function data_attributes($filter_id)
    {
        $d = self::get_for_filter($filter_id);

        $attrs = array(
            'data-pos-desktop' => esc_attr($d['desktop_pos']),
            'data-pos-tablet'  => esc_attr($d['tablet_pos']),
            'data-pos-mobile'  => esc_attr($d['mobile_pos']),
            'data-anim'        => esc_attr($d['anim']),
            'data-overlay-m'   => (int)$d['overlay_mobile'],
            'data-close-out'   => (int)$d['close_outside'],
            'data-bp-tablet'   => (int)$d['bp_tablet'],
            'data-bp-mobile'   => (int)$d['bp_mobile'],
            'data-w-total'     => (int)$d['w_total'],
            'data-w-facets'    => (int)$d['w_facets'],
            'data-w-pinned'    => (int)$d['w_pinned'],
            'data-position'    => esc_attr($d['position']),
            'data-chips'       => (int)$d['chips_on'],
        );

        $out = '';
        foreach ($attrs as $k=>$v) {
            $out .= $k . '="' . $v . '" ';
        }
        return trim($out);
    }

    /**
     * Injecte un petit CSS "takeover" pour masquer les filtres natifs des thèmes
     * (limitons-nous à des sélecteurs courants — peut être complété selon le thème).
     * Appeler au moment du rendu si $design['takeover'] === 1.
     */
    public static function emit_takeover_css()
    {
        // CSS minimal, volontairement prudent
        echo '<style id="sello-takeover-css">
/* Masquer certains widgets de filtres courants dans la sidebar */
.widget_layered_nav,
.widget_product_categories .children,
.widget_price_filter,
.woocommerce .widget_price_filter,
.woocommerce .widget_layered_nav,
.woocommerce-widget-layered-nav,
.woocommerce-widget-layered-nav-list {
    display: none !important;
}
</style>';
    }

    /* ===========================
     * Helpers internes
     * ========================= */

    private static function pos_lr($v)
    {
        $v = is_string($v) ? strtolower($v) : 'right';
        return in_array($v, array('left','right'), true) ? $v : 'right';
    }

    private static function pos_sidebar($v)
    {
        $v = is_string($v) ? strtolower($v) : 'sidebar_right';
        return in_array($v, array('sidebar_left','sidebar_right'), true) ? $v : 'sidebar_right';
    }

    private static function pick($v, array $allowed, $fallback)
    {
        $v = is_string($v) ? $v : '';
        return in_array($v, $allowed, true) ? $v : $fallback;
    }

    private static function bool($arr, $key, $def = 0)
    {
        $v = isset($arr[$key]) ? $arr[$key] : $def;
        return empty($v) ? 0 : 1;
    }

    private static function int($arr, $key, $min, $max, $def)
    {
        $v = isset($arr[$key]) ? $arr[$key] : $def;
        if (!is_numeric($v)) $v = $def;
        $v = (int)$v;
        if ($v < $min) $v = (int)$min;
        if ($v > $max) $v = (int)$max;
        return $v;
    }

    private static function bound($n, $min, $max)
    {
        $n = (int)$n;
        if ($n < $min) return (int)$min;
        if ($n > $max) return (int)$max;
        return $n;
    }
}
