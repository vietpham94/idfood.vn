<?php
// Register admin menu
add_action('admin_menu', 'mfpd_create_menu');
function register_firebase_settings()
{
    register_setting('firebase-token-setting', 'firebase_token');
}

function mfpd_create_menu()
{
    add_menu_page('Firebase', 'Firebase Settings', 'administrator', __FILE__, 'firebase_settings_page', '', 100);
    add_action('admin_init', 'register_firebase_settings');
}

function firebase_settings_page()
{
    ?>
    <div class="wrap">
        <h2>Firebase setting</h2>
        <?php if (isset($_GET['settings-updated'])) { ?>
            <div id="message" class="updated">
                <p><strong><?php _e('Settings saved.') ?></strong></p>
            </div>
        <?php } ?>

        <form method="post" action="options.php">
            <?php settings_fields('firebase-token-setting'); ?>
            <h3><?= __('Token'); ?></h3>
            <input type="text" name="firebase_token" value="<?php echo get_option('firebase_token'); ?>"/>
            <?php submit_button(); ?>
        </form>
    </div>
<?php }

// Push notification to client device
function push_notification(int $handler_user_id, int $order_id, $notification = array())
{
    $apiAccessKey = get_option('firebase_token');
    if (empty($apiAccessKey)) {
        return;
    }

    if (empty($notification)) {
        $notification = array(
            'title' => 'Đơn đặt hàng mới',
            'body' => 'Bạn có đơn một đặt hàng mới. Ấn vào để xử lý.',
            "sound" => "default",
            "click_action" => "FCM_PLUGIN_ACTIVITY"
        );
    }

    $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    $firebase_token = get_user_meta($handler_user_id, 'firebase_token', true);

    if (empty($firebase_token)) {
        return;
    }

    $fcmNotification = [
        //'registration_ids' => $tokenList, //multiple token array
        'to' => $firebase_token, //single token
        'notification' => $notification,
        "priority" => "high",
        'data' => ['order_id' => $order_id]
    ];

    $headers = array('Authorization: Bearer ' . $apiAccessKey, 'Content-Type: application/json; UTF-8');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fcmUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmNotification));
    $result = curl_exec($ch);
    write_log(__FILE__ . ':77 ' . $result);
    curl_close($ch);

    $notification = array(
        'title' => $notification['title'],
        'body' => $notification['body'],
        'receiver_id' => $handler_user_id,
        'order_id' => $order_id
    );
    notifications_install_data($notification);

    return $result;
}

// Save notification to database
function notifications_install_data($data)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'notifications';
    $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name));

    if ($wpdb->get_var($query) == $table_name) {
        $wpdb->insert($table_name, $data);
    }
}
