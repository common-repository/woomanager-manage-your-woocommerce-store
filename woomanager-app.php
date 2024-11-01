<?php
/*
Plugin Name: WooManager - Manage your WooCommerce Store
Plugin URI: http://woomanagerapp.com
Description: With WooManager you'll be able to control your entire sales operations from the palm of your hand. Check order, make notes, call customers, get informed about low stock products, and much much more.
Version: 1.1.9
Author: hellodev
Author URI: http://www.hellodev.us
License: Closed Source
Text Domain: woomanager-app
*/


// Prevent data leaks if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

define( 'WOOMANAGER_API_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
require_once(WOOMANAGER_API_PLUGIN_DIR . "woomanager-hooks.php");

if(!class_exists('WOOMANAGER_API')){

	class WOOMANAGER_API {

	    function __construct() {

		    // Checks if user is admin and if RestAPI is inactive
			if ( !in_array( 'rest-api/plugin.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ))){

    			// If so display warning message on admin notices
				add_action( 'admin_notices', array($this, 'hd_wm_activation_notice'));

	    	} else{
		    				error_log('start');

		        add_action('rest_api_init', array($this, 'hd_wm_register_routes'));
		        add_action( 'show_user_profile',  array($this, 'hd_wm_generates_qrcode_fields') );
				add_action( 'edit_user_profile',  array($this, 'hd_wm_generates_qrcode_fields') );
				add_action( 'personal_options_update',  array($this, 'hd_wm_save_qrcode_fields') );
				add_action( 'edit_user_profile_update',  array($this, 'hd_wm_save_qrcode_fields') );
				new WOOMANAGER_APP();
	      }

	    }

	    public function hd_wm_register_routes () {

			update_option('woomanager_pid', 'LKX12321LQSSAXZLPASMASMAKDWEIASZMZ8');
			global $methods;

		    require_once(WOOMANAGER_API_PLUGIN_DIR . "Methods.php");
	        $methods = new Methods();
	        $methods->register_routes();

	    }

	    public function hd_wm_woo_gqc_rand_hash() {
			if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
				return bin2hex( openssl_random_pseudo_bytes( 20 ) );
			} else {
				return sha1( wp_rand() );
			}
		}

		public static function hd_wm_woo_gqc_rand_hash_self() {
			if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
				return bin2hex( openssl_random_pseudo_bytes( 20 ) );
			} else {
				return sha1( wp_rand() );
			}
		}

		public function woo_gqc_api_hash( $data ) {
			return hash_hmac( 'sha256', $data, 'wc-api' );
		}

		public static function woo_gqc_api_hash_self( $data ) {
			return hash_hmac( 'sha256', $data, 'wc-api' );
		}


		public function hd_wm_generates_qrcode_fields( $user ) {

			$roles = $user->roles;
			error_log(print_r($roles,true));


			if(in_array('administrator', $roles) || in_array('shop_manager', $roles)){

				echo '<h3>WooManager App</h3>';

				echo '<table class="form-table">';
				echo '<tr>';
				echo '<td colspan="2">Scan this code with the app on your mobile.</td></tr>';

				if (get_the_author_meta('generateqrcode', $user->ID) != "on") {
					echo '<tr><td>';
					echo '<input type="hidden" name="generate-qrcode" value="0" />
				        <input type="checkbox" name="generate-qrcode" <br />';
					echo '	<span class="description">Check to generate a QR Code.</span>';
					echo '</td>';
					echo '</tr>';
				}

				if (get_the_author_meta('generateqrcode', $user->ID) == "on") {

					global $wpdb;
					$string = "select consumer_secret, description from " . $wpdb->prefix . "woocommerce_api_keys where description like 'ck_%' and user_id = " . $user->ID;
					$data = $wpdb->get_row($string);
					$cs_key = $data->description;
					$css_key = $data->consumer_secret;
					$site_url = site_url();

					$string = $cs_key . '|' . $css_key . '|' . $site_url;

					echo '<tr>';
					echo '<td><img src="https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . $string . '" /></tr>';
				}

				echo '</table>';
			}

		}

		public function hd_wm_save_qrcode_fields( $user_id ) {

			if ( !current_user_can( 'edit_user', $user_id ) )
				return false;

			$user = get_userdata( $user_id );
			$roles = $user->roles;
			if(in_array('administrator', $roles) || in_array('shop_manager', $roles)){
				$status          = 2;
				$consumer_key    = 'ck_' . $this->hd_wm_woo_gqc_rand_hash();
				$consumer_secret = 'cs_' . $this->hd_wm_woo_gqc_rand_hash();

				update_usermeta($user_id, 'hellocommerce_csk', $consumer_key);

				$data = array(
					'user_id'         => $user_id,
					'description'     => $consumer_key,
					'permissions'     => 'read_write',
					'consumer_key'    => $this->woo_gqc_api_hash( $consumer_key ),
					'consumer_secret' => $consumer_secret,
					'truncated_key'   => substr( $consumer_key, -7 )
				);

				global $wpdb;
				$wpdb->insert(
					$wpdb->prefix . 'woocommerce_api_keys',
					$data,
					array(
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s'
					)
				);

				$qrcode = intval( $_POST['my-zipcode'] );
				if ( ! $safe_zipcode ) {
				  $safe_zipcode = '';
				}

				update_usermeta($user_id, 'generate-qrcode', sanitize_text_field($_POST['generate-qrcode']));
			}
		}

		function hd_wm_activation_notice() {

	    	// Show error notice if REST API is not active
	  		$hd_wooacf_notice = __('WP REST API is not active and WooManager App is not working!', 'woomanager-app');
        $domain = $_SERVER["HTTP_HOST"];
	  		$download_url = $domain . '/wp-admin/plugin-install.php?s=WordPress+REST+API+(Version+2)&tab=search&type=term';
	  		echo "<div class='error'><p><strong>$hd_wooacf_notice </strong><a href=$download_url>Download here</a></p></div>";
	  	}
		function hd_wm_activation_notice_2() {
	    	// Show error notice if REST API is not active
	  		$hd_wooacf_notice = __('There is no write access to .htaccess. WooManager - Manage your WooCommerce Store plugin might need to modify this line to include the authorization headers for login in to your WooCommerce from outside.', 'woomanager-app');
			echo "<div class='warning'><p><strong>$hd_wooacf_notice </strong><code>RewriteRule ^index\.php$ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]</code></p></div>";
	  	}


	  	public static function check_htaccess() {
		  	$possible_file = ABSPATH . '.htaccess';
		  	if( file_exists($possible_file)) {

			  	// MODIFIY
		  		if (is_writable($possible_file)) {
			  		$file = file_get_contents($possible_file);
			  		$newfile = str_replace('RewriteRule ^index\.php$ - [L]', 'RewriteRule ^index\.php$ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]', $file);
			  		file_put_contents($possible_file, $newfile);
			  		// ALL GOOD
		  		} else {
			  		// NEED TO ALERT THE CLIENT THIS ISNT POSSIBLE
					add_action( 'admin_notices', array($this, 'hd_wm_activation_notice_2'));
		  		}

		  	} else {
			  	// CREATE
				$content = "# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress";
			  	file_put_contents($possible_file, $content);
		  	}

	  	}

	  public static function hd_hs_woomanager_install() {

     	self::check_htaccess();


		$status          = 2;
		$consumer_key    = 'ck_' . self::hd_wm_woo_gqc_rand_hash_self();
		$consumer_secret = 'cs_' . self::hd_wm_woo_gqc_rand_hash_self();

		update_usermeta(get_current_user_id(), 'hellocommerce_csk', $consumer_key);

		$data = array(
			'user_id'         => get_current_user_id(),
			'description'     => $consumer_key,
			'permissions'     => 'read_write',
			'consumer_key'    => self::woo_gqc_api_hash_self( $consumer_key ),
			'consumer_secret' => $consumer_secret,
			'truncated_key'   => substr( $consumer_key, -7 )
		);

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_api_keys',
			$data,
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s'
			)
		);

		update_usermeta(get_current_user_id(), 'generate-qrcode', 1);
		update_usermeta(get_current_user_id(), 'generateqrcode', 'on');

		}

	}
}
if(class_exists('WOOMANAGER_API'))
{

	if ( ! defined( 'ABSPATH' ) ) {
	    exit; // Exit if accessed directly
	}
	new WOOMANAGER_API();
	register_uninstall_hook( __FILE__, 'hd_hs_woomanager_uninstall' );
	register_activation_hook( __FILE__, array('WOOMANAGER_API', 'hd_hs_woomanager_install') );

	function hd_hs_woomanager_uninstall() {
		// Revoke key and delete metas
		$userID = get_current_user_id();

		delete_user_meta($userID, 'generate-qrcode');
		delete_user_meta($userID, 'hellocommerce_csk');
		delete_user_meta($userID, 'generateqrcode');

		global $wpdb;
		$table = $wpdb->prefix . 'woocommerce_api_keys';
		$wpdb->delete($table, array( 'user_id' => $userID), array( '%d' ) );
	}
}
