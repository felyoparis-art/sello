<?php
/**
 * Admin Page — Settings (Export / Import / Tools)
 * Compat PHP 7.0+
 *
 * - Export : options SELLO (Design, Groups) + entités (Facets / Presets / Filters)
 * - Import : colle le JSON et importe (crée ou met à jour "par titre" si coché)
 * - Tools  : vidage cache (placeholder) + infos système
 */
namespace Sello\Admin;

defined('ABSPATH') || exit;

class SettingsPage
{
    const NONCE_KEY = 'sello_settings_nonce';

    /** Page renderer + handlers (POST same-page) */
    public static function render()
    {
        if (!current_user_can('manage_sello')) {
            wp_die(__('You do not have permission to access SELLO.', 'sello'));
        }

        $notice = '';

        /* =========================
         * Handle: EXPORT (build JSON)
         * ======================= */
        $export_json = '';
        if (isset($_POST['sello_do_export'])) {
            check_admin_referer(self::NONCE_KEY, self::NONCE_KEY);
            $export_json = self::build_export_json();
            if ($export_json === '') {
                $notice = '<div class="notice notice-warning is-dismissible"><p>'.esc_html__('Nothing to export yet.', 'sello').'</p></div>';
            } else {
                $notice = '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Export generated below.', 'sello').'</p></div>';
            }
        }

        /* =========================
         * Handle: IMPORT (parse JSON)
         * ======================= */
        if (isset($_POST['sello_do_import'])) {
            check_admin_referer(self::NONCE_KEY, self::NONCE_KEY);
            $json   = isset($_POST['sello_import_json']) ? (string)wp_unslash($_POST['sello_import_json']) : '';
            $merge  = !empty($_POST['sello_import_merge']); // update by title if exists
            $result = self::handle_import_json($json, $merge);
            if (is_wp_error($result)) {
                $notice = '<div class="notice notice-error is-dismissible"><p>'.esc_html($result->get_error_message()).'</p></div>';
            } else {
                $msg = sprintf(
                    /* translators: 1: facets, 2: presets, 3: filters */
                    esc_html__('Import done. Facets: %1$d, Presets: %2$d, Filters: %3$d', 'sello'),
                    (int)$result['facets'],
                    (int)$result['presets'],
                    (int)$result['filters']
                );
                $notice = '<div class="notice notice-success is-dismissible"><p>'.$msg.'</p></div>';
            }
        }

