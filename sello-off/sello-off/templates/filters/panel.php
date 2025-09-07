<?php
/** Panel template
 * vars: $filter_id (int)
 */
if (!defined('ABSPATH')) exit;

$filter_id = isset($filter_id) ? (int)$filter_id : 0;
if ($filter_id <= 0) return;

$meta = function($k,$d=null){ $v=get_post_meta($filter_id,$k,true); return ($v===''||$v===null)?$d:$v; };

$content_mode = $meta('content_mode','preset');
$preset_id    = (int)$meta('preset_id',0);
$facets_ids   = $content_mode==='preset'
    ? (array)get_post_meta($preset_id,'facets',true)
    : (array)$meta('facets',[]);
$facets_ids   = array_values(array_filter(array_map('intval',$facets_ids)));

$chips_on     = (bool)$meta('chips_on', true);
$position     = $meta('design_position','sidebar_right');
$w_total      = (int)$meta('design_width_total',700);
$w_facets     = (int)$meta('design_width_facets',500);
$w_pinned     = (int)$meta('design_width_pinned',200);
$pinned_on    = (bool)$meta('pinned_on', false);

$wrapper_cls = 'sello-panel sello-'.$position;
?>
<div class="<?php echo esc_attr($wrapper_cls);?>" data-sello-filter="<?php echo (int)$filter_id;?>" style="--sello-w-total:<?php echo (int)$w_total;?>px;--sello-w-facets:<?php echo (int)$w_facets;?>px;--sello-w-pinned:<?php echo (int)$w_pinned;?>px;">
  <div class="sello-panel-inner">
    <form class="sello-filters-form" method="get">
      <?php
      // CHIPS (selected filters)
      if ($chips_on) {
          echo \Sello\Services\Renderer::view('filters/chips.php', [
              'filter_id' => $filter_id,
              'facets_ids'=> $facets_ids,
          ]);
      }

      // FACETS
      echo '<div class="sello-facets">';
      foreach ($facets_ids as $fid) {
          $facet = get_post($fid);
          if (!$facet || $facet->post_type!=='sello_facet') continue;
          echo \Sello\Services\Renderer::view('filters/facet.php', [
              'facet_id' => $fid,
              'facet_title' => $facet->post_title,
          ]);
      }
      echo '</div>';

      // FOOTER actions
      ?>
      <div class="sello-actions">
        <button type="submit" class="button button-primary"><?php esc_html_e('Apply filters','sello');?></button>
        <a href="<?php echo esc_url( remove_query_arg(array_keys($_GET)) );?>" class="button"><?php esc_html_e('Reset','sello');?></a>
      </div>
    </form>
  </div>

  <?php if ($pinned_on): ?>
    <aside class="sello-pinned">
      <?php echo \Sello\Services\Renderer::view('filters/pinned.php', ['filter_id'=>$filter_id]);?>
    </aside>
  <?php endif; ?>
</div>
