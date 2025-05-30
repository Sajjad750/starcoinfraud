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
            error_log('[StarMaker] Error: Order not found for ID: ' . $order_id);
            return;
        }

        // Add initial debug note
        $order->add_order_note('[StarMaker] Starting coin calculation for order');

        // Calculate gold amount based on order items
        $gold_amount = 0;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                $order->add_order_note('[StarMaker] Warning: Product not found for item');
                continue;
            }

            // Get product details
            $price = $product->get_price();
            $quantity = $item->get_quantity();
            $sku = $product->get_sku();
            
            if (empty($sku)) {
                // If no SKU set, log warning and skip this product
                $order->add_order_note(sprintf(
                    '[StarMaker] Warning: No SKU set for product "%s" (ID: %d). SKU should contain the coin amount.',
                    $product->get_name(),
                    $product->get_id()
                ));
                error_log(sprintf(
                    '[StarMaker] Warning: No SKU set for product "%s" (ID: %d)',
                    $product->get_name(),
                    $product->get_id()
                ));
                continue;
            }
            
            // Get coin amount from SKU
            $coins = intval($sku);
            
            if ($coins <= 0) {
                // If SKU is not a valid number, log warning and skip this product
                $order->add_order_note(sprintf(
                    '[StarMaker] Warning: Invalid SKU "%s" for product "%s". SKU should be a number representing coins.',
                    $sku,
                    $product->get_name()
                ));
                error_log(sprintf(
                    '[StarMaker] Warning: Invalid SKU "%s" for product "%s"',
                    $sku,
                    $product->get_name()
                ));
                continue;
            }
            
            // Calculate total coins for this item
            $item_coins = $coins * $quantity;
            $gold_amount += $item_coins;
            
            // Add detailed debug note for each item
            $order->add_order_note(sprintf(
                '[StarMaker] Item: %s | Price: $%s | Coins per unit: %d | Quantity: %d | Total coins: %d',
                $product->get_name(),
                $price,
                $coins,
                $quantity,
                $item_coins
            ));
        }
        
        // Store gold amount in order meta
        update_post_meta($order_id, '_gold_amount', $gold_amount);
        
        // Add final debug note
        $order->add_order_note(sprintf(
            '[StarMaker] Final calculation: Total coins: %d | Order total: $%s',
            $gold_amount,
            $order->get_total()
        ));
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
            error_log('[StarMaker] Error: Order not found for ID: ' . $order_id);
            return false;
        }

        // Add debug note about starting API call
        $order->add_order_note('[StarMaker] Starting API call to deliver coins');

        // Check if order has already been processed
        if (get_post_meta($order_id, '_starmaker_api_processed', true)) {
            $order->add_order_note('[StarMaker] Warning: Order already processed with StarMaker API');
            return true;
        }

        // Get StarMaker ID
        $starmaker_id = get_post_meta($order_id, '_billing_starmaker_id', true);
        if (empty($starmaker_id)) {
            $order->add_order_note('[StarMaker] Error: No StarMaker ID found for order');
            return false;
        }

        // Get gold amount from order meta (which was calculated from SKUs)
        $gold_amount = get_post_meta($order_id, '_gold_amount', true);
        if (empty($gold_amount)) {
            // If no gold amount found, recalculate from order items
            $gold_amount = 0;
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) {
                    continue;
                }
                
                $sku = $product->get_sku();
                if (!empty($sku)) {
                    $coins = intval($sku);
                    $gold_amount += $coins * $item->get_quantity();
                }
            }
            
            if ($gold_amount <= 0) {
                $order->add_order_note('[StarMaker] Error: Could not determine coin amount from SKUs');
                return false;
            }
            
            // Store the recalculated amount
            update_post_meta($order_id, '_gold_amount', $gold_amount);
        }

        // Prepare request data
        $request_data = array(
            'sid' => $starmaker_id,
            'currency' => $order->get_currency(),
            'price' => $order->get_total(),
            'gold' => intval($gold_amount), // Ensure we're sending an integer value
            'oid' => $order_id . '_' . time(),
        );

        // Add debug note about request data
        $order->add_order_note('[StarMaker] API Request Data: ' . print_r($request_data, true));

        // API credentials
        $app_key = 'hashtag-cb5bbd8450f75d34ad6fca62d26725c6';
        $app_secret = '4651d13d5ed453feab40add2b900b089';
        $agent_uid = '12666373960089905';
        
        // API endpoint
        $api_url = 'https://pay.starmakerstudios.com/api/v3/external/agent/create-order';
        
        // Generate timestamp and signature
        $timestamp = time();
        $request_body = json_encode($request_data);
        $message = '/api/v3/external/agent/create-order:' . $timestamp . ':' . $request_body;
        $signature = hash_hmac('sha256', $message, $app_secret);
        
        // Add debug note about request details
        $order->add_order_note(sprintf(
            '[StarMaker] API Details - URL: %s | Timestamp: %d | Signature: %s',
            $api_url,
            $timestamp,
            $signature
        ));
        
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
        $order->add_order_note('[StarMaker] Making API request...');
        
        // Make the API request
        $response = wp_remote_post($api_url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $order->add_order_note('[StarMaker] API Error: ' . $error_message);
            error_log('[StarMaker] API Error: ' . $error_message);
            return false;
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Add debug note about response
        $order->add_order_note('[StarMaker] API Response: ' . print_r($data, true));
        
        // Check response status
        if (empty($data) || !isset($data['code'])) {
            $error_message = 'Invalid response from StarMaker API';
            $order->add_order_note('[StarMaker] Error: ' . $error_message);
            error_log('[StarMaker] Error: ' . $error_message);
            return false;
        }
        
        // Mark order as processed
        update_post_meta($order_id, '_starmaker_api_processed', true);
        
        // Store StarMaker order ID
        if (isset($data['data']['cid'])) {
            update_post_meta($order_id, '_starmaker_order_id', $data['data']['cid']);
            $order->add_order_note(sprintf(
                '[StarMaker] Stored StarMaker Order ID: %s',
                $data['data']['cid']
            ));
        }
        
        // Handle response based on status code
        switch ($data['code']) {
            case 0:
                $order->add_order_note('[StarMaker] Success: Order completed successfully');
                $order->update_status('completed', 'StarMaker API: Order completed successfully.');
                return true;
                
            case 1:
                $order->add_order_note('[StarMaker] Error: Payment declined');
                $order->update_status('failed', 'StarMaker API: Payment declined.');
                return false;
                
            case 151:
                $order->add_order_note('[StarMaker] Notice: Order pending payment (risk control)');
                $order->update_status('on-hold', 'StarMaker API: Order pending payment due to risk control.');
                return false;
                
            default:
                $error_message = isset($data['msg']) ? $data['msg'] : 'Unknown error';
                $order->add_order_note('[StarMaker] Error: ' . $error_message);
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