(function($) {
    'use strict';

    $(document).ready(function() {
        initCouponWidget();
    });

    function initCouponWidget() {
        var $widgets = $('.vt-coupon-widget');

        $widgets.each(function() {
            var $widget = $(this);
            var $form = $widget.find('.vt-coupon-form');
            var $list = $widget.find('.vt-coupon-list');

            // Initialize form
            if ($form.length) {
                initForm($form, $list);
            }

            // Load coupons
            if ($list.length) {
                loadCoupons($list);
            }
        });
    }

    function initForm($form, $list) {
        var $discountType = $form.find('#vt-coupon-discount-type');
        var $percentField = $form.find('.vt-discount-percent');
        var $fixedField = $form.find('.vt-discount-fixed');
        var $duration = $form.find('#vt-coupon-duration');
        var $durationMonths = $form.find('.vt-duration-months-row');
        var $submitBtn = $form.find('.vt-coupon-submit');
        var $message = $form.find('.vt-coupon-message');
        var originalBtnText = $submitBtn.text();

        // Toggle discount type fields
        $discountType.on('change', function() {
            if ($(this).val() === 'percent') {
                $percentField.show();
                $fixedField.hide();
            } else {
                $percentField.hide();
                $fixedField.show();
            }
        });

        // Toggle duration months field
        $duration.on('change', function() {
            if ($(this).val() === 'repeating') {
                $durationMonths.show();
            } else {
                $durationMonths.hide();
            }
        });

        // Form submit
        $form.on('submit', function(e) {
            e.preventDefault();

            var discountType = $discountType.val();
            var name = $form.find('#vt-coupon-name').val().trim();
            var code = $form.find('#vt-coupon-code').val().trim();
            var percentOff = $form.find('#vt-coupon-percent-off').val();
            var amountOff = $form.find('#vt-coupon-amount-off').val();
            var duration = $duration.val();
            var durationMonths = $form.find('#vt-coupon-duration-months').val();
            var maxRedemptions = $form.find('#vt-coupon-max-redemptions').val();
            var redeemBy = $form.find('#vt-coupon-redeem-by').val();
            var firstTimeOnly = $form.find('#vt-coupon-first-time').is(':checked');

            // Validate
            if (!name) {
                showMessage($message, voxelCouponWidget.i18n.error, 'error');
                return;
            }

            if (discountType === 'percent' && (!percentOff || percentOff < 1 || percentOff > 100)) {
                showMessage($message, 'Please enter a valid percent (1-100)', 'error');
                return;
            }

            if (discountType === 'fixed' && (!amountOff || amountOff <= 0)) {
                showMessage($message, 'Please enter a valid amount', 'error');
                return;
            }

            if (duration === 'repeating' && (!durationMonths || durationMonths < 1)) {
                showMessage($message, 'Please enter duration months', 'error');
                return;
            }

            // Disable button
            $submitBtn.prop('disabled', true).text(voxelCouponWidget.i18n.creating);

            $.ajax({
                url: voxelCouponWidget.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_create_coupon',
                    nonce: voxelCouponWidget.nonce,
                    name: name,
                    code: code,
                    discount_type: discountType,
                    percent_off: percentOff,
                    amount_off: amountOff,
                    duration: duration,
                    duration_months: durationMonths,
                    max_redemptions: maxRedemptions,
                    redeem_by: redeemBy,
                    first_time_only: firstTimeOnly
                },
                success: function(response) {
                    if (response.success) {
                        showMessage($message, voxelCouponWidget.i18n.success, 'success');
                        $form[0].reset();

                        // Reset visibility
                        $percentField.show();
                        $fixedField.hide();
                        $durationMonths.hide();

                        // Reload coupons list
                        if ($list.length) {
                            loadCoupons($list);
                        }
                    } else {
                        showMessage($message, response.data.message || voxelCouponWidget.i18n.error, 'error');
                    }
                },
                error: function() {
                    showMessage($message, voxelCouponWidget.i18n.error, 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalBtnText);
                }
            });
        });
    }

    function loadCoupons($list) {
        $list.html('<div class="vt-coupon-loading">' + voxelCouponWidget.i18n.loading + '</div>');

        $.ajax({
            url: voxelCouponWidget.ajaxUrl,
            type: 'POST',
            data: {
                action: 'voxel_toolkit_get_user_coupons',
                nonce: voxelCouponWidget.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderCoupons($list, response.data.coupons);
                } else {
                    $list.html('<div class="vt-coupon-error">' + (response.data.message || voxelCouponWidget.i18n.error) + '</div>');
                }
            },
            error: function() {
                $list.html('<div class="vt-coupon-error">' + voxelCouponWidget.i18n.error + '</div>');
            }
        });
    }

    function renderCoupons($list, coupons) {
        if (!coupons || coupons.length === 0) {
            $list.html('<div class="vt-coupon-empty">' + voxelCouponWidget.i18n.noCoupons + '</div>');
            return;
        }

        var html = '';

        coupons.forEach(function(coupon) {
            var discount = coupon.percent_off
                ? coupon.percent_off + '% OFF'
                : formatCurrency(coupon.amount_off / 100, coupon.currency) + ' OFF';

            var duration = coupon.duration.charAt(0).toUpperCase() + coupon.duration.slice(1);
            if (coupon.duration === 'repeating' && coupon.duration_in_months) {
                duration += ' (' + coupon.duration_in_months + ' months)';
            }

            var redemptions = coupon.times_redeemed || 0;
            if (coupon.max_redemptions) {
                redemptions += ' / ' + coupon.max_redemptions;
            }

            var expiry = '';
            if (coupon.redeem_by) {
                var expiryDate = new Date(coupon.redeem_by * 1000);
                expiry = expiryDate.toLocaleDateString();
            }

            html += '<div class="vt-coupon-item" data-coupon-id="' + coupon.id + '">';
            html += '<div class="vt-coupon-header">';
            html += '<span class="vt-coupon-name">' + escapeHtml(coupon.name) + '</span>';
            html += '<span class="vt-coupon-discount">' + discount + '</span>';
            html += '</div>';

            html += '<div class="vt-coupon-details">';
            html += '<span class="vt-coupon-duration">Duration: ' + duration + '</span>';
            html += '<span class="vt-coupon-redemptions">Redeemed: ' + redemptions + '</span>';
            if (expiry) {
                html += '<span class="vt-coupon-expiry">Expires: ' + expiry + '</span>';
            }
            if (!coupon.valid) {
                html += '<span class="vt-coupon-invalid">Inactive</span>';
            }
            html += '</div>';

            if (coupon.promo_codes && coupon.promo_codes.length > 0) {
                html += '<div class="vt-coupon-codes">';
                coupon.promo_codes.forEach(function(promo) {
                    var promoClass = promo.active ? '' : ' inactive';
                    var firstTime = promo.first_time_transaction ? ' (First-time only)' : '';
                    html += '<span class="vt-promo-code' + promoClass + '">' + promo.code + firstTime + '</span>';
                });
                html += '</div>';
            }

            html += '<button type="button" class="vt-coupon-delete" data-coupon-id="' + coupon.id + '">Delete</button>';
            html += '</div>';
        });

        $list.html(html);

        // Bind delete handlers
        $list.find('.vt-coupon-delete').on('click', function() {
            var $btn = $(this);
            var couponId = $btn.data('coupon-id');
            var $item = $btn.closest('.vt-coupon-item');

            if (!confirm(voxelCouponWidget.i18n.confirmDelete)) {
                return;
            }

            $btn.prop('disabled', true).text(voxelCouponWidget.i18n.deleting);

            $.ajax({
                url: voxelCouponWidget.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'voxel_toolkit_delete_coupon',
                    nonce: voxelCouponWidget.nonce,
                    coupon_id: couponId
                },
                success: function(response) {
                    if (response.success) {
                        $item.fadeOut(300, function() {
                            $(this).remove();
                            // Check if list is now empty
                            if ($list.find('.vt-coupon-item').length === 0) {
                                $list.html('<div class="vt-coupon-empty">' + voxelCouponWidget.i18n.noCoupons + '</div>');
                            }
                        });
                    } else {
                        alert(response.data.message || voxelCouponWidget.i18n.error);
                        $btn.prop('disabled', false).text('Delete');
                    }
                },
                error: function() {
                    alert(voxelCouponWidget.i18n.error);
                    $btn.prop('disabled', false).text('Delete');
                }
            });
        });
    }

    function showMessage($message, text, type) {
        $message
            .removeClass('success error')
            .addClass(type)
            .text(text)
            .show();

        setTimeout(function() {
            $message.fadeOut();
        }, 5000);
    }

    function formatCurrency(amount, currency) {
        currency = (currency || voxelCouponWidget.currency || 'USD').toUpperCase();

        var symbols = {
            'USD': '$',
            'EUR': '\u20AC',
            'GBP': '\u00A3',
            'JPY': '\u00A5',
            'CAD': 'CA$',
            'AUD': 'A$'
        };

        var symbol = symbols[currency] || currency + ' ';
        return symbol + amount.toFixed(2);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
