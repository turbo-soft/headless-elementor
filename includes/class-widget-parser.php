<?php
/**
 * Widget parser for extracting structured widget data.
 *
 * @package Headless_Elementor
 */

namespace Headless_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parses Elementor documents into structured widget data.
 */
class Widget_Parser {

    /**
     * Widget type handlers.
     *
     * @var array
     */
    private $widget_handlers = array();

    /**
     * Constructor.
     */
    public function __construct() {
        $this->register_default_handlers();
    }

    /**
     * Register default widget handlers.
     */
    private function register_default_handlers() {
        // Text widgets
        $this->widget_handlers['heading'] = array( $this, 'parse_heading_widget' );
        $this->widget_handlers['text-editor'] = array( $this, 'parse_text_editor_widget' );

        // Media widgets
        $this->widget_handlers['video'] = array( $this, 'parse_video_widget' );
        $this->widget_handlers['image'] = array( $this, 'parse_image_widget' );
        $this->widget_handlers['image-gallery'] = array( $this, 'parse_image_gallery_widget' );
        $this->widget_handlers['image-carousel'] = array( $this, 'parse_image_carousel_widget' );

        // Interactive widgets
        $this->widget_handlers['button'] = array( $this, 'parse_button_widget' );
        $this->widget_handlers['icon'] = array( $this, 'parse_icon_widget' );
        $this->widget_handlers['icon-box'] = array( $this, 'parse_icon_box_widget' );
        $this->widget_handlers['icon-list'] = array( $this, 'parse_icon_list_widget' );
        $this->widget_handlers['counter'] = array( $this, 'parse_counter_widget' );
        $this->widget_handlers['progress'] = array( $this, 'parse_progress_widget' );
        $this->widget_handlers['testimonial'] = array( $this, 'parse_testimonial_widget' );
        $this->widget_handlers['tabs'] = array( $this, 'parse_tabs_widget' );
        $this->widget_handlers['accordion'] = array( $this, 'parse_accordion_widget' );
        $this->widget_handlers['toggle'] = array( $this, 'parse_toggle_widget' );
        $this->widget_handlers['social-icons'] = array( $this, 'parse_social_icons_widget' );
        $this->widget_handlers['alert'] = array( $this, 'parse_alert_widget' );
        $this->widget_handlers['html'] = array( $this, 'parse_html_widget' );
        $this->widget_handlers['shortcode'] = array( $this, 'parse_shortcode_widget' );
        $this->widget_handlers['divider'] = array( $this, 'parse_divider_widget' );
        $this->widget_handlers['spacer'] = array( $this, 'parse_spacer_widget' );
        $this->widget_handlers['google_maps'] = array( $this, 'parse_google_maps_widget' );

        // Form widgets
        $this->widget_handlers['form'] = array( $this, 'parse_form_widget' );

        // Allow extensions.
        $this->widget_handlers = apply_filters( 'headless_elementor/widget_handlers', $this->widget_handlers );
    }

    /**
     * Parse a document into structured widget data.
     *
     * @param \Elementor\Core\Base\Document $document Elementor document.
     * @return array Structured widget data.
     */
    public function parse_document( $document ) {
        $elements = $document->get_elements_data();
        return $this->parse_elements( $elements );
    }

    /**
     * Parse elements recursively.
     *
     * @param array $elements Elements data.
     * @return array Parsed elements.
     */
    public function parse_elements( $elements ) {
        $parsed = array();

        foreach ( $elements as $element ) {
            $parsed_element = $this->parse_element( $element );
            if ( $parsed_element ) {
                $parsed[] = $parsed_element;
            }
        }

        return $parsed;
    }

    /**
     * Parse a single element.
     *
     * @param array $element Element data.
     * @return array|null Parsed element or null.
     */
    private function parse_element( $element ) {
        $element_type = isset( $element['elType'] ) ? $element['elType'] : '';
        $widget_type = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
        $settings = isset( $element['settings'] ) ? $element['settings'] : array();
        $id = isset( $element['id'] ) ? $element['id'] : '';

        $parsed = array(
            'id' => $id,
            'type' => $element_type,
        );

        // Handle containers/sections
        if ( in_array( $element_type, array( 'container', 'section', 'column' ), true ) ) {
            $parsed['settings'] = $this->parse_container_settings( $settings );

            // Parse children.
            if ( ! empty( $element['elements'] ) ) {
                $parsed['children'] = $this->parse_elements( $element['elements'] );
            }

            return $parsed;
        }

        // Handle widgets.
        if ( 'widget' === $element_type && $widget_type ) {
            $parsed['type'] = 'widget';
            $parsed['widgetType'] = $widget_type;

            // Use specific handler if available.
            if ( isset( $this->widget_handlers[ $widget_type ] ) ) {
                $parsed['data'] = call_user_func( $this->widget_handlers[ $widget_type ], $settings, $element );
            } else {
                // Generic handler for unknown widgets.
                $parsed['data'] = $this->parse_generic_widget( $settings, $element );
            }

            return $parsed;
        }

        return null;
    }

