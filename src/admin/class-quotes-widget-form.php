<?php
namespace BetterWorld;

use Timber\Timber;

if ( ! class_exists( 'BetterWorld\\Quotes_Widget_Form' ) ) {
	class Quotes_Widget_Form {
		const REFRESH_INTERVAL_MIN_VALUE = 1;
		const REFRESH_INTERVAL_MAX_VALUE = 60;

		/**
		 * @var array if update finds some errors, this variable contains the list of error messages
		 */
		protected $form_validation_errors;

		/**
		 * @var Quotes_Widget
		 */
		protected $quotes_widget;

		public function __construct( Quotes_Widget $quotes_widget ) {
			$this->quotes_widget = $quotes_widget;
		}

		protected function get_form_options() {
			return [
				'title'            => [
					'label'        => _x( 'Title', 'Quote widget form title of the widget', Quotes_Widget::PLUGIN_DOMAIN ),
					'defaultValue' => _x( 'Quotes Widget', 'Quote widget form default title of the widget', Quotes_Widget::PLUGIN_DOMAIN ),
				],
				'show_author'      => [
					'label'        => _x( 'Show author?', 'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN ),
					'defaultValue' => 1,
				],
				'show_source'      => [
					'label'        => _x( 'Show source?', 'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN ),
					'defaultValue' => 0,
				],
				'ajax_refresh'     => [
					'label'        => _x( 'Show a refresh button', 'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN ),
					'defaultValue' => 1,
				],
				'auto_refresh'     => [
					'label'        => _x( 'Auto refresh', 'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN ),
					'description'  => _x(
						'if auto refresh activated, loop on quotes every n seconds',
						'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN
					),
					'defaultValue' => 0,
				],
				'refresh_interval' => [
					'label'                    => _x(
						'if auto refresh activated, refresh automatically after this delay (in seconds)',
						'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN
					),
					'refresh_link_text'        => _x( 'Refresh', 'Quote widget form Refresh button/link label', Quotes_Widget::PLUGIN_DOMAIN ),
					'min'                      => self::REFRESH_INTERVAL_MIN_VALUE,
					'max'                      => self::REFRESH_INTERVAL_MAX_VALUE,
					'step'                     => 1,
					'defaultValue'             => 5,
					'validation_error_message' =>
						sprintf(
							// translators: %1$d and %2$d are replaced with min and max int value
							_x(
								'<strong>Warning : </strong> default value restored because entered refresh interval is invalid(value should be between %1$d to %2$d)',
								'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN
							),
							self::REFRESH_INTERVAL_MIN_VALUE,
							self::REFRESH_INTERVAL_MAX_VALUE
						),
				],
				'random_refresh'   => [
					'label'        => _x( 'Random refresh', 'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN ),
					'description'  => _x(
						'if activated next quote will be chosen randomly, otherwise in the order added, latest first.',
						'Quote widget form random refresh description', Quotes_Widget::PLUGIN_DOMAIN
					),
					'defaultValue' => 1,
				],
				'tags'             => [
					'label'                    => _x( 'Tags filter (comma separated)', 'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN ),
					'defaultValue'             => '',
					'validation_error_message' =>
						_x(
							'<strong>Warning : </strong>Following tags doesn\'t exist and have been removed',
							'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN
						),
				],
				'char_limit'       => [
					'label'                    => _x( 'Character limit (0 for unlimited)', 'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN ),
					'min'                      => 0,
					'step'                     => 1,
					'defaultValue'             => 500,
					'validation_error_message' =>
						_x(
							'<strong>Warning : </strong> default value restored because entered char limit is invalid(value should be greater or equal to 0)',
							'Quote widget form', Quotes_Widget::PLUGIN_DOMAIN
						),
				],
			];
		}

		public function get_form_options_default_values() {
			$options       = $this->get_form_options();
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
			$form_options = $this->get_form_options();

			//store instance id
			$instance['widget_id'] = $this->quotes_widget->id;

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
					$ret = term_exists( $tag, Quotes_Widget_Custom_Type::QUOTE_TAXONOMY_ID );
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
			$options = $this->get_form_options_default_values();

			$options = array_merge( $options, $instance );

			//convert tags array to string
			if ( isset( $options['tags'] ) ) {
				$tags = '';
				if ( is_array( $options['tags'] ) ) {
					foreach ( $options['tags'] as $tag ) {
						$ret = get_term( $tag, Quotes_Widget_Custom_Type::QUOTE_TAXONOMY_ID );
						if ( $ret ) {
							//tag still exist
							$tags .= $ret->name . ', ';
						}
					}
				}
				$options['tags'] = $tags;
			}

			$form_options = $this->get_form_options();
			$add_option = function ( &$data, $field_name, $field_value, $form_options ) {

				if ( isset( $form_options[ $field_name ] ) ) {
					$field              = $form_options[ $field_name ];
					$field['value']     = $field_value;
					$field['id']        = $this->quotes_widget->get_field_id( $field_name );
					$field['name']      = $this->quotes_widget->get_field_name( $field_name );
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

			Timber::render( 'templates/quotesWidgetForm.twig', $render_args );
		}
	}
}
