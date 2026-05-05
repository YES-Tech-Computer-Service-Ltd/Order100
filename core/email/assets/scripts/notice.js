jQuery(document).ready(function () {
  /**
   * Dismiss suggest addons
   */
  jQuery('#o100ne-suggest-addons-notice .o100ne-dismiss-suggest-addons-notice').on(
    'click',
    function () {
      jQuery('#o100ne-suggest-addons-notice .notice-dismiss').trigger('click');
    },
  );
  jQuery('#o100ne-suggest-addons-notice .o100ne-see-addons').on('click', function () {
    jQuery('#o100ne-suggest-addons-notice .notice-dismiss').trigger('click');
  });

  /**
   * Dismiss upgrade pro
   */
  jQuery('#o100ne-upgrade-notice .o100ne-dismiss-upgrade-notice').on('click', function () {
    jQuery('#o100ne-upgrade-notice .notice-dismiss').trigger('click');
  });
  jQuery('#o100ne-upgrade-notice .o100ne-upgrade-pro').on('click', function () {
    jQuery('#o100ne-upgrade-notice .notice-dismiss').trigger('click');
  });

  jQuery(document).on('click', '#o100ne-suggest-addons-notice .notice-dismiss', function () {
    handleDismiss();
  });

  jQuery(document).on('click', '#o100ne-upgrade-notice .notice-dismiss', function () {
    handleDismiss(true);
  });

  function handleDismiss(isUpgrade = false) {
    jQuery
      .ajax({
        dataType: 'json',
        url: o100ne_notice.admin_ajax,
        type: 'post',
        data: {
          action: isUpgrade
            ? 'o100_dismiss_upgrade_notice'
            : 'o100_dismiss_suggest_addons_notice',
          nonce: o100ne_notice.nonce,
        },
      })
      .done(function (result) {
        if (result.success) {
          console.log('success hide notice');
        } else {
          console.log('Error', result.message);
        }
      })
      .fail(function (res) {
        console.log(res.responseText);
      });
  }
});



/* TS: 20260124174344 */

/* TS: 20260224142535 */

/* TS: 20260504164724 */
