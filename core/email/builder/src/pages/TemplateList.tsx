import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

const getRestConfig = () => {
  const data = (window as any).o100neData?.rest_path || {};
  return { root: data.root || '/wp-json/', nonce: data.nonce || '' };
};

interface TemplateItem {
  id: number;
  name: string;
  template_title: string;
  type?: string;
  source?: string;
  status: string;
  elements_type?: string;
  recipient_type?: string;
  additional_recipients?: string;
  support_info?: { status: string };
}

const TAB_CATEGORIES = [
  { id: 'orders', label: 'Order Flow' },
  { id: 'loyalty', label: 'Loyalty & Promos' },
  { id: 'reservations', label: 'Reservations' },
  { id: 'accounts', label: 'Accounts & General' }
];

const TEMPLATE_METADATA: Record<string, { desc: string, recipient: string, category: string }> = {
  // Order Flow
  'new_order': { desc: 'Customer places order → Admin notified', recipient: 'Admin / Restaurant', category: 'orders' },
  'customer_processing_order': { desc: 'Restaurant confirms order → Customer notified', recipient: 'Customer', category: 'orders' },
  'o100_order_ready': { desc: 'Food ready (Pickup) → Customer notified', recipient: 'Customer', category: 'orders' },
  'o100_out_for_delivery': { desc: 'Food ready (Delivery) → Customer notified', recipient: 'Customer', category: 'orders' },
  'o100_driver_dispatch': { desc: 'Order ready → Driver notified to pick up', recipient: 'Driver', category: 'orders' },
  'customer_completed_order': { desc: 'Pickup/delivery done → Thank you + review invite', recipient: 'Customer', category: 'orders' },
  'customer_on_hold_order': { desc: 'Awaiting payment confirmation', recipient: 'Customer', category: 'orders' },
  'cancelled_order': { desc: 'Order cancelled by customer or restaurant', recipient: 'Admin', category: 'orders' },
  'customer_refunded_order': { desc: 'Refund processed → Customer notified', recipient: 'Customer', category: 'orders' },
  'customer_note': { desc: 'Restaurant sends a note to customer', recipient: 'Customer', category: 'orders' },
  'customer_invoice': { desc: 'Invoice with payment link', recipient: 'Customer', category: 'orders' },
  'failed_order': { desc: 'Payment failed → Admin notified', recipient: 'Admin', category: 'orders' },
  'customer_failed_order': { desc: 'Payment failed → Customer notified', recipient: 'Customer', category: 'orders' },
  'customer_cancelled_order': { desc: 'Order cancelled → Customer notified', recipient: 'Customer', category: 'orders' },
  
  // Accounts
  'customer_reset_password': { desc: 'Password reset link', recipient: 'Customer', category: 'accounts' },
  'customer_new_account': { desc: 'Welcome email for new registrations', recipient: 'Customer', category: 'accounts' },

  // Reservations
  'o100_reservation_new': { desc: 'Customer submits reservation → Admin notified', recipient: 'Admin', category: 'reservations' },
  'o100_reservation_confirmed': { desc: 'Admin approves reservation → Customer notified', recipient: 'Customer', category: 'reservations' },
  'o100_reservation_rejected': { desc: 'Admin rejects reservation → Customer notified', recipient: 'Customer', category: 'reservations' },
  'o100_reservation_reminder': { desc: 'Reminder sent before dining time', recipient: 'Customer', category: 'reservations' },

  // Loyalty & Promos
  'o100_loyalty_birthday': { desc: 'Automated birthday greetings', recipient: 'Customer', category: 'loyalty' },
  'o100_loyalty_points_earned': { desc: 'Points balance update notification', recipient: 'Customer', category: 'loyalty' },
  'o100_loyalty_tier_upgrade': { desc: 'Tier upgrade congratulations', recipient: 'Customer', category: 'loyalty' },
  'o100_loyalty_reward_issued': { desc: 'New coupon or promo code issued', recipient: 'Customer', category: 'loyalty' },
  'o100_loyalty_reward_expiring': { desc: 'Reminder that a reward is expiring soon', recipient: 'Customer', category: 'loyalty' },
  'o100_loyalty_referral_invite': { desc: 'Invitation sent to a referred friend', recipient: 'Customer', category: 'loyalty' },
  'o100_loyalty_referral_reward': { desc: 'Reward for successful referral', recipient: 'Customer', category: 'loyalty' },
  'o100_promo_win_back': { desc: 'Welcome back email for inactive customers', recipient: 'Customer', category: 'loyalty' },
  'o100_promo_campaign': { desc: 'Mass promotion and flash sale announcements', recipient: 'Customer', category: 'loyalty' }
};

