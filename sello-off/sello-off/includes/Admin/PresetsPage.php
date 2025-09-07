<?php
/*========================================================================
 File: includes/Admin/PresetsPage.php
========================================================================*/
namespace Sello\Admin;

defined('ABSPATH') || exit;

class PresetsPage {
    public static function render(): void { ?>
        <div class="wrap sello-wrap">
            <h1>SELLO — <?php esc_html_e('Presets', 'sello'); ?></h1>
            <p><?php esc_html_e('Group of facets, reusable. Define order & initial open/collapsed states.', 'sello'); ?></p>
        </div>
    <?php }
}
