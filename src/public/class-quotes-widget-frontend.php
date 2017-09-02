<?php
namespace BetterWorld;

use Timber\Post;
use Timber\Timber;

if ( ! class_exists( 'BetterWorld\\Quotes_Widget_Frontend' ) ) {
	class Quotes_Widget_Frontend {

		/**
		 * @var array contains the args to use for rendering the inline javascript part
		 */
		protected $current_quote_ajax_args;

		public function __construct() {
			$this->current_quote_ajax_args = [];
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
				Quotes_Widget::PLUGIN_ID, // handle
				plugins_url( 'js/quotes-widget.js', __FILE__ ), // source
				array( 'jquery' ), // dependencies
				Quotes_Widget::PLUGIN_VERSION, // version
				true
			);

			// Enqueue styles for the front end
			// TODO allow theme customization
			wp_register_style(
				Quotes_Widget::PLUGIN_ID,
				plugins_url( 'css/quotes-widget.css', __FILE__ ),
				false,
				Quotes_Widget::PLUGIN_VERSION
			);
			wp_enqueue_style( 'quotes-widget' );

		}

		/**
		 * ADMIN CUSTOMIZATION OR FRONTEND ONLY
		 *
		 * Load scripts and styles required at the front end
		 * Normally the widget has been rendered when we enter this callback
		 */
		public function load_inline_script() {
			//add inline script specific to this widget
			$inline_script = Timber::fetch( 'templates/quoteJavascript.twig', $this->current_quote_ajax_args );
			wp_add_inline_script( 'quotes-widget', $inline_script );
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
		protected function get_quote( $options, $paged = 1 ) {
			//TODO tag filter
			//TODO random
			$query_filters      = [
				'post_type'   => Quotes_Widget_Custom_Type::QUOTE_CUSTOM_TYPE_ID,
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
					return $this->get_quote( $options );
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
			$quote = $this->get_quote( $instance );
			if ( false === $quote ) {
				//no more quote
				Timber::render(
					'templates/noQuoteAvailable.twig', [
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
					'nonce'            => wp_create_nonce( Quotes_Widget::PLUGIN_ID ),
				],
				//translated strings
				'jsLocalizationArgs' => [
					'loading' => _x(
						'Loading...',
						'frontend displayed when the refresh button/link is clicked', Quotes_Widget::PLUGIN_DOMAIN
					),
					'error'   => _x(
						'Error getting quote',
						'frontend displayed when an error occured when the refresh button/link is clicked', Quotes_Widget::PLUGIN_DOMAIN
					),
				],
				//for plugin customization purpose
				'everything'         => $render_args,
			];
			Timber::render( 'templates/quote.twig', $render_args );
		}
	}
}
