<?php
/**
 * REST API Routes — sello/v1
 * Compat PHP 7.0+
 *
 * Endpoints (MVP, lecture seule) :
 *  - GET /sello/v1/ping
 *  - GET /sello/v1/filters/{id}          → renvoie la config d’un Filter
 *  - GET /sello/v1/facets/{id}/terms     → renvoie des termes pour une Facet
 */
namespace Sello\REST;

defined('ABSPATH') || exit;

class Routes
{
    /** Enregistrement des routes */
    public static function register()
    {
        register_rest_route('sello/v1', '/ping', array(
            'methods'             => 'GET',
            'callback'            => array(self::class, 'ping'),
            'permission_callback' => '__return_true', // public (lecture)
        ));

        register_rest_route('sello/v1', '/filters/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array(self::class, 'get_filter'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($value){ return $value > 0; },
                ),
            ),
        ));

        register_rest_route('sello/v1', '/facets/(?P<id>\d+)/terms', array(
            'methods'             => 'GET',
            'callback'            => array(self::class, 'get_facet_terms'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($value){ return $value > 0; },
                ),
                'search' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'number' => array(
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ),
                'offset' => array(
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }

    /* ===================== Callbacks ===================== */

    public static function ping($req)
    {
        return rest_ensure_response(array(
            'ok'       => true,
            'version'  => defined('SELLO_VERSION') ? SELLO_VERSION : '0',
            'time'     => current_time('mysql'),
            'site_url' => site_url('/'),
        ));
    }

    /**
     * GET /filters/{id}
     * Renvoie la configuration d’un Filter (métas principales)
     */
    public static function get_filter($req)
    {
        $id = absint($req->get_param('id'));
        $p  = get_post($id);
        if (!$p || $p->post_type !== 'sello_filter') {
            return new \WP_Error('sello_not_found', __('Filter not found.', 'sello'), array('status'=>404));
        }

        // Content
        $mode      = get_post_meta($id,'content_mode', true) ?: 'preset';
        $preset_id = (int) get_post_meta($id,'preset_id', true);
        $facets    = get_post_meta($id,'facets', true);
        if (!is_array($facets)) $facets = array();
        $facets    = array_values(array_unique(array_map('intval', $facets)));

        // Si preset → récupérer ses facettes
        if ($mode === 'preset' && $preset_id > 0) {
            $pf = get_post_meta($preset_id,'facets', true);
            if (is_array($pf)) {
                $facets = array_values(array_unique(array_map('intval', $pf)));
            }
        }

        // Scope
        $scope = array(
            'type'     => get_post_meta($id,'scope_type', true) ?: 'category',
            'targets'  => get_post_meta($id,'scope_targets', true) ?: array(),
            'level'    => get_post_meta($id,'level', true) ?: 'all',
            'include'  => get_post_meta($id,'include', true) ?: array(),
            'exclude'  => get_post_meta($id,'exclude', true) ?: array(),
            'hierarchic'=> (int) get_post_meta($id,'hierarchic', true),
            'auto_attach'=> (int) get_post_meta($id,'auto_attach', true),
            'takeover'   => (int) get_post_meta($id,'takeover', true),
        );

        // Design
        $design = array(
            'position' => get_post_meta($id,'design_position', true) ?: 'sidebar_right',
            'chips_on' => (int) get_post_meta($id,'chips_on', true),
            'widths'   => array(
                'total'  => (int) (get_post_meta($id,'design_width_total',  true) ?: 700),
                'facets' => (int) (get_post_meta($id,'design_width_facets', true) ?: 500),
                'pinned' => (int) (get_post_meta($id,'design_width_pinned', true) ?: 200),
            ),
        );

        // Pinned
        $pinned = array(
            'on'     => (int) get_post_meta($id,'pinned_on', true),
            'mode'   => get_post_meta($id,'pinned_mode', true) ?: 'dynamic',
            'items'  => get_post_meta($id,'pinned_items', true) ?: array(),
        );

        // Slider
        $slider = array(
            'on'      => (int) get_post_meta($id,'slider_on', true),
            'mode'    => get_post_meta($id,'slider_mode', true) ?: 'dynamic',
            'click'   => get_post_meta($id,'slider_click', true) ?: 'apply_filter',
            'drill'   => (int) get_post_meta($id,'slider_drilldown', true),
            'binding' => get_post_meta($id,'slider_binding', true) ?: array(),
        );

        $payload = array(
            'id'          => $id,
            'title'       => get_the_title($p),
            'status'      => get_post_status($p),
            'content'     => array('mode'=>$mode, 'preset_id'=>$preset_id, 'facets'=>$facets),
            'scope'       => $scope,
            'design'      => $design,
            'pinned'      => $pinned,
            'slider'      => $slider,
            'edit_link'   => get_edit_post_link($id, ''),
            'shortcodes'  => array(
                'filters' => '[sello_filters id="'.$id.'"]',
                'hero'    => '[sello_hero id="'.$id.'"]',
            ),
        );

        return rest_ensure_response($payload);
    }

    /**
     * GET /facets/{id}/terms
     * Renvoie une liste de termes *brute* pour une Facet (MVP).
     * Query params : search, number, offset
     */
    public static function get_facet_terms($req)
    {
        $id     = absint($req->get_param('id'));
        $search = (string) $req->get_param('search');
        $number = max(1, (int)$req->get_param('number'));
        $offset = max(0, (int)$req->get_param('offset'));

        $p = get_post($id);
        if (!$p || $p->post_type !== 'sello_facet') {
            return new \WP_Error('sello_not_found', __('Facet not found.', 'sello'), array('status'=>404));
        }

        $type = get_post_meta($id,'source_type', true) ?: 'taxonomy';
        $key  = get_post_meta($id,'source_key',  true) ?: 'product_cat';
        $tax  = ($type === 'category') ? 'product_cat' : $key;

        if (!taxonomy_exists($tax)) {
            return new \WP_Error('sello_bad_tax', __('Taxonomy does not exist.', 'sello'), array('status'=>400));
        }

        $args = array(
            'taxonomy'   => $tax,
            'hide_empty' => false,
            'number'     => $number,
            'offset'     => $offset,
        );
        if ($search !== '') $args['search'] = $search;

        $terms = get_terms($args);
        if (is_wp_error($terms)) {
            return new \WP_Error('sello_terms_error', $terms->get_error_message(), array('status'=>400));
        }

        $out = array();
        foreach ($terms as $t) {
            $out[] = array(
                'id'    => (int)$t->term_id,
                'slug'  => (string)$t->slug,
                'name'  => (string)$t->name,
                'count' => (int)$t->count,
                'link'  => get_term_link($t),
            );
        }

        return rest_ensure_response(array(
            'facet_id' => $id,
            'taxonomy' => $tax,
            'items'    => $out,
            'total'    => count($out), // MVP (pas de found_rows ici)
        ));
    }
}
