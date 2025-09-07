<?php
/**
 * Admin Page — Design (global sidebar behavior only)
 * Compat PHP 7.0+
 */
namespace Sello\Admin;

defined('ABSPATH') || exit;

class DesignPage
{
    const OPTION_KEY = 'sello_design';
    const NONCE_KEY  = 'sello_design_nonce';

    /** Rendu + sauvegarde */
    public static function render()
    {
        if (!current_user_can('manage_sello')) {
            wp_die(__('You do not have permission to access SELLO.', 'sello'));
        }

        // Valeurs par défaut (design global = sidebar seulement)
        $defaults = array(
            'desktop_pos'   => 'right',     // left|right|top|bottom
            'tablet_pos'    => 'right',
            'mobile_pos'    => 'left',

            'anim'          => 'slide',     // slide|push|fade|none
            'overlay_mobile'=> 1,           // 1|0
            'close_outside' => 1,           // 1|0

            'bp_tablet'     => 1024,        // px
            'bp_mobile'     => 767,         // px

            // Largeurs globales par défaut (peuvent être overridées par Filter)
            'w_total'       => 700,         // total sidebar (facets + pinned)
            'w_facets'      => 500,
            'w_pinned'      => 200,
        );

        // Sauvegarde
        if (isset($_POST['sello_design_save'])) {
            check_admin_referer(self::NONCE_KEY, self::NONCE_KEY);

            $in = isset($_POST['sello']) && is_array($_POST['sello']) ? $_POST['sello'] : array();

            $opt = array();
            $opt['desktop_pos']    = self::pick($in, 'desktop_pos', array('left','right','top','bottom'), $defaults['desktop_pos']);
            $opt['tablet_pos']     = self::pick($in, 'tablet_pos',  array('left','right','top','bottom'), $defaults['tablet_pos']);
            $opt['mobile_pos']     = self::pick($in, 'mobile_pos',  array('left','right','top','bottom'), $defaults['mobile_pos']);

            $opt['anim']           = self::pick($in, 'anim', array('slide','push','fade','none'), $defaults['anim']);
            $opt['overlay_mobile'] = empty($in['overlay_mobile']) ? 0 : 1;
            $opt['close_outside']  = empty($in['close_outside'])  ? 0 : 1;

            $opt['bp_tablet']      = isset($in['bp_tablet']) ? max(480, (int)$in['bp_tablet']) : $defaults['bp_tablet'];
            $opt['bp_mobile']      = isset($in['bp_mobile']) ? max(320, (int)$in['bp_mobile']) : $defaults['bp_mobile'];

            $opt['w_total']        = isset($in['w_total'])  ? max(300, (int)$in['w_total'])  : $defaults['w_total'];
            $opt['w_facets']       = isset($in['w_facets']) ? max(200, (int)$in['w_facets']) : $defaults['w_facets'];
            $opt['w_pinned']       = isset($in['w_pinned']) ? max(0,   (int)$in['w_pinned']) : $defaults['w_pinned'];

            update_option(self::OPTION_KEY, $opt, true);

            echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Design updated.', 'sello').'</p></div>';
        }

        // Lecture
        $opt = get_option(self::OPTION_KEY, array());
        if (!is_array($opt)) $opt = array();
        $opt = array_merge($defaults, $opt);

        // UI
        echo '<div class="wrap">';
        echo '<h1 style="margin-bottom:10px;">SELLO — '.esc_html__('Design (Global Sidebar)','sello').'</h1>';
        echo '<p class="description">'.esc_html__('These settings control the sidebar container (position, animation, breakpoints). Each Filter can override widths and enable/disable pinned.', 'sello').'</p>';

        // Styles simples
        echo '<style>
            .sello-box{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:14px;margin-top:10px}
            .sello-grid{display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:center}
            .sello-muted{color:#6b7280;font-size:12px}
            .sello-inline{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
            .sello-col{display:flex;gap:12px}
            .sello-col > div{flex:1}
        </style>';

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_KEY, self::NONCE_KEY);

        // Positions par device
        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Positions by device','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Desktop position','sello').'</strong><div class="sello-muted">'.esc_html__('Where the sidebar opens on desktop screens','sello').'</div></div>';
        echo '<div>'.self::select('sello[desktop_pos]', $opt['desktop_pos'], array('left'=>'Left','right'=>'Right','top'=>'Top','bottom'=>'Bottom')).'</div>';

        echo '<div><strong>'.esc_html__('Tablet position','sello').'</strong></div>';
        echo '<div>'.self::select('sello[tablet_pos]', $opt['tablet_pos'], array('left'=>'Left','right'=>'Right','top'=>'Top','bottom'=>'Bottom')).'</div>';

        echo '<div><strong>'.esc_html__('Mobile position','sello').'</strong></div>';
        echo '<div>'.self::select('sello[mobile_pos]', $opt['mobile_pos'], array('left'=>'Left','right'=>'Right','top'=>'Top','bottom'=>'Bottom')).'</div>';
        echo '</div></div>';

        // Animation & comportement
        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Animation & behavior','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Animation','sello').'</strong><div class="sello-muted">'.esc_html__('Transition when opening/closing the sidebar','sello').'</div></div>';
        echo '<div>'.self::select('sello[anim]', $opt['anim'], array('slide'=>'Slide','push'=>'Push','fade'=>'Fade','none'=>'None')).'</div>';

        echo '<div><strong>'.esc_html__('Mobile overlay','sello').'</strong><div class="sello-muted">'.esc_html__('Dim the page content when sidebar is open on mobile','sello').'</div></div>';
        echo '<div><label><input type="checkbox" name="sello[overlay_mobile]" value="1" '.checked($opt['overlay_mobile'],1,false).' /> '.esc_html__('Enable overlay on mobile','sello').'</label></div>';

        echo '<div><strong>'.esc_html__('Close on outside click','sello').'</strong></div>';
        echo '<div><label><input type="checkbox" name="sello[close_outside]" value="1" '.checked($opt['close_outside'],1,false).' /> '.esc_html__('Clicking outside closes the sidebar','sello').'</label></div>';
        echo '</div></div>';

        // Breakpoints
        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Breakpoints','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Tablet breakpoint (px)','sello').'</strong><div class="sello-muted">'.esc_html__('Max width for tablet layout','sello').'</div></div>';
        echo '<div><input type="number" min="600" step="1" name="sello[bp_tablet]" value="'.esc_attr($opt['bp_tablet']).'" /></div>';

        echo '<div><strong>'.esc_html__('Mobile breakpoint (px)','sello').'</strong><div class="sello-muted">'.esc_html__('Max width for mobile layout','sello').'</div></div>';
        echo '<div><input type="number" min="360" step="1" name="sello[bp_mobile]" value="'.esc_attr($opt['bp_mobile']).'" /></div>';
        echo '</div></div>';

        // Largeurs par défaut
        echo '<div class="sello-box"><h2 style="margin:0 0 8px">'.esc_html__('Default widths (can be overridden per Filter)','sello').'</h2>';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Total / Facets / Pinned (px)','sello').'</strong><div class="sello-muted">'.esc_html__('Default width for the sidebar container','sello').'</div></div>';
        echo '<div class="sello-col">';
        echo '<div><input type="number" min="300" step="10" name="sello[w_total]"  value="'.esc_attr($opt['w_total']).'" /> <div class="sello-muted">'.esc_html__('Total','sello').'</div></div>';
        echo '<div><input type="number" min="200" step="10" name="sello[w_facets]" value="'.esc_attr($opt['w_facets']).'" /> <div class="sello-muted">'.esc_html__('Facets','sello').'</div></div>';
        echo '<div><input type="number" min="0"   step="10" name="sello[w_pinned]" value="'.esc_attr($opt['w_pinned']).'" /> <div class="sello-muted">'.esc_html__('Pinned','sello').'</div></div>';
        echo '</div>';
        echo '</div></div>';

        echo '<p><button type="submit" class="button button-primary" name="sello_design_save" value="1">'.esc_html__('Save changes','sello').'</button></p>';
        echo '</form>';
        echo '</div>';
    }

    /* ====================== Helpers ====================== */

    /** Select helper */
    private static function select($name, $value, $choices)
    {
        $out = '<select name="'.esc_attr($name).'">';
        foreach ($choices as $val=>$lab) {
            $out .= '<option value="'.esc_attr($val).'" '.selected($value,$val,false).'>'.esc_html($lab).'</option>';
        }
        $out .= '</select>';
        return $out;
    }

    /** Sanitize choice */
    private static function pick($arr, $key, $allowed, $fallback)
    {
        $v = isset($arr[$key]) ? (string)$arr[$key] : '';
        return in_array($v, $allowed, true) ? $v : $fallback;
    }
}
