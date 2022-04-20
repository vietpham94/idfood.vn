<?php
// Add provider when customer choose a provider to buy
add_filter('woocommerce_add_cart_item_data', 'add_custom_fields_value_to_cart_item', 10, 3);
function add_custom_fields_value_to_cart_item($cart_item_data)
{
    $custom_input = filter_input(INPUT_POST, 'provider');
    if (!empty($custom_input)) {
        $cart_item_data['provider'] = $custom_input;
    }
    return $cart_item_data;
}

add_action('woocommerce_checkout_create_order_line_item', 'add_custom_fields_value_to_order_items', 10, 4);
function add_custom_fields_value_to_order_items($item, $cart_item_key, $values, WC_Order $order)
{
    $order->add_meta_data('_provider', $values['provider']);
}

// After finish order find a provider for order
add_action('woocommerce_thankyou', 'woocommerce_thankyou_change_order_status', 10, 1);
function woocommerce_thankyou_change_order_status($order_id)
{
    if (!$order_id) return;
    $order = wc_get_order($order_id);

    $handler_user_id = get_field('handler_user_id', $order_id);

    if (empty($handler_user_id)) {
        $handler_user_id = $order->get_meta('_provider');
        update_field('handler_user_id', $handler_user_id, $order_id);
    }


    if (empty($handler_user_id)) {
        $handler_user_id = find_handler_user_id($order_id);
        update_field('handler_user_id', $handler_user_id, $order_id);
    }

    // Update status to 'mới nhận' with cod payment method
    if ($order->get_status() == 'processing' && $order->get_payment_method() == 'cod') {
        $order->update_status('pending');
        push_order_notification($handler_user_id, $order_id);
    }
}

// Change status order and push notification to provider
add_action('woocommerce_order_status_changed', 'action_woocommerce_order_status_changed', 10, 4);
function action_woocommerce_order_status_changed($order_id, $this_status_transition_from, $this_status_transition_to, $order)
{
    if (!$order_id) return;
    $handler_user_id = get_field('handler_user_id', $order_id);

    if ($this_status_transition_from == 'on-hold' && $this_status_transition_to == 'pending') {
        push_order_notification($handler_user_id, $order_id);
    }
}

/**
 * Find a provider nearest
 *
 * @param int $order_id
 * @return int
 * @throws Exception
 */
function find_handler_user_id(int $order_id): int
{
    $order = wc_get_order($order_id);
    if (empty($order)) {
        write_log(__FILE__ . ':90 Order id ' . $order_id . ' not exists');
        return 0;
    }

    $fullStrAddress = $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() . ' ' . $order->get_shipping_city() . ' ' . $order->get_shipping_state();
    $arrChars = explode(';', $fullStrAddress);

    $user_ids = find_supplier();
    $customers = WC_API_Customers::get_customers(null, array('include' => $user_ids));

    write_log(__FILE__ . ': 79');
    write_log($customers);

    $result = [];
    foreach ($customers as $customer) {
        $matchCount = 0;
        foreach ($arrChars as $char) {
            if (strpos($customer->get_billing_address_1(), $char)) {
                $matchCount += 1;
            }
        }
        $result[] = array('matchCount' => $matchCount, 'ID' => $customer->ID);
    }

    write_log(__FILE__ . ': 93');
    write_log($result);

    if (!empty($result)) {
        usort($result, 'cmp');
        write_log(__FILE__ . ':124 find_handler_user_id ' . $result[0]['ID']);
        return $result[0]['ID'];
    }

    $args = array('role' => 'shop_manager', 'orderby' => 'user_nicename', 'order' => 'ASC');
    $users = get_users($args);
    if (!empty($users)) {
        return $users[0]->id;
    }

    return 0;

}

function cmp($a, $b)
{
    return strcmp($b['matchCount'], $a['matchCount']);
}

function find_supplier()
{
    $args = array(
        'role' => 'supplier',
        'order' => 'ASC'
    );

    $users = get_users($args);
    $user_ids = array();
    foreach ($users as $user) {
        $user_ids[] = $user->ID;
    }

    return $user_ids;
}