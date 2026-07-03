jQuery(document).ready(function ($) {


    // Function to inject ASAP option
    function injectASAP() {
        if (!o100_vars.addon_enabled) {
            return;
        }

        var $dateField = $('#o100_date_deli');
        var $timeField = $('#o100_time_deli');

        if ($dateField.length && $timeField.length) {
            var selectedDateVal = $dateField.val();
            var selectedTimestamp = 0;

            // Handle timestamp value (Woo Food usually uses timestamp for value)
            if ($.isNumeric(selectedDateVal)) {
                selectedTimestamp = parseInt(selectedDateVal);
            } else {
                // Try to parse date string
                var parsedMs = Date.parse(selectedDateVal);
                if (!isNaN(parsedMs)) {
                    selectedTimestamp = parsedMs / 1000;
                } else {
                    return;
                }
            }

            // Check if selected date is Today (compare with localized today timestamp)
            var today = parseInt(o100_vars.today);
            var isToday = (selectedTimestamp >= today && selectedTimestamp < (today + 86400));

            // Conditions to SHOW ASAP:
            // 1. Date is Today
            // 2. Store has remaining hours for today (Open now OR Before opening)
            // 3. ASAP is Enabled
            // Note: o100_vars values are 1 or 0
            var hasRemainingHours = (o100_vars.has_remaining_hours == 1);
            var isAsapEnabled = (o100_vars.enable_asap == 1);
            
            // CRITICAL FIX: Only allow ASAP if there is at least one valid timeslot available for today.
            // Ignore the "ASAP" option itself and the "No time slot available" option (which has value="").
            var hasValidTimeslots = $timeField.find('option').filter(function() {
                return $(this).val() !== 'ASAP' && $(this).val() !== '' && $(this).val() !== 'no_slot';
            }).length > 0;

            if (isToday && hasRemainingHours && isAsapEnabled && hasValidTimeslots) {
                // Check if ASAP already exists
                if ($timeField.find('option[value="ASAP"]').length === 0) {
                    var asapOption = '<option value="ASAP">' + o100_vars.asap_label + '</option>';
                    $timeField.prepend(asapOption);

                    // If no option was selected or value is empty, select ASAP
                    if ($timeField.val() === '' || $timeField.val() === null) {
                        $timeField.val('ASAP');
                    }
                }
            } else {
                // Remove ASAP if conditions are not met (Closed, Not Today, or ASAP Disabled)
                $timeField.find('option[value="ASAP"]').remove();
            }
        }
    }

    // Function to inject Warning
    function injectWarning() {
        if (o100_vars.show_warning !== '1') {
            return;
        }

        // Removed 'woocommerce-info' class to remove the default icon that was overlapping
        // Added horizontal padding (20px) to prevent text from hitting the borders
        // Added background/border resets to ensure no theme styles leak in

        var messageHtml = '<div class="o100-warning-message" style="background: none !important; border: none !important; box-shadow: none !important; margin-bottom: 20px; color: red; font-weight: bold; font-size: 18px; padding: 15px 20px; line-height: 1.5;">' + o100_vars.warning_message + '</div>';

        // Inject into Checkout (Address Input Area or Additional Fields)
        $('.woocommerce-additional-fields').each(function() {
            if ($(this).find('.o100-warning-message').length === 0) {
                $(this).prepend(messageHtml);
            }
        });

        // Inject into Popup (Shipping Method Selection)
        $('.exwf-opcls-info .exwf-method-ct').each(function() {
            if ($(this).find('.o100-warning-message').length === 0) {
                $(this).prepend(messageHtml);
            }
        });
    }

    // Listen for the event triggered by Woo Food after time slots are loaded via AJAX
    $(document).on('exwf_time_delivery_slots_loaded', function () {
        injectASAP();
    });

    // Run on load
    setTimeout(function () {
        injectASAP();
        injectWarning();
    }, 500);

    // Run warning check periodically for popup
    setInterval(injectWarning, 1000);

    // ==============================================================================
    // Native Integration: Order Method Tabs
    // ==============================================================================
    $('body').on('click', '.exwf-cksp-method .exwf-method-title > div', function (e) {
        var $this = $(this);
        if ($this.hasClass('at-method')) return; // Already active
        
        $('.exwf-cksp-method .exwf-method-title > div').removeClass('at-method');
        $this.addClass('at-method');
        
        var method = $this.attr('data-method');
        
        // Add loading state
        $('.exwf-cksp-method').addClass('ex-loading').css('opacity', '0.5');

        var ajax_url = (typeof exwf_jspr !== 'undefined' && exwf_jspr.ajaxurl) ? exwf_jspr.ajaxurl : o100_vars.ajaxurl;

        $.ajax({
            type: "post",
            url: ajax_url,
            dataType: 'json',
            data: {
                action: 'o100_update_order_method',
                method: method
            },
            success: function(response) {
                // Update global state for UI restructurer
                window.o100_isDelivery = (method === 'delivery');
                
                // Trigger native WooCommerce checkout fragment refresh
                $('body').trigger('update_checkout');
                
                // Remove loading state
                $('.exwf-cksp-method').removeClass('ex-loading').css('opacity', '1');
            },
            error: function() {
                $('.exwf-cksp-method').removeClass('ex-loading').css('opacity', '1');
            }
        });
    });

    // ==============================================================================
    // Native Integration: Tipping Logic
    // ==============================================================================
    function o100_update_tipping(tip_val, tip_type) {
        $('.exwf-tip-form').addClass('ex-loading').css('opacity', '0.5');
        
        var ajax_url = (typeof exwf_jspr !== 'undefined' && exwf_jspr.ajaxurl) ? exwf_jspr.ajaxurl : o100_vars.ajaxurl;

        $.ajax({
            type: "post",
            url: ajax_url,
            dataType: 'json',
            data: {
                action: 'o100_update_tip',
                tip: tip_val,
                type: tip_type
            },
            success: function() {
                $('.exwf-tip-form').removeClass('ex-loading').css('opacity', '1');
                $('.exwf-tip-form input[name=exwf-tip-fixed]').removeClass('exwf-actip');
                $('body').trigger('update_checkout');
            },
            error: function() {
                $('.exwf-tip-form').removeClass('ex-loading').css('opacity', '1');
            }
        });
    }

    $('body').on('click', '.exwf-tip-form input[name=exwf-add-tip], .exwf-tip-form input[name=exwf-tip-fixed]', function (e) {
        e.preventDefault();
        var $form = $(this).closest('.exwf-tip-form');
        $('.exwf-tip-form input[name=exwf-tip-fixed]').removeClass('exwf-actip');
        
        var tip_type = '';
        var tip_val = '';
        
        if ($(this).hasClass('exwf-tfixed')) {
            $(this).addClass('exwf-actip');
            tip_val = $(this).attr('data-value');
            tip_type = $(this).attr('data-type');
        } else {
            tip_val = $form.find('input[name=exwf-tip]').val();
        }
        
        if (tip_val === '' || !$.isNumeric(tip_val)) {
            $form.find('.exwf-tip-error').fadeIn();
            return;
        } else {
            $form.find('.exwf-tip-error').fadeOut();
        }
        
        o100_update_tipping(tip_val, tip_type);
    });

    $('body').on('click', '.exwf-tip-form input[name=exwf-remove-tip]', function (e) {
        e.preventDefault();
        var $form = $(this).closest('.exwf-tip-form');
        $form.find('input[name=exwf-tip]').val('');
        o100_update_tipping('0', '');
    });


    // ==============================================================================
    // Native Integration: Fetch Timeslots on Date or Method Change
    // ==============================================================================
    function fetchTimeslots() {
        var $dateField = $('select[name=o100_date_deli]');
        var $timeWrapper = $('#o100_time_deli_field .woocommerce-input-wrapper');
        
        if (!$dateField.length || !$timeWrapper.length) {
            return;
        }

        var selectedDate = $dateField.val();
        if (!selectedDate) {
            return;
        }

        // Add loading state
        var $fieldWrapper = $dateField.closest('.exwf-deli-field, .exwf-take-field');
        $fieldWrapper.addClass('ex-loading').css('opacity', '0.5');

        var ajax_url = (typeof exwf_jspr !== 'undefined' && exwf_jspr.ajaxurl) ? exwf_jspr.ajaxurl : o100_vars.ajaxurl;

        $.ajax({
            type: "post",
            url: ajax_url,
            dataType: 'json',
            data: {
                action: 'exwf_time_delivery_slots',
                date: selectedDate,
                loc: '' // Location not used currently, but kept for compatibility
            },
            success: function(response) {
                $fieldWrapper.removeClass('ex-loading').css('opacity', '1');
                
                if (response && response.html_timesl) {
                    // Cache the previously selected time
                    var previousSelectedTime = $('#o100_time_deli').val();
                    
                    // Replace the HTML
                    $timeWrapper.html(response.html_timesl);
                    
                    // Restore selection if the option exists
                    if (previousSelectedTime) {
                        var $newSelect = $('#o100_time_deli');
                        if ($newSelect.find('option[value="' + previousSelectedTime + '"]').length) {
                            $newSelect.val(previousSelectedTime);
                        }
                    }

                    // Update data-time attribute
                    if (response.data_time) {
                        $('#o100_time_deli').attr('data-time', response.data_time);
                    }

                    // Trigger the event so ASAP gets injected
                    $(document).trigger('exwf_time_delivery_slots_loaded');
                }
            },
            error: function() {
                $fieldWrapper.removeClass('ex-loading').css('opacity', '1');
            }
        });
    }

    // Bind event to Date dropdown change
    $('body').on('change', 'select[name=o100_date_deli]', function() {
        fetchTimeslots();
    });

    // Fetch on initial load AND whenever WooCommerce refreshes the checkout fragment
    $(document.body).on('updated_checkout', function() {
        // Only fetch if the timeslot dropdown is empty (has only the placeholder)
        // or if it doesn't have the ASAP option yet but should.
        // Actually, it's safer to always fetch if we have a date, as the DOM was just replaced.
        if ($('select[name=o100_date_deli]').val()) {
            fetchTimeslots();
        }
    });

    // Also fetch immediately on page load just in case updated_checkout is delayed
    if ($('select[name=o100_date_deli]').val()) {
        setTimeout(function() {
            if ($('select[name=o100_time_deli]').find('option').length <= 1) {
                fetchTimeslots();
            }
        }, 100);
    }

});




