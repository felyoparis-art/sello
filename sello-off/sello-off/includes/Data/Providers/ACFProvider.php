<?php
/**
 * Data Provider — ACFProvider
 * Compat PHP 7.0+
 *
 * Implémente ProviderInterface avec focus sur ACF si disponible.
 * - Hérite de WPProvider pour tout le reste.
 * - Tolérant : si ACF n'est pas installé/actif, retourne des valeurs vides.
 *
 * Bonus :
 * - getACFImageUrl() gère les 3 formats ACF (array / ID / URL) + fallback
 *   image des catégories produit WooCommerce (thumbnail_id) quand pertinent.
 */

namespace Sello\Data\Providers;

defined('ABSPATH') || exit;

class ACFProvider extends WPProvider implements ProviderInterface
{
    /* ============================================================
     * ACF access
     * ========================================================== */

    /** @inheritDoc */
    public function getACF($field_key, $object_id)
    {
        // ACF non présent → on ne casse rien
        if (!function_exists('get_field')) {
            return null;
        }

        $field_key = is_string($field_key) ? $field_key : '';
        if ($field_key === '') {
            return null;
        }

        // $object_id : int (post) ou "term_123" ou "option"…
        return get_field($field_key, $object_id);
    }

    /** @inheritDoc */
    public function getACFImageUrl($field_key, $object_id, $size = 'full')
    {
        // 1) Tenter via ACF (si présent)
        if (function_exists('get_field')) {
            $val = get_field($field_key, $object_id);
            $url = $this->resolveImageUrlFromMixed($val, $size);
            if ($url !== '') {
                return $url;
            }
        }

        // 2) Fallbacks : si $object_id est un terme (ex: "term_123"), vérifier une image méta
        if (is_string($object_id) && strpos($object_id, 'term_') === 0) {
            $term_id = (int)substr($object_id, 5);
            if ($term_id > 0) {
                // 2.1) Fallback générique : meta 'image' ou 'thumbnail_id'
                $custom = get_term_meta($term_id, 'image', true);
                $url = $this->resolveImageUrlFromMixed($custom, $size);
                if ($url !== '') {
                    return $url;
                }

                $thumb_id = get_term_meta($term_id, 'thumbnail_id', true);
                if ($thumb_id) {
                    $url = $this->resolveImageUrlFromMixed($thumb_id, $size);
                    if ($url !== '') {
                        return $url;
                    }
                }
            }
        }

        // 3) Fallback WooCommerce catégories (product_cat → thumbnail_id)
        //    Si $object_id est du type "term_123" ou si on a un objet terme en entrée.
        if (is_string($object_id) && strpos($object_id, 'term_') === 0) {
            $term_id = (int)substr($object_id, 5);
            if ($term_id > 0 && $this->taxonomyExists('product_cat')) {
                $thumb_id = get_term_meta($term_id, 'thumbnail_id', true);
                if ($thumb_id) {
                    $url = $this->resolveImageUrlFromMixed($thumb_id, $size);
                    if ($url !== '') {
                        return $url;
                    }
                }
            }
        }

        // Rien trouvé
        return '';
    }

    /* ============================================================
     * Helpers
     * ========================================================== */

    /**
     * Convertit une valeur ACF/Meta "image" en URL :
     * - Array ACF: ['url'] ou ['sizes'][$size] ou ['ID']
     * - ID attachement
     * - URL directe (string)
     * - Vide/Non convertible → ''
     *
     * @param mixed        $val
     * @param string|array $size
     * @return string
     */
    private function resolveImageUrlFromMixed($val, $size = 'full')
    {
        // Cas 1 : tableau ACF d'image
        if (is_array($val)) {
            if (!empty($val['sizes']) && is_array($val['sizes'])) {
                // $size peut être string (thumbnail) — sinon fallback url
                if (is_string($size) && isset($val['sizes'][$size])) {
                    return (string)$val['sizes'][$size];
                }
            }
            if (!empty($val['url'])) {
                return (string)$val['url'];
            }
            if (!empty($val['ID'])) {
                $src = wp_get_attachment_image_src((int)$val['ID'], $size);
                return (is_array($src) && isset($src[0])) ? (string)$src[0] : '';
            }
            return '';
        }

        // Cas 2 : ID d'attachement
        if (is_numeric($val)) {
            $src = wp_get_attachment_image_src((int)$val, $size);
            return (is_array($src) && isset($src[0])) ? (string)$src[0] : '';
        }

        // Cas 3 : URL directe
        if (is_string($val) && $val !== '') {
            $u = esc_url_raw($val);
            return is_string($u) ? $u : '';
        }

        return '';
    }
}
