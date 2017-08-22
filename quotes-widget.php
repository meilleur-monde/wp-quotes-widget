<?php
namespace BetterWorld;

/*
Plugin Name: Better World Quotes widget
Plugin URI: https://github.com/meilleur-monde/wp-quotes-widget
Description: ability to add a widget to display quotes, the quotes are a custom type
with some custom fields editable like any other pages or articles
Version: 1.0
Author: François Chastanet
Author URI: https://github.com/meilleur-monde
License: GNU GENERAL PUBLIC LICENSE Version 3, 29 June 2007
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

use Timber\Post;
use Timber\Timber;

require_once(ABSPATH.'wp-admin/includes/plugin.php');

/**
 *  Activation Class
 **/
if ( ! class_exists( 'BetterWorld\\QuotesWidgetInstallCheck' ) ) {
    class QuotesWidgetInstallCheck {
        static function displayNotice($message, $class = 'notice notice-error') {
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        }

        static function pluginActivated() {
            //indicate on the plugin that it has been just activated
            add_option(QuotesWidget::PLUGIN_ACTIVATED_OPTION_NAME,QuotesWidget::PLUGIN_ID);
        }

        static function pluginDeactivated() {
            //ensure rewrite rules are flush after the Quote custom type is unregistered
            flush_rewrite_rules();
        }

        static function check() {
            //check for plugin dependencies
            $deactivatePlugin = [];
            if (!is_plugin_active('timber-library/timber.php')) {
                $deactivatePlugin[] = __('timber-library', QuotesWidget::PLUGIN_NAME);
            }
            if (!is_plugin_active('better-world-utilities-library/utilities.php')) {
                $deactivatePlugin[] = __('better-world-library', QuotesWidget::PLUGIN_NAME);
            }
            if (!empty($deactivatePlugin)) {
                deactivate_plugins(__FILE__, true);
                delete_option(QuotesWidget::PLUGIN_ACTIVATED_OPTION_NAME);
                $msg  = __('plugin ' . QuotesWidget::PLUGIN_ID . ' has been deactivated because it needs the following plugins : ', QuotesWidget::PLUGIN_NAME);
                $msg .= join(', ', $deactivatePlugin );
                self::displayNotice($msg);
            }

            //first time the plugin is activated
            if (
                is_admin() &&
                get_option(QuotesWidget::PLUGIN_ACTIVATED_OPTION_NAME) === QuotesWidget::PLUGIN_ID
            ) {
                delete_option(QuotesWidget::PLUGIN_ACTIVATED_OPTION_NAME);

                //ensure rewrite rules are flush after registering the new custom type
                flush_rewrite_rules();
                $msg  = __('plugin ' . QuotesWidget::PLUGIN_ID . ', the rewrite rules have been flushed after registering the new custom type Quote', QuotesWidget::PLUGIN_NAME);
                self::displayNotice($msg, 'notice notice-info');
            }
        }
    }
}

//only when the plugin is activated the first time
register_activation_hook( __FILE__, [QuotesWidgetInstallCheck::class, 'pluginActivated'] );

//only when the plugin is activated the first time
register_deactivation_hook( __FILE__, [QuotesWidgetInstallCheck::class, 'pluginDeactivated'] );


//check for plugin dependencies
add_action('admin_init', [QuotesWidgetInstallCheck::class, 'check'] );

//register this widget
add_action('widgets_init', [QuotesWidget::class, 'registerWidget'] );


class QuotesWidget extends \WP_Widget {
    const PLUGIN_ID = 'better_world_quotes_widget';
    const PLUGIN_ACTIVATED_OPTION_NAME = 'Activated_Plugin_better_world_quotes_widget';
    const PLUGIN_NAME = 'Better World Quotes Widget';
    const QUOTE_CUSTOM_TYPE_ID = 'quote';
    const QUOTE_TAXONOMY_ID = 'quote-taxonomy';
    const PLUGIN_VERSION = '1.0';

    /**
     * @var array contains the args to use for rendering the inline javascript part
     */
    protected $currentQuoteAjaxArgs;

    /**
     * @var array if update finds some errors, this variable contains the list of error messages
     */
    protected $formValidationErrors;

