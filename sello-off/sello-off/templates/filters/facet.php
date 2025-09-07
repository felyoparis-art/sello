<?php
/** Facet template
 * vars: $facet_id, $facet_title
 */
if (!defined('ABSPATH')) exit;

$getm = fn($k,$d=null)=> ( ($v=get_post_meta($facet_id,$k,true))==='' ? $d : $v );

$source_type = $getm('source_type','taxonomy');       // taxonomy|attribute|category|acf|meta
$source_key  = $getm('source_key','product_cat');     // taxonomy/attr slug or meta key
$display     = $getm('display','checkbox');           // checkbox|radio|dropdown|list_v|...
$max_visible = (int)$getm('max_visible',5);
$select_mode = $getm('select_mode','multi');          // single|multi

// name for GET params
$param = 'sello_f_' . (int)$facet_id;

// fetch options (taxonomy-like only in MVP)
$options = [];
if (in_array($source_type,['taxonomy','attribute','category'], true)) {
    $tax = $source_key ?: 'product_cat';
    $terms = get_terms([
        'taxonomy'   => $tax,
        'hide_empty' => false,
        'number'     => $max_visible, // MVP: limit to max_visible
    ]);
    foreach ($terms as $t) {
        $options[] = ['val'=>$t->term_id,'label'=>$t->name];
    }
} else {
    // MVP: meta/acf listing to be done via REST later; show placeholder
    $options = [];
}

$selected = [];
if (isset($_GET[$param])) {
    $v = $_GET[$param];
    $selected = is_array($v) ? array_map('sanitize_text_field',$v) : array_map('sanitize_text_field', explode(',', $v));
}
?>
<div class="sello-facet" data-facet="<?php echo (int)$facet_id;?>">
  <div class="sello-facet-head">
    <strong><?php echo esc_html($facet_title);?></strong>
  </div>

  <div class="sello-facet-body">
    <?php if (empty($options) && !in_array($source_type,['taxonomy','attribute','category'], true)): ?>
      <em><?php esc_html_e('Options will load dynamically (meta/ACF).','sello');?></em>
    <?php elseif ($display==='dropdown'): ?>
      <select name="<?php echo esc_attr($param) . ($select_mode==='multi'?'[]':'');?>" <?php echo ($select_mode==='multi'?'multiple':'');?> class="sello-facet-dd">
        <?php foreach ($options as $opt): ?>
          <option value="<?php echo esc_attr($opt['val']);?>" <?php selected(in_array((string)$opt['val'], $selected,true));?>><?php echo esc_html($opt['label']);?></option>
        <?php endforeach;?>
      </select>
    <?php else: // checkbox/radio/list ?>
      <ul class="sello-facet-list">
        <?php foreach ($options as $opt): ?>
          <li>
            <?php if ($select_mode==='single' || $display==='radio'): ?>
              <label><input type="radio" name="<?php echo esc_attr($param);?>" value="<?php echo esc_attr($opt['val']);?>" <?php checked(in_array((string)$opt['val'],$selected,true));?>> <?php echo esc_html($opt['label']);?></label>
            <?php else: ?>
              <label><input type="checkbox" name="<?php echo esc_attr($param);?>[]" value="<?php echo esc_attr($opt['val']);?>" <?php checked(in_array((string)$opt['val'],$selected,true));?>> <?php echo esc_html($opt['label']);?></label>
            <?php endif; ?>
          </li>
        <?php endforeach;?>
      </ul>
    <?php endif; ?>
  </div>
</div>
