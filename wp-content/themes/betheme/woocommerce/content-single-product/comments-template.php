<?php global $product; ?>

<?php if (get_option('woocommerce_review_rating_verification_required') === 'no' || wc_customer_bought_product('', get_current_user_id(), $product->get_id())) : ?>
    <div class=" comment-form-area mt-4">
        <h2 class="title"><?php __('Viết đánh giá') ?></h2>
        <div id="reviews" class="woocommerce-Reviews">
            <div id="review_form_wrapper">
                <div id="review_form">
                    <?php
                    $commenter = wp_get_current_commenter();
                    $comment_form = array(
                        'title_reply' => have_comments() ? __('Add a review', 'woocommerce') : sprintf(__('Đánh giá của bạn rất hữu ích với cộng đồng. Đánh giá ngay thôi nào.', 'woocommerce'), get_the_title()),
                        'fields' => array(
                            'author' => '<p class="comment-form-author">' . '<label for="author">' . esc_html__('Name', 'woocommerce') . '&nbsp;    </label> ' .
                                '<input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '"     size="30" aria-required="true" required /></p>',
                            'email' => '<p class="comment-form-email"><label for="email">' . esc_html__('Email', 'woocommerce') . '&nbsp;</label> ' .
                                '<input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" size="30" aria-required="true" required /></p>',
                        ),
                        'label_submit' => __('Submit', 'woocommerce'),
                        'logged_in_as' => '',
                        'comment_field' => '',
                    );

                    if ($account_page_url = wc_get_page_permalink('myaccount')) {
                        $comment_form['must_log_in'] = '<p class="must-log-in">' . sprintf(__('Bạn cần phải <a href="%s">đăng nhập</a> để có thể bình luận.', 'woocommerce'), esc_url($account_page_url)) . '</p>';
                    }

                    if (get_option('woocommerce_enable_review_rating') === 'yes') {
                        $comment_form['comment_field'] = '
                                        <div class="comment-form-rating">
                                            <label for="rating">' . esc_html__('Điểm đánh giá', 'woocommerce') . '<span style="color: red;">*</span></label>
                                            <select name="rating" id="rating" aria-required="true" required>
                                                <option value="">' . esc_html__('Rate&hellip;', 'woocommerce') . '</option>
                                                <option value="5">' . esc_html__('Perfect', 'woocommerce') . '</option>
                                                <option value="4">' . esc_html__('Good', 'woocommerce') . '</option>
                                                <option value="3">' . esc_html__('Average', 'woocommerce') . '</option>
                                                <option value="2">' . esc_html__('Not that bad', 'woocommerce') . '</option>
                                                <option value="1">' . esc_html__('Very poor', 'woocommerce') . '</option>
                                            </select>
                                        </div>';
                    }

                    $comment_form['comment_field'] .= '
                                        <p class="comment-form-comment">
                                            <textarea id="comment" name="comment" cols="45" rows="8" required placeholder="Nội dung đánh giá(*)"></textarea>
                                        </p>';

                    comment_form(apply_filters('woocommerce_product_review_comment_form_args', $comment_form));
                    ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="comment-list" class="comment-list mt-4">
    <?php
    $comments = get_comments('post_id=' . $product->get_id());
    $number_comments = get_comments_number(get_the_ID());
    ?>

    <h2 class="title pb-3">
        <?= __('Đánh giá sản phẩm'); ?> (<?php echo $product->get_review_count(); ?>)
    </h2>

    <?php if (sizeof($comments) == 0): ?>
        <p style="font-style: italic;">
            <?= __('Hãy đặt mua sản phẩm ngay và trở thành người đầu tiên đánh giá sản phẩm này!'); ?>
        </p>
    <?php else: ?>
        <?php foreach ($comments as $comment): ?>
            <?php
            $userAvartar = get_avatar_url($comment->comment_author_email);
            $user = get_user_by_email($comment->comment_author_email);
            $rating = get_comment_meta($comment->comment_ID, 'rating', true);
            ?>

            <div class="row comment-item mb-2 mb-lg-0">
                <div class="col-2 avatar m-auto">
                    <img style="width: auto; height: 100%; border-radius: 50%; padding: 10%;" alt=""
                         src="<?php echo $userAvartar; ?>" srcset="<?php echo $userAvartar; ?>"
                         class="avatar avatar-26 photo" height="26" width="26" loading="lazy">
                </div>

                <div class="col-10 comment-data m-auto">
                    <p class="name mb-0 font-weight-bold"><?php echo $user->display_name; ?></p>
                    <div class="rating d-inline-flex m-auto">
                        <p class="stars selected d-inline-flex m-auto pr-3">
                        <span class="d-inline-flex">
                        <a class="star-1<?= $rating == '1' ? ' active' : ''; ?>" href="#" style="color: #f2643d;">1</a>
                        <a class="star-2<?= $rating == '2' ? ' active' : ''; ?>" href="#" style="color: #f2643d;">2</a>
                        <a class="star-3<?= $rating == '3' ? ' active' : ''; ?>" href="#" style="color: #f2643d;">3</a>
                        <a class="star-4<?= $rating == '4' ? ' active' : ''; ?>" href="#" style="color: #f2643d;">4</a>
                        <a class="star-5<?= $rating == '5' ? ' active' : ''; ?>" href="#" style="color: #f2643d;">5</a>
                        </span>
                        </p>
                        <p class="date d-inline-flex m-auto">
                            <?php echo date_format(date_create($comment->comment_date), "d/m/Y"); ?>
                        </p>
                    </div>
                    <p class="comment-content mb-0"><?php echo $comment->comment_content; ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($product->get_review_count() > 5): ?>
        <div class="view-all-comments text-center">
            <a class="button show-all-comments"><?= __('Xem thêm'); ?></a>
        </div>
    <?php endif; ?>
</div>