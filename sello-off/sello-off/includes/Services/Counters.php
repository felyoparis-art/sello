<?php
/**
 * Services — Counters
 * Compat PHP 7.0+
 *
 * Calcule les compteurs produits (n) par terme pour alimenter l'UI des facettes.
 * - Respecte le contexte courant (autres filtres déjà actifs) si demandé
 * - Supporte WooCommerce (post_type=product) ou WordPress pur (post_type=post)
 * - Utilise un cache simple via Services\Cache
 *
 * Limitations MVP :
 * - Fait 1 requête par terme (optimisé par cache et limite de termes)
 * - Contexte = tax_query du $wp_query courant, en excluant la taxonomie comptée
 * - Ne gère pas (ici) prix/stock/availability ; ce sera ajouté plus tard
 */

namespace Sello\Services;

use Sello\Data\Providers\ProviderInterface;

defined('ABSPATH') || exit;

class Counters
{
    /** @var ProviderInterface */
    private $provider;

    /** @var Cache */
    private $cache;

    public function __construct(ProviderInterface $provider, Cache $cache)
    {
        $this->provider = $provider;
        $this->cache    = $cache;
    }

    /**
     * Compte le nombre d'objets pour une liste de termes.
     *
     * @param string               $taxonomy       ex: product_cat, pa_marque
     * @param array<int,int>|array<int,string> $term_ids_or_slugs  IDs ou slugs
     * @param array                $opts           Options:
     *    - respect_context (bool)  : tenir compte du contexte courant (def: true)
     *    - exclude_self   (bool)   : retirer $taxonomy du contexte pour éviter auto-filtrage (def: true)
     *    - post_type      (string) : override (def: provider->getProductPostType())
     *    - max_terms      (int)    : limite de termes pour protéger la perf (def: 50)
     *    - ttl            (int)    : cache TTL en secondes (def: 300)
     * @return array<int,int> map term_id => count
     */
    public function counts_for_terms($taxonomy, array $term_ids_or_slugs, array $opts = array())
    {
        $taxonomy = (string)$taxonomy;
        if ($taxonomy === '' || empty($term_ids_or_slugs)) {
            return array();
        }

        $respect_context = isset($opts['respect_context']) ? (bool)$opts['respect_context'] : true;
        $exclude_self    = isset($opts['exclude_self']) ? (bool)$opts['exclude_self'] : true;
        $post_type       = isset($opts['post_type']) ? (string)$opts['post_type'] : $this->provider->getProductPostType();
        $max_terms       = isset($opts['max_terms']) ? (int)$opts['max_terms'] : 50;
        $ttl             = isset($opts['ttl']) ? (int)$opts['ttl'] : 300;

        // Normaliser la liste (et limiter)
        $list = array();
        foreach ($term_ids_or_slugs as $v) {
            $v = is_scalar($v) ? (string)$v : '';
            if ($v === '') continue;
            $list[] = $v;
        }
        $list = array_values(array_unique($list));
        if (count($list) > $max_terms) {
            $list = array_slice($list, 0, $max_terms);
        }

        // Résoudre slugs → IDs pour stabiliser les cache keys
        $resolved = $this->resolve_terms_to_ids($taxonomy, $list); // map input(string) => term_id
        if (empty($resolved)) return array();

        // Contexte tax_query
        $context_tax_query = array();
        if ($respect_context) {
            $exclude = $exclude_self ? array($taxonomy) : array();
            $context_tax_query = $this->provider->getCurrentTaxQuery($exclude);
        }

        // Hash de contexte pour cache key
        $ctx_key = $this->hash_tax_query($context_tax_query);

        $results = array();

        foreach ($resolved as $input => $term_id) {
            if ($term_id <= 0) continue;

            // Cache key par terme
            $ck = sprintf('cnt:%s:%d:%s:%s',
                $post_type,
                (int)$term_id,
                strtolower($taxonomy),
                $ctx_key
            );

            $count = $this->cache->get($ck, null);
            if ($count === null) {
                $count = $this->count_for_term($post_type, $taxonomy, (int)$term_id, $context_tax_query);
                $this->cache->set($ck, (int)$count, $ttl);
            }

            $results[(int)$term_id] = (int)$count;
        }

        return $results;
    }

