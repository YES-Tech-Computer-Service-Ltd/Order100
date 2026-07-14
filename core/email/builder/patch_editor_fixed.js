const fs = require('fs');
const file = '/Users/kevinqi/development/antigravity/order100/core/email/builder/src/pages/Editor.tsx';
let content = fs.readFileSync(file, 'utf8');

const search = `        '[o100_customer_name]': orderData.billing_first_name !== undefined ? \`\${orderData.billing_first_name} \${orderData.billing_last_name}\`.trim() : 'Customer',`;
const replace = `        '[o100_customer_name]': (orderData.billing_first_name || orderData.billing_last_name) ? \`\${orderData.billing_first_name || ''} \${orderData.billing_last_name || ''}\`.trim() : 'Customer',
        '[o100_customer_first_name]': orderData.billing_first_name !== undefined ? orderData.billing_first_name : 'Customer',
        '[o100_customer_last_name]': orderData.billing_last_name !== undefined ? orderData.billing_last_name : '',`;

content = content.replace(search, replace);

const regexSearch = `      Object.keys(mapping).forEach(key => {
        parsed = parsed.split(key).join(mapping[key]);
      });`;
const regexReplace = `      Object.keys(mapping).forEach(key => {
        // Handle HTML tags inside shortcodes e.g. [o100_customer_<b>name</b>]
        const escapedKey = key.replace(/[-\\/\\\\^$*+?.()|[\\]{}]/g, '\\\\$&');
        const regexStr = escapedKey.replace(/([a-zA-Z0-9_])/g, '$1(?:<[^>]+>)*');
        const regex = new RegExp(regexStr, 'g');
        parsed = parsed.replace(regex, mapping[key]);
        parsed = parsed.split(key).join(mapping[key]);
      });`;

content = content.replace(regexSearch, regexReplace);
fs.writeFileSync(file, content);
