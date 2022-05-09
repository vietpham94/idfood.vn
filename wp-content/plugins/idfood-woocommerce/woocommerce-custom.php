<?php
/** Add filter & action hook **/
add_filter('woocommerce_customer_meta_fields', 'supplier_admin_fields');
add_action('edit_user_profile_update', 'update_user_info_bulling_shipping');

add_filter('woocommerce_add_cart_item_data', 'add_custom_fields_value_to_cart_item', 10, 3);
add_action('woocommerce_checkout_create_order_line_item', 'add_custom_fields_value_to_order_items', 10, 4);
add_filter('woocommerce_cod_process_payment_order_status', 'set_cod_process_payment_order_status_pending', 10, 2);

add_action('woocommerce_thankyou', 'woocommerce_thankyou_change_order_status', 10, 1);
add_action('woocommerce_new_order', 'action_woocommerce_api_create_order', 10, 3);

add_action('woocommerce_order_status_changed', 'action_woocommerce_order_status_changed', 10, 4);
add_action('woocommerce_order_status_processing', 'action_woocommerce_order_processing', 10, 4);
add_action('woocommerce_order_status_completed', 'action_woocommerce_order_completed', 10, 4);

add_filter('woocommerce_my_account_my_orders_actions', 'remove_myaccount_orders_cancel_button', 10, 2);

/**
 * Add provider when customer choose a provider to buy
 *
 * @param $cart_item_data
 * @return mixed
 */
function add_custom_fields_value_to_cart_item($cart_item_data)
{
    write_log(__FILE__ . ': function add_custom_fields_value_to_cart_item');
    $custom_input = filter_input(INPUT_POST, 'provider');
    if (!empty($custom_input)) {
        $cart_item_data['provider'] = $custom_input;
    }
    return $cart_item_data;
}

/**
 * Add provider when customer choose a provider to buy
 *
 * @param $item
 * @param $cart_item_key
 * @param $values
 * @param WC_Order $order
 * @return void
 */
function add_custom_fields_value_to_order_items($item, $cart_item_key, $values, WC_Order $order)
{
    write_log(__FILE__ . ': function add_custom_fields_value_to_order_items');
    if (isset($values['provider'])) {
        $order->add_meta_data('_provider', $values['provider']);
    }
}

/**
 * Set default COD order status is pending
 *
 * @param $status
 * @param $order
 * @return mixed|string
 */
function set_cod_process_payment_order_status_pending($status, $order)
{
    write_log(__FILE__ . ': function set_cod_process_payment_order_status_pending');
    if ($order->get_payment_method() == 'cod')
        return 'pending';
    return $status;
}

/**
 * After customer finish order find a provider for order
 *
 * @param $order_id
 * @return void
 * @throws Exception
 */
function woocommerce_thankyou_change_order_status($order_id)
{
    write_log(__FILE__ . ': function woocommerce_thankyou_change_order_status');
    $background_process = new WC_IdFood_Background_Process(find_supplier_for_order_process($order_id));
    $background_process->save()->dispatch();
}

/**
 * After provider finish order add handler_user_id is shop_manager
 *
 * @param $order_id
 * @return void
 * @throws Exception
 */
function action_woocommerce_api_create_order($order_id)
{
    write_log(__FILE__ . ': function action_woocommerce_api_create_order');
    $handler_user_id = get_field('handler_user_id', $order_id);

    if (empty($handler_user_id)) {
        $user_data = get_userdata(get_current_user_id());
    }

    if (isset($user_data) && in_array('supplier', $user_data->roles)) {
        // Get Shop Manager for order of supplier
        $args = array('role' => 'shop_manager', 'orderby' => 'user_nicename', 'order' => 'ASC');
        $users = get_users($args);

        if (!empty($users)) {
            update_field('handler_user_id', $users[0]->id, $order_id);
        }

        $background_process = new WC_IdFood_Background_Process(find_supplier_for_order_process($order_id), false);
        $background_process->save()->dispatch();
    }
}

/**
 * @param $order_id
 * @return void
 * @throws Exception
 */