    /**
     * Compte les objets pour un seul terme (avec contexte).
     *
     * @param string $post_type
     * @param string $taxonomy
     * @param int    $term_id
     * @param array  $context_tax_query
     * @return int
     */
    private function count_for_term($post_type, $taxonomy, $term_id, array $context_tax_query)
    {
        // Construire la tax_query finale : contexte + le terme ciblé
        $tax_query = $context_tax_query;

        $tax_query[] = array(
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => array((int)$term_id),
            'operator' => 'IN',
        );

        // WP_Query pour obtenir found_posts
        $args = array(
            'post_type'           => $post_type,
            'post_status'         => 'publish',
            'fields'              => 'ids',
            'posts_per_page'      => 1,       // on veut juste found_posts
            'paged'               => 1,
            'no_found_rows'       => false,   // IMPORTANT: calcule FOUND_ROWS()
            'ignore_sticky_posts' => true,
            'tax_query'           => $this->normalize_tax_query($tax_query),
            'cache_results'       => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );

        // Sécurité : si Woo inactif, restreindre au type "post" mais ça fonctionne pareil
        $q = new \WP_Query($args);
        $count = (int)$q->found_posts;
        wp_reset_postdata();

        return $count;
    }

    /**
     * Construit un tax_query à partir du contexte courant (global $wp_query),
     * en excluant certaines taxonomies si demandé.
     *
     * @param array<string> $exclude_taxonomies
     * @return array
     */
    public function get_context_tax_query(array $exclude_taxonomies = array())
    {
        return $this->provider->getCurrentTaxQuery($exclude_taxonomies);
    }

    /* ============================================================
     * Helpers internes
     * ========================================================== */

    /**
     * Transforme toute entrée (ID ou slug) en term_id et renvoie une map.
     * @param string $taxonomy
     * @param array<int,string> $values
     * @return array<string,int> input => term_id
     */
    private function resolve_terms_to_ids($taxonomy, array $values)
    {
        $out = array();
        foreach ($values as $v) {
            if ($v === '') continue;

            if (ctype_digit($v)) {
                $out[$v] = (int)$v;
                continue;
            }

            $slug = sanitize_title($v);
            $term = get_term_by('slug', $slug, $taxonomy);
            if ($term && !is_wp_error($term)) {
                $out[$v] = (int)$term->term_id;
            }
        }
        return $out;
    }

    /**
     * Normalise un tax_query : supprime les entrées vides et ajoute la relation AND si nécessaire.
     * @param array $tax_query
     * @return array
     */
    private function normalize_tax_query(array $tax_query)
    {
        $out = array();
        foreach ($tax_query as $clause) {
            if (!is_array($clause)) continue;
            if (empty($clause['taxonomy']) || empty($clause['terms'])) continue;

            $taxonomy = (string)$clause['taxonomy'];
            $field    = isset($clause['field']) ? (string)$clause['field'] : 'term_id';
            $terms    = is_array($clause['terms']) ? array_values(array_unique(array_map('intval', $clause['terms']))) : array();
            if (empty($terms)) continue;

            $out[] = array(
                'taxonomy' => $taxonomy,
                'field'    => $field,
                'terms'    => $terms,
                'operator' => isset($clause['operator']) ? $clause['operator'] : 'IN',
                'include_children' => isset($clause['include_children']) ? (bool)$clause['include_children'] : true,
            );
        }

        if (!empty($out) && !isset($out['relation'])) {
            // WordPress accepte soit une string 'AND'/'OR', soit on ajoute séparément
            $out['relation'] = 'AND';
        }
        return $out;
    }

    /**
     * Hache un tax_query pour en faire une clé de cache stable.
     * @param array $tax_query
     * @return string
     */
    private function hash_tax_query(array $tax_query)
    {
        if (empty($tax_query)) return 'none';
        // Trier pour stabilité
        $normalized = $this->normalize_tax_query($tax_query);
        return substr(md5(wp_json_encode($normalized)), 0, 12);
    }
}
