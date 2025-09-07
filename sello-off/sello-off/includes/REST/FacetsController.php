<?php
/*========================================================================
 File: includes/REST/FacetsController.php
========================================================================*/
namespace Sello\REST;

defined('ABSPATH') || exit;

class FacetsController {
    public static function options(\WP_REST_Request $req) {
        $fid = absint($req->get_param('facet_id'));
        if ($fid<=0) return new \WP_REST_Response(['ok'=>false,'error'=>'facet_id required'], 400);

        $source_type = get_post_meta($fid,'source_type',true) ?: 'taxonomy';
        $source_key  = get_post_meta($fid,'source_key',true) ?: 'product_cat';
        $max_visible = (int) (get_post_meta($fid,'max_visible',true) ?: 5);

        $items = [];
        if (in_array($source_type,['taxonomy','attribute','category'], true)) {
            $terms = get_terms(['taxonomy'=>$source_key,'hide_empty'=>false,'number'=>$max_visible]);
            foreach ($terms as $t) {
                $items[] = ['value'=>$t->term_id, 'label'=>$t->name, 'count'=> (int)$t->count];
            }
        } else {
            // TODO: meta/acf values scan
        }

        return new \WP_REST_Response(['ok'=>true,'items'=>$items], 200);
    }
}
