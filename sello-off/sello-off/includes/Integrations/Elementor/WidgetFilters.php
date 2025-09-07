<?php
/**
 * Elementor Widget — SELLO Filters
 * Compat Elementor 3.5+ / PHP 7.0+
 *
 * Affiche un FILTRE (post type: sello_filter) dans une page construite avec Elementor.
 * - Sélecteur du Filter à rendre
 * - Option : forcer l’injection du CSS "takeover" (masquer widgets natifs du thème)
 * - Dégrade proprement si aucun Filter sélectionné / aucun trouvé
 *
 * Rendu :
 *  - Utilise le shortcode [sello_filters id="123"] si disponible
 *  - Sinon appelle la classe \Sello\Frontend\Shortcodes::render_filters() si présente
 *  - Sinon affiche un message d’aide côté admin uniquement
 */

namespace Sello\Integrations\Elementor;

defined('ABSPATH') || exit;

// Si Elementor n'est pas présent, on déclare quand même la classe (ne sera jamais instanciée)
if (!class_exists('\Elementor\Widget_Base')) {
    // Classe coquille vide pour éviter les fatals si fichier chargé directement
    class WidgetFilters {}
    return;
}

class WidgetFilters extends \Elementor\Widget_Base
{
    /* =========================================
     * Métadonnées du widget
     * ======================================= */

    public function get_name()
    {
        return 'sello_filters';
    }

    public function get_title()
    {
        return __('SELLO — Filters', 'sello');
    }

    public function get_icon()
    {
        return 'eicon-filter';
    }

    public function get_categories()
    {
        return array('sello'); // défini dans Register::register_category
    }

    public function get_keywords()
    {
        return array('filter', 'facets', 'sello', 'woocommerce', 'products');
    }

    /* =========================================
     * Contrôles de l’éditeur
     * ======================================= */

    protected function _register_controls() // Elementor <3.6 compat
    {
        $this->register_controls();
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_sello_filters',
            array(
                'label' => __('SELLO Filters', 'sello'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        // Liste des filters disponibles
        $options = $this->get_filters_options();

        $this->add_control(
            'filter_id',
            array(
                'label'       => __('Choose Filter', 'sello'),
                'type'        => \Elementor\Controls_Manager::SELECT2,
                'multiple'    => false,
                'options'     => $options,
                'label_block' => true,
                'description' => __('Pick a SELLO Filter (created under SELLO → Filters).', 'sello'),
            )
        );

        $this->add_control(
            'force_takeover',
            array(
                'label'        => __('Force Takeover CSS', 'sello'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'sello'),
                'label_off'    => __('No', 'sello'),
                'return_value' => 'yes',
                'default'      => '',
                'description'  => __('Hide native theme filter widgets around this area.', 'sello'),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Récup options (id => "Title (#id)") pour SELECT2
     * @return array<int,string>
     */
    private function get_filters_options()
    {
        $opts = array();

        $posts = get_posts(array(
            'post_type'              => 'sello_filter',
            'post_status'            => array('publish','draft','pending','private'),
            'numberposts'            => 200,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        if (!empty($posts) && is_array($posts)) {
            foreach ($posts as $pid) {
                $title = get_the_title($pid);
                if ($title === '') $title = '(' . __('no title', 'sello') . ')';
                $opts[(int)$pid] = $title . ' (#' . (int)$pid . ')';
            }
        } else {
            $opts[0] = __('No filters found. Create one in SELLO → Filters.', 'sello');
        }

        return $opts;
    }

    /* =========================================
     * Rendu front
     * ======================================= */

    protected function render()
    {
        $settings  = $this->get_settings_for_display();
        $filter_id = isset($settings['filter_id']) ? (int)$settings['filter_id'] : 0;

        if ($filter_id <= 0) {
            // En front : ne rien montrer. En éditeur : message d’aide.
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="sello-widget-placeholder" style="padding:12px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc">';
                echo '<strong>'.esc_html__('SELLO Filters — Not configured', 'sello').'</strong><br/>';
                echo esc_html__('Choose a Filter in the widget settings.', 'sello');
                echo '</div>';
            }
            return;
        }

        // Optionnel : takeover CSS
        $force_takeover = isset($settings['force_takeover']) && $settings['force_takeover'] === 'yes';
        if ($force_takeover) {
            if (class_exists('\Sello\Domain\Design')) {
                \Sello\Domain\Design::emit_takeover_css();
            } else {
                // Fallback minimal si la classe n’est pas dispo
                echo '<style>.widget_layered_nav,.widget_product_categories .children,.widget_price_filter{display:none!important}</style>';
            }
        }

        // 1) Préférer le shortcode s’il existe
        if (shortcode_exists('sello_filters')) {
            echo do_shortcode('[sello_filters id="'.(int)$filter_id.'"]');
            return;
        }

        // 2) Sinon, tenter un rendu direct via la classe frontend si présente
        if (class_exists('\Sello\Frontend\Shortcodes') && method_exists('\Sello\Frontend\Shortcodes', 'render_filters')) {
            echo \Sello\Frontend\Shortcodes::render_filters($filter_id, array());
            return;
        }

        // 3) Sinon, placeholder discret (admin/éditeur)
        if (current_user_can('manage_sello')) {
            echo '<div class="sello-widget-placeholder" style="padding:12px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc">';
            echo '<strong>'.esc_html__('SELLO Filters — Runtime helper missing', 'sello').'</strong><br/>';
            echo esc_html__('The shortcode or frontend renderer is not available yet. Ensure SELLO is up-to-date.', 'sello');
            echo '</div>';
        }
    }

    /**
     * Rendu dans l’éditeur (peut différer si besoin). Ici on réutilise render().
     */
    protected function _content_template()
    {
        // Elementor éditeur legacy (JS template) — on laisse vide pour éviter la duplication.
    }
}
