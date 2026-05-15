jQuery(document).ready(function($) {
    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        return;
    }

    var originAddress = o100_gmap_vars.origin_address;
    if (!originAddress) {
        console.warn('Order100 Maps: No origin address defined for distance calculation.');
        return;
    }

    var distanceService = new google.maps.DistanceMatrixService();

    function initAutocomplete(inputId) {
        var input = document.getElementById(inputId);
        if (!input) return;

        // Prevent attaching multiple times
        if ($(input).hasClass('pac-target-input')) return;

        var autocomplete = new google.maps.places.Autocomplete(input);
        
        // Bias to checkout country if possible
        var countrySelect = $('#' + inputId.replace('_address_1', '_country'));
        if (countrySelect.length && countrySelect.val()) {
            autocomplete.setComponentRestrictions({'country': countrySelect.val().toLowerCase()});
        }

        autocomplete.addListener('place_changed', function() {
            var place = autocomplete.getPlace();
            if (!place.geometry) {
                return;
            }

            // Fill address components into WooCommerce fields
            var addressComponents = place.address_components;
            var city = '';
            var state = '';
            var postcode = '';
            var country = '';

            for (var i = 0; i < addressComponents.length; i++) {
                var types = addressComponents[i].types;
                if (types.indexOf('locality') !== -1) {
                    city = addressComponents[i].long_name;
                }
                if (types.indexOf('administrative_area_level_1') !== -1) {
                    state = addressComponents[i].short_name;
                }
                if (types.indexOf('postal_code') !== -1) {
                    postcode = addressComponents[i].long_name;
                }
                if (types.indexOf('country') !== -1) {
                    country = addressComponents[i].short_name;
                }
            }

            var prefix = inputId.split('_')[0]; // 'billing' or 'shipping'
            
            if (city) $('#' + prefix + '_city').val(city);
            if (postcode) $('#' + prefix + '_postcode').val(postcode);
            if (state) {
                var stateSelect = $('#' + prefix + '_state');
                if (stateSelect.is('select')) {
                    stateSelect.val(state).change();
                } else {
                    stateSelect.val(state);
                }
            }
            if (country) {
                var countrySelect = $('#' + prefix + '_country');
                if (countrySelect.is('select')) {
                    countrySelect.val(country).change();
                } else {
                    countrySelect.val(country);
                }
            }

            // Trigger WooCommerce to validate and possibly recalculate
            $('#' + inputId).trigger('change');

            // Calculate Distance
            var destination = place.formatted_address || place.name;
            calculateDistance(destination);
        });
    }

    function calculateDistance(destination) {
        console.log('Order100: Calculating distance from ' + originAddress + ' to ' + destination);
        distanceService.getDistanceMatrix({
            origins: [originAddress],
            destinations: [destination],
            travelMode: 'DRIVING',
            unitSystem: google.maps.UnitSystem.METRIC,
        }, function(response, status) {
            console.log('Order100: DistanceMatrix status - ' + status);
            if (status === 'OK') {
                var results = response.rows[0].elements;
                if (results && results[0] && results[0].status === 'OK') {
                    // Distance in meters, convert to km
                    var distanceMeters = results[0].distance.value;
                    var distanceKm = (distanceMeters / 1000).toFixed(2);
                    console.log('Order100: Computed distance is ' + distanceKm + ' km');
                    
                    // Inject distance into checkout form
                    var $form = $('form.checkout');
                    if ($form.length) {
                        var $input = $('#o100_user_distance');
                        if (!$input.length) {
                            $form.append('<input type="hidden" name="o100_user_distance" id="o100_user_distance" value="">');
                            $input = $('#o100_user_distance');
                        }
                        
                        // Clear previous distance error warnings
                        $('.o100-distance-error').remove();
                        
                        // Enforce frontend UI warning
                        var maxAllowed = typeof o100_gmap_vars !== 'undefined' ? parseFloat(o100_gmap_vars.max_allowed) : -1;
                        if (maxAllowed > 0 && parseFloat(distanceKm) > maxAllowed) {
                            var msg = o100_gmap_vars.error_msg.replace('{dist}', distanceKm).replace('{max}', maxAllowed);
                            var html = '<div class="o100-distance-error" style="background: #ffeaec; border: 2px solid #ff4d4f; color: #ff4d4f; padding: 15px; border-radius: 8px; font-weight: bold; font-size: 16px; margin-bottom: 20px; text-align: center; box-shadow: 0 4px 12px rgba(255,77,79,0.2);">⛔ ' + msg + '</div>';
                            
                            // Prepend above the active address fields
                            $('#billing_address_1_field, #shipping_address_1_field').before(html);
                            
                            // Smooth scroll to the warning
                            $('html, body').animate({
                                scrollTop: $('.o100-distance-error').first().offset().top - 100
                            }, 500);
                        }

                        // Only trigger update if distance actually changed to prevent loops
                        if ($input.val() !== distanceKm) {
                            console.log('Order100: Triggering WooCommerce update_checkout');
                            $input.val(distanceKm);
                            $('body').trigger('update_checkout');
                        }
                    }
                } else {
                    console.error('Order100: Distance API returned row status: ' + (results && results[0] ? results[0].status : 'UNKNOWN'));
                }
            } else {
                console.error('Order100: Distance Matrix API failed. Did you enable Distance Matrix API in Google Cloud Console?');
            }
        });
    }

    // Initialize Autocomplete on checkout fields
    if ($('form.checkout').length) {
        initAutocomplete('billing_address_1');
        initAutocomplete('shipping_address_1');
        
        // Re-bind when checkout is updated (in case fields are replaced)
        $(document).on('updated_checkout', function() {
            initAutocomplete('billing_address_1');
            initAutocomplete('shipping_address_1');
        });

        // Trigger distance calculation on load for pre-filled addresses
        setTimeout(function() {
            var shipDiff = $('#ship-to-different-address-checkbox').is(':checked');
            var prefix = shipDiff ? 'shipping' : 'billing';
            var addr1 = $('#' + prefix + '_address_1').val();
            var city = $('#' + prefix + '_city').val();
            var state = $('#' + prefix + '_state').val() || '';
            var country = $('#' + prefix + '_country').val() || '';

            if (addr1 && city) {
                var destination = addr1 + ', ' + city + ' ' + state + ' ' + country;
                console.log('Order100: Pre-filled address detected, calculating initial distance for: ' + destination);
                calculateDistance(destination);
            }
        }, 1500); // Slight delay to let WC initialize
    }
});

/* TS: 20260126122334 */

/* TS: 20260130215710 */

/* TS: 20260515170952 */
