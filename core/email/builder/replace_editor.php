<?php
$tsx_code = <<<'EOT'
  // ─── Per-Type MJML Templates ────────────────────────────────────

  const templates: Record<string, { heading: string; body: string; cta?: string; ctaLabel?: string; showOrder?: boolean; showAddress?: boolean; showProducts?: boolean }> = {
    'new_order': {
      heading: 'New Order Received!',
      body: 'Thank you for your order with [o100_site_name]. Your delivery order has been received and scheduled according to your selected delivery time.',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'View Your Order',
      showOrder: true,
      showAddress: true,
      showProducts: true,
    },
    'cancelled_order': {
      heading: 'Order Cancelled',
      body: 'Order #[o100_order_number] from [o100_billing_first_name] [o100_billing_last_name] has been cancelled.',
      showOrder: true,
      showAddress: false,
    },
    'customer_cancelled_order': {
      heading: 'Your Order Has Been Cancelled',
      body: 'Your order #[o100_order_number] has been cancelled. If this was a mistake, please contact us.',
      cta: '[o100_site_url]',
      ctaLabel: 'Visit Store',
      showOrder: true,
    },
    'failed_order': {
      heading: 'Payment Failed',
      body: 'Payment for order #[o100_order_number] from [o100_billing_first_name] [o100_billing_last_name] has failed.',
      showOrder: true,
      showAddress: false,
    },
    'customer_failed_order': {
      heading: 'Payment Unsuccessful',
      body: 'Unfortunately, we couldn\'t process the payment for your order #[o100_order_number]. Please try again with a different payment method.',
      cta: '[o100_order_payment_url_string]',
      ctaLabel: 'Retry Payment',
      showOrder: true,
    },
    'customer_on_hold_order': {
      heading: 'Order On Hold',
      body: 'Thank you for your order! Your order #[o100_order_number] is on hold until we confirm your payment.<br/><br/>[o100_payment_instruction]',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'View Order',
      showOrder: true,
      showAddress: true,
    },
    'customer_processing_order': {
      heading: 'Order Confirmed!',
      body: 'Great news! Your order #[o100_order_number] has been received and is now being processed.',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'Track Order',
      showOrder: true,
      showAddress: true,
    },
    'customer_completed_order': {
      heading: 'Order Complete!',
      body: 'Your order #[o100_order_number] has been completed. We hope you enjoy your purchase!',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'View Order',
      showOrder: true,
      showAddress: true,
      showProducts: true,
    },
    'customer_refunded_order': {
      heading: 'Refund Processed',
      body: 'Your order #[o100_order_number] has been refunded. The refund will be credited back to your original payment method within 5–10 business days.',
      showOrder: true,
    },
    'customer_invoice': {
      heading: 'Invoice for Order #[o100_order_number]',
      body: 'Here are the details for your order placed on [o100_order_date].',
      cta: '[o100_order_payment_url_string]',
      ctaLabel: 'Pay for This Order',
      showOrder: true,
      showAddress: true,
    },
    'customer_note': {
      heading: 'A Note About Your Order',
      body: 'The following note has been added to your order #[o100_order_number]:<br/><br/><em style="color:#555;">[o100_customer_note]</em>',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'View Order',
    },
    'customer_reset_password': {
      heading: 'Password Reset Request',
      body: 'Someone has requested a new password for your account on [o100_site_name].<br/><br/>If you didn\'t make this request, you can safely ignore this email.',
      cta: '[o100_password_reset_url_string]',
      ctaLabel: 'Reset Password',
    },
    'customer_new_account': {
      heading: 'Welcome to [o100_site_name]!',
      body: 'Thanks for creating an account! Your username is: <strong>[o100_customer_username]</strong>.',
      cta: '[o100_user_account_url_string]',
      ctaLabel: 'Go to My Account',
    },
  };

  const cfg = templates[type] || templates['new_order']!;

  // 1. Logo and Hero image (from the user's template)
  const headerHtml = `
    <mj-raw></mj-raw>
    <mj-section padding-bottom="0px">
      <mj-column>
        <mj-image src="http://localhost:10019/wp-content/uploads/2026/06/logo-placeholder-png-2.png" alt="logo" width="160px" padding-top="0px" padding-bottom="0px"></mj-image>
      </mj-column>
    </mj-section>
    <mj-section padding-top="0px" padding-bottom="0px">
      <mj-column>
        <mj-image src="http://localhost:10019/wp-content/uploads/2026/06/different-poke-bowls-on-blue-background-top-view-2026-03-20-03-26-50-utc.webp" padding-top="0px" padding-bottom="0px" padding-right="0px" padding-left="0px" alt="banner"></mj-image>
      </mj-column>
    </mj-section>
  `;

  // 2. Greeting and Body
  const greetingHtml = `
    <mj-section css-class="woo-order-detail" padding-top="0px" padding-bottom="0px">
      <mj-column>
        <mj-text>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Dear [o100_customer_first_name],</span></p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">${cfg.heading}</span></p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">${cfg.body}</span></p>
        </mj-text>
      </mj-column>
    </mj-section>
  `;

  // 3. Order Details
  const orderDetailsHtml = cfg.showOrder ? `
    <mj-section padding-top="0px">
      <mj-column>
        <mj-divider border-width="1px" border-color="#bcacac"></mj-divider>
        <mj-text>
          <span style="font-size:16px;line-height:25.6px;">Order Number: [o100_order_number]</span><br/>
          <span style="font-size:16px;line-height:25.6px;">Order Date: [o100_order_date]</span><br/>
          <span style="font-size:16px;line-height:25.6px;">Order Type: [o100_order_type]</span><br/>
          <span style="font-size:16px;line-height:25.6px;">Payment Method: [o100_order_payment_method]</span>
        </mj-text>
        ${cfg.cta ? `<mj-button href="${cfg.cta}" background-color="#f60909" font-size="18px">${cfg.ctaLabel}</mj-button>` : ''}
        <mj-divider border-width="1px" border-color="#bcacac"></mj-divider>
      </mj-column>
    </mj-section>
  ` : '';

  // 4. Delivery Information (Show if address exists)
  const deliveryHtml = cfg.showAddress ? `
    <mj-section padding-top="0px">
      <mj-column>
        <mj-text font-size="17px">
          <p style="line-height:160%;"><strong><span style="font-size:16px;line-height:25.6px;">Delivery Information</span></strong></p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Delivery Address:</span><br/>[o100_shipping_address]</p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Scheduled Delivery Time:</span><br/>[o100_prep_time]</p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Please ensure someone is available at the delivery address during the scheduled time window. Our driver may contact you if needed.</span></p>
        </mj-text>
        <mj-divider border-width="1px" border-color="#bcacac"></mj-divider>
      </mj-column>
    </mj-section>
  ` : '';

  // 5. Footer and questions
  const footerHtml = `
    <mj-section padding-top="0px">
      <mj-column>
        <mj-text>
          <p style="line-height:160%;"><strong><span style="font-size:16px;line-height:25.6px;">Order Changes or Questions</span></strong></p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">If you need to update delivery instructions or have any concerns, please contact us as soon as possible.</span></p>
          <p style="line-height:160%;">
            <span style="font-size:16px;line-height:25.6px;">📞 Phone: <a href="tel:+11111111111">(+1) 111-111-1111</a></span><br/>
            <span style="font-size:16px;line-height:25.6px;">🌐 <a href="[o100_site_url]">[o100_site_url]</a></span>
          </p>
        </mj-text>
        <mj-text>
          <span style="font-size:18px;line-height:28.8px;">Thank you for choosing [o100_site_name]. We appreciate your support and hope you enjoy your meal.</span>
        </mj-text>
      </mj-column>
    </mj-section>
  `;

  // 6. Products
  const productsHtml = cfg.showProducts ? `
    <mj-section>
      <mj-column>
        <mj-text font-family="helvetica" padding="10px" css-class="woo-products" data-max-rows="1" data-columns="3" data-add-to-cart-url="true">
        </mj-text>
      </mj-column>
    </mj-section>
    <mj-section css-class="woo-products" data-product-type="newest" data-max-rows="1" data-columns="3" data-add-to-cart-url="true" data-show-sku="false"></mj-section>
  ` : '';

  return `
    <mjml>
      <mj-body width="800px" background-color="#ffffff">
        ${headerHtml}
        ${greetingHtml}
        ${orderDetailsHtml}
        ${deliveryHtml}
        ${footerHtml}
        ${productsHtml}
      </mj-body>
    </mjml>
  `;
EOT;

$file = '/Users/kevinqi/development/antigravity/order100/core/email/builder/src/pages/Editor.tsx';
$content = file_get_contents($file);

$start_marker = "  // ─── Per-Type MJML Templates ────────────────────────────────────";
$end_marker = "const SHORTCODE_CATEGORIES = [";

$start_pos = strpos($content, $start_marker);
$end_pos = strpos($content, $end_marker);

if ($start_pos !== false && $end_pos !== false) {
    // Look backwards from $end_marker to find the ending brace of the function "};\n\nconst SHORTCODE_CATEGORIES"
    $end_pos = strrpos(substr($content, 0, $end_pos), "};");
    if ($end_pos !== false) {
        $end_pos += 2; // Include "};"
        $new_content = substr($content, 0, $start_pos) . $tsx_code . "\n};\n\n" . substr($content, $end_pos);
        file_put_contents($file, $new_content);
        echo "Replaced successfully!";
    } else {
        echo "Could not find ending };";
    }
} else {
    echo "Could not find markers";
}
