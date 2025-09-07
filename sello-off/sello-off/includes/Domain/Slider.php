<?php
/**
 * Domain — Slider (Hero / Aero-navigation)
 * Compat PHP 7.0+
 *
 * Construit la liste d’éléments du Hero/Slider pour un Filter donné.
 * S’appuie sur les métas du Filter :
 *   - slider_on        : 0|1
 *   - slider_mode      : 'dynamic' | 'manual'
 *   - slider_click     : 'apply_filter' | 'navigate_only'
 *   - slider_drilldown : 0|1
 *   - slider_binding   : {type:'category|taxonomy|attribute', targets:string[], level:'1|2|3|all'}
 *   - slider_items     : array<int, {img,title,type,value}> (optionnel, si on ajoute l’UI plus tard)
 *
 * Item retourné :
 * [
 *   'img'   => 'https://…',         // URL image (peut être '')
 *   'title' => 'Parfums',           // Libellé visible
 *   'url'   => 'https://…',         // Lien (archives) — utile si navigate_only
 *   'meta'  => [
 *       'type'    => 'category|taxonomy|attribute|url',
 *       'ref'     => ['taxonomy'=>'product_cat','term_id'=>123], // pour tax/attr
 *       'click'   => 'apply_filter|navigate_only',
 *       'drill'   => 0|1
 *   ]
 * ]
 *
 * Règles dynamiques (MVP) :
 *  - Si binding.type = category :
 *       * targets vide :
 *           - sur archive product_cat → enfants du terme courant (jusqu’à level)
 *           - sinon → catégories racines (niveau 0)
 *       * targets fournis :
 *           - si valeurs = IDs/SLUGs de termes → retourner ces termes (ou leurs enfants selon level)
 *  - Si binding.type = taxonomy :
 *       * targets = slugs de taxonomies (ex: product_cat, brand, pa_marque)
 *       * pour chaque taxo ciblée → top termes les plus populaires (level=1 → parent=0)
 *  - Si binding.type = attribute :
 *       * targets = slugs ‘pa_*’ ; même logique que taxonomy
 */

namespace Sello\Domain;

use Sello\Data\Providers\ACFProvider;

defined('ABSPATH') || exit;

class Slider
{
    /**
     * Construit la liste des éléments du slider.
     *
     * @param int   $filter_id
     * @param int   $max  Nombre max d’items (def 12)
     * @return array<int,array>
     */
    public static function build($filter_id, $max = 12)
    {
        $filter_id = (int)$filter_id;
        $max       = max(1, (int)$max);

        $on = (int)get_post_meta($filter_id, 'slider_on', true);
        if ($on !== 1) {
            return array();
        }

        $mode   = get_post_meta($filter_id, 'slider_mode', true) ?: 'dynamic';
        $click  = get_post_meta($filter_id, 'slider_click', true) ?: 'apply_filter';
        $drill  = (int)(get_post_meta($filter_id, 'slider_drilldown', true) ?: 1);

        if ($mode === 'manual') {
            // Option future : items définis manuellement comme pour Pinned
            $items = get_post_meta($filter_id, 'slider_items', true);
            if (!is_array($items)) $items = array();
            return self::normalize_manual($items, $click, $drill, $max);
        }

        // Mode dynamique
        $binding = get_post_meta($filter_id, 'slider_binding', true);
        if (!is_array($binding)) {
            $binding = array('type'=>'category','targets'=>array(),'level'=>'1');
        }
        return self::build_dynamic($binding, $click, $drill, $max);
    }

    /* ============================================================
     * Manual
     * ========================================================== */
    private static function normalize_manual(array $items, $click, $drill, $max)
    {
        $out  = array();
        $prov = new ACFProvider();

        foreach ($items as $row) {
            if (count($out) >= $max) break;

            $type  = isset($row['type']) ? (string)$row['type'] : 'url';
            $title = isset($row['title']) ? (string)$row['title'] : '';
            $img   = isset($row['img'])   ? (string)$row['img']   : '';
            $value = isset($row['value']) ? (string)$row['value'] : '';

            $url = '';
            $metaRef = null;

            switch ($type) {
                case 'category':
                    $term = self::get_term_by_id_or_slug('product_cat', $value);
                    if ($term) {
                        $url = $prov->getTermLink($term);
                        if ($img === '') {
                            $img = $prov->getACFImageUrl('image', 'term_'.$term->term_id, 'thumbnail');
                            if ($img === '') {
                                $thumb_id = get_term_meta($term->term_id, 'thumbnail_id', true);
                                if ($thumb_id) {
                                    $src = wp_get_attachment_image_src((int)$thumb_id, 'thumbnail');
                                    if (is_array($src) && isset($src[0])) $img = (string)$src[0];
                                }
                            }
                        }
                        if ($title === '') $title = (string)$term->name;
                        $metaRef = array('taxonomy'=>'product_cat','term_id'=>(int)$term->term_id);
                    }
                    break;

                case 'taxonomy':
                case 'attribute':
                    list($tax, $termKey) = self::split_tax_value($value);
                    if ($tax !== '') {
                        $term = self::get_term_by_id_or_slug($tax, $termKey);
                        if ($term) {
                            $url = $prov->getTermLink($term);
                            if ($img === '') {
                                $img = $prov->getACFImageUrl('image', 'term_'.$term->term_id, 'thumbnail');
                            }
                            if ($title === '') $title = (string)$term->name;
                            $metaRef = array('taxonomy'=>$tax,'term_id'=>(int)$term->term_id);
                        }
                    }
                    break;

                default: // url
                    $url = esc_url_raw($value);
                    if ($title === '') $title = esc_html__('Open', 'sello');
                    break;
            }

            if ($url === '') continue;

            $out[] = array(
                'img'   => $img,
                'title' => $title,
                'url'   => $url,
                'meta'  => array('type'=>$type, 'ref'=>$metaRef, 'click'=>$click, 'drill'=>$drill),
            );
        }

        return $out;
    }

