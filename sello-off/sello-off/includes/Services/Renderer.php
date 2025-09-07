<?php
/*========================================================================
 File: includes/Services/Renderer.php
========================================================================*/
namespace Sello\Services;

defined('ABSPATH') || exit;

class Renderer {
    public static function view(string $rel_path, array $vars = []): string {
        $file = trailingslashit(SELLO_PATH) . 'templates/' . ltrim($rel_path, '/');
        if (!file_exists($file)) return '';
        ob_start();
        extract($vars, EXTR_SKIP);
        include $file;
        return (string)ob_get_clean();
    }

    public static function e(string $rel_path, array $vars = []): void {
        echo self::view($rel_path, $vars);
    }
}
