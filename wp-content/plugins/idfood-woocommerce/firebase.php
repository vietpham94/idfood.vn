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

// Form setting firebase token
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

// Push order notification to Supplier
function push_order_notification(int $handler_user_id, int $order_id, $notification = array())
{
    if (empty($notification)) {
        $notification = array(
            'title' => 'Đơn đặt hàng mới',
            'body' => 'Bạn có đơn một đặt hàng mới. Ấn vào để xử lý.',
            "sound" => "default",
            "click_action" => "FCM_PLUGIN_ACTIVITY"
        );
    }

    $firebase_token = get_user_meta($handler_user_id, 'firebase_token', true);
    if (empty($firebase_token)) {
        return;
    }

    if (empty($order_id)) {
        return;
    }

    $receiver_tokens = array($firebase_token);
    $data_attached = array('order_id' => $order_id);

    push_notifications($receiver_tokens, $notification, $data_attached);

    $notification_data = array(
        'post_title' => $notification['title'],
        'post_type' => 'notification',
        'post_content' => $notification['body']
    );
    $notification_id = wp_insert_post($notification_data);

    if ($notification_id) {
        update_field('order_id', $order_id, [$notification_id]);
        update_field('receiver_id', $handler_user_id, [$notification_id]);
        update_field('status', 0, [$notification_id]);
        update_field('type', 'order', [$notification_id]);
    }
}

// Push notifications Firebase
function push_notifications(array $receiver_tokens, $notification = array(), $data_attached = array())
{
    $apiAccessKey = get_option('firebase_token');
    if (empty($apiAccessKey)) {
        return;
    }

    if (empty($notification)) {
        return;
    }

    if (empty($receiver_tokens)) {
        return;
    }

    $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    $fcmNotification = [
        'notification' => $notification,
        'priority' => "high",
        'data' => $data_attached
    ];

    if (sizeof($receiver_tokens) == 1) {
        $fcmNotification['to'] = $receiver_tokens[0];
    } else {
        $fcmNotification['registration_ids'] = $receiver_tokens;
    }

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
    return $result;
}
