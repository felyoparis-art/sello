<?php
/**
 * Data Provider — WPProvider
 * Compat PHP 7.0+
 *
 * Implémentation "WordPress pur" du ProviderInterface.
 * - Ne dépend pas de WooCommerce ni d’ACF (tolère leur présence)
 * - Fournit des helpers génériques pour taxonomies/termes/liens
 *
 * Remarque importante :
 * Ton fichier ProviderInterface actuel ne déclare pas deux méthodes pourtant
 * utilisées ailleurs dans SELLO (getProductPostType, getCurrentTaxQuery).
 * Pour éviter toute casse, WPProvider implémente **aussi** ces deux méthodes.
 * C’est autorisé en PHP et garantit la compatibilité avec Services\Counters.
 */

namespace Sello\Data\Providers;

defined('ABSPATH') || exit;

class WPProvider implements ProviderInterface
{
    /* ============================================================
     * Taxonomies & terms
     * ========================================================== */

    /** @inheritDoc */
    public function getObjectTaxonomies($object_type)
    {
        $object_type = is_string($object_type) ? $object_type : 'post';
        $tax = function_exists('get_object_taxonomies')
            ? get_object_taxonomies($object_type, 'objects')
            : array();
        return is_array($tax) ? array_values($tax) : array();
    }

    /** @inheritDoc */
    public function taxonomyExists($taxonomy)
    {
        return function_exists('taxonomy_exists') ? taxonomy_exists((string)$taxonomy) : false;
    }

    /** @inheritDoc */
    public function getTerms(array $args)
    {
        if (!function_exists('get_terms')) return array();

        // get_terms accepte un array complet (taxonomy, number, parent, search, etc.)
        $terms = get_terms($args);
        if (is_wp_error($terms) || !is_array($terms)) {
            return array();
        }
        return $terms;
    }

    /** @inheritDoc */
    public function getTerm($taxonomy, $id_or_slug)
    {
        $taxonomy = (string)$taxonomy;
        if ($taxonomy === '' || !function_exists('get_term')) {
            return null;
        }

        if (is_numeric($id_or_slug)) {
            $t = get_term((int)$id_or_slug, $taxonomy);
            return (!is_wp_error($t) && $t) ? $t : null;
        }

        $slug = sanitize_title((string)$id_or_slug);
        if ($slug === '' || !function_exists('get_term_by')) {
            return null;
        }
        $t = get_term_by('slug', $slug, $taxonomy);
        return ($t && !is_wp_error($t)) ? $t : null;
    }

    /** @inheritDoc */
    public function getAncestors($term_id, $taxonomy)
    {
        if (!function_exists('get_ancestors')) return array();
        $a = get_ancestors((int)$term_id, (string)$taxonomy);
        return is_array($a) ? array_values(array_map('intval', $a)) : array();
    }

    /* ============================================================
     * WooCommerce-like helpers (fonctionnent en WP simple aussi)
     * ========================================================== */

    /** @inheritDoc */
    public function getAttributeTaxonomySlugs()
    {
        // En WP simple, on liste toutes les taxos dont le slug commence par pa_
        $slugs = array();
        if (function_exists('get_taxonomies')) {
            $all = get_taxonomies(array(), 'names');
            foreach ((array)$all as $slug) {
                if (strpos($slug, 'pa_') === 0) {
                    $slugs[] = (string)$slug;
                }
            }
        }
        return $slugs;
    }

    /** @inheritDoc */
    public function getAttributeLabels()
    {
        $labels = array();
        if (function_exists('get_taxonomies')) {
            $objs = get_taxonomies(array(), 'objects');
            foreach ((array)$objs as $slug => $obj) {
                if (strpos($slug, 'pa_') === 0) {
                    // Utiliser label "human-readable"
                    $lab = isset($obj->labels, $obj->labels->name) ? (string)$obj->labels->name : (string)$obj->label;
                    if ($lab === '') $lab = (string)$slug;
                    $labels[(string)$slug] = $lab;
                }
            }
        }
        return $labels;
    }

    /** @inheritDoc */
    public function getProductCategoryTaxonomy()
    {
        // Si Woo est présent : product_cat ; sinon fallback vers "category"
        if (function_exists('taxonomy_exists') && taxonomy_exists('product_cat')) {
            return 'product_cat';
        }
        return 'category';
    }

    /* ============================================================
     * Liens & URLs
     * ========================================================== */

    /** @inheritDoc */
    public function getTermLink($term)
    {
        if (!function_exists('get_term_link')) return '';

        // Tolérer ID/slug à tout hasard
        if (is_numeric($term)) {
            $term_id = (int)$term;
            // Deviner la taxonomie : essayer product_cat puis category
            $tax = taxonomy_exists('product_cat') ? 'product_cat' : 'category';
            $link = get_term_link($term_id, $tax);
            return is_wp_error($link) ? '' : (string)$link;
        }

        // Objet WP_Term-like
        if (is_object($term) && isset($term->term_id)) {
            $link = get_term_link($term);
            return is_wp_error($link) ? '' : (string)$link;
        }

        return '';
    }

