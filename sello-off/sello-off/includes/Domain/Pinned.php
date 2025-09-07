<?php
/**
 * Domain — Pinned (You-may-also-like area)
 * Compat PHP 7.0+
 *
 * Construit la liste d’éléments "pinned" pour un Filter donné.
 * - Mode "manual" : lit les lignes déjà parsées et stockées en meta 'pinned_items'
 * - Mode "dynamic": déduit une liste contextuelle en fonction du scope/terme courant
 *
 * Structure d’un item retourné :
 * [
 *   'img'   => 'https://…',         // URL image (peut être '')
 *   'title' => 'Dior',              // Titre court
 *   'url'   => 'https://…',         // Lien cliquable
 *   'meta'  => [ 'type'=>'category|taxonomy|attribute|url', 'ref'=> … ] // info technique
 * ]
 *
 * Notes :
 * - L’image tentera un fallback via ACF (image de terme) ou thumbnail_id des catégories.
 * - Limite par défaut : 8 items (surchargable via $max).
 */
namespace Sello\Domain;

use Sello\Data\Providers\ACFProvider;

defined('ABSPATH') || exit;

class Pinned
{
    /**
     * Construit la liste des éléments "pinned" prêts à rendre.
     *
     * @param int  $filter_id
     * @param int  $max        Nombre max d’items (def 8)
     * @return array<int,array>
     */
    public static function build($filter_id, $max = 8)
    {
        $filter_id = (int)$filter_id;
        $max       = max(1, (int)$max);

        $enabled = (int) get_post_meta($filter_id, 'pinned_on', true);
        if ($enabled !== 1) {
            return array();
        }

        $mode = get_post_meta($filter_id, 'pinned_mode', true) ?: 'dynamic';

        if ($mode === 'manual') {
            $items = get_post_meta($filter_id, 'pinned_items', true);
            if (!is_array($items)) $items = array();
            $out = self::normalize_manual_items($items, $max);
            return $out;
        }

        // Dynamic
        return self::build_dynamic($filter_id, $max);
    }

    /* ============================================================
     * Manual
     * ========================================================== */

    /**
     * Normalise les items manuels (issus de Validator::parsePinnedLines)
     *
     * @param array $items
     * @param int   $max
     * @return array
     */
    private static function normalize_manual_items(array $items, $max)
    {
        $out = array();
        $prov = new ACFProvider();

        foreach ($items as $row) {
            if (count($out) >= $max) break;

            $type  = isset($row['type']) ? (string)$row['type'] : 'url';
            $title = isset($row['title']) ? (string)$row['title'] : '';
            $img   = isset($row['img']) ? (string)$row['img'] : '';
            $value = isset($row['value']) ? (string)$row['value'] : '';

            $url = '';
            $metaRef = null;

            switch ($type) {
                case 'category':
                    // $value peut être ID ou slug
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
                        $metaRef = array('taxonomy'=>'product_cat', 'term_id'=>(int)$term->term_id);
                    }
                    break;

                case 'taxonomy':
                case 'attribute':
                    // value attendu: "{taxonomy_slug}:{term_id_or_slug}" ex "pa_marque:dior"
                    list($tax, $termKey) = self::split_tax_value($value);
                    if ($tax !== '') {
                        $term = self::get_term_by_id_or_slug($tax, $termKey);
                        if ($term) {
                            $url = $prov->getTermLink($term);
                            if ($img === '') {
                                $img = $prov->getACFImageUrl('image', 'term_'.$term->term_id, 'thumbnail');
                            }
                            if ($title === '') $title = (string)$term->name;
                            $metaRef = array('taxonomy'=>$tax, 'term_id'=>(int)$term->term_id);
                        }
                    }
                    break;

                default: // url
                    $url = esc_url_raw($value);
                    if ($title === '') $title = esc_html__('Open', 'sello');
                    break;
            }

            if ($url === '') {
                // On ignore les lignes invalides
                continue;
            }

            $out[] = array(
                'img'   => $img,
                'title' => $title,
                'url'   => $url,
                'meta'  => array('type'=>$type, 'ref'=>$metaRef),
            );
        }

