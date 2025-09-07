<?php
/**
 * Domain — Scope helper (auto-attach logic & archive matching)
 * Compat PHP 7.0+
 *
 * Objectif :
 *  - Savoir si un Filter (sello_filter) doit s'afficher automatiquement
 *    dans le contexte courant (archives WooCommerce : catégories, taxos, attributs)
 *  - Gérer targets / hierarchic / level / include / exclude
 *
 * Rappels méta Filter :
 *   scope_type     : 'category' | 'taxonomy' | 'attribute' (def: category)
 *   scope_targets  : string[] (IDs ou slugs — pour 'taxonomy' et 'attribute', ce sont des slugs de taxonomie)
 *   hierarchic     : 0|1   (si category : enfants autorisés)
 *   level          : '1'|'2'|'3'|'all' (profondeur max relative aux cibles si hierarchic=1)
 *   include        : string[] (ids/slugs de termes autorisés explicitement)
 *   exclude        : string[] (ids/slugs de termes exclus)
 *   auto_attach    : 0|1
 *
 * Hypothèses MVP :
 *  - Auto-attach ne vise que les archives de taxonomie (catégories produit et attributs),
 *    pas la page Boutique (shop) ni les pages produit.
 *  - Pour scope_type = 'taxonomy' ou 'attribute' :
 *      * scope_targets liste des taxonomies (ex: ['product_cat'] ou ['pa_marque','pa_couleur'])
 *      * include/exclude s'appliquent aux termes (id/slug) si on est sur un terme.
 */
namespace Sello\Domain;

defined('ABSPATH') || exit;

class Scope
{
    /**
     * Doit-on auto-afficher ce Filter dans le contexte courant ?
     * @param int $filter_id
     * @return bool
     */
    public static function should_auto_attach($filter_id)
    {
        $filter_id = (int)$filter_id;
        if ($filter_id <= 0) return false;

        $auto = (int)get_post_meta($filter_id, 'auto_attach', true);
        if ($auto !== 1) return false;

        return self::matches_current_context($filter_id);
    }

    /**
     * Le Filter cible-t-il le contexte courant (archive) ?
     * @param int $filter_id
     * @return bool
     */
    public static function matches_current_context($filter_id)
    {
        if (!is_tax()) {
            // Ignorer les pages non-taxonomie (Shop, produit, etc.)
            return false;
        }

        $qo = get_queried_object();
        if (!$qo || !isset($qo->taxonomy)) return false;

        $current_tax = (string)$qo->taxonomy;
        $current_id  = isset($qo->term_id) ? (int)$qo->term_id : 0;
        $current_slug= isset($qo->slug) ? (string)$qo->slug : '';

        // Lire configuration du Filter
        $type      = get_post_meta($filter_id, 'scope_type', true) ?: 'category';
        $targets   = get_post_meta($filter_id, 'scope_targets', true);
        $targets   = is_array($targets) ? array_values(array_filter(array_map('strval', $targets))) : array();
        $hierarch  = (int)(get_post_meta($filter_id, 'hierarchic', true) ?: 0);
        $level     = get_post_meta($filter_id, 'level', true) ?: 'all';
        $include   = get_post_meta($filter_id, 'include', true);
        $exclude   = get_post_meta($filter_id, 'exclude', true);
        $include   = is_array($include) ? array_values(array_filter(array_map('strval', $include))) : array();
        $exclude   = is_array($exclude) ? array_values(array_filter(array_map('strval', $exclude))) : array();

        // Normaliser
        $level = in_array($level, array('1','2','3','all'), true) ? $level : 'all';

        // Dispatcher selon scope_type
        switch ($type) {
            case 'category':
                // Vise exclusivement la taxonomie des catégories produit
                if ($current_tax !== 'product_cat') return false;

                // Cibles : termes (id ou slug). Vide = toutes catégories.
                if (!empty($targets)) {
                    if (!self::is_term_in_targets($current_id, $current_slug, 'product_cat', $targets)) {
                        // Si hiérarchique, accepter descendants (avec contrôle de level)
                        if (!($hierarch === 1 && self::is_descendant_of_any_target($current_id, 'product_cat', $targets, $level))) {
                            return false;
                        }
                    }
                }

                // include/exclude sur le terme courant
                if (!self::passes_include_exclude($current_id, $current_slug, $include, $exclude)) {
                    return false;
                }
                return true;

            case 'taxonomy':
                // scope_targets = slugs de taxonomies (ex: product_cat, brand, pa_marque…)
                if (!empty($targets) && !in_array($current_tax, array_map('strval', $targets), true)) {
                    return false;
                }
                // include/exclude sur terme si applicable
                if (!self::passes_include_exclude($current_id, $current_slug, $include, $exclude)) {
                    return false;
                }
                return true;

            case 'attribute':
                // Attributs Woo = taxonomies 'pa_*'
                if (strpos($current_tax, 'pa_') !== 0) return false;
                if (!empty($targets) && !in_array($current_tax, array_map('strval', $targets), true)) {
                    return false;
                }
                if (!self::passes_include_exclude($current_id, $current_slug, $include, $exclude)) {
                    return false;
                }
                return true;

            default:
                return false;
        }
    }

