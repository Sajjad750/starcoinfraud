<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://myportfoliosajjad.netlify.app/
 * @since             1.0.0
 * @package           SC_Fraud_Prevention
 *
 * Plugin Name:       Star Coin Fraud Prevention
 * Plugin URI:        https://starcoinsavings.com/
 * Description:       Star Coin Fraud prevention
 * Version:           1.0.0
 * Author:            Sajjad Ahmad
 * Author URI:        https://myportfoliosajjad.netlify.app/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       sc-fraud-prevention
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

foreach ( glob( plugin_dir_path( __FILE__ ) . 'admin/*.php' ) as $file ) {
	include_once $file;
}	

// Include logger class
require_once plugin_dir_path( __FILE__ ) . 'includes/class-dd-fraud-logger.php';

// Include StarMaker API integration class
require_once plugin_dir_path( __FILE__ ) . 'includes/class-starmaker-api-integration.php';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'DD_FRAUD_PREVENTION_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-dd-fraud-prevention-activator.php
 */
function activate_dd_fraud_prevention() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-dd-fraud-prevention-activator.php';
	Dd_Fraud_Prevention_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-dd-fraud-prevention-deactivator.php
 */
function deactivate_dd_fraud_prevention() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-dd-fraud-prevention-deactivator.php';
	Dd_Fraud_Prevention_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_dd_fraud_prevention' );
register_deactivation_hook( __FILE__, 'deactivate_dd_fraud_prevention' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-dd-fraud-prevention.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_dd_fraud_prevention() {

	$plugin = new Dd_Fraud_Prevention();
	$plugin->run();

	Dd_Order_Statuses::init();

	$settings = new Settings( new Settings_Page(), new Import_Export_Page(), new Listings_Page(), new Add_Entry_Page() );
	$settings->init();
	
	// Initialize logger
	$logger = new DD_Fraud_Logger();
	$logger->init();
	
	// Initialize StarMaker API integration
	$starmaker_api = new SC_StarMaker_API_Integration();
	$starmaker_api->init();

}

run_dd_fraud_prevention();

// validate the 2 Starmaker ID inputs
add_action( 'woocommerce_after_checkout_validation', 'dd_validate_starmaker_ids', 10, 2 );
 
add_action( 'woocommerce_after_checkout_validation', 'dd_validate_starmaker_ids', 10, 2 );
 
function dd_validate_starmaker_ids( $fields, $errors ){
 
    if ($fields['billing_starmaker_id'] !== $fields['billing_confirm_starmaker_id']) {
        $errors->add( 'validation', 'Oops, the Starmaker IDs you entered don\'t match. Please correct this error to proceed.' );
    }

		if (str_contains($fields['billing_starmaker_id'], " ")) {
			$errors->add( 'validation', 'Oops, your Starmaker ID cannot contain spaces. Please correct this error to proceed.' );
	}
}

add_action( 'wp_footer', 'dd_add_starmaker_id_checkout_validation_js');

//old

function dd_add_starmaker_id_checkout_validation_js() {
 
	// we need it only on our checkout page
	if( ! is_checkout() ) {
		return;
	}
 
	?>
	<script>
	jQuery(function($){
		// Starmaker ID inputs need to match
		$( 'body' ).on( 'blur change', '#billing_confirm_starmaker_id', function(){

			const wrapper = $(this).closest( '.form-row' );
			let starmakerId = $('#billing_starmaker_id').val();
			let val = $(this).val();

			if( starmakerId !== val ) {
				wrapper.addClass( 'woocommerce-invalid' );
				wrapper.removeClass( 'woocommerce-validated' );
			}
			elseif ( val.indexOf(' ') > -1 )
			{
				wrapper.addClass( 'woocommerce-invalid' );
				wrapper.removeClass( 'woocommerce-validated' );
			} else {
				wrapper.addClass( 'woocommerce-validated' );
				wrapper.removeClass( 'woocommerce-invalid' ); 
			}
		});

		// Starmaker ID can't contain spaces
		$( 'body' ).on( 'blur change', '#billing_starmaker_id', function(){
			const wrapper = $(this).closest( '.form-row' );
			const val = $(this).val();

			if( val.indexOf(' ') > -1 ) {
				wrapper.addClass( 'woocommerce-invalid' );
				wrapper.removeClass( 'woocommerce-validated' );

				if (!wrapper.find('.error-message').length)
				{
					wrapper.append('<p class="error-message" style="color:#a00">Starmaker ID cannot contain spaces</p>')
				}
			} else {
				wrapper.addClass( 'woocommerce-validated' );
				wrapper.removeClass( 'woocommerce-invalid' ); 
				wrapper.find('.error-message').remove();
			}
});
	});
	</script>
	<?php
}

add_action( 'woocommerce_checkout_order_processed', 'dd_scan_orders_for_fraud', 1000, 3);

function dd_scan_orders_for_fraud($order_id, $posted_data, $order)
{
	if ( ! $order_id ) {
		return;
	}

	// back end validation for matching Starmaker ID inputs
	$starmaker_id = $order->get_meta('_billing_starmaker_id');
	$starmaker_confirm_id = $order->get_meta('_billing_confirm_starmaker_id');

	if ($starmaker_id !== $starmaker_confirm_id) {
		throw new Exception( __( "Oops, the Starmaker IDs you entered don't match. Please correct this error to proceed.", 'dd_fraud' ) );
	}

	$status = dd_run_manual_scan($order);

	if ($status === "blocked") {
		throw new Exception( __( 'Our fraud system has detected a problem with this order and has blocked it. If there is a mistake, please email Support at <a href="mailto:starcoinsavings@gmail.com">starcoinsavings@gmail.com.</a> We are open 24 hours a day, 7 days a week.</a>', 'dd_fraud' ) );
	}
	elseif ($status !== "verified-email")
	{
		dd_run_automatic_scan($order, $status);
	}
}

// Function to get customer IP address with improved validation
function dd_get_customer_ip() {
    $ipaddress = '';
    
    // Check for Cloudflare
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    // Check for other proxy headers
    else if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    }
    else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    }
    else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    }
    else if (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    }
    else if (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    }
    else {
        $ipaddress = 'UNKNOWN';
    }

    // Clean the IP address
    $ipaddress = filter_var($ipaddress, FILTER_VALIDATE_IP);
    
    // If validation failed, return UNKNOWN
    if ($ipaddress === false) {
        $ipaddress = 'UNKNOWN';
    }
    
    return $ipaddress;
}

// Function to check if IP is from a VPN
function dd_check_vpn_ip($ip_address) {
    // Check using IPQualityScore API
    $api_result = dd_check_ipqualityscore($ip_address);
    
    if ($api_result !== null) {
        // Log VPN detection
        $logger = new DD_Fraud_Logger();
        $logger->log('VPN Detection', "IP address {$ip_address} detected as VPN using IPQualityScore API");
        return $api_result;
    }
    
    // If API check fails, log the failure and return false
    $logger = new DD_Fraud_Logger();
    $logger->log('VPN Detection', "IPQualityScore API check failed for IP address {$ip_address}");
    return false;
}

/**
 * Check IP address using IPQualityScore API
 * 
 * @param string $ip_address The IP address to check
 * @return bool|null Returns true if VPN detected, false if not, null if API check failed
 */
function dd_check_ipqualityscore($ip_address) {
    // Get API key from WordPress options
    $api_key = get_option('dd_ipqualityscore_api_key');
    if (empty($api_key)) {
        error_log('IPQualityScore API Error: No API key configured');
        return null;
    }

    // Build API URL
    $url = add_query_arg(
        array(
            'key' => $api_key,
            'ip' => $ip_address,
            'strictness' => 1, // 0-3, higher means stricter checking
            'allow_public_access_points' => 'true',
            'fast' => 'false',
            'lighter_penalties' => 'false'
        ),
        'https://www.ipqualityscore.com/api/json/ip'
    );

    // Make API request
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        error_log('IPQualityScore API Error: ' . $response->get_error_message());
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data) || !isset($data['vpn'])) {
        error_log('IPQualityScore API Error: Invalid response format');
        return null;
    }

    // Log the full response for debugging
    error_log('IPQualityScore API Response: ' . print_r($data, true));

    // Check if IP is using VPN/proxy
    return ($data['vpn'] === true || $data['proxy'] === true || $data['tor'] === true);
}

// Helper function to check if IP is in range
function dd_ip_in_range($ip, $range) {
    list($range, $netmask) = explode('/', $range, 2);
    $range_decimal = ip2long($range);
    $ip_decimal = ip2long($ip);
    $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
    $netmask_decimal = ~ $wildcard_decimal;
    return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
}

