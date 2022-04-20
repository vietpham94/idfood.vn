jQuery(document).ready(function ($) {
    $('#shipping_state').change(function (e) {
        loadCities('#shipping_city', $(this).val(), '#shipping_address_2');
    });

    $('#billing_state').change(function (e) {
        loadCities('#billing_city', $(this).val(), '#billing_address_2');
    });

    $('#shipping_city').change(function (e) {
        loadWards('#shipping_address_2', $(this).val());
    });

    $('#billing_city').change(function (e) {
        loadWards('#billing_address_2', $(this).val());
    });

    function loadCities(appendId, stateId, address2Id) {
        $.ajax({
            type: 'post',
            dataType: 'json',
            url: '/wp-admin/admin-ajax.php',
            data: {action: 'load_diagioihanhchinh', matp: stateId},
            context: this,
            beforeSend: function () {
                $(address2Id).find('option').remove();
                $(appendId).find('option').remove();

                let option = new Option('Đang tải dữ liệu...', null);
                $(appendId).append(option);
            },
            success: function (response) {
                if (response.success) {
                    let listQH = response.data;
                    $(appendId).find('option').remove();
                    let option = new Option('Chọn Quận / Huyện', null);
                    $(appendId).append(option);
                    $.each(listQH, function (index, value) {
                        let newState = new Option(value.name, value.maqh);
                        $(appendId).append(newState);
                    });
                }
            }
        });
    }

    function loadWards(appendId, cityId) {
        $.ajax({
            type: 'post',
            dataType: 'json',
            url: '/wp-admin/admin-ajax.php',
            data: {action: 'load_diagioihanhchinh', maqh: cityId},
            context: this,
            beforeSend: function () {
                $(appendId).find('option').remove();

                let option = new Option('Đang tải dữ liệu...', null);
                $(appendId).append(option);
            },
            success: function (response) {
                if (response.success) {
                    let listPX = response.data;
                    $(appendId).find('option').remove();
                    let option = new Option('Chọn Xã/Phường', null);
                    $(appendId).append(option);
                    $.each(listPX, function (index, value) {
                        let newWards = new Option(value.name, value.maqh);
                        $(appendId).append(newWards);
                    });
                }
            }
        });
    }

});