    /**
     * Parse container settings.
     *
     * @param array $settings Container settings.
     * @return array Parsed settings.
     */
    private function parse_container_settings( $settings ) {
        return array(
            'content_width' => $this->get_setting( $settings, 'content_width', 'boxed' ),
            'flex_direction' => $this->get_setting( $settings, 'flex_direction', '' ),
            'flex_wrap' => $this->get_setting( $settings, 'flex_wrap', '' ),
            'gap' => $this->get_setting( $settings, 'gap', array() ),
            'background_background' => $this->get_setting( $settings, 'background_background', '' ),
            'background_color' => $this->get_setting( $settings, 'background_color', '' ),
            'background_image' => $this->parse_image_setting( $settings, 'background_image' ),
        );
    }

    /**
     * Parse heading widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_heading_widget( $settings, $element ) {
        return array(
            'title' => $this->get_setting( $settings, 'title', '' ),
            'tag' => $this->get_setting( $settings, 'header_size', 'h2' ),
            'align' => $this->get_setting( $settings, 'align', '' ),
            'link' => $this->parse_link_setting( $settings, 'link' ),
        );
    }

    /**
     * Parse text editor widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_text_editor_widget( $settings, $element ) {
        return array(
            'content' => $this->get_setting( $settings, 'editor', '' ),
            'align' => $this->get_setting( $settings, 'align', '' ),
        );
    }

    /**
     * Parse video widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_video_widget( $settings, $element ) {
        $video_type = $this->get_setting( $settings, 'video_type', 'youtube' );

        $data = array(
            'videoType' => $video_type,
            'autoplay' => $this->get_setting( $settings, 'autoplay', '' ) === 'yes',
            'mute' => $this->get_setting( $settings, 'mute', '' ) === 'yes',
            'loop' => $this->get_setting( $settings, 'loop', '' ) === 'yes',
            'controls' => $this->get_setting( $settings, 'controls', 'yes' ) === 'yes',
            'showinfo' => $this->get_setting( $settings, 'showinfo', 'yes' ) === 'yes',
            'modestbranding' => $this->get_setting( $settings, 'modestbranding', '' ) === 'yes',
            'rel' => $this->get_setting( $settings, 'rel', '' ) === 'yes',
            'lazyLoad' => $this->get_setting( $settings, 'lazy_load', '' ) === 'yes',
            'aspectRatio' => $this->get_setting( $settings, 'aspect_ratio', '169' ),
        );

        switch ( $video_type ) {
            case 'youtube':
                $data['youtubeUrl'] = $this->get_setting( $settings, 'youtube_url', '' );
                $data['videoId'] = $this->extract_youtube_id( $data['youtubeUrl'] );
                $data['startTime'] = $this->get_setting( $settings, 'start', 0 );
                $data['endTime'] = $this->get_setting( $settings, 'end', 0 );
                break;

            case 'vimeo':
                $data['vimeoUrl'] = $this->get_setting( $settings, 'vimeo_url', '' );
                $data['videoId'] = $this->extract_vimeo_id( $data['vimeoUrl'] );
                $data['color'] = $this->get_setting( $settings, 'color', '' );
                break;

            case 'dailymotion':
                $data['dailymotionUrl'] = $this->get_setting( $settings, 'dailymotion_url', '' );
                $data['videoId'] = $this->extract_dailymotion_id( $data['dailymotionUrl'] );
                break;

            case 'hosted':
                $hosted_url = $this->get_setting( $settings, 'hosted_url', array() );
                $external_url = $this->get_setting( $settings, 'external_url', array() );
                $data['hostedUrl'] = isset( $hosted_url['url'] ) ? $hosted_url['url'] : '';
                $data['externalUrl'] = isset( $external_url['url'] ) ? $external_url['url'] : '';
                $data['download'] = $this->get_setting( $settings, 'download_button', '' ) === 'yes';
                $data['poster'] = $this->parse_image_setting( $settings, 'poster' );
                break;
        }

        // Image overlay (thumbnail).
        $data['imageOverlay'] = $this->get_setting( $settings, 'show_image_overlay', '' ) === 'yes';
        if ( $data['imageOverlay'] ) {
            $data['overlayImage'] = $this->parse_image_setting( $settings, 'image_overlay' );
            $data['playIcon'] = $this->get_setting( $settings, 'show_play_icon', 'yes' ) === 'yes';
        }

        return $data;
    }

    /**
     * Parse image widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_image_widget( $settings, $element ) {
        return array(
            'image' => $this->parse_image_setting( $settings, 'image' ),
            'imageSize' => $this->get_setting( $settings, 'image_size', 'full' ),
            'align' => $this->get_setting( $settings, 'align', '' ),
            'caption' => $this->get_setting( $settings, 'caption', '' ),
            'captionSource' => $this->get_setting( $settings, 'caption_source', 'none' ),
            'link' => $this->parse_link_setting( $settings, 'link' ),
            'linkTo' => $this->get_setting( $settings, 'link_to', 'none' ),
        );
    }

    /**
     * Parse image gallery widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_image_gallery_widget( $settings, $element ) {
        $gallery = $this->get_setting( $settings, 'gallery', array() );
        $images = array();

        foreach ( $gallery as $image ) {
            if ( isset( $image['id'] ) ) {
                $images[] = array(
                    'id' => $image['id'],
                    'url' => isset( $image['url'] ) ? $image['url'] : wp_get_attachment_url( $image['id'] ),
                );
            }
        }

        return array(
            'images' => $images,
            'imageSize' => $this->get_setting( $settings, 'thumbnail_size', 'medium' ),
            'columns' => $this->get_setting( $settings, 'gallery_columns', 4 ),
            'link' => $this->get_setting( $settings, 'gallery_link', 'file' ),
            'orderBy' => $this->get_setting( $settings, 'order_by', '' ),
            'lazyLoad' => $this->get_setting( $settings, 'lazyload', 'yes' ) === 'yes',
        );
    }

    /**
     * Parse image carousel widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_image_carousel_widget( $settings, $element ) {
        $carousel = $this->get_setting( $settings, 'carousel', array() );
        $slides = array();

        foreach ( $carousel as $slide ) {
            if ( isset( $slide['id'] ) || isset( $slide['url'] ) ) {
                $slides[] = array(
                    'id' => isset( $slide['id'] ) ? $slide['id'] : 0,
                    'url' => isset( $slide['url'] ) ? $slide['url'] : '',
                );
            }
        }

        return array(
            'slides' => $slides,
            'imageSize' => $this->get_setting( $settings, 'thumbnail_size', 'full' ),
            'slidesToShow' => $this->get_setting( $settings, 'slides_to_show', 3 ),
            'slidesToScroll' => $this->get_setting( $settings, 'slides_to_scroll', 1 ),
            'navigation' => $this->get_setting( $settings, 'navigation', 'both' ),
            'autoplay' => $this->get_setting( $settings, 'autoplay', 'yes' ) === 'yes',
            'autoplaySpeed' => $this->get_setting( $settings, 'autoplay_speed', 5000 ),
            'infinite' => $this->get_setting( $settings, 'infinite', 'yes' ) === 'yes',
            'pauseOnHover' => $this->get_setting( $settings, 'pause_on_hover', 'yes' ) === 'yes',
            'link' => $this->get_setting( $settings, 'link_to', 'none' ),
        );
    }

    /**
     * Parse button widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_button_widget( $settings, $element ) {
        return array(
            'text' => $this->get_setting( $settings, 'text', '' ),
            'link' => $this->parse_link_setting( $settings, 'link' ),
            'size' => $this->get_setting( $settings, 'size', 'sm' ),
            'icon' => $this->parse_icon_setting( $settings, 'selected_icon' ),
            'iconAlign' => $this->get_setting( $settings, 'icon_align', 'left' ),
            'buttonType' => $this->get_setting( $settings, 'button_type', '' ),
            'align' => $this->get_setting( $settings, 'align', '' ),
        );
    }

    /**
     * Parse icon widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_icon_widget( $settings, $element ) {
        return array(
            'icon' => $this->parse_icon_setting( $settings, 'selected_icon' ),
            'view' => $this->get_setting( $settings, 'view', 'default' ),
            'shape' => $this->get_setting( $settings, 'shape', 'circle' ),
            'link' => $this->parse_link_setting( $settings, 'link' ),
            'align' => $this->get_setting( $settings, 'align', '' ),
        );
    }

    /**
     * Parse icon box widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_icon_box_widget( $settings, $element ) {
        return array(
            'icon' => $this->parse_icon_setting( $settings, 'selected_icon' ),
            'view' => $this->get_setting( $settings, 'view', 'default' ),
            'shape' => $this->get_setting( $settings, 'shape', 'circle' ),
            'title' => $this->get_setting( $settings, 'title_text', '' ),
            'description' => $this->get_setting( $settings, 'description_text', '' ),
            'link' => $this->parse_link_setting( $settings, 'link' ),
            'position' => $this->get_setting( $settings, 'position', 'top' ),
            'titleTag' => $this->get_setting( $settings, 'title_size', 'h3' ),
        );
    }

    /**
     * Parse icon list widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_icon_list_widget( $settings, $element ) {
        $items = $this->get_setting( $settings, 'icon_list', array() );
        $parsed_items = array();

        foreach ( $items as $item ) {
            $parsed_items[] = array(
                'text' => isset( $item['text'] ) ? $item['text'] : '',
                'icon' => $this->parse_icon_setting( $item, 'selected_icon' ),
                'link' => $this->parse_link_setting( $item, 'link' ),
            );
        }

        return array(
            'items' => $parsed_items,
            'view' => $this->get_setting( $settings, 'view', 'traditional' ),
        );
    }

    /**
     * Parse counter widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_counter_widget( $settings, $element ) {
        return array(
            'startingNumber' => $this->get_setting( $settings, 'starting_number', 0 ),
            'endingNumber' => $this->get_setting( $settings, 'ending_number', 100 ),
            'prefix' => $this->get_setting( $settings, 'prefix', '' ),
            'suffix' => $this->get_setting( $settings, 'suffix', '' ),
            'duration' => $this->get_setting( $settings, 'duration', 2000 ),
            'thousandSeparator' => $this->get_setting( $settings, 'thousand_separator', '' ) === 'yes',
            'title' => $this->get_setting( $settings, 'title', '' ),
        );
    }

    /**
     * Parse progress widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_progress_widget( $settings, $element ) {
        return array(
            'title' => $this->get_setting( $settings, 'title', '' ),
            'progressType' => $this->get_setting( $settings, 'progress_type', '' ),
            'percent' => $this->get_setting( $settings, 'percent', 50 ),
            'displayPercent' => $this->get_setting( $settings, 'display_percentage', '' ) === 'show',
            'innerText' => $this->get_setting( $settings, 'inner_text', '' ),
        );
    }

    /**
     * Parse testimonial widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_testimonial_widget( $settings, $element ) {
        return array(
            'content' => $this->get_setting( $settings, 'testimonial_content', '' ),
            'image' => $this->parse_image_setting( $settings, 'testimonial_image' ),
            'name' => $this->get_setting( $settings, 'testimonial_name', '' ),
            'job' => $this->get_setting( $settings, 'testimonial_job', '' ),
            'imagePosition' => $this->get_setting( $settings, 'testimonial_image_position', 'aside' ),
            'alignment' => $this->get_setting( $settings, 'testimonial_alignment', 'center' ),
        );
    }

    /**
     * Parse tabs widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_tabs_widget( $settings, $element ) {
        $tabs = $this->get_setting( $settings, 'tabs', array() );
        $parsed_tabs = array();

        foreach ( $tabs as $index => $tab ) {
            $parsed_tabs[] = array(
                'id' => isset( $tab['_id'] ) ? $tab['_id'] : 'tab-' . $index,
                'title' => isset( $tab['tab_title'] ) ? $tab['tab_title'] : '',
                'content' => isset( $tab['tab_content'] ) ? $tab['tab_content'] : '',
            );
        }

        return array(
            'tabs' => $parsed_tabs,
            'type' => $this->get_setting( $settings, 'type', 'horizontal' ),
        );
    }

    /**
     * Parse accordion widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_accordion_widget( $settings, $element ) {
        $tabs = $this->get_setting( $settings, 'tabs', array() );
        $parsed_tabs = array();

        foreach ( $tabs as $index => $tab ) {
            $parsed_tabs[] = array(
                'id' => isset( $tab['_id'] ) ? $tab['_id'] : 'accordion-' . $index,
                'title' => isset( $tab['tab_title'] ) ? $tab['tab_title'] : '',
                'content' => isset( $tab['tab_content'] ) ? $tab['tab_content'] : '',
            );
        }

        return array(
            'items' => $parsed_tabs,
            'icon' => $this->parse_icon_setting( $settings, 'selected_icon' ),
            'iconActive' => $this->parse_icon_setting( $settings, 'selected_active_icon' ),
        );
    }

    /**
     * Parse toggle widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_toggle_widget( $settings, $element ) {
        $tabs = $this->get_setting( $settings, 'tabs', array() );
        $parsed_tabs = array();

        foreach ( $tabs as $index => $tab ) {
            $parsed_tabs[] = array(
                'id' => isset( $tab['_id'] ) ? $tab['_id'] : 'toggle-' . $index,
                'title' => isset( $tab['tab_title'] ) ? $tab['tab_title'] : '',
                'content' => isset( $tab['tab_content'] ) ? $tab['tab_content'] : '',
            );
        }

        return array(
            'items' => $parsed_tabs,
            'icon' => $this->parse_icon_setting( $settings, 'selected_icon' ),
            'iconActive' => $this->parse_icon_setting( $settings, 'selected_active_icon' ),
        );
    }

    /**
     * Parse social icons widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_social_icons_widget( $settings, $element ) {
        $icons = $this->get_setting( $settings, 'social_icon_list', array() );
        $parsed_icons = array();

        foreach ( $icons as $item ) {
            $parsed_icons[] = array(
                'icon' => $this->parse_icon_setting( $item, 'social_icon' ),
                'link' => $this->parse_link_setting( $item, 'link' ),
                'label' => isset( $item['social'] ) ? $item['social'] : '',
            );
        }

        return array(
            'icons' => $parsed_icons,
            'shape' => $this->get_setting( $settings, 'shape', 'rounded' ),
            'align' => $this->get_setting( $settings, 'align', 'center' ),
        );
    }

    /**
     * Parse alert widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_alert_widget( $settings, $element ) {
        return array(
            'alertType' => $this->get_setting( $settings, 'alert_type', 'info' ),
            'title' => $this->get_setting( $settings, 'alert_title', '' ),
            'description' => $this->get_setting( $settings, 'alert_description', '' ),
            'showDismiss' => $this->get_setting( $settings, 'show_dismiss', 'show' ) === 'show',
        );
    }

    /**
     * Parse HTML widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_html_widget( $settings, $element ) {
        return array(
            'html' => $this->get_setting( $settings, 'html', '' ),
        );
    }

    /**
     * Parse shortcode widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_shortcode_widget( $settings, $element ) {
        $shortcode = $this->get_setting( $settings, 'shortcode', '' );
        return array(
            'shortcode' => $shortcode,
            'rendered' => do_shortcode( $shortcode ),
        );
    }

    /**
     * Parse divider widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_divider_widget( $settings, $element ) {
        return array(
            'style' => $this->get_setting( $settings, 'style', 'solid' ),
            'weight' => $this->get_setting( $settings, 'weight', array() ),
            'color' => $this->get_setting( $settings, 'color', '' ),
            'width' => $this->get_setting( $settings, 'width', array() ),
            'align' => $this->get_setting( $settings, 'align', '' ),
            'element' => $this->get_setting( $settings, 'look', 'line' ),
            'text' => $this->get_setting( $settings, 'text', '' ),
            'icon' => $this->parse_icon_setting( $settings, 'icon' ),
        );
    }

    /**
     * Parse spacer widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_spacer_widget( $settings, $element ) {
        return array(
            'space' => $this->get_setting( $settings, 'space', array( 'size' => 50 ) ),
        );
    }

    /**
     * Parse Google Maps widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_google_maps_widget( $settings, $element ) {
        return array(
            'address' => $this->get_setting( $settings, 'address', '' ),
            'zoom' => $this->get_setting( $settings, 'zoom', array( 'size' => 10 ) ),
            'height' => $this->get_setting( $settings, 'height', array( 'size' => 300 ) ),
        );
    }

    /**
     * Parse form widget (Elementor Pro).
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_form_widget( $settings, $element ) {
        $fields = $this->get_setting( $settings, 'form_fields', array() );
        $parsed_fields = array();

        foreach ( $fields as $field ) {
            $parsed_fields[] = array(
                'id' => isset( $field['custom_id'] ) ? $field['custom_id'] : '',
                'type' => isset( $field['field_type'] ) ? $field['field_type'] : 'text',
                'label' => isset( $field['field_label'] ) ? $field['field_label'] : '',
                'placeholder' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
                'required' => isset( $field['required'] ) ? $field['required'] === 'true' : false,
                'width' => isset( $field['width'] ) ? $field['width'] : '100',
                'options' => isset( $field['field_options'] ) ? $field['field_options'] : '',
            );
        }

        return array(
            'formName' => $this->get_setting( $settings, 'form_name', '' ),
            'fields' => $parsed_fields,
            'buttonText' => $this->get_setting( $settings, 'button_text', 'Submit' ),
            'buttonSize' => $this->get_setting( $settings, 'button_size', 'sm' ),
            'buttonAlign' => $this->get_setting( $settings, 'button_align', 'stretch' ),
        );
    }

    /**
     * Parse generic/unknown widget.
     *
     * @param array $settings Widget settings.
     * @param array $element Full element data.
     * @return array Parsed data.
     */
    private function parse_generic_widget( $settings, $element ) {
        // Return raw settings for unknown widgets.
        return array(
            'rawSettings' => $settings,
        );
    }

