<?php
/**
 * Metabox — Facet (source + display + options)
 * Compat PHP 7.0+
 */
namespace Sello\Admin\Metabox;

defined('ABSPATH') || exit;

class FacetMetabox
{
    const NONCE = 'sello_facet_mb_nonce';

    /** Enregistre la metabox pour le CPT 'sello_facet' */
    public static function register()
    {
        add_meta_box(
            'sello_facet_main',
            __('Facet settings','sello'),
            array(self::class, 'render'),
            'sello_facet',
            'normal',
            'high'
        );
    }

    /** Affiche le formulaire */
    public static function render($post)
    {
        wp_nonce_field(self::NONCE, self::NONCE);

        $g = function($k,$d=''){ $v=get_post_meta($post->ID,$k,true); return ($v===''? $d : $v); };

        // Valeurs enregistrées
        $source_type = $g('source_type','taxonomy');   // taxonomy|attribute|category|acf|meta
        $source_key  = $g('source_key','product_cat'); // slug de taxonomie/attribute, ou meta_key
        $display     = $g('display','checkbox');       // checkbox|radio|dropdown|list_v|list_h|buttons
        $select_mode = $g('select_mode','multi');      // single|multi
        $max_visible = (int)$g('max_visible',5);
        $layout_cols = (int)$g('layout_cols',1);
        $search_on   = (int)$g('search_on',0);
        $counts_on   = (int)$g('counts_on',1);
        $hierarchic  = (int)$g('hierarchic',0);
        $level       = $g('level','all');             // 1|2|3|all

        // Aide : liste des taxonomies produit et attributs Woo
        $tax_objects = get_object_taxonomies('product', 'objects');
        $tax_list = array();
        if (is_array($tax_objects)) {
            foreach ($tax_objects as $t) $tax_list[$t->name] = $t->labels->singular_name.' ('.$t->name.')';
        }

        $attr_list = array();
        if (function_exists('wc_get_attribute_taxonomies')) {
            $atts = wc_get_attribute_taxonomies();
            if ($atts) {
                foreach ($atts as $a) {
                    $slug = 'pa_'.sanitize_title($a->attribute_name);
                    $attr_list[$slug] = $a->attribute_label.' ('.$slug.')';
                }
            }
        }

        // Styles simples (évite page blanche)
        echo '<style>
            .sello-grid{display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:center}
            .sello-box{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:14px;margin-top:10px}
            .sello-muted{color:#6b7280;font-size:12px}
            .sello-row{margin:6px 0}
        </style>';

        echo '<div class="sello-box">';
        echo '<div class="sello-grid">';

        // Source type
        echo '<div><label for="sello_source_type"><strong>'.esc_html__('Source type','sello').'</strong></label><div class="sello-muted">'.esc_html__('Where values come from','sello').'</div></div>';
        echo '<div><select id="sello_source_type" name="sello[source_type]">';
        $types = array(
            'taxonomy'  => 'Taxonomy (e.g. product_cat, brand, …)',
            'attribute' => 'Woo Attribute (e.g. pa_color, pa_size, …)',
            'category'  => 'Product Category (product_cat)',
            'acf'       => 'ACF Field (text/select) — later',
            'meta'      => 'Post Meta Key — later',
        );
        foreach ($types as $k=>$label){
            echo '<option value="'.esc_attr($k).'" '.selected($source_type,$k,false).'>'.esc_html($label).'</option>';
        }
        echo '</select></div>';

        // Source key
        echo '<div><label for="sello_source_key"><strong>'.esc_html__('Source key','sello').'</strong></label><div class="sello-muted">'.esc_html__('Taxonomy/attribute slug or meta key','sello').'</div></div>';
        echo '<div>';
        echo '<input type="text" id="sello_source_key" name="sello[source_key]" value="'.esc_attr($source_key).'" class="regular-text" />';
        // Aides
        echo '<div class="sello-row sello-muted">'.esc_html__('Product taxonomies detected:','sello').'</div>';
        if ($tax_list){
            echo '<div class="sello-row"><select onchange="document.getElementById(\'sello_source_key\').value=this.value"><option value="">'.esc_html__('— pick taxonomy —','sello').'</option>';
            foreach ($tax_list as $slug=>$label){
                echo '<option value="'.esc_attr($slug).'">'.esc_html($label).'</option>';
            }
            echo '</select></div>';
        }
        if ($attr_list){
            echo '<div class="sello-row sello-muted">'.esc_html__('Woo attributes:','sello').'</div>';
            echo '<div class="sello-row"><select onchange="document.getElementById(\'sello_source_key\').value=this.value"><option value="">'.esc_html__('— pick attribute —','sello').'</option>';
            foreach ($attr_list as $slug=>$label){
                echo '<option value="'.esc_attr($slug).'">'.esc_html($label).'</option>';
            }
            echo '</select></div>';
        }
        echo '</div>';

        // Display
        echo '<div><label for="sello_display"><strong>'.esc_html__('Display type','sello').'</strong></label><div class="sello-muted">'.esc_html__('How the facet is rendered','sello').'</div></div>';
        echo '<div><select id="sello_display" name="sello[display]">';
        $displays = array(
            'checkbox' => 'Checkbox list',
            'radio'    => 'Radio list (single)',
            'dropdown' => 'Dropdown',
            'list_v'   => 'Vertical list',
            'list_h'   => 'Horizontal list',
            'buttons'  => 'Buttons/Pills',
        );
        foreach ($displays as $k=>$label){
            echo '<option value="'.esc_attr($k).'" '.selected($display,$k,false).'>'.esc_html($label).'</option>';
        }
        echo '</select></div>';

        // Select mode
        echo '<div><label for="sello_select_mode"><strong>'.esc_html__('Selection mode','sello').'</strong></label></div>';
        echo '<div><select id="sello_select_mode" name="sello[select_mode]">';
        echo '<option value="single" '.selected($select_mode,'single',false).'>'.esc_html__('Single','sello').'</option>';
        echo '<option value="multi"  '.selected($select_mode,'multi',false).'>'.esc_html__('Multiple','sello').'</option>';
        echo '</select></div>';

        // Max visible
        echo '<div><label for="sello_max_visible"><strong>'.esc_html__('Max visible items','sello').'</strong></label><div class="sello-muted">'.esc_html__('Show at most N options (e.g. 5)','sello').'</div></div>';
        echo '<div><input type="number" id="sello_max_visible" min="1" step="1" name="sello[max_visible]" value="'.esc_attr($max_visible).'" /></div>';

        // Layout columns
        echo '<div><label for="sello_layout_cols"><strong>'.esc_html__('Layout columns','sello').'</strong></label></div>';
        echo '<div><select id="sello_layout_cols" name="sello[layout_cols]">';
        echo '<option value="1" '.selected($layout_cols,1,false).'>1</option>';
        echo '<option value="2" '.selected($layout_cols,2,false).'>2</option>';
        echo '</select></div>';

        // Search toggle
        echo '<div><label for="sello_search_on"><strong>'.esc_html__('Search bar','sello').'</strong></label></div>';
        echo '<div><label><input type="checkbox" id="sello_search_on" name="sello[search_on]" value="1" '.checked($search_on,1,false).' /> '.esc_html__('Enable search inside facet','sello').'</label></div>';

        // Counts toggle
        echo '<div><label for="sello_counts_on"><strong>'.esc_html__('Show counts','sello').'</strong></label></div>';
        echo '<div><label><input type="checkbox" id="sello_counts_on" name="sello[counts_on]" value="1" '.checked($counts_on,1,false).' /> '.esc_html__('Display product counts per option','sello').'</label></div>';

        // Hierarchic + level (catégories)
        echo '<div><label for="sello_hierarchic"><strong>'.esc_html__('Hierarchic (categories)','sello').'</strong></label><div class="sello-muted">'.esc_html__('Apply to children levels','sello').'</div></div>';
        echo '<div>';
        echo '<label class="sello-row"><input type="checkbox" id="sello_hierarchic" name="sello[hierarchic]" value="1" '.checked($hierarchic,1,false).' /> '.esc_html__('Enable hierarchical behaviour','sello').'</label>';
        echo '<label>'.esc_html__('Depth','sello').' ';
        echo '<select name="sello[level]">';
        foreach (array('1','2','3','all') as $opt){
            echo '<option value="'.esc_attr($opt).'" '.selected($level,$opt,false).'>'.esc_html($opt).'</option>';
        }
        echo '</select></label>';
        echo '</div>';

        echo '</div>'; // grid
        echo '</div>'; // box
    }

    /** Sauvegarde */
    public static function save($post_id, $post)
    {
        if ($post->post_type !== 'sello_facet') return;
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce($_POST[self::NONCE], self::NONCE)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('manage_sello')) return;

        $in = isset($_POST['sello']) && is_array($_POST['sello']) ? $_POST['sello'] : array();

        $source_type = isset($in['source_type']) ? sanitize_text_field($in['source_type']) : 'taxonomy';
        $source_key  = isset($in['source_key'])  ? sanitize_text_field($in['source_key'])  : '';
        $display     = isset($in['display'])     ? sanitize_text_field($in['display'])     : 'checkbox';
        $select_mode = isset($in['select_mode']) ? sanitize_text_field($in['select_mode']) : 'multi';
        $max_visible = isset($in['max_visible']) ? absint($in['max_visible']) : 5;
        $layout_cols = isset($in['layout_cols']) ? absint($in['layout_cols']) : 1;
        $search_on   = !empty($in['search_on']) ? 1 : 0;
        $counts_on   = !empty($in['counts_on']) ? 1 : 0;
        $hierarchic  = !empty($in['hierarchic']) ? 1 : 0;
        $level       = isset($in['level']) ? sanitize_text_field($in['level']) : 'all';

        update_post_meta($post_id, 'source_type', $source_type);
        update_post_meta($post_id, 'source_key',  $source_key);
        update_post_meta($post_id, 'display',     $display);
        update_post_meta($post_id, 'select_mode', $select_mode);
        update_post_meta($post_id, 'max_visible', $max_visible);
        update_post_meta($post_id, 'layout_cols', $layout_cols);
        update_post_meta($post_id, 'search_on',   $search_on);
        update_post_meta($post_id, 'counts_on',   $counts_on);
        update_post_meta($post_id, 'hierarchic',  $hierarchic);
        update_post_meta($post_id, 'level',       $level);
    }
}
