<?php
/**
 * Admin Page — Groups (Category / Taxonomy / Attribute groups)
 * Compat PHP 7.0+
 *
 * Objectif MVP :
 * - Permettre de définir des groupes nommés de cibles (catégories, taxonomies, attributs)
 * - Stockage en option unique: sello_groups
 * - Syntaxe simple "une ligne = un groupe" :
 *     NomDuGroupe | item1, item2, item3
 *   Où chaque "item" peut être un slug ou un ID (nombre).
 *
 * Exemples :
 *   -- Onglet Category --
 *   Parfums (Core) | parfums, 321, eau-de-parfum
 *
 *   -- Onglet Taxonomy --
 *   Marques Top | pa_marque:dior, pa_marque:chanel, pa_marque:ysl
 *   Couleurs Chaudes | pa_couleur:rouge, pa_couleur:orange
 *
 *   -- Onglet Attribute --
 *   Pointures Femme | pa_pointure:36, pa_pointure:37, pa_pointure:38
 *
 * Remarque :
 * - Pour les taxonomies/attributs, préfixe conseillé `taxonomy_slug:value`
 *   (ex: pa_marque:dior). Si juste "dior" est donné, la résolution devra être
 *   faite côté usage (hors de ce MVP).
 */
namespace Sello\Admin;

defined('ABSPATH') || exit;

class GroupsPage
{
    const OPTION_KEY = 'sello_groups';
    const NONCE_KEY  = 'sello_groups_nonce';

