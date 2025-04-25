<?php
/**
 * StarMaker API Integration
 *
 * Handles integration with the StarMaker API for delivering purchased items
 * and updating order status based on API responses.
 *
 * @package    SC_Fraud_Prevention
 * @subpackage SC_Fraud_Prevention/includes
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * StarMaker API Integration Class
 *
 * This class handles the integration with the StarMaker API for delivering
 * purchased items and updating order status based on API responses.
 *
 * @package    SC_Fraud_Prevention
 * @subpackage SC_Fraud_Prevention/includes
 */
class SC_StarMaker_API_Integration {

    /**
     * Initialize the class and set up hooks
     */
    public function init() {
        // Store gold amount in order meta when order is created
        add_action('woocommerce_checkout_create_order', array($this, 'store_gold_amount_in_order_meta'), 10, 1);
        
        // Deliver items via StarMaker API when order status changes to processing
        add_action('woocommerce_order_status_changed', array($this, 'deliver_items_on_order_status_change'), 10, 3);
        
        // Mark order as passed fraud check when status changes to processing
        add_action('woocommerce_order_status_changed', array($this, 'mark_order_as_passed_fraud_check'), 10, 3);
    }

    /**
     * Store gold amount in order meta when order is created
     *
     * @param int $order_id The order ID
     */
    public function store_gold_amount_in_order_meta($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Calculate gold amount based on order items
        $gold_amount = 0;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            // Check if product is a gold product
            $is_gold_product = false;
            
            // Check product categories
            $categories = get_the_terms($product->get_id(), 'product_cat');
            if ($categories && !is_wp_error($categories)) {
                foreach ($categories as $category) {
                    if (stripos($category->name, 'gold') !== false) {
                        $is_gold_product = true;
                        break;
                    }
                }
            }
            
            // Check product name
            if (!$is_gold_product && stripos($product->get_name(), 'gold') !== false) {
                $is_gold_product = true;
            }
            
            if ($is_gold_product) {
                // If it's a gold product, use the quantity as gold amount
                $gold_amount += $item->get_quantity();
            }
        }
        
        // If no gold products found, calculate gold based on order total
        if ($gold_amount === 0) {
            // Default conversion: 1 USD = 1 gold (adjust as needed)
            $gold_amount = $order->get_total();
        }
        