// Function to check past orders for inconsistencies
function dd_check_past_orders($order) {
    $past_orders_limit = get_option('dd_fraud_past_orders_check', 10);
    $current_email = $order->get_billing_email();
    $current_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $current_starmaker_id = $order->get_meta('_billing_starmaker_id');

    
    $args = array(
        'limit' => $past_orders_limit,
        'exclude' => array($order->get_id()),
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $past_orders = wc_get_orders($args);
    $inconsistencies = array();
    
    foreach ($past_orders as $past_order) {
        $past_email = $past_order->get_billing_email();
        $past_name = $past_order->get_billing_first_name() . ' ' . $past_order->get_billing_last_name();
        $past_starmaker_id = $past_order->get_meta('_billing_starmaker_id');
        
        if ($past_email !== $current_email) {
            $inconsistencies[] = "Email mismatch with order #" . $past_order->get_id();
        }
        if ($past_name !== $current_name) {
            $inconsistencies[] = "Name mismatch with order #" . $past_order->get_id();
        }
        if ($past_starmaker_id !== $current_starmaker_id) {
            $inconsistencies[] = "Starmaker ID mismatch with order #" . $past_order->get_id();
        }
    }
    
    return $inconsistencies;
}

// Check order data (Starmer ID, email, customer name, IP) to see if they are stored in the database as blocked, review or verified
function dd_run_manual_scan($order) {
	global $wpdb;

	// fetch data
	$starmaker_table = $wpdb->prefix . 'dd_fraud_starmaker_id';
	$email_table = $wpdb->prefix . 'dd_fraud_email';
	$customer_name_table = $wpdb->prefix . 'dd_fraud_customer_name';
	$ip_table = $wpdb->prefix . 'dd_fraud_ip';

	$order_id = $order->get_id();
	$starmaker_id = $order->get_meta('_billing_starmaker_id');
	$email = $order->get_billing_email();
	$name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
	$ip_address = $order->get_meta('_customer_ip');

	// Debug logging for IP check
	error_log('DD Fraud Prevention - Checking IP: ' . $ip_address);

	// Check IP address
	if (!empty($ip_address) && $ip_address !== 'UNKNOWN') {
		$ip_query = $wpdb->prepare("SELECT * FROM $ip_table WHERE ip_address = %s", $ip_address);
		$ip_row = $wpdb->get_row($ip_query, ARRAY_A);
		
		if (!empty($ip_row)) {
			error_log('DD Fraud Prevention - IP Match Found: ' . print_r($ip_row, true));
			
			$order_check_arr['ip_address'] = [
				'flag' => $ip_row['flag'],
				'notes' => $ip_row['notes']
			];

			if ($ip_row['flag'] === "blocked") {
				// Log IP blocked
				$logger = new DD_Fraud_Logger();
				$logger->log('Order Blocked', "Order #{$order_id} blocked due to blocked IP address: {$ip_address}");
				return "blocked";
			}
			elseif ($ip_row['flag'] === "review") {
				// Log IP review
				$logger = new DD_Fraud_Logger();
				$logger->log('Order Review', "Order #{$order_id} flagged for review due to IP address: {$ip_address}");
				return "review";
			}
			elseif ($ip_row['flag'] === "verified") {
				// Log IP verified
				$logger = new DD_Fraud_Logger();
				$logger->log('Order Verified', "Order #{$order_id} verified due to verified IP address: {$ip_address}");
				return "verified";
			}
		} else {
			// Add IP to order check array even if not found in database
			$order_check_arr['ip_address'] = [
				'flag' => 'check',
				'notes' => 'IP address checked'
			];
		}
	}

	$starmaker_id_query = $wpdb->prepare( "SELECT * FROM $starmaker_table WHERE %s LIKE starmaker_id" , $starmaker_id );
	$starmaker_id_row = $wpdb->get_row( $starmaker_id_query, ARRAY_A );
	$starmaker_id_row = isset($starmaker_id_row) ? $starmaker_id_row : [];

	$email_query = $wpdb->prepare( "SELECT * FROM $email_table WHERE %s LIKE email" , $email );
	$email_row = $wpdb->get_row( $email_query, ARRAY_A );
	$email_row = isset($email_row) ? $email_row : [];

	$name_query = $wpdb->prepare( "SELECT * FROM $customer_name_table WHERE %s LIKE customer_name" , $name );
	$name_row = $wpdb->get_row( $name_query, ARRAY_A );

	$is_email_verifed = false;
	$is_email_verified = isset($is_email_verified) ? $is_email_verified : false;
	$is_verified = false;
	$is_blocked = false;
	$review_required = false;

	$order_check_arr = [];
	
	if (!empty($starmaker_id_row))
	{
		if ($starmaker_id_row['flag'] === "verified")
		{
			$is_verified = true;
		}
		elseif ($starmaker_id_row['flag'] === "review")
		{
			$review_required = true;
		}
		elseif ($starmaker_id_row['flag'] === "blocked")
		{
			$is_blocked = true;
		}

		if (str_contains($starmaker_id_row['starmaker_id'], "%"))
		{
			$notes = $starmaker_id_row['notes'] . "<br>" . "Matched wildcard: " . $starmaker_id_row['starmaker_id']; 
		}
		else
		{
			$notes = $starmaker_id_row['notes'];
		}

		$order_check_arr['starmaker_id'] = [
			'flag' => $starmaker_id_row['flag'],
			'notes' => $notes
		];
	}

	if (!empty($email_row))
	{
		if ($email_row['flag'] === "verified")
		{
			$is_email_verified = true;
		}
		elseif ($email_row['flag'] === "review")
		{
			$review_required = true;
		}
		elseif ($email_row['flag'] === "blocked")
		{
			$is_blocked = true;
		}

		if (str_contains($email_row['email'], "%"))
		{
			$notes = $email_row['notes'] . "<br>" . "Matched wildcard: " . $email_row['email']; 
		}
		else
		{
			$notes = $email_row['notes'];
		}

		$order_check_arr['email'] = [
			'flag' => $email_row['flag'],
			'notes' => $notes
		];
	}

	if (!empty($name_row))
	{
		if ($name_row['flag'] === "verified")
		{
			$is_verified = true;
		}
		elseif ($name_row['flag'] === "review")
		{
			$review_required = true;
		}
		elseif ($name_row['flag'] === "blocked")
		{
			$is_blocked = true;
		}
	
		if (str_contains($name_row['customer_name'], "%"))
		{
			$notes = $name_row['notes'] . "<br>" . "Matched wildcard: " . $name_row['customer_name']; 
		}
		else
		{
			$notes = $name_row['notes'];
		}

		$order_check_arr['customer_name'] = [
			'flag' => $name_row['flag'],
			'notes' => $notes
		];
	}

	// if email is verified, that takes precedence over found blocked Starmaker ID or name
	if ($is_email_verifed)
	{
		$status = "verified-email";
		// Log email verified
		$logger = new DD_Fraud_Logger();
		$logger->log('Order Verified', "Order #{$order_id} verified due to verified email: {$email}");
	}
 	else if ($is_blocked)
	{
		$order->update_status('blocked');
		dd_record_block_details($order, 'auto', 'Blocked by fraud prevention system based on customer data');
		$status = "blocked";
		// Log order blocked
		$logger = new DD_Fraud_Logger();
		$logger->log('Order Blocked', "Order #{$order_id} blocked by fraud prevention system based on customer data");
	}
	else if ($review_required && !$is_verified)
	{
		update_post_meta($order_id, '_review_required', 1 );
		$status = "review_required";
		// Log review required
		$logger = new DD_Fraud_Logger();
		$logger->log('Order Review', "Order #{$order_id} flagged for review");
	}
	else if ($is_verified) {
		$status = "verified";
		// Log order verified
		$logger = new DD_Fraud_Logger();
		$logger->log('Order Verified', "Order #{$order_id} verified");
	}
	else {
		$status = "processing";
		// Log order processing
		$logger = new DD_Fraud_Logger();
		$logger->log('Order Processing', "Order #{$order_id} proceeding to automatic scan");
	}

	if (!empty($order_check_arr))
	{
		update_post_meta($order_id, '_fraud_check', json_encode($order_check_arr) );
	}

	$order->save();

	// if email or order is not verified and found a blocked email or Starmaker id,
	// then flag other emails/Starmaker ids used in other orders
	if ($is_email_verified || $is_verified)
	{
		return $status;
	}
	else {
    if (isset($starmaker_id_row['flag']) && $starmaker_id_row['flag'] === "blocked") 
		{
			dd_flag_emails($order);
		}
		
    if (isset($email_row['flag']) && $email_row['flag'] === "blocked") {
			dd_flag_starmaker_ids($order);
		}
	}
	
	return $status;
}

// scan previous orders based on Starmaker ID for unique addresses and emails
function dd_run_automatic_scan($current_order, $status) 
{
	$review_required = false;
	
	$starmaker_id = $current_order->get_meta('_billing_starmaker_id');
	$fraud_threshold = get_option('dd_fraud_match_threshold') ?: 70;
	$limit = get_option('dd_fraud_order_limit') ?: 100;


	$args = [
		'starmaker_id' => $starmaker_id,
		'limit' => $limit,
	];

	$orders = wc_get_orders($args);

	$name_arr = [];
	$address_arr = [];
	$email_arr = [];

	foreach($orders as $order) {
		$id = $order->get_id();
		$status_arr[$id] = $order->get_status();
		$email_arr[$id] = strtolower($order->get_billing_email());
		$name_arr[$id] = strtolower($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
		$address_arr[$id] = strtolower($order->get_billing_address_1() . ' ' . $order->get_billing_address_2() . ' ' . $order->get_billing_city() . ' ' . $order->get_billing_state() . ' ' . $order->get_billing_country());
	}

	$unique_emails = array_unique($email_arr);
	$unique_names = array_unique($name_arr);
	$unique_addresses = array_unique($address_arr);

	$arr_to_check = ['emails' => $unique_emails, 'names' => $unique_names, 'addresses' => $unique_addresses];
	$notes = [
		'emails' => [], 
		'names' => [], 
		'addresses' => []
	];

	foreach($arr_to_check as $key => $arr) {
		$values = array_values($arr);
		$order_ids = array_keys($arr); 
		$count = count($arr);
		for ($i = 0; $i < $count - 1; $i++) {
			$percents = [];
			for ($j = $i + 1; $j < $count; $j++) {
				$sim = similar_text($values[$i],$values[$j],$percent);
				$percents[] = $percent;
				
				if ($percent <= $fraud_threshold) {
					$notes[$key][$order_ids[$i]] = $values[$i];
					$notes[$key][$order_ids[$j]] = $values[$j];
					$review_required = true;
					break;
				}
			}
		}
	}

	if (!empty($review_required) && $status != "verified") {
		update_post_meta($current_order->get_id(), '_review_required', 1 );
	}

	$notes['status_count'] = array_count_values($status_arr);
	$notes['status_count']['total'] = count($orders);
	update_post_meta($current_order->get_id(), '_auto_fraud_check', json_encode($notes) );
}

function dd_flag_starmaker_ids($current_order) 
{
	$current_email = $current_order->get_billing_email();
	$current_starmaker_id = $current_order->get_meta('_billing_starmaker_id');
	$limit = get_option('dd_fraud_order_limit') ?: 100;

	$args = [
		'billing_email' => $current_email,
		'limit' => $limit
	];

	$orders = wc_get_orders($args);
	$starmaker_id_arr = [];

	if (count($orders)) {
		foreach($orders as $order) {
			$starmaker_id_arr[$order->get_id()] = $order->get_meta('_billing_starmaker_id');
		}
	
		$unique_starmaker_ids = array_unique($starmaker_id_arr);
	
		if (count($unique_starmaker_ids)) {
			update_post_meta($current_order->get_id(), '_flagged_starmaker_ids', implode(', ', $unique_starmaker_ids) );
		}

		foreach($unique_starmaker_ids as $order_id => $starmaker_id)
		{
			global $wpdb;

			$table = $wpdb->prefix . "dd_fraud_starmaker_id";

			// if Starmaker id is already verified or blocked in the db, then don't need to add it to the database
			$fetch_sql = $wpdb->prepare( "SELECT * FROM $table WHERE starmaker_id = %d", $starmaker_id );
			$existing_starmaker_id = $wpdb->get_row($fetch_sql, ARRAY_A);
			
			if ($existing_starmaker_id)
			{
				if ($existing_starmaker_id['flag'] === "blocked" || $existing_starmaker_id['flag'] === "verified") {
					continue;
				}
			}

			$date = date('Y-m-d h:i:s');
			$flag = "blocked";
			$notes = "Automatically blocked - used with blocked email: " . $current_email . " for Order #" . $order_id . ". Triggered by Order #" . $current_order->get_id();
			
			$sql = $wpdb->prepare( "INSERT INTO $table (starmaker_id, flag, notes, created_at) VALUES (%s, %s, %s, %s) ON DUPLICATE KEY UPDATE flag = %s, notes = %s", [$starmaker_id, $flag, $notes, $date, $flag, $notes]);
		
			$wpdb->get_results($sql);
		}
	}
}

function dd_flag_emails($current_order) 
{
	$current_starmaker_id = $current_order->get_meta('_billing_starmaker_id');
	$limit = get_option('dd_fraud_order_limit') ?: 100;

	$args = [
		'starmaker_id' => $current_starmaker_id,
		'limit' => $limit,
	];

	$orders = wc_get_orders($args);
	$email_arr = [];

	if (count($orders)) {
		foreach($orders as $order) {
			$email_arr[$order->get_id()] = $order->get_billing_email();
		}
	
		$unique_emails = array_unique($email_arr);
	
		if (count($unique_emails)) {
			update_post_meta($current_order->get_id(), '_flagged_emails', implode(', ', $unique_emails) );
		}

		foreach($unique_emails as $order_id => $email)
		{
			global $wpdb;

			$table = $wpdb->prefix . "dd_fraud_email";

			// if email is already blocked or verified in the db, then don't need to add it to the database
			$fetch_sql = $wpdb->prepare( "SELECT * FROM $table WHERE email = %s", $email );
			$existing_email = $wpdb->get_row($fetch_sql, ARRAY_A);
			
			if ($existing_email)
			{
				if ($existing_email['flag'] === "blocked" || $existing_email['flag'] === "verified") {
					continue;
				}
			}

			$date = date('Y-m-d h:i:s');
			$flag = "blocked";
			$notes = "Automatically blocked - used with blocked Starmaker ID: " . $order_id . " for Order #" . $order_id . ". Triggered by Order #" . $current_order->get_id();
			
			$sql = $wpdb->prepare( "INSERT INTO $table (email, flag, notes, created_at) VALUES (%s, %s, %s, %s) ON DUPLICATE KEY UPDATE flag = %s, notes = %s", [$email, $flag, $notes, $date, $flag, $notes]);
		
			$wpdb->get_results($sql);
		}
	}
}

// See if order has the order meta "review_required", if it is then update the status
add_action( 'woocommerce_order_status_processing', 'dd_update_status', 1000, 1);

function dd_update_status($order_id)
{
	if ( ! $order_id ) {
		return;
	}

	$order = wc_get_order( $order_id );
	$review_required = $order->get_meta('_review_required', true);

	if ($review_required)
	{
		$order->update_status('review-required');
	}
}

function dd_add_custom_box() 	
{
	add_meta_box(
			'dd_fraud_details',      // Unique ID
			'Fraud Check Details',   // Box title
			'dd_fraud_details_html', // Content callback, must be of type callable
			'shop_order'             // Post type
	);
}

add_action( 'add_meta_boxes', 'dd_add_custom_box' );

function dd_fraud_details_html($post) {
	$order = wc_get_order($post->ID);

    // Get order details
	$starmaker_id = $order->get_meta('_billing_starmaker_id');
	$email = $order->get_billing_email();
	$first_name = $order->get_billing_first_name();
	$last_name = $order->get_billing_last_name();
    $ip_address = $order->get_meta('_customer_ip');
    $customer_name = $first_name . " " . $last_name;

    // Get fraud check data
	$fraud_check_string = $order->get_meta('_fraud_check');
    $fraud_check_arr = is_serialized($fraud_check_string) ? 
        unserialize($fraud_check_string) : 
        json_decode(stripslashes($fraud_check_string), true);

	$auto_fraud_check_string = $order->get_meta('_auto_fraud_check');
    $auto_fraud_check_arr = is_serialized($auto_fraud_check_string) ? 
        unserialize($auto_fraud_check_string) : 
        json_decode(stripslashes($auto_fraud_check_string), true);

    // Determine status and icon
    $status = 'Processing';
    $status_class = '';
    $status_icon = '';
    $status_description = '';
    
    if ($order->get_status() === "blocked") {
        $status = "Blocked";
        $status_class = "blocked";
        $status_icon = "🚫";
        $status_description = "This order has been blocked due to suspected fraudulent activity.";
    } else if ($order->get_status() === "review-required") {
        $status = "Held for Review";
        $status_class = "review";
        $status_icon = "⚠️";
        $status_description = "This order requires manual review due to suspicious activity.";
    } else if ($order->get_status() === "verified") {
        $status = "Verified";
        $status_class = "verified";
        $status_icon = "✅";
        $status_description = "This order has passed all fraud checks.";
    }

    // Get discrepancies from previous orders
    $discrepancies = array();
    if (!empty($auto_fraud_check_arr)) {
        if (!empty($auto_fraud_check_arr['emails'])) {
            $discrepancies['emails'] = array_unique($auto_fraud_check_arr['emails']);
        }
        if (!empty($auto_fraud_check_arr['names'])) {
            $discrepancies['names'] = array_unique($auto_fraud_check_arr['names']);
        }
        if (!empty($auto_fraud_check_arr['addresses'])) {
            $discrepancies['addresses'] = array_unique($auto_fraud_check_arr['addresses']);
        }
    }

    // Get flagged items
    $flagged_starmaker_ids = $order->get_meta('_flagged_starmaker_ids');
    $flagged_emails = $order->get_meta('_flagged_emails');
    $flagged_ips = $order->get_meta('_flagged_ips');

    // Get current order status for action buttons
    $current_status = $order->get_status();
    ?>

    <div class="fraud-details-container">
        <!-- Status Banner -->
        <div class="fraud-status-banner <?php echo esc_attr($status_class); ?>">
            <div class="fraud-status-icon"><?php echo $status_icon; ?></div>
            <div class="fraud-status-content">
                <h2><?php echo esc_html($status); ?></h2>
                <p><?php echo esc_html($status_description); ?></p>
		</div>
        </div>

        <!-- Quick Actions -->
        <div class="fraud-quick-actions">
            <?php if ($current_status !== 'blocked'): ?>
                <button type="button" class="fraud-quick-action-button block" data-order-id="<?php echo $order->get_id(); ?>">
                    <span class="dashicons dashicons-shield"></span>
                    Block Customer
                    <span class="fraud-tooltip">
                        Block this customer from placing future orders. 
                        This will affect all orders using the same email, Starmaker ID, or IP address.
                        <br><kbd>Alt</kbd> + <kbd>B</kbd>
                    </span>
                </button>
					<?php else: ?>
                <button type="button" class="fraud-quick-action-button verify" data-order-id="<?php echo $order->get_id(); ?>">
                    <span class="dashicons dashicons-yes"></span>
                    Unblock Customer
                    <span class="fraud-tooltip">
                        Remove blocking restrictions from this customer. 
                        This will allow future orders from this customer.
                        <br><kbd>Alt</kbd> + <kbd>U</kbd>
                    </span>
                </button>
					<?php endif; ?>
            
            <?php if ($current_status === 'review-required'): ?>
                <button type="button" class="fraud-quick-action-button verify" data-order-id="<?php echo $order->get_id(); ?>">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Verify Customer
                    <span class="fraud-tooltip">
                        Mark this customer as verified. 
                        Future orders will be processed automatically.
                        <br><kbd>Alt</kbd> + <kbd>V</kbd>
                    </span>
                </button>
            <?php endif; ?>
        </div>

        <!-- Fraud Check Details -->
        <div class="fraud-details-grid">
            <!-- Starmaker ID -->
            <div class="fraud-detail-card">
                <h4>StarMaker ID</h4>
                <div class="fraud-detail-value"><?php echo esc_html($starmaker_id); ?></div>
                <?php if (!empty($fraud_check_arr['starmaker_id'])): ?>
                    <div class="fraud-status-badge <?php echo esc_attr($fraud_check_arr['starmaker_id']['flag']); ?>">
                        <?php echo esc_html(ucfirst($fraud_check_arr['starmaker_id']['flag'])); ?>
                    </div>
				<?php if (!empty($fraud_check_arr['starmaker_id']['notes'])): ?>
                        <div class="fraud-notes"><?php echo wp_kses_post($fraud_check_arr['starmaker_id']['notes']); ?></div>
                    <?php endif; ?>
				<?php endif; ?>
			</div>

            <!-- Email -->
            <div class="fraud-detail-card">
                <h4>Email</h4>
                <div class="fraud-detail-value"><?php echo esc_html($email); ?></div>
				<?php if (!empty($fraud_check_arr['email'])): ?>
                    <div class="fraud-status-badge <?php echo esc_attr($fraud_check_arr['email']['flag']); ?>">
                        <?php echo esc_html(ucfirst($fraud_check_arr['email']['flag'])); ?>
                    </div>
				<?php if (!empty($fraud_check_arr['email']['notes'])): ?>
                        <div class="fraud-notes"><?php echo wp_kses_post($fraud_check_arr['email']['notes']); ?></div>
                    <?php endif; ?>
				<?php endif; ?>
			</div>

            <!-- Customer Name -->
            <div class="fraud-detail-card">
                <h4>Customer Name</h4>
                <div class="fraud-detail-value"><?php echo esc_html($customer_name); ?></div>
                <?php if (!empty($fraud_check_arr['customer_name'])): ?>
                    <div class="fraud-status-badge <?php echo esc_attr($fraud_check_arr['customer_name']['flag']); ?>">
                        <?php echo esc_html(ucfirst($fraud_check_arr['customer_name']['flag'])); ?>
                    </div>
                    <?php if (!empty($fraud_check_arr['customer_name']['notes'])): ?>
                        <div class="fraud-notes"><?php echo wp_kses_post($fraud_check_arr['customer_name']['notes']); ?></div>
				<?php endif; ?>
				<?php endif; ?>
			</div>

            <!-- IP Address -->
            <div class="fraud-detail-card">
                <h4>IP Address</h4>
                <div class="fraud-detail-value"><?php echo esc_html($ip_address); ?></div>
                <?php if (!empty($fraud_check_arr['ip_address'])): ?>
                    <div class="fraud-status-badge <?php echo esc_attr($fraud_check_arr['ip_address']['flag']); ?>">
                        <?php echo esc_html(ucfirst($fraud_check_arr['ip_address']['flag'])); ?>
		</div>
                    <?php if (!empty($fraud_check_arr['ip_address']['notes'])): ?>
                        <div class="fraud-notes"><?php echo wp_kses_post($fraud_check_arr['ip_address']['notes']); ?></div>
		<?php endif; ?>
		<?php endif; ?>
            </div>

            <!-- Trigger Type - Consolidated -->
            <div class="fraud-detail-card">
                <h4>Trigger Type</h4>
                <div class="fraud-detail-value">
                    <?php
                    $trigger_types = array();
                    
                    // Check for trigger types in fraud check data
                    if (!empty($fraud_check_arr)) {
                        foreach ($fraud_check_arr as $key => $data) {
                            if (isset($data['trigger_type'])) {
                                $trigger_types[$key] = $data['trigger_type'];
                            } else {
                                // Default to manual if not specified
                                $trigger_types[$key] = 'manual';
                            }
                        }
                    }
                    
                    // If no trigger types found in fraud check, check individual flags
                    if (empty($trigger_types)) {
                        if (!empty($fraud_check_arr['starmaker_id'])) {
                            $trigger_types['StarMaker ID'] = isset($fraud_check_arr['starmaker_id']['trigger_type']) ? 
                                $fraud_check_arr['starmaker_id']['trigger_type'] : 'manual';
                        }
                        if (!empty($fraud_check_arr['email'])) {
                            $trigger_types['Email'] = isset($fraud_check_arr['email']['trigger_type']) ? 
                                $fraud_check_arr['email']['trigger_type'] : 'manual';
                        }
                        if (!empty($fraud_check_arr['customer_name'])) {
                            $trigger_types['Customer Name'] = isset($fraud_check_arr['customer_name']['trigger_type']) ? 
                                $fraud_check_arr['customer_name']['trigger_type'] : 'manual';
                        }
                        if (!empty($fraud_check_arr['ip_address'])) {
                            $trigger_types['IP Address'] = isset($fraud_check_arr['ip_address']['trigger_type']) ? 
                                $fraud_check_arr['ip_address']['trigger_type'] : 'manual';
                        }
                    }

                    if (!empty($trigger_types)) {
                        echo '<div class="trigger-type-details">';
                        foreach ($trigger_types as $type => $trigger) {
                            $class = $trigger === 'automatic' ? 'auto-trigger' : 'manual-trigger';
                            echo '<div class="trigger-type-item">';
                            echo '<span class="trigger-type-label">' . esc_html($type) . ':</span> ';
                            echo '<span class="' . esc_attr($class) . '">' . esc_html(ucfirst($trigger)) . '</span>';
                            echo '</div>';
                        }
                        echo '</div>';
                    } else {
                        // If still no trigger types found, show default values
                        echo '<div class="trigger-type-details">';
                        echo '<div class="trigger-type-item">';
                        echo '<span class="trigger-type-label">StarMaker ID:</span> ';
                        echo '<span class="manual-trigger">Manual</span>';
                        echo '</div>';
                        echo '<div class="trigger-type-item">';
                        echo '<span class="trigger-type-label">Email:</span> ';
                        echo '<span class="manual-trigger">Manual</span>';
                        echo '</div>';
                        echo '<div class="trigger-type-item">';
                        echo '<span class="trigger-type-label">Customer Name:</span> ';
                        echo '<span class="manual-trigger">Manual</span>';
                        echo '</div>';
                        echo '<div class="trigger-type-item">';
                        echo '<span class="trigger-type-label">IP Address:</span> ';
                        echo '<span class="manual-trigger">Manual</span>';
                        echo '</div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>

    </div>
    <div class="">
            <!-- Discrepancies Section -->
            <?php if (!empty($discrepancies)): ?>
        <div class="fraud_section">
            <h3>Discrepancies Found in Last <?php echo esc_html(get_option('dd_fraud_order_limit', '100')); ?> Orders</h3>
            <div class="discrepancies_grid">
                <?php if (!empty($discrepancies['emails'])): ?>
                <div class="discrepancy_card">
                    <h4>Different Emails Used</h4>
                    <ul>
                        <?php foreach($discrepancies['emails'] as $email): ?>
                            <li><?php echo esc_html($email); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
                <?php endif; ?>

                <?php if (!empty($discrepancies['names'])): ?>
                <div class="discrepancy_card">
                    <h4>Different Names Used</h4>
                    <ul>
                        <?php foreach($discrepancies['names'] as $name): ?>
                            <li><?php echo esc_html($name); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
                <?php endif; ?>

                <?php if (!empty($discrepancies['addresses'])): ?>
                <div class="discrepancy_card">
                    <h4>Different Addresses Used</h4>
                    <ul>
                        <?php foreach($discrepancies['addresses'] as $address): ?>
                            <li><?php echo esc_html($address); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
            </div>
        </div>
			<?php endif; ?>
      <br>
      
      <!-- StarMaker Profile Information -->
      <?php 
      // Get the current StarMaker ID from the order
      $starmaker_id = get_post_meta($post->ID, '_billing_starmaker_id', true);
      
      if (!empty($starmaker_id)) {
          // Fetch StarMaker user info
          $starmaker_user_info = sc_fetch_starmaker_user_info($starmaker_id);
          
          if (!empty($starmaker_user_info) && !is_wp_error($starmaker_user_info)) {
      ?>
      <div class="starmaker-profile-section">
          <h3>StarMaker Profile</h3>
          <div class="starmaker-profile-container">
              <?php if (!empty($starmaker_user_info['avatar'])): ?>
                  <div class="starmaker-avatar">
                      <img src="<?php echo esc_url($starmaker_user_info['avatar']); ?>" alt="<?php esc_attr_e('StarMaker Avatar', 'sc-fraud-prevention'); ?>" style="max-width: 100px; border-radius: 50%;">
                  </div>
		<?php endif; ?>
              
              <div class="starmaker-details">
                  <p><strong>StarMaker ID:</strong> <?php echo esc_html($starmaker_id); ?></p>
                  <?php if (!empty($starmaker_user_info['name'])): ?>
                      <p><strong>Username:</strong> <?php echo esc_html($starmaker_user_info['name']); ?></p>
                  <?php endif; ?>
              </div>
          </div>
      </div>
      <style>
          .starmaker-profile-section {
              margin: 20px 0;
              padding: 15px;
              background: #f8f8f8;
              border-radius: 5px;
          }
          .starmaker-profile-container {
              display: flex;
              align-items: center;
              gap: 20px;
          }
          .starmaker-avatar {
              flex-shrink: 0;
          }
          .starmaker-details {
              flex-grow: 1;
          }
      </style>
      <?php 
          }
      }
      ?>
      
      <!-- Flagged Items -->
      <?php if (!empty($flagged_starmaker_ids) || !empty($flagged_emails) || !empty($flagged_ips)): ?>
      <div class="fraud_section">
          <h3>Flagged Items</h3>
          <div class="flagged_items_table_container">
              <table class="flagged_items_table">
                  <thead>
                      <tr>
                          <th>Type</th>
                          <th>Current Value</th>
                          <th>Previous Values</th>
                          <th>Issue</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php if (!empty($flagged_starmaker_ids)): 
                          $starmaker_ids = explode(', ', $flagged_starmaker_ids);
                          $current_starmaker_id = $starmaker_ids[0];
                          $previous_starmaker_ids = array_slice($starmaker_ids, 1);
                      ?>
                      <tr>
                          <td>Starmaker ID</td>
                          <td><?php echo esc_html($current_starmaker_id); ?></td>
                          <td>
                              <?php if (!empty($previous_starmaker_ids)): ?>
                                  <ul class="previous-values-list">
                                      <?php foreach ($previous_starmaker_ids as $previous_id): ?>
                                          <li><?php echo esc_html($previous_id); ?></li>
                                      <?php endforeach; ?>
                                  </ul>
                              <?php else: ?>
                                  <span class="no-previous-values">None</span>
                              <?php endif; ?>
                          </td>
                          <td>Multiple Starmaker IDs used across orders</td>
                      </tr>
                      <?php endif; ?>
                      
                      <?php if (!empty($flagged_emails)): 
                          $emails = explode(', ', $flagged_emails);
                          $current_email = $emails[0];
                          $previous_emails = array_slice($emails, 1);
                      ?>
                      <tr>
                          <td>Email</td>
                          <td><?php echo esc_html($current_email); ?></td>
                          <td>
                              <?php if (!empty($previous_emails)): ?>
                                  <ul class="previous-values-list">
                                      <?php foreach ($previous_emails as $previous_email): ?>
                                          <li><?php echo esc_html($previous_email); ?></li>
                                      <?php endforeach; ?>
                                  </ul>
                              <?php else: ?>
                                  <span class="no-previous-values">None</span>
                              <?php endif; ?>
                          </td>
                          <td>Multiple emails used across orders</td>
                      </tr>
                      <?php endif; ?>

                      <?php if (!empty($flagged_ips)): 
                          $ips = explode(', ', $flagged_ips);
                          $current_ip = $ips[0];
                          $previous_ips = array_slice($ips, 1);
                      ?>
                      <tr>
                          <td>IP Address</td>
                          <td><?php echo esc_html($current_ip); ?></td>
                          <td>
                              <?php if (!empty($previous_ips)): ?>
                                  <ul class="previous-values-list">
                                      <?php foreach ($previous_ips as $previous_ip): ?>
                                          <li><?php echo esc_html($previous_ip); ?></li>
                                      <?php endforeach; ?>
                                  </ul>
                              <?php else: ?>
                                  <span class="no-previous-values">None</span>
                              <?php endif; ?>
                          </td>
                          <td>Multiple IP addresses used across orders</td>
                      </tr>
                      <?php endif; ?>
                  </tbody>
              </table>
          </div>
      </div>
      <?php endif; ?>
    </div>

    <style>
  
    </style>
    <script>
    jQuery(document).ready(function($) {
        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Only process if no input/textarea is focused
            if ($('input:focus, textarea:focus').length) {
                return;
            }
            
            // Alt + B: Block Customer
            if (e.altKey && e.key.toLowerCase() === 'b') {
                $('.fraud-quick-action-button.block').click();
            }
            // Alt + U: Unblock Customer
            if (e.altKey && e.key.toLowerCase() === 'u') {
                $('.fraud-quick-action-button.verify:contains("Unblock")').click();
            }
            // Alt + V: Verify Customer
            if (e.altKey && e.key.toLowerCase() === 'v') {
                $('.fraud-quick-action-button.verify:contains("Verify")').click();
            }
        });

        // Block Customer
        $('.fraud-quick-action-button.block').on('click', function() {
            if (!confirm('Are you sure you want to block this customer?\n\nThis will:\n- Prevent future orders\n- Flag associated email and Starmaker ID\n- Block the IP address')) {
                return;
            }
            
            var orderId = $(this).data('order-id');
            $.post(ajaxurl, {
                action: 'dd_block_customer',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('dd_fraud_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error blocking customer: ' + response.data);
                }
            });
        });

        // Verify/Unblock Customer
        $('.fraud-quick-action-button.verify').on('click', function() {
            var isUnblock = $(this).text().trim().startsWith('Unblock');
            var confirmMessage = isUnblock ? 
                'Are you sure you want to unblock this customer?\n\nThis will:\n- Allow future orders\n- Remove blocking flags\n- Enable normal order processing' :
                'Are you sure you want to verify this customer?\n\nThis will:\n- Mark the customer as verified\n- Allow future orders\n- Enable automatic processing';

            if (!confirm(confirmMessage)) {
                return;
            }
            
            var orderId = $(this).data('order-id');
            $.post(ajaxurl, {
                action: isUnblock ? 'dd_unblock_customer' : 'dd_verify_customer',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('dd_fraud_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error ' + (isUnblock ? 'unblocking' : 'verifying') + ' customer: ' + response.data);
                }
            });
        });
    });
    </script>
	<?php
}