    /**
     * Helper: Get setting value.
     *
     * @param array  $settings Settings array.
     * @param string $key      Setting key.
     * @param mixed  $default  Default value.
     * @return mixed
     */
    private function get_setting( $settings, $key, $default = '' ) {
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Helper: Parse link setting.
     *
     * @param array  $settings Settings array.
     * @param string $key      Setting key.
     * @return array|null
     */
    private function parse_link_setting( $settings, $key ) {
        $link = $this->get_setting( $settings, $key, array() );

        if ( empty( $link ) || empty( $link['url'] ) ) {
            return null;
        }

        return array(
            'url' => $link['url'],
            'isExternal' => isset( $link['is_external'] ) && $link['is_external'] === 'on',
            'nofollow' => isset( $link['nofollow'] ) && $link['nofollow'] === 'on',
        );
    }

    /**
     * Helper: Parse image setting.
     *
     * @param array  $settings Settings array.
     * @param string $key      Setting key.
     * @return array|null
     */
    private function parse_image_setting( $settings, $key ) {
        $image = $this->get_setting( $settings, $key, array() );

        if ( empty( $image ) ) {
            return null;
        }

        return array(
            'id' => isset( $image['id'] ) ? $image['id'] : 0,
            'url' => isset( $image['url'] ) ? $image['url'] : '',
            'alt' => isset( $image['alt'] ) ? $image['alt'] : '',
        );
    }

    /**
     * Helper: Parse icon setting.
     *
     * @param array  $settings Settings array.
     * @param string $key      Setting key.
     * @return array|null
     */
    private function parse_icon_setting( $settings, $key ) {
        $icon = $this->get_setting( $settings, $key, array() );

        if ( empty( $icon ) ) {
            return null;
        }

        return array(
            'library' => isset( $icon['library'] ) ? $icon['library'] : '',
            'value' => isset( $icon['value'] ) ? $icon['value'] : '',
        );
    }

    /**
     * Helper: Extract YouTube video ID.
     *
     * @param string $url YouTube URL.
     * @return string|null Video ID or null.
     */
    private function extract_youtube_id( $url ) {
        if ( empty( $url ) ) {
            return null;
        }

        $pattern = '/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        if ( preg_match( $pattern, $url, $matches ) ) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Helper: Extract Vimeo video ID.
     *
     * @param string $url Vimeo URL.
     * @return string|null Video ID or null.
     */
    private function extract_vimeo_id( $url ) {
        if ( empty( $url ) ) {
            return null;
        }

        $pattern = '/vimeo\.com\/(?:video\/)?(\d+)/';
        if ( preg_match( $pattern, $url, $matches ) ) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Helper: Extract Dailymotion video ID.
     *
     * @param string $url Dailymotion URL.
     * @return string|null Video ID or null.
     */
    private function extract_dailymotion_id( $url ) {
        if ( empty( $url ) ) {
            return null;
        }

        $pattern = '/dailymotion\.com\/(?:video|embed\/video)\/([a-zA-Z0-9]+)/';
        if ( preg_match( $pattern, $url, $matches ) ) {
            return $matches[1];
        }

        return null;
    }
}
