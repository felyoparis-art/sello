<?php
/**
 * Core — Autoloader (PSR-4 minimal) pour SELLO
 * Compat PHP 7.0+
 *
 * Objectif :
 *  - Charger automatiquement toutes les classes du namespace "Sello\"
 *  - Fonctionne même si Composer n'est pas utilisé sur le site
 *  - N’interfère pas avec un autoloader Composer existant (cohabitation OK)
 *
 * Convention :
 *  - Namespace racine :  Sello\
 *  - Racine fichiers :   {plugin-dir}/includes/
 *  - Mapping PSR-4 :     "Sello\Foo\Bar" -> "includes/Foo/Bar.php"
 */

namespace Sello\Core;

defined('ABSPATH') || exit;

class Autoloader
{
    /** @var string Chemin absolu vers /includes/ (avec trailing slash) */
    private static $baseDir = '';

    /** @var bool */
    private static $registered = false;

    /**
     * Initialise et enregistre l’autoloader.
     * Idempotent : appel multiple sans effet.
     */
    public static function boot()
    {
        if (self::$registered) {
            return;
        }

        // Déterminer la base "includes/" à partir de ce fichier
        $includes = dirname(__DIR__) . DIRECTORY_SEPARATOR; // …/includes/
        self::$baseDir = self::normalize_dir($includes);

        // Registre SPL
        spl_autoload_register(array(__CLASS__, 'autoload'), true, true);
        self::$registered = true;
    }

    /**
     * Fonction d’autoload PSR-4 minimaliste.
     *
     * @param string $class Nom complet de la classe demandée
     * @return void
     */
    private static function autoload($class)
    {
        // On ne gère que notre namespace
        $prefix = 'Sello\\';
        $len    = strlen($prefix);

        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        // Convertir "Sello\Foo\Bar" -> "Foo/Bar.php"
        $relative = substr($class, $len);
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

        // Chemin absolu
        $file = self::$baseDir . $relativePath;

        // Charger si présent
        if (is_file($file)) {
            // Sécurité : éviter double inclusion
            require_once $file;
            return;
        }

        // Essayer une variante "class-foo-bar.php" (fallback, utile si besoin)
        $alt = self::alt_filename($relativePath);
        if ($alt && is_file(self::$baseDir . $alt)) {
            require_once self::$baseDir . $alt;
            return;
        }

        // En dernier recours : rien (laisser d’autres autoloaders gérer)
    }

    /* ============================================================
     * Helpers
     * ========================================================== */

    /**
     * Normalise un chemin de dossier avec un séparateur de fin.
     *
     * @param string $dir
     * @return string
     */
    private static function normalize_dir($dir)
    {
        $dir = (string) $dir;
        $dir = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR;
        return $dir;
    }

    /**
     * Fallback de nommage alternatif :
     *  "Foo/Bar.php" -> "Foo/class-bar.php"
     *  "Admin/Menu.php" -> "Admin/class-menu.php"
     *
     * @param string $relativePath
     * @return string|null
     */
    private static function alt_filename($relativePath)
    {
        $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
        if (empty($parts)) return null;

        $filename = array_pop($parts); // "Bar.php"
        $nameNoExt = preg_replace('/\.php$/i', '', $filename); // "Bar"
        if ($nameNoExt === '') return null;

        $alt = 'class-' . strtolower(str_replace('_', '-', self::camel_to_snake($nameNoExt))) . '.php';
        $parts[] = $alt;

        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * Convertit "MyAwesomeClass" -> "my_awesome_class"
     * (utilisé pour fabriquer le fallback "class-*.php")
     *
     * @param string $s
     * @return string
     */
    private static function camel_to_snake($s)
    {
        $s = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $s);
        $s = strtolower($s);
        return $s;
    }
}

// Enregistrement immédiat si ce fichier est chargé directement
Autoloader::boot();