    /* ============================================================
     * Dynamic
     * ========================================================== */

    /**
     * Génération dynamique selon binding.
     * @param array  $binding  ['type','targets','level']
     * @param string $click    'apply_filter'|'navigate_only'
     * @param int    $drill    0|1
     * @param int    $max
     * @return array
     */
    private static function build_dynamic(array $binding, $click, $drill, $max)
    {
        $type    = isset($binding['type']) ? (string)$binding['type'] : 'category';
        $targets = isset($binding['targets']) && is_array($binding['targets']) ? $binding['targets'] : array();
        $level   = isset($binding['level']) ? (string)$binding['level'] : '1';
        if (!in_array($level, array('1','2','3','all'), true)) $level = '1';

        switch ($type) {
            case 'category':
                return self::build_for_category($targets, $level, $click, $drill, $max);

            case 'attribute':
                // targets = slugs 'pa_*'
                return self::build_for_taxonomies($targets, true, $click, $drill, $max);

            case 'taxonomy':
            default:
                // targets = slugs de taxonomies (ex: product_cat, brand, pa_marque)
                return self::build_for_taxonomies($targets, false, $click, $drill, $max);
        }
    }

    /** Slider dynamique pour catégories produits */
    private static function build_for_category(array $targets, $level, $click, $drill, $max)
    {
        $prov = new ACFProvider();
        $out  = array();
        $targets = array_values(array_filter(array_map('strval', $targets)));

        // Cas 1 : targets vide → dépend du contexte
        if (empty($targets)) {
            if (is_tax('product_cat')) {
                // Enfants du terme courant selon level
                $qo = get_queried_object();
                if ($qo && isset($qo->term_id)) {
                    $parent_id = (int)$qo->term_id;
                    $out = array_merge($out, self::collect_category_children($parent_id, $level, $max, $prov, $click, $drill));
                }
            } else {
                // Racines (niveau 0)
                $roots = get_terms(array(
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => false,
                    'parent'     => 0,
                    'number'     => $max,
                    'orderby'    => 'count',
                    'order'      => 'DESC',
                ));
                if (!is_wp_error($roots) && $roots) {
                    foreach ($roots as $t) {
                        if (count($out) >= $max) break;
                        $out[] = self::term_to_item($t, 'category', $prov, $click, $drill);
                    }
                }
            }
            return array_slice($out, 0, $max);
        }

        // Cas 2 : targets fournis (IDs/SLUGs de termes) → collecter ces termes (et/ou enfants selon level)
        $terms = array();
        foreach ($targets as $t) {
            $term = self::get_term_by_id_or_slug('product_cat', $t);
            if ($term) $terms[] = $term;
        }

        foreach ($terms as $term) {
            if (count($out) >= $max) break;

            // Ajouter le terme lui-même
            $out[] = self::term_to_item($term, 'category', $prov, $click, $drill);
            if (count($out) >= $max) break;

            // Puis ses enfants selon $level
            $children = self::collect_category_children((int)$term->term_id, $level, $max - count($out), $prov, $click, $drill);
            foreach ($children as $it) {
                if (count($out) >= $max) break;
                $out[] = $it;
            }
        }

        return array_slice($out, 0, $max);
    }

    /**
     * Collecte enfants pour une catégorie avec profondeur $level.
     * @return array<int,array>
     */
    private static function collect_category_children($parent_id, $level, $remaining, ACFProvider $prov, $click, $drill)
    {
        $items = array();
        $parent_id = (int)$parent_id;
        if ($parent_id <= 0 || $remaining <= 0) return $items;

        // Level = 1 : enfants directs
        $queue = array(array('id'=>$parent_id, 'depth'=>0));
        $maxDepth = ($level === 'all') ? PHP_INT_MAX : (int)$level;

        while (!empty($queue) && count($items) < $remaining) {
            $node = array_shift($queue);
            $depth = (int)$node['depth'];

            if ($depth >= $maxDepth) {
                continue;
            }

            $children = get_terms(array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'parent'     => (int)$node['id'],
                'number'     => $remaining - count($items),
                'orderby'    => 'name',
                'order'      => 'ASC',
            ));
            if (is_wp_error($children) || empty($children)) continue;

            foreach ($children as $t) {
                if (count($items) >= $remaining) break;
                $items[] = self::term_to_item($t, 'category', $prov, $click, $drill);
                // Enfiler pour profondeur suivante
                $queue[] = array('id'=>(int)$t->term_id, 'depth'=>$depth + 1);
            }
        }

