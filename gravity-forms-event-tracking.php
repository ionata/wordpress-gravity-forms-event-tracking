<?php
/**
 * Plugin Name:       Gravity Forms Event Tracking
 * Plugin URI:        https://wordpress.org/plugins/gravity-forms-google-analytics-event-tracking/
 * Description:       Add Google Analytics event tracking to your Gravity Forms with ease.
 * Version:           2.0.0
 * Author:            Ronald Huereca
 * Author URI:        https://mediaron.com
 * Text Domain:       gravity-forms-google-analytics-event-tracking
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * Developer Credit:  Nathan Marks
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class GFGAET {

	/**
	 * Holds the class instance.
	 *
	 * @since 2.0.0
	 * @access private
	 */
	private static $instance = null;

	/**
	 * Retrieve a class instance.
	 *
	 * @since 2.0.0
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	} //end get_instance

	/**
	 * Class constructor.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		load_plugin_textdomain( 'gravity-forms-google-analytics-event-tracking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
		spl_autoload_register( array( $this, 'loader' ) );
		
		add_action( 'gform_loaded', array( $this, 'gforms_loaded' ) );
	}

	/**
	 * Check for the minimum supported PHP version.
	 *
	 * @since 2.0.0
	 *
	 * @return bool true if meets minimum version, false if not
	 */
	public static function check_php_version() {
		if( ! version_compare( '5.3', PHP_VERSION, '<=' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Check the plugin to make sure it meets the minimum requirements.
	 *
	 * @since 2.0.0
	 */
	public static function check_plugin() {
		if( ! GFGAET::check_php_version() ) {
			deactivate_plugins( GFGAET::get_plugin_basename() );
			exit( sprintf( esc_html__( 'Gravity Forms Event Tracking requires PHP version 5.3 and up. You are currently running PHP version %s.', 'gravity-forms-google-analytics-event-tracking' ), esc_html( PHP_VERSION ) ) );
		}
	}

	/**
	 * Retrieve the plugin basename.
	 *
	 * @since 2.0.0
	 *
	 * @return string plugin basename
	 */
	public static function get_plugin_basename() {
		return plugin_basename( __FILE__ );	
	}

	/**
	 * Return the absolute path to an asset.
	 *
	 * @since 2.0.0
	 *
	 * @param string @path Relative path to the asset.
	 *
	 * return string Absolute path to the relative asset.
	 */
	public static function get_plugin_dir( $path = '' ) {
		$dir = rtrim( plugin_dir_path(__FILE__), '/' );
		if ( !empty( $path ) && is_string( $path) )
			$dir .= '/' . ltrim( $path, '/' );
		return $dir;		
	}

	/**
	 * Initialize Gravity Forms related add-ons.
	 *
	 * @since 2.0.0
	 */
	public function gforms_loaded() {
		if ( ! GFGAET::check_php_version() ) return;
		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		// Initialize settings screen and feeds
		GFAddOn::register( 'GFGAET_UA' );
		GFAddOn::register( 'GFGAET_Submission_Feeds' );

		// Initialize pagination
		add_action( 'gform_post_paging', array( $this, 'pagination'), 10, 3 );
		
		// Initialize whether Ajax is on or off
		add_filter( 'gform_form_args', array( $this, 'maybe_ajax_only' ), 15, 1 );
		
		// Fire a tag manager event
		add_action( 'gform_after_submission', array( $this, 'tag_manager' ), 10, 2 );
	}
	
	/**
	 * Get the Google Analytics UA Code
	 * 
	 * @since 2.0.0
	 * @return string/bool Returns string UA code, false otherwise
	 */
	public static function get_ua_code() {
		$gravity_forms_add_on_settings = get_option( 'gravityformsaddon_GFGAET_UA_settings', array() );
		
		$ua_id = isset( $gravity_forms_add_on_settings[ 'gravity_forms_event_tracking_ua' ] ) ? $gravity_forms_add_on_settings[ 'gravity_forms_event_tracking_ua' ] : false;

		$ua_regex = "/^UA-[0-9]{5,}-[0-9]{1,}$/";

		if ( preg_match( $ua_regex, $ua_id ) ) {
			return $ua_id;
		}
		return false;
	}

	/**
	 * Checks whether JS only mode is activated for sending events.
	 *
	 * @since 2.0.0
	 *
	 * @return bool true if JS only, false if not
	 */
	public static function is_js_only() {
		$ga_options = get_option( 'gravityformsaddon_GFGAET_UA_settings', false );
		if ( ! isset( $ga_options[ 'js_only' ] ) ) {
			return false;
		}
		if ( 'on' == $ga_options[ 'js_only' ] ) {
			return true;
		}
		return false;
	}

	/**
	 * Autoload class files.
	 *
	 * @since 2.0.0
	 *
	 * @param string $class_name The class name
	 */
	public function loader( $class_name ) {
		if ( class_exists( $class_name, false ) || false === strpos( $class_name, 'GFGAET' ) ) {
			return;
		}
		$file = GFGAET::get_plugin_dir( "includes/{$class_name}.php" );
		if ( file_exists( $file ) ) {
			include_once( $file );
		}
	}

	/**
	 * Sets all forms to Ajax only depeneding on settings
	 *
	 * @since 2.0.0
	 *
	 * @param array $form_args The form arguments
	 */
	public function maybe_ajax_only( $form_args ) {
		$gravity_forms_add_on_settings = get_option( 'gravityformsaddon_GFGAET_UA_settings', array() );

		if ( isset( $gravity_forms_add_on_settings[ 'ajax_only' ] ) && 'on' == $gravity_forms_add_on_settings[ 'ajax_only' ] ) {
			$form_args[ 'ajax' ] = true;
		}
		return $form_args;
	}

	/**
	 * Initialize the pagination events.
	 *
	 * @since 2.0.0
	 *
	 * @param array $form                The form arguments
	 * @param int   @source_page_number  The original page number
	 * @param int   $current_page_number The new page number
	 */
	public function pagination( $form, $source_page_number, $current_page_number ) {
		$pagination = GFGAET_Pagination::get_instance();
		$pagination->paginate( $form, $source_page_number, $current_page_number );
	}

	/**
	 * Initialize the tag manager push.
	 *
	 * @since 2.0.0
	 *
	 * @param array $entry   The entry array
	 * @param array $form    The form array
	 */
	public function tag_manager( $entry, $form ) {
		$tag_manager = GFGAET_Tag_Manager::get_instance();
		$tag_manager->send( $entry, $form );
	}
	
	
}

register_activation_hook( __FILE__, array( 'GFGAET', 'check_plugin' ) );
GFGAET::get_instance();