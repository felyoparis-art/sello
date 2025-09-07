<?php
/*========================================================================
 File: includes/Admin/FacetsPage.php
========================================================================*/
namespace Sello\Admin;

defined('ABSPATH') || exit;

class FacetsPage {
    public static function render(): void { ?>
        <div class="wrap sello-wrap">
            <h1>SELLO — <?php esc_html_e('Facets', 'sello'); ?></h1>
            <p><?php esc_html_e('Create and manage Facets (one data source per facet, with display & options).', 'sello'); ?></p>
        </div>
    <?php }
}
