<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.6.0
 */

defined('ABSPATH') || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked wc_print_notices - 10
 */
do_action('woocommerce_before_single_product');

if (post_password_required()) {
    echo get_the_password_form(); // WPCS: XSS ok.
    return;
}

// prev & next post -------------------

$single_post_nav = array(
    'hide-header' => false,
    'hide-sticky' => false,
);

$opts_single_post_nav = mfn_opts_get('prev-next-nav');
if (is_array($opts_single_post_nav)) {

    if (isset($opts_single_post_nav['hide-header'])) {
        $single_post_nav['hide-header'] = true;
    }
    if (isset($opts_single_post_nav['hide-sticky'])) {
        $single_post_nav['hide-sticky'] = true;
    }

}

$post_prev = get_adjacent_post(false, '', true);
$post_next = get_adjacent_post(false, '', false);
$shop_page_id = wc_get_page_id('shop');


// post classes -----------------------

$classes = array();

if (mfn_opts_get('share') == 'hide-mobile') {
    $classes[] = 'no-share-mobile';
} elseif (!mfn_opts_get('share')) {
    $classes[] = 'no-share';
}

if (mfn_opts_get('share-style')) {
    $classes[] = 'share-' . mfn_opts_get('share-style');
}

$single_product_style = mfn_opts_get('shop-product-style');
$classes[] = $single_product_style;
$classes[] = 'container-fluid';

// translate
$translate['all'] = mfn_opts_get('translate') ? mfn_opts_get('translate-all', 'Show all') : __('Show all', 'betheme');

