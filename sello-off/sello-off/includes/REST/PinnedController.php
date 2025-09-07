<?php
/*========================================================================
 File: includes/REST/PinnedController.php
========================================================================*/
namespace Sello\REST;

defined('ABSPATH') || exit;

class PinnedController {
    public static function items(\WP_REST_Request $req) {
        $filter_id = absint($req->get_param('filter_id'));
        if ($filter_id<=0) return new \WP_REST_Response(['ok'=>false,'error'=>'filter_id required'], 400);

        // MVP: no dynamic pinned yet
        return new \WP_REST_Response(['ok'=>true,'items'=>[]], 200);
    }
}
