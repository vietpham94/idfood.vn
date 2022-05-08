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

        register_rest_route(
            $this->namespace,
            '/suppliers',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_suppliers'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/idf-stock',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_idfood_stock_prroduct'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/supplier-stock',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'update_supplier_stock'),
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

        $user_data = get_userdata(get_current_user_id());

        $args = array(
            'post_type' => 'shop_order',
            'post_status' => ($request->get_param('status') && $request->get_param('status') != 'any') ? [$request->get_param('status')] : array_keys(wc_get_order_statuses()),
            'posts_per_page' => 10,
        );

        if (!empty($request->get_param('page'))) {
            $offset = ($request->get_param('page') - 1) * 10;
            $args['offset'] = $offset;
        }

        if (!empty($request->get_param('customer'))) {
            $args['customer_id'] = $request->get_param('customer');
            if ($request->get_param('customer') != get_current_user_id()) {
                $args['meta_key'] = 'handler_user_id';
                $args['meta_value'] = get_current_user_id();
                if (in_array('supplier', $user_data->roles)) {
                    $args['created_via'] = 'checkout';
                }
            }
        } else {
            $args['meta_key'] = 'handler_user_id';
            $args['meta_value'] = get_current_user_id();
            if (in_array('supplier', $user_data->roles)) {
                $args['created_via'] = 'checkout';
            }
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
            } else {
                $product_ids = $this->get_search_product_ids($request->get_param('search'));
                if (empty($product_ids)) {
                    return array();
                }
            }
        }

        $loop = wc_get_orders($args);

        $orders = array();
        foreach ($loop as $itemLoop) {
            $orderData = $itemLoop->get_data();
            $orderData['line_items'] = [];

            if (!empty($product_ids)) {
                $flagFilterProduct = false;
            } else {
                $flagFilterProduct = true;
            }

            foreach ($itemLoop->get_items() as $line_item) {
                $product = $line_item->get_product();

                $productData = $line_item->get_data();
                $productData['image_link'] = wp_get_attachment_thumb_url($product->get_image_id());
                $productData['price'] = $product->get_price();
                $productData['product_id'] = $product->get_id();
                $productData['_woo_uom_input'] = $product->get_meta('_woo_uom_input');

                $orderData['line_items'][] = $productData;

                if (!empty($product_ids) && in_array($line_item->get_product_id(), $product_ids)) {
                    $flagFilterProduct = true;
                }
            }

            $orderData['acf'] = get_fields($itemLoop->get_id());
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

        $offset = 0;
        if (!empty($request->get_param('page'))) {
            $offset = ($request->get_param('page') - 1) * 10;
        }

        $offset = array(
            'post_type' => 'notification',
            'numberposts' => 10,
            'offset' => $offset,
            'meta_key' => 'receiver_id',
            'meta_value' => get_current_user_id()
        );

        $notifications = get_posts($offset);
        $notificationList = array();
        if (!empty($notifications)) {
            foreach ($notifications as $notification) {
                $order = wc_get_order(get_field('order_id', $notification->ID));
                $orderData = $order ? $order->get_data() : false;
                if ($orderData) {
                    $orderData['line_items'] = array();
                    $line_items = $order->get_items();
                    if (!empty($line_items)) {
                        foreach ($line_items as $line_item) {
                            $product = $line_item->get_product();
                            $lineItemData = $line_item->get_data();
                            $lineItemData['_woo_uom_input'] = $product ? $product->get_meta('_woo_uom_input') : '';
                            $orderData['line_items'][] = $lineItemData;
                        }
                    }
                }
                $notificationList[] = array(
                    id => $notification->ID,
                    title => $notification->post_title,
                    body => $notification->post_content,
                    receiver_id => get_field('receiver_id', $notification->ID),
                    order_id => get_field('order_id', $notification->ID),
                    status => get_field('status', $notification->ID),
                    type => get_field('type', $notification->ID),
                    order => $orderData,
                    time => $notification->post_date
                );
            }
        }

        return $notificationList;
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

        return update_field('status', true, $notification_id);
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
        if (!empty($orderData['acf']['refunded'])) {
            update_field('refunded', $orderData['acf']['refunded'], $order_id);
        }
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
                        'key' => 'create_by_users',
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
            $customers[] = $this->fillDataCustomer(new WC_Customer($user_id));
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
        $quan_huyen = array();
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

        $xa_phuong_thitran = array();
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

    public function get_suppliers(WP_REST_Request $request)
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
            'post_type' => 'supplier',
            'meta_key' => 'supplier_user',
            'meta_value' => get_current_user_id()
        );

        $suppliers = get_posts($args);

        if (sizeof($suppliers) > 0) {
            $supplier = (array)$suppliers[0];
            $supplier['stock'] = array();
            $stocks = get_field('supplier_products', $suppliers[0]->ID);
            foreach ($stocks as $stock) {
                $data = (array)$stock;
                $data['_woo_uom_input'] = get_post_meta($stock['supplier_product']->ID, '_woo_uom_input');
                $data['product_image'] = get_the_post_thumbnail_url($stock['supplier_product']->ID);
                $supplier['stock'][] = $data;
            }
            return $supplier;
        }

        return false;
    }

    public function get_idfood_stock_prroduct(WP_REST_Request $request)
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
            'post_type' => 'idf_stock',
        );

        if (!empty($request->get_param('products'))) {
            $args['meta_key'] = 'product';
            $args['meta_value'] = $request->get_param('products');
            $args['meta_compare'] = 'IN';
        }

        $idf_stock = get_posts($args);

        $result = array();
        if (sizeof($idf_stock) > 0) {
            foreach ($idf_stock as $stock) {
                $stock = get_fields($stock->ID);
                $product = wc_get_product($stock['product']);
                $stock['product'] = array(
                    ID => $product->get_id(),
                    post_title => $product->get_name()
                );

                $_woo_uom_input = $product->get_meta('_woo_uom_input');
                $stock['_woo_uom_input'] = $_woo_uom_input;
                $stock['product_image'] = wp_get_attachment_image_url($product->get_image_id());

                $result[] = $stock;
            }
        }

        return $result;
    }

    public function update_supplier_stock(WP_REST_Request $request)
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

        $supplier_product = $request->get_param('supplier_product');
        if (empty($supplier_product)) {
            return new WP_Error(
                'update_supplier_stock_lost_data',
                'Xin lỗi, không thể xác định được sản phẩm cần cập nhật.',
                array(
                    'status' => 400,
                )
            );
        }

        $user_data = get_userdata(get_current_user_id());
        if (in_array('shop_manager', $user_data->roles)) {
            return $this->update_idf_stock($request);
        }

        $args = array(
            'post_type' => 'supplier',
            'meta_key' => 'supplier_user',
            'meta_value' => get_current_user_id()
        );
        $suppliers = get_posts($args);

        if (empty($suppliers)) {
            return new WP_Error(
                'update_supplier_stock_lost_data',
                'Xin lỗi, không thể xác định được thông tin kho hàng của bạn.',
                array(
                    'status' => 400,
                )
            );
        }

        $supplier = current($suppliers);
        $products_stock = get_field('supplier_products', $supplier->ID);
        $supplier_num_sku = $request->get_param('supplier_num_sku');

        $stock_row_index = searchSupplierProductsStock($products_stock, $supplier_product);

        if ($stock_row_index == -1) {
            return new WP_Error(
                'order_update_lost_data',
                'Xin lỗi, không thể xác định được kho hàng cần cập nhật.',
                array(
                    'status' => 400,
                )
            );
        }

        $stock_row = $products_stock[$stock_row_index];
        $stock_row['supplier_product'] = $supplier_product;
        $stock_row['supplier_num_sku'] = $stock_row['supplier_num_sku'] + $supplier_num_sku;
        $products_stock[$stock_row_index] = $stock_row;

        update_field('supplier_products', $products_stock, $supplier->ID);
        return $stock_row;
    }

    public function update_idf_stock(WP_REST_Request $request)
    {
        $idf_product = $request->get_param('supplier_product');

        $args = array(
            'post_type' => 'idf_stock',
        );

        if (!empty($request->get_param('products'))) {
            $args['meta_key'] = 'product';
            $args['meta_value'] = $idf_product;
            $args['meta_compare'] = 'IN';
        }

        $idf_stock = get_posts($args);
        $stock = current($idf_stock);

        if (empty($stock)) {
            return new WP_Error(
                'update_supplier_stock_lost_data',
                'Xin lỗi, không thể xác định được thông tin kho hàng của bạn.',
                array(
                    'status' => 400,
                )
            );
        }
        $supplier_num_sku = $request->get_param('supplier_num_sku');
        $stock_num = get_field('stock', $stock->ID);
        $stock_num = $stock_num + $supplier_num_sku;

        update_field('stock', $stock_num, $stock->ID);
        return array('supplier_num_sku' => $stock_num);
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

    private function get_search_product_ids($search)
    {
        $args = array('post_type' => 'product', 'numberposts' => -1, 's' => $search, 'fields' => 'ids');
        $products = get_posts($args);
        write_log($products);
        return $products;
    }

    private function fillDataCustomer(WC_Customer $customer)
    {
        $last_order = $customer->get_last_order();

        $address = array(
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
        );

        $last_order = array(
            'id' => $customer->get_id(),
            'role' => $customer->get_role(),
            'created_at' => $this->format_datetime($customer->get_date_created() ? $customer->get_date_created()->getTimestamp() : 0), // API gives UTC times.
            'last_update' => $this->format_datetime($customer->get_date_modified() ? $customer->get_date_modified()->getTimestamp() : 0), // API gives UTC times.
            'email' => $customer->get_email(),
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'username' => $customer->get_username(),
            'last_order_id' => is_object($last_order) ? $last_order->get_id() : null,
            'last_order_date' => is_object($last_order) ? $this->format_datetime($last_order->get_date_created() ? $last_order->get_date_created()->getTimestamp() : 0) : null, // API gives UTC times.
            'orders_count' => $customer->get_order_count(),
            'total_spent' => wc_format_decimal($customer->get_total_spent(), 2),
            'avatar_url' => $customer->get_avatar_url(),
            'billing' => $address,
            'shipping' => $address,
            'meta_data' => $customer->get_meta_data(),
        );
        return $last_order;
    }

}

add_filter('woocommerce_rest_api_get_rest_namespaces', 'wc_custom_api');
function wc_custom_api($controllers): array
{
    $controllers['wc/v3']['custom'] = 'WC_REST_Custom_Controller';
    return $controllers;
}
