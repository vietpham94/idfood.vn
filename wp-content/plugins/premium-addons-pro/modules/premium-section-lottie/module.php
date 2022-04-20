<?php

/**
 * Class: Module
 * Name: Section Lottie
 * Slug: premium-lottie
 */

namespace PremiumAddonsPro\Modules\PremiumSectionLottie;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Repeater;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Helper_Functions;
use PremiumAddonsPro\Base\Module_Base;

if( !defined( 'ABSPATH' ) ) exit;

class Module extends Module_Base {
    
    public function __construct() {
        
        parent::__construct();

        $modules = Admin_Helper::get_enabled_elements();
        
        //Checks if Section Lottie is enabled
        $lottie = $modules['premium-lottie'];

        if( ! $lottie ) {
            return;
        }
            
        //Enqueue the required JS file
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        //Register Controls inside Section/Column Layout tab
        add_action( 'elementor/element/section/section_layout/after_section_end',array( $this,'register_controls' ), 10 );

        add_action( 'elementor/section/print_template', array( $this, '_print_template' ), 10, 2 );

        //insert data before section rendering
        add_action( 'elementor/frontend/section/before_render',array( $this,'before_render' ), 10, 1 );
        
    }
    
    /**
	 * Enqueue scripts.
	 *
	 * Registers required dependencies for the extension and enqueues them.
	 *
	 * @since 1.6.5
	 * @access public
	 */
	public function enqueue_scripts() {
        
        if ( ( true === \Elementor\Plugin::$instance->db->is_built_with_elementor( get_the_ID() ) ) || ( function_exists( 'elementor_location_exits' ) && ( elementor_location_exits( 'archive', true ) || elementor_location_exits( 'single', true ) ) ) ) {
            wp_add_inline_script(
                'elementor-frontend',
                'window.scopes_array = {};
                window.backend = 0;
                jQuery( window ).on( "elementor/frontend/init", function() {
                    
                    elementorFrontend.hooks.addAction( "frontend/element_ready/section", function( $scope, $ ){
                        if ( "undefined" == typeof $scope ) {
                                return;
                        }
                        if ( $scope.hasClass( "premium-lottie-yes" ) ) {
                            var id = $scope.data("id");
                            window.scopes_array[ id ] = $scope;
                        }
                        if(elementorFrontend.isEditMode()){
                            
                            var url = papro_addons.lottie_url;
                            jQuery.cachedAssets = function( url, options ) {
                                // Allow user to set any option except for dataType, cache, and url.
                                options = jQuery.extend( options || {}, {
                                    dataType: "script",
                                    cache: true,
                                    url: url
                                });
                                // Return the jqXHR object so we can chain callbacks.
                                return jQuery.ajax( options );
                            };
                            jQuery.cachedAssets( url );
                            window.backend = 1;
                        }
                    });
                });
                jQuery(document).ready(function(){
                    if ( jQuery.find( ".premium-lottie-yes" ).length < 1 ) {
                        return;
                    }
                    var url = papro_addons.lottie_url;
                    
                    jQuery.cachedAssets = function( url, options ) {
                        // Allow user to set any option except for dataType, cache, and url.
                        options = jQuery.extend( options || {}, {
                            dataType: "script",
                            cache: true,
                            url: url
                        });
                        
                        // Return the jqXHR object so we can chain callbacks.
                        return jQuery.ajax( options );
                    };
                    jQuery.cachedAssets( url );
                });	'
            );
        }
	}
    