    /**
     * Constructor. Sets up the widget name, description, etc.
     */
    public function __construct()
    {
        $this->currentQuoteAjaxArgs = [];

        $id_base = self::PLUGIN_ID;
        $name = self::PLUGIN_NAME;
        $description = [
            'description' => _x(
                'display quotes created via custom type quote',
                $context = 'widget description',
                $domain = 'quotes-collection'
            ),
        ];

        if (Utilities::isFrontEnd() || Utilities::isAdminCustomizationEnabled()) {
            add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts_and_styles' ) );
            add_action( 'wp_footer', array( $this, 'load_inline_script' ) );
        }

        if (is_admin()) {
            add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_script') );
        }

        parent::__construct($id_base, $name, $description);
    }

    /**
     * ADMIN ONLY
     */
    public function load_admin_script() {
        //load css style
        wp_enqueue_style(self::PLUGIN_ID, plugins_url( 'admin/css/quotes-widget.css', __FILE__));
        //ensure jquery is loaded
        wp_enqueue_script('jquery');
        // Builtin tag auto complete script
        wp_enqueue_script( 'suggest' );
    }

    /**
     * ADMIN CUSTOMIZATION OR FRONTEND ONLY
     *
     * Load scripts and styles required at the front end
     * Normally the widget has been rendered when we enter this callback
     */
    public function load_inline_script() {
        //add inline script specific to this widget
        $inlineScript = Timber::fetch( 'public/templates/quoteJavascript.twig', $this->currentQuoteAjaxArgs);
        wp_add_inline_script( 'quotes-widget', $inlineScript );
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
            plugins_url( 'public/js/quotes-widget.js', __FILE__), // source
            array('jquery'), // dependencies
            self::PLUGIN_VERSION, // version
            true
        );

