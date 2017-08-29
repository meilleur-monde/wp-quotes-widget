<?php

namespace BetterWorld;

use Timber\Post;
use Timber\Timber;

if ( ! class_exists( 'BetterWorld\\Quotes_Widget' ) ) {
	class Quotes_Widget extends \WP_Widget {
		const PLUGIN_ID = 'better_world_quotes_widget';
		const PLUGIN_DOMAIN = 'quotes-widget';
		const PLUGIN_ACTIVATED_OPTION_NAME = 'Activated_Plugin_better_world_quotes_widget';
		const PLUGIN_NAME = 'Better World Quotes Widget';
		const QUOTE_CUSTOM_TYPE_ID = 'quote';
		const QUOTE_TAXONOMY_ID = 'quote-taxonomy';
		const PLUGIN_VERSION = '1.0';
		const QUOTE_MAX_LENGTH = 500;
		const QUOTE_AUTHOR_MAX_LENGTH = 250;
		const QUOTE_SOURCE_MAX_LENGTH = 250;
		const REFRESH_INTERVAL_MIN_VALUE = 1;
		const REFRESH_INTERVAL_MAX_VALUE = 60;

		/**
		 * @var array contains the args to use for rendering the inline javascript part
		 */
		protected $current_quote_ajax_args;

		/**
		 * @var array if update finds some errors, this variable contains the list of error messages
		 */
		protected $form_validation_errors;

		/**
		 * Constructor. Sets up the widget name, description, etc.
		 */
		public function __construct() {
			$this->current_quote_ajax_args = [];

			$id_base     = self::PLUGIN_ID;
			$name        = self::PLUGIN_NAME;
			$description = [
				'description' =>
					_x( 'display quotes created via custom type quote', 'widget description', self::PLUGIN_DOMAIN ),
			];

			if ( Utilities::isFrontEnd() || Utilities::isAdminCustomizationEnabled() ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts_and_styles' ) );
				add_action( 'wp_footer', array( $this, 'load_inline_script' ) );
			}

			if ( is_admin() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_script' ) );
			}

			parent::__construct( $id_base, $name, $description );
		}

		/**
		 * ADMIN ONLY
		 */
		public function load_admin_script() {
			//load css style
			wp_enqueue_style( self::PLUGIN_ID, plugins_url( 'admin/css/quotes-widget.css', __FILE__ ) );

			//register javascript
			wp_register_script(
				'admin-quotes-widget',
				plugins_url( 'admin/js/quotes-widget.js', __FILE__ ),
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
		 * ADMIN CUSTOMIZATION OR FRONTEND ONLY
		 *
		 * Load scripts and styles required at the front end
		 * Normally the widget has been rendered when we enter this callback
		 */
		public function load_inline_script() {
			//add inline script specific to this widget
			$inline_script = Timber::fetch( 'public/templates/quoteJavascript.twig', $this->current_quote_ajax_args );
			wp_add_inline_script( 'quotes-widget', $inline_script );
		}

		/**
		 * ADMIN CUSTOMIZATION OR FRONTEND ONLY
		 *
		 * Load scripts and styles required at the front end
		 *Normally the widget has been rendered when we enter this callback
		 */
		public function load_scripts_and_styles() {

			//add jquery if necessary
			if ( ! wp_script_is( 'jquery', 'done' ) ) {
				wp_enqueue_script( 'jquery' );
			}

			// ajax refresh feature
			wp_enqueue_script(
				self::PLUGIN_ID, // handle
				plugins_url( 'public/js/quotes-widget.js', __FILE__ ), // source
				array( 'jquery' ), // dependencies
				self::PLUGIN_VERSION, // version
				true
			);

			// Enqueue styles for the front end
			// TODO allow theme customization
			wp_register_style(
				self::PLUGIN_ID,
				plugins_url( 'public/css/quotes-widget.css', __FILE__ ),
				false,
				self::PLUGIN_VERSION
			);
			wp_enqueue_style( 'quotes-widget' );

		}

		/**
		 * Register the widget. Should be hooked to 'widgets_init'.
		 */
		public static function register_widget() {
			register_widget( __CLASS__ );
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
			add_filter( 'rwmb_meta_boxes', [ $this, 'registerMetaBoxes' ] );
		}

		/**
		 * @internal
		 */
		public function registerQuoteCustomType() {
			$labels = [
				'name'               => _x( 'Quotes', 'Quote post type general name', self::PLUGIN_DOMAIN ),
				'singular_name'      => _x( 'Quote', 'Quote post type singular name', self::PLUGIN_DOMAIN ),
				'name_admin_bar'     => _x( 'Quote', 'Quote post type add new on admin bar', self::PLUGIN_DOMAIN ),
				'menu_name'          => _x( 'Quotes', 'Quote post type admin menu', self::PLUGIN_DOMAIN ),
				'add_new'            => _x( 'Add', 'Quote post type', self::PLUGIN_DOMAIN ),
				'add_new_item'       => _x( 'Add a new Quote', 'Quote post type', self::PLUGIN_DOMAIN ),
				'new_item'           => _x( 'New Quote', 'Quote post type', self::PLUGIN_DOMAIN ),
				'edit_item'          => _x( 'Update the Quote', 'Quote post type', self::PLUGIN_DOMAIN ),
				'view_item'          => _x( 'See quote', 'Quote post type', self::PLUGIN_DOMAIN ),
				'search_items'       => _x( 'Search a quote', 'Quote post type', self::PLUGIN_DOMAIN ),
				'not_found'          => _x( 'No quote found', 'Quote post type', self::PLUGIN_DOMAIN ),
				'not_found_in_trash' => _x( 'No quote found in the trash', 'Quote post type', self::PLUGIN_DOMAIN ),
				'parent_item_colon'  => _x( 'Parent quote', 'Quote post type', self::PLUGIN_DOMAIN ),
				'all_items'          => _x( 'All Quotes', 'Quote post type', self::PLUGIN_DOMAIN ),
			];

			$args = [
				'labels'              => $labels,
				'description'         => sprintf(
					// translators: %1$s is replaced with the plugin name
					_x(
						'Create a quote that can be displayed using the widget %1$s',
						'Quote post type description (%1$s widget name)', self::PLUGIN_DOMAIN
					),
					self::PLUGIN_NAME
				),
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post', //['quote', 'quotes'], not working
				'map_meta_cap'        => true, // Set to false, if users are not allowed to edit/delete existing schema
				'public'              => true,
				'hierarchical'        => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'query_var'           => false,
				'supports'            => [ 'title' ],
				'taxonomies'          => [],
				'show_ui'             => true,
				'menu_position'       => null,
				'menu_icon'           => 'dashicons-format-quote',
				'can_export'          => true,
				'show_in_nav_menus'   => true,
				'show_in_menu'        => true,
			];
			register_post_type( self::QUOTE_CUSTOM_TYPE_ID, $args );

			if ( ! taxonomy_exists( self::QUOTE_TAXONOMY_ID ) ) {
				$labels = array(
					'name'                       => _x( 'Quote Tag', 'Quote post type taxonomy general name', self::PLUGIN_DOMAIN ),
					'singular_name'              => _x( 'Quote Tag', 'Quote post type taxonomy singular name', self::PLUGIN_DOMAIN ),
					'search_items'               => _x( 'Search Quote Tags', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
					'popular_items'              => _x( 'Popular Quote Tags', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
					'all_items'                  => _x( 'All Quote Tags', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
					'parent_item'                => null,
					'parent_item_colon'          => null,
					'edit_item'                  => _x( 'Edit Quote Tag', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
					'update_item'                => _x( 'Update Quote Tag', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
					'add_new_item'               => _x( 'Add New Quote Tag', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
					'new_item_name'              => _x( 'New Quote Tag Name', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
					'separate_items_with_commas' => _x(
						'Separate Quote Tag with commas',
						'Quote post type taxonomy - instructions in order to fill tags list field',
						self::PLUGIN_DOMAIN
					),
					'add_or_remove_items'        => _x( 'Add or remove Quote Tags', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
					'choose_from_most_used'      => _x( 'Choose from the most used Quote Tags', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
					'not_found'                  => _x( 'No Quote Tags  found.', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
					'menu_name'                  => _x( 'Quote Tags', 'Quote post type taxonomy', self::PLUGIN_DOMAIN ),
				);

				$args = array(
					'labels'                => $labels,
					'show_ui'               => true,
					'show_admin_column'     => true,
					'update_count_callback' => '_update_post_term_count',
					'hierarchical'          => false,
					'rewrite'               => false,
					'query_var'             => false,
					'with_front'            => true,
				);

				register_taxonomy( self::QUOTE_TAXONOMY_ID, 'post', $args );
			}

			register_taxonomy_for_object_type( self::QUOTE_TAXONOMY_ID, self::QUOTE_CUSTOM_TYPE_ID );
		}

		/**
		 * @internal
		 *
		 * @param $meta_boxes
		 *
		 * @return array
		 */
		public function registerMetaBoxes( $meta_boxes ) {
			$this->registerQuoteCustomType();
			$prefix       = self::QUOTE_CUSTOM_TYPE_ID;
			$meta_boxes[] = array(
				// Meta box id, UNIQUE per meta box. Optional since 4.1.5
				'id'         => 'quote',

				// Meta box title - Will appear at the drag and drop handle bar. Required.
				'title'      => _x( 'Quote Fields', 'Quote post type fields', self::PLUGIN_DOMAIN ),

				// Post types, accept custom post types as well - DEFAULT is array('post'). Optional.
				'pages'      => array( self::QUOTE_CUSTOM_TYPE_ID ),

				// Where the meta box appear: normal (default), advanced, side. Optional.
				'context'    => 'normal',

				// Order of meta box: high (default), low. Optional.
				'priority'   => 'high',

				// Auto save: true, false (default). Optional.
				'autosave'   => true,

				// List of meta fields
				'fields'     => array(
					// Citation
					array(
						'name'  => _x( 'Quote', 'Quote post type fields Name field', self::PLUGIN_DOMAIN ),
						'id'    => "{$prefix}_quote",
						'desc'  => _x(
							'title will be used to calculate the slug but not used in rendering',
							'Quote post type fields description below title', self::PLUGIN_DOMAIN
						),
						'type'  => 'textarea',
						'std'   => '', //default value
						'clone' => false,
						'size'  => 500,
						'cols'  => 80,
						'rows'  => 10,
					),

					// Auteur
					array(
						'name'  => _x( 'Author', 'Quote post type fields author', self::PLUGIN_DOMAIN ),
						'id'    => "{$prefix}_quote_author",
						'desc'  => _x( 'The author of the quote (facultative)', 'Quote post type fields', self::PLUGIN_DOMAIN ),
						'type'  => 'text',
						'std'   => '', //default value
						'clone' => false,
						'size'  => 100,
					),

					// Source
					array(
						'name'  => _x( 'Source', 'Quote post type fields source', self::PLUGIN_DOMAIN ),
						'id'    => "{$prefix}_quote_source",
						'desc'  => _x(
							'The source of the quote (facultative) - can be an url or book reference, ...',
							'Quote post type fields source description', self::PLUGIN_DOMAIN
						),
						'type'  => 'text',
						'std'   => '', //default value
						'clone' => false,
						'size'  => 100,
					),
				),
				'validation' => array(
					'rules'    => array(
						"{$prefix}_quote"        => array(
							'required'  => true,
							'maxlength' => self::QUOTE_MAX_LENGTH,
						),
						"{$prefix}_quote_author" => array(
							'required'  => false,
							'maxlength' => self::QUOTE_AUTHOR_MAX_LENGTH,
						),
						"{$prefix}_quote_source" => array(
							'required'  => false,
							'maxlength' => self::QUOTE_SOURCE_MAX_LENGTH,
						),
					),
					// optional override of default jquery.validate messages
					'messages' => array(
						"{$prefix}_quote"        => array(
							'required'  => _x(
								'Quote is mandatory',
								'Quote post type fields validation rule quote required',
								self::PLUGIN_DOMAIN
							),
							'maxlength' => sprintf(
								// translators: %1$s is replaced with the number of characters
								_x(
									'Quote length is limited to %1$d characters',
									'Quote post type fields validation rule quote max length - %1$d is an int',
									self::PLUGIN_DOMAIN
								),
								self::QUOTE_MAX_LENGTH
							),
						),
						"{$prefix}_quote_author" => array(
							'maxlength' => sprintf(
								// translators: %1$d is an int
								_x(
									'Quote Author length is limited to %1$d characters',
									'Quote post type fields validation rule author max length - %1$d is an int',
									self::PLUGIN_DOMAIN
								),
								self::QUOTE_AUTHOR_MAX_LENGTH
							),
						),
						"{$prefix}_quote_source" => array(
							'maxlength' => sprintf(
								// translators: %1$d is an int
								_x(
									'Quote Source length is limited to %1$d characters',
									'Quote post type fields validation rule source %1$d is an int',
									self::PLUGIN_DOMAIN
								),
								self::QUOTE_SOURCE_MAX_LENGTH
							),
						),
					),
				),
			);

			// ONLY QUOTE CUSTOM TYPE POSTS
			add_filter( 'manage_quote_posts_columns', [ $this, 'addColumnsToQuotesList' ], 10 );
			add_action( 'manage_quote_posts_custom_column', [ $this, 'addColumnsContentToQuotesList' ], 10, 2 );

			return $meta_boxes;
		}

		/**
		 * ADMIN ONLY
		 *
		 * add the columns(author and source) on the quote custom type list
		 *
		 * @param $defaults
		 *
		 * @return mixed
		 */
		public function addColumnsToQuotesList( $defaults ) {
			$prefix                         = self::QUOTE_CUSTOM_TYPE_ID;
			$defaults[ $prefix . '_quote' ] = _x( 'Quote', 'Quote List column', self::PLUGIN_DOMAIN );

			//sort the columns
			uksort(
				$defaults, function ( $a, $b ) use ( $prefix ) {
					$columns_position = [
						'cb'                      => 0,
						'title'                   => 1,
						'taxonomy-quote-taxonomy' => 4,
						'date'                    => 5,
						$prefix . '_quote'        => 2,
					];

					return $columns_position[ $a ] > $columns_position[ $b ] ? 1 : - 1;
				}
			);

			return $defaults;
		}

		/**
		 * ADMIN ONLY
		 *
		 * @param $column_name
		 * @param $post_ID
		 */
		public function addColumnsContentToQuotesList( $column_name, $post_id ) {
			$prefix = self::QUOTE_CUSTOM_TYPE_ID;

			if ( $column_name === $prefix . '_quote' ) {
				echo get_post_meta( // WPCS: XSS OK
					esc_sql( $post_id ),
					esc_sql( $column_name ),
					true
				);
			}
		}

		protected function getFormOptions() {
			return [
				'title'            => [
					'label'        => _x( 'Title', 'Quote widget form title of the widget', self::PLUGIN_DOMAIN ),
					'defaultValue' => _x( 'Quotes Widget', 'Quote widget form default title of the widget', self::PLUGIN_DOMAIN ),
				],
				'show_author'      => [
					'label'        => _x( 'Show author?', 'Quote widget form', self::PLUGIN_DOMAIN ),
					'defaultValue' => 1,
				],
				'show_source'      => [
					'label'        => _x( 'Show source?', 'Quote widget form', self::PLUGIN_DOMAIN ),
					'defaultValue' => 0,
				],
				'ajax_refresh'     => [
					'label'        => _x( 'Show a refresh button', 'Quote widget form', self::PLUGIN_DOMAIN ),
					'defaultValue' => 1,
				],
				'auto_refresh'     => [
					'label'        => _x( 'Auto refresh', 'Quote widget form', self::PLUGIN_DOMAIN ),
					'description'  => _x(
						'if auto refresh activated, loop on quotes every n seconds',
						'Quote widget form', self::PLUGIN_DOMAIN
					),
					'defaultValue' => 0,
				],
				'refresh_interval' => [
					'label'                    => _x(
						'if auto refresh activated, refresh automatically after this delay (in seconds)',
						'Quote widget form', self::PLUGIN_DOMAIN
					),
					'refresh_link_text'        => _x( 'Refresh', 'Quote widget form Refresh button/link label', self::PLUGIN_DOMAIN ),
					'min'                      => self::REFRESH_INTERVAL_MIN_VALUE,
					'max'                      => self::REFRESH_INTERVAL_MAX_VALUE,
					'step'                     => 1,
					'defaultValue'             => 5,
					'validation_error_message' =>
						sprintf(
							// translators: %1$d and %2$d are replaced with min and max int value
							_x(
								'<strong>Warning : </strong> default value restored because entered refresh interval is invalid(value should be between %1$d to %2$d)',
								'Quote widget form', self::PLUGIN_DOMAIN
							),
							self::REFRESH_INTERVAL_MIN_VALUE,
							self::REFRESH_INTERVAL_MAX_VALUE
						),
				],
				'random_refresh'   => [
					'label'        => _x( 'Random refresh', 'Quote widget form', self::PLUGIN_DOMAIN ),
					'description'  => _x(
						'if activated next quote will be chosen randomly, otherwise in the order added, latest first.',
						'Quote widget form random refresh description', self::PLUGIN_DOMAIN
					),
					'defaultValue' => 1,
				],
				'tags'             => [
					'label'                    => _x( 'Tags filter (comma separated)', 'Quote widget form', self::PLUGIN_DOMAIN ),
					'defaultValue'             => '',
					'validation_error_message' =>
						_x(
							'<strong>Warning : </strong>Following tags doesn\'t exist and have been removed',
							'Quote widget form', self::PLUGIN_DOMAIN
						),
				],
				'char_limit'       => [
					'label'                    => _x( 'Character limit (0 for unlimited)', 'Quote widget form', self::PLUGIN_DOMAIN ),
					'min'                      => 0,
					'step'                     => 1,
					'defaultValue'             => 500,
					'validation_error_message' =>
						_x(
							'<strong>Warning : </strong> default value restored because entered char limit is invalid(value should be greater or equal to 0)',
							'Quote widget form', self::PLUGIN_DOMAIN
						),
				],
			];
		}

		protected function getFormOptionsDefaultValues() {
			$options       = $this->getFormOptions();
			$default_values = [];
			foreach ( $options as $key => $value ) {
				$default_values[ $key ] = $value['defaultValue'];
			}

			return $default_values;
		}

		/**
		 * ADMIN ONLY
		 *
		 * @param array $new_instance
		 * @param array $old_instance
		 *
		 * @return array
		 */
		public function update( $new_instance, $old_instance ) {
			$this->form_validation_errors = [];

			$instance    = $old_instance;
			$form_options = $this->getFormOptions();

			//store instance id
			$instance['widget_id'] = $this->id;

			//trim string values
			$instance['title'] = trim( $new_instance['title'] );

			// convert on/off values to boolean(int) values
			$instance['show_author']    = (bool) $new_instance['show_author'];
			$instance['show_source']    = (bool) $new_instance['show_source'];
			$instance['ajax_refresh']   = (bool) $new_instance['ajax_refresh'];
			$instance['auto_refresh']   = (bool) $new_instance['auto_refresh'];
			$instance['random_refresh'] = (bool) $new_instance['random_refresh'];

			//convert and validate int value
			$val = $new_instance['refresh_interval'];
			if (
				! is_numeric( $val ) ||
				( (int) $val ) < $form_options['refresh_interval']['min']
				|| ( (int) $val ) > $form_options['refresh_interval']['max']
			) {
				$this->form_validation_errors['refresh_interval_error_msg']   = $form_options['refresh_interval']['validation_error_message'];
				$this->form_validation_errors['refresh_interval_error_value'] = $val;
				$instance['refresh_interval']                               = (int) $form_options['refresh_interval']['defaultValue'];
			} else {
				$instance['refresh_interval'] = (int) $val;
			}

			$val = $new_instance['char_limit'];
			if (
				! is_numeric( $val ) || ( (int) $val ) < $form_options['char_limit']['min']
			) {
				$this->form_validation_errors['char_limit_error_msg']   = $form_options['char_limit']['validation_error_message'];
				$this->form_validation_errors['char_limit_error_value'] = $val;
				$instance['char_limit']                               = (int) $form_options['char_limit']['defaultValue'];
			} else {
				$instance['char_limit'] = (int) $val;
			}

			//convert tags list to array
			$instance['tags'] = $new_instance['tags'];
			if ( ! empty( $new_instance['tags'] ) ) {
				$tags = explode( ',', $new_instance['tags'] );
				//tags validation
				$validated_tag_list = [];
				$error_tag_list     = [];
				foreach ( $tags as $tag ) {
					$tag = trim( $tag );
					if ( empty( $tag ) ) {
						continue;
					}
					$ret = term_exists( $tag, self::QUOTE_TAXONOMY_ID );
					if ( $ret ) {
						$validated_tag_list [] = $ret['term_id'];
					} else {
						$error_tag_list[] = $tag;
					}
				}
				if ( ! empty( $error_tag_list ) ) {
					$this->form_validation_errors['tags_error_msg']   = $form_options['tags']['validation_error_message'];
					$this->form_validation_errors['tags_error_value'] = implode( ', ', $error_tag_list );
				}
				//tags validated list
				$instance['tags'] = array_unique( $validated_tag_list );

			}

			return $instance;
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
		 * @return void
		 * @inheritdoc
		 */
		public function form( $instance ) {
			$options = $this->getFormOptionsDefaultValues();

			$options = array_merge( $options, $instance );

			//convert tags array to string
			if ( isset( $options['tags'] ) ) {
				$tags = '';
				if ( is_array( $options['tags'] ) ) {
					foreach ( $options['tags'] as $tag ) {
						$ret = get_term( $tag, self::QUOTE_TAXONOMY_ID );
						if ( $ret ) {
							//tag still exist
							$tags .= $ret->name . ', ';
						}
					}
				}
				$options['tags'] = $tags;
			}

			$form_options = $this->getFormOptions();
			$add_option = function ( &$data, $field_name, $field_value, $form_options ) {

				if ( isset( $form_options[ $field_name ] ) ) {
					$field              = $form_options[ $field_name ];
					$field['value']     = $field_value;
					$field['id']        = $this->get_field_id( $field_name );
					$field['name']      = $this->get_field_name( $field_name );
					$field['label']     = $form_options[ $field_name ]['label'];
					$data[ $field_name ] = $field;
				} else {
					//add it as is
					$data[ $field_name ] = $field_value;
				}
			};

			$render_args = [];
			$fields     = array_keys( $options );
			foreach ( $fields as $field ) {
				$add_option( $render_args, $field, $options[ $field ], $form_options );
			}
			$render_args['errors'] = $this->form_validation_errors;

			Timber::render( 'admin/templates/quotesWidgetForm.twig', $render_args );
		}

		/**
		 * ADMIN CUSTOMIZATION OR FRONTEND ONLY
		 *
		 * @see WP_Widget::widget()
		 *
		 * @param array $args Display arguments
		 *      - name (of the sidebar)
		 *      - id (of the sidebar)
		 *      - description
		 *      - class
		 *      - before_widget
		 *      - after_widget
		 *      - before_title
		 *      - after_title
		 *      - widget_id
		 *      - widget_name
		 * @param array $instance Saved values from database (overriding defaultOptions)
		 *      - title
		 *      - refresh_link_text
		 *      - show_author
		 *      - show_source
		 *      - ajax_refresh
		 *      - auto_refresh
		 *      - random_refresh
		 *      - refresh_interval
		 *      - tags
		 *      - char_limit
		 *
		 * @inheritdoc
		 */
		public function widget( $args, $instance ) {
			$options  = $this->getFormOptionsDefaultValues();
			$instance = array_merge( $options, $instance );

			$quote = $this->getQuote( $instance );
			if ( false === $quote ) {
				//no more quote
				Timber::render(
					'public/templates/noQuoteAvailable.twig', [
						'args'     => $args,
						'instance' => $instance,
					]
				);

				return;
			}

			//renders the found quote
			$render_args = [
				'args'     => $args,
				'instance' => $instance,
			];
			$render_args = array_merge( $render_args, $quote );

			//initialize ajax rendering args
			$this->current_quote_ajax_args = [
				'jsArgs'             => [
					'widget_id'        => str_replace( '-', '_', $instance['widget_id'] ),
					'auto_refresh'     => $instance['auto_refresh'],
					'refresh_interval' => $instance['refresh_interval'],
					'currentPage'      => $quote['current_page'],
					'nb_pages'         => $quote['nb_pages'],
					// URL to wp-admin/admin-ajax.php to process the request
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),

					// generate a token
					'nonce'            => wp_create_nonce( self::PLUGIN_ID ),
				],
				//translated strings
				'jsLocalizationArgs' => [
					'loading' => _x(
						'Loading...',
						'frontend displayed when the refresh button/link is clicked', self::PLUGIN_DOMAIN
					),
					'error'   => _x(
						'Error getting quote',
						'frontend displayed when an error occured when the refresh button/link is clicked', self::PLUGIN_DOMAIN
					),
				],
				//for plugin customization purpose
				'everything'         => $render_args,
			];
			Timber::render( 'public/templates/quote.twig', $render_args );
		}

		/**
		 * ADMIN CUSTOMIZATION OR FRONTEND ONLY
		 *
		 * @param $options
		 * @param int $paged page number (1-based)
		 *
		 * @return array|bool
		 *  - quote :
		 *      - quote_quote
		 *      - quote_quote_author
		 *      - quote_quote_source
		 *      - quote_quote_source_is_url
		 *  - current_page
		 *  - nb_pages
		 */
		protected function getQuote( $options, $paged = 1 ) {
			//TODO tag filter
			//TODO random
			$query_filters      = [
				'post_type'   => self::QUOTE_CUSTOM_TYPE_ID,
				'post_status' => 'publish',
			];
			$pagination_filters = [
				'posts_per_page' => 1,
				'paged'          => $paged,
			];
			$query             = new \WP_Query( array_merge( $query_filters, $pagination_filters ) );

			if ( 0 === $query->max_num_pages ) {
				if ( $paged > 0 ) {
					//something wrong with the pagination occurred
					//try to get quote from the beginning
					return $this->getQuote( $options );
				}

				//first page contains nothing, nothing to return
				return false;
			}

			$posts = $query->get_posts();
			$post  = $posts[0];
			$quote = new Post( $post->ID );

			//apply filters on some properties
			$quote->quote_quote        = trim( $quote->quote_quote );
			$quote->quote_quote_author = trim( $quote->quote_quote_author );
			$quote->quote_quote_source = trim( $quote->quote_quote_source );

			//check if source is an url
			$quote->quote_quote_source_is_url = false;
			if ( isset( $quote->quote_quote_source ) ) {
				$quote->quote_quote_source_is_url =
					filter_var( $quote->quote_quote_source, FILTER_VALIDATE_URL );
			}

			return [
				'quote'        => $quote,
				'current_page' => $paged,
				'nb_pages'     => $query->max_num_pages,
			];
		}

	} // end class

	// I18N
	load_plugin_textdomain( Quotes_Widget::PLUGIN_DOMAIN, false, basename( __DIR__ . '/languages/' ) );

	//register this widget
	add_action( 'widgets_init', [ Quotes_Widget::class, 'register_widget' ] );
}