    /**
	 * Register Lottie Animations controls.
	 *
	 * @since 1.9.4
	 * @access public
	 * @param object $element for current element.
	 */
    public function register_controls( $element ) {
        
        $element->start_controls_section('section_premium_lottie',
            [
                'label'         => sprintf( '<i class="pa-extension-icon pa-dash-icon"></i> %s', __('Lottie Animations', 'premium-addons-pro') ),
                'tab'           => Controls_Manager::TAB_LAYOUT
            ]
        );
        
        $element->add_control('premium_lottie_switcher',
            [
                'label'         => __( 'Enable Lottie Animations', 'premium-addons-pro' ),
                'type'          => Controls_Manager::SWITCHER,
                'return_value'  => 'yes',
                'prefix_class'  => 'premium-lottie-'
            ]
        );

        $element->add_control('lottie_notice',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => sprintf( __( '%1$s How to speed up pages with many Lottie animations » %2$s', 'premium-addons-pro' ), '<a href="https://premiumaddons.com/docs/how-to-speed-up-elementor-pages-with-many-lottie-animations/?utm_source=pa-dashboard&utm_medium=pa-editor&utm_campaign=pa-plugin" target="_blank" rel="noopener">', '</a>' ),
                'content_classes'  => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control('source',
            [
                'label'     => __('File Source', 'premium-addons-pro'),
                'type'      => Controls_Manager::SELECT,
                'options'   => [
                    'url'       => __('External URL','premium-addons-pro'),
                    'file'      => __('Media File', 'premium-addons-pro')
                ],
                'default'   => 'url'
            ]
        );
        
        $repeater->add_control('lottie_url', 
            [
                'label'             => __( 'Animation JSON URL', 'premium-addons-pro' ),
                'type'              => Controls_Manager::TEXT,
                'dynamic'           => [ 'active' => true ],
                'description'       => 'Get JSON code URL from <a href="https://lottiefiles.com/" target="_blank">here</a>',
                'label_block'       => true,
                'condition'     => [
                    'source'   => 'url'
                ]
            ]
        );

        $repeater->add_control('lottie_file',
			[
				'label'     => __( 'Upload JSON File', 'elementor-pro' ),
				'type'      => Controls_Manager::MEDIA,
				'media_type'=> 'application/json',
				'frontend_available' => true,
				'condition' => [
					'source' => 'file',
				],
			]
		);

        $repeater->add_control('lottie_loop',
            [
                'label'         => __('Loop','premium-addons-pro'),
                'type'          => Controls_Manager::SWITCHER,
                'return_value'  => 'true',
                'default'       => 'true',
            ]
        );

        $repeater->add_control('lottie_reverse',
            [
                'label'         => __('Reverse','premium-addons-pro'),
                'type'          => Controls_Manager::SWITCHER,
                'return_value'  => 'true',
            ]
        );

        $repeater->add_control('lottie_speed',
			[
                'label'         => __( 'Speed', 'premium-addons-pro' ),
                'type'          => Controls_Manager::NUMBER,
                'default'       => 1,
                'min'           => 0.1,
                'max'           => 3,
                'step'          => 0.1
			]
        );

        $repeater->add_control('hover_action',
            [
                'label'         => __('Hover Action', 'premium-addons-pro'),
                'type'          => Controls_Manager::SELECT,
                'options'       => [
                    'none'         => __('None', 'premium-addons-pro'),
                    'play'         => __('Play', 'premium-addons-pro'),
                    'pause'        => __('Pause', 'premium-addons-pro'),
                ],
                'default'       => 'none',
            ]
        );

        $repeater->add_control('start_on_visible',
            [
                'label'         => __('Start Animation On Viewport','premium-addons-pro'),
                'type'          => Controls_Manager::SWITCHER,
                'description'   => __('Enable this option if you want the animation to start when the element is visible on the viewport', 'premium-addons-pro'),
                'return_value'  => 'true',
                'condition'     => [
                    'hover_action!'     => 'play',
                    'animate_on_scroll!'   => 'true'
                ]
            ]
        );

        $repeater->add_control('animate_on_scroll',
            [
                'label'         => __('Animate On Scroll','premium-addons-pro'),
                'type'          => Controls_Manager::SWITCHER,
                'return_value'  => 'true',
                'condition'     => [
                    'hover_action!'  => 'play',
                    'start_on_visible!' => 'true',
                    'lottie_reverse!'   => 'true'
                ]
            ]
        );

        $repeater->add_control('premium_lottie_animate_speed',
			[
				'label'         => __( 'Speed', 'premium-addons-pro' ),
				'type'          => Controls_Manager::SLIDER,
				'default' => [
                    'size' => 4,
                ],
                'range' => [
                    'px' => [
                        'max' => 10,
                        'step' => 0.1,
                    ],
                ],
				'condition'     => [
                    'hover_action!'  => 'play',
                    'animate_on_scroll' => 'true',
                    'lottie_reverse!'   => 'true'
				],
			]
		);
        
        $repeater->add_control('premium_lottie_animate_view',
			[
				'label'         => __( 'Viewport', 'premium-addons-pro' ),
				'type'          => Controls_Manager::SLIDER,
				'default' => [
                    'sizes' => [
                        'start' => 0,
                        'end' => 100,
                    ],
                    'unit' => '%',
                ],
                'labels' => [
                    __( 'Bottom', 'premium-addons-pro' ),
                    __( 'Top', 'premium-addons-pro' ),
                ],
                'scales' => 1,
                'handles' => 'range',
                'condition'     => [
                    'hover_action!'  => 'play',
                    'animate_on_scroll' => 'true',
                    'lottie_reverse!'   => 'true'
				],
			]
        );

        $repeater->add_control('lottie_renderer', 
            [
                'label'         => __('Render As', 'premium-addons-pro'),
                'type'          => Controls_Manager::SELECT,
                'options'       => [
                    'svg'    => __('SVG', 'premium-addons-pro'),
                    'canvas' => __('Canvas', 'premium-addons-pro'),
                ],
                'default'       => 'svg',
                'classes'       => 'editor-pa-spacer',
                'render_type'   => 'template',
                'label_block'   => true,
            ]
        );

        $repeater->add_control('render_notice', 
            [
                'raw'               => __('Set render type to canvas if you\'re having performance issues on the page.', 'premium-addons-pro'),
                'type'              => Controls_Manager::RAW_HTML,
                'content_classes'   => 'elementor-panel-alert elementor-panel-alert-info',
            ] 
        );

        $repeater->add_responsive_control('premium_lottie_hor',
            [
                'label'         => __('Horizontal Position (%)', 'premium-addons-pro'),
                'type'          => Controls_Manager::SLIDER,
                'default'       => [
                    'size'  => 50
                ],
                'min'           => 0,
                'max'           => 100,
                'label_block'   => true,
                'separator'     => 'before',
                'selectors'     => [
                    '{{WRAPPER}} {{CURRENT_ITEM}}' => 'left: {{SIZE}}%',
                ],
            ]
        );
        
        $repeater->add_responsive_control('premium_lottie_ver',
            [
                'label'         => __('Vertical Position (%)', 'premium-addons-pro'),
                'type'          => Controls_Manager::SLIDER,
                'default'       => [
                    'size'  => 50
                ],
                'min'           => 0,
                'max'           => 100,
                'label_block'   => true,
                'selectors'     => [
                    '{{WRAPPER}} {{CURRENT_ITEM}}' => 'top: {{SIZE}}%',
                ],
            ]
        );
        
        $repeater->add_responsive_control('premium_lottie_size',
            [
                'label'         => __('Size', 'premium-addons-pro'),
                'type'          => Controls_Manager::SLIDER,
                'size_units'    => ['px', 'em'],
                'range'         => [
                    'px'    => [
                        'min'   => 1,
                        'max'   => 600
                    ],
                    'em'    => [
                        'min'   => 1,
                        'max'   => 60
                    ],
                ],
                'label_block'   => true,
                'separator'     => 'before',
                'selectors'     => [
                    '{{WRAPPER}} {{CURRENT_ITEM}}.premium-lottie-canvas, {{WRAPPER}} {{CURRENT_ITEM}}.premium-lottie-svg svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}',
                ],
            ]
        );

        $repeater->add_control('premium_lottie_opacity',
			[
				'label'         => __( 'Opacity', 'premium-addons-pro' ),
				'type'          => Controls_Manager::SLIDER,
				'range'         => [
					'px'    => [
		                'min' => 0,
		                'max' => 1,
		                'step'=> .1,
		            ]
				],
				'selectors'     => [
		            '{{WRAPPER}} {{CURRENT_ITEM}}' => 'opacity: {{SIZE}};',
		        ],
			]
        );
        
        $repeater->add_control('premium_lottie_rotate',
            [
                'label'         => __('Rotate', 'premium-addons-pro'),
                'type'          => Controls_Manager::SLIDER,
                'description'   => __('Set rotation value in degrees', 'premium-addons-pro'),
                'range'         => [
                    'px'    => [
                        'min'   => -180,
                        'max'   => 180,    
                    ]
                ],
                'default'       => [
                    'size'  => 0,
                ],
                'selectors'     => [
                    '{{WRAPPER}} {{CURRENT_ITEM}}' => 'transform: rotate({{SIZE}}deg)'
                ],
            ]
        );

        $repeater->add_control('premium_lottie_background', 
            [
                'label'         => __('Background Color', 'premium-addons-pro'),
                'type'          => Controls_Manager::COLOR,
                'selectors'     => [
                    '{{WRAPPER}} {{CURRENT_ITEM}}' => 'background-color: {{VALUE}}',
                ],
            ]
        );
        
        $repeater->add_group_control(
            Group_Control_Border::get_type(), 
            [
                'name'          => 'premium_lottie_border',
                'selector'      => '{{WRAPPER}} {{CURRENT_ITEM}}',
            ]
        );

        $repeater->add_control('premium_lottie_radius',
            [
                'label'         => __('Border Radius', 'premium-addons-pro'),
                'type'          => Controls_Manager::SLIDER,
                'size_units'    => ['px', '%' ,'em'],
                'selectors'     => [
                    '{{WRAPPER}} {{CURRENT_ITEM}}' => 'border-radius: {{SIZE}}{{UNIT}};'
                ]
            ]
        );

        $repeater->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'          => 'premium_lottie_shadow',
                'selector'      => '{{WRAPPER}} {{CURRENT_ITEM}}',
            ]
        );

