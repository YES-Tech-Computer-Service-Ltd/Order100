const fs = require('fs');
const jsPath = '/Users/kevinqi/development/antigravity/order100/assets/js/o100-loyalty-admin.js';
let content = fs.readFileSync(jsPath, 'utf8');

// Replace all occurrences of wlr_ with o100_loyalty_ for ajax actions
content = content.replace(/action:\s*['"]wlr_([^'"]+)['"]/g, "action: 'o100_loyalty_$1'");
// Replace wlr_nonce with o100_loyalty_nonce
content = content.replace(/wlr_nonce/g, "o100_loyalty_nonce");
// Replace nonces
content = content.replace(/wlr_dashboard_nonce/g, "o100_dashboard_nonce");
content = content.replace(/wlr-earn-campaign-nonce/g, "o100-earn-campaign-nonce");
content = content.replace(/wlr-reward-nonce/g, "o100-reward-nonce");
content = content.replace(/wlr-customers-nonce/g, "o100-customers-nonce");
content = content.replace(/wlr-level-nonce/g, "o100-level-nonce");

fs.writeFileSync(jsPath, content, 'utf8');
console.log('JS Updated');

