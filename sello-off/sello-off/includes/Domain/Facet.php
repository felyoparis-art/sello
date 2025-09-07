<?php
/**
 * Domain — Facet (lecture & normalisation des métas Facette)
 * Compat PHP 7.0+
 *
 * Objectif :
 *  - Centraliser la lecture d’une facette (post type: sello_facet)
 *  - Normaliser sa configuration pour le rendu et l’admin
 *  - Exposer un helper pour proposer une liste d’options (termes) limitée
 *
 * Métas attendues (synchronisées avec l’UI Admin Facets) :
 *  - title              : string
 *  - description        : string
 *  - source_type        : 'taxonomy'|'attribute'|'category'|'acf'|'meta'   (def: taxonomy)
 *  - source_key         : string   (ex: 'product_cat', 'pa_marque', meta_key…)
 *  - hierarchic         : 0|1      (pertinent surtout pour category/product_cat)
 *  - level              : '1'|'2'|'3'|'all'
 *  - include            : array<string> (ids/slugs)
 *  - exclude            : array<string> (ids/slugs)
 *  - display            : 'checkbox'|'radio'|'dropdown'|'list_v'|'list_h'|'buttons'
 *  - select_mode        : 'single'|'multi'
 *  - columns            : 1|2
 *  - searchbar          : 0|1
 *  - max_visible        : int (def: 5)  — nombre d’options visibles avant "Voir plus"
 *  - show_counts        : 0|1
 *
 * Remarques :
 *  - Le helper build_items() ne fait que des propositions "top" (termes les plus utilisés),
 *    ou enfants d’un terme si hierarchic+level ⇒ selon la source_key.
 *  - Le calcul des "counts" respecte le contexte courant via Services\Counters.
 */

namespace Sello\Domain;

use Sello\Core\ServiceContainer;
use Sello\Services\Validator;
use Sello\Services\Counters;
use Sello\Data\Providers\ProviderInterface;

defined('ABSPATH') || exit;

class Facet
{
    /** @var int */
    private $id = 0;

    /** @var array<string,mixed> */
    private $cfg = array();

    /** Charger et normaliser une facette */
    public static function load($facet_id)
    {
        $o = new self();
        $o->id  = (int)$facet_id;
        $o->cfg = $o->read_and_normalize($o->id);
        return $o;
    }

    /** Retourne l’ID de la facette */
    public function get_id()
    {
        return $this->id;
    }

    /** Configuration normalisée prête à consommer */
    public function to_array()
    {
        return $this->cfg;
    }

    /**
     * Construit une liste d’options (termes) pour cette facette.
     * - Par défaut limitée à max_visible (5)
     * - Ajoute 'count' si show_counts=1
     *
     * @param int|null $limit  Nombre max d’items (def: meta max_visible)
     * @return array<int,array{term_id:int,slug:string,name:string,count?:int}>
     */
    public function build_items($limit = null)
    {
        $cfg = $this->cfg;
        $provider = ServiceContainer::getProvider();
        $counters = ServiceContainer::getCounters();

        $limit = is_null($limit) ? (int)$cfg['max_visible'] : (int)$limit;
        if ($limit <= 0) $limit = 5;

        $items = array();

        // Seules les sources basées sur taxonomies sont gérées en MVP
        $source_type = (string)$cfg['source_type'];
        $tax = '';
        if ($source_type === 'category') {
            $tax = 'product_cat';
        } elseif ($source_type === 'attribute' || $source_type === 'taxonomy') {
            $tax = (string)$cfg['source_key'];
        }

        if ($tax !== '' && $provider->taxonomyExists($tax)) {
            $items = $this->collect_terms($provider, $tax, $cfg, $limit);
        }

        // Ajouter les counts si demandé
        if (!empty($items) && (int)$cfg['show_counts'] === 1 && $tax !== '') {
            $ids = array();
            foreach ($items as $it) {
                $ids[] = (string)$it['term_id'];
            }
            $counts = $counters->counts_for_terms($tax, $ids, array(
                'respect_context' => true,
                'exclude_self'    => true,
                'max_terms'       => max(50, $limit),
                'ttl'             => 300,
            ));
            foreach ($items as &$it) {
                $tid = (int)$it['term_id'];
                $it['count'] = isset($counts[$tid]) ? (int)$counts[$tid] : 0;
            }
            unset($it);
        }

        return $items;
    }