        // Store gold amount in order meta
        update_post_meta($order_id, '_gold_amount', $gold_amount);
    }

    /**
     * Deliver items via StarMaker API
     *
     * @param int $order_id The order ID
     * @return bool Whether the delivery was successful
     */
    public function deliver_items_via_starmaker_api($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        // Add debug note about starting API call
        $order->add_order_note('[Debug] Starting StarMaker API call');

        // Check if order has already been processed
        if (get_post_meta($order_id, '_starmaker_api_processed', true)) {
            $order->add_order_note('[Debug] Order already processed with StarMaker API');
            return true;
        }

        // Get StarMaker ID
        $starmaker_id = get_post_meta($order_id, '_billing_starmaker_id', true);
        if (empty($starmaker_id)) {
            $order->add_order_note('[Debug] Error: No StarMaker ID found for order');
            return false;
        }

        // Get gold amount
        $gold_amount = get_post_meta($order_id, '_gold_amount', true);
        if (empty($gold_amount)) {
            $gold_amount = $order->get_total();
            $order->add_order_note('[Debug] Using order total as gold amount: ' . $gold_amount);
        }

        // Prepare request data
        $request_data = array(
            'sid' => $starmaker_id,
            'currency' => $order->get_currency(),
            'price' => $order->get_total(),
            'gold' => $gold_amount,
            'oid' => $order_id . '_' . time(),
        );

        // Add debug note about request data
        $order->add_order_note('[Debug] Request Data: ' . print_r($request_data, true));

        // API credentials
        $app_key = 'hashtag-7i36xt0t';
        $app_secret = '8a0c3250725d09be379ce8ed901c5cd7';
        $agent_uid = '12666376951992244';
        
        // API endpoint
        $api_url = 'https://pay-test.starmakerstudios.com/api/v3/external/agent/create-order';
        
        // Generate timestamp and signature
        $timestamp = time();
        $request_body = json_encode($request_data);
        $message = '/api/v3/external/agent/create-order:' . $timestamp . ':' . $request_body;
        $signature = hash_hmac('sha256', $message, $app_secret);
        
        // Add debug note about request details
        $order->add_order_note('[Debug] API URL: ' . $api_url);
        $order->add_order_note('[Debug] Request Timestamp: ' . $timestamp);
        $order->add_order_note('[Debug] Request Signature: ' . $signature);
        
        // Set up request arguments
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'x-app-key' => $app_key,
                'x-request-id' => uniqid(),
                'x-request-timestamp' => $timestamp,
                'x-request-sign' => $signature,
                'x-agent-uid' => $agent_uid,
                'Content-Type' => 'application/json'
            ),
            'body' => $request_body,
            'timeout' => 30
        );
        
        // Add debug note before making the request
        $order->add_order_note('[Debug] Making API request...');
        
        // Make the API request
        $response = wp_remote_post($api_url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $order->add_order_note('[Debug] API Error: ' . $error_message);
            return false;
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Add debug note about response
        $order->add_order_note('[Debug] API Response: ' . print_r($data, true));
        
        // Check response status
        if (empty($data) || !isset($data['code'])) {
            $error_message = 'Invalid response from StarMaker API';
            $order->add_order_note('[Debug] Error: ' . $error_message);
            return false;
        }
        
        // Mark order as processed
        update_post_meta($order_id, '_starmaker_api_processed', true);
        
        // Store StarMaker order ID
        if (isset($data['data']['cid'])) {
            update_post_meta($order_id, '_starmaker_order_id', $data['data']['cid']);
            $order->add_order_note('[Debug] Stored StarMaker Order ID: ' . $data['data']['cid']);
        }
        
        // Handle response based on status code
        switch ($data['code']) {
            case 0:
                $order->add_order_note('[Debug] Success: Order completed successfully');
                $order->update_status('completed', 'StarMaker API: Order completed successfully.');
                return true;
                
            case 1:
                $order->add_order_note('[Debug] Error: Payment declined');
                $order->update_status('failed', 'StarMaker API: Payment declined.');
                return false;
                
            case 151:
                $order->add_order_note('[Debug] Notice: Order pending payment (risk control)');
                $order->update_status('on-hold', 'StarMaker API: Order pending payment due to risk control.');
                return false;
                
            default:
                $error_message = isset($data['msg']) ? $data['msg'] : 'Unknown error';
                
                $order->add_order_note('[Debug] Error: ' . $error_message);
                $order->update_status('failed', 'StarMaker API Error: ' . $error_message);
                return false;
        }
    }

    /**
     * Deliver items on order status change
     *
     * @param int $order_id The order ID
     * @param string $old_status The old order status
     * @param string $new_status The new order status
     */
    public function deliver_items_on_order_status_change($order_id, $old_status, $new_status) {
        // Only process orders that are changing to "processing" status
        if ($new_status !== 'processing') {
            return;
        }

        // Check if order has passed fraud checks
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if order has passed fraud checks
        if ($order->get_meta('_passed_fraud_check', true) !== 'yes') {
            return;
        }

        // Deliver items via StarMaker API
        $this->deliver_items_via_starmaker_api($order_id);
    }

    /**
     * Mark order as passed fraud check
     *
     * @param int $order_id The order ID
     * @param string $old_status The old order status
     * @param string $new_status The new order status
     */
    public function mark_order_as_passed_fraud_check($order_id, $old_status, $new_status) {
        // Only process orders that are changing to "processing" status
        if ($new_status !== 'processing') {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Mark order as passed fraud check
        update_post_meta($order_id, '_passed_fraud_check', 'yes');
        $order->add_order_note('Order marked as passed fraud check.');
    }
} 