function find_supplier_for_order_process($order_id, $send_notification = true)
{
    write_log(__FILE__ . ': function find_supplier_for_order_process');
    if (empty($order_id)) return;
    $order = wc_get_order($order_id);
    $handler_user_id = get_field('handler_user_id', $order_id);

    if (empty($handler_user_id)) {
        $handler_user_id = $order->get_meta('_provider');
        write_log(__FILE__ . ':' . __LINE__);
        write_log($handler_user_id);
        if (isset($handler_user_id)) {
            update_field('handler_user_id', $handler_user_id, $order_id);
        }
    }

    if (empty($handler_user_id)) {
        $handler_user_id = find_handler_user_id($order_id);
        update_field('handler_user_id', $handler_user_id, $order_id);
    }

    $customer = new WC_Customer($order->get_customer_id());

    $create_by = $customer->get_meta('create_by_users');
    write_log(__FILE__ . ':' . __LINE__);
    write_log($create_by);
    if (empty($create_by)) {
        $create_by = array($handler_user_id);
        write_log(__FILE__ . ':' . __LINE__);
        write_log($create_by);
    } elseif (is_array($create_by) && !in_array($handler_user_id, $create_by)) {
        $create_by[] = $handler_user_id;
        write_log(__FILE__ . ':' . __LINE__);
        write_log($create_by);
    } elseif (!is_array($create_by)) {
        $create_by = unserialize($create_by);
        write_log(__FILE__ . ':' . __LINE__);
        write_log($create_by);
        if (!in_array($handler_user_id, $create_by)) {
            $create_by[] = $handler_user_id;
            write_log(__FILE__ . ':' . __LINE__);
            write_log($create_by);
        }
    }

    $customer->update_meta_data('create_by_users', $create_by);
    $customer->save();

    $user_data = get_userdata($handler_user_id);

    if ($send_notification || in_array('shop_manager', $user_data->roles)) {
        push_order_notification($handler_user_id, $order_id);
    }
}

/**
 * When Shop Manager change order status from on-hold to pending, push notification to provider
 *
 * @param $order_id
 * @param $this_status_transition_from
 * @param $this_status_transition_to
 * @param $order
 * @return void
 */
function action_woocommerce_order_status_changed($order_id, $this_status_transition_from, $this_status_transition_to, $order)
{
    write_log(__FILE__ . ': function action_woocommerce_order_status_changed');
    if (empty($order_id)) return;

    $handler_user_id = get_field('handler_user_id', $order_id);

    if ($this_status_transition_from == 'on-hold' && $this_status_transition_to == 'pending') {
        $background_process = new WC_IdFood_Background_Process(push_order_notification($handler_user_id, $order_id));
        $background_process->save()->dispatch();
    }

    if ($this_status_transition_from == 'processing' && $this_status_transition_to == 'pending') {
        $user_data = get_userdata($handler_user_id);
        if (in_array('supplier', $user_data->roles)) {
            $background_process = new WC_IdFood_Background_Process(updateSupplierStock($order_id, true));
            $background_process->save()->dispatch();
        } else if (in_array('shop_manager', $user_data->roles)) {
            $background_process = new WC_IdFood_Background_Process(updateIdfStock($order_id));
            $background_process->save()->dispatch();
        }
    }
}

/**
 * Process order pending to in-progress
 *
 * @param $order_id
 * @return void
 */
function action_woocommerce_order_processing($order_id)
{
    write_log(__FILE__ . ': function action_woocommerce_order_processing');
    if (empty($order_id)) return;

    $handler_user_id = get_field('handler_user_id', $order_id);
    $user_data = get_userdata($handler_user_id);

    write_log(__FILE__ . ':' . __LINE__);
    write_log($user_data->roles);

    if (in_array('supplier', $user_data->roles)) {
        $background_process = new WC_IdFood_Background_Process(updateSupplierStock($order_id));
        $background_process->save()->dispatch();
    } else if (in_array('shop_manager', $user_data->roles)) {
        $background_process = new WC_IdFood_Background_Process(updateIdfStock($order_id));
        $background_process->save()->dispatch();
    }
}

/**
 * Update stock of Supplier
 *
 * @param $order_id
 * @param bool $increase
 * @return void
 */