    /* ============================================================
     * Lecture & normalisation
     * ========================================================== */

    private function read_and_normalize($facet_id)
    {
        $v = new Validator();

        $title       = get_post_meta($facet_id, 'title', true);
        $description = get_post_meta($facet_id, 'description', true);
        $source_type = get_post_meta($facet_id, 'source_type', true);
        $source_key  = get_post_meta($facet_id, 'source_key', true);
        $hierarchic  = get_post_meta($facet_id, 'hierarchic', true);
        $level       = get_post_meta($facet_id, 'level', true);
        $include     = get_post_meta($facet_id, 'include', true);
        $exclude     = get_post_meta($facet_id, 'exclude', true);
        $display     = get_post_meta($facet_id, 'display', true);
        $select_mode = get_post_meta($facet_id, 'select_mode', true);
        $columns     = get_post_meta($facet_id, 'columns', true);
        $searchbar   = get_post_meta($facet_id, 'searchbar', true);
        $max_visible = get_post_meta($facet_id, 'max_visible', true);
        $show_counts = get_post_meta($facet_id, 'show_counts', true);

        // Normalisation via Services\Validator
        $cfg = array();
        $cfg['title']        = Validator::text($title, '');
        $cfg['description']  = Validator::text($description, '');

        $cfg['source_type']  = Validator::facetSourceType($source_type ?: 'taxonomy');
        $cfg['source_key']   = Validator::text($source_key, '');

        $cfg['hierarchic']   = Validator::bool($hierarchic);
        $cfg['level']        = Validator::level($level ?: 'all');

        $cfg['include']      = is_array($include) ? array_values(array_filter(array_map('strval', $include))) : array();
        $cfg['exclude']      = is_array($exclude) ? array_values(array_filter(array_map('strval', $exclude))) : array();

        $cfg['display']      = Validator::facetDisplay($display ?: 'checkbox');
        $cfg['select_mode']  = Validator::facetSelectMode($select_mode ?: 'multi');

        $columns             = is_numeric($columns) ? (int)$columns : 1;
        $cfg['columns']      = ($columns === 2) ? 2 : 1;

        $cfg['searchbar']    = Validator::bool($searchbar);
        $cfg['max_visible']  = Validator::int($max_visible, 3, 50, 5);
        $cfg['show_counts']  = Validator::bool($show_counts);

        return $cfg;
    }

    /* ============================================================
     * Collecte des termes selon la source
     * ========================================================== */

    /**
     * @param ProviderInterface $provider
     * @param string            $tax
     * @param array             $cfg
     * @param int               $limit
     * @return array<int,array>
     */
    private function collect_terms(ProviderInterface $provider, $tax, array $cfg, $limit)
    {
        $out = array();

        $include = $cfg['include'];
        $exclude = $cfg['exclude'];

        // 1) Si include non vide → respecter l’ordre demandé autant que possible
        if (!empty($include)) {
            foreach ($include as $v) {
                $term = $provider->getTerm($tax, $v);
                if (!$term) continue;
                if ($this->is_excluded($term, $exclude)) continue;

                $out[] = array(
                    'term_id' => (int)$term->term_id,
                    'slug'    => (string)$term->slug,
                    'name'    => (string)$term->name,
                );
                if (count($out) >= $limit) break;
            }

            return $out;
        }

        // 2) Si hierarchic=1 + category/product_cat : récupérer enfants/descendants
        if ((int)$cfg['hierarchic'] === 1 && $tax === 'product_cat') {
            // Déterminer le(s) point(s) de départ :
            //  - si include vide → racines (parent=0)
            //  - sinon chaque cible de include (déjà géré plus haut)
            $args = array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'parent'     => 0,
                'number'     => $limit,
                'orderby'    => 'name',
                'order'      => 'ASC',
            );
            $roots = $provider->getTerms($args);

            foreach ($roots as $t) {
                if (count($out) >= $limit) break;
                if ($this->is_excluded($t, $exclude)) continue;

                $out[] = array(
                    'term_id' => (int)$t->term_id,
                    'slug'    => (string)$t->slug,
                    'name'    => (string)$t->name,
                );

                // Si level >1 → empiler enfants (simple BFS limité)
                $rem = $limit - count($out);
                if ($rem <= 0) break;

                $children = $this->collect_children_bfs($provider, (int)$t->term_id, 'product_cat', $cfg['level'], $rem, $exclude);
                foreach ($children as $it) {
                    if (count($out) >= $limit) break;
                    $out[] = $it;
                }
            }

            return $out;
        }

