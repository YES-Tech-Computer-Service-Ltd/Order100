const html = "Hello [o100_customer_name], order [o100_order_type]";
const orderData = { billing_first_name: "Kevin", meta: { _o100_order_type: "Pickup" } };

const mapping = {
  '[o100_customer_name]': (orderData.billing_first_name || orderData.billing_last_name) ? `${orderData.billing_first_name || ''} ${orderData.billing_last_name || ''}`.trim() : 'Customer',
  '[o100_order_type]': orderData.meta?._o100_order_type || orderData.meta?._o100_order_method || 'Delivery'
};

let parsed = html;
Object.keys(mapping).forEach(key => {
  const escapedKey = key.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
  const regexStr = escapedKey.replace(/([a-zA-Z0-9_])/g, '$1(?:<[^>]+>)*');
  const regex = new RegExp(regexStr, 'g');
  parsed = parsed.replace(regex, mapping[key]);
  parsed = parsed.split(key).join(mapping[key]);
});

console.log(parsed);
