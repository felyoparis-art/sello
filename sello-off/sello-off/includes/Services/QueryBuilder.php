<?php
/*========================================================================
 File: includes/Services/QueryBuilder.php
========================================================================*/
namespace Sello\Services;

defined('ABSPATH') || exit;

class QueryBuilder {

    /**
     * MVP: Apply taxonomy-based facets from GET to WP_Query (product archives).
     * Only handles taxonomy/attribute/category facets in V1.
     */
    public static function apply_to_query(\WP_Query $q, int $filter_id): void {
        if (is_admin() || !$q->is_main_query()) return;

        // Only apply on product archives/search
        if (!$q->is_post_type_archive('product') && !is_tax() && !$q->is_search()) return;

        $content_mode = get_post_meta($filter_id,'content_mode',true) ?: 'preset';
        $facets_ids   = ($content_mode==='preset')
            ? (array)get_post_meta((int)get_post_meta($filter_id,'preset_id',true),'facets',true)
            : (array)get_post_meta($filter_id,'facets',true);
        $facets_ids = array_values(array_filter(array_map('intval',$facets_ids)));

        $tax_query = (array)$q->get('tax_query');
        foreach ($facets_ids as $fid) {
            $source_type = get_post_meta($fid,'source_type',true) ?: 'taxonomy';
            if (!in_array($source_type,['taxonomy','attribute','category'], true)) continue;

            $taxonomy = get_post_meta($fid,'source_key',true) ?: 'product_cat';
            $vals     = UrlManager::get_selected($fid);
            if (!$vals) continue;

            // values are term IDs (as rendered in facet.php)
            $term_ids = array_map('intval',$vals);
            $tax_query[] = [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $term_ids,
                'operator' => 'IN',
            ];
        }

        if ($tax_query) {
            $tax_query['relation'] = 'AND';
            $q->set('tax_query', $tax_query);
        }
    }
}
