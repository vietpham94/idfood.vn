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
            '/cities',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_cities'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/add-my-customer',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_customer'),
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

    public function get_cities(): array
    {
        $cities = array(
            "HANOI" => "Hà Nội",
            "HOCHIMINH" => "Hồ Chí Minh",
            "ANGIANG" => "An Giang",
            "BACGIANG" => "Bắc Giang",
            "BACKAN" => "Bắc Kạn",
            "BACLIEU" => "Bạc Liêu",
            "BACNINH" => "Bắc Ninh",
            "BARIAVUNGTAU" => "Bà Rịa - Vũng Tàu",
            "BENTRE" => "Bến Tre",
            "BINHDINH" => "Bình Định",
            "BINHDUONG" => "Bình Dương",
            "BINHPHUOC" => "Bình Phước",
            "BINHTHUAN" => "Bình Thuận",
            "CAMAU" => "Cà Mau",
            "CANTHO" => "Cần Thơ",
            "CAOBANG" => "Cao Bằng",
            "DAKLAK" => "Đắk Lắk",
            "DAKNONG" => "Đắk Nông",
            "DANANG" => "Đà Nẵng",
            "DIENBIEN" => "Điện Biên",
            "DONGNAI" => "Đồng Nai",
            "DONGTHAP" => "Đồng Tháp",
            "GIALAI" => "Gia Lai",
            "HAGIANG" => "Hà Giang",
            "HAIDUONG" => "Hải Dương",
            "HAIPHONG" => "Hải Phòng",
            "HANAM" => "Hà Nam",
            "HATINH" => "Hà Tĩnh",
            "HAUGIANG" => "Hậu Giang",
            "HOABINH" => "Hòa Bình",
            "HUNGYEN" => "Hưng Yên",
            "KHANHHOA" => "Khánh Hòa",
            "KIENGIANG" => "Kiên Giang",
            "KONTUM" => "Kon Tum",
            "LAICHAU" => "Lai Châu",
            "LAMDONG" => "Lâm Đồng",
            "LANGSON" => "Lạng Sơn",
            "LAOCAI" => "Lào Cai",
            "LONGAN" => "Long An",
            "NAMDINH" => "Nam Định",
            "NGHEAN" => "Nghệ An",
            "NINHBINH" => "Ninh Bình",
            "NINHTHUAN" => "Ninh Thuận",
            "PHUTHO" => "Phú Thọ",
            "PHUYEN" => "Phú Yên",
            "QUANGBINH" => "Quảng Bình",
            "QUANGNAM" => "Quảng Nam",
            "QUANGNGAI" => "Quảng Ngãi",
            "QUANGNINH" => "Quảng Ninh",
            "QUANGTRI" => "Quảng Trị",
            "SOCTRANG" => "Sóc Trăng",
            "SONLA" => "Sơn La",
            "TAYNINH" => "Tây Ninh",
            "THAIBINH" => "Thái Bình",
            "THAINGUYEN" => "Thái Nguyên",
            "THANHHOA" => "Thanh Hóa",
            "THUATHIENHUE" => "Thừa Thiên Huế",
            "TIENGIANG" => "Tiền Giang",
            "TRAVINH" => "Trà Vinh",
            "TUYENQUANG" => "Tuyên Quang",
            "VINHLONG" => "Vĩnh Long",
            "VINHPHUC" => "Vĩnh Phúc",
            "YENBAI" => "Yên Bái",
        );

        $result = array();
        foreach ($cities as $key => $value) {
            $result[] = array(
                "value" => $key,
                "title" => $value
            );
        }

        return $result;
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

        global $wpdb;
        $table_customer = $wpdb->prefix . 'wc_customer_lookup';
        $table_usermeta = $wpdb->prefix . 'usermeta';
        $provider_id = get_current_user_id();

        $offset = 0;
        if (!empty($request->get_param('page'))) {
            $offset = ($request->get_param('page') - 1) * 10;
        }

        $sql_customers = "SELECT * FROM $table_customer WHERE provider_id=$provider_id ";

        if (!empty($request->get_param('search'))) {
            $search = $request->get_param('search');
            $sql_customers .= "AND (username LIKE '%$search%' OR first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR address LIKE '%$search%')";
        }

        $args['meta_key'] = 'handler_user_id';
        $args['meta_value'] = get_current_user_id();
        $loop = wc_get_orders($args);
        $customer_ids = array();
        foreach ($loop as $itemLoop) {
            $order = wc_get_order($itemLoop->get_id());
            if ($order->get_customer_id() > 0) {
                $customer_ids[] = $order->get_customer_id();
            }
        }

        if (sizeof($customer_ids) > 0) {
            $include_ids = implode(",", $customer_ids);
            $sql_customers .= "OR customer_id IN($include_ids)";
        }

        $sql_customers .= " LIMIT 10 OFFSET $offset";

        write_log(__FILE__ . ':520 ' . $sql_customers);

        $customer_lockup = $wpdb->get_results($sql_customers, "ARRAY_A");
        $customers = array();
        foreach ($customer_lockup as $customer) {
            if (!empty($customer['user_id'])) {
                $user_id = $customer['user_id'];
                $sql_usermeta = "SELECT * FROM $table_usermeta WHERE user_id=$user_id";
                $meta_user = $wpdb->get_results($sql_usermeta, "ARRAY_A");
                $user_address = $this->findObjectByKey('meta_key', 'billing_address_1', $meta_user);
                $user_city = $this->findObjectByKey('meta_key', 'billing_city', $meta_user);
                $customer['address'] = ($user_address ? $user_address['meta_value'] : '') . ', ' . ($user_city ? $user_city['meta_value'] : '');
                $user_phone = $this->findObjectByKey('meta_key', 'billing_phone', $meta_user);
                $customer['phone'] = $user_phone ? $user_phone['meta_value'] : '';
                $customer['provider_id'] = $provider_id;
            }
            $customers[] = $customer;
        }

        return $customers;
    }

    public function create_customer(WP_REST_Request $request)
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

        global $wpdb;
        $data = $request->get_params();
        $data['provider_id'] = get_current_user_id();
        $table_customer = $wpdb->prefix . 'wc_customer_lookup';
        $wpdb->insert($table_customer, $data);
        return $data;
    }

    public function get_dns(WP_REST_Request $request) {
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
            'numberposts'	=> 1,
            'post_type'		=> 'dns-domain',
            'meta_key'		=> 'prefix',
            'meta_value'	=> $request->get_param('prefix')
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

    private function findObjectByKey($key, $value, $data = array())
    {
        foreach ($data as $element) {
            if ($element[$key] == $value) {
                return $element;
            }
        }
        return false;
    }
}

add_filter('woocommerce_rest_api_get_rest_namespaces', 'wc_custom_api');
function wc_custom_api($controllers): array
{
    $controllers['wc/v3']['custom'] = 'WC_REST_Custom_Controller';
    return $controllers;
}
