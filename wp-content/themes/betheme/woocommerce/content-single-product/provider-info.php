<?php
function get_the_providers($providers_number_str, $customerCity)
{

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

    global $tinh_thanhpho;

    $cac_nha_cung_cap_khu_vuc = array();
    foreach ($cac_nha_cung_cap as $item) {

        $provider = new WC_Customer($item['nha_cung_cap']);
        if (empty($provider)) {
            continue;
        }

        $suppliers = get_posts(array(
            'post_type' => 'supplier',
            'meta_key' => 'supplier_user',
            'meta_value' => $item['nha_cung_cap'],
        ));

        if (empty($suppliers)) {
            continue;
        }

        $supplier = current($suppliers);

        $products_stock = get_field('supplier_products', $supplier->ID);
        if (empty($products_stock)) {
            write_log(__FILE__ . ':' . __LINE__ . ' products_stock is empty');
            continue;
        }

        $inStockProduct = 0;
        foreach ($products_stock as $key => $stock_row) {
            if (!empty($stock_row['supplier_product']->ID) && $stock_row['supplier_product']->ID != get_the_ID()) {
                continue;
            }

            if (is_numeric($stock_row['supplier_product']) && $stock_row['supplier_product'] != get_the_ID()) {
                continue;
            }

            $inStockProduct = $stock_row['supplier_num_sku'];
        }


        if (!empty($customerCity) & $customerCity == strtoupper(vn_to_str($provider->get_billing_state()))) {
            $cityVn = $tinh_thanhpho[$provider->get_billing_state()];
        }

        $arrProvider = $provider->get_data();
        $address = $provider->get_billing_address_1() . ', ' . get_name_village($provider->get_billing_address_2()) . ', ' . get_name_district($provider->get_billing_city()) . ', ' . $tinh_thanhpho[$provider->get_billing_state()];
        $arrProvider['billing']['address_1'] = $address;
        $arrProvider['stock'] = $inStockProduct;
        $cac_nha_cung_cap_khu_vuc[] = $arrProvider;
    }

    return [$cac_nha_cung_cap_khu_vuc, !empty($cityVn) ? $tinh_thanhpho[$cityVn] : ''];
}

function get_name_district($id = '')
{
    include(WP_PLUGIN_DIR . '/idfood-woocommerce/cities/quan_huyen.php');

    $id_quan = sprintf("%03d", intval($id));
    if (!empty($quan_huyen) && is_array($quan_huyen)) {
        $nameQuan = search_in_array($quan_huyen, 'maqh', $id_quan);
        $nameQuan = isset($nameQuan[0]['name']) ? $nameQuan[0]['name'] : '';
        return $nameQuan;
    }
    return false;
}

function get_name_village($id = '')
{
    include(WP_PLUGIN_DIR . '/idfood-woocommerce/cities/xa_phuong_thitran.php');

    $id_xa = sprintf("%05d", intval($id));
    if (!empty($xa_phuong_thitran) && is_array($xa_phuong_thitran)) {
        $name = search_in_array($xa_phuong_thitran, 'xaid', $id_xa);
        $name = isset($name[0]['name']) ? $name[0]['name'] : '';
        return $name;
    }
    return false;
}

function search_in_array($array, $key, $value)
{
    $results = array();

    if (is_array($array)) {
        if (isset($array[$key]) && $array[$key] == $value) {
            $results[] = $array;
        } elseif (isset($array[$key]) && is_serialized($array[$key]) && in_array($value, maybe_unserialize($array[$key]))) {
            $results[] = $array;
        }
        foreach ($array as $subarray) {
            $results = array_merge($results, search_in_array($subarray, $key, $value));
        }
    }

    return $results;
}