<?php
/** Hero/Slider template
 * vars: $filter_id (int)
 */
if (!defined('ABSPATH')) exit;

$filter_id = isset($filter_id)? (int)$filter_id : 0;
if ($filter_id<=0) return;

$binding = (array) get_post_meta($filter_id,'slider_binding', true);
$type    = $binding['type']    ?? 'category';
$targets = $binding['targets'] ?? [];
$level   = $binding['level']   ?? '1';

echo '<div class="sello-hero">';
echo '<div class="sello-hero-track">';

if (in_array($type,['category','taxonomy','attribute'], true)) {
    // MVP: show bound targets as cards
    foreach ($targets as $slugOrId) {
        $term = is_numeric($slugOrId) ? get_term((int)$slugOrId) : get_term_by('slug', $slugOrId, ($type==='category'?'product_cat':null));
        if (!$term || is_wp_error($term)) continue;
        $url = get_term_link($term);
        echo '<a class="sello-hero-card" href="'.esc_url($url).'"><span>'.esc_html($term->name).'</span></a>';
    }
} else {
    echo '<em>'.esc_html__('Bind this slider to a taxonomy/attribute to populate items.','sello').'</em>';
}
echo '</div></div>';
