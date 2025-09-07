<?php
/**
 * Domain — Preset (CPT sello_preset)
 * Compat PHP 7.0+
 *
 * Rôle :
 *  - Définir un groupe de facettes réutilisable
 *  - Metabox d’édition (sélection des Facets)
 *  - Sauvegarde assainie via Services\Validator
 *
 * Métas principales :
 *  - facets : array<int> (IDs de posts sello_facet)
 *  - note   : string (description interne facultative)
 */
namespace Sello\Domain;

use Sello\Services\Validator;

defined('ABSPATH') || exit;

class Preset
{
    /** Hooker la metabox et la sauvegarde */
    public static function boot()
    {
        add_action('add_meta_boxes', array(self::class, 'add_box'));
        add_action('save_post',      array(self::class, 'save'), 10, 2);
    }

    /* ============================================================
     * Metabox
     * ========================================================== */

    public static function add_box()
    {
        add_meta_box(
            'sello_preset_box',
            __('SELLO — Preset (Facets group)', 'sello'),
            array(self::class, 'render_box'),
            'sello_preset',
            'normal',
            'high'
        );
    }

    public static function render_box($post)
    {
        if (!current_user_can('manage_sello')) {
            echo '<p>'.esc_html__('You do not have permission to edit this.', 'sello').'</p>';
            return;
        }

        wp_nonce_field('sello_preset_save', 'sello_preset_nonce');

        // Valeurs actuelles
        $facets = get_post_meta($post->ID, 'facets', true);
        if (!is_array($facets)) $facets = array();
        $facets = array_values(array_unique(array_map('intval', $facets)));

        $note = (string) get_post_meta($post->ID, 'note', true);

        // Récup liste des facettes disponibles
        $facet_posts = get_posts(array(
            'post_type'              => 'sello_facet',
            'post_status'            => array('publish','draft','pending'),
            'numberposts'            => 200,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        // Styles
        echo '<style>
            .sello-box{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:14px;margin-top:12px}
            .sello-grid{display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:start}
            .sello-muted{color:#6b7280;font-size:12px}
            .sello-facets-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;max-height:340px;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fafafa}
            .sello-pill{display:flex;align-items:center;gap:8px;border:1px solid #e5e7eb;background:#fff;border-radius:999px;padding:6px 10px}
            .sello-pill input{margin:0}
            .sello-chosen{display:flex;flex-wrap:wrap;gap:6px}
            .sello-note{width:100%;min-height:80px}
        </style>';

        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Facets included in this Preset','sello').'</h2>';
        echo '<div class="sello-grid">';

        echo '<div><strong>'.esc_html__('Select Facets','sello').'</strong><div class="sello-muted">'.esc_html__('Pick the facets to include. Order will be respected below.','sello').'</div></div>';

        echo '<div>';
        if (empty($facet_posts)) {
            echo '<p class="sello-muted">'.esc_html__('No facets created yet. Create some facets first.', 'sello').'</p>';
        } else {
            // Liste de sélection
            echo '<div class="sello-facets-list">';
            foreach ($facet_posts as $fid) {
                $title = get_the_title($fid);
                if ($title === '') $title = '('.__('no title','sello').')';
                $checked = in_array((int)$fid, $facets, true) ? ' checked' : '';
                echo '<label class="sello-pill"><input type="checkbox" name="sello[facets][]" value="'.(int)$fid.'"'.$checked.'> '.esc_html($title).' (#'.(int)$fid.')</label>';
            }
            echo '</div>';

            // Ordre manuel via simple text (ids CSV) — MVP
            echo '<p style="margin:10px 0 6px"><strong>'.esc_html__('Order (optional)','sello').'</strong> <span class="sello-muted">'.esc_html__('IDs comma-separated; leave empty to keep default alphabetical.','sello').'</span></p>';
            echo '<input type="text" name="sello[facets_order]" class="regular-text" value="'.esc_attr(implode(', ', $facets)).'" placeholder="12, 34, 56" />';
        }
        echo '</div>'; // right col
        echo '</div>'; // grid
        echo '</div>'; // box

        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Internal note','sello').'</h2>';
        echo '<textarea class="sello-note" name="sello[note]" placeholder="'.esc_attr__('Optional notes for editors…','sello').'">'.esc_textarea($note).'</textarea>';
        echo '</div>';
    }

    /* ============================================================
     * Sauvegarde
     * ========================================================== */

    public static function save($post_id, $post)
    {
        if ($post->post_type !== 'sello_preset') return;

        // Nonce & droits
        if (!isset($_POST['sello_preset_nonce']) || !wp_verify_nonce($_POST['sello_preset_nonce'], 'sello_preset_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('manage_sello')) return;

        $in = isset($_POST['sello']) && is_array($_POST['sello']) ? $_POST['sello'] : array();

        // Facets sélectionnées
        $facets = array();
        if (isset($in['facets']) && is_array($in['facets'])) {
            foreach ($in['facets'] as $fid) {
                if (is_numeric($fid)) $facets[] = (int)$fid;
            }
            $facets = array_values(array_unique(array_filter($facets)));
        }

        // Ordre optionnel (CSV d’IDs)
        $order = array();
        if (!empty($in['facets_order'])) {
            $order = Validator::csvToArray($in['facets_order']);
            $order = array_values(array_filter(array_map('intval', $order)));
        }

        // Si un ordre est fourni, on réordonne $facets selon cet ordre
        if (!empty($order) && !empty($facets)) {
            $map = array_flip($order); // id => position
            usort($facets, function($a,$b) use($map) {
                $pa = isset($map[$a]) ? $map[$a] : PHP_INT_MAX;
                $pb = isset($map[$b]) ? $map[$b] : PHP_INT_MAX;
                if ($pa === $pb) return 0;
                return ($pa < $pb) ? -1 : 1;
            });
        }

        $note = isset($in['note']) ? Validator::text($in['note']) : '';

        update_post_meta($post_id, 'facets', $facets);
        update_post_meta($post_id, 'note',   $note);
    }
}
