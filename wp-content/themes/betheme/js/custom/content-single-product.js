jQuery(document).ready(function ($) {

    $(".view-product").mousemove(function (e) {
        let lw = $(this).width();
        let lh = $(this).height();

        let offset = $(this).offset();
        let relX = e.pageX - offset.left;
        let relY = e.pageY - offset.top;

        if (relX < 0) {
            relX = 0
        }

        if (relX > lw) {
            relX = lw
        }

        if (relY < 0) {
            relY = 0
        }

        if (relY > lh) {
            relY = lh
        }

        let zx = -(0.5 * relX);
        let zy = -(0.5 * relY);

        $(this).css('background-position', (zx) + "px " + (zy) + "px");
    });

    $(".view-product").mouseout(function (e) {
        $(this).css('background-position', '');
    });

    $(".image-item img").click(function (e) {
        let imgUrl = $(this).attr('src');
        $(".view-product img").attr('src', imgUrl);
        $(".view-product a").attr('href', imgUrl);
        $(".view-product").css('background-image', 'url(' + imgUrl + ')');
        $(".image-item img").removeClass("active");
        $(this).addClass("active");
    });

    $(".show-more-content").click(function (e) {
        e.preventDefault();
        $(".summary-content").toggleClass('show');
        if ($(".summary-content").hasClass('show')) {
            $(this).html('Thu gọn');
        } else {
            $(this).html('Xem thêm');
        }
    });

    let viewProductHeight = $(".view-product img").width();
    $(".view-product").css("max-height", viewProductHeight + "px");
    $(".view-product").css("min-height", viewProductHeight + "px");

    let imageItemWidth = $(".image-item").width();
    $(".image-item").css("max-height", imageItemWidth + "px");
    $(".image-item").css("min-height", imageItemWidth + "px");

    $(".buy-now").click(function (e) {
        $('#provider').val($(this).data('provider'));
        $('.single-pay-now-button').trigger('click');
    });
});