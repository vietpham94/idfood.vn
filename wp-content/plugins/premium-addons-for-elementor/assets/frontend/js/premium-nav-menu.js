(function ($) {

    var PremiumNavMenuHandler = function ($scope, $) {
        var $menuContainer = $scope.find('.premium-mobile-menu'),
            $menuToggler = $scope.find('.premium-hamburger-toggle'),
            $hamMenuCloser = $scope.find('.premium-mobile-menu-close'),
            settings = $scope.find('.premium-nav-widget-container').data('settings');

        if ('slide' === settings.mobileLayout || 'slide' === settings.mainLayout) {
            $scope.addClass('premium-ver-hamburger-menu');
        }

        $hamMenuCloser.on('click', function () {
            $scope.find('.premium-mobile-menu-outer-container, .premium-nav-slide-overlay').removeClass('premium-vertical-toggle-open');
        });

        $menuToggler.on('click', function () {
            if ('slide' === settings.mobileLayout || 'slide' === settings.mainLayout) {
                $scope.find('.premium-mobile-menu-outer-container, .premium-nav-slide-overlay').addClass('premium-vertical-toggle-open');
            } else {
                $menuContainer.toggleClass('premium-active-menu');
            }

            $menuToggler.toggleClass('premium-toggle-opened');
        });

        checkBreakPoint(settings);

        $menuContainer.find('.premium-nav-menu-item.menu-item-has-children, .premium-mega-nav-item').on('click', function (e) {

            e.preventDefault();
            $(this).toggleClass('premium-active-menu');

            // make sure the parent node is always open whenever the child node is opened.
            $(this).parents('.premium-nav-menu-item.menu-item-has-children').toggleClass('premium-active-menu');
        });

        // $(window).on('resize', function() {
        //     checkBreakPoint(settings);
        // });

        $(document).on('click', '.premium-nav-slide-overlay', function () {
            $scope.find('.premium-mobile-menu-outer-container, .premium-nav-slide-overlay').removeClass('premium-vertical-toggle-open');
        });

        function checkBreakPoint(settings) {
            if (settings.breakpoint >= $(window).width()) {

                $scope.addClass('premium-hamburger-menu');
                $scope.find('.premium-active-menu').removeClass('premium-active-menu');
            } else {
                $scope.removeClass('premium-hamburger-menu');
                $scope.find('.premium-vertical-toggle-open').removeClass('premium-vertical-toggle-open');
            }
        }
    };

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction('frontend/element_ready/premium-nav-menu.default', PremiumNavMenuHandler);
    });

})(jQuery);