        $repeater->add_responsive_control('premium_lottie_padding',
            [
                'label'         => __('Padding', 'premium-addons-pro'),
                'type'          => Controls_Manager::DIMENSIONS,
                'size_units'    => ['px', 'em', '%'],
                'selectors'     => [
                    '{{WRAPPER}} {{CURRENT_ITEM}}' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'
                ]
            ]
        );

        $repeater->add_control('premium_lottie_parallax',
            [
                'label'         => __('Scroll Parallax', 'premium-addons-pro'),
                'description'   => __('Enable or disable vertical scroll parallax','premium-addons-pro'),
                'type'          => Controls_Manager::SWITCHER,
            ]
        );
        
        $repeater->add_control('premium_lottie_parallax_direction',
			[
				'label'         => __( 'Direction', 'premium-addons-pro' ),
				'type'          => Controls_Manager::SELECT,
				'options'       => [
					'up'    => __( 'Up', 'premium-addons-pro' ),
					'down'  => __( 'Down', 'premium-addons-pro' ),
				],
                'default'       => 'down',
				'condition'     => [
					'premium_lottie_parallax'     => 'yes'
				],
			]
		);
        
        $repeater->add_control('premium_lottie_parallax_speed',
			[
				'label'         => __( 'Speed', 'premium-addons-pro' ),
				'type'          => Controls_Manager::SLIDER,
				'default' => [
                    'size' => 4,
                ],
                'range' => [
                    'px' => [
                        'max' => 10,
                        'step' => 0.1,
                    ],
                ],
				'condition'     => [
                    'premium_lottie_parallax' => 'yes'
				],
			]
		);
        
