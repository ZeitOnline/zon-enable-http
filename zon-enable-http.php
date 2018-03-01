<?php

/**
 * ZEIT ONLINE Enable HTTP (in HTTPS environment)
 * Filters the home_url from https to http on blogs that are https configured if the request scheme is http
 *
 * @link              https://github.com/ZeitOnline/zon-enable-http
 * @since             1.0.0
 * @package           ZON_Enable_HTTP
 *
 * Plugin Name:       ZEIT ONLINE Enable HTTP
 * Plugin URI:        https://github.com/zeitonline/zon-enable-http
 * Description:       Filters the home_url from https to http on blogs that are https configured if the request scheme is http
 * Version:           1.0.0
 * Author:            Nico Brünjes
 * Author URI:        https://www.zeit.de
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * ZEIT ONLINE Enable HTTP (in HTTPS environment)
 * Filters the home_url from https to http on blogs that are https configured if the request scheme is http
 *
 * @since      1.0.0
 * @package    ZON_Enable_HTTP
 * @author     Nico Brünjes <nico.bruenjes@zeit.de>
 */
 class ZON_Enable_HTTP {

 	const PREFIX = 'zonhttp';
 	const SETTINGS = 'zonhttp_settings';

 	/**
 	 * Holds our instance later
 	 *
 	 * @since 1.0.0
 	 * @var mixed
 	 */
 	static $instance = false;

 	/**
 	 * Name is used for menu url slug and identification
 	 *
 	 * @since 1.0.0
 	 * @var string
 	 */
 	static $plugin_name = 'zon_enable_http';

 	/**
 	 * Version of the plugin
 	 * used for cache busting for css files
 	 * and compatibility checks
 	 * Use semver version numbering
 	 *
 	 * @since 1.0.0
 	 * @see https://semver.org/
 	 * @var string
 	 */
 	static $version = '1.0.0';

 	/**
 	 * Needed to check if the plugin is correctly activated
 	 * in multisite environment
 	 *
 	 * @since 1.0.0
 	 * @access private
 	 * @var bool
 	 */
 	private $networkactive;

 	/**
 	 * construction time again
 	 *
 	 * @since  1.0.0
 	 */
 	private function __construct() {
 		// are we network activated?
 		$this->networkactive = ( is_multisite() && array_key_exists( plugin_basename( __FILE__ ), (array) get_site_option( 'active_sitewide_plugins' ) ) );
 		if( is_admin() ) {
 			// initialise plugin admin area
 			add_action( 'admin_init', array( $this, 'init_settings' ) );
 			// add menu entry
 			$hook = is_multisite() ? 'network_admin_menu' : 'admin_menu';
 			add_action( $hook, array( $this, 'add_admin_menu' ) );
 		} else {
 			// add the filter
 			add_filter( 'home_url', array( $this, 'home_url_filter' ), 10, 4 );
 		}
 	}

 	/**
 	 * If an instance exists, this returns it.  If not, it creates one and
 	 * retuns it.
 	 *
 	 * @since  1.0.0
 	 * @return ZON_Get_Frame_From_API
 	 */
 	public static function getInstance() {
 		if ( !self::$instance )
 			self::$instance = new self;
 		return self::$instance;
 	}

 	/**
 	 * Check if HTTPS mode is on, which is a prequisit for this plugin to run
 	 * deactivate plugin otherwise
 	 *
 	 * @since  1.0.0
 	 */
 	public static function activate() {
 		if ( ! isset( $_SERVER[ 'HTTPS' ] ) || 'on' != strtolower( $_SERVER[ 'HTTPS' ] ) ) {
 			deactivate_plugins( plugin_basename(__FILE__) );
 			wp_die( 'Dieses Plugin kann nur in einer HTTPS Umgebung sinnvoll eingesetzt werden.' );
      	}
 	}

 	/**
 	 * Wordpress automatically called deactivation hook
 	 *
 	 * @since  1.0.0
 	 */
 	public static function deactivate() {
 		$deleted = self::getInstance()->delete_all_transients();
 	}

 	/**
 	 * Query all transients from the database and hand them to delete_transient
 	 * use to immediatly delete all cached frames on request or as garbage collection
 	 *
 	 * @since  1.0.0
 	 * @return bool
 	 */
 	public function delete_all_transients() {
 		global $wpdb;
 		$return_check = true;
 		$table = is_multisite() ? $wpdb->sitemeta : $wpdb->options;
 		$needle = is_multisite() ? 'meta_key' : 'option_name';
 		$name_chunk = is_multisite() ? '_site_transient_' : '_transient_';
 		$query = "
 			SELECT `$needle`
 			FROM `$table`
 			WHERE `$needle`
 			LIKE '%transient_" . self::PREFIX . "%'";
 		$results = $wpdb->get_results( $query );
 		foreach( $results as $result ) {
 			$transient = str_replace( $name_chunk, '', $result->$needle );
 			if ( ! $this->delete_correct_transient( $transient ) ) {
 				$return_check = false;
 			}
 		}
 		return $return_check;
 	}

 	/**
 	 * Covers get_option for use with multisite wordpress
 	 *
 	 * @since  1.0.0
 	 * @return mixed    The value set for the option.
 	 */
 	public function get_options() {
 		$default = array( 'http_on' => 0 );

 		if ( is_multisite() ) {
 			return get_site_option( self::SETTINGS, $default );
 		}

 		return get_option( self::SETTINGS, $default );
 	}

 	/**
 	 * Covers update_option for use with multisite wordpress
 	 *
 	 * @since 1.0.0
 	 * @return bool    False if value was not updated and true if value was updated.
 	 */
 	public function update_options( $options ) {
 		if ( is_multisite() ) {
 			return update_site_option( self::SETTINGS, $options );
 		}

 		return update_option( self::SETTINGS, $options );
 	}

 	/**
 	 * Set site transient if multisite environment
 	 *
 	 * @since 1.0.0
 	 * @param string $transient  name of the transient
 	 * @param mixed  $value      content to set as transient
 	 * @param int    $expiration time in seconds for maximum cache time
 	 * @return bool
 	 */
 	public function set_correct_transient( $transient, $value, $expiration ) {
 		if ( is_multisite() ) {
 			return set_site_transient( $transient, $value, $expiration );
 		} else {
 			return set_transient( $transient, $value, $expiration );
 		}
 	}

 	/**
 	 * Get site transient if multisite environment
 	 *
 	 * @since 1.0.0
 	 * @param  string $transient name of the transient
 	 * @return mixed             content stored in the transient or false if no adequate transient found
 	 */
 	public function get_correct_transient( $transient ) {
 		if ( is_multisite() ) {
 			return get_site_transient( $transient );
 		} else {
 			return get_transient( $transient );
 		}
 	}

 	/**
 	 * Use site transient if multisite environment
 	 * @param  string $transient name of the transient to delete
 	 *
 	 * @return bool
 	 */
 	public function delete_correct_transient( $transient ) {
 		if ( is_multisite() ) {
 			return delete_site_transient( $transient );
 		} else {
 			return delete_transient( $transient );
 		}
 	}

 	/**
 	 * Initialise settings and their callbacks for use on admin page
 	 *
 	 * @since  1.0.0
 	 */
 	public function init_settings() {
 		// Set up the settings for this plugin
 		register_setting( self::PREFIX . '_group', self::SETTINGS );

 		add_settings_section(
 			'zonhttp_general_settings',								// section name
 			'Einstellungen',							 			// section title
 			array( $this, 'render_settings_section_helptext' ), 	// rendering callback
 			self::$plugin_name 										// page slug
 		);

 		add_settings_field(
 			'http_on',												// settings name
 			'HTTP einschalten',										// settings title
 			array( $this, 'render_main_switch_checkbox' ),			// rendering callback
 			self::$plugin_name,										// page slug
 			'zonhttp_general_settings'								// section
 		);
 	}

 	/**
 	 * Render help text to general settings section
 	 *
 	 * @since  1.0.0
 	 */
 	public function render_settings_section_helptext() {
 		echo "<p>Ermöglicht die Nutzung per <code>http</code> parallel zu <code>https</code>, auch wenn das Blog für <code>https</code> konfiguriert ist. (Sofern der Server Zugriff per http erlaubt.)</p>";
 	}

 	/**
 	 * Render checkox for main switch
 	 *
 	 * @since  1.0.0
 	 */
 	public function render_main_switch_checkbox() {
 		$settings = self::SETTINGS;
 		$options = $this->get_options();
 		?>
 		<label>
 			<input type="checkbox" value="1" name="<?php echo $settings; ?>[http_on]" <?php checked( 1 == $options[ 'http_on' ] );?>> HTTP Zugriff ermöglichen
 		</label>
 		<?php
 	}

 	/**
 	 * Adding the settings&options page to the (network) menu
 	 *
 	 * @since 1.0.0
 	 */
 	public function add_admin_menu () {
 		// in multisite only show when network activated
 		if ( $this->networkactive ) {
 			add_submenu_page(
 				'settings.php', 									// parent_slug
 				'ZEIT ONLINE Enable HTTP', 							// page_title
 				'ZEIT ONLINE Enable HTTP', 							// menu_title
 				'manage_network_options', 							// capability (super admin)
 				self::$plugin_name, 								// menu_slug
 				array( $this, 'options_page' ) 						// callback
 			);
 		} else if ( ! is_multisite() ) {
 			add_options_page(
 				'ZEIT ONLINE Enable HTTP', 							// page_title
 				'ZEIT ONLINE Enable HTTP', 							// menu_title
 				'manage_options', 									// capability (admin)
 				self::$plugin_name, 								// menu_slug
 				array( $this, 'options_page' ) 						// callback
 			);
 		}
 	}

 	/**
 	 * Render administration page
 	 *
 	 * @since 1.0.0
 	 */
 	public function options_page() {
 		if ( isset( $_POST[ 'submit' ] ) && isset( $_POST[ '_' . self::PREFIX . '_nonce' ] ) &&  wp_verify_nonce( $_POST[ '_' . self::PREFIX . '_nonce' ], self::PREFIX . '_settings_nonce' ) ) {

 			$options = $this->get_options();
 			$options[ 'http_on' ] = isset( $_POST[ self::SETTINGS ][ 'maint_on' ] ) ? 1 : 0;

 			$updated = $this->update_options( $options );
 			if ( $updated ) {
 				add_settings_error(
 					'zonhttp_general_settings',
 					'settings_updated',
 					'Einstellungen gespeichert.',
 					'updated'
 				);
 			}
 		}
 		?>
 		<div class="wrap">
 			<h2>Einstellungen › <?php echo esc_html( get_admin_page_title() ); ?></h2>
 			<?php settings_errors(); ?>
 			<form method="POST" action="">
 				<?php
 				settings_fields( self::PREFIX . '_group' );
 				do_settings_sections( self::$plugin_name );
 				wp_nonce_field( self::PREFIX . '_settings_nonce', '_' . self::PREFIX . '_nonce' );
 				?>
 				<p class="submit">
 				<?php submit_button( null, 'primary', 'submit', false ); ?>
 				</p>
 			</form>
 		</div>
 		<?php
 	}

 	/**
 	 * Conditionally filter back from https to http
 	 * home_url is widely used for defining in blog urls
 	 *
 	 * @since  1.0.0
 	 * @param  string $url         previously filtered url
 	 * @param  string $path        path component
 	 * @param  string $orig_scheme the scheme if set explicetly
 	 * @param  mixed  $blog_id     id of the blog or null
 	 * @return string              url
 	 */
 	public function home_url_filter( $url, $path, $orig_scheme, $blog_id ) {
      	$options = $this->get_options();
      	if ( isset( $options[ 'http_on' ] ) && $options[ 'http_on' ] == 1 ) {
      		// Server Environment set to HTTPS
      		if (
      			// ZON live server config
      			( isset( $_SERVER[ 'HTTP_X_ZON_EDGE_PROTO' ] ) && 'http' == strtolower( $_SERVER[ 'HTTP_X_ZON_EDGE_PROTO' ] ) ) ||
      			// development environment
      			( !isset( $_SERVER[ 'HTTP_X_ZON_EDGE_PROTO' ] ) && 'http' == strtolower( $_SERVER[ 'REQUEST_SCHEME' ] ) )
      		) {
      			$parsed_url = parse_url( $url );
      			if ( isset( $parsed_url[ 'scheme' ] ) && 'https' == strtolower( $parsed_url[ 'scheme' ] ) ) {
					$url = str_replace( 'https://', 'http://', $url );
      			}
      		}
        }
        return $url;
	}
}
