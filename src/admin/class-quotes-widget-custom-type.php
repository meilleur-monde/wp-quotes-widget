<?php
namespace BetterWorld;

if ( ! class_exists( 'BetterWorld\\Quotes_Widget_Custom_Type' ) ) {
	class Quotes_Widget_Custom_Type {
		const QUOTE_CUSTOM_TYPE_ID = 'quote';
		const QUOTE_TAXONOMY_ID = 'quote-taxonomy';

		const QUOTE_MAX_LENGTH = 500;
		const QUOTE_AUTHOR_MAX_LENGTH = 250;
		const QUOTE_SOURCE_MAX_LENGTH = 250;

		/**
		 * @internal
		 *
		 * @param $meta_boxes
		 *
		 * @return array
		 */
		public function register_meta_boxes( $meta_boxes ) {
			$this->register_quote_custom_type();
			$prefix       = self::QUOTE_CUSTOM_TYPE_ID;
			$meta_boxes[] = array(
				// Meta box id, UNIQUE per meta box. Optional since 4.1.5
				'id'         => 'quote',

				// Meta box title - Will appear at the drag and drop handle bar. Required.
				'title'      => _x( 'Quote Fields', 'Quote post type fields', Quotes_Widget::PLUGIN_DOMAIN ),

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
						'name'  => _x( 'Quote', 'Quote post type fields Name field', Quotes_Widget::PLUGIN_DOMAIN ),
						'id'    => "{$prefix}_quote",
						'desc'  => _x(
							'title will be used to calculate the slug but not used in rendering',
							'Quote post type fields description below title', Quotes_Widget::PLUGIN_DOMAIN
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
						'name'  => _x( 'Author', 'Quote post type fields author', Quotes_Widget::PLUGIN_DOMAIN ),
						'id'    => "{$prefix}_quote_author",
						'desc'  => _x( 'The author of the quote (facultative)', 'Quote post type fields', Quotes_Widget::PLUGIN_DOMAIN ),
						'type'  => 'text',
						'std'   => '', //default value
						'clone' => false,
						'size'  => 100,
					),

					// Source
					array(
						'name'  => _x( 'Source', 'Quote post type fields source', Quotes_Widget::PLUGIN_DOMAIN ),
						'id'    => "{$prefix}_quote_source",
						'desc'  => _x(
							'The source of the quote (facultative) - can be an url or book reference, ...',
							'Quote post type fields source description', Quotes_Widget::PLUGIN_DOMAIN
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
								Quotes_Widget::PLUGIN_DOMAIN
							),
							'maxlength' => sprintf(
								// translators: %1$s is replaced with the number of characters
								_x(
									'Quote length is limited to %1$d characters',
									'Quote post type fields validation rule quote max length - %1$d is an int',
									Quotes_Widget::PLUGIN_DOMAIN
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
									Quotes_Widget::PLUGIN_DOMAIN
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
									Quotes_Widget::PLUGIN_DOMAIN
								),
								self::QUOTE_SOURCE_MAX_LENGTH
							),
						),
					),
				),
			);

			// ONLY QUOTE CUSTOM TYPE POSTS
			add_filter( 'manage_quote_posts_columns', [ $this, 'add_columns_to_quotes_list' ], 10 );
			add_action( 'manage_quote_posts_custom_column', [ $this, 'add_columns_content_to_quotes_list' ], 10, 2 );

			return $meta_boxes;
		}

		/**
		 * @internal
		 */
		public function register_quote_custom_type() {
			$labels = [
				'name'               => _x( 'Quotes', 'Quote post type general name', Quotes_Widget::PLUGIN_DOMAIN ),
				'singular_name'      => _x( 'Quote', 'Quote post type singular name', Quotes_Widget::PLUGIN_DOMAIN ),
				'name_admin_bar'     => _x( 'Quote', 'Quote post type add new on admin bar', Quotes_Widget::PLUGIN_DOMAIN ),
				'menu_name'          => _x( 'Quotes', 'Quote post type admin menu', Quotes_Widget::PLUGIN_DOMAIN ),
				'add_new'            => _x( 'Add', 'Quote post type', Quotes_Widget::PLUGIN_DOMAIN ),
				'add_new_item'       => _x( 'Add a new Quote', 'Quote post type', Quotes_Widget::PLUGIN_DOMAIN ),
				'new_item'           => _x( 'New Quote', 'Quote post type', Quotes_Widget::PLUGIN_DOMAIN ),
				'edit_item'          => _x( 'Update the Quote', 'Quote post type', Quotes_Widget::PLUGIN_DOMAIN ),
				'view_item'          => _x( 'See quote', 'Quote post type', Quotes_Widget::PLUGIN_DOMAIN ),
				'search_items'       => _x( 'Search a quote', 'Quote post type', Quotes_Widget::PLUGIN_DOMAIN ),
				'not_found'          => _x( 'No quote found', 'Quote post type', Quotes_Widget::PLUGIN_DOMAIN ),
				'not_found_in_trash' => _x( 'No quote found in the trash', 'Quote post type', Quotes_Widget::PLUGIN_DOMAIN ),
				'parent_item_colon'  => _x( 'Parent quote', 'Quote post type', Quotes_Widget::PLUGIN_DOMAIN ),
				'all_items'          => _x( 'All Quotes', 'Quote post type', Quotes_Widget::PLUGIN_DOMAIN ),
			];

			$args = [
				'labels'              => $labels,
				'description'         => sprintf(
					// translators: %1$s is replaced with the plugin name
					_x(
						'Create a quote that can be displayed using the widget %1$s',
						'Quote post type description (%1$s widget name)', Quotes_Widget::PLUGIN_DOMAIN
					),
					Quotes_Widget::PLUGIN_NAME
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
					'name'                       => _x( 'Quote Tag', 'Quote post type taxonomy general name', Quotes_Widget::PLUGIN_DOMAIN ),
					'singular_name'              => _x( 'Quote Tag', 'Quote post type taxonomy singular name', Quotes_Widget::PLUGIN_DOMAIN ),
					'search_items'               => _x( 'Search Quote Tags', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
					'popular_items'              => _x( 'Popular Quote Tags', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
					'all_items'                  => _x( 'All Quote Tags', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
					'parent_item'                => null,
					'parent_item_colon'          => null,
					'edit_item'                  => _x( 'Edit Quote Tag', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
					'update_item'                => _x( 'Update Quote Tag', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
					'add_new_item'               => _x( 'Add New Quote Tag', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
					'new_item_name'              => _x( 'New Quote Tag Name', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
					'separate_items_with_commas' => _x(
						'Separate Quote Tag with commas',
						'Quote post type taxonomy - instructions in order to fill tags list field',
						Quotes_Widget::PLUGIN_DOMAIN
					),
					'add_or_remove_items'        => _x( 'Add or remove Quote Tags', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
					'choose_from_most_used'      => _x( 'Choose from the most used Quote Tags', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
					'not_found'                  => _x( 'No Quote Tags  found.', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
					'menu_name'                  => _x( 'Quote Tags', 'Quote post type taxonomy', Quotes_Widget::PLUGIN_DOMAIN ),
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
		 * ADMIN ONLY
		 *
		 * add the columns(author and source) on the quote custom type list
		 *
		 * @param $defaults
		 *
		 * @return mixed
		 * @internal
		 */
		public function add_columns_to_quotes_list( $defaults ) {
			$prefix                         = self::QUOTE_CUSTOM_TYPE_ID;
			$defaults[ $prefix . '_quote' ] = _x( 'Quote', 'Quote List column', Quotes_Widget::PLUGIN_DOMAIN );

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
		 * @param $post_id
		 * @internal
		 */
		public function add_columns_content_to_quotes_list( $column_name, $post_id ) {
			$prefix = self::QUOTE_CUSTOM_TYPE_ID;

			if ( $column_name === $prefix . '_quote' ) {
				echo get_post_meta( // WPCS: XSS OK
					esc_sql( $post_id ),
					esc_sql( $column_name ),
					true
				);
			}
		}
	}
}