        return $items;
    }

    /** Slider dynamique pour taxonomies diverses (y compris attributs) */
    private static function build_for_taxonomies(array $tax_slugs, $only_pa, $click, $drill, $max)
    {
        $prov = new ACFProvider();
        $out  = array();
        $tax_slugs = array_values(array_unique(array_map('strval', $tax_slugs)));

        // Si aucune taxo fournie → déduire du contexte (si on est déjà sur une taxonomie)
        if (empty($tax_slugs) && is_tax()) {
            $qo = get_queried_object();
            if ($qo && isset($qo->taxonomy)) {
                if (!$only_pa || strpos($qo->taxonomy, 'pa_') === 0) {
                    $tax_slugs[] = (string)$qo->taxonomy;
                }
            }
        }

        // Toujours un fallback sur product_cat si rien (et only_pa = false)
        if (empty($tax_slugs) && !$only_pa) {
            $tax_slugs[] = 'product_cat';
        }

        foreach ($tax_slugs as $tax) {
            if ($only_pa && strpos($tax, 'pa_') !== 0) {
                continue;
            }
            if (!taxonomy_exists($tax)) {
                continue;
            }

            // On prend les termes populaires de niveau 0 si hiérarchie (parent = 0)
            $args = array(
                'taxonomy'   => $tax,
                'hide_empty' => false,
                'number'     => max(1, $max - count($out)),
            );

            // Si la taxo est hiérarchique → privilégier parent=0
            $taxonomy = get_taxonomy($tax);
            if ($taxonomy && isset($taxonomy->hierarchical) && $taxonomy->hierarchical) {
                $args['parent']  = 0;
                $args['orderby'] = 'count';
                $args['order']   = 'DESC';
            } else {
                $args['orderby'] = 'count';
                $args['order']   = 'DESC';
            }

            $terms = get_terms($args);
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            foreach ($terms as $t) {
                if (count($out) >= $max) break;
                $type = (strpos($tax, 'pa_') === 0) ? 'attribute' : 'taxonomy';
                $out[] = self::term_to_item($t, $type, $prov, $click, $drill);
            }

            if (count($out) >= $max) break;
        }

        return array_slice($out, 0, $max);
    }

    /* ============================================================
     * Helpers
     * ========================================================== */

    /** Convertit un WP_Term en item slider */
    private static function term_to_item($term, $type, ACFProvider $prov, $click, $drill)
    {
        $img = $prov->getACFImageUrl('image', 'term_'.$term->term_id, 'thumbnail');

        // Fallback image Woo cat (thumbnail_id) uniquement pour product_cat
        if ($img === '' && $type === 'category') {
            $thumb_id = get_term_meta($term->term_id, 'thumbnail_id', true);
            if ($thumb_id) {
                $src = wp_get_attachment_image_src((int)$thumb_id, 'thumbnail');
                if (is_array($src) && isset($src[0])) $img = (string)$src[0];
            }
        }

        $url = $prov->getTermLink($term);

        return array(
            'img'   => $img,
            'title' => (string)$term->name,
            'url'   => $url,
            'meta'  => array(
                'type'  => $type, // category|taxonomy|attribute
                'ref'   => array('taxonomy'=>$term->taxonomy, 'term_id'=>(int)$term->term_id),
                'click' => $click,
                'drill' => (int)$drill,
            ),
        );
    }

    /** WP_Term par id ou slug */
    private static function get_term_by_id_or_slug($taxonomy, $id_or_slug)
    {
        $taxonomy = (string)$taxonomy;
        if ($taxonomy === '') return null;

        if (is_numeric($id_or_slug)) {
            $t = get_term((int)$id_or_slug, $taxonomy);
            return (!is_wp_error($t) && $t) ? $t : null;
        }

        $slug = sanitize_title((string)$id_or_slug);
        if ($slug === '') return null;

        $t = get_term_by('slug', $slug, $taxonomy);
        return ($t && !is_wp_error($t)) ? $t : null;
    }

    /** Décompose "pa_marque:dior" → ['pa_marque','dior'] */
    private static function split_tax_value($value)
    {
        $value = (string)$value;
        $pos = strpos($value, ':');
        if ($pos === false) {
            return array($value, '');
        }
        $tax  = substr($value, 0, $pos);
        $term = substr($value, $pos + 1);
        return array(trim($tax), trim($term));
    }
}