    /* ============================================================
     * Helpers
     * ========================================================== */

    /**
     * Le terme courant correspond-il à une des cibles (IDs ou slugs) ?
     * @param int $term_id
     * @param string $term_slug
     * @param string $taxonomy
     * @param array $targets
     * @return bool
     */
    private static function is_term_in_targets($term_id, $term_slug, $taxonomy, array $targets)
    {
        if ($term_id <= 0 && $term_slug === '') return false;
        $targets = array_map('strval', $targets);

        // Match direct par ID
        if ($term_id > 0 && in_array((string)$term_id, $targets, true)) {
            return true;
        }

        // Match direct par slug
        if ($term_slug !== '' && in_array($term_slug, $targets, true)) {
            return true;
        }

        // Certains utilisateurs passent "taxonomy:slug". On le tolère.
        $compound = $taxonomy . ':' . $term_slug;
        if ($term_slug !== '' && in_array($compound, $targets, true)) {
            return true;
        }

        return false;
    }

    /**
     * Le terme courant est-il descendant (enfants/niveaux) d'une des cibles ?
     * Respecte le paramètre $level ('1','2','3','all').
     *
     * @param int      $term_id
     * @param string   $taxonomy
     * @param string[] $targets  (ids ou slugs)
     * @param string   $level
     * @return bool
     */
    private static function is_descendant_of_any_target($term_id, $taxonomy, array $targets, $level)
    {
        $term_id = (int)$term_id;
        if ($term_id <= 0) return false;

        // Construire set de cibles (ids)
        $target_ids = array();
        foreach ($targets as $t) {
            if (is_numeric($t)) {
                $target_ids[] = (int)$t;
            } else {
                $slug = sanitize_title((string)$t);
                $obj  = get_term_by('slug', $slug, $taxonomy);
                if ($obj && !is_wp_error($obj)) {
                    $target_ids[] = (int)$obj->term_id;
                }
            }
        }
        $target_ids = array_values(array_unique(array_filter($target_ids)));

        if (empty($target_ids)) return false;

        $ancestors = get_ancestors($term_id, $taxonomy); // liste d'IDs (du parent direct jusqu'à la racine)
        if (!is_array($ancestors)) $ancestors = array();

        // Si aucune des cibles n'est dans les ancêtres, pas descendant.
        $intersect = array_values(array_intersect($ancestors, $target_ids));
        if (empty($intersect)) return false;

        // Contrôle du niveau : distance entre target et term
        if ($level === 'all') return true;

        // Pour chaque cible trouvée dans les ancêtres, calculer la profondeur relative
        foreach ($intersect as $target_id) {
            $depth = self::distance_between_terms($term_id, $target_id, $taxonomy);
            if ($depth <= (int)$level) {
                return true;
            }
        }
        return false;
    }

    /**
     * Distance (nb de pas parent) entre un terme et une cible ancêtre.
     * @param int $term_id
     * @param int $ancestor_id
     * @param string $taxonomy
     * @return int|PHP_INT_MAX
     */
    private static function distance_between_terms($term_id, $ancestor_id, $taxonomy)
    {
        $term_id     = (int)$term_id;
        $ancestor_id = (int)$ancestor_id;

        if ($term_id <= 0 || $ancestor_id <= 0) return PHP_INT_MAX;
        if ($term_id === $ancestor_id) return 0;

        $depth = 0;
        $current = $term_id;
        $safety  = 0;

        while ($current > 0 && $safety < 100) {
            $term = get_term($current, $taxonomy);
            if (!$term || is_wp_error($term)) break;

            if ((int)$term->parent === 0) {
                // Atteint la racine
                break;
            }

            $depth++;
            $current = (int)$term->parent;

            if ($current === $ancestor_id) {
                return $depth;
            }
            $safety++;
        }

        return PHP_INT_MAX;
    }

    /**
     * Vérifie include/exclude par id/slug sur le terme courant.
     * - Si include non vide : le terme DOIT être dans include.
     * - Si exclude non vide : le terme NE DOIT PAS être dans exclude.
     *
     * @param int $term_id
     * @param string $term_slug
     * @param array $include
     * @param array $exclude
     * @return bool
     */
    private static function passes_include_exclude($term_id, $term_slug, array $include, array $exclude)
    {
        $term_id  = (int)$term_id;
        $term_slug= (string)$term_slug;

        // include
        if (!empty($include)) {
            $in = false;
            foreach ($include as $v) {
                $v = (string)$v;
                if ($v === (string)$term_id || $v === $term_slug) {
                    $in = true; break;
                }
            }
            if (!$in) return false;
        }

        // exclude
        if (!empty($exclude)) {
            foreach ($exclude as $v) {
                $v = (string)$v;
                if ($v === (string)$term_id || $v === $term_slug) {
                    return false;
                }
            }
        }

        return true;
    }
}
