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

    $product = wc_get_product($order);

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

    $arrAdd1 = explode(' ', $order->get_shipping_address_1());

    $user_ids = find_supplier();
    $result = [];
    foreach ($user_ids as $user_id) {
        $customer = new WC_Customer($user_id);

        $matchCount = 0;

        if ($customer->get_billing_state() == $order->get_billing_state()) {
            $matchCount += 5;
        }

        if ($customer->get_billing_city() != $order->get_billing_city()) {
            $matchCount += 10;
        }

        if ($customer->get_billing_address_2() != $order->get_billing_address_2()) {
            $matchCount += 15;
        }

        if ($matchCount == 30) {
            $matchCountAdd1 = 0;
            foreach ($arrAdd1 as $char) {
                if (strpos($customer->get_shipping_address_1(), $char)) {
                    $matchCountAdd1 += 1;
                }
            }

            if ($matchCountAdd1 > sizeof($arrAdd1)) {
                $matchCountAdd1 = sizeof($arrAdd1) - 1;
            }

            $matchCount += $matchCountAdd1;
        }

        if ($matchCount > 0) {
            $result[] = array('matchCount' => $matchCount, 'ID' => $user_id);
        }
    }

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

/**
 * To short by matchCount
 * @param $a
 * @param $b
 * @return int
 */
function cmp($a, $b)
{
    return strcmp($b['matchCount'], $a['matchCount']);
}

/**
 * Get All supplier
 * @return array
 */
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

/**
 * Custom shipping and  building in admin user manager
 * Action to create supplier
 *
 */
add_filter('woocommerce_customer_meta_fields', 'supplier_admin_fields');
function supplier_admin_fields($admin_fields)
{
    if (!isset($_GET['user_id'])) {
        return;
    }

    global $tinh_thanhpho;
    $customer = new WC_Customer($_GET['user_id']);

    $admin_fields['billing']['fields']['billing_last_name'] = array(
        'label' => __('Họ và tên'),
        'required' => true,
    );
    $admin_fields['billing']['fields']['billing_address_1'] = array(
        'label' => __('Địa chỉ'),
        'placeholder' => __('Số nhà, tên đường,..'),
    );

    $admin_fields['billing']['fields']['billing_state'] = array(
        'label' => __('Province/City', 'devvn-vncheckout'),
        'required' => true,
        'type' => 'select',
        'placeholder' => _x('Select Province/City', 'placeholder', 'devvn-vncheckout'),
        'options' => array('' => __('Select Province/City', 'devvn-vncheckout')) + apply_filters('devvn_states_vn', $tinh_thanhpho),
        'priority' => 30
    );

    $admin_fields['billing']['fields']['billing_city'] = array(
        'label' => __('District', 'devvn-vncheckout'),
        'required' => true,
        'type' => 'select',
        'class' => array('form-row-last'),
        'placeholder' => _x('Select District', 'placeholder', 'devvn-vncheckout'),
        'options' => get_default_districts($customer),
        'priority' => 40
    );

    $admin_fields['billing']['fields']['billing_address_2'] = array(
        'label' => __('Commune/Ward/Town', 'devvn-vncheckout'),
        'required' => true,
        'type' => 'select',
        'class' => array('form-row-first'),
        'placeholder' => _x('Select Commune/Ward/Town', 'placeholder', 'devvn-vncheckout'),
        'options' => get_default_wards($customer),
        'priority' => 50
    );

    $admin_fields['shipping']['fields']['shipping_last_name'] = array(
        'label' => __('Họ và tên')
    );
    $admin_fields['shipping']['fields']['shipping_address_1'] = array(
        'label' => __('Địa chỉ'),
        'placeholder' => __('Số nhà, tên đường,..')
    );

    $admin_fields['shipping']['fields']['shipping_state'] = array(
        'label' => __('Province/City', 'devvn-vncheckout'),
        'required' => true,
        'type' => 'select',
        'class' => array('form-row-first', 'address-field', 'update_totals_on_change'),
        'placeholder' => _x('Select Province/City', 'placeholder', 'devvn-vncheckout'),
        'options' => array('' => __('Select Province/City', 'devvn-vncheckout')) + apply_filters('devvn_states_vn', $tinh_thanhpho),
        'priority' => 30
    );

    $admin_fields['shipping']['fields']['shipping_city'] = array(
        'label' => __('District', 'devvn-vncheckout'),
        'required' => true,
        'type' => 'select',
        'class' => array('form-row-last'),
        'placeholder' => _x('Select District', 'placeholder', 'devvn-vncheckout'),
        'options' => get_default_districts($customer),
        'priority' => 40
    );

    $admin_fields['shipping']['fields']['shipping_address_2'] = array(
        'label' => __('Commune/Ward/Town', 'devvn-vncheckout'),
        'required' => true,
        'type' => 'select',
        'class' => array('form-row-first'),
        'placeholder' => _x('Select Commune/Ward/Town', 'placeholder', 'devvn-vncheckout'),
        'options' => get_default_wards($customer),
        'priority' => 50
    );

    unset($admin_fields['billing']['fields']['billing_first_name']);
    unset($admin_fields['billing']['fields']['billing_company']);
    unset($admin_fields['billing']['fields']['billing_postcode']);
    unset($admin_fields['billing']['fields']['billing_country']);
    unset($admin_fields['shipping']['fields']['shipping_first_name']);
    unset($admin_fields['shipping']['fields']['shipping_company']);
    unset($admin_fields['shipping']['fields']['shipping_postcode']);
    unset($admin_fields['shipping']['fields']['shipping_country']);

    return $admin_fields;
}

