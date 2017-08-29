<?php

namespace BetterWorld;

/**
 *  Activation Class
 **/
if ( ! class_exists( 'BetterWorld\\Quotes_Widget_Install_Check' ) ) {
	class Quotes_Widget_Install_Check {
		protected static function display_notice( $message, $class = 'notice notice-error' ) {
			printf( // WPCS: XSS OK
				'<div class="%1$s"><p>%2$s</p></div>',
				esc_attr( $class ),
				$message
			);
		}

		public static function plugin_activated() {
			//indicate on the plugin that it has been just activated
			add_option( Quotes_Widget::PLUGIN_ACTIVATED_OPTION_NAME,Quotes_Widget::PLUGIN_ID );
		}

		public static function plugin_deactivated() {
			//ensure rewrite rules are flush after the Quote custom type is unregistered
			flush_rewrite_rules();
		}

		public static function check() {
			//check for plugin dependencies
			$deactivate_plugin = [];
			if ( ! is_plugin_active( 'timber-library/timber.php' ) ) {
				$deactivate_plugin[] = _x( 'timber-library', 'Installation plugin name', Quotes_Widget::PLUGIN_DOMAIN );
			}
			if ( ! is_plugin_active( 'better-world-utilities-library/utilities.php' ) ) {
				$deactivate_plugin[] = _x( 'better-world-library', 'Installation plugin name', Quotes_Widget::PLUGIN_DOMAIN );
			}
			if ( ! empty( $deactivate_plugin ) ) {
				deactivate_plugins( __FILE__, true );
				delete_option( Quotes_Widget::PLUGIN_ACTIVATED_OPTION_NAME );
				$msg  = sprintf(
					// translators: %1$s is replaced with the plugin name
					_x(
						'the plugin %1$s has been deactivated because it needs the following plugins : ',
						'Installation', Quotes_Widget::PLUGIN_DOMAIN
					), Quotes_Widget::PLUGIN_NAME
				);
				$msg .= implode( ', ', $deactivate_plugin );
				self::display_notice( $msg );
			}

			//first time the plugin is activated
			if (
				is_admin() &&
				get_option( Quotes_Widget::PLUGIN_ACTIVATED_OPTION_NAME ) === Quotes_Widget::PLUGIN_ID
			) {
				delete_option( Quotes_Widget::PLUGIN_ACTIVATED_OPTION_NAME );

				//ensure rewrite rules are flush after registering the new custom type
				flush_rewrite_rules();
				$msg  = sprintf(
					// translators: %1$s is replaced with the plugin name
					_x(
						'plugin %1$s, the rewrite rules have been flushed after registering the new custom type Quote',
						'Installation', Quotes_Widget::PLUGIN_DOMAIN
					), Quotes_Widget::PLUGIN_NAME
				);
				self::display_notice( $msg, 'notice notice-info' );
			}
		}
	}
}

//only when the plugin is activated the first time
register_activation_hook( __FILE__, [ Quotes_Widget_Install_Check::class, 'plugin_activated' ] );

//only when the plugin is activated the first time
register_deactivation_hook( __FILE__, [ Quotes_Widget_Install_Check::class, 'plugin_deactivated' ] );


//check for plugin dependencies
add_action( 'admin_init', [ Quotes_Widget_Install_Check::class, 'check' ] );
