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
 * Version:           1.1.1
 * Author:            Nico Brünjes
 * Author URI:        https://www.zeit.de
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * GitHub Plugin URI: https://github.com/ZeitOnline/zon-enable-http
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
 			// add the url filter
 			add_filter( 'home_url', array( $this, 'home_url_filter' ), 10, 4 );
 			// conditionally echo different robtos.txt
 			add_filter( 'robots_txt', array( $this, 'robots_txt_filter' ), 10, 2 );
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
 		if ( !ZON_HTTP_ENABLED && ( ! isset( $_SERVER[ 'HTTPS' ] ) || 'on' != strtolower( $_SERVER[ 'HTTPS' ] ) ) ) {
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
 		$options = self::getInstance()->get_options();
 		$options[ 'http_on' ] = 0;
 		self::getInstance()->update_options( $options );
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

 		add_settings_field(
 			'http_robots_text',
 			'robots.txt für HTTP',
 			array( $this, 'render_robots_text_texarea' ),
 			self::$plugin_name,
 			'zonhttp_general_settings',
 			array(
 				'setting' => 'http_robots_text',
 				'helptext' => 'Robots.txt die für http ausgespielt wird',
 				'standard' => $this->standard_robots_text( true )
 			)
 		);

 		add_settings_field(
 			'https_robots_text',
 			'robots.txt für HTTPS',
 			array( $this, 'render_robots_text_texarea' ),
 			self::$plugin_name,
 			'zonhttp_general_settings',
 			array(
 				'setting' => 'https_robots_text',
				'helptext' => 'Robots.txt die für https ausgespielt wird',
				'standard' => $this->standard_robots_text( false )
 			)
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
 	 * Render different textareas
 	 *
 	 * @since  1.1.0
 	 * @param  array $args 	items to render
 	 */
 	public function render_robots_text_texarea( $args ) {
 		$settings = self::SETTINGS;
 		$options = $this->get_options();
 		$setting = $args[ 'setting' ];
 		$text = isset( $options[ $setting ] ) ? $options[ $setting ] : $args[ 'standard' ];
 		echo <<<HTML
 			<textarea name="{$settings}[{$setting}]" cols="40" rows="10">{$text}</textarea>
 			<p class="description">{$args['helptext']}</p>
 			<p class="description">Standard:</p>
 			<pre><code>{$args['standard']}</code></pre>
HTML;
 	}

 	/**
 	 * Return standard robots.txt
 	 *
 	 * @param  bool $public render an restricted or unrestrigted robots.txt
 	 * @return string 	robots.txt
 	 */
 	public function standard_robots_text( $public ) {
 		$output = '';
 		if( $public ) {
 			$site_url = parse_url( site_url() );
 			$path = ( !empty( $site_url['path'] ) ) ? $site_url['path'] : '';
 			$output .= "Disallow: $path/wp-admin/\n";
 			$output .= "Disallow: /temp/\n";
 			$output .= "Allow: $path/wp-admin/admin-ajax.php";
 		} else {
 			$output .= "User-agent: *\n";
 			$output .= "Disallow: /";
 		}
 		return $output;
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

 			$options = isset( $_POST[ self::SETTINGS ] ) ? $_POST[ self::SETTINGS ] : $this->get_options();
 			$options[ 'http_on' ] = isset( $_POST[ self::SETTINGS ][ 'http_on' ] ) ? 1 : 0;

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

	/**
	 * Filter to edit robots.txt on https vs. http
	 *
	 * @since  1.1.0
	 * @param  string 	$output 	robots.txt text
	 * @param  bool 	$public 	public switch
	 * @return string         		robots.txt
	 */
	public function robots_txt_filter( $output, $public ) {
		$options = $this->get_options();
		if ( isset( $options[ 'http_on' ] ) && $options[ 'http_on' ] == 1 ) {
			$output  = "# robots.txt generated by zon-enable-http plugin\n";
			if (
				// ZON live server config
				( isset( $_SERVER[ 'HTTP_X_ZON_EDGE_PROTO' ] ) && 'http' == strtolower( $_SERVER[ 'HTTP_X_ZON_EDGE_PROTO' ] ) ) ||
				// development environment
				( !isset( $_SERVER[ 'HTTP_X_ZON_EDGE_PROTO' ] ) && 'http' == strtolower( $_SERVER[ 'REQUEST_SCHEME' ] ) )
			) {
				// http environment
				$output  .= "# Mode: http\n\n";
				$output .= $options[ 'http_robots_text' ];
			} else {
				// https environment
				$output  .= "# Mode: https\n\n";
				$output .= $options[ 'https_robots_text' ];
			}
		}
		return $output;
	}
}

register_activation_hook( __FILE__, array( 'ZON_Enable_HTTP', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ZON_Enable_HTTP', 'deactivate' ) );

// Instantiate our class
$ZON_Enable_HTTP = ZON_Enable_HTTP::getInstance();
