<?php
/*========================================================================
 File: includes/Services/UrlManager.php
========================================================================*/
namespace Sello\Services;

defined('ABSPATH') || exit;

class UrlManager {
    /** Return GET param name for a facet id */
    public static function param_for_facet(int $facet_id): string {
        return 'sello_f_' . $facet_id;
    }

    /** Get selected values for a facet from $_GET */
    public static function get_selected(int $facet_id): array {
        $p = self::param_for_facet($facet_id);
        if (!isset($_GET[$p])) return [];
        $v = $_GET[$p];
        $vals = is_array($v) ? $v : explode(',', (string)$v);
        return array_filter(array_map('sanitize_text_field', $vals));
    }
}
