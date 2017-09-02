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
License: LGPL-3.0
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! defined( 'BETTER_WORLD_QUOTES_WIDGET_VERSION_KEY' ) ) {
	define( 'BETTER_WORLD_QUOTES_WIDGET_VERSION_KEY', 'BetterWorldQuotesWidgetVersion' );
}

if ( ! defined( 'BETTER_WORLD_QUOTES_WIDGET_VERSION_NUM' ) ) {
	define( 'BETTER_WORLD_QUOTES_WIDGET_VERSION_NUM', '1.0.0' );
}

add_option( BETTER_WORLD_QUOTES_WIDGET_VERSION_KEY, BETTER_WORLD_QUOTES_WIDGET_VERSION_NUM );

require_once __DIR__ . '/admin/class-quotes-widget-install-check.php';
require_once __DIR__ . '/admin/class-quotes-widget.php';

