<?php

namespace BetterWorld;

require_once __DIR__ . '/class-quotes-widget-custom-type.php';
require_once __DIR__ . '/class-quotes-widget-form.php';
require_once __DIR__ . '/../public/class-quotes-widget-frontend.php';

if ( ! class_exists( 'BetterWorld\\Quotes_Widget' ) ) {
	class Quotes_Widget extends \WP_Widget {
		const PLUGIN_ID = 'better_world_quotes_widget';
		const PLUGIN_DOMAIN = 'quotes-widget';
		const PLUGIN_ACTIVATED_OPTION_NAME = 'Activated_Plugin_better_world_quotes_widget';
		const PLUGIN_NAME = 'Better World Quotes Widget';
		const PLUGIN_VERSION = '1.0';

		/**
		 * @var Quotes_Widget_Frontend delegated object used for frontend part
		 */
		protected $frontend_delegate;

		/**
		 * @var Quotes_Widget_Form delegated object used for form part
		 */
		protected $form_delegate;

		/**
		 * Constructor. Sets up the widget name, description, etc.
		 */
		public function __construct() {
			$id_base     = self::PLUGIN_ID;
			$name        = self::PLUGIN_NAME;
			$description = [
				'description' =>
					_x( 'display quotes created via custom type quote', 'widget description', self::PLUGIN_DOMAIN ),
			];

			$this->frontend_delegate = new Quotes_Widget_Frontend();
			$this->form_delegate = new Quotes_Widget_Form( $this );

			if ( Utilities::isFrontEnd() || Utilities::isAdminCustomizationEnabled() ) {
				add_action( 'wp_enqueue_scripts', array( $this->frontend_delegate, 'load_scripts_and_styles' ) );
				add_action( 'wp_footer', array( $this->frontend_delegate, 'load_inline_script' ) );
			}

			if ( is_admin() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_script' ) );
			}

			parent::__construct( $id_base, $name, $description );
		}

		/**
		 * Register the widget. Should be hooked to 'widgets_init'.
		 */
		public static function register_widget() {
			register_widget( __CLASS__ );
		}

		/**
		 * ADMIN ONLY
		 */
		public function load_admin_script() {
			//load css style
			wp_enqueue_style( self::PLUGIN_ID, plugins_url( 'css/quotes-widget.css', __FILE__ ) );

			//register javascript
			wp_register_script(
				'admin-quotes-widget',
				plugins_url( 'js/quotes-widget.js', __FILE__ ),
				[
					//ensure jquery is loaded
					'jquery',
					// Builtin tag auto complete script
					'suggest',
				],
				false,
				true
			);
			wp_enqueue_script( 'admin-quotes-widget' );
		}

		/**
		 * Register all widget instances of this widget class.
		 *
		 * @since 2.8.0
		 * @access public
		 */
		public function _register() {
			parent::_register();

			//custom type initialization
			add_filter( 'rwmb_meta_boxes', [ new Quotes_Widget_Custom_Type(), 'register_meta_boxes' ] );
		}

		/**
		 * ADMIN CUSTOMIZATION OR FRONTEND ONLY
		 * widget frontend rendering
		 * @see Quotes_Widget_Frontend::widget feature delegated to this class
		 * @inheritdoc
		 */
		public function widget( $args, $instance ) {
			$options  = $this->form_delegate->get_form_options_default_values();
			$instance = array_merge( $options, $instance );

			$this->frontend_delegate->widget( $args, $instance );
		}

		/**
		 * ADMIN ONLY
		 *
		 * Outputs the settings update form.
		 *
		 * @since 2.8.0
		 * @access public
		 *
		 * @param array $instance Current settings.
		 *
		 * @inheritdoc
		 */
		public function form( $instance ) {
			return $this->form_delegate->form( $instance );
		}

		public function update( $new_instance, $old_instance ) {
			return $this->form_delegate->update( $new_instance, $old_instance );
		}

	} // end class

	// I18N
	load_plugin_textdomain( Quotes_Widget::PLUGIN_DOMAIN, false, basename( __DIR__ . '/../languages/' ) );

	//register this widget
	add_action( 'widgets_init', [ Quotes_Widget::class, 'register_widget' ] );
}