        // 3) Fallback : top termes par "count" (termes populaires), parent=0 si hiérarchique
        $tax_obj = function_exists('get_taxonomy') ? get_taxonomy($tax) : null;
        $args = array(
            'taxonomy'   => $tax,
            'hide_empty' => false,
            'number'     => $limit * 2, // sur-prélever pour filtrage exclude
            'orderby'    => 'count',
            'order'      => 'DESC',
        );
        if ($tax_obj && !empty($tax_obj->hierarchical)) {
            $args['parent'] = 0;
        }

        $terms = $provider->getTerms($args);
        foreach ($terms as $t) {
            if (count($out) >= $limit) break;
            if ($this->is_excluded($t, $exclude)) continue;

            $out[] = array(
                'term_id' => (int)$t->term_id,
                'slug'    => (string)$t->slug,
                'name'    => (string)$t->name,
            );
        }

        return $out;
    }

    /**
     * BFS des enfants jusqu’à un niveau, limité en nombre.
     *
     * @param ProviderInterface $provider
     * @param int               $parent_id
     * @param string            $taxonomy
     * @param string            $level   '1'|'2'|'3'|'all'
     * @param int               $remaining
     * @param array<string>     $exclude
     * @return array<int,array{term_id:int,slug:string,name:string}>
     */
    private function collect_children_bfs(ProviderInterface $provider, $parent_id, $taxonomy, $level, $remaining, array $exclude)
    {
        $items = array();
        $maxDepth = ($level === 'all') ? 99 : (int)$level;

        $queue = array(array('id' => (int)$parent_id, 'depth' => 0));
        while (!empty($queue) && count($items) < $remaining) {
            $node = array_shift($queue);
            $depth = (int)$node['depth'];
            if ($depth >= $maxDepth) continue;

            $children = $provider->getTerms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'parent'     => (int)$node['id'],
                'number'     => $remaining - count($items),
                'orderby'    => 'name',
                'order'      => 'ASC',
            ));
            if (!is_array($children) || empty($children)) continue;

            foreach ($children as $t) {
                if (count($items) >= $remaining) break;
                if ($this->is_excluded($t, $exclude)) continue;

                $items[] = array(
                    'term_id' => (int)$t->term_id,
                    'slug'    => (string)$t->slug,
                    'name'    => (string)$t->name,
                );

                $queue[] = array('id' => (int)$t->term_id, 'depth' => $depth + 1);
            }
        }

        return $items;
    }

    /**
     * Détermine si un terme est exclu par une liste include/exclude (slug/id).
     * @param object $term
     * @param array<string> $exclude
     * @return bool
     */
    private function is_excluded($term, array $exclude)
    {
        if (empty($exclude)) return false;

        $tid  = (string)(isset($term->term_id) ? (int)$term->term_id : 0);
        $slug = (string)(isset($term->slug) ? $term->slug : '');

        foreach ($exclude as $ex) {
            $ex = (string)$ex;
            if ($ex === $tid || $ex === $slug) {
                return true;
            }
        }
        return false;
    }
}