const TemplateList: React.FC = () => {
  const [templates, setTemplates] = useState<TemplateItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('orders');
  const [searchQuery, setSearchQuery] = useState('');
  const [savingId, setSavingId] = useState<number | null>(null);
  const [savedId, setSavedId] = useState<number | null>(null);

  useEffect(() => {
    // Tailwind CSS is loaded from PHP (settings.php) before React mounts

    const { root, nonce } = getRestConfig();
    fetch(`${root}o100ne/v1/templates`, {
      headers: nonce ? { 'X-WP-Nonce': nonce } : {},
      credentials: 'same-origin',
    })
      .then((res) => res.json())
      .then((data) => {
        if (Array.isArray(data)) {
          setTemplates(data);
        } else {
          console.error("Templates API returned non-array:", data);
          setTemplates([]);
        }
        console.log("Templates loaded:", data.length); // Force hash change
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, []);

  const handleUpdateMeta = async (id: number, name: string, updates: { status?: string, additional_recipients?: string }) => {
    setSavingId(id);
    const { root, nonce } = getRestConfig();
    try {
      const response = await fetch(`${root}o100ne/v1/templates/update-meta`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(nonce ? { 'X-WP-Nonce': nonce } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ template_id: id, name: name, ...updates }),
      });
      if (response.ok) {
        setTemplates(templates.map(t => (t.id === id ? { ...t, ...updates } : t)));
        setSavedId(id);
        setTimeout(() => setSavedId(null), 2000);
      } else {
        alert('Failed to save changes.');
      }
    } catch (err) {
      alert('An error occurred while saving.');
    } finally {
      setSavingId(null);
    }
  };

  const getTemplateCategory = (tpl: any) => {
    const name = (tpl.name || '').toLowerCase();
    const id = (tpl.id || '').toString().toLowerCase();
    const title = (tpl.template_title || '').toLowerCase();

    // 1. Explicit ID/Name matching
    if (name.includes('loyalty') || id.includes('loyalty') || title.includes('loyalty')) return 'loyalty';
    if (name.includes('promo') || id.includes('promo') || title.includes('promo')) return 'loyalty';
    if (name.includes('birthday') || id.includes('birthday')) return 'loyalty';
    if (name.includes('point') || id.includes('point')) return 'loyalty';
    if (name.includes('reward') || id.includes('reward')) return 'loyalty';
    if (name.includes('tier') || id.includes('tier')) return 'loyalty';
    if (name.includes('campaign') || id.includes('campaign')) return 'loyalty';
    if (name.includes('win_back') || id.includes('win_back') || name.includes('winback')) return 'loyalty';
    if (name.includes('referral') || id.includes('referral')) return 'loyalty';
    if (name.includes('wlr_') || id.includes('wlr_')) return 'loyalty';

    // 2. Metadata matching
    const meta = TEMPLATE_METADATA[tpl.name] || TEMPLATE_METADATA[tpl.id];
    if (meta) return meta.category;

    // 3. Fallbacks
    if (name.includes('reservation') || id.includes('reservation')) return 'reservations';
    if (name.includes('account') || id.includes('account')) return 'accounts';
    if (name.includes('order') || name.includes('invoice') || id.includes('order')) return 'orders';

    // Default to accounts instead of orders to prevent cluttering order flow
    return 'accounts';
  };

  if (loading) {
    return (
      <div style={{ padding: '60px', textAlign: 'center', color: '#64748b', fontFamily: 'sans-serif' }}>
        Loading templates…
      </div>
    );
  }

  // Filter templates by tab and search
  let filteredTemplates = templates.filter(tpl => getTemplateCategory(tpl) === activeTab);
  if (searchQuery) {
    filteredTemplates = filteredTemplates.filter(tpl => 
      (tpl.template_title || tpl.name).toLowerCase().includes(searchQuery.toLowerCase())
    );
  }

  // Sort them
  const keysOrder = Object.keys(TEMPLATE_METADATA);
  filteredTemplates.sort((a, b) => {
    const idxA = keysOrder.indexOf(a.name);
    const idxB = keysOrder.indexOf(b.name);
    if (idxA !== -1 && idxB !== -1) return idxA - idxB;
    if (idxA !== -1) return -1;
    if (idxB !== -1) return 1;
    return (a.template_title || a.name).localeCompare(b.template_title || b.name);
  });

  return (
    <div className="o100-notification-list" style={{ background: 'transparent', minHeight: '100vh', fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif' }}>
      
      {/* Toggle CSS injected as standard in promo module */}
      <style>{`
        .o100-toggle { position: relative; display: inline-block; width: 36px; height: 20px; }
        .o100-toggle input { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; appearance: none; background: none !important; }
        .o100-toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 20px; transition: 0.3s; }
        .o100-toggle-slider:before { content: ''; position: absolute; height: 14px; width: 14px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; }
        .o100-toggle input:checked + .o100-toggle-slider { background: #22c55e; }
        .o100-toggle input:checked + .o100-toggle-slider:before { transform: translateX(16px); }
        .o100-toggle input:disabled + .o100-toggle-slider { opacity: 0.5; cursor: not-allowed; }
      `}</style>

      <div className="w-full">
        {/* Header matching Promo module (Removed as per feedback to avoid duplication) */}

        {/* Tabs */}
        <div className="mb-6 border-b border-slate-200">
          <nav className="-mb-px flex space-x-8" aria-label="Tabs">
            {TAB_CATEGORIES.map(cat => (
              <button
                key={cat.id}
                type="button"
                onClick={() => setActiveTab(cat.id)}
                className={`bg-transparent ${
                  activeTab === cat.id
                    ? 'border-indigo-500 text-indigo-600'
                    : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors cursor-pointer`}
                style={{ background: 'transparent' }}
              >
                {cat.label}
                <span className={`ml-2 py-0.5 px-2 rounded-full text-xs ${
                  activeTab === cat.id ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-500'
                }`}>
                  {templates.filter(t => getTemplateCategory(t) === cat.id).length}
                </span>
              </button>
            ))}
          </nav>
        </div>

        {/* Toolbar: Search */}
        <div className="mb-4 flex flex-wrap items-center gap-3">
          <div className="relative flex-1 min-w-[200px] max-w-xs">
            <input 
              type="text" 
              placeholder="Search templates..." 
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full border border-slate-300 rounded-lg py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
              style={{ paddingLeft: '36px', paddingRight: '12px' }} 
            />
            <svg className="absolute w-4 h-4 text-slate-400" style={{ left: '12px', top: '50%', transform: 'translateY(-50%)' }} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
          </div>
        </div>

        {/* Active Templates Table */}
        <div>
          <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
            <div style={{ overflowX: 'auto' }}>
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500 uppercase tracking-wider">
                    <th className="py-3 px-6" style={{ textAlign: 'left', paddingLeft: '24px' }}>Name</th>
                    <th className="py-3 px-6" style={{ textAlign: 'left', width: '1%', whiteSpace: 'nowrap' }}>Recipient</th>
                    <th className="py-3 px-6" style={{ textAlign: 'left' }}>Additional Recipients (CC/BCC)</th>
                    <th className="py-3 px-6 text-center" style={{ width: '1%', whiteSpace: 'nowrap', textAlign: 'center' }}>Enabled</th>
                    <th className="py-3 px-6 text-right" style={{ width: '1%', whiteSpace: 'nowrap', textAlign: 'right' }}>Actions</th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-slate-200">
                  {filteredTemplates.length > 0 ? (
                    filteredTemplates.map(tpl => {
                      const meta = TEMPLATE_METADATA[tpl.name] || { desc: '', recipient: tpl.recipient_type === 'customer' ? 'Customer' : 'Admin' };
                      const isEditable = !tpl.support_info || tpl.support_info.status === 'already_supported';
                      const isInactive = tpl.status !== 'active';
                      const typeLabel = tpl.elements_type === 'mjml' ? 'MJML' : (tpl.source || 'WooCommerce');

                      // Badge styles like promo
                      const isOrder100 = (tpl.source || '').toLowerCase().includes('order100');
                      const isAutoManaged = tpl.name.startsWith('o100_loyalty_') || tpl.name.startsWith('o100_promo_');
                      
                      let typeBadge = `<span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">${typeLabel}</span>`;
                      if (isOrder100) {
                        typeBadge = `<span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">Order100</span>`;
                      } else if (tpl.elements_type === 'mjml') {
                        typeBadge = `<span class="text-xs bg-sky-100 text-sky-700 px-2 py-0.5 rounded-full">MJML</span>`;
                      }

                      return (
                        <tr key={tpl.name} className={`border-b border-slate-100 hover:bg-slate-50 transition-colors ${isInactive ? 'opacity-60' : ''}`}>
                          <td className="px-6 py-4">
                            <div className="text-sm font-bold text-slate-900">{tpl.template_title || tpl.name}</div>
                            {meta.desc && <div className="text-xs text-slate-500 mt-1">{meta.desc}</div>}
                          </td>

                          <td className="px-6 py-4 whitespace-nowrap">
                            <div className="text-sm text-slate-700">{meta.recipient}</div>
                          </td>

                          <td className="px-6 py-4">
                            <div className="flex items-center gap-2">
                              <input 
                                type="text" 
                                className="w-full border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="e.g. notify@store.com"
                                defaultValue={tpl.additional_recipients || ''}
                                onBlur={(e) => {
                                  if (e.target.value !== (tpl.additional_recipients || '')) {
                                    handleUpdateMeta(tpl.id, tpl.name, { additional_recipients: e.target.value });
                                  }
                                }}
                              />
                              {savedId === tpl.id && (
                                <span className="text-xs text-green-600 font-medium whitespace-nowrap" style={{ animation: 'fadeIn 0.2s ease-in' }}>Saved ✓</span>
                              )}
                            </div>
                          </td>
                          <td className="px-6 py-4 text-center">
                            <label className="o100-toggle" title={isAutoManaged ? "This email is automatically enabled/disabled by its module settings" : ""}>
                              <input 
                                type="checkbox" 
                                checked={!isInactive} 
                                disabled={savingId === tpl.id || isAutoManaged}
                                onChange={(e) => handleUpdateMeta(tpl.id, tpl.name, { status: e.target.checked ? 'active' : 'inactive' })}
                              />
                              <span className="o100-toggle-slider"></span>
                            </label>
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" style={{ textAlign: 'right' }}>
                            {isEditable ? (
                              <Link 
                                to={`/editor/${tpl.name}`} 
                                className="text-indigo-600 hover:text-indigo-900 bg-indigo-50 px-3 py-1.5 rounded text-sm font-medium transition-colors inline-block"
                              >
                                Edit
                              </Link>
                            ) : (
                              <span className="text-xs text-slate-400">Not Supported</span>
                            )}
                          </td>
                        </tr>
                      );
                    })
                  ) : (
                    <tr>
                      <td colSpan={4} className="py-8 text-center text-slate-500">
                        No templates found in this category.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TemplateList;
