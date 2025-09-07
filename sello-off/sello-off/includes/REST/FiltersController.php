<?php
/*========================================================================
 File: includes/REST/FiltersController.php
========================================================================*/
namespace Sello\REST;

defined('ABSPATH') || exit;

class FiltersController {
    public static function apply(\WP_REST_Request $req) {
        // MVP: echo back selections (front uses standard form GET submit anyway)
        return new \WP_REST_Response([
            'ok' => true,
            'received' => $req->get_params()
        ], 200);
    }
}