add_action( 'admin_post_dd_import', 'dd_import' );
 
function dd_import() 
{
	global $wpdb;

	$file_info = pathinfo($_FILES['upload']['name']);
	
	if ($file_info['extension'] !== 'csv')
	{
		echo "<p>The uploaded file is not a CSV file.</p>";
		exit();
	}
		
	if($_FILES['upload']['name']) 
	{
		if(!$_FILES['upload']['error']) 
		{
			$rows = array_map('str_getcsv', file($_FILES['upload']['tmp_name']));
			$header = array_shift($rows);
			$header = array_map('trim', $header);
			
			$csv = array();
			$error = array();
			
			foreach ($rows as $row) {
				if (count($header) === count($row))
				{
					$csv[] = array_combine($header, $row);
				}
				else
				{
					$error[] = $row[0];
				}
			}
	
			$type = $header[0];
			$accepted_types = ['starmaker_id', 'email', 'customer_name', 'ip_address'];
	
			if (!in_array($type, $accepted_types))
			{
				echo "<p>CSV must contain starmaker_id, email, customer_name or ip as one of its headers.</p>";
				exit();
			}
	
			$table = $wpdb->prefix . 'dd_fraud_' . $type;
	
			foreach ($csv as $data)
			{
				$data = array_map('trim', $data);
				$data['flag'] = strtolower($data['flag']);
	
				if ($data['flag'] === "delete")
				{
					$sql = $wpdb->prepare( "DELETE FROM $table WHERE {$type} = %s", $data[$type]);
				}
				else if ($data['flag'] === "review" || $data['flag'] === "verified" || $data['flag'] === "blocked")
				{
					$date = date('Y-m-d h:i:s');
					$sql = $wpdb->prepare( "INSERT INTO $table ({$type}, flag, notes, created_at) VALUES (%s, %s, %s, %s) ON DUPLICATE KEY UPDATE flag = %s, notes = %s", [$data[$type], $data['flag'], $data['notes'], $date, $data['flag'], $data['notes']]);
				}
	
				$results = $wpdb->get_results($sql);
			}
		}
	}

	echo "<p>Import $type processed</p>";
	echo "<p>Errors for rows: " . implode( ", ", $error) . "</p>";
}

