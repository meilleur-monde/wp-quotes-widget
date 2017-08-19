<?php
namespace BetterWorld;

if(!defined('ABSPATH')) exit;

/*
Plugin Name: BetterWorld Quotes widget
Plugin URI: https://github.com/meilleur-monde/wp-quotes-widget
Description: ability to add a widget to display quotes, the quotes are a custom type
with some custom fields editable like any other pages or articles
Version: 1.0
Author: François Chastanet
Author URI: https://github.com/meilleur-monde
License: GNU GENERAL PUBLIC LICENSE Version 3, 29 June 2007
*/

//needs is_plugin_active function
use Timber\Post;
use Timber\Timber;

include_once(ABSPATH.'wp-admin/includes/plugin.php');

//TODO
//register_activation_hook( __FILE__, array( 'QuotesWidget', 'activate' ) );
//add_action( 'plugins_loaded', array( 'Quotes_Collection', 'load' ) );
add_action('widgets_init', QuotesWidget::class.'::registerWidget' );


class QuotesWidget extends \WP_Widget {
    const PLUGIN_ID = 'better-world-quotes-widget';
    const PLUGIN_NAME = 'Quotes Widget';
    const QUOTE_CUSTOM_TYPE_ID = 'quote';
    const QUOTE_TAXONOMY_ID = 'quote-taxonomy';
    const PLUGIN_VERSION = '1.0';

    /**
     * Constructor. Sets up the widget name, description, etc.
     */
    public function __construct()
    {
        $id_base = self::PLUGIN_ID;
        $name = self::PLUGIN_NAME;
        $description = [
            'description' => _x(
                'display quotes created via custom type quote',
                $context = 'widget description',
                $domain = 'quotes-collection'
            ),
        ];

        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts_and_styles' ) );

        parent::__construct($id_base, $name, $description);
    }

    /** Load scripts and styles required at the front end **/
    public function load_scripts_and_styles() {

        // ajax refresh feature
        wp_enqueue_script(
            'quotes_ajax', // handle
            plugins_url( 'js/quotes-collection.js' ), // source
            array('jquery'), // dependencies
            self::PLUGIN_VERSION, // version
            true
        );
        wp_localize_script( 'quotescollection', 'quotescollectionAjax', array(
                // URL to wp-admin/admin-ajax.php to process the request
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),

                // generate a nonce with a unique ID "myajax-post-comment-nonce"
                // so that you can check it later when an AJAX request is sent
                'nonce' => wp_create_nonce( self::PLUGIN_ID ),

                'loading' => __('Loading...', self::PLUGIN_ID),
                'error' => __('Error getting quote', self::PLUGIN_ID),
                //TODO
//                'nextQuote' => $this->refresh_link_text,
//                'autoRefreshMax' => $this->auto_refresh_max,
//                'autoRefreshCount' => 0
            )
        );

