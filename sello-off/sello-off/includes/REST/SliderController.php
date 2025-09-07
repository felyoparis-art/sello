<?php
/*========================================================================
 File: includes/REST/SliderController.php
========================================================================*/
namespace Sello\REST;

defined('ABSPATH') || exit;

class SliderController {
    public static function items(\WP_REST_Request $req) {
        $filter_id = absint($req->get_param('filter_id'));
        if ($filter_id<=0) return new \WP_REST_Response(['ok'=>false,'error'=>'filter_id required'], 400);

        $binding = (array) get_post_meta($filter_id,'slider_binding', true);
        $type    = $binding['type']    ?? 'category';
        $targets = $binding['targets'] ?? [];

        $items = [];
        if (in_array($type,['category','taxonomy','attribute'], true)) {
            foreach ($targets as $slugOrId) {
                $term = is_numeric($slugOrId) ? get_term((int)$slugOrId) : get_term_by('slug', $slugOrId, ($type==='category'?'product_cat':null));
                if ($term && !is_wp_error($term)) {
                    $items[] = ['id'=>$term->term_id,'title'=>$term->name,'url'=>get_term_link($term)];
                }
            }
        }
        return new \WP_REST_Response(['ok'=>true,'items'=>$items], 200);
    }
}