        $repeater->add_control('premium_lottie_parallax_view',
			[
				'label'         => __( 'Viewport', 'premium-addons-pro' ),
				'type'          => Controls_Manager::SLIDER,
				'default' => [
                    'sizes' => [
                        'start' => 0,
                        'end' => 100,
                    ],
                    'unit' => '%',
                ],
                'labels' => [
                    __( 'Bottom', 'premium-addons-pro' ),
                    __( 'Top', 'premium-addons-pro' ),
                ],
                'scales' => 1,
                'handles' => 'range',
                'condition'     => [
                    'premium_lottie_parallax' => 'yes'
				],
			]
        );
        
        $repeater->add_control('premium_lottie_zindex',
			[
                'label'         => __( 'z-index', 'premium-addons-pro' ),
                'description'   => __( 'Set z-index for the current layer', 'premium-addons-pro' ),
                'type'          => Controls_Manager::NUMBER,
                'classes'       => 'editor-pa-spacer',
                'default'       => 2,
                'selectors'     => [
                    '{{WRAPPER}} {{CURRENT_ITEM}}' => 'z-index: {{VALUE}}'
                ]
			]
        );
        
        $repeater->add_control('show_layer_on',
            [
                'label'             => __('Show Layer On', 'premium-addons-pro'),
                'type'              => Controls_Manager::SELECT2,
                'options'           => [
                    'desktop'   => __('Desktop','premium-addons-pro'),
                    'tablet'    => __('Tablet','premium-addons-pro'),
                    'mobile'    => __('Mobile','premium-addons-pro'),
                ],
                'default'           => [ 'desktop', 'tablet', 'mobile' ],
                'multiple'          => true,
                'separator'         => 'before',
                'label_block'       => true,
            ]
        );

