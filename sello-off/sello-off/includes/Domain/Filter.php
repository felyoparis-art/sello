<?php
/**
 * Domain — Filter (CPT sello_filter)
 * Compat PHP 7.0+
 *
 * Rôle :
 *  - Définir la configuration complète d’un FILTRE SELLO
 *  - Metabox d’édition (Content / Scope / Design / Pinned / Slider)
 *  - Sauvegarde assainie via Services\Validator
 *
 * Métas principales (déjà consommées par Shortcodes + WooCommerce integration) :
 *  Content
 *    - content_mode : 'preset' | 'facets'                       (def: preset)
 *    - preset_id    : int
 *    - facets       : int[]  (IDs de sello_facet)
 *
 *  Scope & Attach
 *    - scope_type   : 'category' | 'taxonomy' | 'attribute'     (def: category)
 *    - scope_targets: string[] (ids ou slugs)
 *    - level        : '1'|'2'|'3'|'all'                         (def: all)
 *    - include      : string[] (ids/slug)
 *    - exclude      : string[] (ids/slug)
 *    - hierarchic   : 0|1
 *    - auto_attach  : 0|1   (afficher automatiquement sur les pages correspondantes)
 *    - takeover     : 0|1   (tenter de masquer les filtres thème natifs)
 *
 *  Design (sidebar container)
 *    - design_position     : 'sidebar_left' | 'sidebar_right'   (def: sidebar_right)
 *    - design_width_total  : int (px)                           (def: 700)
 *    - design_width_facets : int (px)                           (def: 500)
 *    - design_width_pinned : int (px)                           (def: 200)
 *    - chips_on            : 0|1  (afficher Selected Filters chips)
 *
 *  Pinned (You-may-also-like zone)
 *    - pinned_on    : 0|1
 *    - pinned_mode  : 'dynamic' | 'manual'
 *    - pinned_items : array<int, {img,title,type,value}>
 *
 *  Slider / Hero (navigation)
 *    - slider_on     : 0|1
 *    - slider_mode   : 'dynamic' | 'manual'
 *    - slider_click  : 'apply_filter' | 'navigate_only'
 *    - slider_drilldown : 0|1 (quand on clique, mettre à jour le contexte des facettes)
 *    - slider_binding   : {type:'category|taxonomy|attribute', targets:string[], level:'1|2|3|all'}
 */

namespace Sello\Domain;

use Sello\Services\Validator;

defined('ABSPATH') || exit;

class Filter
{
    /** Enregistrer hooks */
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
            'sello_filter_box',
            __('SELLO — Filter settings', 'sello'),
            array(self::class, 'render_box'),
            'sello_filter',
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

        wp_nonce_field('sello_filter_save', 'sello_filter_nonce');

        // ====== Lire les valeurs actuelles ======
        // Content
        $content_mode = get_post_meta($post->ID, 'content_mode', true) ?: 'preset';
        $preset_id    = (int) get_post_meta($post->ID, 'preset_id', true);
        $facets       = get_post_meta($post->ID, 'facets', true); if (!is_array($facets)) $facets = array();
        $facets       = array_values(array_unique(array_map('intval', $facets)));

        // Scope & attach
        $scope_type    = get_post_meta($post->ID, 'scope_type',   true) ?: 'category';
        $scope_targets = get_post_meta($post->ID, 'scope_targets',true); if (!is_array($scope_targets)) $scope_targets = array();
        $level         = get_post_meta($post->ID, 'level',        true) ?: 'all';
        $include       = get_post_meta($post->ID, 'include',      true); if (!is_array($include)) $include = array();
        $exclude       = get_post_meta($post->ID, 'exclude',      true); if (!is_array($exclude)) $exclude = array();
        $hierarchic    = (int) (get_post_meta($post->ID, 'hierarchic', true) ?: 0);
        $auto_attach   = (int) (get_post_meta($post->ID, 'auto_attach',true) ?: 1);
        $takeover      = (int) (get_post_meta($post->ID, 'takeover',   true) ?: 0);