add_action( 'admin_post_dd_export', 'dd_export' );

function dd_export()
{
	global $wpdb;
	if (!current_user_can( "administrator" ))
	{
		header("Location:" . wp_login_url());
		exit();
	}

	if (!isset($_POST['type']))
	{
		exit();
	}

	$type = $_POST['type'];

	header("Content-Type: text/csv; charset=utf-8");
	header("Content-Disposition: attachment; filename=$type.csv");  
	$output = fopen("php://output", "w");  
	fputcsv($output, array($type, "flag", "notes"));
	$table = $wpdb->prefix . "dd_fraud_" . $type;
	$sql = "SELECT $type, flag, notes from $table";
	$rows = $wpdb->get_results($sql, ARRAY_A);

	foreach($rows as $row)
	{
		fputcsv($output, $row);
	}

	fclose($output);

	return $ouput;
}


add_action( 'admin_post_dd_add_entry', 'dd_add_entry' );

function dd_add_entry()
{
	global $wpdb;
	$type = $_POST['type'];
	$table = $wpdb->prefix . 'dd_fraud_' . $type;
	$entry = sanitize_text_field($_POST['entry']);
	$notes = sanitize_textarea_field($_POST['notes']);

	$date = date('Y-m-d h:i:s');
	$current_user = wp_get_current_user();
	$admin_user = $current_user->user_login;

	$trigger_type = 'manual'; // Default to manual

	// Example logic to determine if the block is automatic
	if ($is_automatic) {
		$trigger_type = 'automatic';
	}

	$sql = $wpdb->prepare(
		"INSERT INTO $table ({$type}, flag, notes, created_at, admin_user, trigger_type) VALUES (%s, %s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE flag = %s, notes = %s, admin_user = %s, trigger_type = %s",
		[$entry, $_POST['flag'], $notes, $date, $admin_user, $trigger_type, $_POST['flag'], $notes, $admin_user, $trigger_type]
	);

	$added = $wpdb->get_results($sql);

	// Log the manual entry
	$logger = new DD_Fraud_Logger();
	$logger->log(
		'Manual Entry Added',
		sprintf(
			'Added %s entry: %s with flag: %s. Notes: %s',
			$type,
			$entry,
			$_POST['flag'],
			$notes
		)
	);

	wp_safe_redirect( esc_url_raw( add_query_arg( array( 'added' => $added ), admin_url( 'admin.php?page=dd_fraud_' . $type ) ) ) );
	exit();
}

