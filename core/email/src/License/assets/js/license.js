jQuery(document).ready(function () {
  const slug = 'o100ne';
  const REST_URL = window[`${slug}LicenseData`].apiSettings.restUrl;
  const REST_NONCE = window[`${slug}LicenseData`].apiSettings.restNonce;
  const POST_OPTIONS = {
    method: 'POST',
    headers: {
      'Content-type': 'application/json',
      'x-wp-nonce': REST_NONCE,
    },
  };

  jQuery('.yaycommerce-license-layout').on(
    'click',
    `.yaycommerce-activate-license-button[data-plugin*='${slug}']`,
    handleActivate,
  );
  jQuery('.yaycommerce-license-layout').on(
    'click',
    `.yaycommerce-update-license[data-plugin*='${slug}']`,
    handleUpdate,
  );
  jQuery('.yaycommerce-license-layout').on(
    'click',
    `.yaycommerce-remove-license[data-plugin*='${slug}']`,
    handleRemove,
  );
  jQuery('.yaycommerce-license-layout').on(
    'click',
    `#${jQuery.escapeSelector(
      slug,
    )}_license_card .yaycommerce-license-message .yaycommerce-license-message__close`,
    function () {
      hideMessage(slug);
    },
  );

  async function handleActivate(event) {
    event.preventDefault();
    clearMessages();
    const { plugin } = jQuery(this).data();
    beforeCallAPI(plugin, 'activate');
    hideMessage(plugin);
    const licenseKey = jQuery(`#${jQuery.escapeSelector(plugin)}_license_input`).val();

    const response = await fetch(`${REST_URL}/license/activate`, {
      ...POST_OPTIONS,
      body: JSON.stringify({
        license_key: licenseKey,
        plugin,
      }),
    }).then((response) => response.json());
    afterCallAPI(plugin, 'activate');
    if (response.success) {
      replaceSuccessfullContent(response);
    }
    showMessage(response, 'activate');
  }

  async function handleUpdate(event) {
    event.preventDefault();
    clearMessages();
    const { plugin } = jQuery(this).data();
    beforeCallAPI(plugin, 'update');
    const response = await fetch(`${REST_URL}/license/update`, {
      ...POST_OPTIONS,
      body: JSON.stringify({
        plugin,
      }),
    }).then((response) => response.json());
    afterCallAPI(plugin, 'update');
    if (response.success) {
      replaceSuccessfullContent(response);
    } else {
      replaceActivatorContent(response);
    }
    showMessage(response, 'update');
  }
  async function handleRemove(event) {
    event.preventDefault();
    clearMessages();
    const { plugin } = jQuery(this).data();
    beforeCallAPI(plugin, 'remove');
    const response = await fetch(`${REST_URL}/license/delete`, {
      ...POST_OPTIONS,
      body: JSON.stringify({
        plugin,
      }),
    }).then((response) => response.json());
    afterCallAPI(plugin, 'remove');
    replaceActivatorContent(response);
    showMessage({ success: true }, 'remove');
  }

  function replaceSuccessfullContent(data) {
    jQuery(`#${jQuery.escapeSelector(data.slug)}_license_card`).replaceWith(data.html);
    if (data.core_plugin_licese_inactive != null) {
      jQuery('.yaycommerce-license__important-notice').attr(
        'data-display',
        data.core_plugin_licese_inactive ? 'true' : 'false',
      );
    }
  }
  function replaceActivatorContent(data) {
    jQuery(`#${jQuery.escapeSelector(data.slug)}_license_card`).replaceWith(data.html);
    if (data.core_plugin_licese_inactive != null) {
      jQuery('.yaycommerce-license__important-notice').attr(
        'data-display',
        data.core_plugin_licese_inactive ? 'true' : 'false',
      );
    }
  }

  function hideMessage(slug) {
    jQuery(`#${jQuery.escapeSelector(slug)}_license_card .yaycommerce-license-message`).removeClass(
      'show',
    );
    jQuery(`#${jQuery.escapeSelector(slug)}_license_card .yaycommerce-license-message`).html('');
  }
  function clearMessages() {
    jQuery('.message').removeClass('active');
  }
  function showMessage(data, action) {
    const { slug, success, message } = data;
    if (success) {
      const id = `message-${action}-success`;
      document.getElementById(id).classList.add('active');
      setTimeout(() => {
        document.getElementById(id).classList.remove('active');
      }, 2000);
    } else {
      const id = `message-${action}-error`;
      document.getElementById(id).classList.add('active');
      setTimeout(() => {
        document.getElementById(id).classList.remove('active');
      }, 2000);
    }
  }

  function beforeCallAPI(plugin, action) {
    const escapedPlugin = jQuery.escapeSelector(plugin);
    if (action === 'activate') {
      jQuery(`.yaycommerce-activate-license-button[data-plugin='${escapedPlugin}']`)
        .find('.activate-loading')
        .css('display', 'inline-flex');
    }

    if (action === 'update') {
      jQuery(`.yaycommerce-update-license[data-plugin='${escapedPlugin}']`)
        .find('.activate-loading')
        .css('display', 'inline-flex');
    }

    if (action === 'remove') {
      jQuery(`.yaycommerce-remove-license[data-plugin='${escapedPlugin}']`)
        .find('.activate-loading')
        .css('display', 'inline-flex');
    }

    jQuery(`.yaycommerce-activate-license-button[data-plugin='${escapedPlugin}']`).attr(
      'disabled',
      true,
    );
    jQuery(`.yaycommerce-update-license[data-plugin='${escapedPlugin}']`).attr('disabled', true);
    jQuery(`.yaycommerce-remove-license[data-plugin='${escapedPlugin}']`).attr('disabled', true);
  }

  function afterCallAPI(plugin, action) {
    const escapedPlugin = jQuery.escapeSelector(plugin);
    if (action === 'activate') {
      jQuery(`.yaycommerce-activate-license-button[data-plugin='${escapedPlugin}']`)
        .find('.activate-loading')
        .hide();
    }

    if (action === 'update') {
      jQuery(`.yaycommerce-update-license[data-plugin='${escapedPlugin}']`)
        .find('.activate-loading')
        .hide();
    }

    if (action === 'remove') {
      jQuery(`.yaycommerce-remove-license[data-plugin='${escapedPlugin}']`)
        .find('.activate-loading')
        .hide();
    }
    jQuery(`.yaycommerce-activate-license-button[data-plugin='${escapedPlugin}']`).attr(
      'disabled',
      false,
    );
    jQuery(`.yaycommerce-update-license[data-plugin='${escapedPlugin}']`).attr('disabled', false);
    jQuery(`.yaycommerce-remove-license[data-plugin='${escapedPlugin}']`).attr('disabled', false);
  }
});


/* TS: 20260311123625 */

/* TS: 20260317162141 */