        // Design
        $design_position     = get_post_meta($post->ID,'design_position', true) ?: 'sidebar_right';
        $design_width_total  = (int) (get_post_meta($post->ID,'design_width_total',  true) ?: 700);
        $design_width_facets = (int) (get_post_meta($post->ID,'design_width_facets', true) ?: 500);
        $design_width_pinned = (int) (get_post_meta($post->ID,'design_width_pinned', true) ?: 200);
        $chips_on            = (int) (get_post_meta($post->ID,'chips_on', true) ?: 1);

        // Pinned
        $pinned_on   = (int) (get_post_meta($post->ID, 'pinned_on', true) ?: 0);
        $pinned_mode = get_post_meta($post->ID, 'pinned_mode', true) ?: 'dynamic';
        $pinned_items= get_post_meta($post->ID, 'pinned_items', true); if (!is_array($pinned_items)) $pinned_items = array();

        // Slider
        $slider_on     = (int) (get_post_meta($post->ID, 'slider_on', true) ?: 0);
        $slider_mode   = get_post_meta($post->ID, 'slider_mode', true) ?: 'dynamic';
        $slider_click  = get_post_meta($post->ID, 'slider_click', true) ?: 'apply_filter';
        $slider_drill  = (int) (get_post_meta($post->ID, 'slider_drilldown', true) ?: 1);
        $slider_bind   = get_post_meta($post->ID, 'slider_binding', true); if (!is_array($slider_bind)) $slider_bind = array('type'=>'category','targets'=>array(),'level'=>'1');

