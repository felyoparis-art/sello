<?php
/**
 * Services — Validator
 * Compat PHP 7.0+
 *
 * Utilitaires d’assainissement et de validation centralisés pour SELLO.
 * - Toutes les sauvegardes de métas passent par ici (Domain\*)
 * - Retourne des valeurs sûres et bornées (jamais d’exceptions)
 */

namespace Sello\Services;

defined('ABSPATH') || exit;

class Validator
{
    /* ============================================================
     * Types primitifs
     * ========================================================== */

    /**
     * Texte simple (1 ligne). Fallback si vide.
     * @param mixed  $v
     * @param string $fallback
     * @return string
     */
    public static function text($v, $fallback = '')
    {
        $v = is_scalar($v) ? (string)$v : '';
        $v = trim($v);
        if ($v === '') return (string)$fallback;
        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field($v);
        }
        return strip_tags($v);
    }

    /**
     * Entier borné avec défaut.
     * @param mixed $v
     * @param int   $min
     * @param int   $max
     * @param int   $def
     * @return int
     */
    public static function int($v, $min, $max, $def)
    {
        $n = is_numeric($v) ? (int)$v : (int)$def;
        if ($n < $min) $n = (int)$min;
        if ($n > $max) $n = (int)$max;
        return $n;
    }

    /**
     * Booléen normalisé (1|0).
     * @param mixed $v
     * @return int
     */
    public static function bool($v)
    {
        if (is_string($v)) {
            $v = strtolower(trim($v));
            if ($v === 'yes' || $v === 'on' || $v === 'true' || $v === '1') {
                return 1;
            }
        }
        return empty($v) ? 0 : 1;
    }

    /**
     * Choix dans un ensemble (fallback si invalide).
     * @param mixed  $v
     * @param array  $allowed
     * @param string $fallback
     * @return string
     */
    public static function pick($v, array $allowed, $fallback)
    {
        $v = is_scalar($v) ? (string)$v : '';
        return in_array($v, $allowed, true) ? $v : (string)$fallback;
    }

    /**
     * Transforme une liste CSV (ou par lignes) en array<string> nettoyé.
     * Accepte séparateurs: virgule, point-virgule, retour ligne.
     * @param mixed $csv
     * @return array<int,string>
     */
    public static function csvToArray($csv)
    {
        if (is_array($csv)) {
            $parts = $csv;
        } else {
            $s = is_scalar($csv) ? (string)$csv : '';
            $s = str_replace(array("\r\n", "\r"), "\n", $s);
            // Unifier ; en ,
            $s = str_replace(';', ',', $s);
            $s = str_replace("\n", ',', $s);
            $parts = explode(',', $s);
        }

        $out = array();
        foreach ($parts as $p) {
            $p = trim((string)$p);
            if ($p === '') continue;
            // Conserver ID ou slug (tolérer "taxonomy:slug")
            $out[] = $p;
        }
        // Uniques, valeurs string
        $out = array_values(array_unique(array_map('strval', $out)));
        return $out;
    }

    /* ============================================================
     * Facet
     * ========================================================== */

    /**
     * taxonomy|attribute|category|acf|meta
     */
    public static function facetSourceType($v)
    {
        return self::pick($v, array('taxonomy','attribute','category','acf','meta'), 'taxonomy');
    }

    /**
     * checkbox|radio|dropdown|list_v|list_h|buttons
     */
    public static function facetDisplay($v)
    {
        return self::pick($v, array('checkbox','radio','dropdown','list_v','list_h','buttons'), 'checkbox');
    }

    /**
     * single|multi
     */
    public static function facetSelectMode($v)
    {
        return self::pick($v, array('single','multi'), 'multi');
    }

    /**
     * 1|2|3|all
     */
    public static function level($v)
    {
        return self::pick($v, array('1','2','3','all'), 'all');
    }

    /* ============================================================
     * Filter (Scope / Design / Pinned / Slider)
     * ========================================================== */

    /**
     * category|taxonomy|attribute
     */
    public static function scopeType($v)
    {
        return self::pick($v, array('category','taxonomy','attribute'), 'category');
    }

    /**
     * sidebar_left|sidebar_right
     */
    public static function sidebarPosition($v)
    {
        return self::pick($v, array('sidebar_left','sidebar_right'), 'sidebar_right');
    }

    /**
     * dynamic|manual
     */
    public static function pinnedMode($v)
    {
        return self::pick($v, array('dynamic','manual'), 'dynamic');
    }

    /**
     * dynamic|manual
     */
    public static function sliderMode($v)
    {
        return self::pick($v, array('dynamic','manual'), 'dynamic');
    }

    /**
     * apply_filter|navigate_only
     */
    public static function sliderClick($v)
    {
        return self::pick($v, array('apply_filter','navigate_only'), 'apply_filter');
    }

    /**
     * Parse des lignes "pinned" collées en textarea.
     * Format par ligne : image_url | title | type | value
     *  - type ∈ {category, taxonomy, attribute, url}
     *  - value :
     *      * category : ID ou slug du terme product_cat
     *      * taxonomy/attribute : "taxonomy_slug:term_id_or_slug"
     *      * url : URL complète
     *
     * @param mixed $text
     * @return array<int,array{img:string,title:string,type:string,value:string}>
     */
    public static function parsePinnedLines($text)
    {
        $rows = array();
        $s = is_scalar($text) ? (string)$text : '';
        if ($s === '') return $rows;

        $s = str_replace(array("\r\n","\r"), "\n", $s);
        $lines = explode("\n", $s);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // Découper par '|'
            $parts = explode('|', $line);
            // Normaliser 4 champs
            $img   = isset($parts[0]) ? trim($parts[0]) : '';
            $title = isset($parts[1]) ? trim($parts[1]) : '';
            $type  = isset($parts[2]) ? trim(strtolower($parts[2])) : 'url';
            $value = isset($parts[3]) ? trim($parts[3]) : '';

            // Sanitize rapides
            $img   = esc_url_raw($img);
            $title = self::text($title);
            $type  = self::pick($type, array('category','taxonomy','attribute','url'), 'url');
            $value = self::text($value);

            // Si type=url => value doit être une URL valide (sinon ignorer plus tard)
            if ($type === 'url' && $value !== '') {
                $value = esc_url_raw($value);
            }

            $rows[] = array(
                'img'   => $img,
                'title' => $title,
                'type'  => $type,
                'value' => $value,
            );
        }

        return $rows;
    }
}