        return $out;
    }

    /* ============================================================
     * Dynamic
     * ========================================================== */

    /**
     * Génère des items dynamiques en fonction du contexte/scope du Filter.
     * Règles MVP :
     *  - Si on est sur une archive product_cat → proposer ses sous-catégories
     *  - Sinon, si archive d’attribut (pa_*) → proposer des termes frères (top)
     *  - Sinon → top catégories produits (niveau 0)
     *
     * @param int $filter_id
     * @param int $max
     * @return array
     */
    private static function build_dynamic($filter_id, $max)
    {
        $prov = new ACFProvider();
        $out  = array();

        // Contexte courant (si archive)
        $qo = is_tax() ? get_queried_object() : null;
        $is_tax = ($qo && isset($qo->taxonomy));

        if ($is_tax && $qo->taxonomy === 'product_cat') {
            // Sous-catégories du terme courant
            $children = get_terms(array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'parent'     => (int)$qo->term_id,
                'number'     => $max,
            ));
            if (!is_wp_error($children) && $children) {
                foreach ($children as $t) {
                    if (count($out) >= $max) break;
                    $url = $prov->getTermLink($t);
                    $img = $prov->getACFImageUrl('image', 'term_'.$t->term_id, 'thumbnail');
                    if ($img === '') {
                        $thumb_id = get_term_meta($t->term_id, 'thumbnail_id', true);
                        if ($thumb_id) {
                            $src = wp_get_attachment_image_src((int)$thumb_id, 'thumbnail');
                            if (is_array($src) && isset($src[0])) $img = (string)$src[0];
                        }
                    }
                    $out[] = array(
                        'img'   => $img,
                        'title' => (string)$t->name,
                        'url'   => $url,
                        'meta'  => array('type'=>'category', 'ref'=>array('taxonomy'=>'product_cat','term_id'=>(int)$t->term_id)),
                    );
                }
            }
        } elseif ($is_tax && strpos($qo->taxonomy, 'pa_') === 0) {
            // Attribut : proposer quelques valeurs populaires (sœurs du parent si hiérarchie)
            $siblings = get_terms(array(
                'taxonomy'   => $qo->taxonomy,
                'hide_empty' => false,
                'number'     => $max,
                'orderby'    => 'count',
                'order'      => 'DESC',
            ));
            if (!is_wp_error($siblings) && $siblings) {
                foreach ($siblings as $t) {
                    if (count($out) >= $max) break;
                    $url = $prov->getTermLink($t);
                    $img = $prov->getACFImageUrl('image', 'term_'.$t->term_id, 'thumbnail');
                    $out[] = array(
                        'img'   => $img,
                        'title' => (string)$t->name,
                        'url'   => $url,
                        'meta'  => array('type'=>'attribute', 'ref'=>array('taxonomy'=>$qo->taxonomy,'term_id'=>(int)$t->term_id)),
                    );
                }
            }
        } else {
            // Fallback : top catégories racine
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
                    $url = $prov->getTermLink($t);
                    $img = $prov->getACFImageUrl('image', 'term_'.$t->term_id, 'thumbnail');
                    if ($img === '') {
                        $thumb_id = get_term_meta($t->term_id, 'thumbnail_id', true);
                        if ($thumb_id) {
                            $src = wp_get_attachment_image_src((int)$thumb_id, 'thumbnail');
                            if (is_array($src) && isset($src[0])) $img = (string)$src[0];
                        }
                    }
                    $out[] = array(
                        'img'   => $img,
                        'title' => (string)$t->name,
                        'url'   => $url,
                        'meta'  => array('type'=>'category', 'ref'=>array('taxonomy'=>'product_cat','term_id'=>(int)$t->term_id)),
                    );
                }
            }
        }

        // Si rien trouvé (sites vides), retourner tableau vide
        return array_slice($out, 0, $max);
    }

    /* ============================================================
     * Helpers
     * ========================================================== */

    /** Retourne WP_Term par id ou slug, ou null */
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

    /**
     * Décompose "pa_marque:dior" → ['pa_marque','dior']
     * Si pas de ":", renvoie [$value,''] (le terme devra être résolu autrement).
     */
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
