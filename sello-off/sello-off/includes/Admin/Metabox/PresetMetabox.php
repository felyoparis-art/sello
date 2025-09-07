<?php
/**
 * Metabox — Preset (groupe de facettes + ordre)
 * Compat PHP 7.0+
 */
namespace Sello\Admin\Metabox;

defined('ABSPATH') || exit;

class PresetMetabox
{
    const NONCE = 'sello_preset_mb_nonce';

    /** Enregistre la metabox pour le CPT 'sello_preset' */
    public static function register()
    {
        add_meta_box(
            'sello_preset_main',
            __('Preset settings','sello'),
            array(self::class, 'render'),
            'sello_preset',
            'normal',
            'high'
        );
    }

    /** Affiche le formulaire (liste de facettes sélectionnées + ordre) */
    public static function render($post)
    {
        wp_nonce_field(self::NONCE, self::NONCE);

        // Facettes déjà dans le preset (IDs)
        $selected = get_post_meta($post->ID, 'facets', true);
        if (!is_array($selected)) $selected = array();
        $selected = array_values(array_unique(array_map('intval', $selected)));

        // Toutes les facettes disponibles
        $all_facets = get_posts(array(
            'post_type'              => 'sello_facet',
            'post_status'            => 'publish',
            'numberposts'            => -1,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ));

        // Fabrique une map id => titre
        $facet_titles = array();
        if ($all_facets) {
            foreach ($all_facets as $fid) {
                $facet_titles[$fid] = get_the_title($fid);
            }
        }

        // Styles simples (uniformiser les pages vides)
        echo '<style>
            .sello-box{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:14px;margin-top:10px}
            .sello-grid{display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:start}
            .sello-muted{color:#6b7280;font-size:12px}
            .sello-row{margin:6px 0}
            .sello-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
            .sello-item{display:flex;align-items:center;gap:10px;border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fafafa}
            .sello-item .handle{cursor:move;user-select:none;padding:0 6px;border:1px solid #e5e7eb;border-radius:4px;background:#fff}
            .sello-item .title{flex:1}
            .sello-item .btn{display:inline-block;border:1px solid #d1d5db;border-radius:4px;background:#fff;padding:4px 8px;text-decoration:none;color:#111}
            .sello-item .btn:hover{background:#f3f4f6}
            .sello-inline{display:flex;gap:8px;align-items:center}
        </style>';

        echo '<div class="sello-box">';
        echo '<div class="sello-grid">';

        // Sélecteur d'ajout
        echo '<div><label for="sello_add_facet"><strong>'.esc_html__('Add facets','sello').'</strong></label><div class="sello-muted">'.esc_html__('Pick a facet then click “Add”. Reorder by dragging.','sello').'</div></div>';
        echo '<div class="sello-inline">';
        echo '<select id="sello_add_facet" style="min-width:260px">';
        echo '<option value="">'.esc_html__('— Select a facet —','sello').'</option>';
        if ($facet_titles) {
            // Liste triée alpha
            asort($facet_titles);
            foreach ($facet_titles as $fid=>$title) {
                // Évite d’afficher celles déjà sélectionnées (on peut garder quand même pour re-add)
                $disabled = in_array($fid, $selected, true) ? ' data-selected="1"' : '';
                echo '<option value="'.esc_attr($fid).'"'.$disabled.'>'.esc_html($title).' (#'.(int)$fid.')</option>';
            }
        }
        echo '</select>';
        echo '<button type="button" class="button" id="sello_add_facet_btn">'.esc_html__('Add','sello').'</button>';
        echo '</div>';

        // Liste ordonnable
        echo '<div><label><strong>'.esc_html__('Selected facets (order)','sello').'</strong></label><div class="sello-muted">'.esc_html__('Drag to reorder. Click “Remove” to delete.','sello').'</div></div>';
        echo '<div>';
        echo '<ul class="sello-list" id="sello_preset_list">';
        if ($selected) {
            foreach ($selected as $fid) {
                $title = isset($facet_titles[$fid]) ? $facet_titles[$fid] : ('#'.$fid);
                self::render_item($fid, $title);
            }
        }
        echo '</ul>';

        echo '<p class="sello-muted">'.esc_html__('Tip: A “Preset” is reusable. You can attach it to multiple Filters.','sello').'</p>';
        echo '</div>'; // right col

        echo '</div>'; // grid
        echo '</div>'; // box

        // Inline JS minimal (sortable + add/remove)
        self::inline_js();
    }

    /** Rendu d’un item sélectionné */
    private static function render_item($fid, $title)
    {
        $fid = (int)$fid;
        echo '<li class="sello-item" data-id="'.esc_attr($fid).'">';
        echo '<span class="handle" title="'.esc_attr__('Drag','sello').'">↕</span>';
        echo '<span class="title">'.esc_html($title).'</span>';
        echo '<a href="#" class="btn remove">'.esc_html__('Remove','sello').'</a>';
        // Champ caché pour l’ordre
        echo '<input type="hidden" name="sello[facets][]" value="'.esc_attr($fid).'" />';
        echo '</li>';
    }

    /** JS inline (sans dépendances externes) */
    private static function inline_js()
    {
        ?>
<script>
(function(){
  // Simple drag & drop (HTML5)
  var list = document.getElementById('sello_preset_list');
  if (list) {
    var draggingEl = null;
    list.addEventListener('dragstart', function(e){
      var li = e.target.closest('.sello-item');
      if (!li) return;
      draggingEl = li;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain','');
      li.style.opacity = '.5';
    });
    list.addEventListener('dragend', function(e){
      if (draggingEl) draggingEl.style.opacity = '1';
      draggingEl = null;
    });
    list.addEventListener('dragover', function(e){
      if (!draggingEl) return;
      e.preventDefault();
      var li = e.target.closest('.sello-item');
      if (!li || li === draggingEl) return;
      var rect = li.getBoundingClientRect();
      var next = (e.clientY - rect.top) / (rect.height) > .5;
      list.insertBefore(draggingEl, next ? li.nextSibling : li);
    });
    // Make current items draggable
    Array.prototype.forEach.call(list.querySelectorAll('.sello-item'), function(li){
      li.setAttribute('draggable','true');
    });
  }

  // Remove
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.sello-item .remove');
    if (!btn) return;
    e.preventDefault();
    var li = btn.closest('.sello-item');
    if (!li) return;
    // Mark option as re-addable in the select
    var id = li.getAttribute('data-id');
    var opt = document.querySelector('#sello_add_facet option[value="'+id+'"]');
    if (opt) opt.removeAttribute('data-selected');
    li.parentNode.removeChild(li);
  });

  // Add
  var addBtn = document.getElementById('sello_add_facet_btn');
  if (addBtn) addBtn.addEventListener('click', function(){
    var sel = document.getElementById('sello_add_facet');
    if (!sel || !sel.value) return;
    var opt = sel.options[sel.selectedIndex];
    if (opt.getAttribute('data-selected') === '1') {
      alert('Already added.');
      return;
    }
    var id = sel.value, title = opt.textContent || opt.innerText;

    var li = document.createElement('li');
    li.className = 'sello-item';
    li.setAttribute('data-id', id);
    li.setAttribute('draggable','true');
    li.innerHTML =
      '<span class="handle" title="Drag">↕</span>' +
      '<span class="title"></span>' +
      '<a href="#" class="btn remove"><?php echo esc_js(__('Remove','sello')); ?></a>' +
      '<input type="hidden" name="sello[facets][]" />';
    li.querySelector('.title').textContent = title;
    li.querySelector('input').value = id;
    document.getElementById('sello_preset_list').appendChild(li);

    opt.setAttribute('data-selected','1');
  });
})();
</script>
<style>
/* Ensure draggable cursor on current items (in case JS adds after load) */
#sello_preset_list .sello-item{ position:relative }
#sello_preset_list .sello-item .handle{ cursor:move }
</style>
        <?php
    }

    /** Sauvegarde des facettes du preset (ordre) */
    public static function save($post_id, $post)
    {
        if ($post->post_type !== 'sello_preset') return;
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce($_POST[self::NONCE], self::NONCE)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('manage_sello')) return;

        $in = isset($_POST['sello']) && is_array($_POST['sello']) ? $_POST['sello'] : array();

        $facets = array();
        if (isset($in['facets']) && is_array($in['facets'])) {
            foreach ($in['facets'] as $fid) {
                $fid = absint($fid);
                if ($fid > 0) $facets[] = $fid;
            }
        }
        // Normaliser (unique + index réinitialisé)
        $facets = array_values(array_unique($facets));

        update_post_meta($post_id, 'facets', $facets);

        // Placeholder pour états par facette si un jour nécessaire
        // $states = isset($in['facet_states']) && is_array($in['facet_states']) ? $in['facet_states'] : array();
        // update_post_meta($post_id, 'facet_states', $states);
    }
}
