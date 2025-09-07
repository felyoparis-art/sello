<?php
/**
 * Services — Cache
 * Compat PHP 7.0+
 *
 * Cache simple clé/valeur pour SELLO.
 * - Utilise en priorité l'Object Cache WordPress (wp_cache_*).
 * - Fallback sur les Transients si object cache indisponible.
 * - Double fallback mémoire (durée de la requête) pour éviter les hit répétés.
 *
 * API :
 *   - get(string $key, $default = null)
 *   - set(string $key, $value, int $ttl = 300)
 *   - delete(string $key)
 *   - remember(string $key, int $ttl, callable $callback)
 *
 * Notes :
 *   - Les clés sont préfixées "sello_" + version courte.
 *   - $ttl en secondes (0 = pas d'expiration pour transients / object cache laisse gérer).
 */

namespace Sello\Services;

defined('ABSPATH') || exit;

class Cache
{
    /** @var string */
    private $group = 'sello';

    /** @var string Préfixe unique pour éviter collisions */
    private $prefix;

    /** @var array Mémoire locale (durée de la requête) */
    private static $local = array();

    public function __construct($prefix = '')
    {
        $ver = defined('SELLO_VERSION') ? (string)SELLO_VERSION : '1';
        $this->prefix = 'sello_' . substr(md5($ver), 0, 6) . ($prefix !== '' ? '_'.preg_replace('/[^a-z0-9_]/i','_', $prefix) : '');
    }

    /* ============================================================
     * API publique
     * ========================================================== */

    /**
     * Récupère une valeur de cache.
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $ck = $this->ck($key);

        // 1) Mémoire locale
        if (array_key_exists($ck, self::$local)) {
            return self::$local[$ck];
        }

        // 2) Object cache
        if (function_exists('wp_cache_get')) {
            $found = null;
            $val = wp_cache_get($ck, $this->group, false, $found);
            if ($found) {
                self::$local[$ck] = $val;
                return $val;
            }
        }

        // 3) Transient (global)
        $t = get_transient($ck);
        if ($t !== false) {
            self::$local[$ck] = $t;
            return $t;
        }

        return $default;
    }

    /**
     * Stocke une valeur en cache.
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl Seconds (0 = pas d'expiration)
     * @return void
     */
    public function set($key, $value, $ttl = 300)
    {
        $ck = $this->ck($key);

        // Mémoire locale
        self::$local[$ck] = $value;

        // Object cache
        if (function_exists('wp_cache_set')) {
            // Certaines implémentations ignorent $ttl ; on l'indique quand même
            wp_cache_set($ck, $value, $this->group, (int)$ttl);
        }

        // Transient
        set_transient($ck, $value, (int)$ttl);
    }

    /**
     * Supprime une entrée de cache.
     * @param string $key
     * @return void
     */
    public function delete($key)
    {
        $ck = $this->ck($key);
        unset(self::$local[$ck]);

        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($ck, $this->group);
        }
        delete_transient($ck);
    }

    /**
     * Récupère une valeur ou la calcule et la met en cache via $callback.
     * @param string   $key
     * @param int      $ttl
     * @param callable $callback function():mixed
     * @return mixed
     */
    public function remember($key, $ttl, $callback)
    {
        $val = $this->get($key, null);
        if ($val !== null) {
            return $val;
        }
        $val = is_callable($callback) ? call_user_func($callback) : null;
        $this->set($key, $val, (int)$ttl);
        return $val;
    }

    /* ============================================================
     * Helpers internes
     * ========================================================== */

    /**
     * Construit une clé normalisée et préfixée.
     * @param string $key
     * @return string
     */
    private function ck($key)
    {
        $k = strtolower(trim((string)$key));
        if ($k === '') {
            $k = 'k_' . wp_generate_password(6, false, false);
        }
        // Sécuriser : autoriser a-z 0-9 _ : convertir le reste en _
        $k = preg_replace('/[^a-z0-9_:\-]/', '_', $k);

        // Longueur : réduire si trop long pour certains backends
        if (strlen($k) > 128) {
            $k = substr($k, 0, 100) . '_' . substr(md5($k), 0, 16);
        }

        return $this->prefix . '_' . $k;
    }
}