function updateSupplierStock($order_id, bool $increase = false)
{
    write_log(__FILE__ . ': function updateSupplierStock');

    if (empty($order_id)) {
        write_log(__FILE__ . ':' . __LINE__ . ' order_id is empty');
        return;
    }

    $order = wc_get_order($order_id);
    $handler_user_id = get_field('handler_user_id', $order_id);
    if (empty($order) || empty($handler_user_id)) {
        write_log(__FILE__ . ':' . __LINE__ . ' handler_user_id is empty');
        return;
    }

    $items = $order->get_items();
    $quantity = 0;
    foreach ($items as $item) {
        $product = $item->get_product();
        $quantity = $item->get_quantity();
    }

    if (empty($product)) {
        write_log(__FILE__ . ':' . __LINE__ . ' 113 product is empty');
        return;
    }

    $suppliers = get_posts(array(
        'post_type' => 'supplier',
        'meta_key' => 'supplier_user',
        'meta_value' => $handler_user_id,
    ));

    write_log(__FILE__ . ':' . __LINE__);
    write_log($suppliers);

    if (empty($suppliers)) {
        return;
    }

    $supplier = current($suppliers);
    $supplier_user = get_field('supplier_user', $supplier->ID);
    if ($supplier_user != $handler_user_id) {
        write_log(__FILE__ . ':' . __LINE__ . ' ' . get_field('supplier_user', $supplier_user) . ' <> ' . $handler_user_id);
        return;
    }

    $products_stock = get_field('supplier_products', $supplier->ID);
    if (empty($products_stock)) {
        write_log(__FILE__ . ':' . __LINE__ . ' products_stock is empty');
        return;
    }

    foreach ($products_stock as $key => $stock_row) {
        if (!empty($stock_row['supplier_product']->ID) && $stock_row['supplier_product']->ID != $product->get_id()) {
            write_log(__FILE__ . ':' . __LINE__);
            write_log($stock_row['supplier_product']->ID);
            write_log($product->get_id());
            continue;
        }

        if (is_numeric($stock_row['supplier_product']) && $stock_row['supplier_product'] != $product->get_id()) {
            write_log(__FILE__ . ':' . __LINE__);
            write_log($stock_row['supplier_product']);
            write_log($product->get_id());
            continue;
        }

        if ($increase || $supplier_user == $order->get_customer_id()) {
            $stock_row['supplier_num_sku'] = $stock_row['supplier_num_sku'] + $quantity;
        } else {
            $stock_row['supplier_num_sku'] = $stock_row['supplier_num_sku'] - $quantity;
        }

        write_log(__FILE__ . ':' . __LINE__);
        write_log($stock_row);
        write_log($key);

        $products_stock[$key] = $stock_row;
    }

    $result = update_field('supplier_products', $products_stock, $supplier->ID);
    write_log(__FILE__ . ':' . __LINE__ . ' Update product stock ' . $result);
}

/**
 * Input Supplier Stock
 *
 * @param WC_Order $order
 * @return void
 */
function inputSupplierStock(WC_Order $order)
{
    $suppliers = get_posts(array(
        'post_type' => 'supplier',
        'meta_key' => 'supplier_user',
        'meta_value' => $order->get_customer_id(),
    ));

    if (empty($suppliers)) {
        return;
    }

    $supplier = current($suppliers);
    $products_stock = get_field('supplier_products', $supplier->ID);

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $quantity = $item->get_quantity();

        if (empty($products_stock)) {
            $products_stock = array();
            $products_stock[] = array(
                'supplier_product' => $product->get_id(),
                'supplier_num_sku' => $quantity
            );
            continue;
        }

        $stock_row_index = searchSupplierProductsStock($products_stock, $product->get_id());
        if ($stock_row_index == -1) {
            $products_stock[] = array(
                'supplier_product' => $product->get_id(),
                'supplier_num_sku' => $quantity
            );
            continue;
        }

        $stock_row = $products_stock[$stock_row_index];
        $stock_row['supplier_num_sku'] = $stock_row['supplier_num_sku'] + $quantity;
        $products_stock[$stock_row_index] = $stock_row;
    }

    $result = update_field('supplier_products', $products_stock, $supplier->ID);
    write_log(__FILE__ . ':' . __LINE__ . ' Update product stock ' . $result);
}

/**
 * Search Supplier Products Stock
 *
 * @param $products_stock
 * @param $productId
 * @return false|int|string|void
 */
function searchSupplierProductsStock($products_stock, $productId)
{
    if (empty($products_stock)) {
        return -1;
    }

    if (empty($productId)) {
        return -1;
    }

    foreach ($products_stock as $key => $stock_row) {
        if (!empty($stock_row['supplier_product']->ID) && $stock_row['supplier_product']->ID != $productId) {
            continue;
        }

        if (is_numeric($stock_row['supplier_product']) && $stock_row['supplier_product'] != $productId) {
            continue;
        }

        return $key;
    }
}

/**
 * Update stock of IdFdod
 *
 * @param $order_id
 * @param bool $increase
 * @return void
 */
