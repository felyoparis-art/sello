<?php
/**
 * Data Providers — Interface
 * Compat PHP 7.0+
 *
 * But :
 *  Unifier l’accès aux données WordPress / WooCommerce / ACF pour SELLO,
 *  afin que le reste du plugin ne dépende pas directement d’APIs externes.
 *
 * Implémentations prévues :
 *  - WPProvider   : fonctions WP de base (terms, taxonomies, catégories produit…)
 *  - WooProvider  : helpers WooCommerce (attributs produits, pages boutique…)
 *  - ACFProvider  : lecture de champs ACF (images de taxo, options, etc.)
 *
 * Remarque :
 *  Cette interface reste minimaliste pour le MVP (ce que SELLO utilise).
 *  On pourra l’étendre en V2 (produits filtrés, comptages contextuels, etc.).
 */

namespace Sello\Data\Providers;

defined('ABSPATH') || exit;

interface ProviderInterface
{
    /* ============================================================
     * Taxonomies & terms
     * ========================================================== */

    /**
     * Liste les taxonomies d’un objet (ex: 'product') sous forme d’objets.
     *
     * @param string $object_type   ex: 'product'
     * @return array<int,object>    objets taxonomie (WP_Taxonomy en pratique)
     */
    public function getObjectTaxonomies($object_type);

    /**
     * Vérifie si une taxonomie existe.
     *
     * @param string $taxonomy
     * @return bool
     */
    public function taxonomyExists($taxonomy);

    /**
     * Récupère des termes pour une taxonomie.
     *
     * @param array $args  (passe 1:1 à get_terms – taxonomy, number, parent, search…)
     * @return array<int,object>  WP_Term-like
     */
    public function getTerms(array $args);

    /**
     * Récupère un terme par ID ou slug.
     *
     * @param string $taxonomy
     * @param string|int $id_or_slug
     * @return object|null
     */
    public function getTerm($taxonomy, $id_or_slug);

    /**
     * Retourne les ancêtres d’un terme (IDs).
     *
     * @param int    $term_id
     * @param string $taxonomy
     * @return int[]
     */
    public function getAncestors($term_id, $taxonomy);

    /* ============================================================
     * WooCommerce (attributs & catégories)
     * ========================================================== */

    /**
     * Slugs des taxonomies d’attributs WooCommerce (pa_*).
     *
     * @return array<int,string>   ex: ['pa_marque','pa_couleur']
     */
    public function getAttributeTaxonomySlugs();

    /**
     * Libellés lisibles des attributs (clé = slug).
     *
     * @return array<string,string>  ex: ['pa_marque'=>'Marque','pa_couleur'=>'Couleur']
     */
    public function getAttributeLabels();

    /**
     * Slug de la taxonomie des catégories produits (souvent 'product_cat').
     *
     * @return string
     */
    public function getProductCategoryTaxonomy();

    /* ============================================================
     * Liens & URLs
     * ========================================================== */

    /**
     * Lien d’un terme (term archive).
     *
     * @param object $term  WP_Term-like
     * @return string
     */
    public function getTermLink($term);

    /**
     * Lien d’édition d’un post (admin).
     *
     * @param int $post_id
     * @return string
     */
    public function getEditPostLink($post_id);

    /* ============================================================
     * ACF (facultatif, peut retourner valeurs vides si ACF absent)
     * ========================================================== */

    /**
     * Valeur ACF attachée à un objet (term, post, option), si disponible.
     *
     * @param string       $field_key   Key/Name du champ ACF
     * @param int|string   $object_id   ID ou "term_123", "option", etc.
     * @return mixed|null
     */
    public function getACF($field_key, $object_id);

    /**
     * URL d’image ACF (ex: image de taxonomie), si disponible.
     *
     * @param string       $field_key
     * @param int|string   $object_id
     * @param string|array $size  (ex: 'thumbnail' ou ['width'=>..,'height'=>..])
     * @return string  URL ou '' si indisponible
     */
    public function getACFImageUrl($field_key, $object_id, $size = 'full');
}