function get_default_districts(WC_Customer $customer)
{
    include 'cities/quan_huyen.php';

    if (empty($quan_huyen)) {
        return array();
    }

    $id_state = $customer->get_billing_state();

    $quan_huyen_ = array();
    foreach ($quan_huyen as $obj) {
        if (isset($obj['matp']) && $obj['matp'] == $id_state) {
            $quan_huyen_[$obj['maqh']] = $obj['name'];
        }
    }

    return $quan_huyen_;
}

function get_default_wards(WC_Customer $customer)
{
    include 'cities/xa_phuong_thitran.php';

    if (empty($xa_phuong_thitran)) {
        return array();
    }

    $id_district = $customer->get_billing_city();

    $districts = array();
    foreach ($xa_phuong_thitran as $obj) {
        if (isset($obj['maqh']) && $obj['maqh'] == $id_district) {
            $districts[$obj['xaid']] = $obj['name'];
        }
    }

    return $districts;
}

add_action('edit_user_profile_update', 'update_user_info_bulling_shipping');
function update_user_info_bulling_shipping($user_id)
{
    update_user_meta($user_id, 'billing_last_name', $_POST['billing_last_name']);
    update_user_meta($user_id, 'billing_address_1', $_POST['shipping_address_1']);
    update_user_meta($user_id, 'billing_address_2', $_POST['shipping_address_2']);
    update_user_meta($user_id, 'billing_city', $_POST['shipping_city']);
    update_user_meta($user_id, 'billing_state', $_POST['shipping_state']);
    update_user_meta($user_id, 'billing_phone', $_POST['shipping_phone']);
    update_user_meta($user_id, 'billing_email', $_POST['billing_email']);

    update_user_meta($user_id, 'shipping_last_name', $_POST['shipping_last_name']);
    update_user_meta($user_id, 'billing_address_1', $_POST['billing_address_1']);
    update_user_meta($user_id, 'shipping_address_2', $_POST['shipping_address_2']);
    update_user_meta($user_id, 'shipping_city', $_POST['shipping_city']);
    update_user_meta($user_id, 'shipping_state', $_POST['shipping_state']);
    update_user_meta($user_id, 'shipping_phone', $_POST['shipping_phone']);
}

