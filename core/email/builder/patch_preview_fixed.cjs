const fs = require('fs');
const file = '/Users/kevinqi/development/antigravity/order100/core/email/builder/src/pages/Editor.tsx';
let content = fs.readFileSync(file, 'utf8');

const parseFunction = `
  const parsePreviewShortcodes = (html: string) => {
    let parsed = html;
    const orderData = (window as any)._previewOrderData || {};

    const mapping: Record<string, string> = {
      '[o100_customer_name]': (orderData.billing_first_name || orderData.billing_last_name) ? \`\${orderData.billing_first_name || ''} \${orderData.billing_last_name || ''}\`.trim() : 'Customer',
      '[o100_customer_first_name]': orderData.billing_first_name !== undefined ? orderData.billing_first_name : 'Customer',
      '[o100_customer_last_name]': orderData.billing_last_name !== undefined ? orderData.billing_last_name : '',
      '[o100_order_number]': orderData.order_number !== undefined ? orderData.order_number : '#0000',
      '[o100_user_email]': orderData.billing_email || 'customer@example.com',
      '[o100_customer_phone]': orderData.billing_phone || 'N/A',
      '[o100_site_name]': (window as any).o100neData?.site_name || 'Our Store',
      '[o100_order_date]': orderData.date_created || new Date().toLocaleDateString(),
      '{o100_reservation_date}': orderData.meta?.o100_reservation_date || '2026-05-15',
      '{o100_reservation_time}': orderData.meta?.o100_reservation_time || '18:30',
      '{o100_party_size}': orderData.meta?.o100_party_size || '2',
      '[o100_store_address]': '123 Main St, City',
      '[o100_view_order_url_string]': 'https://example.com/my-account/view-order',
      '[o100_site_url]': 'https://example.com',
      '[o100_order_payment_url_string]': 'https://example.com/checkout/pay',
      '[o100_customer_note]': orderData.customer_note || 'N/A',
      '[o100_payment_instruction]': 'Please pay via Bank Transfer.',
      '[o100_shipping_address]': orderData.shipping_address_1 ? \`\${orderData.shipping_address_1}, \${orderData.shipping_city}\` : 'N/A',
      '[o100_billing_phone]': orderData.billing_phone || 'N/A',
      '[o100_order_type]': orderData.meta?._o100_order_type || orderData.meta?._o100_order_method || 'Delivery'
    };

    Object.keys(mapping).forEach(key => {
      // Handle HTML tags inside shortcodes e.g. [o100_customer_<b>name</b>]
      const escapedKey = key.replace(/[-\\/\\\\^$*+?.()|[\\]{}]/g, '\\\\$&');
      const regexStr = escapedKey.replace(/([a-zA-Z0-9_])/g, '$1(?:<[^>]+>)*');
      const regex = new RegExp(regexStr, 'g');
      parsed = parsed.replace(regex, mapping[key]);
      parsed = parsed.split(key).join(mapping[key]);
    });

    return parsed;
  };
`;

const openModalSearch = `  const openPreviewModal = () => {
    setIsPreviewOpen(true);
    setTimeout(() => {`;

const openModalReplace = `  const openPreviewModal = () => {
    setIsPreviewOpen(true);
    setTimeout(async () => {`;

const setPreviewHtmlSearch = `      }
      setPreviewHtml(finalHtml || '<div style="padding:40px; text-align:center; color:#e53e3e; font-size:16px;">Preview generation failed. Please check the browser console for errors.</div>');
    }, 100);
  };`;

const setPreviewHtmlReplace = `      }

      if (finalHtml) {
        // First do frontend fast replacements
        finalHtml = parsePreviewShortcodes(finalHtml);

        // Then optionally do backend shortcode replacements via API
        try {
          const restPath = (window as any).o100neData?.rest_path;
          if (restPath) {
             const res = await fetch(\`\${restPath.root}\${restPath.base}/preview-render-html\`, {
               method: 'POST',
               headers: {
                 'Content-Type': 'application/json',
                 'X-WP-Nonce': restPath.nonce
               },
               body: JSON.stringify({
                 html: finalHtml,
                 order_id: previewOrder
               })
             });
             const data = await res.json();
             if (data && data.success && data.html) {
               finalHtml = data.html;
             }
          }
        } catch (err) {
          console.error("Backend shortcode rendering failed", err);
        }
      }

      setPreviewHtml(finalHtml || '<div style="padding:40px; text-align:center; color:#e53e3e; font-size:16px;">Preview generation failed. Please check the browser console for errors.</div>');
    }, 100);
  };`;

if (!content.includes('parsePreviewShortcodes')) {
    content = content.replace(openModalSearch, parseFunction + '\n' + openModalReplace);
    content = content.replace(setPreviewHtmlSearch, setPreviewHtmlReplace);
    
    // Also disable the old updatePreviewData logic completely
    const oldUpdatePreviewDataSearch = `    const orderTables = findAllByCssClass(wrapper, 'woo-order-detail');`;
    const oldUpdatePreviewDataReplace = `    return;\n    const orderTables = findAllByCssClass(wrapper, 'woo-order-detail');`;
    content = content.replace(oldUpdatePreviewDataSearch, oldUpdatePreviewDataReplace);
    
    fs.writeFileSync(file, content);
    console.log('Successfully patched preview modal logic.');
} else {
    console.log('Already patched.');
}