function updateIdfStock($order_id, bool $increase = false)
{
    write_log(__FILE__ . ': function updateIdfStock');

    if (empty($order_id)) {
        write_log(__FILE__ . ':' . __LINE__ . ' order_id is empty');
        return;
    }

    $order = wc_get_order($order_id);
    $handler_user_id = get_field('handler_user_id', $order_id);
    if (empty($order) || empty($handler_user_id)) {
        write_log(__FILE__ . ':' . __LINE__ . ' 101 handler_user_id is empty');
        return;
    }

    $items = $order->get_items();
    foreach ($items as $item) {
        $product = $item->get_product();
        $quantity = $item->get_quantity();

        if (empty($product)) {
            write_log(__FILE__ . ':' . __LINE__ . ' product is empty');
            continue;
        }

        $idf_stocks = get_posts(array(
            'post_type' => 'idf_stock',
            'meta_key' => 'product',
            'meta_value' => $product->get_id(),
        ));

        write_log(__FILE__ . ':' . __LINE__);
        write_log($idf_stocks);

        if (sizeof($idf_stocks) == 0) {
            write_log(__FILE__ . ':' . __LINE__ . ' idf_stocks is empty');
            continue;
        }

        $idf_stock = current($idf_stocks);
        if (empty($idf_stock)) {
            write_log(__FILE__ . ':' . __LINE__ . ' idf_stock is empty');
            continue;
        }

        $stock_num = get_field('stock', $idf_stock->ID);
        if ($increase) {
            $stock_num += $quantity;
        } else {
            $stock_num -= $quantity;
        }
        write_log(__FILE__ . ':' . __LINE__);
        write_log($idf_stock);
        write_log($stock_num);
        update_field('stock', $stock_num, $idf_stock);
    }
}

/**
 * When manual add order complete
 *
 * @param $order_id
 * @return void
 */
