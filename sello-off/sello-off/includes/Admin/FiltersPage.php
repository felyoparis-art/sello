<?php
/*========================================================================
 File: includes/Admin/FiltersPage.php
========================================================================*/
namespace Sello\Admin;

defined('ABSPATH') || exit;

class FiltersPage {
    public static function render(): void { ?>
        <div class="wrap sello-wrap">
            <h1>SELLO — <?php esc_html_e('Filters', 'sello'); ?></h1>
            <p><?php esc_html_e('Where to display (scope), what to display (preset or facets), panel design, Pinned, and Slider.', 'sello'); ?></p>
        </div>
    <?php }
}