    /** Rendu + sauvegarde */
    public static function render()
    {
        if (!current_user_can('manage_sello')) {
            wp_die(__('You do not have permission to access SELLO.', 'sello'));
        }

        // Valeurs par défaut
        $defaults = array(
            'category' => array(),  // each: ['name'=>'', 'items'=>['slug','123', ...]]
            'taxonomy' => array(),  // each: ['name'=>'', 'items'=>['pa_marque:dior', ...]]
            'attribute'=> array(),  // each: ['name'=>'', 'items'=>['pa_pointure:36', ...]]
        );

        // Sauvegarde
        if (isset($_POST['sello_groups_save'])) {
            check_admin_referer(self::NONCE_KEY, self::NONCE_KEY);

            $in = isset($_POST['sello']) && is_array($_POST['sello']) ? $_POST['sello'] : array();

            $cat_lines = isset($in['category'])  ? (string)$in['category']  : '';
            $tax_lines = isset($in['taxonomy'])  ? (string)$in['taxonomy']  : '';
            $att_lines = isset($in['attribute']) ? (string)$in['attribute'] : '';

            $opt = array(
                'category'  => self::parse_groups_lines($cat_lines),
                'taxonomy'  => self::parse_groups_lines($tax_lines),
                'attribute' => self::parse_groups_lines($att_lines),
            );

            update_option(self::OPTION_KEY, $opt, true);
            echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Groups updated.', 'sello').'</p></div>';
        }

        // Lecture
        $opt = get_option(self::OPTION_KEY, array());
        if (!is_array($opt)) $opt = array();
        $opt = array_merge($defaults, $opt);

        // Valeurs → textareas
        $cat_text = self::groups_to_lines($opt['category']);
        $tax_text = self::groups_to_lines($opt['taxonomy']);
        $att_text = self::groups_to_lines($opt['attribute']);

        // UI
        echo '<div class="wrap">';
        echo '<h1 style="margin-bottom:10px;">SELLO — '.esc_html__('Groups','sello').'</h1>';
        echo '<p class="description">'.esc_html__('Define named groups of categories, taxonomies, or attributes. These can be referenced later for presets, pinned, or sliders.', 'sello').'</p>';

        // Styles simples
        echo '<style>
            .sello-tabs{margin-top:14px}
            .sello-tab-nav{display:flex;gap:8px;margin-bottom:10px}
            .sello-tab-nav a{display:inline-block;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;text-decoration:none;color:#111}
            .sello-tab-nav a.active{background:#111;color:#fff;border-color:#111}
            .sello-box{border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:14px}
            textarea.sello-lines{width:100%;min-height:220px;font-family:monospace}
            .sello-muted{color:#6b7280;font-size:12px}
            .sello-ex{background:#f9fafb;border:1px dashed #e5e7eb;border-radius:6px;padding:10px;margin-top:8px}
            .sello-grid{display:grid;grid-template-columns:220px 1fr;gap:12px;align-items:start}
        </style>';

        echo '<form method="post">';
        wp_nonce_field(self::NONCE_KEY, self::NONCE_KEY);

        echo '<div class="sello-tabs">';
        echo '<div class="sello-tab-nav">';
        echo '<a href="#sello-tab-cat" class="active">'.esc_html__('Category groups','sello').'</a>';
        echo '<a href="#sello-tab-tax">'.esc_html__('Taxonomy groups','sello').'</a>';
        echo '<a href="#sello-tab-att">'.esc_html__('Attribute groups','sello').'</a>';
        echo '</div>';

        // TAB Category
        echo '<div id="sello-tab-cat" class="sello-box">';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Category groups','sello').'</strong><div class="sello-muted">'.esc_html__('One group per line: Name | id/slug, id/slug, …','sello').'</div></div>';
        echo '<div>';
        echo '<textarea name="sello[category]" class="sello-lines" placeholder="Parfums (Core) | parfums, 321, eau-de-parfum">'.esc_textarea($cat_text).'</textarea>';
        echo '<div class="sello-ex"><strong>'.esc_html__('Example','sello').':</strong><br>Parfums (Core) | parfums, 321, eau-de-parfum</div>';
        echo '</div></div></div>';

        // TAB Taxonomy
        echo '<div id="sello-tab-tax" class="sello-box" style="display:none">';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Taxonomy groups','sello').'</strong><div class="sello-muted">'.esc_html__('Use taxonomy:value format (e.g. pa_marque:dior). One group per line.','sello').'</div></div>';
        echo '<div>';
        echo '<textarea name="sello[taxonomy]" class="sello-lines" placeholder="Marques Top | pa_marque:dior, pa_marque:chanel, pa_marque:ysl">'.esc_textarea($tax_text).'</textarea>';
        echo '<div class="sello-ex"><strong>'.esc_html__('Example','sello').':</strong><br>Marques Top | pa_marque:dior, pa_marque:chanel, pa_marque:ysl</div>';
        echo '</div></div></div>';

        // TAB Attribute
        echo '<div id="sello-tab-att" class="sello-box" style="display:none">';
        echo '<div class="sello-grid">';
        echo '<div><strong>'.esc_html__('Attribute groups','sello').'</strong><div class="sello-muted">'.esc_html__('Use attribute:value format (e.g. pa_pointure:36). One group per line.','sello').'</div></div>';
        echo '<div>';
        echo '<textarea name="sello[attribute]" class="sello-lines" placeholder="Pointures Femme | pa_pointure:36, pa_pointure:37, pa_pointure:38">'.esc_textarea($att_text).'</textarea>';
        echo '<div class="sello-ex"><strong>'.esc_html__('Example','sello').':</strong><br>Pointures Femme | pa_pointure:36, pa_pointure:37, pa_pointure:38</div>';
        echo '</div></div></div>';

        echo '</div>'; // tabs

        echo '<p><button type="submit" class="button button-primary" name="sello_groups_save" value="1">'.esc_html__('Save groups','sello').'</button></p>';
        echo '</form>';

        // JS tabs
        ?>
<script>
(function(){
  var navs = document.querySelectorAll('.sello-tab-nav a');
  var tabs = {
    'sello-tab-cat': document.getElementById('sello-tab-cat'),
    'sello-tab-tax': document.getElementById('sello-tab-tax'),
    'sello-tab-att': document.getElementById('sello-tab-att')
  };
  function activate(id){
    for (var k in tabs){ if (!tabs.hasOwnProperty(k)) continue;
      tabs[k].style.display = (k===id)?'block':'none';
    }
    Array.prototype.forEach.call(navs, function(a){
      a.classList.toggle('active', a.getAttribute('href')==='#'+id);
    });
    history.replaceState(null, '', '#'+id);
  }
  Array.prototype.forEach.call(navs, function(a){
    a.addEventListener('click', function(e){ e.preventDefault(); activate(this.getAttribute('href').substring(1)); });
  });
  // Open tab from hash
  var hash = (location.hash||'#sello-tab-cat').substring(1);
  activate(tabs[hash] ? hash : 'sello-tab-cat');
})();
</script>
        <?php
        echo '</div>'; // wrap
    }

    /* ====================== Helpers ====================== */

    /** Transforme un tableau de groupes en texte multi-lignes "Name | a, b, c" */
    private static function groups_to_lines($groups)
    {
        if (!is_array($groups) || empty($groups)) return '';
        $lines = array();
        foreach ($groups as $g) {
            $name  = isset($g['name']) ? (string)$g['name'] : '';
            $items = isset($g['items']) && is_array($g['items']) ? $g['items'] : array();
            $items = array_values(array_filter(array_unique(array_map('strval', $items))));
            $lines[] = $name.' | '.implode(', ', $items);
        }
        return implode("\n", $lines);
    }

    /**
     * Parse texte multi-lignes en tableau de groupes :
     * - "Nom | item1, item2, item3"
     * - Ignore lignes vides / sans séparateur "|"
     */
    private static function parse_groups_lines($text)
    {
        $out = array();
        $text = (string)$text;
        if ($text === '') return $out;

        $lines = preg_split('/\r\n|\r|\n/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = explode('|', $line, 2);
            if (count($parts) < 2) continue;

            $name = trim($parts[0]);
            $vals = trim($parts[1]);

            $items = array();
            if ($vals !== '') {
                foreach (explode(',', $vals) as $v) {
                    $v = trim($v);
                    if ($v === '') continue;
                    // garde nombre ou slug brut (ex: "pa_marque:dior")
                    if (is_numeric($v)) $items[] = (string)(int)$v;
                    else $items[] = sanitize_text_field($v);
                }
            }

            if ($name !== '' && $items) {
                $out[] = array('name' => $name, 'items' => array_values(array_unique($items)));
            }
        }
        return $out;
    }
}
