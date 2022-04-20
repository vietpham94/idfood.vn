<?php
function get_the_providers($providers_number_str, $customerCity) {
    if (!empty($providers_number_str)) {
        $providers_number_num = intval($providers_number_str[0]);
    } else {
        $providers_number_num = 0;
    }
    $cac_nha_cung_cap = [];
    if ($providers_number_num > 0) {
        for ($i = 0; $i < $providers_number_num; $i++) {
            $key_cc = 'cac_nha_cung_cap_' . $i . '_nha_cung_cap';
            $cac_nha_cung_cap[] = array('nha_cung_cap' => get_post_meta(get_the_ID(), $key_cc)[0]);
        }
    }

    $cac_nha_cung_cap_khu_vuc = array();
    foreach ($cac_nha_cung_cap as $item) {
        $provider = new WC_Customer($item['nha_cung_cap']);
        if ($customerCity == strtoupper(vn_to_str($provider->get_billing_city()))) {
            $cac_nha_cung_cap_khu_vuc[] = $provider;
            $cityVn = $provider->get_billing_city();
        }
    }

    return [$cac_nha_cung_cap_khu_vuc, $cityVn];
}