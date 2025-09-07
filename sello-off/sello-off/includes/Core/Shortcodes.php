<?php
/**
 * Shortcodes — [sello_filters] & [sello_hero]
 * Compat PHP 7.0+
 */
namespace Sello\Core;

defined('ABSPATH') || exit;

class Shortcodes
{
    /** Enregistre les deux shortcodes */
    public static function register()
    {
        add_shortcode('sello_filters', array(self::class, 'sc_filters'));
        add_shortcode('sello_hero',    array(self::class, 'sc_hero'));
    }

    /* ======================================================================
     * [sello_filters id="123"]
     *  - Rend un panneau de filtres basique (statique, sans AJAX pour l’instant)
     *  - Utilise le Filter #id → lit Preset ou Facets individuels
     *  - Chaque Facette liste jusqu’à "max_visible" termes
     * ==================================================================== */
    public static function sc_filters($atts = array(), $content = '')
    {
        $a = shortcode_atts(array('id'=>0), $atts, 'sello_filters');
        $filter_id = absint($a['id']);
        if ($filter_id <= 0) return '<!-- SELLO: missing filter id -->';

        $filter = get_post($filter_id);
        if (!$filter || $filter->post_type !== 'sello_filter') {
            return '<!-- SELLO: filter not found -->';
        }

        // Charger config design
        $position  = get_post_meta($filter_id,'design_position', true) ?: 'sidebar_right';
        $chips_on  = (int) get_post_meta($filter_id,'chips_on', true);
        $w_total   = (int) (get_post_meta($filter_id,'design_width_total',  true) ?: 700);
        $w_facets  = (int) (get_post_meta($filter_id,'design_width_facets', true) ?: 500);
        $w_pinned  = (int) (get_post_meta($filter_id,'design_width_pinned', true) ?: 200);

        // Récupérer les facettes du filter (via preset ou direct)
        $facets_ids = self::get_filter_facets($filter_id);

        ob_start();

        // Styles minimaux pour éviter une page “vide”
        ?>
<style>
.sello-wrap{max-width:100%;margin:10px 0}
.sello-panel{display:flex;gap:16px}
.sello-panel.left  {flex-direction:row}
.sello-panel.right {flex-direction:row-reverse}
.sello-facets{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px;box-sizing:border-box}
.sello-pinned{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px;box-sizing:border-box}
.sello-facet{border-bottom:1px solid #f3f4f6;padding:10px 0}
.sello-facet:last-child{border-bottom:none}
.sello-facet h4{margin:0 0 8px;font-size:14px}
.sello-list{margin:0;padding:0;list-style:none;display:grid;grid-template-columns:1fr 1fr;grid-gap:6px}
.sello-list.one-col{grid-template-columns:1fr}
.sello-list .count{color:#6b7280;font-size:12px}
.sello-chips{margin:0 0 10px}
.sello-chip{display:inline-block;margin:0 6px 6px 0;padding:4px 8px;border:1px solid #d1d5db;border-radius:999px;background:#fff;font-size:12px}
.sello-muted{color:#6b7280;font-size:12px}
</style>
        <?php

        // Container global
        $panel_dir = ($position === 'sidebar_left') ? 'left' : 'right';
        echo '<div class="sello-wrap" data-sello="filters" data-filter-id="'.esc_attr($filter_id).'">';
        if ($chips_on) {
            echo '<div class="sello-chips" aria-label="Selected filters">';
            echo '<span class="sello-chip">'.esc_html__('(Selected filters will appear here)','sello').'</span>';
            echo '</div>';
        }

        echo '<div class="sello-panel '.$panel_dir.'">';
        // Bloc Facets
        echo '<div class="sello-facets" style="width:'.(int)$w_facets.'px">';
        if (empty($facets_ids)) {
            echo '<p class="sello-muted">'.esc_html__('No facets selected. Edit this Filter and add some Facets or a Preset.','sello').'</p>';
        } else {
            foreach ($facets_ids as $fid) {
                self::render_facet_box($fid);
            }
        }
        echo '</div>';

        // Bloc Pinned (placeholder)
        $pinned_on = (int) get_post_meta($filter_id,'pinned_on', true);
        if ($pinned_on && $w_pinned > 0) {
            echo '<aside class="sello-pinned" style="width:'.(int)$w_pinned.'px">';
            $pinned_mode = get_post_meta($filter_id,'pinned_mode', true) ?: 'dynamic';
            if ($pinned_mode === 'manual') {
                $items = get_post_meta($filter_id,'pinned_items', true);
                if (is_array($items) && $items) {
                    foreach ($items as $it) {
                        $img   = isset($it['img']) ? esc_url($it['img']) : '';
                        $title = isset($it['title']) ? esc_html($it['title']) : '';
                        $type  = isset($it['type']) ? esc_html($it['type']) : '';
                        $val   = isset($it['value']) ? esc_html($it['value']) : '';
                        echo '<div style="margin-bottom:10px">';
                        if ($img) echo '<img src="'.$img.'" alt="" style="max-width:100%;height:auto;border-radius:6px;display:block;margin-bottom:6px">';
                        echo '<div><strong>'.$title.'</strong><br><span class="sello-muted">'.$type.': '.$val.'</span></div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="sello-muted">'.esc_html__('No manual pinned items.','sello').'</p>';
                }
            } else {
                echo '<p class="sello-muted">'.esc_html__('Dynamic pinned items (placeholder).','sello').'</p>';
            }
            echo '</aside>';
        }

        echo '</div>'; // .sello-panel
        echo '</div>'; // .sello-wrap

        return ob_get_clean();
    }

    /* ======================================================================
     * [sello_hero id="123"]
     *  - Rend un “slider/hero” simple (liste horizontale de cartes)
     *  - Basé sur slider_* du Filter #id
     * ==================================================================== */
    public static function sc_hero($atts = array(), $content = '')
    {
        $a = shortcode_atts(array('id'=>0), $atts, 'sello_hero');
        $filter_id = absint($a['id']);
        if ($filter_id <= 0) return '<!-- SELLO: missing filter id -->';

        $filter = get_post($filter_id);
        if (!$filter || $filter->post_type !== 'sello_filter') {
            return '<!-- SELLO: filter not found -->';
        }

        $slider_on   = (int) get_post_meta($filter_id,'slider_on', true);
        if (!$slider_on) return '<!-- SELLO: slider disabled for this filter -->';

        $mode   = get_post_meta($filter_id,'slider_mode', true) ?: 'dynamic';
        $click  = get_post_meta($filter_id,'slider_click', true) ?: 'apply_filter';
        $bind   = get_post_meta($filter_id,'slider_binding', true);
        if (!is_array($bind)) $bind = array();
        $bind_type    = isset($bind['type']) ? $bind['type'] : 'category';
        $bind_targets = isset($bind['targets']) ? (array)$bind['targets'] : array();
        $bind_level   = isset($bind['level']) ? $bind['level'] : '1';

        // Récupère les items
        $items = self::get_slider_items($mode, $bind_type, $bind_targets, $bind_level);

        ob_start();
        ?>
<style>
.sello-hero{margin:12px 0;padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fff}
.sello-hero h3{margin:0 0 10px}
.sello-hero-row{display:flex;gap:12px;overflow:auto;padding-bottom:6px}
.sello-card{min-width:140px;border:1px solid #e5e7eb;border-radius:8px;background:#fafafa}
.sello-card a{display:block;text-decoration:none;color:inherit}
.sello-card img{width:100%;height:auto;border-top-left-radius:8px;border-top-right-radius:8px;display:block}
.sello-card .ttl{padding:8px;font-size:13px}
</style>
<div class="sello-hero" data-sello="hero" data-filter-id="<?php echo esc_attr($filter_id); ?>" data-click="<?php echo esc_attr($click); ?>">
  <h3><?php echo esc_html__('Hero / Navigation','sello'); ?></h3>
  <div class="sello-hero-row">
    <?php
    if (empty($items)) {
        echo '<span class="sello-muted">'.esc_html__('No items to display.','sello').'</span>';
    } else {
        foreach ($items as $it) {
            $url   = isset($it['url']) ? esc_url($it['url']) : '#';
            $img   = isset($it['img']) ? esc_url($it['img']) : '';
            $title = isset($it['title']) ? esc_html($it['title']) : '';
            echo '<div class="sello-card">';
            echo '<a href="'.$url.'">';
            if ($img) echo '<img src="'.$img.'" alt="">';
            echo '<div class="ttl">'.$title.'</div>';
            echo '</a>';
            echo '</div>';
        }
    }
    ?>
  </div>
</div>
        <?php
        return ob_get_clean();
    }

    /* **********************************************************************
     * Helpers
     * ******************************************************************** */

    /** Retourne la liste des facettes pour un Filter (#ids) */
    private static function get_filter_facets($filter_id)
    {
        $mode = get_post_meta($filter_id,'content_mode', true) ?: 'preset';
        if ($mode === 'facets') {
            $f = get_post_meta($filter_id,'facets', true);
            return is_array($f) ? array_values(array_unique(array_map('intval',$f))) : array();
        }
        // via preset
        $pid = absint(get_post_meta($filter_id,'preset_id', true));
        if ($pid > 0) {
            $f = get_post_meta($pid,'facets', true);
            return is_array($f) ? array_values(array_unique(array_map('intval',$f))) : array();
        }
        return array();
    }

    /** Rend une “carte” de facette (statique, sans AJAX) */
    private static function render_facet_box($facet_id)
    {
        $p = get_post($facet_id);
        if (!$p || $p->post_type !== 'sello_facet') return;

        $title       = get_the_title($p) ?: ('Facet #'.$facet_id);
        $source_type = get_post_meta($facet_id,'source_type', true) ?: 'taxonomy';
        $source_key  = get_post_meta($facet_id,'source_key',  true) ?: 'product_cat';
        $display     = get_post_meta($facet_id,'display',     true) ?: 'checkbox';
        $select_mode = get_post_meta($facet_id,'select_mode', true) ?: 'multi';
        $max_visible = (int) (get_post_meta($facet_id,'max_visible', true) ?: 5);
        $layout_cols = (int) (get_post_meta($facet_id,'layout_cols', true) ?: 1);
        $search_on   = (int) (get_post_meta($facet_id,'search_on',   true) ?: 0);
        $counts_on   = (int) (get_post_meta($facet_id,'counts_on',   true) ?: 1);

        // Récupération simple des termes (taxonomie/attribut/catégorie)
        $taxonomy = ($source_type === 'category') ? 'product_cat' : $source_key;
        $terms = array();
        if (taxonomy_exists($taxonomy)) {
            $found = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'number'     => $max_visible, // MVP: limite dure; “Voir plus” viendra après
            ));
            if (!is_wp_error($found) && $found) $terms = $found;
        }

        echo '<div class="sello-facet" data-facet-id="'.(int)$facet_id.'" data-display="'.esc_attr($display).'" data-select="'.esc_attr($select_mode).'">';
        echo '<h4>'.esc_html($title).'</h4>';

        if ($search_on) {
            echo '<div style="margin:6px 0;"><input type="search" placeholder="'.esc_attr__('Search…','sello').'" style="width:100%"></div>';
        }

        if ($display === 'dropdown') {
            echo '<select '.($select_mode==='multi'?'multiple':'').'>';
            foreach ($terms as $t) {
                $label = esc_html($t->name);
                if ($counts_on) $label .= ' ('.(int)$t->count.')';
                echo '<option value="'.esc_attr($t->term_id).'">'.$label.'</option>';
            }
            echo '</select>';
        } else {
            $cls = 'sello-list '.($layout_cols>1 ? '' : 'one-col');
            echo '<ul class="'.$cls.'">';
            foreach ($terms as $t) {
                $label = esc_html($t->name);
                if ($counts_on) $label .= ' <span class="count">('.(int)$t->count.')</span>';
                echo '<li>';
                if ($display === 'radio' || ($display!=='checkbox' && $select_mode==='single')) {
                    echo '<label><input type="radio" name="facet_'.$facet_id.'" value="'.esc_attr($t->term_id).'"> '.$label.'</label>';
                } elseif ($display === 'buttons') {
                    echo '<button type="button" data-value="'.esc_attr($t->term_id).'" class="button">'.$label.'</button>';
                } else {
                    echo '<label><input type="checkbox" value="'.esc_attr($t->term_id).'"> '.$label.'</label>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    /**
     * Construit une liste d’items pour le Hero/Slider.
     * - mode "dynamic": liste les termes selon type/targets/level
     * - mode "manual": retourne les “targets” comme items (slug ou id → lien)
     */
    private static function get_slider_items($mode, $type, $targets, $level)
    {
        $items = array();

        // Helper pour transformer un terme en item
        $term_to_item = function($term) {
            if (!$term || is_wp_error($term)) return null;
            $url = get_term_link($term);
            if (is_wp_error($url)) $url = '#';
            return array(
                'url'   => $url,
                'img'   => '', // pas d’image yet (on branchera ACF/media plus tard)
                'title' => $term->name,
            );
        };

        if ($mode === 'manual') {
            // Manual: chaque target (id/slug) dans une taxonomie par défaut
            $tax = ($type === 'attribute' || $type === 'taxonomy') ? (isset($targets[0]) ? $targets[0] : 'product_cat') : 'product_cat';
            foreach ((array)$targets as $val) {
                $term = is_numeric($val) ? get_term((int)$val, $tax) : get_term_by('slug', sanitize_title($val), $tax);
                $it = $term_to_item($term);
                if ($it) $items[] = $it;
            }
            return $items;
        }

        // Dynamic
        $taxonomies = array();
        if ($type === 'category') {
            $taxonomies = array('product_cat');
        } elseif ($type === 'attribute' || $type === 'taxonomy') {
            // targets ici peuvent être “taxonomies” (ex: pa_marque). S’il est vide, fallback product_cat
            $taxonomies = $targets ? (array)$targets : array('product_cat');
        } else {
            $taxonomies = array('product_cat');
        }

        foreach ($taxonomies as $tax) {
            if (!taxonomy_exists($tax)) continue;

            // S’il n’y a pas de cibles, on liste les termes “top-level”
            if (empty($targets) || ($type!=='attribute' && $type!=='taxonomy')) {
                $top = get_terms(array(
                    'taxonomy'   => $tax,
                    'hide_empty' => false,
                    'parent'     => 0,
                    'number'     => 20,
                ));
                if (!is_wp_error($top) && $top) {
                    foreach ($top as $term) {
                        $it = $term_to_item($term);
                        if ($it) $items[] = $it;
                    }
                }
            } else {
                // Pour chaque “target” (slug/id), lister ses enfants (selon level=1 → enfants directs)
                foreach ((array)$targets as $target) {
                    $term = is_numeric($target) ? get_term((int)$target, $tax) : get_term_by('slug', sanitize_title($target), $tax);
                    if (!$term || is_wp_error($term)) continue;

                    $children = get_terms(array(
                        'taxonomy'   => $tax,
                        'hide_empty' => false,
                        'parent'     => (int)$term->term_id,
                        'number'     => 50,
                    ));
                    if (!is_wp_error($children) && $children) {
                        foreach ($children as $c) {
                            $it = $term_to_item($c);
                            if ($it) $items[] = $it;
                        }
                    } else {
                        // Pas d’enfants → on push le terme lui-même
                        $it = $term_to_item($term);
                        if ($it) $items[] = $it;
                    }
                }
            }
        }

        return $items;
    }
}