        /* =========================
         * Handle: TOOLS (clear cache)
         * ======================= */
        if (isset($_POST['sello_clear_cache'])) {
            check_admin_referer(self::NONCE_KEY, self::NONCE_KEY);
            // Placeholder safe — si un service Cache existe plus tard, on l'appellera ici.
            // \Sello\Services\Cache::clear_all();
            $notice = '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Cache cleared (placeholder).', 'sello').'</p></div>';
        }

        // UI
        echo '<div class="wrap">';
        echo '<h1 style="margin-bottom:10px;">SELLO — '.esc_html__('Settings','sello').'</h1>';
        echo '<p class="description">'.esc_html__('Export/Import your SELLO setup (Design, Groups, Facets, Presets, Filters) and access basic tools.', 'sello').'</p>';

        // Styles
        echo '<style>
            .sello-box{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:14px;margin-top:12px}
            .sello-grid{display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:start}
            textarea.sello-json{width:100%;min-height:260px;font-family:monospace}
            .sello-muted{color:#6b7280;font-size:12px}
            .sello-inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
            .sello-kv{display:grid;grid-template-columns:240px 1fr;gap:8px}
            .sello-kv div{padding:6px 0;border-bottom:1px solid #f3f4f6}
        </style>';

        if ($notice) echo $notice;

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_KEY, self::NONCE_KEY);

        /* =========================
         * EXPORT
         * ======================= */
        echo '<div class="sello-box">';
        echo '<h2 style="margin:0 0 8px">'.esc_html__('Export','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Build JSON','sello').'</strong><div class="sello-muted">'.esc_html__('Click "Generate export". Copy the JSON below and keep it safe.','sello').'</div></div>';
        echo '<div>';
        echo '<p><button class="button button-primary" name="sello_do_export" value="1">'.esc_html__('Generate export','sello').'</button></p>';
        echo '<textarea class="sello-json" readonly="readonly" placeholder="'.esc_attr__('Export JSON will appear here…','sello').'">'.esc_textarea($export_json).'</textarea>';
        echo '</div>';
        echo '</div></div>';

        /* =========================
         * IMPORT
         * ======================= */
        echo '<div class="sello-box">';
        echo '<h2 style="margin:0 0 8px">'.esc_html__('Import','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Paste JSON','sello').'</strong><div class="sello-muted">'.esc_html__('Paste the JSON you exported previously.','sello').'</div></div>';
        echo '<div>';
        echo '<textarea class="sello-json" name="sello_import_json" placeholder="'.esc_attr__('Paste export JSON here…','sello').'"></textarea>';
        echo '<p class="sello-inline"><label><input type="checkbox" name="sello_import_merge" value="1"> '.esc_html__('Merge by title (update if a post with the same title already exists)','sello').'</label></p>';
        echo '<p><button class="button button-primary" name="sello_do_import" value="1">'.esc_html__('Import now','sello').'</button></p>';
        echo '</div>';
        echo '</div></div>';

        /* =========================
         * TOOLS
         * ======================= */
        echo '<div class="sello-box">';
        echo '<h2 style="margin:0 0 8px">'.esc_html__('Tools','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Maintenance','sello').'</strong><div class="sello-muted">'.esc_html__('Lightweight actions you can run safely.','sello').'</div></div>';
        echo '<div class="sello-inline">';
        echo '<button class="button" name="sello_clear_cache" value="1">'.esc_html__('Clear cache','sello').'</button>';
        echo '</div>';

        echo '<div><strong>'.esc_html__('System info','sello').'</strong><div class="sello-muted">'.esc_html__('Environment quick view.','sello').'</div></div>';
        echo '<div class="sello-kv">';
        echo '<div>'.esc_html__('WordPress','sello').'</div><div>'.esc_html(get_bloginfo('version')).'</div>';
        echo '<div>'.esc_html__('PHP','sello').'</div><div>'.esc_html(PHP_VERSION).'</div>';
        echo '<div>'.esc_html__('WooCommerce','sello').'</div><div>'.(function_exists('WC') ? esc_html(WC()->version) : esc_html__('N/A','sello')).'</div>';
        echo '<div>'.esc_html__('SELLO','sello').'</div><div>'.(defined('SELLO_VERSION') ? esc_html(SELLO_VERSION) : '0').'</div>';
        echo '</div>';

        echo '</div>'; // grid
        echo '</div>'; // box

        echo '</form>';
        echo '</div>'; // wrap
    }

    /* =========================================================
     * Export builder
     * ======================================================= */
    private static function build_export_json()
    {
        // Options
        $design = get_option(DesignPage::OPTION_KEY, array());
        $groups = get_option(GroupsPage::OPTION_KEY, array());

        // CPTs
        $facets  = self::export_cpt('sello_facet');
        $presets = self::export_cpt('sello_preset');
        $filters = self::export_cpt('sello_filter');

        $data = array(
            'sello_version' => defined('SELLO_VERSION') ? SELLO_VERSION : '0',
            'exported_at'   => gmdate('c'),
            'options'       => array(
                'design' => $design,
                'groups' => $groups,
            ),
            'cpts'          => array(
                'sello_facet'  => $facets,
                'sello_preset' => $presets,
                'sello_filter' => $filters,
            ),
        );

        $json = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '';
    }

    /** Export all posts of a CPT with meta (safe, no IDs leaked that matter) */
    private static function export_cpt($post_type)
    {
        $posts = get_posts(array(
            'post_type'              => $post_type,
            'post_status'            => array('publish','draft','pending'),
            'numberposts'            => -1,
            'orderby'                => 'date',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));
        if (!$posts) return array();

        $out = array();
        foreach ($posts as $pid) {
            $meta = get_post_meta($pid);
            $meta_clean = array();
            foreach ($meta as $k=>$vals) {
                if (!is_array($vals)) continue;
                // keep single value or raw array (already sanitized on save)
                $meta_clean[$k] = count($vals) === 1 ? maybe_unserialize($vals[0]) : array_map('maybe_unserialize', $vals);
            }
            $out[] = array(
                'post_title'  => get_the_title($pid),
                'post_status' => get_post_status($pid),
                'meta'        => $meta_clean,
            );
        }
        return $out;
    }

    /* =========================================================
     * Import handler
     * ======================================================= */
    private static function handle_import_json($json, $merge_by_title)
    {
        $json = trim((string)$json);
        if ($json === '') return new \WP_Error('sello_empty', __('Import JSON is empty.', 'sello'));

        $data = json_decode($json, true);
        if (!is_array($data)) return new \WP_Error('sello_bad_json', __('Invalid JSON.', 'sello'));

        // Options
        if (isset($data['options']['design']) && is_array($data['options']['design'])) {
            update_option(DesignPage::OPTION_KEY, $data['options']['design'], true);
        }
        if (isset($data['options']['groups']) && is_array($data['options']['groups'])) {
            update_option(GroupsPage::OPTION_KEY, $data['options']['groups'], true);
        }

        // CPTs
        $counts = array('facets'=>0,'presets'=>0,'filters'=>0);
        $counts['facets']  = self::import_cpt('sello_facet',  isset($data['cpts']['sello_facet'])  ? $data['cpts']['sello_facet']  : array(), $merge_by_title);
        $counts['presets'] = self::import_cpt('sello_preset', isset($data['cpts']['sello_preset']) ? $data['cpts']['sello_preset'] : array(), $merge_by_title);
        $counts['filters'] = self::import_cpt('sello_filter', isset($data['cpts']['sello_filter']) ? $data['cpts']['sello_filter'] : array(), $merge_by_title);

        return $counts;
    }

    /** Create/Update posts from export payload */
    private static function import_cpt($post_type, $items, $merge_by_title)
    {
        if (!is_array($items) || empty($items)) return 0;
        $done = 0;

        foreach ($items as $row) {
            $title = isset($row['post_title']) ? (string)$row['post_title'] : '';
            $status= isset($row['post_status']) ? (string)$row['post_status'] : 'publish';
            $meta  = isset($row['meta']) && is_array($row['meta']) ? $row['meta'] : array();
            if ($title === '') continue;

            $post_id = 0;

            if ($merge_by_title) {
                $exists = get_page_by_title($title, OBJECT, $post_type);
                if ($exists && !is_wp_error($exists)) {
                    $post_id = (int)$exists->ID;
                    wp_update_post(array(
                        'ID'          => $post_id,
                        'post_title'  => $title,
                        'post_status' => $status,
                        'post_type'   => $post_type,
                    ));
                }
            }

            if (!$post_id) {
                $post_id = wp_insert_post(array(
                    'post_title'  => $title,
                    'post_status' => $status,
                    'post_type'   => $post_type,
                ), true);
                if (is_wp_error($post_id) || !$post_id) continue;
            }

            // Write meta (overwrite)
            foreach ($meta as $k=>$v) {
                // Safety: strip private WP keys
                if (strpos($k, '_edit_') === 0) continue;
                if ($k === '_edit_last') continue;

                delete_post_meta($post_id, $k);
                if (is_array($v)) {
                    // store array directly (WP will serialize)
                    update_post_meta($post_id, $k, $v);
                } else {
                    update_post_meta($post_id, $k, $v);
                }
            }

            $done++;
        }

        return $done;
    }
}
