<?php
/**
 * Metabox — Filter (content: preset/facets + scope + design + pinned + slider)
 * Compat PHP 7.0+
 */
namespace Sello\Admin\Metabox;

defined('ABSPATH') || exit;

class FilterMetabox
{
    const NONCE = 'sello_filter_mb_nonce';

    /** Enregistre la metabox pour le CPT 'sello_filter' */
    public static function register()
    {
        add_meta_box(
            'sello_filter_main',
            __('Filter settings','sello'),
            array(self::class, 'render'),
            'sello_filter',
            'normal',
            'high'
        );
        add_meta_box(
            'sello_filter_shortcodes',
            __('Shortcodes','sello'),
            array(self::class, 'render_shortcodes'),
            'sello_filter',
            'side',
            'default'
        );
    }

    /** UI principale */
    public static function render($post)
    {
        wp_nonce_field(self::NONCE, self::NONCE);

        $g = function($k,$d=''){ $v=get_post_meta($post->ID,$k,true); return ($v===''? $d : $v); };

        // ---- CONTENT (Preset / Facets) ----
        $content_mode = $g('content_mode','preset'); // preset|facets
        $preset_id    = (int)$g('preset_id',0);
        $facets       = $g('facets',array());
        if (!is_array($facets)) $facets = array();

        // ---- SCOPE (où afficher) ----
        $scope_type   = $g('scope_type','category'); // category|taxonomy|attribute
        $scope_targets= $g('scope_targets',array()); // array of ids/slugs
        if (!is_array($scope_targets)) $scope_targets = array();
        $hierarchic   = (int)$g('hierarchic',1);
        $level        = $g('level','all');           // 1|2|3|all
        $include      = $g('include',array());       // array
        $exclude      = $g('exclude',array());       // array
        $auto_attach  = (int)$g('auto_attach',1);
        $takeover     = (int)$g('takeover',0);

        // ---- DESIGN (sidebar + chips + widths) ----
        $chips_on     = (int)$g('chips_on',1);
        $position     = $g('design_position','sidebar_right'); // sidebar_left|sidebar_right
        $w_total      = (int)$g('design_width_total',700);
        $w_facets     = (int)$g('design_width_facets',500);
        $w_pinned     = (int)$g('design_width_pinned',200);

        // ---- PINNED ----
        $pinned_on    = (int)$g('pinned_on',0);
        $pinned_mode  = $g('pinned_mode','dynamic'); // dynamic|manual
        $pinned_items = $g('pinned_items',array());  // stored array (manual)
        if (!is_array($pinned_items)) $pinned_items = array();

        // ---- SLIDER / HERO ----
        $slider_on     = (int)$g('slider_on',0);
        $slider_mode   = $g('slider_mode','dynamic'); // dynamic|manual
        $slider_click  = $g('slider_click','apply_filter'); // apply_filter|navigate_only
        $slider_drill  = (int)$g('slider_drilldown',0);
        $slider_bind   = $g('slider_binding',array(
            'type'    => 'category', // category|taxonomy|attribute
            'targets' => array(),    // ids/slugs
            'level'   => '1',
            'include' => array(),
            'exclude' => array(),
        ));
        if (!is_array($slider_bind)) $slider_bind = array();

        // Helper lists
        $presets = get_posts(array(
            'post_type'=>'sello_preset','numberposts'=>-1,'post_status'=>'publish',
            'orderby'=>'title','order'=>'ASC','fields'=>'ids',
            'no_found_rows'=>true,'update_post_meta_cache'=>false,'update_post_term_cache'=>false
        ));
        $presets_map = array();
        foreach ($presets as $pid) $presets_map[$pid] = get_the_title($pid);

        $facets_all = get_posts(array(
            'post_type'=>'sello_facet','numberposts'=>-1,'post_status'=>'publish',
            'orderby'=>'title','order'=>'ASC','fields'=>'ids',
            'no_found_rows'=>true,'update_post_meta_cache'=>false,'update_post_term_cache'=>false
        ));
        $facets_map = array();
        foreach ($facets_all as $fid) $facets_map[$fid] = get_the_title($fid);

        $tax_objects = get_object_taxonomies('product','objects');
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

        // Styles
        echo '<style>
            .sello-box{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:14px;margin-top:10px}
            .sello-grid{display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:start}
            .sello-muted{color:#6b7280;font-size:12px}
            .sello-row{margin:6px 0}
            .sello-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
            .sello-item{display:flex;align-items:center;gap:10px;border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fafafa}
            .sello-inline{display:flex;gap:8px;align-items:center}
            .sello-help{font-size:12px;color:#6b7280;margin-top:6px}
            .sello-tag{display:inline-block;padding:2px 6px;border:1px solid #d1d5db;border-radius:999px;background:#fff;margin-right:6px;margin-bottom:4px}
            .sello-code{font-family:monospace;background:#f3f4f6;padding:2px 6px;border-radius:4px}
            textarea.small{width:100%;min-height:80px}
        </style>';

        /* ============================================================
         * CONTENT
         * ============================================================ */
        echo '<div class="sello-box"><h2 style="margin-top:0">'.esc_html__('Content','sello').'</h2>';
        echo '<div class="sello-grid">';

        echo '<div><label><strong>'.esc_html__('Mode','sello').'</strong></label><div class="sello-muted">'.esc_html__('Use a Preset (recommended) or pick Facets individually','sello').'</div></div>';
        echo '<div class="sello-inline">';
        echo '<label><input type="radio" name="sello[content_mode]" value="preset" '.checked($content_mode,'preset',false).'> '.esc_html__('Preset','sello').'</label>';
        echo '<label><input type="radio" name="sello[content_mode]" value="facets" '.checked($content_mode,'facets',false).'> '.esc_html__('Individual facets','sello').'</label>';
        echo '</div>';

        // Preset select
        echo '<div><label><strong>'.esc_html__('Preset','sello').'</strong></label></div>';
        echo '<div>';
        echo '<select name="sello[preset_id]" '.($content_mode==='preset'?'':'disabled="disabled"').'>';
        echo '<option value="0">'.esc_html__('— Select a preset —','sello').'</option>';
        if ($presets_map) {
            foreach ($presets_map as $pid=>$label) {
                echo '<option value="'.esc_attr($pid).'" '.selected($preset_id,$pid,false).'>'.esc_html($label).' (#'.(int)$pid.')</option>';
            }
        }
        echo '</select>';
        echo '<div class="sello-help">'.esc_html__('Create/Manage Presets in SELLO → Presets.','sello').'</div>';
        echo '</div>';

        // Facets picker
        echo '<div><label><strong>'.esc_html__('Facets','sello').'</strong></label><div class="sello-muted">'.esc_html__('Only used if “Individual facets” mode is selected','sello').'</div></div>';
        echo '<div>';
        echo '<div class="sello-row">';
        echo '<select id="sello_add_facet_filter" '.($content_mode==='facets'?'':'disabled="disabled"').'>';
        echo '<option value="">'.esc_html__('— Select a facet —','sello').'</option>';
        if ($facets_map) {
            asort($facets_map);
            foreach ($facets_map as $fid=>$title) {
                echo '<option value="'.esc_attr($fid).'">'.esc_html($title).' (#'.(int)$fid.')</option>';
            }
        }
        echo '</select> ';
        echo '<button type="button" class="button" id="sello_add_facet_btn" '.($content_mode==='facets'?'':'disabled="disabled"').'>'.esc_html__('Add','sello').'</button>';
        echo '</div>';
        echo '<ul class="sello-list" id="sello_filter_facets">';
        if ($facets) {
            foreach ($facets as $fid) {
                $title = isset($facets_map[$fid]) ? $facets_map[$fid] : ('#'.$fid);
                echo '<li class="sello-item" data-id="'.esc_attr($fid).'">';
                echo '<span class="title">'.esc_html($title).'</span>';
                echo '<a href="#" class="button remove">'.esc_html__('Remove','sello').'</a>';
                echo '<input type="hidden" name="sello[facets][]" value="'.esc_attr($fid).'" />';
                echo '</li>';
            }
        }
        echo '</ul>';
        echo '</div>';

        echo '</div>'; // grid
        echo '</div>'; // box

        /* ============================================================
         * SCOPE
         * ============================================================ */
        echo '<div class="sello-box"><h2 style="margin-top:0">'.esc_html__('Scope (where to display)','sello').'</h2>';
        echo '<div class="sello-grid">';

        echo '<div><label><strong>'.esc_html__('Scope type','sello').'</strong></label><div class="sello-muted">'.esc_html__('Choose the context where this filter auto-attaches','sello').'</div></div>';
        echo '<div class="sello-inline">';
        echo '<select name="sello[scope_type]">';
        $scope_types = array('category'=>'Product Category','taxonomy'=>'Taxonomy','attribute'=>'Attribute');
        foreach ($scope_types as $k=>$lab) echo '<option value="'.esc_attr($k).'" '.selected($scope_type,$k,false).'>'.esc_html($lab).'</option>';
        echo '</select>';
        echo '</div>';

        echo '<div><label><strong>'.esc_html__('Targets','sello').'</strong></label><div class="sello-muted">'.esc_html__('IDs or slugs, comma-separated. Empty = any.','sello').'</div></div>';
        echo '<div>';
        $targets_csv = implode(',', array_map('strval',$scope_targets));
        echo '<input type="text" name="sello[scope_targets]" value="'.esc_attr($targets_csv).'" class="regular-text" placeholder="e.g. parfums, 123, eau-de-parfum" />';
        // helpers
        echo '<div class="sello-help">'.esc_html__('Taxonomies detected for products:','sello').' ';
        if ($tax_objects) {
            foreach ($tax_objects as $tax) {
                echo '<span class="sello-tag">'.esc_html($tax->labels->singular_name).' <span class="sello-muted">('.esc_html($tax->name).')</span></span>';
            }
        }
        if ($attr_list) {
            echo '<div class="sello-help">'.esc_html__('Attributes:','sello').' ';
            foreach ($attr_list as $slug=>$label) {
                echo '<span class="sello-tag">'.esc_html($label).'</span> ';
            }
            echo '</div>';
        }
        echo '</div>';

        echo '<div><label><strong>'.esc_html__('Hierarchic / Depth','sello').'</strong></label><div class="sello-muted">'.esc_html__('Apply also to child categories/terms and control depth','sello').'</div></div>';
        echo '<div class="sello-inline">';
        echo '<label><input type="checkbox" name="sello[hierarchic]" value="1" '.checked($hierarchic,1,false).'> '.esc_html__('Hierarchic','sello').'</label>';
        echo '<label>'.esc_html__('Depth','sello').' ';
        echo '<select name="sello[level]">';
        foreach (array('1','2','3','all') as $opt) echo '<option value="'.esc_attr($opt).'" '.selected($level,$opt,false).'>'.esc_html($opt).'</option>';
        echo '</select></label>';
        echo '</div>';

        echo '<div><label><strong>'.esc_html__('Include / Exclude children','sello').'</strong></label><div class="sello-muted">'.esc_html__('Comma-separated IDs/slugs for fine tuning','sello').'</div></div>';
        echo '<div class="sello-inline" style="flex-direction:column;align-items:stretch">';
        echo '<input type="text" name="sello[include]" class="regular-text" value="'.esc_attr(implode(',',(array)$include)).'" placeholder="IDs/slugs to force-include" />';
        echo '<input type="text" name="sello[exclude]" class="regular-text" value="'.esc_attr(implode(',',(array)$exclude)).'" placeholder="IDs/slugs to exclude" />';
        echo '<label class="sello-row"><input type="checkbox" name="sello[auto_attach]" value="1" '.checked($auto_attach,1,false).'> '.esc_html__('Auto-attach on matching archives','sello').'</label>';
        echo '<label class="sello-row"><input type="checkbox" name="sello[takeover]" value="1" '.checked($takeover,1,false).'> '.esc_html__('Takeover (try to hide theme filters/widgets)','sello').'</label>';
        echo '</div>';

        echo '</div>'; // grid
        echo '</div>'; // box

        /* ============================================================
         * DESIGN
         * ============================================================ */
        echo '<div class="sello-box"><h2 style="margin-top:0">'.esc_html__('Design (sidebar & chips)','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><label><strong>'.esc_html__('Position','sello').'</strong></label><div class="sello-muted">'.esc_html__('Sidebar left or right','sello').'</div></div>';
        echo '<div><select name="sello[design_position]">';
        echo '<option value="sidebar_left"  '.selected($position,'sidebar_left',false).'>'.esc_html__('Sidebar Left','sello').'</option>';
        echo '<option value="sidebar_right" '.selected($position,'sidebar_right',false).'>'.esc_html__('Sidebar Right','sello').'</option>';
        echo '</select></div>';

        echo '<div><label><strong>'.esc_html__('Chips (selected filters)','sello').'</strong></label></div>';
        echo '<div><label><input type="checkbox" name="sello[chips_on]" value="1" '.checked($chips_on,1,false).'> '.esc_html__('Show selected filters as chips','sello').'</label></div>';

        echo '<div><label><strong>'.esc_html__('Widths (px)','sello').'</strong></label><div class="sello-muted">'.esc_html__('Total / Facets / Pinned','sello').'</div></div>';
        echo '<div class="sello-inline">';
        echo '<input type="number" min="300" step="10" name="sello[design_width_total]"  value="'.esc_attr($w_total).'"  style="width:110px"> ';
        echo '<input type="number" min="200" step="10" name="sello[design_width_facets]" value="'.esc_attr($w_facets).'" style="width:110px"> ';
        echo '<input type="number" min="0"   step="10" name="sello[design_width_pinned]" value="'.esc_attr($w_pinned).'" style="width:110px"> ';
        echo '</div>';

        echo '</div>'; // grid
        echo '</div>'; // box

        /* ============================================================
         * PINNED
         * ============================================================ */
        echo '<div class="sello-box"><h2 style="margin-top:0">'.esc_html__('Pinned elements','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><label><strong>'.esc_html__('Enable','sello').'</strong></label></div>';
        echo '<div class="sello-inline">';
        echo '<label><input type="checkbox" name="sello[pinned_on]" value="1" '.checked($pinned_on,1,false).'> '.esc_html__('Show pinned sidebar','sello').'</label>';
        echo '<select name="sello[pinned_mode]">';
        echo '<option value="dynamic" '.selected($pinned_mode,'dynamic',false).'>'.esc_html__('Dynamic','sello').'</option>';
        echo '<option value="manual"  '.selected($pinned_mode,'manual',false).'>'.esc_html__('Manual','sello').'</option>';
        echo '</select>';
        echo '</div>';

        echo '<div><label><strong>'.esc_html__('Manual items','sello').'</strong></label><div class="sello-muted">'.esc_html__('One item per line: image_url | title | link_type(category/taxonomy/attribute/url) | value_or_url','sello').'</div></div>';
        echo '<div>';
        $manual_lines = array();
        if ($pinned_items && is_array($pinned_items)) {
            foreach ($pinned_items as $it) {
                $manual_lines[] = implode(' | ', array(
                    isset($it['img'])?$it['img']:'',
                    isset($it['title'])?$it['title']:'',
                    isset($it['type'])?$it['type']:'',
                    isset($it['value'])?$it['value']:''
                ));
            }
        }
        echo '<textarea name="sello[pinned_items_raw]" class="small" placeholder="https://...jpg | Dior | taxonomy | pa_marque:dior&#10;/uploads/dior.png | Nouveautés | url | /nouveautes">'.esc_textarea(implode("\n",$manual_lines)).'</textarea>';
        echo '</div>';

        echo '</div>'; // grid
        echo '</div>'; // box

        /* ============================================================
         * SLIDER / HERO
         * ============================================================ */
        echo '<div class="sello-box"><h2 style="margin-top:0">'.esc_html__('Hero / Slider','sello').'</h2>';
        echo '<div class="sello-grid">';

        echo '<div><label><strong>'.esc_html__('Enable','sello').'</strong></label></div>';
        echo '<div class="sello-inline">';
        echo '<label><input type="checkbox" name="sello[slider_on]" value="1" '.checked($slider_on,1,false).'> '.esc_html__('Show hero/slider','sello').'</label>';
        echo '<select name="sello[slider_mode]">';
        echo '<option value="dynamic" '.selected($slider_mode,'dynamic',false).'>'.esc_html__('Dynamic','sello').'</option>';
        echo '<option value="manual"  '.selected($slider_mode,'manual',false).'>'.esc_html__('Manual (use “binding” targets as list)','sello').'</option>';
        echo '</select>';
        echo '<select name="sello[slider_click]">';
        echo '<option value="apply_filter"  '.selected($slider_click,'apply_filter',false).'>'.esc_html__('On click: apply filter','sello').'</option>';
        echo '<option value="navigate_only" '.selected($slider_click,'navigate_only',false).'>'.esc_html__('On click: navigate only','sello').'</option>';
        echo '</select>';
        echo '<label><input type="checkbox" name="sello[slider_drilldown]" value="1" '.checked($slider_drill,1,false).'> '.esc_html__('Drill-down (show children after click)','sello').'</label>';
        echo '</div>';

        echo '<div><label><strong>'.esc_html__('Binding (what to list)','sello').'</strong></label><div class="sello-muted">'.esc_html__('Type + targets (ids/slugs, comma-separated), depth, include/exclude','sello').'</div></div>';
        echo '<div>';
        $bind_type = isset($slider_bind['type']) ? $slider_bind['type'] : 'category';
        $bind_targets = isset($slider_bind['targets']) ? $slider_bind['targets'] : array();
        $bind_level = isset($slider_bind['level']) ? $slider_bind['level'] : '1';
        $bind_include= isset($slider_bind['include']) ? $slider_bind['include'] : array();
        $bind_exclude= isset($slider_bind['exclude']) ? $slider_bind['exclude'] : array();
        echo '<div class="sello-inline">';
        echo '<select name="sello[slider_binding][type]">';
        foreach (array('category'=>'Product Category','taxonomy'=>'Taxonomy','attribute'=>'Attribute') as $k=>$lab) {
            echo '<option value="'.esc_attr($k).'" '.selected($bind_type,$k,false).'>'.esc_html($lab).'</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '<div class="sello-row"><label>'.esc_html__('Targets','sello').' <input type="text" name="sello[slider_binding][targets]" value="'.esc_attr(implode(',',(array)$bind_targets)).'" class="regular-text" placeholder="e.g. parfums, 321"></label></div>';
        echo '<div class="sello-row"><label>'.esc_html__('Depth','sello').' 
              <select name="sello[slider_binding][level]">';
        foreach (array('1','2','3','all') as $opt) echo '<option value="'.esc_attr($opt).'" '.selected($bind_level,$opt,false).'>'.esc_html($opt).'</option>';
        echo '</select></label></div>';
        echo '<div class="sello-row"><input type="text" name="sello[slider_binding][include]" value="'.esc_attr(implode(',',(array)$bind_include)).'" class="regular-text" placeholder="force include children (ids/slugs)" /></div>';
        echo '<div class="sello-row"><input type="text" name="sello[slider_binding][exclude]" value="'.esc_attr(implode(',',(array)$bind_exclude)).'" class="regular-text" placeholder="exclude children (ids/slugs)" /></div>';
        echo '<div class="sello-help">'.esc_html__('Tip: slider items are rendered as simple cards for now.','sello').'</div>';
        echo '</div>';

        echo '</div>'; // grid
        echo '</div>'; // box

        // Inline JS: add/remove facets in "facets" mode only
        self::inline_js();
    }

    /** Panneau Shortcodes */
    public static function render_shortcodes($post)
    {
        echo '<div style="font-size:13px;line-height:1.6">';
        echo '<p><strong>'.esc_html__('Filters shortcode','sello').'</strong><br>';
        echo '<code class="sello-code">[sello_filters id="'.(int)$post->ID.'"]</code></p>';
        echo '<p><strong>'.esc_html__('Hero/Slider shortcode','sello').'</strong><br>';
        echo '<code class="sello-code">[sello_hero id="'.(int)$post->ID.'"]</code></p>';
        echo '<hr><p class="description">'.esc_html__('If “Auto-attach” is ON, the filter is injected automatically on matching WooCommerce archives.','sello').'</p>';
        echo '</div>';
    }

    /** JS inline pour la liste de facettes (mode "facets") */
    private static function inline_js()
    {
        ?>
<script>
(function(){
  var addBtn = document.getElementById('sello_add_facet_btn');
  var sel    = document.getElementById('sello_add_facet_filter');
  var list   = document.getElementById('sello_filter_facets');
  if (addBtn && sel && list) {
    addBtn.addEventListener('click', function(){
      if (!sel.value) return;
      var id = sel.value, title = sel.options[sel.selectedIndex].textContent || sel.options[sel.selectedIndex].innerText;
      // prevent duplicates
      if (list.querySelector('input[value="'+id+'"]')) { alert('Already added.'); return; }
      var li = document.createElement('li');
      li.className = 'sello-item'; li.setAttribute('data-id', id);
      li.innerHTML = '<span class="title"></span> <a href="#" class="button remove"><?php echo esc_js(__('Remove','sello')); ?></a> <input type="hidden" name="sello[facets][]" />';
      li.querySelector('.title').textContent = title;
      li.querySelector('input').value = id;
      list.appendChild(li);
    });
    document.addEventListener('click', function(e){
      var b = e.target.closest('#sello_filter_facets .remove');
      if (!b) return;
      e.preventDefault();
      var li = b.closest('.sello-item'); if (li) li.parentNode.removeChild(li);
    });
  }
})();
</script>
        <?php
    }

    /** Sauvegarde */
    public static function save($post_id, $post)
    {
        if ($post->post_type !== 'sello_filter') return;
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce($_POST[self::NONCE], self::NONCE)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('manage_sello')) return;

        $in = isset($_POST['sello']) && is_array($_POST['sello']) ? $_POST['sello'] : array();

        // Content
        $content_mode = isset($in['content_mode']) ? sanitize_text_field($in['content_mode']) : 'preset';
        $preset_id    = isset($in['preset_id']) ? absint($in['preset_id']) : 0;

        $facets = array();
        if (isset($in['facets']) && is_array($in['facets'])) {
            foreach ($in['facets'] as $fid) { $fid = absint($fid); if ($fid>0) $facets[] = $fid; }
        }
        $facets = array_values(array_unique($facets));

        update_post_meta($post_id,'content_mode',$content_mode);
        update_post_meta($post_id,'preset_id',$preset_id);
        update_post_meta($post_id,'facets',$facets);

        // Scope
        $scope_type = isset($in['scope_type']) ? sanitize_text_field($in['scope_type']) : 'category';
        $scope_targets = self::csv_to_array(isset($in['scope_targets']) ? $in['scope_targets'] : '');
        $hierarchic  = !empty($in['hierarchic']) ? 1 : 0;
        $level       = isset($in['level']) ? sanitize_text_field($in['level']) : 'all';
        $include     = self::csv_to_array(isset($in['include']) ? $in['include'] : '');
        $exclude     = self::csv_to_array(isset($in['exclude']) ? $in['exclude'] : '');
        $auto_attach = !empty($in['auto_attach']) ? 1 : 0;
        $takeover    = !empty($in['takeover']) ? 1 : 0;

        update_post_meta($post_id,'scope_type',$scope_type);
        update_post_meta($post_id,'scope_targets',$scope_targets);
        update_post_meta($post_id,'hierarchic',$hierarchic);
        update_post_meta($post_id,'level',$level);
        update_post_meta($post_id,'include',$include);
        update_post_meta($post_id,'exclude',$exclude);
        update_post_meta($post_id,'auto_attach',$auto_attach);
        update_post_meta($post_id,'takeover',$takeover);

        // Design
        $chips_on = !empty($in['chips_on']) ? 1 : 0;
        $position = isset($in['design_position']) ? sanitize_text_field($in['design_position']) : 'sidebar_right';
        $w_total  = isset($in['design_width_total'])  ? absint($in['design_width_total'])  : 700;
        $w_facets = isset($in['design_width_facets']) ? absint($in['design_width_facets']) : 500;
        $w_pinned = isset($in['design_width_pinned']) ? absint($in['design_width_pinned']) : 200;

        update_post_meta($post_id,'chips_on',$chips_on);
        update_post_meta($post_id,'design_position',$position);
        update_post_meta($post_id,'design_width_total',$w_total);
        update_post_meta($post_id,'design_width_facets',$w_facets);
        update_post_meta($post_id,'design_width_pinned',$w_pinned);

        // Pinned
        $pinned_on   = !empty($in['pinned_on']) ? 1 : 0;
        $pinned_mode = isset($in['pinned_mode']) ? sanitize_text_field($in['pinned_mode']) : 'dynamic';
        $pinned_items = array();
        if (!empty($in['pinned_items_raw'])) {
            $lines = preg_split('/\r\n|\r|\n/', (string)$in['pinned_items_raw']);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line==='') continue;
                $parts = array_map('trim', explode('|', $line));
                $pinned_items[] = array(
                    'img'   => isset($parts[0]) ? $parts[0] : '',
                    'title' => isset($parts[1]) ? $parts[1] : '',
                    'type'  => isset($parts[2]) ? $parts[2] : '',
                    'value' => isset($parts[3]) ? $parts[3] : '',
                );
            }
        }

        update_post_meta($post_id,'pinned_on',$pinned_on);
        update_post_meta($post_id,'pinned_mode',$pinned_mode);
        update_post_meta($post_id,'pinned_items',$pinned_items);

        // Slider
        $slider_on   = !empty($in['slider_on']) ? 1 : 0;
        $slider_mode = isset($in['slider_mode']) ? sanitize_text_field($in['slider_mode']) : 'dynamic';
        $slider_click= isset($in['slider_click']) ? sanitize_text_field($in['slider_click']) : 'apply_filter';
        $slider_drill= !empty($in['slider_drilldown']) ? 1 : 0;

        $sb = isset($in['slider_binding']) && is_array($in['slider_binding']) ? $in['slider_binding'] : array();
        $bind_type   = isset($sb['type']) ? sanitize_text_field($sb['type']) : 'category';
        $bind_targets= self::csv_to_array(isset($sb['targets']) ? $sb['targets'] : '');
        $bind_level  = isset($sb['level']) ? sanitize_text_field($sb['level']) : '1';
        $bind_include= self::csv_to_array(isset($sb['include']) ? $sb['include'] : '');
        $bind_exclude= self::csv_to_array(isset($sb['exclude']) ? $sb['exclude'] : '');
        $slider_bind = array(
            'type'    => $bind_type,
            'targets' => $bind_targets,
            'level'   => $bind_level,
            'include' => $bind_include,
            'exclude' => $bind_exclude,
        );

        update_post_meta($post_id,'slider_on',$slider_on);
        update_post_meta($post_id,'slider_mode',$slider_mode);
        update_post_meta($post_id,'slider_click',$slider_click);
        update_post_meta($post_id,'slider_drilldown',$slider_drill);
        update_post_meta($post_id,'slider_binding',$slider_bind);
    }

    /** Util: CSV -> array of trimmed unique values (ids or slugs) */
    private static function csv_to_array($csv)
    {
        $out = array();
        if (is_array($csv)) return array_values(array_filter(array_unique(array_map('strval',$csv))));
        $csv = (string)$csv;
        if ($csv==='') return $out;
        $parts = explode(',', $csv);
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p==='') continue;
            // keep numeric ids or slugs (lowercase)
            if (is_numeric($p)) { $out[] = (string)(int)$p; }
            else { $out[] = sanitize_title($p); }
        }
        return array_values(array_unique($out));
    }
}