/**
 * Handle a custom 'starmaker_id' query var to get orders with the 'starmaker_id' meta.
 * @param array $query - Args for WP_Query.
 * @param array $query_vars - Query vars from WC_Order_Query.
 * @return array modified $query
 */
function dd_handle_starmaker_id_query_var( $query, $query_vars ) {
	if ( ! empty( $query_vars['starmaker_id'] ) ) {
		$query['meta_query'][] = array(
			'key' => '_billing_starmaker_id',
			'value' => esc_attr( $query_vars['starmaker_id'] ),
		);
	}

	return $query;
}
add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'dd_handle_starmaker_id_query_var', 10, 2 );

function woocommerce_shop_order_search_starmaker_id( $search_fields ) {

  $search_fields[] = '_billing_starmaker_id';

  return $search_fields;
}
add_filter( 'woocommerce_shop_order_search_fields', 'woocommerce_shop_order_search_starmaker_id' );

// Register settings
function dd_register_settings() {
    // Debug output
    error_log('Registering DD Fraud Prevention settings');
    
    register_setting('dd_fraud_options_group', 'dd_fraud_order_limit');
    register_setting('dd_fraud_options_group', 'dd_fraud_match_threshold');
    register_setting('dd_fraud_options_group', 'dd_fraud_auto_block');
    register_setting('dd_fraud_options_group', 'dd_fraud_vpn_block');
    register_setting('dd_fraud_options_group', 'dd_fraud_past_orders_check');
    
    // Debug output
    error_log('DD Fraud Prevention settings registered');
}
add_action('admin_init', 'dd_register_settings');

