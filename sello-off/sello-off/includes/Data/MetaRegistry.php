<?php
/*========================================================================
 File: includes/Data/MetaRegistry.php
========================================================================*/
namespace Sello\Data;

defined('ABSPATH') || exit;

class MetaRegistry {
    // Facet metas
    public const FACET = [
        'source_type',      // category|taxonomy|attribute|acf|meta
        'source_key',       // meta key OR taxonomy/attribute slug
        'display',          // list_v|list_h|checkbox|radio|dropdown|chips|swatch|range|rating
        'layout_cols',      // 1|2
        'search_on',        // bool
        'max_visible',      // int
        'counts_on',        // bool
        'logic',            // AND|OR
        'select_mode',      // single|multi
        // hierarchy
        'hierarchic',       // bool
        'level',            // 1|2|3|all
        'include',          // array(term_ids)
        'exclude',          // array(term_ids)
    ];

    // Preset metas
    public const PRESET = [
        'facets',           // array of facet IDs (order)
        'facet_states',     // array open|collapsed keyed by facet id
    ];

    // Filter metas
    public const FILTER = [
        // scope
        'scope_type',       // category|taxonomy|attribute|brand|univers
        'scope_targets',    // array of term ids/slugs
        'hierarchic',       // bool
        'level',            // 1|2|3|all
        'include',          // array(term_ids)
        'exclude',          // array(term_ids)
        // content
        'content_mode',     // preset|manual_facets
        'preset_id',        // int
        'facets',           // array of facet IDs when manual
        // design
        'design_position',  // sidebar_left|sidebar_right|offcanvas_l|offcanvas_r|offcanvas_t|offcanvas_b|topbar
        'design_width_total',  // int px
        'design_width_facets', // int px
        'design_width_pinned', // int px
        'chips_on',         // bool
        'sticky_footer_on', // bool
        // pinned
        'pinned_on',        // bool
        'pinned_mode',      // dynamic|manual
        'pinned_items',     // array of {image_id,title,link_type,link_target}
        // slider
        'slider_on',        // bool
        'slider_binding',   // {type, targets[], level, include[], exclude[]}
        'slider_mode',      // dynamic|manual|mixed
        'slider_items',     // array of {image_source, image_id, title, link_type, target|url}
        'slider_click',     // apply_filter|navigate
        'slider_drilldown', // bool
        // devices
        'visible_desktop',  // bool
        'visible_tablet',   // bool
        'visible_mobile',   // bool
        // attach
        'auto_attach',      // bool
        'takeover',         // bool
    ];
}