?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class($classes, $product); ?>>

    <?php
    // single post navigation | sticky
    if (!$single_post_nav['hide-sticky']) {
        echo mfn_post_navigation_sticky($post_prev, 'prev', 'icon-left-open-big');
        echo mfn_post_navigation_sticky($post_next, 'next', 'icon-right-open-big');
    }
    ?>

    <?php
    // single post navigation | header
    if (!$single_post_nav['hide-header']) {
        echo mfn_post_navigation_header($post_prev, $post_next, $shop_page_id, $translate);
    }
    ?>

    <?php
    require_once(get_theme_file_path('/woocommerce/content-single-product/customer-address.php'));
    require_once(get_theme_file_path('/woocommerce/content-single-product/provider-info.php'));
    ?>

    <?php
    global $product;

    $gallery_image_ids = $product->get_gallery_image_ids();
    $attachment_image = wp_get_attachment_image_url($product->get_image_id(), 'single-post-thumbnail');

    //    $cityVn = get_the_customer_city();
    //    $customerCity = strtoupper(vn_to_str($cityVn));

    $providers_number_str = get_post_meta(get_the_ID(), 'cac_nha_cung_cap');
    $providers = get_the_providers($providers_number_str, empty($customerCity) ? null : $customerCity);
    $cac_nha_cung_cap_khu_vuc = $providers[0];
    $cityVn = $providers[1];
    ?>

    <div class="product_wrapper clearfix">
        <div class="row">
            <!-- Product gallery images -->
            <div class="col-12 col-lg-6 col-xl-5">
                <?php if (!empty($attachment_image)): ?>
                    <div id="viewProduct" class="view-product border"
                         style="background-image: url(<?php echo $attachment_image; ?>)"
                         title="<?php get_the_title(); ?>">
                        <a href="<?php echo $attachment_image; ?>" rel="lightbox" data-type="image"
                           style="padding: 45% 0;">
                            <img src="<?php echo $attachment_image; ?>" alt="<?php get_the_title(); ?>"
                                 style="visibility: hidden"/>
                        </a>
                    </div>
                <?php endif; ?>
                <div class="row product-gallery-nav">
                    <?php if (!empty($attachment_image)): ?>
                        <div class="col-3 cursor-pointer image-item mt-3">
                            <img src="<?php echo $attachment_image; ?>" alt="<?php get_the_title(); ?>"
                                 class="active border" data-img-index="0"
                                 title="<?php get_the_title(); ?>"/>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($gallery_image_ids)): ?>
                        <?php foreach ($gallery_image_ids as $key => $gallery_image_id): ?>
                            <div class="col-3 cursor-pointer image-item mt-3">
                                <img src="<?php echo wp_get_attachment_url($gallery_image_id); ?>"
                                     alt="<?php get_the_title(); ?>" class="border" data-img-index="<?= $key + 1; ?>"
                                     title="<?php get_the_title(); ?>"/>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product data & action -->
            <div class="col-12 col-lg-6 col-xl-7">
                <div class="row product-data-action">
                    <!-- product name-->
                    <div class="col-12">
                        <h1 class="product_title"><?php the_title() ?></h1>
                    </div>

                    <!-- meta data -->
                    <div class="col-12">
                        <p class="average-rating pr-2"><?php echo $product->get_average_rating(); ?></p>
                        <div class="star-rating" role="img">
                            <?php $average_rating_percent = ($product->get_average_rating() / 5) * 100; ?>
                            <span style="width:<?php echo $average_rating_percent ?>%;"></span>
                        </div>
                        <p class="slap">|</p>
                        <p class="product-review-count">
                            <a href="#comment-list">
                                <?php echo $product->get_review_count(); ?>&nbsp;
                                <?= __('đánh giá') ?>
                            </a>
                        </p>
                        <p class="slap">|</p>
                        <p class="product-sold-count">
                            &nbsp;<?= __('đã bán') ?>&nbsp;
                            <?php echo get_post_meta($product->get_id(), 'total_sales', true); ?>
                        </p>
                        <div class="icons d-inline-flex">
                            <p class="heading-title d-inline-block mb-0">;<?= __('Chia sẻ') ?>: &nbsp;&nbsp;&nbsp;</p>
                            <a target="popup" class="facebook d-inline-flex"
                               href="https://www.facebook.com/sharer/sharer.php?u=<?php echo get_the_permalink(); ?>">
                                <i class="fab fa-facebook" aria-hidden="true"></i>
                            </a>
                            <a target="popup" class="linkedin d-inline-flex"
                               href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo get_the_permalink(); ?>">
                                <i class="fab fa-linkedin" aria-hidden="true" style="color: #007ab9;"></i>
                            </a>
                            <a target="popup" class="pinterest d-inline-flex"
                               href="https://www.pinterest.com/pin/create/button/?url=<?php echo get_the_permalink(); ?>&media=<?php echo $attachment_image; ?>">
                                <i class="fab fa-pinterest" aria-hidden="true" style="color: #bd081c;"></i>
                            </a>
                        </div>
                        <hr class="mt-2"/>
                    </div>

                    <!-- product price -->
                    <div class="col-12">
                        <h2 class="heading-title mb-0"><?= __('Giá bán'); ?></h2>
                        <p class="price">
                            <?php echo $product->get_price_html(); ?>
                        </p>
                    </div>

                    <!-- Add to Cart    -->
                    <div class="col-12">
                        <h2 class="heading-title"><?= __('Số lượng'); ?></h2>
                        <form class="cart" action="<?php echo get_the_permalink(); ?>" method="post"
                              enctype="multipart/form-data" id="add-to-card-form">
                            <div class="quantity">
                                <input type="number" step="1" min="1"
                                       max="<?php echo get_post_meta(get_the_ID(), '_stock', true) ?>"
                                       name="quantity" value="1" inputmode="numeric" autocomplete="off">
                            </div>
                            <input type="hidden" id="provider" name="provider" value="">
                            <button type="submit" name="add-to-cart"
                                    value="<?php echo get_the_ID(); ?>"
                                    class="single-pay-now-button button ml-0 ml-md-2">
                                <?= __('Mua ngay') ?>
                            </button>
                        </form>
                    </div>

                    <!-- Buy directly / Register to distribute  -->
                    <div class="col-12">
                        <div class="row">
                            <div class="col-12 col-md-6 buy-in-location mb-3 mb-md-0">
                                <h4 class="title"><?= __('Bạn muốn mua hàng trực tiếp?'); ?></h4>
                                <p class="number-location mb-1">
                                    <?= __('Có tất cả'); ?>
                                    <?= sizeof($cac_nha_cung_cap_khu_vuc); ?>
                                    <?= __('điểm bán lẻ tại khu vực'); ?> <?= $cityVn ?>.
                                </p>
                                <a href="#diem-ban-gan-day"><?= __('Xem chi tiết'); ?></a>
                            </div>
                            <div class="col-12 col-md-6 border-md-left">
                                <?php echo do_shortcode('[elementor-template id="1452"]'); ?>
                            </div>
                        </div>
                        <hr class="d-none d-md-block"/>
                    </div>

                    <!-- Quality standards   -->
                    <div class="col-12 mt-3 mt-md-0 quality-standards">
                        <div class="row">
                            <div class="col-12 col-md-6">
                                <p class="heading-title mr-3"><?= __('Tiêu chuẩn chất lượng'); ?></p>
                                <?php $danh_sach_tem = get_field('danh_sach_tem', get_the_ID()); ?>
                                <?php if (!empty($danh_sach_tem)): ?>
                                    <?php foreach ($danh_sach_tem as $tem): ?>
                                        <img src="<?php echo $tem['image']; ?>"
                                             class="attachment-medium size-medium p-1" alt="" loading="lazy"/>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 col-md-6 border-md-left htx">
                                <?php $htx = get_field('htx', get_the_ID()); ?>
                                <?php if (!empty($htx["link"])): ?>
                                    <?php if (empty($htx["title"])) {
                                        $htx_page = get_page_by_path($htx["link"]);
                                    } ?>
                                    <p class="heading-title mb-1 mt-4 mt-xl-0 mr-2 mr-md-0">
                                        <?= __('Sản phẩm của'); ?>
                                    </p>
                                    <a href="<?php echo $htx["link"]; ?>" target="_blank">
                                        <i class="premium-title-icon far fa-hand-point-right" aria-hidden="true"></i>
                                        <span class="premium-title-text">
                                            <?= empty($htx['title']) ? $htx_page->post_title : $htx['title']; ?>
                                        </span>
                                    </a>
                                <?php endif; ?>

                                <?php $process_certificate = get_field('process_certificate', get_the_ID()); ?>
                                <a class="button mt-3" target="_blank"
                                   href="<?= empty($process_certificate) ? '#' : $process_certificate; ?>">
                                    <i aria-hidden="true" class="fas fa-qrcode"></i>
                                    <span><?= __('Quy trình sản xuất'); ?></span>
                                </a>

                                <?php $link_truy_xuat_nguon_goc = get_field('link_truy_xuat_nguon_goc', get_the_ID()); ?>
                                <a class="button mt-3 d-none" target="_blank"
                                   href="<?= empty($link_truy_xuat_nguon_goc) ? '#' : $link_truy_xuat_nguon_goc; ?>">
                                    <i aria-hidden="true" class="fas fa-qrcode"></i>
                                    <span><?= __('Tra cứu nguồn gốc sản phẩm'); ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4 product-summary">
            <div class="col-12 col-lg-9">
                <div class="product-content">
                    <h2 class="title"><?= __('Giới thiệu sản phẩm'); ?></h2>
                    <div class="summary-content">
                        <?php the_content(); ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" class="show-more-content button"><?= __('Xem thêm'); ?></a>
                    </div>
                </div>

                <div class="product-location mt-4" id="diem-ban-gan-day">
                    <h2 class="title">
                        <?= __('Điểm bán lẻ tại khu vực'); ?>
                        <?= $cityVn; ?>(<?= sizeof($cac_nha_cung_cap_khu_vuc); ?>)
                    </h2>

                    <?php foreach ($cac_nha_cung_cap_khu_vuc as $diem_ban_le): ?>
                        <div class="location">
                            <p class="location-name mb-1">
                                <?= $diem_ban_le->display_name; ?>
                            </p>
                            <p class="location-position mb-1 d-none"><?= __('Khoảng cách: 1,5km'); ?></p>
                            <p class="location-address">
                                <?= $diem_ban_le->address_1; ?><br/>
                                <?= __('Điện thoại'); ?>:
                                <?php echo $diem_ban_le->billing['phone']; ?>
                            </p>
                            <a class="button buy-now"
                               data-provider="<?= $diem_ban_le->id; ?>"><?= __('Mua hàng'); ?></a>
                            <a class="button direct" target="_blank"
                               href="https://www.google.co.uk/maps/place/<?= formatSearchAddressGoogle($diem_ban_le->address_1); ?>">
                                <?= __('Chỉ đường'); ?>
                            </a>
                            <hr>
                        </div>
                    <?php endforeach; ?>

                    <?php if (sizeof($cac_nha_cung_cap_khu_vuc) > 2): ?>
                        <div class="text-center mt-3">
                            <a href="#" class="show-more-location button"><?= __('Xem thêm'); ?></a>
                        </div>
                    <?php endif; ?>

                </div>

                <?php require_once(get_theme_file_path('/woocommerce/content-single-product/comments-template.php')); ?>
            </div>

            <div class="col-12 col-lg-3 d-none d-lg-block related-product-area">
                <h3 class="related-product-title"><?= __('CÓ THỂ BẠN QUAN TÂM'); ?></h3>
                <?php echo do_shortcode('[recent_products per_page="10" columns="1" orderby="rand" order="rand"]'); ?>
            </div>
        </div>
    </div>
</div>

<?php do_action('woocommerce_after_single_product'); ?>
