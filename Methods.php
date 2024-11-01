<?php

class Methods extends WP_REST_Controller {

    public function register_routes() {

		date_default_timezone_set('Europe/Lisbon');
		$namespace = 'woomanager-app';

        register_rest_route( $namespace, '/' . 'menudata', array(
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'menuData' ),
                'permission_callback' => array( $this, 'get_menu_permissions_check' ),
                'args'            => array( ),
            ),

        ) );

         register_rest_route( $namespace, '/' . 'orders', array(
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'queryOrders' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'            => array( ),
            ),

        ) );

        register_rest_route( $namespace, '/' . 'products', array(
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'queryProducts' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'            => array( ),
            ),

        ) );

         register_rest_route( $namespace, '/' . 'customers', array(
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'get_customers' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'            => array( ),
            ),

        ) );

        register_rest_route( $namespace, '/' . 'addsite', array(
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'addSite' ),
                'args'            => array( ),
            ),

        ) );

        register_rest_route( $namespace, '/' . 'addsite', array(
            array(
                'methods'         => WP_REST_Server::CREATABLE,
                'callback'        => array( $this, 'addSite' ),
                'args'            => array( ),
            ),

        ) );
        
        register_rest_route( $namespace, '/' . 'get_total_month', array(
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'getTotalMonth' ),
                'args'            => array( ),
            ),

        ) );

        register_rest_route( $namespace, '/' . 'get_total_week', array(
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'getTotalWeek' ),
                'args'            => array( ),
            ),

        ) );

	}

	public static function getTotalMonth($request) {

		$parameters = array('period' => 'month');
		$response = self::hd_API_Curl_Request($parameters);
		$totalMonth = $response['sales']['net_sales'];

		return $totalMonth;
	}

	public static function getTotalWeek($request) {

		$parameters = array('period' => 'week');
		$response = self::hd_API_Curl_Request($parameters);
		$totalWeek = $response['sales']['net_sales'];

		return $totalWeek;
	}

	public static function addSite($request) {

		// Get data from post
		$params = $request->get_params();
		$login = $params["login"];
		$pass  = $params["pass"];

		error_log(print_r($params, true));

		$user = get_user_by('login', $login);
		error_log(print_r($user, true));
		if ($user && wp_check_password($pass, $user->data->user_pass, $user->ID) ) {
			global $wpdb;
			$cs = $wpdb->get_var("select consumer_secret from " . $wpdb->prefix . "woocommerce_api_keys where user_id = " . $user->ID);
			$ck = $wpdb->get_var("select description from " . $wpdb->prefix . "woocommerce_api_keys where user_id = " . $user->ID);
			if ($cs) {
				return array("result" => true, "data" => array("cs" => $cs, "ck" => $ck));
			}
			else
				return array("result" => false, "data" => array("No consumer secret generated for this user."));

		}
		else
			return array("result" => false, "data" => array("Username or Password invalid for that site."));

	}

	public static function menuData( $request ) {

		global $wpdb;

		// Get data from post
		$params = $request->get_params();
		$data = $params["token"];
		$onesignal = $params["onesignal"];

		// Set Global Option with Consumer Secret
		update_option('woomanager_cs', $data);

		// Name of user
		$name = $wpdb->get_var("select display_name from " . $wpdb->prefix . "users where id = (select user_id from " . $wpdb->prefix . "woocommerce_api_keys where consumer_secret = '$data')");

		// Get avatar from user
		$userEmail = $wpdb->get_var("select user_email from " . $wpdb->prefix . "users where id = (select user_id from " . $wpdb->prefix . "woocommerce_api_keys where consumer_secret = '$data')");
		$avatar = get_avatar_url($userEmail);

		// Update user meta with One Signal Token
		$userID = $wpdb->get_var("select ID from " . $wpdb->prefix . "users where id = (select user_id from " . $wpdb->prefix . "woocommerce_api_keys where consumer_secret = '$data')");
		$oneSignalToken = get_user_meta($userID, '_onesignal_token', true);
		if ($oneSignalToken) {
			if (strpos($oneSignalToken, $onesignal) === false) {
				$value = $oneSignalToken . ',' .$onesignal;
				update_user_meta($userID, '_onesignal_token', $oneSignalToken .','.$onesignal);
			}
		} else {
			update_user_meta($userID, '_onesignal_token', $onesignal);
		}

		// Site name
		$sitename = get_option('blogname');

		// Currency of WooCommerce Store
		$currency = get_option("woocommerce_currency");

		// Symbol of Currency
		$symbol = get_woocommerce_currency_symbol();

		// Currency position
		$currency_pos = get_option('woocommerce_currency_pos');

		// Number of clients
		$clients = $wpdb->get_var("select count(*) from " . $wpdb->prefix . "usermeta where meta_value like '%customer%' and meta_key = '" . $wpdb->prefix . "capabilities'");

		// Sales of Month by day
		$slines = array();

		// Sales of month
		$sales_current_month = $wpdb->get_results("select round(sum(x.total),2) as total, x.dia from (
		select 	round(ifnull(sum(woi.meta_value),0),2) as total,
				day(p.post_date) as dia

		from 	" . $wpdb->prefix . "woocommerce_order_itemmeta woi
				inner join " . $wpdb->prefix . "woocommerce_order_items woc on woi.order_item_id = woc.order_item_id
				inner join " . $wpdb->prefix . "posts p on woc.order_id = p.id

		where 	p.post_status IN ('wc-completed','wc-processing')
				and month(p.post_date) = month(now())
				and year(p.post_date) = year(now())
				and woi.meta_key = '_line_subtotal'

		group by dia
		union all
		select ifnull(sum(-meta_value),0) as total,day(p.post_date) dia from " . $wpdb->prefix . "postmeta pm inner join " . $wpdb->prefix . "posts p on pm.post_id = p.id where pm.meta_key = '_refund_amount' and p.post_type = 'shop_order_refund' and month(p.post_date) = month(now()) and year(p.post_date) = year(now()) group by dia
		) x group by dia
		");

		// Verify if any day dont exist and create with value 0
		foreach ($sales_current_month as $list) {
			$slines[$list->dia] = $list->total;
		}

		$dayToday = 31;

		for ($day = 1; $day <= $dayToday; $day++) {

			if ((array_key_exists($day,$slines)) == "")
				$slines[$day] = "0";
		}

		// Remove keys and put the same order
		$slines1 = array();
		for ($day = 1; $day <= $dayToday; $day++) {

			if (array_key_exists($day,$slines))
				array_push($slines1,$slines[$day]);
		}

		// Number of products
		$products = $wpdb->get_var("select count(*) from " . $wpdb->prefix . "posts where post_type = 'product' and post_status='publish'");

		// Number of Products with low stock
		$lowstock = get_option('woocommerce_notify_low_stock_amount');

		// Products with low stock
		$query = $wpdb->get_results("select p.id, p.post_parent from " . $wpdb->prefix . "postmeta pm inner join " . $wpdb->prefix . "posts p on pm.post_id = p.id where p.id in (select pm.post_id from " . $wpdb->prefix . "postmeta pm inner join " . $wpdb->prefix . "posts p on pm.post_id = p.id where p.post_type in ('product', 'product_variation') and pm.meta_key in ('_manage_stock') and pm.meta_value = 'yes') and pm.meta_key = '_stock' and pm.meta_value <= $lowstock and p.post_status='publish'");
		$list_ls = array();

		foreach ($query as $row) {
			if ($row->post_parent <> 0)
				array_push($list_ls, $row->post_parent);
			else
				array_push($list_ls, $row->id);
		}


		// Sales Pending of Current Year
		$pending = $wpdb->get_var("select ifnull(sum(meta_value),0) as total from " . $wpdb->prefix . "woocommerce_order_itemmeta where order_item_id in (select order_item_id from " . $wpdb->prefix . "woocommerce_order_items where order_id in (select id from " . $wpdb->prefix . "posts where post_status IN ('wc-pending','wc-on-hold') and year(post_date) in (" . date('Y') . "))) and meta_key = '_line_subtotal' and meta_value > 0");

		$url = get_home_url() . "/wc-api/v3/reports/sales";

		$parameters = array('period' => 'year');

		$response = self::hd_API_Curl_Request($parameters);

		$year = $response['sales']['net_sales'];;

		$parameters = array('period' => 'month');

		$response = self::hd_API_Curl_Request($parameters);

		$totalMonth = $response['sales']['net_sales'];;

		// Sales
		$sales = $year;

		$s = (string) "&euro;";


		return array("display_name"=>$name, "avatar" => $avatar, "site_name"=>$sitename, "total_clients"=>$clients, "sales"=>round($sales,2), "sales_graph" => $slines1, "currency"=>$currency, "currency_symbol"=>html_entity_decode($symbol,ENT_COMPAT, 'UTF-8'), "currency_pos" => $currency_pos, "products"=>$products, "low_stock" => $list_ls, "totalmonth" => number_format($totalMonth, 2, '.', ''), "pending" => number_format($pending, 2, '.', ''), "totalSales_current" => number_format($year, 2, '.', ''));

	}

	/**
	 * Get all orders
	 *
	 * @since 2.1
	 * @param int id
	 * @param string user
	 * @param int page
	 * @return array
	 */

	public function queryOrders( $request ) {

		// Get data from post
		$filter = $request->get_params();

		$query = $this->query_orders( $filter );

		$orders = array();

		foreach ( $query as $order ) {
			$new_orders = $this->prepare_item_for_response($order, array());
			$new_orders['customer'] = current( $this->get_customer($new_orders['customer_id'], array()));
			if($new_orders['customer']['id'] == 0){
				$order_obj = wc_get_order( $order );
				$new_orders['customer'] = array(
				'id'               => 0,
				'email'            => $order_obj->billing_email,
				'first_name'       => $order_obj->billing_first_name,
				'last_name'        => $order_obj->billing_last_name,
				'billing_address'  => array(
					'first_name' => $order_obj->billing_first_name,
					'last_name'  => $order_obj->billing_last_name,
					'company'    => $order_obj->billing_company,
					'address_1'  => $order_obj->billing_address_1,
					'address_2'  => $order_obj->billing_address_2,
					'city'       => $order_obj->billing_city,
					'state'      => $order_obj->billing_state,
					'postcode'   => $order_obj->billing_postcode,
					'country'    => $order_obj->billing_country,
					'email'      => $order_obj->billing_email,
					'phone'      => $order_obj->billing_phone,
				),
				'shipping_address' => array(
					'first_name' => $order_obj->shipping_first_name,
					'last_name'  => $order_obj->shipping_last_name,
					'company'    => $order_obj->shipping_company,
					'address_1'  => $order_obj->shipping_address_1,
					'address_2'  => $order_obj->shipping_address_2,
					'city'       => $order_obj->shipping_city,
					'state'      => $order_obj->shipping_state,
					'postcode'   => $order_obj->shipping_postcode,
					'country'    => $order_obj->shipping_country,
				),
			);
			}
			$orders[] = $new_orders;
		}

		return array('orders' => $orders);
	}

	private function query_orders( $args = array() ) {

		$offset = 0;
		$include = '';

		if(isset($args['page'])){
			$offset = ($args['page'] - 1) * 10;
		}

		if(isset($args['id']) && $args['id']){
			$include = $args['id'];
		}

		$meta_args = array(
			'posts_per_page'   => 10,
			'offset'           => $offset,
			'orderby'          => 'date',
			'include'          => $include,
			'order'            => 'DESC',
			'post_type'        => 'shop_order',
			'post_status'      => 'publish',
			'suppress_filters' => true
		);

		if (!empty( $args['user'] ) ) {

			if (strpos($args['user'], ' ') !== false) {
				$search_array = explode(' ', $args['user']);
				$first_name_search = $search_array[0];
				$last_name_search = $search_array[1];

				$meta_args['meta_query'] = array(
				        'relation' => 'AND',
				        array(
				            'key'     => '_billing_first_name',
				            'value'   => $first_name_search,
				            'compare' => 'LIKE'
				        ),
				        array(
				            'key'     => '_billing_last_name',
				            'value'   => $last_name_search,
				            'compare' => 'LIKE'
				        )
				);
			}
			else{
				$meta_args['meta_query'] = array(
				        'relation' => 'OR',
				        array(
				            'key'     => '_billing_first_name',
				            'value'   => $args['user'],
				            'compare' => 'LIKE'
				        ),
				        array(
				            'key'     => '_billing_last_name',
				            'value'   => $args['user'],
				            'compare' => 'LIKE'
				        )
				);
			}

		}

		$orders = get_posts( $meta_args );

		return $orders;
	}

	/**
	 * Get products
	 *
	 * @since 2.1
	 * @param int id
	 * @param string product
	 * @param int page
	 * @return array
	 */

	public function queryProducts( $request ) {

		// Get data from post
		$filter = $request->get_params();

		$query = $this->query_products( $filter );

		$products = array();

		foreach ( $query as $product ) {
			$new_product = $this->prepare_product_for_response($product, array());
			$new_product['title'] = $new_product['name'];
			$products[] = $new_product;

		}

		return array('products' => $products);
	}

	private function query_products( $args = array() ) {

		$offset = 0;
		$include = '';
		$product = '';

		if(isset($args['page'])){
			$offset = ($args['page'] - 1) * 10;
		}

		if(isset($args['id']) && $args['id']){
			$include = $args['id'];
		}

		if (isset($args['product']) && $args['product']) {
			$product = $args['product'];
		}

		$meta_args = array(
			'posts_per_page'   => 10,
			'offset'           => $offset,
			'orderby'          => 'date',
			'include'          => $include,
			'order'            => 'DESC',
			'post_type'        => 'product',
			's'       		   => $product,
			'post_status'      => 'published',
			'suppress_filters' => true
		);

		$products = get_posts( $meta_args );

		return $products;
	}

	/**
	 * Get all customers
	 *
	 * @since 2.1
	 * @param array $fields
	 * @param array $filter
	 * @param int $page
	 * @return array
	 */

	public function get_customers( $request ) {

		// Get data from post
		$filter = $request->get_params();

		$query = $this->query_customers( $filter );

		$customers = array();

		foreach ( $query->get_results() as $user_id ) {
			$customers[] = current( $this->get_customer( $user_id, $fields ) );
		}

		return array( 'customers' => $customers );
	}

	/**
	 * Helper method to get customer user objects
	 *
	 * Note that WP_User_Query does not have built-in pagination so limit & offset are used to provide limited
	 * pagination support
	 *
	 * The filter for role can only be a single role in a string.
	 *
	 * @since 2.3
	 * @param array $args request arguments for filtering query
	 * @return WP_User_Query
	 */

	private function query_customers( $args = array() ) {

		// default users per page
		$users_per_page = get_option( 'posts_per_page' );

		// Set base query arguments
		$query_args = array(
			'fields'  => 'ID',
			'role'    => 'customer',
			'orderby' => 'registered',
			'number'  => $users_per_page

			);

		// Custom Role
		if ( ! empty( $args['role'] ) ) {
			$query_args['role'] = $args['role'];
		}

		// Search
		if (!empty( $args['q'] ) ) {

			if (strpos($args['q'], ' ') !== false) {
				$search_array = explode(' ', $args['q']);
				$first_name_search = $search_array[0];
				$last_name_search = $search_array[1];

				$query_args['search'] = '*'.esc_attr( $args['q'] ).'*';
				$query_args['meta_query'] = array(
				        'relation' => 'AND',
				        array(
				            'key'     => 'first_name',
				            'value'   => $first_name_search,
				            'compare' => 'LIKE'
				        ),
				        array(
				            'key'     => 'last_name',
				            'value'   => $last_name_search,
				            'compare' => 'LIKE'
				        )
				);
			}
			else if(strpos($args['q'], '@') !== false){
				$query_args['search'] = '*'.esc_attr( $args['q'] ).'*';
				$query_args['search_columns'] = array('user_email');
			}
			else{
				$query_args['search'] = '*'.esc_attr( $args['q'] ).'*';
				$query_args['meta_query'] = array(
				        'relation' => 'OR',
				        array(
				            'key'     => 'first_name',
				            'value'   => $args['q'],
				            'compare' => 'LIKE'
				        ),
				        array(
				            'key'     => 'last_name',
				            'value'   => $args['q'],
				            'compare' => 'LIKE'
				        ),
				        array(
				            'key' => 'description',
				            'value' => $args['q'] ,
				            'compare' => 'LIKE'
				        )
				);
			}

		}


		// Limit number of users returned
		if ( ! empty( $args['limit'] ) ) {
			if ( $args['limit'] == -1 ) {
				unset( $query_args['number'] );
			} else {
				$query_args['number'] = absint( $args['limit'] );
				$users_per_page       = absint( $args['limit'] );
			}
		} else {
			$args['limit'] = $query_args['number'];
		}

		// Page
		$page = ( isset( $args['page'] ) ) ? absint( $args['page'] ) : 1;

		// Offset
		if ( ! empty( $args['offset'] ) ) {
			$query_args['offset'] = absint( $args['offset'] );
		} else {
			$query_args['offset'] = $users_per_page * ( $page - 1 );
		}

		// Created date
		if ( ! empty( $args['created_at_min'] ) ) {
			$this->created_at_min = $this->server->parse_datetime( $args['created_at_min'] );
		}

		if ( ! empty( $args['created_at_max'] ) ) {
			$this->created_at_max = $this->server->parse_datetime( $args['created_at_max'] );
		}

		// Order (ASC or DESC, ASC by default)
		if ( ! empty( $args['order'] ) ) {
			$query_args['order'] = $args['order'];
		}

		// Orderby
		if ( ! empty( $args['orderby'] ) ) {
			$query_args['orderby'] = $args['orderby'];

			// Allow sorting by meta value
			if ( ! empty( $args['orderby_meta_key'] ) ) {
				$query_args['meta_key'] = $args['orderby_meta_key'];
			}
		}

		$query = new \WP_User_Query( $query_args );


		// Helper members for pagination headers
		$query->total_pages = ( $args['limit'] == -1 ) ? 1 : ceil( $query->get_total() / $users_per_page );
		$query->page = $page;

		return $query;
	}

	public function get_customer( $id, $fields = null ) {
		global $wpdb;

		$customer = new \WP_User( $id );

		// Get info about user's last order
		$last_order = $wpdb->get_row( "SELECT id, post_date_gmt
						FROM $wpdb->posts AS posts
						LEFT JOIN {$wpdb->postmeta} AS meta on posts.ID = meta.post_id
						WHERE meta.meta_key = '_customer_user'
						AND   meta.meta_value = {$customer->ID}
						AND   posts.post_type = 'shop_order'
						AND   posts.post_status IN ( '" . implode( "','", array_keys( wc_get_order_statuses() ) ) . "' )
						ORDER BY posts.ID DESC
					" );

		$customer_data = array(
			'id'               => $customer->ID,
			/*'created_at'       => $this->server->format_datetime( $customer->user_registered ),*/
			'email'            => $customer->user_email,
			'first_name'       => $customer->first_name,
			'last_name'        => $customer->last_name,
			'username'         => $customer->user_login,
			'role'             => $customer->roles[0],
			'create_at'        => $this->wc_rest_prepare_date_response($customer->user_registered),
			'last_order_id'    => is_object( $last_order ) ? $last_order->id : null,
			'last_order_date'  => null,
			'orders_count'     => wc_get_customer_order_count( $customer->ID ),
			'total_spent'      => wc_format_decimal( wc_get_customer_total_spent( $customer->ID ), 2 ),
			'avatar_url'       => $this->get_avatar_url( $customer->customer_email ),
			'billing_address'  => array(
				'first_name' => $customer->billing_first_name,
				'last_name'  => $customer->billing_last_name,
				'company'    => $customer->billing_company,
				'address_1'  => $customer->billing_address_1,
				'address_2'  => $customer->billing_address_2,
				'city'       => $customer->billing_city,
				'state'      => $customer->billing_state,
				'postcode'   => $customer->billing_postcode,
				'country'    => $customer->billing_country,
				'email'      => $customer->billing_email,
				'phone'      => $customer->billing_phone,
			),
			'shipping_address' => array(
				'first_name' => $customer->shipping_first_name,
				'last_name'  => $customer->shipping_last_name,
				'company'    => $customer->shipping_company,
				'address_1'  => $customer->shipping_address_1,
				'address_2'  => $customer->shipping_address_2,
				'city'       => $customer->shipping_city,
				'state'      => $customer->shipping_state,
				'postcode'   => $customer->shipping_postcode,
				'country'    => $customer->shipping_country,
			),
		);

		return array( 'customer' => apply_filters( 'woocommerce_api_customer_response', $customer_data, $customer, $fields, $this->server ) );
	}

	/**
	 * Validate the request by checking:
	 *
	 * 1) the ID is a valid integer
	 * 2) the ID returns a valid WP_User
	 * 3) the current user has the proper permissions
	 *
	 * @since 2.1
	 * @see WC_API_Resource::validate_request()
	 * @param integer $id the customer ID
	 * @param string $type the request type, unused because this method overrides the parent class
	 * @param string $context the context of the request, either `read`, `edit` or `delete`
	 * @return int|WP_Error valid user ID or WP_Error if any of the checks fails
	 */
	protected function validate_request( $id, $type, $context ) {

		try {
			$id = absint( $id );

			// validate ID
			if ( empty( $id ) ) {
				return new \WP_Error( 'woocommerce_api_invalid_customer_id', __( 'Invalid customer ID', 'woocommerce' ), 404 );
			}

			// non-existent IDs return a valid WP_User object with the user ID = 0
			$customer = new \WP_User( $id );

			if ( 0 === $customer->ID ) {
				return new \WP_Error( 'woocommerce_api_invalid_customer', __( 'Invalid customer', 'woocommerce' ), 404 );
			}

			// validate permissions
			switch ( $context ) {

				case 'read':
					if ( ! current_user_can( 'list_users' ) ) {
						return new \WP_Error( 'woocommerce_api_user_cannot_read_customer', __( 'You do not have permission to read this customer', 'woocommerce' ), 401 );
					}
					break;

				case 'edit':
					if ( ! current_user_can( 'edit_users' ) ) {
						return new \WP_Error( 'woocommerce_api_user_cannot_edit_customer', __( 'You do not have permission to edit this customer', 'woocommerce' ), 401 );
					}
					break;

				case 'delete':
					if ( ! current_user_can( 'delete_users' ) ) {
						return new \WP_Error( 'woocommerce_api_user_cannot_delete_customer', __( 'You do not have permission to delete this customer', 'woocommerce' ), 401 );
					}
					break;
			}

			return $id;
		} catch ( WC_API_Exception $e ) {
			return new \WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}
	/**
	 * Check if the current user can read users
	 *
	 * @since 2.1
	 * @see WC_API_Resource::is_readable()
	 * @param int|WP_Post $post unused
	 * @return bool true if the current user can read users, false otherwise
	 */
	protected function is_readable( $post ) {
		return current_user_can( 'list_users' );
	}
	/**
	 * Wrapper for @see get_avatar() which doesn't simply return
	 * the URL so we need to pluck it from the HTML img tag
	 *
	 * Kudos to https://github.com/WP-API/WP-API for offering a better solution
	 *
	 * @since 2.1
	 * @param string $email the customer's email
	 * @return string the URL to the customer's avatar
	 */
	private function get_avatar_url( $email ) {
		$avatar_html = get_avatar( $email );

		// Get the URL of the avatar from the provided HTML
		preg_match( '/src=["|\'](.+)[\&|"|\']/U', $avatar_html, $matches );

		if ( isset( $matches[1] ) && ! empty( $matches[1] ) ) {
			return esc_url_raw( $matches[1] );
		}

		return null;
	}

	/**
     * Check if a given request has access to get items
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_items_permissions_check( $request ) {
	    if(is_ssl()){
		    return $this->ssl_permissions_check($request);
	    }
	    else{
		    return $this->general_permissions_check($request);
	    }
    }

    public function ssl_permissions_check($request){
      $headers = $request->get_headers();
			if(isset($headers['xuseragent'][0])){
					$expected_agent_iOs = "iOs woomanager client";
					$expected_agent_android = "Android woomanager client";
					if($headers['xuseragent'][0] == $expected_agent_iOs || $headers['xuseragent'][0] == $expected_agent_android){
            try{
				  return $this->perform_basic_authentication($request);
            }
            catch(Exception $e){
              return false;
            }
					}
					else{
            return false;
					}
			}
			return false;
    }

    public function perform_basic_authentication($request){
	    $key = $_SERVER['PHP_AUTH_USER'];
		$secret = $_SERVER['PHP_AUTH_PW'];

		// if the $_GET parameters are present, use those first
		if ( ! empty( $key ) && ! empty( $secret ) ) {
			$keys = $this->get_keys_by_consumer_key( $key );

			if ( ! $this->is_consumer_secret_valid( $keys['consumer_secret'], $secret ) ) {
				throw new Exception( __( 'Consumer Secret is invalid', 'woocommerce' ), 401 );
			}

			return true;
		}
    }

	private function is_consumer_secret_valid( $keys_consumer_secret, $consumer_secret ) {
		return hash_equals( $keys_consumer_secret, $consumer_secret );
	}

    public function general_permissions_check($request){
      $headers = $request->get_headers();
			if(isset($headers['xuseragent'][0])){
					$expected_agent_iOs = "iOs woomanager client";
					$expected_agent_android = "Android woomanager client";
					if($headers['xuseragent'][0] == $expected_agent_iOs || $headers['xuseragent'][0] == $expected_agent_android){
            try{
				  return $this->perform_oauth_authentication($request);
            }
            catch(Exception $e){
              return false;
            }
					}
					else{
            return false;
					}
			}
			return false;
    }

    public function get_menu_permissions_check( $request ) {
	    $headers = $request->get_headers();
		if(isset($headers['xuseragent'][0])){
			$expected_agent_iOs = "iOs woomanager client";
			$expected_agent_android = "Android woomanager client";
			if($headers['xuseragent'][0] == $expected_agent_iOs || $headers['xuseragent'][0] == $expected_agent_android){
				return true;
			}
		}
    }

    private function perform_oauth_authentication($request){
      $params = $request->get_params();

  		$param_names =  array( 'oauth_consumer_key', 'oauth_signature', 'oauth_signature_method' );

  		// Check for required OAuth parameters
  		foreach ( $param_names as $param_name ) {

  			if ( empty( $params[ $param_name ] ) ) {
  				throw new Exception( sprintf( __( '%s parameter is missing', 'woocommerce' ), $param_name ), 404 );
  			}
  		}
  		// Fetch WP user by consumer key
  		$keys = $this->get_keys_by_consumer_key( $params['oauth_consumer_key'] );
  		unset($keys['nonces']);

  		// Perform OAuth validation
  		return $this->check_oauth_signature( $keys, $params, $request );
    }

    private function get_keys_by_consumer_key( $consumer_key ) {
  		global $wpdb;

  		$consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );
  		$keys = $wpdb->get_row( $wpdb->prepare( "
  			SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces
  			FROM {$wpdb->prefix}woocommerce_api_keys
  			WHERE consumer_key = '%s'
  		", $consumer_key ), ARRAY_A );

   		if ( empty( $keys ) ) {
  			throw new Exception( __( 'Consumer Key is invalid', 'woocommerce' ), 401 );
  		}

  		return $keys;
  	}

    private function check_oauth_signature( $keys, $params, $request ) {
  		$http_method = strtoupper($request->get_method());

  		$server_path = $request->get_route();

  		// if the requested URL has a trailingslash, make sure our base URL does as well
  		if ( isset( $_SERVER['REDIRECT_URL'] ) && '/' === substr( $_SERVER['REDIRECT_URL'], -1 ) ) {
  			$server_path .= '/';
  		}

      $woomanager_api_url = get_home_url() . '/wp-json';

  		$base_request_uri = rawurlencode( untrailingslashit( $woomanager_api_url ) . $server_path );

  		// Get the signature provided by the consumer and remove it from the parameters prior to checking the signature
  		$consumer_signature = rawurldecode( $params['oauth_signature'] );
  		unset( $params['oauth_signature'] );

  		// Sort parameters
  		if ( ! uksort( $params, 'strcmp' ) ) {
  			throw new Exception( __( 'Invalid Signature - failed to sort parameters', 'woocommerce' ), 401 );
  		}

  		// Normalize parameter key/values
  		$params = self::normalize_parameters( $params );
  		$query_parameters = array();
  		foreach ( $params as $param_key => $param_value ) {
  			if ( is_array( $param_value ) ) {
  				foreach ( $param_value as $param_key_inner => $param_value_inner ) {
  					$query_parameters[] = $param_key . '%255B' . $param_key_inner . '%255D%3D' . $param_value_inner;
  				}
  			} else {
  				$query_parameters[] = $param_key . '%3D' . $param_value; // join with equals sign
  			}
  		}
  		$query_string = implode( '%26', $query_parameters ); // join with ampersand

  		$string_to_sign = $http_method . '&' . $base_request_uri . '&' . $query_string;

  		if ( $params['oauth_signature_method'] !== 'HMAC-SHA1' && $params['oauth_signature_method'] !== 'HMAC-SHA256' ) {
  			throw new Exception( __( 'Invalid Signature - signature method is invalid', 'woocommerce' ), 401 );
  		}

  		$hash_algorithm = strtolower( str_replace( 'HMAC-', '', $params['oauth_signature_method'] ) );

  		$secret = $keys['consumer_secret'] . '&';
  		$signature = base64_encode( hash_hmac( $hash_algorithm, $string_to_sign, $secret, true ) );
  		if ( ! hash_equals( $signature, $consumer_signature ) ) {
        throw new Exception( __( 'Invalid Signature - provided signature does not match', 'woocommerce' ), 401 );
        return false;
  		}
      else{
        return true;
      }
  	}

    private static function normalize_parameters( $parameters ) {
  		$keys = self::urlencode_rfc3986( array_keys( $parameters ) );
  		$values = self::urlencode_rfc3986( array_values( $parameters ) );
  		$parameters = array_combine( $keys, $values );
  		return $parameters;
  	}

    private static function urlencode_rfc3986( $value ) {
  		if ( is_array( $value ) ) {
  			return array_map( array( self, 'urlencode_rfc3986' ), $value );
  		} else {
  			// Percent symbols (%) must be double-encoded
  			return str_replace( '%', '%25', rawurlencode( rawurldecode( $value ) ) );
  		}
  	}

    /*public function general_permissions_check($request){

		global $wpdb;

		// Get data from post
		$params = $request->get_params();
		$data = $params["token"];

		// Check if consumer secret is valid
		$check = $wpdb->get_var("select count(*) from " . $wpdb->prefix . "woocommerce_api_keys where consumer_secret = '$data'");

		if ($check > 0)
			return true;
		else
			return false;

    }*/

    public function prepare_item_for_response( $post, $request ) {
		global $wpdb;

		$order = wc_get_order( $post );
		$dp    = $request['dp'];

		$data = array(
			'id'                   => $order->id,
			'parent_id'            => $post->post_parent,
			'status'               => $order->get_status(),
			'order_key'            => $order->order_key,
			'currency'             => $order->get_order_currency(),
			'version'              => $order->order_version,
			'prices_include_tax'   => $order->prices_include_tax,
			'created_at'           => $this->wc_rest_prepare_date_response( $post->post_date_gmt ),
			'date_modified'        => $this->wc_rest_prepare_date_response( $post->post_modified_gmt ),
			'customer_id'          => $order->get_user_id(),
			'total_discount'       => wc_format_decimal( $order->get_total_discount(), $dp ),
			'discount_tax'         => wc_format_decimal( $order->cart_discount_tax, $dp ),
			'total_shipping'       => wc_format_decimal( $order->get_total_shipping(), $dp ),
			'shipping_tax'         => wc_format_decimal( $order->get_shipping_tax(), $dp ),
			'cart_tax'             => wc_format_decimal( $order->get_cart_tax(), $dp ),
			'total'                => wc_format_decimal( $order->get_total(), $dp ),
			'subtotal'             => wc_format_decimal( $order->get_subtotal(), $dp ),
			'total_tax'            => wc_format_decimal( $order->get_total_tax(), $dp ),
			'billing'              => array(),
			'shipping'             => array(),
			'payment_method'       => $order->payment_method,
			'payment_method_title' => $order->payment_method_title,
			'transaction_id'       => $order->get_transaction_id(),
			'customer_ip_address'  => $order->customer_ip_address,
			'customer_user_agent'  => $order->customer_user_agent,
			'created_via'          => $order->created_via,
			'customer_note'        => $order->customer_note,
			'date_completed'       => $this->wc_rest_prepare_date_response( $order->completed_date ),
			'date_paid'            => $order->paid_date,
			'cart_hash'            => $order->cart_hash,
			'line_items'           => array(),
			'tax_lines'            => array(),
			'shipping_lines'       => array(),
			'fee_lines'            => array(),
			'coupon_lines'         => array(),
			'refunds'              => array(),
		);

		// Add addresses.
		$data['billing']  = $order->get_address( 'billing' );
		$data['shipping'] = $order->get_address( 'shipping' );

		// Add line items.
		foreach ( $order->get_items() as $item_id => $item ) {
			$product      = $order->get_product_from_item( $item );
			$product_id   = 0;
			$variation_id = 0;
			$product_sku  = null;

			// Check if the product exists.
			if ( is_object( $product ) ) {
				$product_id   = $product->id;
				$variation_id = $product->variation_id;
				$product_sku  = $product->get_sku();
			}

			$meta = new WC_Order_Item_Meta( $item, $product );

			$item_meta = array();

			$hideprefix = 'true' === $request['all_item_meta'] ? null : '_';

			foreach ( $meta->get_formatted( $hideprefix ) as $meta_key => $formatted_meta ) {
				$item_meta[] = array(
					'key'   => $formatted_meta['key'],
					'label' => $formatted_meta['label'],
					'value' => $formatted_meta['value'],
				);
			}

			$line_item = array(
				'id'           => $item_id,
				'name'         => $item['name'],
				'sku'          => $product_sku,
				'product_id'   => (int) $product_id,
				'variation_id' => (int) $variation_id,
				'quantity'     => wc_stock_amount( $item['qty'] ),
				'tax_class'    => ! empty( $item['tax_class'] ) ? $item['tax_class'] : '',
				'price'        => wc_format_decimal( $order->get_item_total( $item, false, false ), $dp ),
				'subtotal'     => wc_format_decimal( $order->get_line_subtotal( $item, false, false ), $dp ),
				'subtotal_tax' => wc_format_decimal( $item['line_subtotal_tax'], $dp ),
				'total'        => wc_format_decimal( $order->get_line_total( $item, false, false ), $dp ),
				'total_tax'    => wc_format_decimal( $item['line_tax'], $dp ),
				'taxes'        => array(),
				'meta'         => $item_meta,
			);

			$item_line_taxes = maybe_unserialize( $item['line_tax_data'] );
			if ( isset( $item_line_taxes['total'] ) ) {
				$line_tax = array();

				foreach ( $item_line_taxes['total'] as $tax_rate_id => $tax ) {
					$line_tax[ $tax_rate_id ] = array(
						'id'       => $tax_rate_id,
						'total'    => $tax,
						'subtotal' => '',
					);
				}

				foreach ( $item_line_taxes['subtotal'] as $tax_rate_id => $tax ) {
					$line_tax[ $tax_rate_id ]['subtotal'] = $tax;
				}

				$line_item['taxes'] = array_values( $line_tax );
			}

			$data['line_items'][] = $line_item;
		}

		// Add taxes.
		foreach ( $order->get_items( 'tax' ) as $key => $tax ) {
			$tax_line = array(
				'id'                 => $key,
				'rate_code'          => $tax['name'],
				'rate_id'            => $tax['rate_id'],
				'label'              => isset( $tax['label'] ) ? $tax['label'] : $tax['name'],
				'compound'           => (bool) $tax['compound'],
				'tax_total'          => wc_format_decimal( $tax['tax_amount'], $dp ),
				'shipping_tax_total' => wc_format_decimal( $tax['shipping_tax_amount'], $dp ),
			);

			$data['tax_lines'][] = $tax_line;
		}

		// Add shipping.
		foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
			$shipping_line = array(
				'id'           => $shipping_item_id,
				'method_title' => $shipping_item['name'],
				'method_id'    => $shipping_item['method_id'],
				'total'        => wc_format_decimal( $shipping_item['cost'], $dp ),
				'total_tax'    => wc_format_decimal( '', $dp ),
				'taxes'        => array(),
			);

			$shipping_taxes = maybe_unserialize( $shipping_item['taxes'] );

			if ( ! empty( $shipping_taxes ) ) {
				$shipping_line['total_tax'] = wc_format_decimal( array_sum( $shipping_taxes ), $dp );

				foreach ( $shipping_taxes as $tax_rate_id => $tax ) {
					$shipping_line['taxes'][] = array(
						'id'       => $tax_rate_id,
						'total'    => $tax,
					);
				}
			}

			$data['shipping_lines'][] = $shipping_line;
		}

		// Add fees.
		foreach ( $order->get_fees() as $fee_item_id => $fee_item ) {
			$fee_line = array(
				'id'         => $fee_item_id,
				'name'       => $fee_item['name'],
				'tax_class'  => ! empty( $fee_item['tax_class'] ) ? $fee_item['tax_class'] : '',
				'tax_status' => 'taxable',
				'total'      => wc_format_decimal( $order->get_line_total( $fee_item ), $dp ),
				'total_tax'  => wc_format_decimal( $order->get_line_tax( $fee_item ), $dp ),
				'taxes'      => array(),
			);

			$fee_line_taxes = maybe_unserialize( $fee_item['line_tax_data'] );
			if ( isset( $fee_line_taxes['total'] ) ) {
				$fee_tax = array();

				foreach ( $fee_line_taxes['total'] as $tax_rate_id => $tax ) {
					$fee_tax[ $tax_rate_id ] = array(
						'id'       => $tax_rate_id,
						'total'    => $tax,
						'subtotal' => '',
					);
				}

				foreach ( $fee_line_taxes['subtotal'] as $tax_rate_id => $tax ) {
					$fee_tax[ $tax_rate_id ]['subtotal'] = $tax;
				}

				$fee_line['taxes'] = array_values( $fee_tax );
			}

			$data['fee_lines'][] = $fee_line;
		}

		// Add coupons.
		foreach ( $order->get_items( 'coupon' ) as $coupon_item_id => $coupon_item ) {
			$coupon_line = array(
				'id'           => $coupon_item_id,
				'code'         => $coupon_item['name'],
				'discount'     => wc_format_decimal( $coupon_item['discount_amount'], $dp ),
				'discount_tax' => wc_format_decimal( $coupon_item['discount_amount_tax'], $dp ),
			);

			$data['coupon_lines'][] = $coupon_line;
		}

		// Add refunds.
		foreach ( $order->get_refunds() as $refund ) {
			$data['refunds'][] = array(
				'id'     => $refund->id,
				'refund' => $refund->get_refund_reason() ? $refund->get_refund_reason() : '',
				'total'  => '-' . wc_format_decimal( $refund->get_refund_amount(), $dp ),
			);
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $order ) );

		return apply_filters( "woocommerce_rest_prepare_{$this->post_type}", $response->data, $post, $request );
	}

	function wc_rest_prepare_date_response( $timestamp, $convert_to_utc = false ) {
		if ( $convert_to_utc ) {
			$timezone = new DateTimeZone( wc_timezone_string() );
		} else {
			$timezone = new DateTimeZone( 'UTC' );
		}

		try {
			if ( is_numeric( $timestamp ) ) {
				$date = new DateTime( "@{$timestamp}" );
			} else {
				$date = new DateTime( $timestamp, $timezone );
			}

			// convert to UTC by adjusting the time based on the offset of the site's timezone
			if ( $convert_to_utc ) {
				$date->modify( -1 * $date->getOffset() . ' seconds' );
			}

		} catch ( Exception $e ) {
			$date = new DateTime( '@0' );
		}

		return $date->format( 'Y-m-d\TH:i:s\Z' );
	}

	protected function prepare_links( $order ) {
		$links = array(
			'self' => array(
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $order->id ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);

		if ( 0 !== (int) $order->post->post_parent ) {
			$links['up'] = array(
				'href' => rest_url( sprintf( '/%s/orders/%d', $this->namespace, $order->post->post_parent ) ),
			);
		}

		return $links;
	}

	public function prepare_product_for_response( $post, $request ) {
		$product = wc_get_product( $post );
		$data    = $this->get_product_data( $product );

		// Add variations to variable products.
		if ( $product->is_type( 'variable' ) && $product->has_child() ) {
			$data['variations'] = $this->get_variation_data( $product );
		}

		// Add grouped products data.
		if ( $product->is_type( 'grouped' ) && $product->has_child() ) {
			$data['grouped_products'] = $product->get_children();
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $product ) );

		/**
		 * Filter the data for a response.
		 *
		 * The dynamic portion of the hook name, $this->post_type, refers to post_type of the post being
		 * prepared for the response.
		 *
		 * @param WP_REST_Response   $response   The response object.
		 * @param WP_Post            $post       Post object.
		 * @param WP_REST_Request    $request    Request object.
		 */
		return apply_filters( "woocommerce_api_product_response", $response->data, $post, $request );
	}

	protected function get_product_data( $product ) {
		$data = array(
			'id'                    => (int) $product->is_type( 'variation' ) ? $product->get_variation_id() : $product->id,
			'name'                  => $product->get_title(),
			'slug'                  => $product->get_post_data()->post_name,
			'permalink'             => $product->get_permalink(),
			'date_created'          => $this->wc_rest_prepare_date_response( $product->get_post_data()->post_date_gmt ),
			'date_modified'         => $this->wc_rest_prepare_date_response( $product->get_post_data()->post_modified_gmt ),
			'type'                  => $product->product_type,
			'status'                => $product->get_post_data()->post_status,
			'featured'              => $product->is_featured(),
			'catalog_visibility'    => $product->visibility,
			'description'           => wpautop( do_shortcode( $product->get_post_data()->post_content ) ),
			'short_description'     => apply_filters( 'woocommerce_short_description', $product->get_post_data()->post_excerpt ),
			'sku'                   => $product->get_sku(),
			'price'                 => $product->get_price(),
			'regular_price'         => $product->get_regular_price(),
			'sale_price'            => $product->get_sale_price() ? $product->get_sale_price() : '',
			'date_on_sale_from'     => $product->sale_price_dates_from ? date( 'Y-m-d', $product->sale_price_dates_from ) : '',
			'date_on_sale_to'       => $product->sale_price_dates_to ? date( 'Y-m-d', $product->sale_price_dates_to ) : '',
			'price_html'            => $product->get_price_html(),
			'on_sale'               => $product->is_on_sale(),
			'purchasable'           => $product->is_purchasable(),
			'total_sales'           => (int) get_post_meta( $product->id, 'total_sales', true ),
			'virtual'               => $product->is_virtual(),
			'downloadable'          => $product->is_downloadable(),
			'downloads'             => $this->get_downloads( $product ),
			'download_limit'        => '' !== $product->download_limit ? (int) $product->download_limit : -1,
			'download_expiry'       => '' !== $product->download_expiry ? (int) $product->download_expiry : -1,
			'download_type'         => $product->download_type ? $product->download_type : 'standard',
			'external_url'          => $product->is_type( 'external' ) ? $product->get_product_url() : '',
			'button_text'           => $product->is_type( 'external' ) ? $product->get_button_text() : '',
			'tax_status'            => $product->get_tax_status(),
			'tax_class'             => $product->get_tax_class(),
			'managing_stock'          => $product->managing_stock(),
			'stock_quantity'        => $product->get_stock_quantity(),
			'in_stock'              => $product->is_in_stock(),
			'backorders'            => $product->backorders,
			'backorders_allowed'    => $product->backorders_allowed(),
			'backordered'           => $product->is_on_backorder(),
			'sold_individually'     => $product->is_sold_individually(),
			'weight'                => $product->get_weight(),
			'dimensions'            => array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
			),
			'shipping_required'     => $product->needs_shipping(),
			'shipping_taxable'      => $product->is_shipping_taxable(),
			'shipping_class'        => $product->get_shipping_class(),
			'shipping_class_id'     => (int) $product->get_shipping_class_id(),
			'reviews_allowed'       => ( 'open' === $product->get_post_data()->comment_status ),
			'average_rating'        => wc_format_decimal( $product->get_average_rating(), 2 ),
			'rating_count'          => (int) $product->get_rating_count(),
			'related_ids'           => array_map( 'absint', array_values( $product->get_related() ) ),
			'upsell_ids'            => array_map( 'absint', $product->get_upsells() ),
			'cross_sell_ids'        => array_map( 'absint', $product->get_cross_sells() ),
			'parent_id'             => $product->is_type( 'variation' ) ? $product->parent->id : $product->get_post_data()->post_parent,
			'purchase_note'         => wpautop( do_shortcode( wp_kses_post( $product->purchase_note ) ) ),
			'categories'            => $this->get_taxonomy_terms( $product ),
			'tags'                  => $this->get_taxonomy_terms( $product, 'tag' ),
			'images'                => $this->get_images( $product ),
			'attributes'            => $this->get_attributes( $product ),
			'default_attributes'    => $this->get_default_attributes( $product ),
			'variations'            => array(),
			'grouped_products'      => array(),
			'menu_order'            => $this->get_product_menu_order( $product ),
			'featured_src'       	=> 	(string) wp_get_attachment_url( get_post_thumbnail_id( $product->is_type( 'variation' ) ? $product->variation_id : $product->id ) ),
		);

		return $data;
	}

	protected function get_downloads( $product ) {
		$downloads = array();

		if ( $product->is_downloadable() ) {
			foreach ( $product->get_files() as $file_id => $file ) {
				$downloads[] = array(
					'id'   => $file_id, // MD5 hash.
					'name' => $file['name'],
					'file' => $file['file'],
				);
			}
		}

		return $downloads;
	}

	protected function get_taxonomy_terms( $product, $taxonomy = 'cat' ) {
		$terms = array();

		foreach ( wp_get_post_terms( $product->id, 'product_' . $taxonomy ) as $term ) {
			$terms[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}

		return $terms;
	}

	protected function get_attributes( $product ) {
		$attributes = array();

		if ( $product->is_type( 'variation' ) ) {
			// Variation attributes.
			foreach ( $product->get_variation_attributes() as $attribute_name => $attribute ) {
				$name = str_replace( 'attribute_', '', $attribute_name );

				// Taxonomy-based attributes are prefixed with `pa_`, otherwise simply `attribute_`.
				if ( 0 === strpos( $attribute_name, 'attribute_pa_' ) ) {
					$attributes[] = array(
						'id'     => $this->wc_attribute_taxonomy_id_by_name( $name ),
						'name'   => $this->get_attribute_taxonomy_label( $name ),
						'option' => $attribute,
					);
				} else {
					$attributes[] = array(
						'id'     => 0,
						'name'   => str_replace( 'pa_', '', $name ),
						'option' => $attribute,
					);
				}
			}
		} else {
			foreach ( $product->get_attributes() as $attribute ) {
				// Taxonomy-based attributes are comma-separated, others are pipe (|) separated.
				if ( $attribute['is_taxonomy'] ) {
					$attributes[] = array(
						'id'        => $attribute['is_taxonomy'] ? $this->wc_attribute_taxonomy_id_by_name( $attribute['name'] ) : 0,
						'name'      => $this->get_attribute_taxonomy_label( $attribute['name'] ),
						'position'  => (int) $attribute['position'],
						'visible'   => (bool) $attribute['is_visible'],
						'variation' => (bool) $attribute['is_variation'],
						'options'   => array_map( 'trim', explode( ',', $product->get_attribute( $attribute['name'] ) ) ),
					);
				} else {
					$attributes[] = array(
						'id'        => 0,
						'name'      => str_replace( 'pa_', '', $attribute['name'] ),
						'position'  => (int) $attribute['position'],
						'visible'   => (bool) $attribute['is_visible'],
						'variation' => (bool) $attribute['is_variation'],
						'options'   => array_map( 'trim', explode( '|', $product->get_attribute( $attribute['name'] ) ) ),
					);
				}
			}
		}

		return $attributes;
	}

	protected function get_attribute_taxonomy_label( $name ) {
		$tax    = get_taxonomy( $name );
		$labels = get_taxonomy_labels( $tax );

		return $labels->singular_name;
	}

	protected function wc_attribute_taxonomy_id_by_name( $name ) {
		$name       = str_replace( 'pa_', '', $name );
		$taxonomies = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_id', 'attribute_name' );

		return isset( $taxonomies[ $name ] ) ? (int) $taxonomies[ $name ] : 0;
	}

	protected function get_default_attributes( $product ) {
		$default = array();

		if ( $product->is_type( 'variable' ) ) {
			foreach ( (array) get_post_meta( $product->id, '_default_attributes', true ) as $key => $value ) {
				if ( 0 === strpos( $key, 'pa_' ) ) {
					$default[] = array(
						'id'     => $this->wc_attribute_taxonomy_id_by_name( $key ),
						'name'   => $this->get_attribute_taxonomy_label( $key ),
						'option' => $value,
					);
				} else {
					$default[] = array(
						'id'     => 0,
						'name'   => str_replace( 'pa_', '', $key ),
						'option' => $value,
					);
				}
			}
		}

		return $default;
	}

	protected function get_product_menu_order( $product ) {
		$menu_order = $product->get_post_data()->menu_order;

		if ( $product->is_type( 'variation' ) ) {
			$variation  = get_post( $product->get_variation_id() );
			$menu_order = $variation->menu_order;
		}

		return $menu_order;
	}

	protected function get_images( $product ) {
		$images = array();
		$attachment_ids = array();

		if ( $product->is_type( 'variation' ) ) {
			if ( has_post_thumbnail( $product->get_variation_id() ) ) {
				// Add variation image if set.
				$attachment_ids[] = get_post_thumbnail_id( $product->get_variation_id() );
			} elseif ( has_post_thumbnail( $product->id ) ) {
				// Otherwise use the parent product featured image if set.
				$attachment_ids[] = get_post_thumbnail_id( $product->id );
			}
		} else {
			// Add featured image.
			if ( has_post_thumbnail( $product->id ) ) {
				$attachment_ids[] = get_post_thumbnail_id( $product->id );
			}
			// Add gallery images.
			$attachment_ids = array_merge( $attachment_ids, $product->get_gallery_attachment_ids() );
		}

		// Build image data.
		foreach ( $attachment_ids as $position => $attachment_id ) {
			$attachment_post = get_post( $attachment_id );
			if ( is_null( $attachment_post ) ) {
				continue;
			}

			$attachment = wp_get_attachment_image_src( $attachment_id, 'full' );
			if ( ! is_array( $attachment ) ) {
				continue;
			}

			$images[] = array(
				'id'            => (int) $attachment_id,
				'date_created'  => $this->wc_rest_prepare_date_response( $attachment_post->post_date_gmt ),
				'date_modified' => $this->wc_rest_prepare_date_response( $attachment_post->post_modified_gmt ),
				'src'           => current( $attachment ),
				'title'         => get_the_title( $attachment_id ),
				'alt'           => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'position'      => (int) $position,
			);
		}

		// Set a placeholder image if the product has no images set.
		if ( empty( $images ) ) {
			$images[] = array(
				'id'            => 0,
				'date_created'  => $this->wc_rest_prepare_date_response( current_time( 'mysql' ) ), // Default to now.
				'date_modified' => $this->wc_rest_prepare_date_response( current_time( 'mysql' ) ),
				'src'           => $this->wc_placeholder_img_src(),
				'title'         => __( 'Placeholder', 'woocommerce' ),
				'alt'           => __( 'Placeholder', 'woocommerce' ),
				'position'      => 0,
			);
		}

		return $images;
	}

	/**
	 * Get an individual variation's data.
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	protected function get_variation_data( $product ) {
		$variations = array();

		foreach ( $product->get_children() as $child_id ) {
			$variation = $product->get_child( $child_id );
			if ( ! $variation->exists() ) {
				continue;
			}

			$post_data = get_post( $variation->get_variation_id() );

			$variations[] = array(
				'id'                 => $variation->get_variation_id(),
				'date_created'       => $this->wc_rest_prepare_date_response( $post_data->post_date_gmt ),
				'date_modified'      => $this->wc_rest_prepare_date_response( $post_data->post_modified_gmt ),
				'permalink'          => $variation->get_permalink(),
				'sku'                => $variation->get_sku(),
				'price'              => $variation->get_price(),
				'regular_price'      => $variation->get_regular_price(),
				'sale_price'         => $variation->get_sale_price(),
				'date_on_sale_from'  => $variation->sale_price_dates_from ? date( 'Y-m-d', $variation->sale_price_dates_from ) : '',
				'date_on_sale_to'    => $variation->sale_price_dates_to ? date( 'Y-m-d', $variation->sale_price_dates_to ) : '',
				'on_sale'            => $variation->is_on_sale(),
				'purchasable'        => $variation->is_purchasable(),
				'virtual'            => $variation->is_virtual(),
				'downloadable'       => $variation->is_downloadable(),
				'downloads'          => $this->get_downloads( $variation ),
				'download_limit'     => '' !== $variation->download_limit ? (int) $variation->download_limit : -1,
				'download_expiry'    => '' !== $variation->download_expiry ? (int) $variation->download_expiry : -1,
				'tax_status'         => $variation->get_tax_status(),
				'tax_class'          => $variation->get_tax_class(),
				'manage_stock'       => $variation->managing_stock(),
				'stock_quantity'     => $variation->get_stock_quantity(),
				'in_stock'           => $variation->is_in_stock(),
				'backorders'         => $variation->backorders,
				'backorders_allowed' => $variation->backorders_allowed(),
				'backordered'        => $variation->is_on_backorder(),
				'weight'             => $variation->get_weight(),
				'dimensions'         => array(
					'length' => $variation->get_length(),
					'width'  => $variation->get_width(),
					'height' => $variation->get_height(),
				),
				'shipping_class'     => $variation->get_shipping_class(),
				'shipping_class_id'  => $variation->get_shipping_class_id(),
				'image'              => $this->get_images( $variation ),
				'attributes'         => $this->get_attributes( $variation ),
			);
		}

		return $variations;
	}

	private static function setup_report( $filter ) {

		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );
		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-report-sales-by-date.php' );

		$report = new WC_Report_Sales_By_Date();

		if ( empty( $filter['period'] ) ) {

			// custom date range
			$filter['period'] = 'custom';

			$_GET['start_date'] = $_GET['end_date'] = date( 'Y-m-d', current_time( 'timestamp' ) );
		} else {

			// ensure period is valid
			if ( ! in_array( $filter['period'], array( 'week', 'month', 'last_month', 'year' ) ) ) {
				$filter['period'] = 'week';
			}

			if ( 'week' === $filter['period'] ) {
				$filter['period'] = '7day';
			}
		}

		$report->calculate_current_range( $filter['period'] );

		return $report;
	}

	private static function hd_API_Curl_Request($filter){
		// set date filtering
		$report = self::setup_report( $filter );

		// new customers
		$users_query = new WP_User_Query(
			array(
				'fields' => array( 'user_registered' ),
				'role'   => 'customer',
			)
		);

		$customers = $users_query->get_results();

		foreach ( $customers as $key => $customer ) {
			if ( strtotime( $customer->user_registered ) < $report->start_date || strtotime( $customer->user_registered ) > $report->end_date ) {
				unset( $customers[ $key ] );
			}
		}

		$total_customers = count( $customers );
		$report_data     = $report->get_report_data();
		$period_totals   = array();

		// setup period totals by ensuring each period in the interval has data
		for ( $i = 0; $i <= $report->chart_interval; $i ++ ) {

			switch ( $report->chart_groupby ) {
				case 'day' :
					$time = date( 'Y-m-d', strtotime( "+{$i} DAY", $report->start_date ) );
					break;
				default :
					$time = date( 'Y-m', strtotime( "+{$i} MONTH", $report->start_date ) );
					break;
			}

			// set the customer signups for each period
			$customer_count = 0;
			foreach ( $customers as $customer ) {
				if ( date( ( 'day' == $report->chart_groupby ) ? 'Y-m-d' : 'Y-m', strtotime( $customer->user_registered ) ) == $time ) {
					$customer_count++;
				}
 			}

			$period_totals[ $time ] = array(
				'sales'     => wc_format_decimal( 0.00, 2 ),
				'orders'    => 0,
				'items'     => 0,
				'tax'       => wc_format_decimal( 0.00, 2 ),
				'shipping'  => wc_format_decimal( 0.00, 2 ),
				'discount'  => wc_format_decimal( 0.00, 2 ),
				'customers' => $customer_count,
			);
		}

		// add total sales, total order count, total tax and total shipping for each period
		foreach ( $report_data->orders as $order ) {
			$time = ( 'day' === $report->chart_groupby ) ? date( 'Y-m-d', strtotime( $order->post_date ) ) : date( 'Y-m', strtotime( $order->post_date ) );

			if ( ! isset( $period_totals[ $time ] ) ) {
				continue;
			}

			$period_totals[ $time ]['sales']    = wc_format_decimal( $order->total_sales, 2 );
			$period_totals[ $time ]['tax']      = wc_format_decimal( $order->total_tax + $order->total_shipping_tax, 2 );
			$period_totals[ $time ]['shipping'] = wc_format_decimal( $order->total_shipping, 2 );
		}

		foreach ( $report_data->order_counts as $order ) {
			$time = ( 'day' === $report->chart_groupby ) ? date( 'Y-m-d', strtotime( $order->post_date ) ) : date( 'Y-m', strtotime( $order->post_date ) );

			if ( ! isset( $period_totals[ $time ] ) ) {
				continue;
			}

			$period_totals[ $time ]['orders']   = (int) $order->count;
		}

		// add total order items for each period
		foreach ( $report_data->order_items as $order_item ) {
			$time = ( 'day' === $report->chart_groupby ) ? date( 'Y-m-d', strtotime( $order_item->post_date ) ) : date( 'Y-m', strtotime( $order_item->post_date ) );

			if ( ! isset( $period_totals[ $time ] ) ) {
				continue;
			}

			$period_totals[ $time ]['items'] = (int) $order_item->order_item_count;
		}

		// add total discount for each period
		foreach ( $report_data->coupons as $discount ) {
			$time = ( 'day' === $report->chart_groupby ) ? date( 'Y-m-d', strtotime( $discount->post_date ) ) : date( 'Y-m', strtotime( $discount->post_date ) );

			if ( ! isset( $period_totals[ $time ] ) ) {
				continue;
			}

			$period_totals[ $time ]['discount'] = wc_format_decimal( $discount->discount_amount, 2 );
		}

		$sales_data  = array(
			'total_sales'       => $report_data->total_sales,
			'net_sales'         => $report_data->net_sales,
			'average_sales'     => $report_data->average_sales,
			'total_orders'      => $report_data->total_orders,
			'total_items'       => $report_data->total_items,
			'total_tax'         => wc_format_decimal( $report_data->total_tax + $report_data->total_shipping_tax, 2 ),
			'total_shipping'    => $report_data->total_shipping,
			'total_refunds'     => $report_data->total_refunds,
			'total_discount'    => $report_data->total_coupons,
			'totals_grouped_by' => $report->chart_groupby,
			'totals'            => $period_totals,
			'total_customers'   => $total_customers,
		);

		return array( 'sales' => apply_filters( 'woocommerce_api_report_response', $sales_data, $report, $fields, null ) );
	}
}