// Add AJAX handlers for quick actions
add_action('wp_ajax_dd_block_customer', 'dd_block_customer_ajax');
add_action('wp_ajax_dd_unblock_customer', 'dd_unblock_customer_ajax');
add_action('wp_ajax_dd_verify_customer', 'dd_verify_customer_ajax');

function dd_block_customer_ajax() {
    check_ajax_referer('dd_fraud_nonce', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions');
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error('Invalid order ID');
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    // Block the customer
    $order->update_status('blocked');
    dd_record_block_details($order, 'manual', 'Blocked by administrator');
    
    // Add to blocked list
    global $wpdb;
    $starmaker_id = $order->get_meta('_billing_starmaker_id');
    $email = $order->get_billing_email();
    $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $ip_address = $order->get_meta('_customer_ip');

    // Block Starmaker ID
    if ($starmaker_id) {
        $starmaker_table = $wpdb->prefix . 'dd_fraud_starmaker_id';
        $wpdb->insert($starmaker_table, array(
            'starmaker_id' => $starmaker_id,
            'flag' => 'blocked',
            'notes' => 'Manually blocked from order #' . $order_id,
            'created_at' => current_time('mysql'),
            'admin_user' => $admin_user,
            'trigger_type' => 'manual'
        ));
    }

    // Block Email
    if ($email) {
        $email_table = $wpdb->prefix . 'dd_fraud_email';
        $wpdb->insert($email_table, array(
            'email' => $email,
            'flag' => 'blocked',
            'notes' => 'Manually blocked from order #' . $order_id,
            'created_at' => current_time('mysql'),
            'admin_user' => $admin_user,
            'trigger_type' => 'manual'
        ));
    }

    // Block Name
    if ($name) {
        $name_table = $wpdb->prefix . 'dd_fraud_customer_name';
        $wpdb->insert($name_table, array(
            'customer_name' => $name,
            'flag' => 'blocked',
            'notes' => 'Manually blocked from order #' . $order_id,
            'created_at' => current_time('mysql'),
            'admin_user' => $admin_user,
            'trigger_type' => 'manual'
        ));
    }

    // Block IP
    if ($ip_address && $ip_address !== 'UNKNOWN') {
        $ip_table = $wpdb->prefix . 'dd_fraud_ip';
        $wpdb->insert($ip_table, array(
            'ip_address' => $ip_address,
            'flag' => 'blocked',
            'notes' => 'Manually blocked from order #' . $order_id,
            'created_at' => current_time('mysql'),
            'admin_user' => $admin_user,
            'trigger_type' => 'manual'
        ));
    }

    wp_send_json_success('Customer blocked successfully');
}

function dd_unblock_customer_ajax() {
    check_ajax_referer('dd_fraud_nonce', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions');
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error('Invalid order ID');
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    // Unblock the customer
    $order->update_status('processing');
    
    // Remove from blocked list
    global $wpdb;
    $starmaker_id = $order->get_meta('_billing_starmaker_id');
    $email = $order->get_billing_email();
    $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $ip_address = $order->get_meta('_customer_ip');

    // Unblock Starmaker ID
    if ($starmaker_id) {
        $starmaker_table = $wpdb->prefix . 'dd_fraud_starmaker_id';
        $wpdb->delete($starmaker_table, array('starmaker_id' => $starmaker_id));
    }

    // Unblock Email
    if ($email) {
        $email_table = $wpdb->prefix . 'dd_fraud_email';
        $wpdb->delete($email_table, array('email' => $email));
    }

    // Unblock Name
    if ($name) {
        $name_table = $wpdb->prefix . 'dd_fraud_customer_name';
        $wpdb->delete($name_table, array('customer_name' => $name));
    }

    // Unblock IP
    if ($ip_address && $ip_address !== 'UNKNOWN') {
        $ip_table = $wpdb->prefix . 'dd_fraud_ip';
        $wpdb->delete($ip_table, array('ip_address' => $ip_address));
    }

    wp_send_json_success('Customer unblocked successfully');
}

function dd_verify_customer_ajax() {
    check_ajax_referer('dd_fraud_nonce', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Insufficient permissions');
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error('Invalid order ID');
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    // Verify the customer
    $order->update_status('processing');
    
    // Add to verified list
    global $wpdb;
    $starmaker_id = $order->get_meta('_billing_starmaker_id');
    $email = $order->get_billing_email();
    $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $ip_address = $order->get_meta('_customer_ip');

    // Verify Starmaker ID
    if ($starmaker_id) {
        $starmaker_table = $wpdb->prefix . 'dd_fraud_starmaker_id';
        $wpdb->insert($starmaker_table, array(
            'starmaker_id' => $starmaker_id,
            'flag' => 'verified',
            'notes' => 'Manually verified from order #' . $order_id,
            'created_at' => current_time('mysql'),
            'admin_user' => $admin_user,
            'trigger_type' => 'manual'
        ));
    }

    // Verify Email
    if ($email) {
        $email_table = $wpdb->prefix . 'dd_fraud_email';
        $wpdb->insert($email_table, array(
            'email' => $email,
            'flag' => 'verified',
            'notes' => 'Manually verified from order #' . $order_id,
            'created_at' => current_time('mysql'),
            'admin_user' => $admin_user,
            'trigger_type' => 'manual'
        ));
    }

    // Verify Name
    if ($name) {
        $name_table = $wpdb->prefix . 'dd_fraud_customer_name';
        $wpdb->insert($name_table, array(
            'customer_name' => $name,
            'flag' => 'verified',
            'notes' => 'Manually verified from order #' . $order_id,
            'created_at' => current_time('mysql'),
            'admin_user' => $admin_user,
            'trigger_type' => 'manual'
        ));
    }

    // Verify IP
    if ($ip_address && $ip_address !== 'UNKNOWN') {
        $ip_table = $wpdb->prefix . 'dd_fraud_ip';
        $wpdb->insert($ip_table, array(
            'ip_address' => $ip_address,
            'flag' => 'verified',
            'notes' => 'Manually verified from order #' . $order_id,
            'created_at' => current_time('mysql'),
            'admin_user' => $admin_user,
            'trigger_type' => 'manual'
        ));
    }

    wp_send_json_success('Customer verified successfully');
}

/**
 * Add auto-refund settings to the plugin
 */
function dd_add_auto_refund_settings() {
    register_setting('dd_fraud_settings', 'dd_auto_refund_enabled');
    register_setting('dd_fraud_settings', 'dd_auto_refund_reason');
    
    add_settings_field(
        'dd_auto_refund_enabled',
        'Enable Auto-Refund',
        'dd_auto_refund_enabled_callback',
        'dd_fraud_settings',
        'dd_fraud_general_section'
    );
    
    add_settings_field(
        'dd_auto_refund_reason',
        'Refund Reason',
        'dd_auto_refund_reason_callback',
        'dd_fraud_settings',
        'dd_fraud_general_section'
    );
}
add_action('admin_init', 'dd_add_auto_refund_settings');

function dd_auto_refund_enabled_callback() {
    $enabled = get_option('dd_auto_refund_enabled', '0');
    echo '<input type="checkbox" name="dd_auto_refund_enabled" value="1" ' . checked(1, $enabled, false) . ' />';
    echo '<p class="description">Automatically refund orders that are blocked by the fraud prevention system.</p>';
}

function dd_auto_refund_reason_callback() {
    $reason = get_option('dd_auto_refund_reason', 'Order blocked by fraud prevention system');
    echo '<input type="text" name="dd_auto_refund_reason" value="' . esc_attr($reason) . '" class="regular-text" />';
    echo '<p class="description">The reason that will be shown to customers for the refund.</p>';
}

/**
 * Handle auto-refund for blocked orders
 */
function dd_handle_auto_refund($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Only process if order is blocked
    if ($order->get_status() !== 'blocked') {
        return;
    }

    // Check if auto-refund is enabled
    if (get_option('dd_auto_refund_enabled', '0') !== '1') {
        return;
    }

    // Check if order was already refunded
    if ($order->get_meta('_auto_refunded', true)) {
        return;
    }

    try {
        // Get the refund reason from settings
        $refund_reason = get_option('dd_auto_refund_reason', 'Order blocked by fraud prevention system');
        
        // Process the refund
        $refund = wc_create_refund(array(
            'order_id' => $order_id,
            'amount' => $order->get_total(),
            'reason' => $refund_reason,
            'refunded_by' => get_current_user_id(),
            'refund_payment' => true,
            'restock_items' => true,
        ));

        if (is_wp_error($refund)) {
            throw new Exception($refund->get_error_message());
        }

        // Mark order as auto-refunded
        $order->update_meta_data('_auto_refunded', true);
        $order->save();

        // Add note to order
        $order->add_order_note(sprintf(
            'Order automatically refunded due to fraud prevention. Refund ID: %s',
            $refund->get_id()
        ));

    } catch (Exception $e) {
        // Log the error
        error_log('DD Fraud Prevention - Auto-refund failed for order ' . $order_id . ': ' . $e->getMessage());
        
        // Add note to order about failed refund
        $order->add_order_note('Auto-refund failed: ' . $e->getMessage());
    }
}

// Hook into order status changes to trigger auto-refund
add_action('woocommerce_order_status_changed', 'dd_handle_auto_refund', 10, 3);

// Add auto-refund status to order actions
add_filter('woocommerce_order_actions', 'dd_add_auto_refund_order_action');
function dd_add_auto_refund_order_action($actions) {
    $actions['dd_manual_auto_refund'] = array(
        'url'    => wp_nonce_url(admin_url('admin-post.php?action=dd_manual_auto_refund&order_id=' . get_the_ID()), 'dd-manual-auto-refund'),
        'name'   => __('Process Auto-Refund', 'dd-fraud-prevention'),
        'action' => 'dd-manual-auto-refund'
    );
    return $actions;
}

// Handle manual auto-refund action
add_action('admin_post_dd_manual_auto_refund', 'dd_process_manual_auto_refund');
function dd_process_manual_auto_refund() {
    if (!current_user_can('edit_shop_orders')) {
        wp_die(__('You do not have permission to do this.', 'dd-fraud-prevention'));
    }

    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
    if (!$order_id) {
        wp_die(__('No order ID provided.', 'dd-fraud-prevention'));
    }

    check_admin_referer('dd-manual-auto-refund');

    // Check if auto-refund is enabled
    if (get_option('dd_auto_refund_enabled', '0') !== '1') {
        wp_die(__('Auto-refund is currently disabled. Please enable it in the settings first.', 'dd-fraud-prevention'));
    }

    dd_handle_auto_refund($order_id);

    wp_redirect(wp_get_referer() ?: admin_url());
    exit;
}

// Function to record block details
function dd_record_block_details($order, $block_type, $reason) {
    // Record block details in order meta
    $order->update_meta_data('_dd_block_type', $block_type); // 'auto' or 'manual'
    $order->update_meta_data('_dd_block_reason', $reason);
    $order->update_meta_data('_dd_block_date', current_time('mysql'));
    $order->update_meta_data('_dd_blocked_by', $block_type === 'manual' ? get_current_user_id() : 'system');
    
    // Add a note to the order
    $note = sprintf(
        'Order blocked: %s. Reason: %s',
        $block_type === 'auto' ? 'Automatically by the fraud prevention system' : 'Manually by an administrator',
        $reason
    );
    $order->add_order_note($note);
    
    $order->save();
}

/**
 * Fetch StarMaker user information from the API
 * 
 * @param string $starmaker_id The StarMaker ID to fetch information for
 * @return array|WP_Error User information array or WP_Error on failure
 */
function sc_fetch_starmaker_user_info($starmaker_id) {
    // Static API credentials
    $app_key = 'hashtag-7i36xt0t';
    $app_secret = '8a0c3250725d09be379ce8ed901c5cd7';
    $agent_uid = '12666376951992244';
    
    // Use sandbox URL for testing
    $api_base_url = 'https://pay-test.starmakerstudios.com';
    
    // Prepare request parameters
    $timestamp = time();
    $request_path = '/api/v3/external/agent/user';
    $request_query = '?sids=' . urlencode($starmaker_id);
    $path_with_query = $request_path . $request_query;
    $request_body = '';
    
    // Generate signature
    $message = $path_with_query . ':' . $timestamp . ':' . $request_body;
    $signature = hash_hmac('sha256', $message, $app_secret);
    
    // Prepare request URL
    $api_url = $api_base_url . $path_with_query;
    
    // Set up request arguments
    $args = array(
        'headers' => array(
            'x-app-key' => $app_key,
            'x-request-id' => uniqid(),
            'x-request-timestamp' => $timestamp,
            'x-request-sign' => $signature,
            'x-agent-uid' => $agent_uid,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 15
    );
    
    // Log the request for debugging
    error_log('StarMaker API Request: ' . $api_url);
    error_log('StarMaker API Headers: ' . print_r($args['headers'], true));
    
    // Make the API request
    $response = wp_remote_get($api_url, $args);
    
    if (is_wp_error($response)) {
        error_log('StarMaker API Error: ' . $response->get_error_message());
        return $response;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    // Log the response for debugging
    error_log('StarMaker API Response: ' . $body);
    
    if (empty($data) || !isset($data['code']) || $data['code'] !== 0) {
        $error_message = isset($data['msg']) ? $data['msg'] : 'Failed to fetch StarMaker user information';
        error_log('StarMaker API Error: ' . $error_message);
        return new WP_Error('api_error', $error_message);
    }
    
    // Return the first user's information
    return isset($data['data'][0]) ? $data['data'][0] : new WP_Error('no_data', 'No user information found');
}

/**
 * Store StarMaker user information in order meta
 * 
 * @param int $order_id The order ID
 * @param array $user_info The user information array
 */
function sc_store_starmaker_user_info($order_id, $user_info) {
    // Store the entire user info array as a single meta value
    update_post_meta($order_id, '_starmaker_user_info', $user_info);
    
    // Also store individual fields for backward compatibility
    if (!empty($user_info['name'])) {
        update_post_meta($order_id, '_starmaker_nickname', sanitize_text_field($user_info['name']));
    }
    if (!empty($user_info['avatar'])) {
        update_post_meta($order_id, '_starmaker_avatar', esc_url_raw($user_info['avatar']));
    }
}

/**
 * Add StarMaker user information to order confirmation email
 * 
 * @param WC_Order $order The order object
 * @param bool $sent_to_admin Whether the email is sent to admin
 * @param bool $plain_text Whether the email is plain text
 */
function sc_add_starmaker_info_to_email($order, $sent_to_admin, $plain_text) {
    $starmaker_id = $order->get_meta('_billing_starmaker_id');
    $nickname = $order->get_meta('_starmaker_nickname');
    $avatar = $order->get_meta('_starmaker_avatar');
    
    if (empty($starmaker_id)) {
        return;
    }
    
    if ($plain_text) {
        echo "\n\n==========\n\n";
        echo "StarMaker Information:\n";
        echo "StarMaker ID: " . esc_html($starmaker_id) . "\n";
        if (!empty($nickname)) {
            echo "Nickname: " . esc_html($nickname) . "\n";
        }
    } else {
        echo '<h2>StarMaker Information</h2>';
        echo '<p><strong>StarMaker ID:</strong> ' . esc_html($starmaker_id) . '</p>';
        if (!empty($nickname)) {
            echo '<p><strong>Nickname:</strong> ' . esc_html($nickname) . '</p>';
        }
        if (!empty($avatar)) {
            echo '<p><img src="' . esc_url($avatar) . '" alt="StarMaker Avatar" style="max-width: 100px; height: auto;"></p>';
        }
    }
}
add_action('woocommerce_email_order_details', 'sc_add_starmaker_info_to_email', 15, 3);

/**
 * Fetch and store StarMaker user information during checkout validation
 * 
 * @param array $fields The checkout fields
 * @param WP_Error $errors The errors object
 */
function sc_fetch_starmaker_info_during_checkout($fields, $errors) {
    if (empty($fields['billing_starmaker_id'])) {
        return;
    }
    
    $starmaker_id = sanitize_text_field($fields['billing_starmaker_id']);
    $user_info = sc_fetch_starmaker_user_info($starmaker_id);
    
    if (is_wp_error($user_info)) {
        // Log the error but don't block checkout
        error_log('StarMaker API Error: ' . $user_info->get_error_message());
        return;
    }
    
    // Store the user information in a transient for use during order creation
    set_transient('sc_starmaker_info_' . $starmaker_id, $user_info, HOUR_IN_SECONDS);
}
add_action('woocommerce_after_checkout_validation', 'sc_fetch_starmaker_info_during_checkout', 20, 2);

/**
 * Store StarMaker user information when order is created
 * 
 * @param int $order_id The order ID
 */
function sc_store_starmaker_info_on_order_creation($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $starmaker_id = get_post_meta($order_id, '_billing_starmaker_id', true);
    if (!$starmaker_id) {
        return;
    }

    // Fetch user info from StarMaker API
    $user_info = sc_fetch_starmaker_user_info($starmaker_id);
    if (is_wp_error($user_info)) {
        error_log('Failed to fetch StarMaker user info: ' . $user_info->get_error_message());
        return;
    }

    // Store the user info in order meta
    update_post_meta($order_id, '_starmaker_user_info', $user_info);
    
    // Add a note to the order
    $order->add_order_note(sprintf(
        'StarMaker Information: Nickname: %s',
        $user_info['name'] ?? 'N/A'
    ));
}
add_action('woocommerce_checkout_create_order', 'sc_store_starmaker_info_on_order_creation', 10, 1);

/**
 * Display StarMaker user information on the checkout page
 */
function sc_display_starmaker_info_on_checkout() {
    // Only run on checkout page
    if (!is_checkout()) {
        return;
    }
    
    // Get the current StarMaker ID from the form
    $starmaker_id = isset($_POST['billing_starmaker_id']) ? sanitize_text_field($_POST['billing_starmaker_id']) : '';
    
    // If no StarMaker ID is set, try to get it from the session
    if (empty($starmaker_id) && isset(WC()->session) && WC()->session->get('billing_starmaker_id')) {
        $starmaker_id = WC()->session->get('billing_starmaker_id');
    }
    
    // If still no StarMaker ID, return
    if (empty($starmaker_id)) {
        return;
    }
    
    // Check if we already have the user info in the session
    $user_info = null;
    if (isset(WC()->session) && WC()->session->get('starmaker_user_info')) {
        $user_info = WC()->session->get('starmaker_user_info');
    } else {
        // Fetch user info from API
        $user_info = sc_fetch_starmaker_user_info($starmaker_id);
        
        // Store in session for future use
        if (!is_wp_error($user_info) && isset(WC()->session)) {
            WC()->session->set('starmaker_user_info', $user_info);
        }
    }
    
    // If we have user info, display it
    if (!is_wp_error($user_info) && !empty($user_info)) {
        $nickname = isset($user_info['name']) ? $user_info['name'] : '';
        $avatar = isset($user_info['avatar']) ? $user_info['avatar'] : '';
        
        echo '<div class="starmaker-info-display" style="margin: 20px 0; padding: 15px; background: #f8f8f8; border-radius: 5px;">';
        echo '<h3>StarMaker Information</h3>';
        
        if (!empty($nickname)) {
            echo '<p><strong>Nickname:</strong> ' . esc_html($nickname) . '</p>';
        }
        
        if (!empty($avatar)) {
            echo '<p><img src="' . esc_url($avatar) . '" alt="StarMaker Avatar" style="max-width: 100px; height: auto; border-radius: 50%;"></p>';
        }
        
        echo '</div>';
    }
}
add_action('woocommerce_after_checkout_form', 'sc_display_starmaker_info_on_checkout');

/**
 * Add JavaScript to update StarMaker info when ID changes
 */
function sc_add_starmaker_info_update_js() {
    if (!is_checkout()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Function to update StarMaker info
        function updateStarmakerInfo() {
            var starmakerId = $('#billing_starmaker_id').val();
            
            if (!starmakerId) {
                $('.starmaker-info-display').hide();
                return;
            }
            
            // Show loading indicator
            if ($('.starmaker-info-display').length === 0) {
                $('form.checkout').after('<div class="starmaker-info-display" style="margin: 20px 0; padding: 15px; background: #f8f8f8; border-radius: 5px;"><p>Loading StarMaker information...</p></div>');
            } else {
                $('.starmaker-info-display').html('<p>Loading StarMaker information...</p>').show();
            }
            
            // Make AJAX request to fetch user info
            $.ajax({
                url: wc_checkout_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'sc_fetch_starmaker_info',
                    starmaker_id: starmakerId,
                    nonce: '<?php echo wp_create_nonce('sc_fetch_starmaker_info'); ?>'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var userInfo = response.data;
                        var html = '<h3>StarMaker Information</h3>';
                        
                        if (userInfo.name) {
                            html += '<p><strong>Nickname:</strong> ' + userInfo.name + '</p>';
                        }
                        
                        if (userInfo.avatar) {
                            html += '<p><img src="' + userInfo.avatar + '" alt="StarMaker Avatar" style="max-width: 100px; height: auto; border-radius: 50%;"></p>';
                        }
                        
                        $('.starmaker-info-display').html(html);
                    } else {
                        $('.starmaker-info-display').html('<p>Unable to fetch StarMaker information.</p>');
                    }
                },
                error: function() {
                    $('.starmaker-info-display').html('<p>Error fetching StarMaker information.</p>');
                }
            });
        }
        
        // Update on input change
        $('#billing_starmaker_id').on('change input', function() {
            updateStarmakerInfo();
        });
        
        // Initial update
        updateStarmakerInfo();
    });
    </script>
    <?php
}
add_action('wp_footer', 'sc_add_starmaker_info_update_js');

/**
 * AJAX handler for fetching StarMaker user info
 */
function sc_ajax_fetch_starmaker_info() {
    check_ajax_referer('sc_fetch_starmaker_info', 'nonce');
    
    $starmaker_id = isset($_POST['starmaker_id']) ? sanitize_text_field($_POST['starmaker_id']) : '';
    
    if (empty($starmaker_id)) {
        wp_send_json_error('No StarMaker ID provided');
    }
    
    $user_info = sc_fetch_starmaker_user_info($starmaker_id);
    
    if (is_wp_error($user_info)) {
        wp_send_json_error($user_info->get_error_message());
    }
    
    wp_send_json_success($user_info);
}
add_action('wp_ajax_sc_fetch_starmaker_info', 'sc_ajax_fetch_starmaker_info');
add_action('wp_ajax_nopriv_sc_fetch_starmaker_info', 'sc_ajax_fetch_starmaker_info');

/**
 * Display StarMaker information on the order detail page
 */
function sc_display_starmaker_info_on_order_detail($order) {
    // Get StarMaker ID from order meta
    $starmaker_id = get_post_meta($order->get_id(), '_billing_starmaker_id', true);
    if (empty($starmaker_id)) {
        return;
    }

    // Get StarMaker user info
    $user_info = get_post_meta($order->get_id(), '_starmaker_user_info', true);
    
    // If no user info found, try to fetch it again
    if (empty($user_info)) {
        $user_info = sc_fetch_starmaker_user_info($starmaker_id);
        if (!empty($user_info) && !is_wp_error($user_info)) {
            sc_store_starmaker_user_info($order->get_id(), $user_info);
        }
    }

    // Display the information
    ?>
    <section class="starmaker-info">
        <h2><?php esc_html_e('StarMaker Information', 'sc-fraud-prevention'); ?></h2>
        <p><strong><?php esc_html_e('StarMaker ID:', 'sc-fraud-prevention'); ?></strong> <?php echo esc_html($starmaker_id); ?></p>
        
        <?php if (!empty($user_info) && !is_wp_error($user_info)) : ?>
            <?php if (!empty($user_info['name'])) : ?>
                <p><strong><?php esc_html_e('Nickname:', 'sc-fraud-prevention'); ?></strong> <?php echo esc_html($user_info['name']); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($user_info['avatar'])) : ?>
                <div class="starmaker-avatar">
                    <img src="<?php echo esc_url($user_info['avatar']); ?>" alt="<?php esc_attr_e('StarMaker Avatar', 'sc-fraud-prevention'); ?>" style="max-width: 100px; border-radius: 50%;">
                </div>
            <?php endif; ?>
        <?php else : ?>
            <p><?php esc_html_e('StarMaker user information could not be retrieved.', 'sc-fraud-prevention'); ?></p>
        <?php endif; ?>
    </section>
    <style>
        .starmaker-info {
            margin: 20px 0;
            padding: 20px;
            background: #f8f8f8;
            border-radius: 5px;
        }
        .starmaker-info h2 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        .starmaker-avatar {
            margin-top: 10px;
        }
    </style>
    <?php
}
add_action('woocommerce_order_details_after_order_table', 'sc_display_starmaker_info_on_order_detail');