    /** @inheritDoc */
    public function getEditPostLink($post_id)
    {
        $post_id = (int)$post_id;
        if ($post_id <= 0) return '';
        if (function_exists('get_edit_post_link')) {
            $url = get_edit_post_link($post_id, '');
            if (is_string($url) && $url !== '') return $url;
        }
        if (function_exists('admin_url')) {
            return admin_url('post.php?post='.(int)$post_id.'&action=edit');
        }
        return '';
    }

    /* ============================================================
     * ACF (toléré mais non requis)
     * ========================================================== */

    /** @inheritDoc */
    public function getACF($field_key, $object_id)
    {
        // Dans WPProvider, si ACF est présent on peut renvoyer la valeur,
        // sinon on retourne null (comportement neutre).
        if (function_exists('get_field')) {
            // get_field accepte objets ACF : 'term_123', 'option', id post, etc.
            return get_field($field_key, $object_id);
        }
        return null;
    }

    /** @inheritDoc */
    public function getACFImageUrl($field_key, $object_id, $size = 'full')
    {
        if (!function_exists('get_field')) {
            return '';
        }

        $val = get_field($field_key, $object_id);
        if (empty($val)) {
            return '';
        }

        // Selon la config ACF, on peut avoir :
        // - ID d’attachement
        // - array avec ['url'] et ['sizes']
        // - URL directe
        if (is_numeric($val)) {
            $src = wp_get_attachment_image_src((int)$val, $size ? $size : 'full');
            return (is_array($src) && isset($src[0])) ? (string)$src[0] : '';
        }
        if (is_array($val)) {
            // ACF image field (array)
            if (isset($val['sizes']) && is_array($val['sizes']) && isset($val['sizes'][$size])) {
                return (string)$val['sizes'][$size];
            }
            if (isset($val['url'])) {
                return (string)$val['url'];
            }
            return '';
        }
        if (is_string($val)) {
            return esc_url_raw($val);
        }
        return '';
    }

    /* ============================================================
     * Extensions non déclarées par ton interface actuelle
     * (utilisées par d’autres services SELLO)
     * ========================================================== */

    /**
     * Post type de catalogue :
     * - "product" si WooCommerce présent
     * - "post" sinon
     * @return string
     */
    public function getProductPostType()
    {
        if (function_exists('post_type_exists') && post_type_exists('product')) {
            return 'product';
        }
        return 'post';
    }

    /**
     * Construit un tax_query reflétant le contexte du $wp_query courant,
     * en excluant certaines taxonomies si demandé.
     *
     * @param array<string> $exclude_taxonomies
     * @return array
     */
    public function getCurrentTaxQuery(array $exclude_taxonomies = array())
    {
        $exclude = array();
        foreach ($exclude_taxonomies as $t) {
            $t = (string)$t;
            if ($t !== '') $exclude[] = $t;
        }

        // 1) Si on est sur une archive de terme → utiliser l’objet courant
        if (function_exists('is_tax') && is_tax()) {
            $qo = get_queried_object();
            if ($qo && isset($qo->taxonomy, $qo->term_id) && !in_array($qo->taxonomy, $exclude, true)) {
                return array(
                    array(
                        'taxonomy' => (string)$qo->taxonomy,
                        'field'    => 'term_id',
                        'terms'    => array((int)$qo->term_id),
                        'operator' => 'IN',
                        'include_children' => true,
                    ),
                    'relation' => 'AND',
                );
            }
        }

        // 2) Sinon, regarder le $wp_query->tax_query si présent
        global $wp_query;
        if ($wp_query && isset($wp_query->tax_query) && is_object($wp_query->tax_query)) {
            $clauses = isset($wp_query->tax_query->queries) ? $wp_query->tax_query->queries : array();
            $out = array();
            if (is_array($clauses)) {
                foreach ($clauses as $clause) {
                    if (!is_array($clause)) continue;
                    if (isset($clause['relation'])) continue; // géré après
                    $tax = isset($clause['taxonomy']) ? (string)$clause['taxonomy'] : '';
                    if ($tax === '' || in_array($tax, $exclude, true)) continue;

                    $terms = isset($clause['terms']) ? (array)$clause['terms'] : array();
                    $terms = array_values(array_unique(array_map('intval', $terms)));
                    if (empty($terms)) continue;

                    $out[] = array(
                        'taxonomy' => $tax,
                        'field'    => isset($clause['field']) ? (string)$clause['field'] : 'term_id',
                        'terms'    => $terms,
                        'operator' => isset($clause['operator']) ? (string)$clause['operator'] : 'IN',
                        'include_children' => isset($clause['include_children']) ? (bool)$clause['include_children'] : true,
                    );
                }
            }

            if (!empty($out)) {
                $out['relation'] = 'AND';
            }
            return $out;
        }

        // 3) Default: aucun contexte
        return array();
    }
}
