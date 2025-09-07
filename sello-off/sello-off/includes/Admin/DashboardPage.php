<?php
/**
 * Admin Page — Dashboard
 * Compat PHP 7.0+
 */
namespace Sello\Admin;

defined('ABSPATH') || exit;

class DashboardPage
{
    public static function render()
    {
        if (!current_user_can('manage_sello')) {
            wp_die(__('You do not have permission to access SELLO.', 'sello'));
        }

        // Compteurs rapides
        $c_facets  = wp_count_posts('sello_facet');
        $c_presets = wp_count_posts('sello_preset');
        $c_filters = wp_count_posts('sello_filter');
        $n_facets  = isset($c_facets->publish)  ? (int)$c_facets->publish  : 0;
        $n_presets = isset($c_presets->publish) ? (int)$c_presets->publish : 0;
        $n_filters = isset($c_filters->publish) ? (int)$c_filters->publish : 0;

        // Derniers éléments publiés (liste simple)
        $recent_facets  = get_posts(array('post_type'=>'sello_facet','numberposts'=>5,'post_status'=>'publish','orderby'=>'date','order'=>'DESC'));
        $recent_presets = get_posts(array('post_type'=>'sello_preset','numberposts'=>5,'post_status'=>'publish','orderby'=>'date','order'=>'DESC'));
        $recent_filters = get_posts(array('post_type'=>'sello_filter','numberposts'=>5,'post_status'=>'publish','orderby'=>'date','order'=>'DESC'));

        echo '<div class="wrap">';
        echo '<h1 style="margin-bottom:10px;">SELLO — Dashboard</h1>';

        // Actions rapides
        echo '<p>';
        echo '<a class="button button-primary" href="'.esc_url(admin_url('post-new.php?post_type=sello_filter')).'">'.esc_html__('Create Filter','sello').'</a> ';
        echo '<a class="button" href="'.esc_url(admin_url('post-new.php?post_type=sello_preset')).'">'.esc_html__('Create Preset','sello').'</a> ';
        echo '<a class="button" href="'.esc_url(admin_url('post-new.php?post_type=sello_facet')).'">'.esc_html__('Create Facet','sello').'</a>';
        echo '</p>';

        // Cards de stats
        echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">';
        self::stat_card(__('Facets','sello'),  $n_facets,  admin_url('edit.php?post_type=sello_facet'));
        self::stat_card(__('Presets','sello'), $n_presets, admin_url('edit.php?post_type=sello_preset'));
        self::stat_card(__('Filters','sello'), $n_filters, admin_url('edit.php?post_type=sello_filter'));
        echo '</div>';

        // Colonnes
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">';

        // Colonne 1 — Activité récente
        echo '<div>';
        echo '<h2>'.esc_html__('Recent activity','sello').'</h2>';
        self::recent_list(__('Latest Filters','sello'), $recent_filters, 'sello_filter');
        self::recent_list(__('Latest Presets','sello'), $recent_presets, 'sello_preset');
        self::recent_list(__('Latest Facets','sello'),  $recent_facets,  'sello_facet');
        echo '</div>';

        // Colonne 2 — Guide rapide
        echo '<div>';
        echo '<h2>'.esc_html__('Quick guide','sello').'</h2>';
        echo '<ol style="margin-left:20px;">';
        echo '<li><strong>'.esc_html__('Create Facets','sello').'</strong> — '.esc_html__('each facet targets one taxonomy/attribute/category and defines its display (checkbox, radio, dropdown, etc.)','sello').'</li>';
        echo '<li><strong>'.esc_html__('Group them in a Preset','sello').'</strong> — '.esc_html__('a preset is just an ordered set of facets you reuse across filters.','sello').'</li>';
        echo '<li><strong>'.esc_html__('Create a Filter','sello').'</strong> — '.esc_html__('choose content (preset or individual facets), set scope (where it appears), design (sidebar), slider & pinned.','sello').'</li>';
        echo '<li><strong>'.esc_html__('Auto-attach','sello').'</strong> — '.esc_html__('enable scope “auto attach” to inject the panel on matching WooCommerce archives.','sello').'</li>';
        echo '<li><strong>'.esc_html__('Shortcodes (option)','sello').'</strong> — [sello_filters id="123"] / [sello_hero id="123"].</li>';
        echo '</ol>';

        // Aides
        echo '<h3>'.esc_html__('Helpful links','sello').'</h3>';
        echo '<ul style="margin-left:16px;list-style:disc;">';
        echo '<li><a href="'.esc_url(admin_url('edit.php?post_type=sello_facet')).'">'.esc_html__('Manage Facets','sello').'</a></li>';
        echo '<li><a href="'.esc_url(admin_url('edit.php?post_type=sello_preset')).'">'.esc_html__('Manage Presets','sello').'</a></li>';
        echo '<li><a href="'.esc_url(admin_url('edit.php?post_type=sello_filter')).'">'.esc_html__('Manage Filters','sello').'</a></li>';
        echo '<li><a href="'.esc_url(admin_url('admin.php?page=sello-design')).'">'.esc_html__('Global Design','sello').'</a></li>';
        echo '<li><a href="'.esc_url(admin_url('admin.php?page=sello-settings')).'">'.esc_html__('Settings','sello').'</a></li>';
        echo '</ul>';

        echo '</div>'; // end col 2

        echo '</div>'; // end grid

        echo '</div>'; // wrap
    }

    private static function stat_card($label, $count, $url)
    {
        echo '<a href="'.esc_url($url).'" style="flex:1;min-width:220px;text-decoration:none;color:inherit;">';
        echo '<div style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;background:#fff;">';
        echo '<div style="font-size:12px;color:#6b7280;margin-bottom:6px;">'.esc_html($label).'</div>';
        echo '<div style="font-size:28px;font-weight:700;">'.esc_html((string)$count).'</div>';
        echo '</div>';
        echo '</a>';
    }

    private static function recent_list($title, $posts, $ptype)
    {
        echo '<div style="margin:14px 0 24px;">';
        echo '<h3 style="margin:8px 0;">'.esc_html($title).'</h3>';
        if (empty($posts)) {
            echo '<p style="color:#6b7280;"><em>'.esc_html__('Nothing yet.','sello').'</em></p>';
        } else {
            echo '<ul style="margin-left:16px;list-style:disc;">';
            foreach ($posts as $p) {
                $edit = get_edit_post_link($p->ID, '');
                $date = get_the_time(get_option('date_format'), $p);
                echo '<li><a href="'.esc_url($edit).'">'.esc_html(get_the_title($p)).'</a>';
                echo ' <span style="color:#6b7280;">— '.esc_html($date).'</span></li>';
            }
            echo '</ul>';
        }
        echo '<p><a class="button" href="'.esc_url(admin_url('edit.php?post_type='.$ptype)).'">'.esc_html__('View all','sello').'</a></p>';
        echo '</div>';
    }
}
