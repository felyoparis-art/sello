<?php
/**
 * Domain — DeviceVisibility (desktop / tablet / mobile)
 * Compat PHP 7.0+
 *
 * Objectif MVP :
 *  - Centraliser la logique "ce Filter doit-il s'afficher sur cet appareil ?"
 *  - Lire des métas simples au niveau du Filter (vis_desktop/vis_tablet/vis_mobile)
 *  - Offrir des helpers pour produire des data-attributes utilisables en JS/CSS
 *
 * Notes :
 *  - Les métas ne sont pas encore exposés dans l'UI (Admin). Ils sont tolérés si présents.
 *    Valeurs : 1 = visible, 0 = caché. Défaut = 1.
 *  - Détection device : côté PHP on reste approximatif.
 *      * mobile : wp_is_mobile()
 *      * tablet : non détectable précisément côté serveur → on bascule via
 *                 un paramètre de debug "?sello_device=tablet" pour forcer.
 *      * desktop : fallback si pas "mobile" ni "tablet"
 *  - Côté front, vous pouvez compléter par CSS/JS réactif selon les breakpoints
 *    fournis dans Design::get_global() (bp_tablet, bp_mobile).
 */

namespace Sello\Domain;

defined('ABSPATH') || exit;

class DeviceVisibility
{
    const DESKTOP = 'desktop';
    const TABLET  = 'tablet';
    const MOBILE  = 'mobile';

    /**
     * Retourne la visibilité déclarée pour un Filter.
     * Métas tolérés (int 0|1) : vis_desktop, vis_tablet, vis_mobile
     * Défaults : 1
     *
     * @param int $filter_id
     * @return array{desktop:int,tablet:int,mobile:int}
     */
    public static function get_for_filter($filter_id)
    {
        $filter_id = (int)$filter_id;

        $d = (int) get_post_meta($filter_id, 'vis_desktop', true);
        $t = (int) get_post_meta($filter_id, 'vis_tablet',  true);
        $m = (int) get_post_meta($filter_id, 'vis_mobile',  true);

        // Défauts à 1 si meta absente
        if ($d !== 0 && $d !== 1) $d = 1;
        if ($t !== 0 && $t !== 1) $t = 1;
        if ($m !== 0 && $m !== 1) $m = 1;

        return array('desktop'=>$d, 'tablet'=>$t, 'mobile'=>$m);
    }

    /**
     * Détermine l'appareil courant (approximation serveur).
     * - Surchargable via ?sello_device=desktop|tablet|mobile (debug)
     *
     * @return string one of self::DESKTOP|TABLET|MOBILE
     */
    public static function current_device()
    {
        // Override de debug (utile dans l'admin, builders, etc.)
        if (isset($_GET['sello_device'])) {
            $force = strtolower((string)$_GET['sello_device']);
            if (in_array($force, array(self::DESKTOP,self::TABLET,self::MOBILE), true)) {
                return $force;
            }
        }

        // Détection serveur basique
        if (function_exists('wp_is_mobile') && wp_is_mobile()) {
            // Impossible de différencier mobile vs tablette de façon fiable côté serveur,
            // on expose "mobile" et on laisse le front affiner si besoin.
            return self::MOBILE;
        }

        return self::DESKTOP;
    }

    /**
     * Indique si ce Filter est autorisé à s'afficher sur l'appareil courant.
     *
     * @param int $filter_id
     * @return bool
     */
    public static function is_allowed($filter_id)
    {
        $vis = self::get_for_filter($filter_id);
        $dev = self::current_device();

        switch ($dev) {
            case self::MOBILE:  return (int)$vis['mobile']  === 1;
            case self::TABLET:  return (int)$vis['tablet']  === 1;
            case self::DESKTOP:
            default:            return (int)$vis['desktop'] === 1;
        }
    }

    /**
     * Data-attributes utiles pour le rendu front (permettent aux scripts
     * d'appliquer des comportements réactifs selon les breakpoints).
     *
     * Exemple :
     *   echo '<div class="sello-filter" '.DeviceVisibility::data_attributes($id).'></div>';
     *
     * @param int $filter_id
     * @return string attributs HTML
     */
    public static function data_attributes($filter_id)
    {
        $v = self::get_for_filter($filter_id);
        $attrs = array(
            'data-vis-desktop' => (int)$v['desktop'],
            'data-vis-tablet'  => (int)$v['tablet'],
            'data-vis-mobile'  => (int)$v['mobile'],
        );

        $out = '';
        foreach ($attrs as $k=>$val) {
            $out .= $k.'="'.(int)$val.'" ';
        }
        return trim($out);
    }
}
