<?php
/**
 * Capabilities & roles for SELLO (compat PHP 7.0+)
 */
namespace Sello\Core;

defined('ABSPATH') || exit;

class Capabilities
{
    /** Unique capability used to accéder au menu/écrans SELLO */
    const CAP = 'manage_sello';

    /**
     * Ajoute la capacité aux rôles pertinents (idempotent).
     * Appelé à chaque admin_init (léger) + à l’activation du plugin.
     */
    public static function ensure_caps()
    {
        // Rôles cibles : administrateur (core), shop_manager (Woo), editor (optionnel)
        $target_roles = array('administrator', 'shop_manager', 'editor');

        foreach ($target_roles as $role_slug) {
            $role = get_role($role_slug);
            if ($role && !$role->has_cap(self::CAP)) {
                $role->add_cap(self::CAP);
            }
        }
    }

    /**
     * Retire proprement la capacité (appel manuel si besoin).
     * Note: on ne supprime PAS automatiquement à la désactivation, pour éviter
     * de retirer une permission volontairement accordée par un admin.
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

    /** Raccourci: vérifie si l’utilisateur courant peut gérer SELLO */
    public static function user_can_manage()
    {
        return current_user_can(self::CAP);
    }
}
