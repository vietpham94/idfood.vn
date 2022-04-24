<?php

class WC_REST_Custom_Controller
{
    /**
     * You can extend this class with
     * WP_REST_Controller / WC_REST_Controller / WC_REST_Products_V2_Controller / WC_REST_CRUD_Controller etc.
     * Found in packages/woocommerce-rest-api/src/Controllers/
     */
    protected $namespace = 'wc/v3';

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/my-orders',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_orders'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/my-customers',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_customers'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/fb/subscribe',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'subscribeFirebaseToken'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/fb/unsubscribe',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'unsubscribeFirebaseToken'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/notifications',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_notifications'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/notifications/(?P<id>\d+)',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'update_notification_status'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/update-order/(?P<id>\d+)',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'update_order'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/dns',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_dns'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/provinces',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_provinces'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/cities',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_cities'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/wards',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_wards'),
            )
        );
    }

    public function get_orders(WP_REST_Request $request)
    {
        if (get_current_user_id() == 0) {
            return new WP_Error(
                'woocommerce_rest_cannot_view',
                'Xin lỗi, xảy ra lỗi xác thực thông tin người dùng. Vui lòng kiểm tra thông tin và đăng nhập lại.',
                array(
                    'status' => 401,
                )
            );
        }

        $args = array(
            'post_type' => 'shop_order',
            'post_status' => ($request->get_param('status') && $request->get_param('status') != 'any') ? [$request->get_param('status')] : array_keys(wc_get_order_statuses()),
            'posts_per_page' => 10
        );

        if (!empty($request->get_param('page'))) {
            $offset = ($request->get_param('page') - 1) * 10;
            $args['offset'] = $offset;
        }

        if (!empty($request->get_param('customer'))) {
            $args['customer_id'] = $request->get_param('customer');
        } else {
            $args['meta_key'] = 'handler_user_id';
            $args['meta_value'] = get_current_user_id();
        }

        if (!empty($request->get_param('after')) && !empty($request->get_param('before'))) {
            $args['date_query'] = array(
                'after' => date('Y-m-d', strtotime($request->get_param('after'))),
                'before' => date('Y-m-d', strtotime($request->get_param('before')))
            );
        }

        if (!empty($request->get_param('after')) && empty($request->get_param('before'))) {
            $args['date_query'] = array(
                'after' => date('Y-m-d', strtotime($request->get_param('after')))
            );
        }

        if (empty($request->get_param('after')) && !empty($request->get_param('before'))) {
            $args['date_query'] = array(
                'before' => date('Y-m-d', strtotime($request->get_param('before')))
            );
        }

        if (!empty($request->get_param('search'))) {
            if (is_numeric($request->get_param('search'))) {
                $args['p'] = $request->get_param('search');
            } else if (!empty(get_search_product_ids($request->get_param('search')))) {
                $product_ids = get_search_product_ids($request->get_param('search'));
            } else if (empty(get_search_product_ids($request->get_param('search')))) {
                return array();
            }
        }

        $loop = wc_get_orders($args);

        $orders = array();
        foreach ($loop as $itemLoop) {
            $order = wc_get_order($itemLoop->get_id());
            $orderData = $order->get_data();
            $orderData['line_items'] = [];

            if (!empty($product_ids)) {
                $flagFilterProduct = false;
            } else {
                $flagFilterProduct = true;
            }

            foreach ($order->get_items() as $item_key => $item) {
                $product = $item->get_product();
                $imageLink = wp_get_attachment_thumb_url($product->get_image_id());
                $productData = $item->get_data();
                $productData['image_link'] = $imageLink;
                $productData['price'] = $product->get_price();
                $productData['product_id'] = $item->get_product_id();
                $productData['_woo_uom_input'] = $product->get_meta('_woo_uom_input');
                $orderData['line_items'][] = $productData;
                if (!empty($product_ids) && in_array($item->get_product_id(), $product_ids)) {
                    $flagFilterProduct = true;
                }
            }
            if ($flagFilterProduct) {
                $orders[] = $orderData;
            }
        }

        return $orders;
    }

    public function subscribeFirebaseToken(WP_REST_Request $request)
    {
        if (get_current_user_id() == 0) {
            return new WP_Error(
                'woocommerce_rest_cannot_view',
                'Xin lỗi, xảy ra lỗi xác thực thông tin người dùng. Vui lòng kiểm tra thông tin và đăng nhập lại.',
                array(
                    'status' => 401,
                )
            );
        }

        if (empty($request->get_param('token'))) {
            return new WP_Error(
                'firebase_rest_cannot_subscribe',
                'Xin lỗi, không thể đăng ký được token',
                array(
                    'status' => 500,
                )
            );
        }

        try {
            if (!empty(get_user_meta(get_current_user_id(), 'firebase_token', true)) &&
                get_user_meta(get_current_user_id(), 'firebase_token', true) != $request->get_param('token')) {
                $result = update_user_meta(get_current_user_id(), 'firebase_token', $request->get_param('token'));
            } else if (empty(get_user_meta(get_current_user_id(), 'firebase_token', true))) {
                $result = add_user_meta(get_current_user_id(), 'firebase_token', $request->get_param('token'));
            } else {
                return true;
            }
        } catch (Exception $e) {
            return new WP_Error(
                'firebase_rest_cannot_subscribe',
                $e->getMessage(),
                array(
                    'status' => 500,
                )
            );
        }

        return $result;
    }

    public function unsubscribeFirebaseToken(WP_REST_Request $request)
    {
        if (get_current_user_id() == 0) {
            return new WP_Error(
                'woocommerce_rest_cannot_view',
                'Xin lỗi, xảy ra lỗi xác thực thông tin người dùng. Vui lòng kiểm tra thông tin và đăng nhập lại.',
                array(
                    'status' => 401,
                )
            );
        }

        if (get_user_meta(get_current_user_id(), 'firebase_token')) {
            $result = update_user_meta(get_current_user_id(), 'firebase_token', '');
        } else {
            $result = true;
        }

        if ($result != false) {
            return new WP_REST_Response();
        } else {
            return new WP_Error(
                'firebase_rest_cannot_subscribe',
                'Xin lỗi, không thể đăng ký được token',
                array(
                    'status' => 500,
                )
            );
        }
    }

    public function get_notifications(WP_REST_Request $request)
    {
        if (get_current_user_id() == 0) {
            return new WP_Error(
                'woocommerce_rest_cannot_view',
                'Xin lỗi, xảy ra lỗi xác thực thông tin người dùng. Vui lòng kiểm tra thông tin và đăng nhập lại.',
                array(
                    'status' => 401,
                )
            );
        }

        $limit = 10;
        $offset = 0;
        if (!empty($request->get_param('page'))) {
            $offset = ($request->get_param('page') - 1) * $limit;
        }

        global $wpdb;
        $notifications_table_name = $wpdb->prefix . 'notifications';
        $result = $wpdb->get_results("SELECT * FROM `$notifications_table_name` WHERE receiver_id=" . get_current_user_id() . " ORDER BY time DESC LIMIT $offset, $limit", "ARRAY_A");
        $notifications = array();
        foreach ($result as $item_notification) {
            $order = wc_get_order($item_notification["order_id"]);
            if (!empty($order)) {
                $orderData = $order->get_data();
                $orderData['line_items'] = array();
                foreach ($order->get_items() as $item) {
                    $product = $item->get_product();
                    if (!empty($product)) {
                        $imageLink = wp_get_attachment_thumb_url($product->get_image_id());
                        $productData = $item->get_data();
                        $productData['image_link'] = $imageLink;
                        $productData['price'] = $product->get_price();
                        $productData['product_id'] = $item->get_product_id();
                        $productData['_woo_uom_input'] = $product->get_meta('_woo_uom_input');
                        $orderData['line_items'][] = $productData;
                    }
                }
                $item_notification["order"] = $orderData;
                $notifications[] = $item_notification;
            }
        }

        return $notifications;
    }

    public function update_notification_status($data)
    {
        if (get_current_user_id() == 0) {
            return new WP_Error(
                'notifications_rest_cannot_access',
                'Xin lỗi, xảy ra lỗi xác thực thông tin người dùng. Vui lòng kiểm tra thông tin và đăng nhập lại.',
                array(
                    'status' => 401,
                )
            );
        }

        $notification_id = $data['id'];
        if (empty($notification_id)) {
            return new WP_Error(
                'notifications_regist_lost_data',
                'Xin lỗi, không thể xác định được thông báo cần cập nhật',
                array(
                    'status' => 400,
                )
            );
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'notifications';
        $dbData = array("status" => true);
        return $wpdb->update($table_name, $dbData, array('order_id' => $notification_id));
    }

    public function update_order($data)
    {
        if (get_current_user_id() == 0) {
            return new WP_Error(
                'woocommerce_rest_cannot_view',
                'Xin lỗi, xảy ra lỗi xác thực thông tin người dùng. Vui lòng kiểm tra thông tin và đăng nhập lại.',
                array(
                    'status' => 401,
                )
            );
        }

        $order_id = $data['id'];
        if (empty($order_id)) {
            return new WP_Error(
                'order_update_lost_data',
                'Xin lỗi, không thể xác định được đơn hàng cần cập nhật',
                array(
                    'status' => 400,
                )
            );
        }

        $order = wc_get_order($order_id);
        if (empty($order)) {
            return new WP_Error(
                'order_update_lost_data',
                'Xin lỗi, không thể xác định được đơn hàng cần cập nhật',
                array(
                    'status' => 400,
                )
            );
        }

        if ($order->get_status() == 'completed') {
            return false;
        }

        if ($order->get_status() == 'cancelled') {
            return false;
        }

        if ($order->get_status() == 'on-hold') {
            return false;
        }

        $orderData = $data->get_param('data');
        $result = $order->update_status($orderData['status']);
        update_field('thong_tin_giao_nhan', $orderData['acf']['thong_tin_giao_nhan'], $order_id);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    public function get_customers(WP_REST_Request $request)
    {
        if (get_current_user_id() == 0) {
            return new WP_Error(
                'woocommerce_rest_cannot_view',
                'Xin lỗi, xảy ra lỗi xác thực thông tin người dùng. Vui lòng kiểm tra thông tin và đăng nhập lại.',
                array(
                    'status' => 401,
                )
            );
        }

        $provider_id = get_current_user_id();

        $offset = 0;
        if (!empty($request->get_param('page'))) {
            $offset = ($request->get_param('page') - 1) * 10;
        }

        $query = new WP_User_Query(apply_filters(
            'woocommerce_customer_search_customers',
            array(
                'fields' => 'ID',
                'offset' => $offset,
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'create_by',
                        'value' => $provider_id,
                        'compare' => 'LIKE',
                    ),
                    array(array(
                        'relation' => 'OR',
                        array(
                            'key' => 'last_name',
                            'value' => $request->get_param('search'),
                            'compare' => 'LIKE',
                        ),
                        array(
                            'key' => 'first_name',
                            'value' => $request->get_param('search'),
                            'compare' => 'LIKE',
                        )
                    )),
                ),
                'meta_query'
            )
        ));
        $customer_ids = $query->get_results();

        $customers = array();
        foreach ($customer_ids as $user_id) {
            $customer = new WC_Customer($user_id);
            $last_order = $customer->get_last_order();
            $customer_data = array(
                'id' => $customer->get_id(),
                'created_at' => $this->format_datetime($customer->get_date_created() ? $customer->get_date_created()->getTimestamp() : 0), // API gives UTC times.
                'last_update' => $this->format_datetime($customer->get_date_modified() ? $customer->get_date_modified()->getTimestamp() : 0), // API gives UTC times.
                'email' => $customer->get_email(),
                'first_name' => $customer->get_first_name(),
                'last_name' => $customer->get_last_name(),
                'username' => $customer->get_username(),
                'role' => $customer->get_role(),
                'last_order_id' => is_object($last_order) ? $last_order->get_id() : null,
                'last_order_date' => is_object($last_order) ? $this->format_datetime($last_order->get_date_created() ? $last_order->get_date_created()->getTimestamp() : 0) : null, // API gives UTC times.
                'orders_count' => $customer->get_order_count(),
                'total_spent' => wc_format_decimal($customer->get_total_spent(), 2),
                'avatar_url' => $customer->get_avatar_url(),
                'billing' => array(
                    'first_name' => $customer->get_billing_first_name(),
                    'last_name' => $customer->get_billing_last_name(),
                    'company' => $customer->get_billing_company(),
                    'address_1' => $customer->get_billing_address_1(),
                    'address_2' => $customer->get_billing_address_2(),
                    'city' => $customer->get_billing_city(),
                    'state' => $customer->get_billing_state(),
                    'postcode' => $customer->get_billing_postcode(),
                    'country' => $customer->get_billing_country(),
                    'email' => $customer->get_billing_email(),
                    'phone' => $customer->get_billing_phone(),
                ),
                'shipping' => array(
                    'first_name' => $customer->get_shipping_first_name(),
                    'last_name' => $customer->get_shipping_last_name(),
                    'company' => $customer->get_shipping_company(),
                    'address_1' => $customer->get_shipping_address_1(),
                    'address_2' => $customer->get_shipping_address_2(),
                    'city' => $customer->get_shipping_city(),
                    'state' => $customer->get_shipping_state(),
                    'postcode' => $customer->get_shipping_postcode(),
                    'country' => $customer->get_shipping_country(),
                ),
                'meta_data' => $customer->get_meta_data(),
            );
            $customers[] = $customer_data;
        }

        return $customers;
    }

    public function get_dns(WP_REST_Request $request)
    {
        if (empty($request->get_param('prefix'))) {
            return new WP_Error(
                'dns_get_domain',
                'Lỗi không phát hiện được dns',
                array(
                    'status' => 404,
                )
            );
        }

        $posts = get_posts(array(
            'numberposts' => 1,
            'post_type' => 'dns-domain',
            'meta_key' => 'prefix',
            'meta_value' => $request->get_param('prefix')
        ));

        if (empty($posts)) {
            return new WP_Error(
                'dns_get_domain',
                'Lỗi không phát hiện được dns',
                array(
                    'status' => 404,
                )
            );
        }

        $domain = get_field('domain', $posts[0]->ID);
        if (empty($domain)) {
            return new WP_Error(
                'dns_get_domain',
                'Lỗi không phát hiện được dns',
                array(
                    'status' => 404,
                )
            );
        }

        $result = array(
            'prefix' => $request->get_param('prefix'),
            'domain' => $domain
        );

        return $result;
    }

    public function get_provinces(WP_REST_Request $request)
    {
        global $tinh_thanhpho;
        if (empty($request->get_param('id'))) {
            $result = array();
            foreach ($tinh_thanhpho as $key => $title) {
                $result[] = array(
                    'key' => $key,
                    'title' => $title
                );
            }
            return $result;
        } else {
            return array($request->get_param('id') => $tinh_thanhpho[$request->get_param('id')]);
        }
    }

    public function get_cities(WP_REST_Request $request): array
    {
        if (empty($request->get_param('provinceId'))) {
            return new WP_Error(
                'Validation',
                'Vui lòng truyền lên mã của tỉnh cần lấy dánh sách quận huyện',
                array(
                    'status' => 404,
                )
            );
        }

        include 'cities/quan_huyen.php';

        if (empty($request->get_param('id'))) {
            $result = array();
            foreach ($quan_huyen as $value) {
                if ($value['matp'] != $request->get_param('provinceId')) {
                    continue;
                }

                $result[] = $value;
            }

            return $result;
        } else {
            foreach ($quan_huyen as $value) {
                if ($value['matp'] == $request->get_param('provinceId') && $value['maqh'] == $request->get_param('id')) {
                    return $value;
                }
            }
        }

        return array();
    }

    public function get_wards(WP_REST_Request $request): array
    {
        if (empty($request->get_param('cityId'))) {
            return new WP_Error(
                'Validation',
                'Vui lòng truyền lên mã của tỉnh cần lấy dánh sách quận huyện',
                array(
                    'status' => 404,
                )
            );
        }

        include 'cities/xa_phuong_thitran.php';

        if (empty($request->get_param('id'))) {
            $result = array();
            foreach ($xa_phuong_thitran as $value) {
                if ($value['maqh'] != $request->get_param('cityId')) {
                    continue;
                }

                $result[] = $value;
            }

            return $result;
        } else {
            foreach ($xa_phuong_thitran as $value) {
                if ($value['maqh'] == $request->get_param('cityId') && $value['xaid'] == $request->get_param('id')) {
                    return $value;
                }
            }
        }

        return array();
    }

    private function findObjectByKey($key, $value, $data = array())
    {
        foreach ($data as $element) {
            if ($element[$key] == $value) {
                return $element;
            }
        }
        return false;
    }

    private function format_datetime($timestamp, $convert_to_utc = false, $convert_to_gmt = false)
    {
        if ($convert_to_gmt) {
            if (is_numeric($timestamp)) {
                $timestamp = date('Y-m-d H:i:s', $timestamp);
            }

            $timestamp = get_gmt_from_date($timestamp);
        }

        if ($convert_to_utc) {
            $timezone = new DateTimeZone(wc_timezone_string());
        } else {
            $timezone = new DateTimeZone('UTC');
        }

        try {

            if (is_numeric($timestamp)) {
                $date = new DateTime("@{$timestamp}");
            } else {
                $date = new DateTime($timestamp, $timezone);
            }

            // convert to UTC by adjusting the time based on the offset of the site's timezone
            if ($convert_to_utc) {
                $date->modify(-1 * $date->getOffset() . ' seconds');
            }
        } catch (Exception $e) {

            $date = new DateTime('@0');
        }

        return $date->format('Y-m-d\TH:i:s\Z');
    }
}

add_filter('woocommerce_rest_api_get_rest_namespaces', 'wc_custom_api');
function wc_custom_api($controllers): array
{
    $controllers['wc/v3']['custom'] = 'WC_REST_Custom_Controller';
    return $controllers;
}
