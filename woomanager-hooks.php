<?php 
	
/****
	* ALL THE HOOKS TO SEND PUSH NOTIFICATIONS
****/

if(!class_exists('WOOMANAGER_APP')){

	class WOOMANAGER_APP {
		
		function __construct() {
			add_action('woocommerce_order_status_processing', array($this, 'hd_wm_woocommerce_order_status_processing') );
			add_action('woocommerce_order_status_refunded', array($this, 'hd_wm_woocommerce_order_status_refunded') );
			add_action('woomanager_week_notification', array($this, 'hd_wm_getSalesWeek'));
			add_action('woomanager_month_notification', array($this, 'hd_wm_getSalesMonth'));
			add_filter('cron_schedules', array($this, 'hd_wm_intervals') );
			add_action('save_post', array($this, 'hd_wm_newOrder'), 100, 2 );
			
			add_action('woocommerce_checkout_order_processed', array($this, 'add_order_status_hooks'), 11, 1);
			
			add_action('wp', array($this, 'hd_wm_activation'));	
			add_filter('woocommerce_new_order_note_data', array($this, 'hd_wm_woocommerce_new_note'), 10, 2);
			add_action('woocommerce_no_stock', array($this, 'hd_wm_woocommerce_no_stock'), 10, 1 ); 
			add_action('woocommerce_low_stock', array($this, 'hd_wm_woocommerce_low_stock'), 10, 1 ); 
			
			register_activation_hook(__FILE__, array($this, 'hd_wm_activation'));
			register_deactivation_hook(__FILE__, array($this, 'hd_wm_deactivation'));
		}
				
		// Order Processing 
		public function hd_wm_woocommerce_order_status_processing($order_id) {
					
			// Content
			$content = '%23' . $order_id;
			
			// Screen
			$screen = 'orders';
			
			// Type
			$type = 'Processing';
			
			// Site URL
			$url = site_url();
			
			$this->hd_wm_sendCall("http://api.woomanagerapp.com/wp-json/woomanager-api/websites/order_status?screen=$screen&url=$url&content=$content&type=$type");
		
		}
		
		// Order Processing 
		public function hd_wm_woocommerce_order_status_refunded ($order_id) {
					
			// Content
			$content = '%23' . $order_id;
			
			// Screen
			$screen = 'orders';
			
			// Type
			$type = 'Refunded';
			
			// Site URL
			$url = site_url();
		
			$this->hd_wm_sendCall("http://api.woomanagerapp.com/wp-json/woomanager-api/websites/order_status?screen=$screen&url=$url&content=$content&type=$type");
		
		}
			
		// New Order - backend
		public function hd_wm_newOrder($post_id, $post) {
			$slug = 'shop_order';
			
			if(is_admin()){
			
			    // Execute only when post type is like shop order
			    if ($slug == $post->post_type && $post->post_date == $post->post_modified && $post->post_status != "auto-draft") {
						
					// Content
					$content = '%23' . $post_id;
					
					// Screen
					$screen = 'orders';
					
					// Site URL
					$url = site_url();
					
					// Currency
					$currency = get_option('woocommerce_currency');
					
					// Total Order
					$order = new WC_Order($post_id); 
					$total = $order->get_total();
										
					$this->hd_wm_sendCall("http://api.woomanagerapp.com/wp-json/woomanager-api/websites/order_new?screen=$screen&url=$url&content=$content&total=$total&currency=$currency");
			    }
		    }
		  
		
		}
		
		// New Order - frontend
		public function add_order_status_hooks($post_id) {
			$slug = 'shop_order';
								
			// Content
			$content = '%23' . $post_id;
			
			// Screen
			$screen = 'orders';
			
			// Site URL
			$url = site_url();
			
			// Currency
			$currency = get_option('woocommerce_currency');
			
			// Total Order
			$order = new WC_Order($post_id); 
			$total = $order->get_total();
			
			error_log('Order criada (New Order Frontend)');
			error_log($total);
			error_log($post->post_status);
			
			$this->hd_wm_sendCall("http://api.woomanagerapp.com/wp-json/woomanager-api/websites/order_new?screen=$screen&url=$url&content=$content&total=$total&currency=$currency");
		  
		
		}
		
		// New Note
		public function hd_wm_woocommerce_new_note( $compact, $array ) { 
					
			// Content
			$content = '%23' . $array["order_id"];
			
			// Screen
			$screen = 'orders';
			
			// Type
			$note_type = $array["is_customer_note"];
			
			if ($note_type == "0")
				$type = 'Private';
			else
				$type = 'Customer'; 
			
			// Site URL
			$url = site_url();
				
			// Author
			$author = $compact["comment_author"];
			$pos = strpos($compact["comment_content"], "Order status changed");
			
			if ($author != 'WooCommerce' and $pos === false) 
				$this->hd_wm_sendCall("http://api.woomanagerapp.com/wp-json/woomanager-api/websites/order_note?screen=$screen&url=$url&content=$content&type=$type");
			
			return $compact;
		}
			
		// Product Low Stock
		public function hd_wm_woocommerce_low_stock( $product ) { 
						
			// Content
			$name = urlencode($product->post->post_title);
			
			// Screen
			$screen = 'products';
			
			// Stock
			$stock = $product->stock;
			
			// Product ID
			$id = $product->post->ID;
		
			// Site URL
			$url = site_url();

			$this->hd_wm_sendCall("http://api.woomanagerapp.com/wp-json/woomanager-api/websites/low_stock?screen=$screen&url=$url&name=$name&stock=$stock&id=$id");
			
		} 
		
		// Product No Stock
		public function hd_wm_woocommerce_no_stock( $product ) { 
						
			// Content
			$name = urlencode($product->post->post_title);
			
			// Screen
			$screen = 'products';
			
			// Stock
			$stock = $product->stock;
		
			// Site URL
			$url = site_url();
			
			// Product ID
			$id = $product->post->ID;
			$this->hd_wm_sendCall("http://api.woomanagerapp.com/wp-json/woomanager-api/websites/no_stock?screen=$screen&token=$tokens&url=$url&name=$name&id=$id");
			
		}
			
		// Schedule notification to Send Total Net Sales on every Week
		public function hd_wm_getSalesWeek() {
							
			// Site URL
			$url = site_url();
			
			// Currency
			$currency = get_option('woocommerce_currency');
			
			// CS - WooCommerce
			$cs = get_option('woomanager_cs');
			
			// create curl resource 
		    $ch = curl_init(); 
		
		    // set url 
		    curl_setopt($ch, CURLOPT_URL, "https://web.hellodev.us/hellocommerce/wp-json/woomanager-app/get_total_week?token=$cs"); 
		
		    //return the transfer as a string 
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		
		    // $output contains the output string 
		    $output = curl_exec($ch); 
		
		    // close curl resource to free up system resources 
		    curl_close($ch); 
		    
		    $value = str_replace('"', '', $output);
		    $value = preg_replace('/\s+/', '', $value);
			
			$this->hd_wm_sendCall("http://api.woomanagerapp.com/wp-json/woomanager-api/websites/week_sales?screen=dashboard&url=$url&value=$value&currency=$currency");
		}
		
		// Schedule notification to Send Total Net Sales on every Month
		public function hd_wm_getSalesMonth() {
							
			// Site URL
			$url = site_url();
			
			// Currency
			$currency = get_option('woocommerce_currency');
						
			// create curl resource 
		    $ch = curl_init(); 
		
		    // set url 
		    curl_setopt($ch, CURLOPT_URL, "https://web.hellodev.us/hellocommerce/wp-json/woomanager-app/get_total_month"); 
		
		    //return the transfer as a string 
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		
		    // $output contains the output string 
		    $output = curl_exec($ch); 
		
		    // close curl resource to free up system resources 
		    curl_close($ch); 
		    
		    $value = str_replace('"', '', $output);
		    $value = preg_replace('/\s+/', '', $value);
			
			$this->hd_wm_sendCall("http://api.woomanagerapp.com/wp-json/woomanager-api/websites/month_sales?screen=dashboard&token=$tokens&url=$url&value=$value&currency=$currency");
			
		}
		
		public function hd_wm_intervals( $schedules ) {
		 
		    // add a 'weekly' interval
			$schedules['weekly'] = array(
				'interval' => 604800,
				'display' => __('Once Weekly')
			);
			$schedules['monthly'] = array(
				'interval' => 2635200,
				'display' => __('Once a month')
			);
			return $schedules;
			
		}
		
		public function hd_wm_activation() {
			if ( !wp_next_scheduled( 'woomanager_week_notification' ) ) {
				wp_schedule_event('1476662340', 'weekly', 'woomanager_week_notification');
			}
			
			if ( !wp_next_scheduled( 'woomanager_month_notification' ) ) {
				wp_schedule_event('1477871940', 'monthly', 'woomanager_month_notification');
			}
		}
		
		public function hd_wm_deactivation() {
			wp_clear_scheduled_hook('woomanager_week_notification');
			wp_clear_scheduled_hook('woomanager_month_notification');
		}
		
		// Send the WooManager API call
		public function hd_wm_sendCall($url) {
			
			ob_start();
			// create a new cURL resource
			$ch = curl_init();
				
			// set URL and other appropriate options
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			
			// grab URL and pass it to the browser
			curl_exec($ch);
			
			// close cURL resource, and free up system resources
			curl_close($ch);
			ob_clean();
		}
	}
}

?>