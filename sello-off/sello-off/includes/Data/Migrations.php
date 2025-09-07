<?php
/**
 * Migrations & Install Routines for SELLO
 * Compat PHP 7.0+
 *
 * - Création des capacités (manage_sello) pour les rôles admin/editor (optionnel)
 * - Valeurs par défaut des options (Design, Groups)
 * - Gestion de version "DB" du plugin pour upgrades futurs
 * - Flush des permaliens (CPT déjà enregistrés par Plugin::boot())
 */
namespace Sello\Data;

use Sello\Admin\DesignPage;
use Sello\Admin\GroupsPage;

defined('ABSPATH') || exit;

class Migrations
{
    /** Option qui stocke la version "DB" */
    const OPT_DBV = 'sello_db_version';

    /** Version courante du schéma (incrementer si on change la structure/meta) */
    const DBV = 1;

    /**
     * Appelé à l’activation du plugin (depuis Core\Plugin::activate()).
     * Idempotent : peut être rappelé sans risque.
     */
    public static function install()
    {
        // 1) Capabilities
        self::ensure_caps();

        // 2) Options par défaut
        self::ensure_default_options();

        // 3) Versioning
        $current = (int) get_option(self::OPT_DBV, 0);
        if ($current < self::DBV) {
            self::upgrade($current, self::DBV);
            update_option(self::OPT_DBV, self::DBV, true);
        }

        // 4) Flush permalinks (CPT doivent être enregistrés avant)
        if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
            flush_rewrite_rules(false);
        }
    }

    /**
     * Capacité custom "manage_sello" utilisée par l’admin.
     * On l’ajoute aux rôles "administrator". (Optionnel: editor)
     */
    private static function ensure_caps()
    {
        // Admin
        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap('manage_sello')) {
            $admin->add_cap('manage_sello');
        }

        // Facultatif : donner accès aux éditeurs
        $editor = get_role('editor');
        if ($editor && !$editor->has_cap('manage_sello')) {
            $editor->add_cap('manage_sello');
        }
    }

    /**
     * Crée les options globales si absentes, avec des valeurs par défaut.
     */
    private static function ensure_default_options()
    {
        // Design global (sidebar)
        $design_defaults = array(
            'desktop_pos'    => 'right',
            'tablet_pos'     => 'right',
            'mobile_pos'     => 'left',
            'anim'           => 'slide',
            'overlay_mobile' => 1,
            'close_outside'  => 1,
            'bp_tablet'      => 1024,
            'bp_mobile'      => 767,
            'w_total'        => 700,
            'w_facets'       => 500,
            'w_pinned'       => 200,
        );
        $design = get_option(DesignPage::OPTION_KEY, null);
        if (!is_array($design)) {
            update_option(DesignPage::OPTION_KEY, $design_defaults, true);
        }

        // Groups (vides)
        $groups_defaults = array(
            'category'  => array(),
            'taxonomy'  => array(),
            'attribute' => array(),
        );
        $groups = get_option(GroupsPage::OPTION_KEY, null);
        if (!is_array($groups)) {
            update_option(GroupsPage::OPTION_KEY, $groups_defaults, true);
        }
    }

    /**
     * Routine d’upgrade si on change la structure interne.
     *
     * @param int $from
     * @param int $to
     * @return void
     */
    private static function upgrade($from, $to)
    {
        // Placeholders d’exemples — ajouter des cases si DBV++ plus tard
        // switch (true) {
        //     case ($from < 2 && $to >= 2):
        //         // Exemple: migrer des meta keys
        //         self::migrate_v2();
        //         break;
        // }
    }

    /* ========= Exemples de migrations futures ========= */

    /**
     * Exemple de migration (non utilisée): renommer des meta-keys.
     * Laisser ici en référence pour DBV prochaine.
     */
    private static function migrate_v2()
    {
        // Exemple : renommer design_position → design_pos (fictif)
        $posts = get_posts(array(
            'post_type'              => array('sello_filter','sello_facet','sello_preset'),
            'post_status'            => array('publish','draft','pending','private'),
            'numberposts'            => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        foreach ($posts as $pid) {
            $old = get_post_meta($pid, 'design_position', true);
            if ($old !== '') {
                update_post_meta($pid, 'design_pos', $old);
                delete_post_meta($pid, 'design_position');
            }
        }
    }
}