        // ====== Données pour sélecteurs ======
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
        $preset_posts = get_posts(array(
            'post_type'              => 'sello_preset',
            'post_status'            => array('publish','draft','pending'),
            'numberposts'            => 100,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        // ====== Styles ======
        echo '<style>
            .sello-box{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:14px;margin-top:12px}
            .sello-grid{display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:start}
            .sello-pill{display:flex;align-items:center;gap:8px;border:1px solid #e5e7eb;background:#fff;border-radius:999px;padding:6px 10px}
            .sello-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;max-height:320px;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fafafa}
            .sello-inline{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
            .sello-two{display:flex;gap:12px}
            .sello-two > div{flex:1}
            .sello-muted{color:#6b7280;font-size:12px}
            .sello-note{font-size:12px;color:#6b7280;margin:6px 0 0}
            textarea.sello-rows{width:100%;min-height:110px;font-family:monospace}
        </style>';

        /* ================= CONTENT ================= */
        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Content (Facets source)','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Mode','sello').'</strong><div class="sello-muted">'.esc_html__('Use a Preset or pick Facets individually','sello').'</div></div>';
        echo '<div class="sello-inline">';
        echo '<label><input type="radio" name="sello[content_mode]" value="preset" '.checked($content_mode,'preset',false).'> '.esc_html__('Preset','sello').'</label>';
        echo '<label><input type="radio" name="sello[content_mode]" value="facets" '.checked($content_mode,'facets',false).'> '.esc_html__('Facets (manual)','sello').'</label>';
        echo '</div>';

        // Preset select
        echo '<div><strong>'.esc_html__('Preset','sello').'</strong></div><div>';
        if (empty($preset_posts)) {
            echo '<p class="sello-muted">'.esc_html__('No presets yet. Create a Preset or switch to manual facets.','sello').'</p>';
        } else {
            echo '<select name="sello[preset_id]">';
            echo '<option value="0">'.esc_html__('- Select preset -','sello').'</option>';
            foreach ($preset_posts as $pid) {
                $title = get_the_title($pid); if ($title==='') $title = '('.__('no title','sello').')';
                echo '<option value="'.(int)$pid.'" '.selected($preset_id,$pid,false).'>'.esc_html($title).' (#'.(int)$pid.')</option>';
            }
            echo '</select>';
        }
        echo '</div>';

        // Facets multiple (checkboxes)
        echo '<div><strong>'.esc_html__('Facets (manual)','sello').'</strong><div class="sello-muted">'.esc_html__('Checked facets will be rendered (order by title, or via Preset ordering).','sello').'</div></div>';
        echo '<div>';
        if (empty($facet_posts)) {
            echo '<p class="sello-muted">'.esc_html__('No facets yet. Create some Facets first.','sello').'</p>';
        } else {
            echo '<div class="sello-list">';
            foreach ($facet_posts as $fid) {
                $title = get_the_title($fid); if ($title==='') $title = '('.__('no title','sello').')';
                $checked = in_array((int)$fid, $facets, true) ? ' checked' : '';
                echo '<label class="sello-pill"><input type="checkbox" name="sello[facets][]" value="'.(int)$fid.'"'.$checked.'> '.esc_html($title).' (#'.(int)$fid.')</label>';
            }
            echo '</div>';
        }
        echo '</div>';

        echo '</div></div>';

        /* ================= SCOPE ================= */
        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Scope & Attach','sello').'</h2>';
        echo '<div class="sello-grid">';

        echo '<div><strong>'.esc_html__('Scope type','sello').'</strong><div class="sello-muted">'.esc_html__('Where this filter should appear automatically','sello').'</div></div>';
        echo '<div>'.self::select('sello[scope_type]', $scope_type, array(
            'category'  => 'Product Category',
            'taxonomy'  => 'Taxonomy (any)',
            'attribute' => 'Attribute (pa_*)',
        )).'</div>';

        echo '<div><strong>'.esc_html__('Targets (IDs or slugs, comma-separated)','sello').'</strong><div class="sello-muted">'.esc_html__('Leave empty = apply to all in this scope.','sello').'</div></div>';
        echo '<div><input type="text" name="sello[scope_targets]" value="'.esc_attr(implode(', ', $scope_targets)).'" class="regular-text" /></div>';

        echo '<div><strong>'.esc_html__('Hierarchic','sello').'</strong></div>';
        echo '<div class="sello-inline"><label><input type="checkbox" name="sello[hierarchic]" value="1" '.checked($hierarchic,1,false).'> '.esc_html__('Apply to children of targets','sello').'</label></div>';

        echo '<div><strong>'.esc_html__('Levels','sello').'</strong></div>';
        echo '<div>'.self::select('sello[level]', $level, array('1'=>'1','2'=>'2','3'=>'3','all'=>'All')).'</div>';

        echo '<div><strong>'.esc_html__('Include (IDs/slugs CSV)','sello').'</strong></div>';
        echo '<div><input type="text" name="sello[include]" value="'.esc_attr(implode(', ', $include)).'" class="regular-text" /></div>';

        echo '<div><strong>'.esc_html__('Exclude (IDs/slugs CSV)','sello').'</strong></div>';
        echo '<div><input type="text" name="sello[exclude]" value="'.esc_attr(implode(', ', $exclude)).'" class="regular-text" /></div>';

        echo '<div><strong>'.esc_html__('Auto attach','sello').'</strong><div class="sello-muted">'.esc_html__('Automatically render on matching archives (no shortcode needed)','sello').'</div></div>';
        echo '<div><label><input type="checkbox" name="sello[auto_attach]" value="1" '.checked($auto_attach,1,false).'> '.esc_html__('Enable auto-attach','sello').'</label></div>';

        echo '<div><strong>'.esc_html__('Takeover','sello').'</strong><div class="sello-muted">'.esc_html__('Try to hide theme’s native filters/widgets for a cleaner result','sello').'</div></div>';
        echo '<div><label><input type="checkbox" name="sello[takeover]" value="1" '.checked($takeover,1,false).'> '.esc_html__('Enable takeover CSS','sello').'</label></div>';

        echo '</div></div>';

        /* ================= DESIGN ================= */
        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Design (Sidebar container)','sello').'</h2>';
        echo '<div class="sello-grid">';

        echo '<div><strong>'.esc_html__('Position','sello').'</strong></div>';
        echo '<div>'.self::select('sello[design_position]', $design_position, array(
            'sidebar_left'  => 'Sidebar Left',
            'sidebar_right' => 'Sidebar Right',
        )).'</div>';

        echo '<div><strong>'.esc_html__('Widths (px)','sello').'</strong></div>';
        echo '<div class="sello-two">
                <div><label>'.esc_html__('Total','sello').'<br><input type="number" min="300" step="10" name="sello[design_width_total]"  value="'.esc_attr($design_width_total).'"></label></div>
                <div><label>'.esc_html__('Facets','sello').'<br><input type="number" min="200" step="10" name="sello[design_width_facets]" value="'.esc_attr($design_width_facets).'"></label></div>
                <div><label>'.esc_html__('Pinned','sello').'<br><input type="number" min="0"   step="10" name="sello[design_width_pinned]" value="'.esc_attr($design_width_pinned).'"></label></div>
              </div>';

        echo '<div><strong>'.esc_html__('Selected filters chips','sello').'</strong></div>';
        echo '<div><label><input type="checkbox" name="sello[chips_on]" value="1" '.checked($chips_on,1,false).'> '.esc_html__('Show chips row above facets','sello').'</label></div>';

        echo '</div></div>';

        /* ================= PINNED ================= */
        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Pinned elements (optional)','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Enable','sello').'</strong></div>';
        echo '<div><label><input type="checkbox" name="sello[pinned_on]" value="1" '.checked($pinned_on,1,false).'> '.esc_html__('Show a pinned area next to facets','sello').'</label></div>';

        echo '<div><strong>'.esc_html__('Mode','sello').'</strong></div>';
        echo '<div>'.self::select('sello[pinned_mode]', $pinned_mode, array('dynamic'=>'Dynamic','manual'=>'Manual')).'</div>';

        echo '<div><strong>'.esc_html__('Manual items (one per line)','sello').'</strong><div class="sello-muted">'.esc_html__('Format: image_url | title | type(category|taxonomy|attribute|url) | value (id/slug/url)','sello').'</div></div>';
        // Préparer aperçu du tableau actuel
        $lines = '';
        if ($pinned_items) {
            foreach ($pinned_items as $it) {
                $img = isset($it['img']) ? $it['img'] : '';
                $tt  = isset($it['title']) ? $it['title'] : '';
                $ty  = isset($it['type']) ? $it['type'] : 'url';
                $va  = isset($it['value']) ? $it['value'] : '';
                $lines .= $img.' | '.$tt.' | '.$ty.' | '.$va."\n";
            }
        }
        echo '<div><textarea name="sello[pinned_lines]" class="sello-rows" placeholder="https://.../dior.jpg | Dior | taxonomy | pa_marque:dior">'.esc_textarea(trim($lines)).'</textarea></div>';
        echo '</div></div>';

        /* ================= SLIDER ================= */
        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Hero / Slider','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Enable','sello').'</strong></div>';
        echo '<div><label><input type="checkbox" name="sello[slider_on]" value="1" '.checked($slider_on,1,false).'> '.esc_html__('Show hero/navigation slider above filters','sello').'</label></div>';

        echo '<div><strong>'.esc_html__('Mode','sello').'</strong></div>';
        echo '<div>'.self::select('sello[slider_mode]', $slider_mode, array('dynamic'=>'Dynamic','manual'=>'Manual')).'</div>';

        echo '<div><strong>'.esc_html__('On click','sello').'</strong></div>';
        echo '<div>'.self::select('sello[slider_click]', $slider_click, array('apply_filter'=>'Apply filter','navigate_only'=>'Navigate only')).'</div>';

        echo '<div><strong>'.esc_html__('Drilldown (affect facets on click)','sello').'</strong></div>';
        echo '<div><label><input type="checkbox" name="sello[slider_drilldown]" value="1" '.checked($slider_drill,1,false).'> '.esc_html__('Enable drilldown behavior','sello').'</label></div>';

        // Binding
        $bind_type    = isset($slider_bind['type']) ? (string)$slider_bind['type'] : 'category';
        $bind_targets = isset($slider_bind['targets']) && is_array($slider_bind['targets']) ? $slider_bind['targets'] : array();
        $bind_level   = isset($slider_bind['level']) ? (string)$slider_bind['level'] : '1';

        echo '<div><strong>'.esc_html__('Binding type','sello').'</strong><div class="sello-muted">'.esc_html__('What the slider items represent','sello').'</div></div>';
        echo '<div>'.self::select('sello[slider_binding_type]', $bind_type, array(
            'category'  => 'Product Category',
            'taxonomy'  => 'Taxonomy',
            'attribute' => 'Attribute',
        )).'</div>';

        echo '<div><strong>'.esc_html__('Binding targets (CSV)','sello').'</strong><div class="sello-muted">'.esc_html__('IDs or slugs (for taxonomy/attribute: pass taxonomy slugs like pa_marque)','sello').'</div></div>';
        echo '<div><input type="text" name="sello[slider_binding_targets]" value="'.esc_attr(implode(', ', $bind_targets)).'" class="regular-text" /></div>';

        echo '<div><strong>'.esc_html__('Binding level','sello').'</strong></div>';
        echo '<div>'.self::select('sello[slider_binding_level]', $bind_level, array('1'=>'1','2'=>'2','3'=>'3','all'=>'All')).'</div>';

        echo '</div></div>';

        // Note
        echo '<p class="sello-note">'.esc_html__('Tip: After saving, you can use the Elementor widgets or rely on Auto Attach. Shortcodes are also available via the REST payload.', 'sello').'</p>';
    }

    /* ============================================================
     * Sauvegarde
     * ========================================================== */

    public static function save($post_id, $post)
    {
        if ($post->post_type !== 'sello_filter') return;

        if (!isset($_POST['sello_filter_nonce']) || !wp_verify_nonce($_POST['sello_filter_nonce'], 'sello_filter_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('manage_sello')) return;

        $in = isset($_POST['sello']) && is_array($_POST['sello']) ? $_POST['sello'] : array();

        // Content
        $content_mode = isset($in['content_mode']) ? $in['content_mode'] : 'preset';
        $content_mode = ($content_mode === 'facets') ? 'facets' : 'preset';
        $preset_id    = isset($in['preset_id']) ? (int)$in['preset_id'] : 0;

        $facets = array();
        if (!empty($in['facets']) && is_array($in['facets'])) {
            foreach ($in['facets'] as $fid) {
                if (is_numeric($fid)) $facets[] = (int)$fid;
            }
            $facets = array_values(array_unique(array_filter($facets)));
        }

        update_post_meta($post_id, 'content_mode', $content_mode);
        update_post_meta($post_id, 'preset_id',    $preset_id);
        update_post_meta($post_id, 'facets',       $facets);

        // Scope & attach
        $scope_type    = Validator::scopeType(isset($in['scope_type']) ? $in['scope_type'] : 'category');
        $scope_targets = Validator::csvToArray(isset($in['scope_targets']) ? $in['scope_targets'] : '');
        $level         = Validator::level(isset($in['level']) ? $in['level'] : 'all');
        $include       = Validator::csvToArray(isset($in['include']) ? $in['include'] : '');
        $exclude       = Validator::csvToArray(isset($in['exclude']) ? $in['exclude'] : '');
        $hierarchic    = Validator::bool(isset($in['hierarchic']) ? $in['hierarchic'] : 0);
        $auto_attach   = Validator::bool(isset($in['auto_attach']) ? $in['auto_attach'] : 1);
        $takeover      = Validator::bool(isset($in['takeover']) ? $in['takeover'] : 0);

        update_post_meta($post_id, 'scope_type',     $scope_type);
        update_post_meta($post_id, 'scope_targets',  $scope_targets);
        update_post_meta($post_id, 'level',          $level);
        update_post_meta($post_id, 'include',        $include);
        update_post_meta($post_id, 'exclude',        $exclude);
        update_post_meta($post_id, 'hierarchic',     $hierarchic);
        update_post_meta($post_id, 'auto_attach',    $auto_attach);
        update_post_meta($post_id, 'takeover',       $takeover);

        // Design
        $design_position     = Validator::sidebarPosition(isset($in['design_position']) ? $in['design_position'] : 'sidebar_right');
        $design_width_total  = Validator::int(isset($in['design_width_total'])  ? $in['design_width_total']  : 700, 300, 2000, 700);
        $design_width_facets = Validator::int(isset($in['design_width_facets']) ? $in['design_width_facets'] : 500, 200, 2000, 500);
        $design_width_pinned = Validator::int(isset($in['design_width_pinned']) ? $in['design_width_pinned'] : 200, 0,   2000, 200);
        $chips_on            = Validator::bool(isset($in['chips_on']) ? $in['chips_on'] : 1);

        update_post_meta($post_id, 'design_position',     $design_position);
        update_post_meta($post_id, 'design_width_total',  $design_width_total);
        update_post_meta($post_id, 'design_width_facets', $design_width_facets);
        update_post_meta($post_id, 'design_width_pinned', $design_width_pinned);
        update_post_meta($post_id, 'chips_on',            $chips_on);

        // Pinned
        $pinned_on   = Validator::bool(isset($in['pinned_on']) ? $in['pinned_on'] : 0);
        $pinned_mode = Validator::pinnedMode(isset($in['pinned_mode']) ? $in['pinned_mode'] : 'dynamic');

        $pinned_items = array();
        if (!empty($in['pinned_lines'])) {
            $pinned_items = \Sello\Services\Validator::parsePinnedLines($in['pinned_lines']);
        }

        update_post_meta($post_id, 'pinned_on',   $pinned_on);
        update_post_meta($post_id, 'pinned_mode', $pinned_mode);
        update_post_meta($post_id, 'pinned_items',$pinned_items);

        // Slider
        $slider_on    = Validator::bool(isset($in['slider_on']) ? $in['slider_on'] : 0);
        $slider_mode  = Validator::sliderMode(isset($in['slider_mode']) ? $in['slider_mode'] : 'dynamic');
        $slider_click = Validator::sliderClick(isset($in['slider_click']) ? $in['slider_click'] : 'apply_filter');
        $slider_drill = Validator::bool(isset($in['slider_drilldown']) ? $in['slider_drilldown'] : 1);

        $bind_type    = Validator::scopeType(isset($in['slider_binding_type']) ? $in['slider_binding_type'] : 'category');
        $bind_targets = Validator::csvToArray(isset($in['slider_binding_targets']) ? $in['slider_binding_targets'] : '');
        $bind_level   = Validator::level(isset($in['slider_binding_level']) ? $in['slider_binding_level'] : '1');

        $binding = array(
            'type'    => $bind_type,
            'targets' => $bind_targets,
            'level'   => $bind_level,
        );

        update_post_meta($post_id, 'slider_on',        $slider_on);
        update_post_meta($post_id, 'slider_mode',      $slider_mode);
        update_post_meta($post_id, 'slider_click',     $slider_click);
        update_post_meta($post_id, 'slider_drilldown', $slider_drill);
        update_post_meta($post_id, 'slider_binding',   $binding);
    }

    /* ============================================================
     * Helpers
     * ========================================================== */

    /** Helper simple select */
    private static function select($name, $value, $choices)
    {
        $out = '<select name="'.esc_attr($name).'">';
        foreach ($choices as $val=>$lab) {
            $out .= '<option value="'.esc_attr($val).'" '.selected($value,$val,false).'>'.esc_html($lab).'</option>';
        }
        $out .= '</select>';
        return $out;
    }
}
