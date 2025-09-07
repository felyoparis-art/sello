<?php
/**
 * Capabilities & roles for SELLO (compat PHP 7.0+)
 */
namespace Sello\Core;

defined('ABSPATH') || exit;

class Capabilities
{
    /** Unique capability used to acc��der au menu/��crans SELLO */
    const CAP = 'manage_sello';

    /**
     * Ajoute la capacit�� aux r�0�0les pertinents (idempotent).
     * Appel�� �� chaque admin_init (l��ger) + �� l��activation du plugin.
     */
    public static function ensure_caps()
    {
        // R�0�0les cibles : administrateur (core), shop_manager (Woo), editor (optionnel)
        $target_roles = array('administrator', 'shop_manager', 'editor');

        foreach ($target_roles as $role_slug) {
            $role = get_role($role_slug);
            if ($role && !$role->has_cap(self::CAP)) {
                $role->add_cap(self::CAP);
            }
        }
    }

    /**
     * Retire proprement la capacit�� (appel manuel si besoin).
     * Note: on ne supprime PAS automatiquement �� la d��sactivation, pour ��viter
     * de retirer une permission volontairement accord��e par un admin.
     */
    public static function remove_caps()
    {
        $target_roles = array('administrator', 'shop_manager', 'editor');
        foreach ($target_roles as $role_slug) {
            $role = get_role($role_slug);
            if ($role && $role->has_cap(self::CAP)) {
                $role->remove_cap(self::CAP);
            }
        }
    }

    /** Raccourci: v��rifie si l��utilisateur courant peut g��rer SELLO */
    public static function user_can_manage()
    {
        return current_user_can(self::CAP);
    }
}