        $element->add_control('premium_lottie_repeater',
            [
                'type'          => Controls_Manager::REPEATER,
                'fields'        => $repeater->get_controls(),
                'prevent_empty' => false,
                'condition'     => [
                    'premium_lottie_switcher'    => 'yes'
                ]
            ]
        );

        $element->end_controls_section();
        
    }

    /**
	 * Render Lottie Animations output in the editor.
	 *
	 * Written as a Backbone JavaScript template and used to generate the live preview.
	 *
	 * @since 1.9.4
	 * @access public
	 * @param object $template for current template.
	 * @param object $widget for current widget.
	 */
	public function _print_template( $template, $widget ) {
        
		if ( $widget->get_name() !== 'section' ) {
			return $template;
		}
		$old_template = $template;
		ob_start();
		?>
		<# if( 'yes' === settings.premium_lottie_switcher ) {

			view.addRenderAttribute( 'lottie_data', 'id', 'premium-lottie-' + view.getID() );
			view.addRenderAttribute( 'lottie_data', 'class', 'premium-lottie-wrapper' );

            view.addRenderAttribute( 'lottie_data', 'data-pa-lottie', JSON.stringify( settings.premium_lottie_repeater ) );

        #>
			<div {{{ view.getRenderAttributeString( 'lottie_data' ) }}}></div>
		<# } #>
		<?php
		$slider_content = ob_get_contents();
		ob_end_clean();
		$template = $slider_content . $old_template;
		return $template;
	}

    /**
	 * Render Lottie Animations output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.9.4
	 * @access public
	 * @param object $element for current element.
	 */
    public function before_render( $element ) {
        
        $data               = $element->get_data();
        
        $type               = $data['elType'];

        if( 'section' !== $type ) {
            return;
        }
        
        $settings           = $element->get_settings_for_display();
        
        $lottie             = $settings['premium_lottie_switcher'];

        if( 'yes' !== $lottie ) {
            return;
        }

        $repeater             = $settings['premium_lottie_repeater'];

        if( ! count( $repeater ) ) {
            return;
        }
        
        $layers = array();

        foreach( $repeater as $layer ) {
        
            array_push( $layers, $layer );
        
        }
        
        $lottie_settings = [
            'layers'     => $layers
        ];
            
        $element->add_render_attribute( '_wrapper','data-pa-lottie', wp_json_encode( $lottie_settings ) );
            
        
    }
}