function action_woocommerce_order_completed($order_id)
{
    write_log(__FILE__ . ': function action_woocommerce_order_completed');

    if (empty($order_id)) return;

    $order = wc_get_order($order_id);
    if (empty($order)) return;

    if (!empty($order->get_meta('handler_user_id'))) {
        update_field('handler_user_id', $order->get_meta('handler_user_id'), $order->get_id());
    }

    $handler_user_id = get_field('handler_user_id', $order_id);
    $user_data = get_userdata($handler_user_id);
    $customer_data = get_userdata($order->get_customer_id());

    write_log(__FILE__ . ':' . __LINE__);
    write_log($user_data->roles);
    write_log($customer_data->roles);

    if (in_array('supplier', $user_data->roles) && $handler_user_id == get_current_user_id() && $order->get_created_via() == 'rest-api') {
        $background_process = new WC_IdFood_Background_Process(updateSupplierStock($order_id));
        $background_process->save()->dispatch();
    }

    if (in_array('supplier', $customer_data->roles)) {
        $background_process = new WC_IdFood_Background_Process(inputSupplierStock($order));
        $background_process->save()->dispatch();
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
    write_log(__FILE__ . ': function find_handler_user_id');

    $order = wc_get_order($order_id);
    if (empty($order)) {
        write_log(__FILE__ . ':' . __LINE__ . ' Order id ' . $order_id . ' not exists');
        return 0;
    }

    // Find supplier nearest
    $result = smart_find_handler($order);
    if (!empty($result)) {
        write_log(__FILE__ . ':' . __LINE__ . ' find_handler_user_id ' . array_key_last($result));
        return array_key_last($result);
    }

    write_log(__FILE__ . ':' . __LINE__ . ' Find supplier null, get Shop Manager');

    // Get Shop Manager for order of supplier
    $args = array('role' => 'shop_manager', 'orderby' => 'user_nicename', 'order' => 'ASC');
    $users = get_users($args);
    if (!empty($users)) {
        write_log(__FILE__ . ':' . __LINE__ . 'Shop Manager to handler order ' . $users[0]->id);
        return $users[0]->id;
    }

    write_log(__FILE__ . ':' . __LINE__ . ' Do not have anyone to handler order');

    // Don't have anyone to handler order
    return 0;

}

/**
 * Fin nearest handler
 *
 * @param WC_Order $order
 * @return array
 * @throws Exception
 */
function smart_find_handler(WC_Order $order): array
{
    write_log(__FILE__ . ': function smart_find_handler');

    $items = $order->get_items();
    foreach ($items as $item) {
        $product = $item->get_product();
    }

    if (empty($product)) {
        return array();
    }

    $user_ids = find_supplier($product);

    $arrAdd1 = explode(' ', $order->get_shipping_address_1());

    $result = array();
    foreach ($user_ids as $user_id) {
        $customer = new WC_Customer($user_id);

        $matchCount = 0;

        write_log(__FILE__ . ':' . __LINE__ . ' ' . $customer->get_display_name());

        if ($customer->get_billing_state() == $order->get_billing_state()) {
            write_log(__FILE__ . ':' . __LINE__ . ' get_billing_state ' . $customer->get_billing_state() . ' = ' . $order->get_billing_state());
            $matchCount += 5;
        }

        if ($customer->get_billing_city() == $order->get_billing_city()) {
            write_log(__FILE__ . ':' . __LINE__ . ' get_billing_city ' . $customer->get_billing_city() . ' = ' . $order->get_billing_city());
            $matchCount += 10;
        }

        if ($customer->get_billing_address_2() == $order->get_billing_address_2()) {
            write_log(__FILE__ . ':' . __LINE__ . ' get_billing_address_2 ' . $customer->get_billing_address_2() . ' = ' . $order->get_billing_address_2());
            $matchCount += 15;
        }

        if ($matchCount == 30) {
            $matchCountAdd1 = 0;
            foreach ($arrAdd1 as $char) {
                if (empty($char)) {
                    continue;
                }

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
            $result[$user_id] = $matchCount;
        }
    }

    if (!empty($result) && sizeof($result) > 1) {
        asort($result);
    }
    write_log(__FILE__ . ': function smart_find_handler $result');
    write_log($result);
    return $result;
}

/**
 * Get All supplier
 *
 * @param WC_Product $product
 * @return array
 */
function find_supplier(WC_Product $product): array
{
    write_log(__FILE__ . ': function find_supplier');
    $supplier_ids = get_field('cac_nha_cung_cap', $product->get_id());
    $suppliers = get_posts(array(
        'post_type' => 'supplier',
        'meta_query' => array(
            'key' => 'supplier_user',
            'value' => $supplier_ids,
            'compare' => 'IN',
        )
    ));

    $supplier_ids = array();
    write_log(__FILE__ . ':' . __LINE__ . ' size of $suppliers ' . sizeof($suppliers));
    foreach ($suppliers as $supplier) {
        $supplier_products = get_field('supplier_products', $supplier->ID);
        if (empty($supplier_products)) {
            write_log(__FILE__ . ':' . __LINE__ . ' empty $supplier_products');
            continue;
        }

        foreach ($supplier_products as $product_sku) {
            if ($product_sku['supplier_product']->ID != $product->get_id()) {
                write_log(__FILE__ . ':' . __LINE__ . ' ' . $product_sku['supplier_product']->ID . '<>' . $product->get_id());
                continue;
            }

            if ($product_sku['supplier_num_sku'] == 0) {
                write_log(__FILE__ . ':' . __LINE__ . ' empty supplier_num_sku');
                continue;
            }

            $supplier_ids[] = get_field('supplier_user', $supplier->ID);
        }
    }

    write_log(__FILE__ . ':' . __LINE__);
    write_log($supplier_ids);
    return $supplier_ids;
}

/**
 * Custom shipping and  building in admin user manager
 * Action to create supplier
 *
 */
function supplier_admin_fields($admin_fields)
{
    write_log(__FILE__ . ': function supplier_admin_fields');
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

/**
 * Get customer districts
 *
 * @param WC_Customer $customer
 * @return array
 */
function get_default_districts(WC_Customer $customer): array
{
    write_log(__FILE__ . ': function get_default_districts');

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

/**
 * Get customer wards
 *
 * @param WC_Customer $customer
 * @return array
 */
function get_default_wards(WC_Customer $customer): array
{
    write_log(__FILE__ . ': function get_default_wards');

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

/**
 * Update shipping and bulling when crate customer by rest API
 *
 * @param $user_id
 * @return void
 */
function update_user_info_bulling_shipping($user_id)
{
    write_log(__FILE__ . ': function update_user_info_bulling_shipping');

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

/**
 * Remove cancel order when status is in-progress
 *
 * @param $actions
 * @param $order
 * @return mixed
 */
function remove_myaccount_orders_cancel_button($actions, WC_Order $order)
{
    if ($order->get_status() == 'processing') {
        unset($actions['cancel']);
    }
    unset($actions['pay']);

    return $actions;
}

/**
 * @return void
 */
function add_stock_histories()
{

}

