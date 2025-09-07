<?php
namespace Sello\Admin;
defined('ABSPATH') || exit;

class MenuCleanup {

    /** Supprime tous les sous-menus "taxonomies produit" (sauf catégories & étiquettes) */
    public static function hide_product_tax_submenus(): void {
        $parent = 'edit.php?post_type=product';
        $taxos = get_object_taxonomies('product','objects');
        if (!$taxos) return;

        foreach ($taxos as $t) {
            // on conserve seulement les 2 natifs
            if (in_array($t->name, ['product_cat','product_tag'], true)) continue;
            remove_submenu_page($parent, 'edit-tags.php?taxonomy='.$t->name.'&post_type=product');
        }
    }

    /** Replace l'ordre pour avoir le bloc SELLO compact sous Produits */
    public static function group_sello_block(): void {
        global $submenu;
        $parent = 'edit.php?post_type=product';
        if (empty($submenu[$parent])) return;

        $order = [
            'sello-hub',                 // SELLO (page hub)
            'sello-dashboard',           // Dashboard
            'edit.php?post_type=sello_facet',
            'edit.php?post_type=sello_preset',
            'edit.php?post_type=sello_filter',
            'sello-design',
            'sello-groups',
            'sello-settings',
        ];

        $items = $submenu[$parent];
        $block = [];
        $others = [];

        foreach ($items as $it) {
            $slug = isset($it[2]) ? $it[2] : '';
            if (in_array($slug, $order, true)) {
                $block[$slug] = $it;
            } else {
                $others[] = $it;
            }
        }

        // Ré-ordonne notre bloc selon $order
        $ordered_block = [];
        foreach ($order as $slug) {
            if (isset($block[$slug])) $ordered_block[] = $block[$slug];
        }

        // Conserver "Tous les produits" / "Ajouter un produit" en tête si présents
        $fixed = [];
        foreach ($others as $k=>$it) {
            $slug = $it[2] ?? '';
            if ($slug === 'edit.php?post_type=product' || $slug === 'post-new.php?post_type=product') {
                $fixed[] = $it;
                unset($others[$k]);
            }
        }

        // Nouvelle liste : fixes (produits) + bloc SELLO + le reste
        $submenu[$parent] = array_merge($fixed, $ordered_block, array_values($others));
    }
}
