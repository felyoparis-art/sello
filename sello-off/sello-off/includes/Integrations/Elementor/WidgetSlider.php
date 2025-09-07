<?php
/**
 * Elementor Widget — SELLO Slider (Hero / Aero-navigation)
 * Compat Elementor 3.5+ / PHP 7.0+
 *
 * Affiche le SLIDER lié à un FILTRE (post type: sello_filter) dans une page Elementor.
 * - Sélecteur du Filter à rendre
 * - Options de rendu (variant visuel, limiter le nombre d’items, afficher les "selected filters")
 * - Dégrade proprement si aucun Filter sélectionné / runtime manquant
 *
 * Rendu :
 *  - Utilise le shortcode [sello_slider id="123" variant="buttons|titles|image|frame" max="12" chips="yes|no"]
 *    si disponible ;
 *  - Sinon appelle \Sello\Frontend\Shortcodes::render_slider($filter_id, $args) si présent ;
 *  - Sinon affiche un placeholder d’aide côté éditeur uniquement.
 */

namespace Sello\Integrations\Elementor;

defined('ABSPATH') || exit;

// Si Elementor n'est pas présent, on déclare une coquille vide pour éviter les fatals.
if (!class_exists('\Elementor\Widget_Base')) {
    class WidgetSlider {}
    return;
}

class WidgetSlider extends \Elementor\Widget_Base
{
    /* =========================================
     * Métadonnées du widget
     * ======================================= */

    public function get_name()
    {
        return 'sello_slider';
    }

    public function get_title()
    {
        return __('SELLO — Slider (Hero)', 'sello');
    }

    public function get_icon()
    {
        return 'eicon-slider-push';
    }

    public function get_categories()
    {
        return array('sello');
    }

    public function get_keywords()
    {
        return array('slider', 'hero', 'navigation', 'categories', 'sello', 'woocommerce');
    }

    /* =========================================
     * Contrôles de l’éditeur
     * ======================================= */

    protected function _register_controls() // compat <3.6
    {
        $this->register_controls();
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_sello_slider',
            array(
                'label' => __('SELLO Slider', 'sello'),
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
                'description' => __('Pick a SELLO Filter (created under SELLO → Filters). The slider configuration is read from this Filter.', 'sello'),
            )
        );

        $this->add_control(
            'variant',
            array(
                'label'       => __('Variant', 'sello'),
                'type'        => \Elementor\Controls_Manager::SELECT,
                'default'     => 'image',
                'options'     => array(
                    'buttons' => __('Buttons', 'sello'),
                    'titles'  => __('Titles', 'sello'),
                    'image'   => __('Image (caption below)', 'sello'),
                    'frame'   => __('Framed (image inside card)', 'sello'),
                ),
                'description' => __('Visual style override. If your theme/shortcode ignores it, it will fall back to its default.', 'sello'),
            )
        );

        $this->add_control(
            'max_items',
            array(
                'label'       => __('Max items', 'sello'),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'min'         => 3,
                'max'         => 50,
                'step'        => 1,
                'default'     => 12,
                'description' => __('Limit how many items to display in the slider.', 'sello'),
            )
        );

        $this->add_control(
            'show_selected',
            array(
                'label'        => __('Show Selected Filters row', 'sello'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'sello'),
                'label_off'    => __('No', 'sello'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => __('If supported, shows the chips of currently selected filters above/below.', 'sello'),
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
                'description'  => __('Hide theme native filter widgets around this area (cosmetic).', 'sello'),
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
        $settings   = $this->get_settings_for_display();
        $filter_id  = isset($settings['filter_id']) ? (int)$settings['filter_id'] : 0;
        $variant    = isset($settings['variant']) ? (string)$settings['variant'] : 'image';
        $max_items  = isset($settings['max_items']) && is_numeric($settings['max_items']) ? (int)$settings['max_items'] : 12;
        $chips_yes  = (isset($settings['show_selected']) && $settings['show_selected'] === 'yes') ? 'yes' : 'no';
        $takeover   = isset($settings['force_takeover']) && $settings['force_takeover'] === 'yes';

        if ($filter_id <= 0) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="sello-widget-placeholder" style="padding:12px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc">';
                echo '<strong>'.esc_html__('SELLO Slider — Not configured', 'sello').'</strong><br/>';
                echo esc_html__('Choose a Filter in the widget settings.', 'sello');
                echo '</div>';
            }
            return;
        }

        // Optionnel : takeover CSS
        if ($takeover) {
            if (class_exists('\Sello\Domain\Design')) {
                \Sello\Domain\Design::emit_takeover_css();
            } else {
                echo '<style>.widget_layered_nav,.widget_product_categories .children,.widget_price_filter{display:none!important}</style>';
            }
        }

        // Préférence : shortcode si présent
        if (shortcode_exists('sello_slider')) {
            $short = sprintf(
                '[sello_slider id="%d" variant="%s" max="%d" chips="%s"]',
                (int)$filter_id,
                esc_attr($variant),
                (int)$max_items,
                esc_attr($chips_yes)
            );
            echo do_shortcode($short);
            return;
        }

        // Fallback : appel direct d’un renderer si disponible
        if (class_exists('\Sello\Frontend\Shortcodes') && method_exists('\Sello\Frontend\Shortcodes', 'render_slider')) {
            echo \Sello\Frontend\Shortcodes::render_slider($filter_id, array(
                'variant' => $variant,
                'max'     => $max_items,
                'chips'   => ($chips_yes === 'yes'),
            ));
            return;
        }

        // Dernier recours : placeholder (admin)
        if (current_user_can('manage_sello')) {
            echo '<div class="sello-widget-placeholder" style="padding:12px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc">';
            echo '<strong>'.esc_html__('SELLO Slider — Runtime helper missing', 'sello').'</strong><br/>';
            echo esc_html__('The shortcode or frontend renderer is not available yet. Ensure SELLO is up-to-date.', 'sello');
            echo '</div>';
        }
    }

    protected function _content_template()
    {
        // Template JS non nécessaire ; on laisse Elementor appeler render().
    }
}