        // Enqueue styles for the front end
        if ( Utilities::isFrontend() || Utilities::isAdminCustomizationEnabled()) {
            wp_register_style(
                'quotescollection',
                quotescollection_url( 'css/quotes-collection.css' ),
                false,
                self::PLUGIN_VERSION
            );
            wp_enqueue_style( 'quotescollection' );
        }

    }

    /**
     * The default widget options
     *
     * @return array The default options
     */
    protected function defaultOptions() {
        return array(
            'title'               => __('Quotes Widget', self::PLUGIN_ID),
            'show_author'         => 1,
            'show_source'         => 0,
            'ajax_refresh'        => 1,
            'auto_refresh'        => 0,
            'random_refresh'      => 1,
            'refresh_interval'    => 5,
            'tags'                => '',
            'char_limit'          => 500,
        );
    }

    /**
     * Register the widget. Should be hooked to 'widgets_init'.
     */
    public static function registerWidget() {
        register_widget( get_class() );

        //display some activation error or warning information to the admin user
        add_action('admin_notices', __CLASS__.'::adminNoticePluginActivation');
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
        add_filter( 'rwmb_meta_boxes', [$this, 'registerMetaBoxes']);
    }

    /**
     * @internal
     */
    public function registerQuoteCustomType()
    {
        $labels = [
            'name' => _x('Quotes', 'post type general name', self::QUOTE_CUSTOM_TYPE_ID),
            'singular_name' => _x('Quote', 'post type singular name', self::QUOTE_CUSTOM_TYPE_ID),
            'name_admin_bar' => _x('Quote', 'add new on admin bar', self::QUOTE_CUSTOM_TYPE_ID),
            'menu_name' => _x('Quotes', 'admin menu', self::QUOTE_CUSTOM_TYPE_ID),
            'add_new' => _x('Add', 'Quote', self::QUOTE_CUSTOM_TYPE_ID),
            'add_new_item' => __('Add a new Quote', self::QUOTE_CUSTOM_TYPE_ID),
            'new_item' => __('New Quote', self::QUOTE_CUSTOM_TYPE_ID),
            'edit_item' => __('Update the Quote', self::QUOTE_CUSTOM_TYPE_ID),
            'view_item' => __('See quote', self::QUOTE_CUSTOM_TYPE_ID),
            'search_items' => __('Search a quote', self::QUOTE_CUSTOM_TYPE_ID),
            'not_found' => __('No quote found', self::QUOTE_CUSTOM_TYPE_ID),
            'not_found_in_trash' => __('No quote found in the trash', self::QUOTE_CUSTOM_TYPE_ID),
            'parent_item_colon' => __('Parent quote', self::QUOTE_CUSTOM_TYPE_ID),
            'all_items' => __('All Quotes', self::QUOTE_CUSTOM_TYPE_ID),
        ];

        $args = [
            'labels' => $labels,
            'description' => __('Create a quote that can be displayed using '.self::PLUGIN_NAME, self::QUOTE_CUSTOM_TYPE_ID),
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'capability_type' => 'post',//['quote', 'quotes'], not working
            'map_meta_cap' => true, // Set to false, if users are not allowed to edit/delete existing schema
            'public' => true,
            'hierarchical' => false,
            'rewrite' => false,
            'has_archive' => false,
            'query_var' => false,
            'supports' => ['title'],
            'taxonomies' => [],
            'show_ui' => true,
            'menu_position' => null,
            'menu_icon' => 'dashicons-format-quote',
            'can_export' => true,
            'show_in_nav_menus' => true,
            'show_in_menu' => true,
        ];
        register_post_type(self::QUOTE_CUSTOM_TYPE_ID, $args);


        if (!taxonomy_exists(self::QUOTE_TAXONOMY_ID)) {
            $labels = array(
                'name'                       => _x( 'Quote Tag', 'taxonomy general name', self::QUOTE_CUSTOM_TYPE_ID ),
                'singular_name'              => _x( 'Quote Tag', 'taxonomy singular name', self::QUOTE_CUSTOM_TYPE_ID ),
                'search_items'               => __( 'Search Quotes Tag', self::QUOTE_CUSTOM_TYPE_ID ),
                'popular_items'              => __( 'Popular Quotes Tag', self::QUOTE_CUSTOM_TYPE_ID ),
                'all_items'                  => __( 'All Quotes Tag', self::QUOTE_CUSTOM_TYPE_ID ),
                'parent_item'                => null,
                'parent_item_colon'          => null,
                'edit_item'                  => __( 'Edit Quote Tag', self::QUOTE_CUSTOM_TYPE_ID ),
                'update_item'                => __( 'Update Quote Tag', self::QUOTE_CUSTOM_TYPE_ID ),
                'add_new_item'               => __( 'Add New Quote Tag', self::QUOTE_CUSTOM_TYPE_ID ),
                'new_item_name'              => __( 'New Quote Tag Name', self::QUOTE_CUSTOM_TYPE_ID ),
                'separate_items_with_commas' => __( 'Separate Quote Tag with commas', self::QUOTE_CUSTOM_TYPE_ID ),
                'add_or_remove_items'        => __( 'Add or remove Quote Tags', self::QUOTE_CUSTOM_TYPE_ID ),
                'choose_from_most_used'      => __( 'Choose from the most used Quote Tags', self::QUOTE_CUSTOM_TYPE_ID ),
                'not_found'                  => __( 'No Quote Tags  found.', self::QUOTE_CUSTOM_TYPE_ID ),
                'menu_name'                  => __( 'Quote Tags', self::QUOTE_CUSTOM_TYPE_ID ),
            );

            $args = array(
                'labels'                => $labels,
                'show_ui'               => true,
                'show_admin_column'     => true,
                'update_count_callback' => '_update_post_term_count',
                'hierarchical'          => false,
                'rewrite'               => false,
                'query_var'             => false,
                'with_front'            => true
            );

            register_taxonomy( self::QUOTE_TAXONOMY_ID, 'post', $args );
        }

        register_taxonomy_for_object_type( self::QUOTE_TAXONOMY_ID, self::QUOTE_CUSTOM_TYPE_ID );
    }

    public function registerMetaBoxes($meta_boxes) {
        $this->registerQuoteCustomType();
        $prefix = self::QUOTE_CUSTOM_TYPE_ID;
        $meta_boxes[] = array(
            // Meta box id, UNIQUE per meta box. Optional since 4.1.5
            'id' => 'quote',

            // Meta box title - Will appear at the drag and drop handle bar. Required.
            'title' => __( 'Quote Fields', self::QUOTE_CUSTOM_TYPE_ID ),

            // Post types, accept custom post types as well - DEFAULT is array('post'). Optional.
            'pages' => array( self::QUOTE_CUSTOM_TYPE_ID ),

            // Where the meta box appear: normal (default), advanced, side. Optional.
            'context' => 'normal',

            // Order of meta box: high (default), low. Optional.
            'priority' => 'high',

            // Auto save: true, false (default). Optional.
            'autosave' => true,

            // List of meta fields
            'fields' => array(
                // Citation
                array(
                    'name'  => __( 'Quote', self::QUOTE_CUSTOM_TYPE_ID ),
                    'id'    => "{$prefix}_quote",
                    'desc'  => __( "Quote that will be displayed (title is just for reference, not used in rendering)", self::QUOTE_CUSTOM_TYPE_ID ),
                    'type'  => 'textarea',
                    'std'   => '', //default value
                    'clone' => false,
                    'size'  => 500,
                    'cols'  => 80,
                    'rows'  => 10,
                ),

                // Auteur
                array(
                    'name'  => _x( 'Author', 'Quote Author', self::QUOTE_CUSTOM_TYPE_ID ),
                    'id'    => "{$prefix}_quote_author",
                    'desc'  => __( "The author of the quote (facultative)", self::QUOTE_CUSTOM_TYPE_ID ),
                    'type'  => 'text',
                    'std'   => '', //default value
                    'clone' => false,
                    'size'  => 100,
                ),

                // Source
                array(
                    'name'  => _x( 'Source', 'Quote Source', self::QUOTE_CUSTOM_TYPE_ID ),
                    'id'    => "{$prefix}_quote_source",
                    'desc'  => __( "The source of the quote (facultative) - can be an url or book reference, ...", self::QUOTE_CUSTOM_TYPE_ID ),
                    'type'  => 'text',
                    'std'   => '', //default value
                    'clone' => false,
                    'size'  => 100,
                ),
            ),
            'validation' => array(
                'rules' => array(
                    "{$prefix}_quote" => array(
                        'required'  => true,
                        'maxlength' => 500,
                    ),
                    "{$prefix}_quote_author" => array(
                        'required'  => false,
                        'maxlength' => 250,
                    ),
                    "{$prefix}_quote_source" => array(
                        'required'  => false,
                        'maxlength' => 250,
                    ),
                ),
                // optional override of default jquery.validate messages
                'messages' => array(
                    "{$prefix}_quote" => array(
                        'required'  => __( 'Quote is mandatory', self::QUOTE_CUSTOM_TYPE_ID ),
                        'maxlength' => __( 'Quote length is limited to 500 characters', self::QUOTE_CUSTOM_TYPE_ID ),
                    ),
                    "{$prefix}_quote_author" => array(
                        'maxlength' => __( 'Quote Author length is limited to 250 characters', self::QUOTE_CUSTOM_TYPE_ID ),
                    ),
                    "{$prefix}_quote_source" => array(
                        'maxlength' => __( 'Quote Source length is limited to 250 characters', self::QUOTE_CUSTOM_TYPE_ID ),
                    ),
                )
            )
        );

        // ONLY QUOTE CUSTOM TYPE POSTS
        add_filter('manage_quote_posts_columns', [$this, 'addColumnsToQuotesList'], 10);
        add_action('manage_quote_posts_custom_column', [$this, 'addColumnsContentToQuotesList'], 10, 2);

        return $meta_boxes;
    }

    /**
     * add the columns(author and source) on the quote custom type list
     * @param $defaults
     * @return mixed
     */
   public function addColumnsToQuotesList($defaults) {
        $prefix = self::QUOTE_CUSTOM_TYPE_ID;
        $defaults[$prefix.'_quote'] = _x('Quote', 'Quote List column', self::QUOTE_CUSTOM_TYPE_ID);

        //sort the columns
        uksort($defaults, function ($a, $b) use ($prefix) {
           $columnsPosition = ['cb' => 0, 'title' => 1, 'taxonomy-quote-taxonomy' => 4, 'date' => 5, $prefix.'_quote' => 2];

           return $columnsPosition[$a] > $columnsPosition[$b]?1:-1;
        });

        return $defaults;
   }

   public function addColumnsContentToQuotesList($column_name, $post_ID) {
        $prefix = self::QUOTE_CUSTOM_TYPE_ID;

        if ($column_name === $prefix . '_quote') {
            echo get_post_meta($post_ID, $column_name, true);
        }
    }


    /**
     * Outputs the settings update form.
     *
     * @since 2.8.0
     * @access public
     *
     * @param array $instance Current settings.
     * @return void
     * @inheritdoc
     */
    public function form( $instance ) {
        $options = $this->defaultOptions();
        $options = array_merge($options, $instance);
        if( isset( $options['char_limit'] ) && !is_numeric( $options['char_limit'] ) ) {
            $options['char_limit'] = __('none', self::PLUGIN_ID);
        }
        $translations = [
            'title' => ['label' => __( 'Title', self::PLUGIN_ID )],
            'show_author' => ['label' => __( 'Show author?', self::PLUGIN_ID )],
            'show_source' => ['label' => __( 'Show source?', self::PLUGIN_ID )],
            'ajax_refresh' => ['label' => __( 'Refresh feature', self::PLUGIN_ID )],
            'auto_refresh' => [
                'label' => __( 'Auto refresh', self::PLUGIN_ID ),
                'description' => __('if auto refresh activated, loop on quotes after a delay specified below', self::PLUGIN_ID),
            ],
            'refresh_interval' => [
                'label' => __( 'if auto refresh activated, refresh automatically after this delay', self::PLUGIN_ID ),
                'min' => 1,
                'max' => 60,
                'step' => 1,
            ],
            'random_refresh' => [
                'label' => __( 'Random refresh', self::PLUGIN_ID ),
                'description' => __('if auto refresh activated, it will rotate quotes randomly, otherwise in the order added, latest first.', self::PLUGIN_ID),
            ],
            'tags' => [
                'label' => __( 'Tags filter', self::PLUGIN_ID ),
                'description' => __('Comma separated', self::PLUGIN_ID)
            ],
            'char_limit' => [
                'label' => __( 'Character limit (0 for unlimited)', self::PLUGIN_ID ),
                'min' => 0,
                'step' => 1,
            ],

        ];
        $addOption = function(&$data, $fieldName, $fieldValue, $translations) {


            if (isset($translations[$fieldName])) {
                $field = $translations[$fieldName];
                $field['value'] = $fieldValue;
                $field['id'] = $this->get_field_id($fieldName);
                $field['name'] = $this->get_field_name($fieldName);
                $field['label'] = $translations[$fieldName]['label'];
                $data[$fieldName] = $field;
            }
        };

        $formOptions = [];
        $fields = array_keys($options);
        foreach($fields as $field) {

            $addOption($formOptions, $field, $options[$field], $translations);
        }

        Timber::render( 'twigTemplates/quotesWidgetForm.twig', $formOptions);
    }

    /**
     * Front end output
     *
     * @see WP_Widget::widget()
     *
     * @param array $args     Display arguments
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
     *      - show_author
     *      - show_source
     *      - ajax_refresh
     *      - auto_refresh
     *      - random_refresh
     *      - refresh_interval
     *      - tags
     *      - char_limit
     * @inheritdoc
     */
    public function widget( $args, $instance )
    {
        $options = $this->defaultOptions();
        $instance = array_merge($options, $instance);

        $quote = $this->getQuote($instance);
        if ($quote === false) {
            //no more quote
            Timber::render( 'twigTemplates/noQuoteAvailable.twig', ['args' => $args, 'instance' => $instance]);
            return;
        }

        //renders the found quote
        Timber::render( 'twigTemplates/quote.twig', [
            'args' => $args,
            'instance' => $instance,
            'quote' => $quote['quote'],
            'current_page' => $quote['current_page'],
        ]);
    }

    protected function getQuote($options, $paged = 0) {
        //TODO tag filter
        //TODO random
        $queryFilters = [
            'post_type' => self::QUOTE_CUSTOM_TYPE_ID,
            'post_status' => 'publish',
        ];
        $paginationFilters = [
            'posts_per_page' => 1,
            'paged' => $paged,
        ];
        $query = new \WP_Query( array_merge($queryFilters, $paginationFilters));

        if ($query->max_num_pages === 0) {
            if ($paged > 0) {
                //something wrong with the pagination occurred
                //try to get quote from the beginning
                return $this->getQuote($options, 0);
            } else {
                //first page contains nothing, nothing to return
                return false;
            }
        }

        $posts = $query->get_posts();
        $post = $posts[0];
        $quote = new Post($post->ID);

        return [
            'quote' => $quote,
            'current_page' => $paged,
        ];
    }

    /**
     * @internal displays admin notices if dependant plugins not installed
     */
    public static function adminNoticePluginActivation()
    {
        if (!is_plugin_active('timber-library/timber.php')) {
            $class = 'notice notice-error';
            $message = __('plugin '.self::PLUGIN_ID.', needs the plugin timber-library', self::PLUGIN_NAME);

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        }
        if (!is_plugin_active('better-world-utilities-library/utilities.php')) {
            $class = 'notice notice-error';
            $message = __('plugin '.self::PLUGIN_ID.', needs the plugin better-world-library', self::PLUGIN_NAME);

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        }
    }
} // end class