<?php
/** Chips template
 * vars: $filter_id, $facets_ids
 */
if (!defined('ABSPATH')) exit;

$chips = [];
foreach ($facets_ids as $fid) {
    $param = 'sello_f_' . (int)$fid;
    if (!isset($_GET[$param])) continue;
    $vals = is_array($_GET[$param]) ? $_GET[$param] : explode(',', (string)$_GET[$param]);
    $vals = array_filter(array_map('sanitize_text_field',$vals));
    if (!$vals) continue;

    // try labels for taxonomy terms
    $labels = [];
    foreach ($vals as $v) {
        $term = get_term( (int)$v );
        $labels[] = $term && !is_wp_error($term) ? $term->name : $v;
    }
    $chips[] = ['fid'=>$fid,'param'=>$param,'labels'=>$labels,'vals'=>$vals];
}
if (!$chips) return;
?>
<div class="sello-chips">
  <?php foreach ($chips as $c): foreach ($c['labels'] as $i=>$label): ?>
    <a href="#" class="sello-chip" data-param="<?php echo esc_attr($c['param']);?>" data-value="<?php echo esc_attr($c['vals'][$i]);?>">
      <?php echo esc_html($label);?> <span aria-hidden="true">×</span>
    </a>
  <?php endforeach; endforeach; ?>
  <a href="<?php echo esc_url( remove_query_arg(array_keys($_GET)) );?>" class="sello-chip-clear"><?php esc_html_e('Clear all','sello');?></a>
</div>