        // Enqueue styles for the front end
        // TODO allow theme customization
        wp_register_style(
            self::PLUGIN_ID,
            plugins_url( 'public/css/quotes-widget.css',__FILE__ ) ,
            false,
            self::PLUGIN_VERSION
        );
        wp_enqueue_style( 'quotes-widget' );

    }

    /**
     * Register the widget. Should be hooked to 'widgets_init'.
     */
    public static function registerWidget() {
        register_widget( get_class() );
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

    /**
     * @internal
     * @param $meta_boxes
     * @return array
     */
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
     * ADMIN ONLY
     *
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

    /**
     * ADMIN ONLY
     *
     * @param $column_name
     * @param $post_ID
     */
    public function addColumnsContentToQuotesList($column_name, $post_ID) {
        $prefix = self::QUOTE_CUSTOM_TYPE_ID;

        if ($column_name === $prefix . '_quote') {
            echo get_post_meta($post_ID, $column_name, true);
        }
    }

    protected function getFormOptions()
    {
        return [
            'title' => [
                'label' => __( 'Title', self::PLUGIN_ID ),
                'defaultValue' => __('Quotes Widget', self::PLUGIN_ID),
            ],
            'show_author' => [
                'label' => __( 'Show author?', self::PLUGIN_ID ),
                'defaultValue' => 1,
            ],
            'show_source' => [
                'label' => __( 'Show source?', self::PLUGIN_ID ),
                'defaultValue' => 0,
            ],
            'ajax_refresh' => [
                'label' => __( 'Show a refresh button', self::PLUGIN_ID ),
                'defaultValue' => 1,
            ],
            'auto_refresh' => [
                'label' => __( 'Auto refresh', self::PLUGIN_ID ),
                'description' => __('if auto refresh activated, loop on quotes after a delay specified below', self::PLUGIN_ID),
                'defaultValue' => 0,
            ],
            'refresh_interval' => [
                'label' => __( 'if auto refresh activated, refresh automatically after this delay (in seconds)', self::PLUGIN_ID ),
                'refresh_link_text'   => __('Refresh', self::PLUGIN_ID),
                'min' => 1,
                'max' => 60,
                'step' => 1,
                'defaultValue' => 5,
                'validation_error_message' =>
                    __('<strong>Warning : </strong> default value restored because entered refresh interval is invalid(value should be between 1 to 60)', self::PLUGIN_ID),
            ],
            'random_refresh' => [
                'label' => __( 'Random refresh', self::PLUGIN_ID ),
                'description' => __('if auto refresh activated, it will rotate quotes randomly, otherwise in the order added, latest first.', self::PLUGIN_ID),
                'defaultValue' => 1,
            ],
            'tags' => [
                'label' => __( 'Tags filter (comma separated)', self::PLUGIN_ID ),
                'defaultValue' => '',
                'validation_error_message' =>
                    __('<strong>Warning : </strong>Following tags doesn\'t exist and have been removed', self::PLUGIN_ID),
            ],
            'char_limit' => [
                'label' => __( 'Character limit (0 for unlimited)', self::PLUGIN_ID ),
                'min' => 0,
                'step' => 1,
                'defaultValue' => 500,
                'validation_error_message' =>
                    __('<strong>Warning : </strong> default value restored because entered char limit is invalid(value should be between 0 to 500)', self::PLUGIN_ID),
            ],
        ];
    }

    protected function getFormOptionsDefaultValues()
    {
        $options = $this->getFormOptions();
        $defaultValues = [];
        foreach ($options as $key => $value) {
            $defaultValues[$key] = $value['defaultValue'];
        }
        return $defaultValues;
    }

    /**
     * ADMIN ONLY
     *
     * @param array $new_instance
     * @param array $old_instance
     * @return array
     */
    function update( $new_instance, $old_instance ) {
        $this->formValidationErrors = [];

        $instance = $old_instance;
        $formOptions = $this->getFormOptions();

        //store instance id
        $instance['widget_id'] = $this->id;

        //trim string values
        $instance['title'] = trim($new_instance['title']);

        // convert on/off values to boolean(int) values
        $instance[ 'show_author' ] = (bool)($new_instance[ 'show_author' ]);
        $instance[ 'show_source' ] = (bool)($new_instance[ 'show_source' ]);
        $instance[ 'ajax_refresh' ] = (bool)($new_instance[ 'ajax_refresh' ]);
        $instance[ 'auto_refresh' ] = (bool)($new_instance[ 'auto_refresh' ]);
        $instance[ 'random_refresh' ] = (bool)($new_instance[ 'random_refresh' ]);

        //convert and validate int value
        $val = $new_instance[ 'refresh_interval' ];
        if (
            !is_numeric($val) ||
            ((int)($val)) < $formOptions['refresh_interval']['min']
            || ((int)($val)) > $formOptions['refresh_interval']['max']
        ) {
            $this->formValidationErrors[ 'refresh_interval_error_msg' ] = $formOptions['refresh_interval']['validation_error_message'];
            $this->formValidationErrors[ 'refresh_interval_error_value' ] = $val;
            $instance[ 'refresh_interval' ] = (int)($formOptions[ 'refresh_interval' ]['defaultValue']);
        } else {
            $instance[ 'refresh_interval' ] = (int)($val);
        }

        $val = $new_instance[ 'char_limit' ];
        if (
            !is_numeric($val) || ((int)($val)) < $formOptions['char_limit']['min']
        ) {
            $this->formValidationErrors[ 'char_limit_error_msg' ] = $formOptions['char_limit']['validation_error_message'];
            $this->formValidationErrors[ 'char_limit_error_value' ] = $val;
            $instance[ 'char_limit' ] = (int)($formOptions[ 'char_limit' ]['defaultValue']);
        } else {
            $instance[ 'char_limit' ] = (int)($val);
        }

        //convert tags list to array
        $instance['tags'] = $new_instance[ 'tags' ];
        if (!empty($new_instance[ 'tags' ])) {
            $tags = explode(',', $new_instance['tags']);
            //tags validation
            $validatedTagList = [];
            $errorTagList = [];
            foreach($tags as $tag) {
                $tag = trim($tag);
                if (empty($tag)) {
                    continue;
                }
                $ret = term_exists($tag, self::QUOTE_TAXONOMY_ID);
                if ($ret) {
                    $validatedTagList [] = $ret['term_id'];
                } else {
                    $errorTagList[] = $tag;
                }
            }
            if (!empty($errorTagList)) {
                $this->formValidationErrors['tags_error_msg'] = $formOptions['tags']['validation_error_message'];
                $this->formValidationErrors[ 'tags_error_value' ] = (join(', ', $errorTagList));
            }
            //tags validated list
            $instance['tags'] = array_unique($validatedTagList);

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
     * @return void
     * @inheritdoc
     */
    public function form( $instance ) {
        $options = $this->getFormOptionsDefaultValues();

        $options = array_merge($options, $instance);

        if( isset( $options['char_limit'] ) && !is_numeric( $options['char_limit'] ) ) {
            $options['char_limit'] = __('none', self::PLUGIN_ID);
        }
        //convert tags array to string
        if( isset( $options['tags'] )) {
            $tags = '';
            if (is_array( $options['tags'] )) {
                foreach($options['tags'] as $tag) {
                    $ret = get_term($tag, self::QUOTE_TAXONOMY_ID);
                    if ($ret) {
                        //tag still exist
                        $tags .= $ret->name.', ';
                    }

                }
            }
            $options['tags'] = $tags;
        }

        $formOptions = $this->getFormOptions();
        $addOption = function(&$data, $fieldName, $fieldValue, $formOptions) {


            if (isset($formOptions[$fieldName])) {
                $field = $formOptions[$fieldName];
                $field['value'] = $fieldValue;
                $field['id'] = $this->get_field_id($fieldName);
                $field['name'] = $this->get_field_name($fieldName);
                $field['label'] = $formOptions[$fieldName]['label'];
                $data[$fieldName] = $field;
            } else {
                //add it as is
                $data[$fieldName] = $fieldValue;
            }

        };

        $renderArgs = [];
        $fields = array_keys($options);
        foreach($fields as $field) {
            $addOption($renderArgs, $field, $options[$field], $formOptions);
        }
        $renderArgs['errors'] = $this->formValidationErrors;

        Timber::render( 'admin/templates/quotesWidgetForm.twig', $renderArgs);

        //add javascript taxonomy tags selector
        //add inline script specific to this widget
        $context = Timber::get_context();
        $context['taxonomy'] = self::QUOTE_TAXONOMY_ID;
        //TODO change the way the script is loaded a jquery on change should be used
        $context['jquery_selector'] = ".betterworld_tagsSelector";
        Timber::render('admin/templates/tagsSuggestSelectorJavascript.twig', $context);
    }

    /**
     * ADMIN CUSTOMIZATION OR FRONTEND ONLY
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
     *      - refresh_link_text
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
        $options = $this->getFormOptionsDefaultValues();
        $instance = array_merge($options, $instance);

        $quote = $this->getQuote($instance);
        if ($quote === false) {
            //no more quote
            Timber::render( 'public/templates/noQuoteAvailable.twig', ['args' => $args, 'instance' => $instance]);
            return;
        }

        //renders the found quote
        $renderArgs = [
            'args' => $args,
            'instance' => $instance,
        ];
        $renderArgs = array_merge($renderArgs, $quote);

        //initialize ajax rendering args
        $this->currentQuoteAjaxArgs = [
            'jsArgs' => [
                'widget_id'         => str_replace('-', '_', $instance['widget_id']),
                'auto_refresh' => $instance['auto_refresh'],
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
                'loading'          => __('Loading...', self::PLUGIN_ID),
                'error'            => __('Error getting quote', self::PLUGIN_ID),
            ],
            //for plugin customization purpose
            'everything' => $renderArgs,
        ];
        Timber::render( 'public/templates/quote.twig', $renderArgs);
    }

    /**
     * ADMIN CUSTOMIZATION OR FRONTEND ONLY
     *
     * @param $options
     * @param int $paged page number (1-based)
     * @return array|bool
     *  - quote :
     *      - quote_quote
     *      - quote_quote_author
     *      - quote_quote_source
     *      - quote_quote_source_is_url
     *  - current_page
     *  - nb_pages
     */
    protected function getQuote($options, $paged = 1) {
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
                return $this->getQuote($options, 1);
            } else {
                //first page contains nothing, nothing to return
                return false;
            }
        }

        $posts = $query->get_posts();
        $post = $posts[0];
        $quote = new Post($post->ID);

        //apply filters on some properties
        $quote->quote_quote = trim($quote->quote_quote);
        $quote->quote_quote_author = trim($quote->quote_quote_author);
        $quote->quote_quote_source = trim($quote->quote_quote_source);

        //check if source is an url
        $quote->quote_quote_source_is_url = false;
        if (isset($quote->quote_quote_source)) {
            $quote->quote_quote_source_is_url =
                filter_var($quote->quote_quote_source, FILTER_VALIDATE_URL);
        }

        return [
            'quote' => $quote,
            'current_page' => $paged,
            'nb_pages' => $query->max_num_pages,
        ];
    }

} // end class