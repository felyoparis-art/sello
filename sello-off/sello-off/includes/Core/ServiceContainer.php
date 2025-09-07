<?php
/**
 * Core — ServiceContainer (service locator minimal)
 * Compat PHP 7.0+
 *
 * Rôle :
 *  - Enregistrer et fournir les singletons utilisés par SELLO
 *  - Choisir automatiquement le meilleur "Data Provider"
 *      * ACF présent  → Sello\Data\Providers\ACFProvider
 *      * Woo présent  → Sello\Data\Providers\WooProvider
 *      * Sinon        → Sello\Data\Providers\WPProvider
 *  - Exposer des helpers (getProvider, getCache, getCounters, getValidator)
 *
 * Remarque :
 *  - Container très simple (array statique). Suffisant pour le plugin.
 *  - Idempotent : boot() peut être rappelé sans effets secondaires.
 */

namespace Sello\Core;

use Sello\Data\Providers\ProviderInterface;
use Sello\Data\Providers\WPProvider;
use Sello\Data\Providers\WooProvider;
use Sello\Data\Providers\ACFProvider;

use Sello\Services\Cache;
use Sello\Services\Counters;
use Sello\Services\Validator;

defined('ABSPATH') || exit;

class ServiceContainer
{
    /** @var array<string,object> */
    private static $instances = array();

    /** @var bool */
    private static $booted = false;

    /**
     * Initialise le container avec les services par défaut.
     * Détecte l’environnement (Woo/ACF).
     */
    public static function boot()
    {
        if (self::$booted) {
            return;
        }

        // 1) Data Provider
        //    On choisit le provider le plus riche disponible.
        $provider = null;

        $hasWoo = function_exists('wc') || class_exists('\WooCommerce');
        $hasACF = function_exists('get_field') || class_exists('\ACF');

        if ($hasACF) {
            // ACFProvider étend WPProvider et sait lire les champs ACF + fallback Woo cat image
            $provider = new ACFProvider();
        } elseif ($hasWoo) {
            // WooProvider expose les helpers Woo (attributs pa_*, product_cat, etc.)
            $provider = new WooProvider();
        } else {
            // Fallback WordPress pur
            $provider = new WPProvider();
        }

        self::set('provider', $provider);

        // 2) Services transverses
        self::set('cache',     new Cache());
        self::set('validator', new Validator());
        self::set('counters',  new Counters(self::getProvider(), self::getCache()));

        self::$booted = true;
    }

    /* ============================================================
     * API de base
     * ========================================================== */

    /**
     * Enregistre un service (singleton).
     * @param string $id
     * @param object $instance
     */
    public static function set($id, $instance)
    {
        if (!is_string($id) || $id === '' || !is_object($instance)) {
            return;
        }
        self::$instances[$id] = $instance;
    }

    /**
     * Récupère un service.
     * @param string $id
     * @return object|null
     */
    public static function get($id)
    {
        return isset(self::$instances[$id]) ? self::$instances[$id] : null;
    }

    /**
     * Le service existe-t-il ?
     * @param string $id
     * @return bool
     */
    public static function has($id)
    {
        return isset(self::$instances[$id]);
    }

    /* ============================================================
     * Helpers typés
     * ========================================================== */

    /**
     * @return ProviderInterface
     */
    public static function getProvider()
    {
        $p = self::get('provider');
        if ($p instanceof ProviderInterface) {
            return $p;
        }

        // Sécurité : si non booté, on le fait maintenant.
        self::boot();
        $p = self::get('provider');

        // Ultime fallback (ne devrait pas arriver)
        if (!$p instanceof ProviderInterface) {
            $p = new WPProvider();
            self::set('provider', $p);
        }
        return $p;
    }

    /**
     * @return Cache
     */
    public static function getCache()
    {
        $c = self::get('cache');
        if ($c instanceof Cache) {
            return $c;
        }
        $c = new Cache();
        self::set('cache', $c);
        return $c;
    }

    /**
     * @return Validator
     */
    public static function getValidator()
    {
        $v = self::get('validator');
        if ($v instanceof Validator) {
            return $v;
        }
        $v = new Validator();
        self::set('validator', $v);
        return $v;
    }

    /**
     * @return Counters
     */
    public static function getCounters()
    {
        $c = self::get('counters');
        if ($c instanceof Counters) {
            return $c;
        }
        $c = new Counters(self::getProvider(), self::getCache());
        self::set('counters', $c);
        return $c;
    }
}
