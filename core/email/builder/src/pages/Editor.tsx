import React, { useEffect, useRef, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import grapesjs, { Editor as GjsEditor } from 'grapesjs';
import grapesjsMjml from 'grapesjs-mjml';
import 'grapesjs/dist/css/grapes.min.css';

// ─── LinkPicker: autocomplete URL input with WordPress content search ───
const LinkPicker = ({ defaultValue, onChange, label, placeholder }: {
  defaultValue: string;
  onChange: (url: string) => void;
  label?: string;
  placeholder?: string;
}) => {
  const [value, setValue] = useState(defaultValue || '');
  const [suggestions, setSuggestions] = useState<{title: string; url: string; type: string}[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const [loading, setLoading] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const wrapperRef = useRef<HTMLDivElement>(null);

  const searchContent = useCallback((query: string) => {
    if (query.length < 2) { setSuggestions([]); return; }
    // If it looks like a URL already, don't search
    if (query.startsWith('http://') || query.startsWith('https://') || query.startsWith('/') || query.startsWith('#') || query.startsWith('mailto:')) {
      setSuggestions([]);
      return;
    }
    setLoading(true);
    const restRoot = (window as any).o100neData?.rest_path?.root || '/wp-json/';
    const nonce = (window as any).o100neData?.rest_path?.nonce || '';
    fetch(`${restRoot}wp/v2/search?search=${encodeURIComponent(query)}&per_page=8&type=post&subtype=any`, {
      headers: nonce ? { 'X-WP-Nonce': nonce } : {}
    })
      .then(res => res.json())
      .then(data => {
        if (Array.isArray(data)) {
          setSuggestions(data.map((item: any) => ({
            title: item.title || item.name || '',
            url: item.url || '',
            type: item.subtype || item.type || 'post'
          })));
          setShowDropdown(true);
        }
        setLoading(false);
      })
      .catch(() => { setLoading(false); });
  }, []);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const v = e.target.value;
    setValue(v);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => searchContent(v), 300);
  };

  // Close dropdown on click outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const typeIcons: Record<string, string> = {
    page: '📄', post: '📝', product: '🛍️', attachment: '🖼️',
  };

  return (
    <div ref={wrapperRef} style={{ marginBottom: '10px', position: 'relative' }}>
      {label && <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>{label}</label>}
      <input
        type="text"
        value={value}
        onChange={handleInputChange}
        onBlur={() => { setTimeout(() => { setShowDropdown(false); onChange(value); }, 200); }}
        onFocus={() => { if (suggestions.length > 0) setShowDropdown(true); }}
        placeholder={placeholder || 'Type to search pages, posts...'}
        style={{ width: '100%', padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px', boxSizing: 'border-box' }}
      />
      {loading && <div style={{ position: 'absolute', right: '8px', top: label ? '28px' : '8px', fontSize: '11px', color: '#94a3b8' }}>Searching...</div>}
      {showDropdown && suggestions.length > 0 && (
        <div style={{
          position: 'absolute', top: '100%', left: 0, right: 0, zIndex: 9999,
          background: '#fff', border: '1px solid #e2e8f0', borderRadius: '6px',
          boxShadow: '0 4px 12px rgba(0,0,0,.12)', maxHeight: '220px', overflowY: 'auto',
          marginTop: '2px'
        }}>
          {suggestions.map((s, i) => (
            <div
              key={i}
              onMouseDown={(e) => {
                e.preventDefault();
                setValue(s.url);
                onChange(s.url);
                setShowDropdown(false);
              }}
              style={{
                padding: '8px 12px', cursor: 'pointer', fontSize: '13px',
                borderBottom: i < suggestions.length - 1 ? '1px solid #f1f5f9' : 'none',
                display: 'flex', alignItems: 'center', gap: '8px',
              }}
              onMouseEnter={(e) => (e.currentTarget.style.background = '#f8fafc')}
              onMouseLeave={(e) => (e.currentTarget.style.background = '#fff')}
            >
              <span style={{ fontSize: '14px' }}>{typeIcons[s.type] || '🔗'}</span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 500, color: '#334155', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{s.title}</div>
                <div style={{ fontSize: '11px', color: '#94a3b8', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{s.url}</div>
              </div>
              <span style={{ fontSize: '10px', color: '#94a3b8', textTransform: 'uppercase', flexShrink: 0 }}>{s.type}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

// ─── Toast Notification Helper ───
const showToast = (message: string, type: 'success' | 'error' = 'success') => {
  if (typeof (window as any).o100ShowToast === 'function') {
    (window as any).o100ShowToast(message, type);
  } else {
    const el = document.createElement('div');
    el.innerText = message;
    el.style.position = 'fixed';
    el.style.bottom = '20px';
    el.style.right = '20px';
    el.style.backgroundColor = type === 'success' ? '#10b981' : '#ef4444';
    el.style.color = '#fff';
    el.style.padding = '12px 24px';
    el.style.borderRadius = '4px';
    el.style.zIndex = '999999';
    el.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
    el.style.fontSize = '14px';
    el.style.transition = 'opacity 0.3s ease';
    document.body.appendChild(el);
    setTimeout(() => {
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 300);
    }, 3000);
  }
};

// ─── Condition field definitions for Conditional Section ───
const CONDITION_FIELDS = [
  { group: '🍽️ Order100', fields: [
    { value: 'o100_order_method', label: 'Order Method', type: 'select', options: ['delivery', 'takeaway', 'dinein'] },
    { value: 'o100_prep_time', label: 'Prep Time', type: 'text' },
    { value: 'o100_timeslot', label: 'Time Slot', type: 'text' },
    { value: 'o100_tip_amount', label: 'Tip Amount', type: 'number' },
  ]},
  { group: '💰 Order', fields: [
    { value: 'order_total', label: 'Order Total', type: 'number' },
    { value: 'payment_method', label: 'Payment Method', type: 'text' },
    { value: 'order_status', label: 'Order Status', type: 'select', options: ['processing', 'completed', 'on-hold', 'cancelled', 'refunded', 'failed'] },
    { value: 'shipping_method', label: 'Shipping Method', type: 'text' },
    { value: 'coupon_used', label: 'Coupon Used', type: 'select', options: ['yes', 'no'] },
  ]},
  { group: '👤 Customer', fields: [
    { value: 'customer_order_count', label: 'Total Orders', type: 'number' },
    { value: 'customer_role', label: 'User Role', type: 'text' },
    { value: 'is_guest', label: 'Is Guest', type: 'select', options: ['yes', 'no'] },
  ]},
  { group: '📦 Content', fields: [
    { value: 'item_count', label: 'Item Count', type: 'number' },
    { value: 'has_category', label: 'Has Category', type: 'text' },
  ]},
];

const CONDITION_OPERATORS = [
  { value: 'equals', label: 'Is Equal To' },
  { value: 'not_equals', label: 'Is Not Equal To' },
  { value: 'contains', label: 'Contains' },
  { value: 'not_contains', label: 'Does Not Contain' },
  { value: 'greater_than', label: 'Is Greater Than' },
  { value: 'less_than', label: 'Is Less Than' },
  { value: 'is_empty', label: 'Is Empty' },
  { value: 'is_not_empty', label: 'Is Not Empty' },
];

const getConditionFieldDef = (fieldValue: string) => {
  for (const g of CONDITION_FIELDS) {
    const found = g.fields.find(f => f.value === fieldValue);
    if (found) return found;
  }
  return null;
};

const findMjText = (c: any): any => {
  if (!c) return null;
  if (c.get && c.get('type') === 'mj-text') return c;
  if (c.findType) {
    const res = c.findType('mj-text');
    if (res && res.length) return res[0];
  }
  const children = c.components ? c.components().models : [];
  for (let child of children) {
    const res = findMjText(child);
    if (res) return res;
  }
  return null;
};


const getDefaultTemplate = (id: string | undefined) => {
  const type = id || '';

  // ─── Helper: Marketing-style MJML template (no order details) ───
  const storeLogoUrl = (window as any).o100neData?.urls?.store_logo_url || 'http://localhost:10019/wp-content/uploads/2026/06/logo-placeholder-png-2.png';
  const marketingMjml = (heading: string, bodyContent: string, ctaLabel?: string, ctaHref?: string) => `
    <mjml>
      <mj-body background-color="#f4f4f7">
        <mj-section padding="20px 0 0 0">
          <mj-column>
            <mj-image src="${storeLogoUrl}" alt="logo" width="140px" align="center"></mj-image>
          </mj-column>
        </mj-section>
        <mj-section background-color="#ffffff" padding="30px 40px" border-radius="8px">
          <mj-column>
            <mj-text font-size="22px" color="#1e293b" font-weight="bold" padding-bottom="8px">
              ${heading}
            </mj-text>
            <mj-text font-size="15px" color="#475569" line-height="24px">
              ${bodyContent}
            </mj-text>
            ${ctaLabel ? `<mj-button background-color="#6A4BFF" color="#ffffff" border-radius="6px" font-size="15px" font-weight="600" padding="24px 0"${ctaHref ? ` href="${ctaHref}"` : ''}>${ctaLabel}</mj-button>` : ''}
          </mj-column>
        </mj-section>
        <mj-section padding="16px 0">
          <mj-column>
            <mj-text font-size="12px" color="#94a3b8" align="center">
              [o100_site_name] · If you have questions, reply to this email.
            </mj-text>
          </mj-column>
        </mj-section>
      </mj-body>
    </mjml>
  `;

  // ─── Reservation Templates ────────────────────────────────────
  if (type === 'o100_reservation_new') {
    return marketingMjml(
      'New Reservation Request 📋',
      `<p style="margin:0 0 12px;">You have received a new reservation request. Please review the details below and confirm or decline.</p>
       <table style="width:100%;border-collapse:collapse;margin:16px 0;">
         <tr><td style="padding:8px 0;color:#64748b;width:140px;">Guest Name:</td><td style="padding:8px 0;font-weight:600;">[o100_billing_first_name] [o100_billing_last_name]</td></tr>
         <tr><td style="padding:8px 0;color:#64748b;">Email:</td><td style="padding:8px 0;">[o100_billing_email]</td></tr>
         <tr><td style="padding:8px 0;color:#64748b;">Phone:</td><td style="padding:8px 0;">[o100_billing_phone]</td></tr>
         <tr><td style="padding:8px 0;color:#64748b;">Party Size:</td><td style="padding:8px 0;font-weight:600;">{o100_party_size} guests</td></tr>
         <tr><td style="padding:8px 0;color:#64748b;">Date &amp; Time:</td><td style="padding:8px 0;font-weight:600;">{o100_reservation_date} at {o100_reservation_time}</td></tr>
         <tr><td style="padding:8px 0;color:#64748b;">Special Requests:</td><td style="padding:8px 0;font-style:italic;">{o100_special_requests}</td></tr>
       </table>
       <p style="margin:12px 0 0;color:#64748b;">Please take action on this reservation as soon as possible.</p>`,
      'Review Reservation'
    );
  }

  if (type === 'o100_reservation_confirmed') {
    return marketingMjml(
      'Your Reservation is Confirmed! ✅',
      `<p style="margin:0 0 12px;">Dear [o100_billing_first_name],</p>
       <p style="margin:0 0 16px;">Great news! Your reservation at <strong>[o100_site_name]</strong> has been confirmed. Here are the details:</p>
       <table style="width:100%;border-collapse:collapse;margin:16px 0;background:#f8fafc;border-radius:6px;">
         <tr><td style="padding:10px 16px;color:#64748b;width:140px;">Date &amp; Time:</td><td style="padding:10px 16px;font-weight:600;">{o100_reservation_date} at {o100_reservation_time}</td></tr>
         <tr><td style="padding:10px 16px;color:#64748b;">Party Size:</td><td style="padding:10px 16px;font-weight:600;">{o100_party_size} guests</td></tr>
         <tr><td style="padding:10px 16px;color:#64748b;">Location:</td><td style="padding:10px 16px;">[o100_site_name]<br/>[o100_store_address]</td></tr>
       </table>
       <p style="margin:16px 0 0;"><strong>What to expect:</strong> Please arrive 5 minutes before your reservation time. Your table will be held for 15 minutes past the reservation time.</p>
       <p style="margin:12px 0 0;">We look forward to seeing you! 🍽️</p>`,
      'View Reservation'
    );
  }

  if (type === 'o100_reservation_rejected') {
    return marketingMjml(
      'Reservation Update',
      `<p style="margin:0 0 12px;">Dear [o100_billing_first_name],</p>
       <p style="margin:0 0 16px;">Unfortunately, we are unable to accommodate your reservation request for <strong>{o100_reservation_date}</strong> at <strong>{o100_reservation_time}</strong> for <strong>{o100_party_size} guests</strong>.</p>
       <p style="margin:0 0 12px;">We sincerely apologize for the inconvenience. This may be due to limited availability during the requested time slot.</p>
       <p style="margin:0 0 0;"><strong>What you can do:</strong></p>
       <ul style="color:#475569;padding-left:20px;">
         <li style="margin-bottom:6px;">Try a different date or time</li>
         <li style="margin-bottom:6px;">Consider a smaller party size</li>
         <li style="margin-bottom:6px;">Contact us directly for alternative options</li>
       </ul>
       <p style="margin:12px 0 0;">We hope to welcome you soon!</p>`,
      'Make a New Reservation',
      '[o100_site_url]'
    );
  }

  if (type === 'o100_reservation_reminder') {
    return marketingMjml(
      'Reservation Reminder — See You Tomorrow! 🍽️',
      `<p style="margin:0 0 12px;">Dear [o100_billing_first_name],</p>
       <p style="margin:0 0 16px;">Just a friendly reminder about your upcoming reservation at <strong>[o100_site_name]</strong>!</p>
       <table style="width:100%;border-collapse:collapse;margin:16px 0;background:#f8fafc;border-radius:6px;">
         <tr><td style="padding:10px 16px;color:#64748b;width:140px;">Date &amp; Time:</td><td style="padding:10px 16px;font-weight:600;">{o100_reservation_date} at {o100_reservation_time}</td></tr>
         <tr><td style="padding:10px 16px;color:#64748b;">Party Size:</td><td style="padding:10px 16px;font-weight:600;">{o100_party_size} guests</td></tr>
         <tr><td style="padding:10px 16px;color:#64748b;">Location:</td><td style="padding:10px 16px;">[o100_site_name]<br/>[o100_store_address]</td></tr>
       </table>
       <p style="margin:12px 0 0;">📌 Please arrive a few minutes early to ensure a smooth seating experience. If you need to cancel or modify your reservation, please contact us as soon as possible.</p>
       <p style="margin:12px 0 0;">We look forward to welcoming you!</p>`,
      'Get Directions',
      '[o100_site_url]'
    );
  }

  // ─── Loyalty Templates ────────────────────────────────────────
  if (type === 'o100_loyalty_birthday') {
    return marketingMjml(
      'Happy Birthday, {user_name}! 🎂🎉',
      `<p style="margin:0 0 12px;">Dear {user_name},</p>
       <p style="margin:0 0 16px;">Wishing you a wonderful birthday from everyone at <strong>[o100_site_name]</strong>! 🥳</p>
       <p style="margin:0 0 12px;">As a special birthday gift, we'd like to treat you to <strong>{discount_value}</strong> off. Use this exclusive code on your next order:</p>
       <div style="text-align:center;margin:20px 0;padding:16px;background:#f0ecff;border-radius:8px;border:2px dashed #6A4BFF;">
         <span style="font-size:24px;font-weight:700;color:#6A4BFF;letter-spacing:2px;">{coupon_code}</span>
       </div>
       <p style="margin:0 0 0;color:#64748b;font-size:13px;">🎁 Don't let your birthday treat expire!</p>`,
      'Order Now & Celebrate',
      '[o100_site_url]'
    );
  }

  if (type === 'o100_loyalty_points_earned') {
    return marketingMjml(
      'You Earned {points_earned} Points! ⭐',
      `<p style="margin:0 0 12px;">Dear {user_name},</p>
       <p style="margin:0 0 16px;">Thanks for your recent activity at <strong>[o100_site_name]</strong>! You just earned new loyalty points.</p>
       <div style="text-align:center;margin:20px 0;padding:24px;background:linear-gradient(135deg,#f0ecff 0%,#e8e0ff 100%);border-radius:12px;">
         <span style="font-size:14px;color:#64748b;display:block;margin-bottom:4px;">New Balance</span>
         <span style="font-size:36px;font-weight:700;color:#6A4BFF;">{total_points}</span>
       </div>
       <p style="margin:0 0 12px;">Keep collecting points with every order to unlock amazing rewards, discounts, and exclusive perks!</p>`,
      'View My Rewards',
      '[o100_user_account_url_string]'
    );
  }

  if (type === 'o100_loyalty_tier_upgrade') {
    return marketingMjml(
      'Congratulations! You\'ve Been Upgraded! 🏆',
      `<p style="margin:0 0 12px;">Dear {user_name},</p>
       <p style="margin:0 0 16px;">Amazing news — you've reached the <strong>{new_tier}</strong> tier at [o100_site_name]! 🎊</p>
       <div style="text-align:center;margin:20px 0;padding:24px;background:linear-gradient(135deg,#fef9c3 0%,#fde68a 100%);border-radius:12px;">
         <span style="font-size:40px;display:block;margin-bottom:8px;">👑</span>
         <span style="font-size:18px;font-weight:700;color:#92400e;">Welcome to {new_tier}!</span>
       </div>
       <p style="margin:0 0 12px;">As a valued member, you now enjoy exclusive tier benefits. Thank you for your continued loyalty!</p>`,
      'Explore My Benefits',
      '[o100_user_account_url_string]'
    );
  }

  if (type === 'o100_loyalty_reward_issued') {
    return marketingMjml(
      'You\'ve Earned a Reward! 🎁',
      `<p style="margin:0 0 12px;">Dear {user_name},</p>
       <p style="margin:0 0 16px;">Great news! You have a new <strong>{discount_value}</strong> reward waiting for you at <strong>[o100_site_name]</strong>.</p>
       <p style="margin:0 0 12px;">Use the code below on your next order to redeem your reward:</p>
       <div style="text-align:center;margin:20px 0;padding:16px;background:#ecfdf5;border-radius:8px;border:2px dashed #10b981;">
         <span style="font-size:24px;font-weight:700;color:#059669;letter-spacing:2px;">{coupon_code}</span>
       </div>
       <p style="margin:0 0 0;color:#64748b;font-size:13px;">⚠️ Hurry, this reward expires on <strong>{expiry_date}</strong>!</p>`,
      'Use My Reward Now',
      '[o100_site_url]'
    );
  }

  if (type === 'o100_loyalty_reward_expiring') {
    return marketingMjml(
      'Your Reward Expires Soon ⏰',
      `<p style="margin:0 0 12px;">Dear {user_name},</p>
       <p style="margin:0 0 16px;">Just a heads up — your <strong>{discount_value}</strong> reward at <strong>[o100_site_name]</strong> is expiring in <strong>{days_left} days</strong>! Don't miss out on this offer.</p>
       <div style="text-align:center;margin:20px 0;padding:16px;background:#fff7ed;border-radius:8px;border:2px dashed #f97316;">
         <span style="font-size:12px;color:#9a3412;display:block;margin-bottom:4px;">⚠️ EXPIRING SOON</span>
         <span style="font-size:24px;font-weight:700;color:#ea580c;letter-spacing:2px;">{coupon_code}</span>
       </div>
       <p style="margin:0 0 0;color:#64748b;font-size:13px;">Use your reward code before it expires to enjoy your well-deserved discount.</p>`,
      'Order Now Before It Expires',
      '[o100_site_url]'
    );
  }

  if (type === 'o100_loyalty_referral_invite') {
    return marketingMjml(
      'You\'ve Been Invited! 🤝',
      `<p style="margin:0 0 12px;">Hello!</p>
       <p style="margin:0 0 16px;">Your friend <strong>{advocate_name}</strong> thinks you'd love <strong>[o100_site_name]</strong> — and we agree! 😄</p>
       <p style="margin:0 0 12px;">As a welcome gift, use the exclusive link below to visit our store and claim your reward on your first order:</p>
       <div style="text-align:center;margin:20px 0;padding:16px;background:#eff6ff;border-radius:8px;border:2px dashed #3b82f6;">
         <span style="font-size:16px;font-weight:500;color:#2563eb;">{referral_link}</span>
       </div>
       <p style="margin:0 0 12px;">Click the button below to automatically apply your invite!</p>`,
      'Accept Invite & Order',
      '{referral_link}'
    );
  }

  if (type === 'o100_loyalty_referral_reward') {
    return marketingMjml(
      'Referral Reward Earned! 🎉',
      `<p style="margin:0 0 12px;">Dear {advocate_name},</p>
       <p style="margin:0 0 16px;">Your friend <strong>{friend_name}</strong> has placed an order at <strong>[o100_site_name]</strong> — thanks to your referral! 🙌</p>
       <p style="margin:0 0 12px;">As a thank you for spreading the word, you have earned:</p>
       <div style="text-align:center;margin:20px 0;padding:16px;background:#f0fdf4;border-radius:8px;border:2px dashed #22c55e;">
         <span style="font-size:24px;font-weight:700;color:#16a34a;letter-spacing:2px;">{reward_detail}</span>
       </div>
       <p style="margin:0 0 12px;">We've added this to your account. Keep referring friends to earn even more rewards!</p>`,
      'View My Rewards',
      '[o100_user_account_url_string]'
    );
  }

  // ─── Promo Templates ──────────────────────────────────────────
  if (type === 'o100_promo_win_back') {
    return marketingMjml(
      'We Miss You! Come Back for a Special Treat 💝',
      `<p style="margin:0 0 12px;">Dear {user_name},</p>
       <p style="margin:0 0 16px;">It's been <strong>{days_inactive} days</strong> since your last visit to <strong>[o100_site_name]</strong>, and we miss you! 😢</p>
       <p style="margin:0 0 12px;">We'd love to welcome you back with a special <strong>{discount_value}</strong> offer just for you:</p>
       <div style="text-align:center;margin:20px 0;padding:20px;background:linear-gradient(135deg,#fdf2f8 0%,#fce7f3 100%);border-radius:12px;border:2px dashed #ec4899;">
         <span style="font-size:14px;color:#9d174d;display:block;margin-bottom:4px;">YOUR EXCLUSIVE COMEBACK CODE</span>
         <span style="font-size:28px;font-weight:700;color:#db2777;letter-spacing:2px;">{coupon_code}</span>
       </div>
       <p style="margin:0 0 12px;">Whether you're craving your old favorites or want to try something new, we've got you covered.</p>`,
      'Order Now & Save',
      '[o100_site_url]'
    );
  }

  if (type === 'o100_promo_campaign') {
    return marketingMjml(
      'Special Offer Just for You! 🔥',
      `<p style="margin:0 0 12px;">Dear {user_name},</p>
       <p style="margin:0 0 16px;">Don't miss our <strong>{campaign_name}</strong> promotion at <strong>[o100_site_name]</strong>! For a limited time, enjoy <strong>{discount_value}</strong> off your next order.</p>
       <div style="text-align:center;margin:20px 0;padding:20px;background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);border-radius:12px;border:2px dashed #f59e0b;">
         <span style="font-size:14px;color:#92400e;display:block;margin-bottom:4px;">🎟️ LIMITED TIME OFFER</span>
         <span style="font-size:28px;font-weight:700;color:#d97706;letter-spacing:2px;">{coupon_code}</span>
       </div>
       <p style="margin:0 0 12px;">Apply this code at checkout and enjoy the savings. Share with friends and family — the more, the merrier!</p>`,
      'Shop Now',
      '[o100_site_url]'
    );
  }


  // ─── Per-Type MJML Templates (Order-based with header/footer) ─

  const templates: Record<string, { intro?: string; heading: string; body: string; cta?: string; ctaLabel?: string; showOrder?: boolean; showAddress?: boolean; showProducts?: boolean }> = {
    // === Admin Emails ===
    'new_order': {
      intro: 'Dear Store Admin,',
      heading: 'New Order Received!',
      body: 'You have received a new order from <strong>[o100_billing_first_name] [o100_billing_last_name]</strong>. Please prepare the order according to the requested time below.',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'View Order Dashboard',
      showOrder: true,
      showAddress: true,
      showProducts: true,
    },
    'cancelled_order': {
      intro: 'Dear Store Admin,',
      heading: 'Order Cancelled',
      body: 'Order #[o100_order_number] from [o100_billing_first_name] [o100_billing_last_name] has been cancelled.',
      showOrder: true,
      showAddress: false,
    },
    'failed_order': {
      intro: 'Dear Store Admin,',
      heading: 'Payment Failed',
      body: 'Payment for order #[o100_order_number] from [o100_billing_first_name] [o100_billing_last_name] has failed.',
      showOrder: true,
      showAddress: false,
    },

    // === Customer Emails ===
    'customer_processing_order': {
      intro: 'Dear [o100_billing_first_name],',
      heading: 'Order Confirmed!',
      body: 'Great news! Your order #[o100_order_number] has been received and is now being processed by our team.',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'Track Your Order',
      showOrder: true,
      showAddress: true,
    },
    'customer_completed_order': {
      intro: 'Dear [o100_billing_first_name],',
      heading: 'Order Ready / Completed!',
      body: 'Your order #[o100_order_number] is now complete. We hope you enjoy your meal!',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'View Your Order',
      showOrder: true,
      showAddress: true,
      showProducts: true,
    },
    'customer_on_hold_order': {
      intro: 'Dear [o100_billing_first_name],',
      heading: 'Order On Hold',
      body: 'Thank you for your order! Your order #[o100_order_number] is on hold until we confirm your payment.<br/><br/>[o100_payment_instruction]',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'View Order',
      showOrder: true,
      showAddress: true,
    },
    'customer_refunded_order': {
      intro: 'Dear [o100_billing_first_name],',
      heading: 'Refund Processed',
      body: 'Your order #[o100_order_number] has been refunded. The refund will be credited back to your original payment method within 5–10 business days.',
      showOrder: true,
    },
    'customer_cancelled_order': {
      intro: 'Dear [o100_billing_first_name],',
      heading: 'Your Order Has Been Cancelled',
      body: 'Your order #[o100_order_number] has been cancelled. If this was a mistake, please contact us.',
      cta: '[o100_site_url]',
      ctaLabel: 'Visit Store',
      showOrder: true,
    },
    'customer_failed_order': {
      intro: 'Dear [o100_billing_first_name],',
      heading: 'Payment Unsuccessful',
      body: 'Unfortunately, we couldn\'t process the payment for your order #[o100_order_number]. Please try again with a different payment method.',
      cta: '[o100_order_payment_url_string]',
      ctaLabel: 'Retry Payment',
      showOrder: true,
    },
    'customer_invoice': {
      intro: 'Dear [o100_billing_first_name],',
      heading: 'Invoice for Order #[o100_order_number]',
      body: 'Here are the details for your order placed on [o100_order_date].',
      cta: '[o100_order_payment_url_string]',
      ctaLabel: 'Pay for This Order',
      showOrder: true,
      showAddress: true,
    },
    'customer_note': {
      intro: 'Dear [o100_billing_first_name],',
      heading: 'A Note About Your Order',
      body: 'The following note has been added to your order #[o100_order_number]:<br/><br/><em style="color:#555;">[o100_customer_note]</em>',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'View Order',
    },
    'customer_reset_password': {
      intro: 'Hello,',
      heading: 'Password Reset Request',
      body: 'Someone has requested a new password for your account on [o100_site_name].<br/><br/>If you didn\'t make this request, you can safely ignore this email.',
      cta: '[o100_password_reset_url_string]',
      ctaLabel: 'Reset Password',
    },
    'customer_new_account': {
      intro: 'Hello,',
      heading: 'Welcome to [o100_site_name]!',
      body: 'Thanks for creating an account! Your username is: <strong>[o100_customer_username]</strong>.',
      cta: '[o100_user_account_url_string]',
      ctaLabel: 'Go to My Account',
    },

    // === Order Flow — Restaurant Specific ===
    'o100_order_ready': {
      intro: 'Dear [o100_billing_first_name],',
      heading: 'Your Order is Ready for Pickup! 🎉',
      body: 'Great news! Your order #[o100_order_number] has been prepared and is ready for pickup at our restaurant.<br/><br/><strong>Pickup Location:</strong><br/>[o100_site_name]<br/>[o100_store_address]<br/><br/>Please bring your order confirmation or mention your order number #[o100_order_number] at the counter.',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'View Order Details',
      showOrder: true,
    },
    'o100_out_for_delivery': {
      intro: 'Dear [o100_billing_first_name],',
      heading: 'Your Order is On Its Way! 🚗',
      body: 'Your order #[o100_order_number] is now out for delivery!<br/><br/><strong>Delivery Address:</strong><br/>[o100_shipping_address]<br/><br/>Please ensure someone is available at the delivery address. Our driver will arrive shortly.',
      cta: '[o100_view_order_url_string]',
      ctaLabel: 'Track Your Order',
      showOrder: true,
    },
    'o100_driver_dispatch': {
      intro: 'Hello Driver,',
      heading: 'New Delivery Assignment 📦',
      body: 'A new order #[o100_order_number] is ready for pickup and delivery.<br/><br/><strong>Restaurant:</strong> [o100_site_name]<br/>[o100_store_address]<br/><br/><strong>Deliver to:</strong><br/>[o100_shipping_address]<br/><br/><strong>Customer Phone:</strong> [o100_billing_phone]<br/><strong>Customer Name:</strong> [o100_billing_first_name] [o100_billing_last_name]',
      showOrder: true,
    },
  };

  const cfg = templates[type] || templates['new_order']!;
  const introText = cfg.intro || 'Dear [o100_customer_first_name],';

  // 1. Header (Logo & Hero)
  const headerHtml = `
    <mj-raw></mj-raw>
    <mj-section padding-bottom="0px">
      <mj-column>
        <mj-image src="${storeLogoUrl}" alt="logo" width="160px" padding-top="0px" padding-bottom="0px"></mj-image>
      </mj-column>
    </mj-section>
    <mj-section padding-top="0px" padding-bottom="0px">
      <mj-column>
        <mj-image src="http://localhost:10019/wp-content/uploads/2026/06/different-poke-bowls-on-blue-background-top-view-2026-03-20-03-26-50-utc.webp" padding-top="0px" padding-bottom="0px" padding-right="0px" padding-left="0px" alt="banner"></mj-image>
      </mj-column>
    </mj-section>
  `;

  // 2. Greeting & Body
  const greetingHtml = `
    <mj-section padding-top="0px" padding-bottom="0px">
      <mj-column>
        <mj-text>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">${introText}</span></p>
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
          <span style="font-size:16px;line-height:25.6px;">Order Type: [o100_order_method]</span><br/>
          <span style="font-size:16px;line-height:25.6px;">Payment Method: [o100_order_payment_method]</span>
        </mj-text>
        ${cfg.cta ? `<mj-button href="${cfg.cta}" background-color="#f60909" font-size="18px">${cfg.ctaLabel}</mj-button>` : ''}
        <mj-divider border-width="1px" border-color="#bcacac"></mj-divider>
      </mj-column>
    </mj-section>
  ` : '';

  // 4. Conditional Order Methods (Delivery / Pickup / Dine-in)
  const methodConditionalHtml = cfg.showAddress ? `
    <!-- IF: Delivery -->
    <mj-section css-class="o100ne-conditional" data-condition-field="o100_order_method" data-condition-operator="equals" data-condition-value="delivery" padding-top="0px">
      <mj-column>
        <mj-text font-size="17px">
          <p style="line-height:160%;"><strong><span style="font-size:16px;line-height:25.6px;">Delivery Information</span></strong></p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Delivery Address:</span><br/>[o100_shipping_address]</p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Scheduled Delivery Time:</span><br/>[o100_prep_time]</p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Please ensure someone is available at the delivery address during the scheduled time window.</span></p>
        </mj-text>
        <mj-divider border-width="1px" border-color="#bcacac"></mj-divider>
      </mj-column>
    </mj-section>

    <!-- IF: Pickup / Takeaway -->
    <mj-section css-class="o100ne-conditional" data-condition-field="o100_order_method" data-condition-operator="equals" data-condition-value="pickup" padding-top="0px">
      <mj-column>
        <mj-text font-size="17px">
          <p style="line-height:160%;"><strong><span style="font-size:16px;line-height:25.6px;">Pickup Information</span></strong></p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Pickup Location:</span><br/>[o100_site_name]<br/>[o100_store_address]</p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Scheduled Pickup Time:</span><br/>[o100_prep_time]</p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Please arrive at the restaurant at the scheduled pickup time. Provide your order number #[o100_order_number] at the counter.</span></p>
        </mj-text>
        <mj-divider border-width="1px" border-color="#bcacac"></mj-divider>
      </mj-column>
    </mj-section>

    <!-- IF: Dine-in -->
    <mj-section css-class="o100ne-conditional" data-condition-field="o100_order_method" data-condition-operator="equals" data-condition-value="dinein" padding-top="0px">
      <mj-column>
        <mj-text font-size="17px">
          <p style="line-height:160%;"><strong><span style="font-size:16px;line-height:25.6px;">Dine-in Information</span></strong></p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Restaurant Location:</span><br/>[o100_site_name]<br/>[o100_store_address]</p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">Arrival Time:</span><br/>[o100_prep_time]</p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">We look forward to serving you! Please show this email to our staff when you arrive.</span></p>
        </mj-text>
        <mj-divider border-width="1px" border-color="#bcacac"></mj-divider>
      </mj-column>
    </mj-section>
  ` : '';

  // 5. Footer & Contacts
  const footerHtml = `
    <mj-section padding-top="0px">
      <mj-column>
        <mj-text>
          <p style="line-height:160%;"><strong><span style="font-size:16px;line-height:25.6px;">Order Changes or Questions</span></strong></p>
          <p style="line-height:160%;"><span style="font-size:16px;line-height:25.6px;">If you need to update instructions or have any concerns, please contact us as soon as possible.</span></p>
          <p style="line-height:160%;">
            <span style="font-size:16px;line-height:25.6px;">📞 Phone: <a href="tel:+11111111111">(+1) 111-111-1111</a></span><br/>
            <span style="font-size:16px;line-height:25.6px;">🌐 <a href="[o100_site_url]">[o100_site_url]</a></span>
          </p>
        </mj-text>
        <mj-text>
          <span style="font-size:18px;line-height:28.8px;">Thank you for choosing [o100_site_name]. We appreciate your support!</span>
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
        ${methodConditionalHtml}
        ${footerHtml}
        ${productsHtml}
      </mj-body>
    </mjml>
  `;
};



const SHORTCODE_CATEGORIES = [
  {
    title: 'Store Details',
    items: [
      { code: '[o100_site_name]', desc: 'Store Name' },
      { code: '[o100_store_logo_url]', desc: 'Store Logo URL' },
      { code: '[o100_site_link]', desc: 'Store Link' },
      { code: '[o100_domain]', desc: 'Store Domain' },
      { code: '[o100_store_name]', desc: 'Restaurant Name' },
      { code: '[o100_store_phone]', desc: 'Restaurant Phone' },
      { code: '[o100_store_address]', desc: 'Restaurant Address' },
      { code: '[o100_branch_name]', desc: 'Branch Name' },
      { code: '[o100_branch_phone]', desc: 'Branch Phone' },
      { code: '[o100_branch_address]', desc: 'Branch Address' },
    ]
  },
  {
    title: 'Customer Details',
    items: [
      { code: '[o100_customer_name]', desc: 'Full Name' },
      { code: '[o100_customer_first_name]', desc: 'First Name' },
      { code: '[o100_customer_last_name]', desc: 'Last Name' },
      { code: '[o100_user_email]', desc: 'Email Address' },
      { code: '[o100_customer_phone]', desc: 'Phone Number' },
      { code: '[o100_customer_birthday]', desc: 'Birthday' },
      { code: '[o100_customer_username]', desc: 'Username' },
      { code: '[o100_user_id]', desc: 'User ID' },
      { code: '[o100_customer_total_orders]', desc: 'Total Orders' },
      { code: '[o100_customer_provided_note]', desc: 'Customer Checkout Note' },
    ]
  },
  {
    title: 'Order Details',
    items: [
      { code: '[o100_order_number]', desc: 'Order Number' },
      { code: '[o100_order_id]', desc: 'Order ID' },
      { code: '[o100_order_date]', desc: 'Order Date' },
      { code: '[o100_order_type]', desc: 'Order Method (Delivery/Pickup/Dine-in)' },
      { code: '[o100_date_deli]', desc: 'Delivery/Pickup Date' },
      { code: '[o100_time_deli]', desc: 'Delivery/Pickup Time' },
      { code: '[o100_prep_time]', desc: 'Prep Time' },
      { code: '[o100_order_sub_total]', desc: 'Order Sub-Total' },
      { code: '[o100_order_fee]', desc: 'Order Fee' },
      { code: '[o100_discount_amount]', desc: 'Discount Amount' },
      { code: '[o100_order_refund]', desc: 'Order Refunds' },
      { code: '[o100_order_total]', desc: 'Order Total' },
      { code: '[o100_order_link]', desc: 'Order Link' },
      { code: '[o100_order_coupon_codes]', desc: 'Used Coupon Codes' },
      { code: '[woocommerce_email_order_meta]', desc: 'Order Meta Data' },
    ]
  },
  {
    title: 'Order Items',
    items: [
      { code: '[o100_items]', desc: 'Order Items' },
      { code: '[o100_items_products_quantity_price]', desc: 'Items List (Qty & Price)' },
      { code: '[o100_items_border]', desc: 'Items With Border' },
      { code: '[o100_orders_count]', desc: 'Items Count' },
      { code: '[o100_quantity_count]', desc: 'Quantity Count' },
      { code: '[o100_orders_count_double]', desc: 'Items Count (Double)' },
    ]
  },
  {
    title: 'Billing Details',
    items: [
      { code: '[o100_billing_first_name]', desc: 'First Name' },
      { code: '[o100_billing_last_name]', desc: 'Last Name' },
      { code: '[o100_billing_company]', desc: 'Company' },
      { code: '[o100_billing_phone]', desc: 'Phone' },
      { code: '[o100_billing_email]', desc: 'Email' },
      { code: '[o100_billing_address]', desc: 'Full Address' },
      { code: '[o100_billing_address_1]', desc: 'Address Line 1' },
      { code: '[o100_billing_address_2]', desc: 'Address Line 2' },
      { code: '[o100_billing_city]', desc: 'City' },
      { code: '[o100_billing_state]', desc: 'State' },
      { code: '[o100_billing_postcode]', desc: 'Post Code' },
      { code: '[o100_billing_country]', desc: 'Country' },
    ]
  },
  {
    title: 'Shipping Details',
    items: [
      { code: '[o100_shipping_first_name]', desc: 'First Name' },
      { code: '[o100_shipping_last_name]', desc: 'Last Name' },
      { code: '[o100_shipping_company]', desc: 'Company' },
      { code: '[o100_shipping_phone]', desc: 'Phone' },
      { code: '[o100_delivery_instruction]', desc: 'Delivery Instruction' },
      { code: '[o100_shipping_method]', desc: 'Shipping Method' },
      { code: '[o100_order_shipping]', desc: 'Shipping Total' },
      { code: '[o100_shipping_address]', desc: 'Full Address' },
      { code: '[o100_billing_shipping_address]', desc: 'Billing / Shipping Address' },
      { code: '[o100_shipping_address_1]', desc: 'Address Line 1' },
      { code: '[o100_shipping_address_2]', desc: 'Address Line 2' },
      { code: '[o100_shipping_city]', desc: 'City' },
      { code: '[o100_shipping_state]', desc: 'State' },
      { code: '[o100_shipping_postcode]', desc: 'Postal Code' },
      { code: '[o100_shipping_country]', desc: 'Country' },
    ]
  },
  {
    title: 'Payment Details',
    items: [
      { code: '[o100_payment_method]', desc: 'Payment Method' },
      { code: '[o100_transaction_id]', desc: 'Transaction ID' },
      { code: '[o100_payment_instruction]', desc: 'Payment Instruction' },
      { code: '[o100_order_payment_url]', desc: 'Payment URL' },
      { code: '[o100_order_payment_url_string]', desc: 'Payment URL (Text)' },
    ]
  },
  {
    title: 'Loyalty & Referral',
    items: [
      { code: '[o100_loyalty_points]', desc: 'Points (This Transaction)' },
      { code: '[o100_loyalty_points_earned]', desc: 'Points Earned' },
      { code: '[o100_loyalty_balance]', desc: 'Points Balance' },
      { code: '{user_name}', desc: 'Customer Name (Loyalty Emails)' },
      { code: '{advocate_name}', desc: 'Referrer Name' },
      { code: '{friend_name}', desc: 'Referred Friend Name' },
      { code: '{referral_link}', desc: 'Referral Unique URL' },
      { code: '{reward_detail}', desc: 'Referral Reward Description' },
      { code: '{points_earned}', desc: 'Points Earned (Notification)' },
      { code: '{total_points}', desc: 'Total Points (Notification)' },
      { code: '{new_tier}', desc: 'New Tier Name' },
      { code: '{coupon_code}', desc: 'Reward Coupon Code' },
      { code: '{discount_value}', desc: 'Reward Discount Value' },
      { code: '{expiry_date}', desc: 'Reward Expiry Date' },
      { code: '{days_left}', desc: 'Reward Days Left' },
    ]
  },
  {
    title: 'Reservation',
    items: [
      { code: '[o100_reservation_id]', desc: 'Reservation ID' },
      { code: '[o100_reservation_date]', desc: 'Reservation Date' },
      { code: '[o100_reservation_time]', desc: 'Reservation Time' },
      { code: '[o100_reservation_party_size]', desc: 'Party Size' },
      { code: '[o100_reservation_guest_name]', desc: 'Guest Name' },
      { code: '[o100_reservation_guest_email]', desc: 'Guest Email' },
      { code: '[o100_reservation_guest_phone]', desc: 'Guest Phone' },
      { code: '[o100_reservation_special_requests]', desc: 'Special Requests' },
      { code: '[o100_reservation_status]', desc: 'Reservation Status' },
    ]
  },
  {
    title: 'Promo & Campaign',
    items: [
      { code: '{campaign_name}', desc: 'Campaign Name' },
      { code: '{days_inactive}', desc: 'Days Inactive (Win-Back)' },
      { code: '{coupon_code}', desc: 'Promo Coupon Code' },
      { code: '{discount_value}', desc: 'Promo Coupon Value' },
    ]
  },
  {
    title: 'User Account',
    items: [
      { code: '[o100_user_account_link]', desc: 'Account Link' },
      { code: '[o100_set_password_link]', desc: 'Set Password Link' },
      { code: '[o100_password_reset_link]', desc: 'Reset Password Link' },
      { code: '[o100_additional_content]', desc: 'Additional Content' },
    ]
  }
];

const Editor: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const o100neData = (window as any).o100neData || {};
  const wcEmails = Object.values(o100neData.wc_emails || {});
  const listOrders = Object.values(o100neData.list_orders || {});

  const editorRef = useRef<HTMLDivElement>(null);
  const [editor, setEditor] = useState<GjsEditor | null>(null);
  const [activeTab, setActiveTab] = useState<'blocks' | 'styles' | 'global'>('blocks');
  const [selectedComponent, setSelectedComponent] = useState<any>(null);

  // Modals State
  const [isShortcodesModalOpen, setIsShortcodesModalOpen] = useState(false);
  const [activeRte, setActiveRte] = useState<any>(null);
  const [openShortcodeCategories, setOpenShortcodeCategories] = useState<number[]>(SHORTCODE_CATEGORIES.map((_, i) => i)); // All open by default
  const [isPreviewOpen, setIsPreviewOpen] = useState(false);
  const [previewHtml, setPreviewHtml] = useState('');
  const [previewOrder, setPreviewOrder] = useState('sample_order');
  const [testEmail, setTestEmail] = useState('');

  // Template Library State
  const [isLibraryModalOpen, setIsLibraryModalOpen] = useState(false);
  const [templateToDelete, setTemplateToDelete] = useState<{ id: string; name: string } | null>(null);
  const [isExportModalOpen, setIsExportModalOpen] = useState(false);
  const [exportTemplateName, setExportTemplateName] = useState('');
  const [templateLibraryList, setTemplateLibraryList] = useState<any[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [hasChanges, setHasChanges] = useState(false);
  const [sidebarKey, setSidebarKey] = useState(0);
  const forceRefreshSidebar = () => setSidebarKey(k => k + 1);
  const [templateNumericId, setTemplateNumericId] = useState<number | null>(null);
  const [isTemplateEnabled, setIsTemplateEnabled] = useState(false);

  // Global bridge for GrapesJS RTE plugin to open Shortcodes modal
  useEffect(() => {
    (window as any).openO100neShortcodesModal = (rte: any) => {
      // Get selection from the iframe document where GrapesJS RTE lives,
      // NOT from the parent window — fixes cursor position issue
      const iframeDoc = rte?.el?.ownerDocument || document;
      const sel = iframeDoc.defaultView?.getSelection() || window.getSelection();
      if (sel && sel.rangeCount > 0) {
        (window as any)._o100neSavedRange = sel.getRangeAt(0).cloneRange();
        (window as any)._o100neSavedDoc = iframeDoc;
      }
      setActiveRte(rte);
      setIsShortcodesModalOpen(true);
    };
    return () => {
      delete (window as any).openO100neShortcodesModal;
    };
  }, []);

  const fetchCurrentTemplate = () => {
    setIsLoading(true);
    const restPath = (window as any).o100neData?.rest_path || {};
    const url = `${restPath.root}${restPath.base}/templates/get-template-by-name?template_name=${id}`;

    fetch(url, {
      method: 'GET',
      headers: { 'X-WP-Nonce': restPath.nonce },
      cache: 'no-store'
    })
      .then(res => res.json())
      .then(res => {
        if (res && res.id) {
          setTemplateNumericId(res.id);
          setIsTemplateEnabled(res.status === 'active');
          console.log('--- FETCHED TEMPLATE ---');
          console.log(res.elements);

          if (res.elements && typeof res.elements === 'string' && !res.elements.trim().startsWith('[')) {
            // It's MJML string
            (window as any)._loadedTemplateMjml = res.elements;
          } else {
            (window as any)._loadedTemplateMjml = getDefaultTemplate(id || 'new_order');
          }
        } else {
          (window as any)._loadedTemplateMjml = getDefaultTemplate(id || 'new_order');
        }
      })
      .catch(err => {
        console.error(err);
        (window as any)._loadedTemplateMjml = getDefaultTemplate(id || 'new_order');
      })
      .finally(() => {
        setIsLoading(false);
      });
  };

  const saveTemplate = () => {
    if (!editor || !templateNumericId) return;

    setIsSaving(true);

    // Temporarily replace preview tables with shortcodes before exporting
    const wrapper = editor.getWrapper();
    if (!wrapper) {
      setIsSaving(false);
      return;
    }

    const previews: any = {};

    const orderDetails = findAllByCssClass(wrapper, 'woo-order-detail');
    const fullOrderDetails = findAllByCssClass(wrapper, 'woo-full-order-detail');
    [...orderDetails, ...fullOrderDetails].forEach((comp: any) => {
      const isFull = comp.getAttributes()['css-class']?.includes('woo-full-order-detail');

      let textComp = comp.get('type') === 'mj-text' ? comp : findMjText(comp);
      if (textComp) {
        const htmlContent = textComp.components().models.map((m: any) => m.toHTML()).join('');
        
        // Prevent converting paragraphs that user added by deleting the order detail table
        if (!htmlContent.includes('<table') && !htmlContent.includes('<th') && !htmlContent.includes('[o100_order_details')) {
          return;
        }

        previews[comp.getId()] = htmlContent;

        const attrs = comp.getAttributes();
        let shortcode = '[o100_order_details';

        // Extract headers from HTML if possible
        try {
          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = htmlContent;
          const ths = tempDiv.querySelectorAll('th');
          if (ths.length >= 2) {
            // First header might be empty if image is shown, or it's the product
            let prodIndex = attrs['data-show-image'] === 'true' ? 1 : 0;
            if (ths[prodIndex]) shortcode += ` product_title="${ths[prodIndex].innerText.trim()}"`;
            if (ths[prodIndex + 1]) shortcode += ` quantity_title="${ths[prodIndex + 1].innerText.trim()}"`;
            if (ths[prodIndex + 2]) shortcode += ` price_title="${ths[prodIndex + 2].innerText.trim()}"`;
          }
        } catch (e) {
          // Fallback to attributes if extraction fails
          if (attrs['data-text-product']) shortcode += ` product_title="${attrs['data-text-product']}"`;
          if (attrs['data-text-qty']) shortcode += ` quantity_title="${attrs['data-text-qty']}"`;
          if (attrs['data-text-price']) shortcode += ` price_title="${attrs['data-text-price']}"`;
        }

        // Add display flags
        if (attrs['data-show-image'] === 'true') shortcode += ` show_image="true"`;
        if (attrs['data-show-sku'] === 'false') shortcode += ` show_sku="false"`;
        if (attrs['data-show-price'] === 'false') shortcode += ` show_price="false"`;

        shortcode += ']';
        textComp.components(shortcode);
      }
    });

    // Swap Products
    const products = findAllByCssClass(wrapper, 'woo-products');
    products.forEach((comp: any) => {
      let textComp = comp.get('type') === 'mj-text' ? comp : findMjText(comp);
      if (textComp) {
        previews[comp.getId()] = textComp.components().models.map((m: any) => m.toHTML()).join('');
        const attrs = comp.getAttributes();
        let shortcode = '[o100_products';
        if (attrs['data-product-type']) shortcode += ` product_type="${attrs['data-product-type']}"`;
        if (attrs['data-specific-ids']) shortcode += ` specific_ids="${attrs['data-specific-ids']}"`;
        if (attrs['data-exclude-ids']) shortcode += ` exclude_ids="${attrs['data-exclude-ids']}"`;
        if (attrs['data-max-rows']) shortcode += ` max_rows="${attrs['data-max-rows']}"`;
        if (attrs['data-columns']) shortcode += ` columns="${attrs['data-columns']}"`;
        if (attrs['data-add-to-cart-url'] === 'true') shortcode += ` add_to_cart_url="true"`;
        if (attrs['data-show-cart-btn'] === 'true') shortcode += ` show_cart_btn="true"`;
        if (attrs['data-title-size']) shortcode += ` title_size="${attrs['data-title-size']}"`;
        if (attrs['data-title-color']) shortcode += ` title_color="${attrs['data-title-color']}"`;
        if (attrs['data-price-size']) shortcode += ` price_size="${attrs['data-price-size']}"`;
        if (attrs['data-price-color']) shortcode += ` price_color="${attrs['data-price-color']}"`;
        if (attrs['data-vertical-distance']) shortcode += ` distance="${attrs['data-vertical-distance']}"`;
        if (attrs['data-show-image'] === 'false') shortcode += ` show_image="false"`;
        if (attrs['data-show-sku'] === 'false') shortcode += ` show_sku="false"`;
        if (attrs['data-show-price'] === 'false') shortcode += ` show_price="false"`;
        if (attrs['data-remove-link'] === 'true') shortcode += ` remove_link="true"`;
        if (attrs['data-image-size']) shortcode += ` image_size="${attrs['data-image-size']}"`;
        shortcode += ']';
        textComp.components(shortcode);
      }
    });

    // Swap Order Subtotals
    const orderSubtotals = findAllByCssClass(wrapper, 'woo-order-subtotal');
    orderSubtotals.forEach((comp: any) => {
      let textComp = comp.get('type') === 'mj-text' ? comp : findMjText(comp);
      if (textComp) {
        previews[comp.getId()] = textComp.components().models.map((m: any) => m.toHTML()).join('');
        const attrs = comp.getAttributes();
        let shortcode = '[o100_order_subtotal';
        if (attrs['data-text-subtotal']) shortcode += ` text_subtotal="${attrs['data-text-subtotal']}"`;
        if (attrs['data-text-shipping']) shortcode += ` text_shipping="${attrs['data-text-shipping']}"`;
        if (attrs['data-text-discount']) shortcode += ` text_discount="${attrs['data-text-discount']}"`;
        if (attrs['data-text-tax']) shortcode += ` text_tax="${attrs['data-text-tax']}"`;
        if (attrs['data-text-refund']) shortcode += ` text_refund="${attrs['data-text-refund']}"`;
        if (attrs['data-text-fee']) shortcode += ` text_fee="${attrs['data-text-fee']}"`;
        shortcode += ']';
        textComp.components(shortcode);
      }
    });

    // Inject conditional data into css-class so it survives MJML compilation
    const allComps: any[] = [];
    const walk = (comp: any) => {
      allComps.push(comp);
      comp.components().forEach(walk);
    };
    walk(wrapper);

    let condFound = 0;
    allComps.forEach((comp: any) => {
      const attrs = comp.getAttributes();
      const f = attrs['data-condition-field'] || '';
      const o = attrs['data-condition-operator'] || '';
      const v = attrs['data-condition-value'] || '';
      if (f) {
        condFound++;
        let css = attrs['css-class'] || '';
        css = css.replace(/cond_o100_[^\s]+/g, '').trim();
        const jsonStr = JSON.stringify({ f, o, v });
        const base64 = btoa(encodeURIComponent(jsonStr).replace(/%([0-9A-F]{2})/g, (m, p1) => String.fromCharCode(parseInt(p1, 16)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        css += ' cond_o100_' + base64;
        comp.addAttributes({ 'css-class': css.trim() });
      }
    });
    console.log('[Editor] Total conditional sections injected:', condFound);

    (window as any).isExporting = true;
    let mjmlCode = editor.runCommand('mjml-get-code');
    if (!mjmlCode || !mjmlCode.html) {
      mjmlCode = editor.runCommand('mjml-code-to-html');
    }
    
    const mjml = mjmlCode && mjmlCode.mjml ? mjmlCode.mjml : editor.getHtml();
    
    let template_html = '';
    if (mjmlCode && mjmlCode.html) {
      template_html = mjmlCode.html;
    } else {
      template_html = editor.getHtml() + '<style>' + editor.getCss() + '</style>';
    }
    (window as any).isExporting = false;

    console.log('--- SAVING TEMPLATE ---');
    console.log(mjml);

    orderDetails.forEach((comp: any) => {
      let textComp = comp.get('type') === 'mj-text' ? comp : findMjText(comp);
      if (textComp && previews[comp.getId()]) {
        textComp.components(previews[comp.getId()]);
      }
    });
    products.forEach((comp: any) => {
      let textComp = comp.get('type') === 'mj-text' ? comp : findMjText(comp);
      if (textComp && previews[comp.getId()]) {
        textComp.components(previews[comp.getId()]);
      }
    });
    orderSubtotals.forEach((comp: any) => {
      let textComp = comp.get('type') === 'mj-text' ? comp : findMjText(comp);
      if (textComp && previews[comp.getId()]) {
        textComp.components(previews[comp.getId()]);
      }
    });

    if (!mjml) return;

    const restPath = (window as any).o100neData?.rest_path || {};
    const url = `${restPath.root}${restPath.base}/templates/${templateNumericId}`;

    const bodyData = {
      template_id: templateNumericId,
      template_elements: mjml,
      template_elements_type: 'mjml',
      template_html: template_html
    };

    fetch(url, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': restPath.nonce
      },
      body: JSON.stringify({ data: bodyData })
    })
      .then(res => res.json())
      .then(_res => {
        setHasChanges(false);
        showToast('Template saved successfully!', 'success');
      })
      .catch(err => {
        console.error('Save error:', err);
        showToast('Failed to save template. Please check the console.', 'error');
      })
      .finally(() => {
        setIsSaving(false);
      });
  };

  const fetchTemplateLibrary = () => {
    const restPath = (window as any).o100neData?.rest_path || {};
    const url = `${restPath.root}${restPath.base}/template-library?email_type=new_order`;

    fetch(url, {
      method: 'GET',
      headers: { 'X-WP-Nonce': restPath.nonce },
      cache: 'no-store'
    })
      .then(res => res.json())
      .then(res => {
        if (res.success && res.templates) {
          setTemplateLibraryList(res.templates);
        }
      }).catch(err => console.error(err));
  };

  useEffect(() => {
    fetchCurrentTemplate();
    fetchTemplateLibrary();
  }, [id]);

  const saveToTemplateLibrary = () => {
    if (!exportTemplateName.trim()) {
      showToast('Please enter a template name', 'error');
      return;
    }
    const html = editor?.getHtml();
    if (!html) return;

    const restPath = (window as any).o100neData?.rest_path || {};
    const url = `${restPath.root}${restPath.base}/template-library`;

    const params = new URLSearchParams();
    params.append('name', exportTemplateName.trim());
    params.append('mjml', html);

    fetch(url, {
      method: 'POST',
      body: params,
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-WP-Nonce': restPath.nonce
      }
    })
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          setTemplateLibraryList(res.templates);
          setIsExportModalOpen(false);
          setExportTemplateName('');
        } else {
          showToast('Failed to save template: ' + JSON.stringify(res), 'error');
        }
      }).catch(err => console.error(err));
  };

  const executeDeleteTemplate = () => {
    if (!templateToDelete) return;
    const restPath = (window as any).o100neData?.rest_path || {};
    const url = `${restPath.root}${restPath.base}/template-library?id=${templateToDelete.id}`;

    fetch(url, {
      method: 'DELETE',
      headers: { 'X-WP-Nonce': restPath.nonce }
    })
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          setTemplateLibraryList(res.templates);
          setTemplateToDelete(null);
        }
      }).catch(err => console.error(err));
  };

  const loadTemplateFromLibrary = (mjml: string) => {
    if (!editor || !mjml) return;
    editor.setComponents(mjml);
    setIsLibraryModalOpen(false);
  };

  const parsePreviewShortcodes = (html: string) => {
    let parsed = html;
    const orderData = (window as any)._previewOrderData || {};

    const mapping: Record<string, string> = {
      '[o100_customer_name]': (orderData.billing_first_name || orderData.billing_last_name) ? `${orderData.billing_first_name || ''} ${orderData.billing_last_name || ''}`.trim() : (orderData.billing_address ? orderData.billing_address.split('<br/>')[0] : 'Customer'),
      '[o100_customer_first_name]': orderData.billing_first_name || (orderData.billing_address ? orderData.billing_address.split('<br/>')[0].split(' ')[0] : 'Customer'),
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
      '[o100_delivery_instruction]': orderData.meta?._o100_delivery_instruction || orderData.meta?.o100_delivery_instruction || 'Please leave at the door.',
      '[o100_date_deli]': (function(){
        const rawDate = orderData.meta?._o100_date_deli || orderData.meta?.o100_date_deli;
        if (!rawDate) return new Date().toLocaleDateString();
        // If it's a UNIX timestamp (10 digits starting with 1)
        if (/^1\d{9}$/.test(rawDate)) {
          const d = new Date(parseInt(rawDate, 10) * 1000);
          return d.toLocaleDateString();
        }
        // If it's a string of 8 digits like 20260625, format it
        if (/^\d{8}$/.test(rawDate)) {
          const d = new Date(`${rawDate.substring(0,4)}-${rawDate.substring(4,6)}-${rawDate.substring(6,8)}T00:00:00`);
          return d.toLocaleDateString();
        }
        return rawDate;
      })(),
      '[o100_time_deli]': orderData.meta?._o100_time_deli || orderData.meta?.o100_time_deli || '18:30',
      '[o100_shipping_address]': orderData.shipping_address || 'N/A',
      '[o100_billing_address]': orderData.billing_address || 'N/A',
      '[o100_billing_phone]': orderData.billing_phone || 'N/A',
      '[o100_order_type]': orderData.meta?._o100_order_type || orderData.meta?._o100_order_method || 'Delivery',
      // --- Added missing shortcode mappings ---
      '[o100_store_name]': (function() {
        const profile = (window as any).o100neData?.store_profile;
        if (profile && profile.name) return profile.name;
        return (window as any).o100neData?.site_name || 'Our Store';
      })(),
      '[o100_store_phone]': (function() {
        const profile = (window as any).o100neData?.store_profile;
        return profile?.phone || '';
      })(),
      '[o100_store_hours]': (function() {
        const profile = (window as any).o100neData?.store_profile;
        return profile?.hours || '';
      })(),
      '[o100_payment_method]': orderData.payment_method || orderData.payment_method_title || 'N/A',
      '[o100_order_payment_method]': orderData.payment_method || orderData.payment_method_title || 'N/A',
      '[o100_prep_time]': orderData.meta?.o100_prep_time || orderData.meta?._o100_prep_time || '',
      '[o100_estimated_ready]': orderData.meta?.o100_estimated_ready || orderData.meta?._o100_estimated_ready || '',
      '[o100_order_sub_total]': orderData.subtotal || '',
      '[o100_order_total]': orderData.total || '',
      '[o100_order_fee]': orderData.total_fee || '',
      '[o100_discount_amount]': orderData.total_discount || '',
      '[o100_order_shipping]': orderData.total_shipping || '',
      '[o100_delivery_address]': orderData.shipping_address || orderData.billing_address || 'N/A',
      '[o100_delivery_notes]': orderData.meta?._o100_delivery_instruction || orderData.meta?.o100_delivery_instruction || '',
      '[o100_order_tips]': orderData.meta?.o100_tips || orderData.meta?._o100_tips || '',
      '[o100_order_location]': orderData.meta?.o100_location_name || orderData.meta?._o100_location_name || '',
      '[o100_transaction_id]': orderData.transaction_id || '',
      '[o100_order_id]': orderData.id !== undefined ? String(orderData.id) : '#0000',
      '[o100_order_link]': orderData.admin_url || '#',
      '[o100_customer_provided_note]': orderData.customer_note || '',
    };

    Object.keys(mapping).forEach(key => {
      // Handle HTML tags inside shortcodes e.g. [o100_customer_<b>name</b>]
      const escapedKey = key.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
      const regexStr = escapedKey.replace(/([a-zA-Z0-9_])/g, '$1(?:<[^>]+>)*');
      const regex = new RegExp(regexStr, 'g');
      parsed = parsed.replace(regex, mapping[key]);
      parsed = parsed.split(key).join(mapping[key]);
    });

    return parsed;
  };

  const openPreviewModal = () => {
    setIsPreviewOpen(true);
    setTimeout(async () => {
      if (!editor) return;
      let finalHtml = '';
      try {
        const code = editor.runCommand('mjml-code-to-html');
        if (code && code.html) {
          finalHtml = code.html;
        } else {
          // Fallback: try mjml-get-code
          const code2 = editor.runCommand('mjml-get-code');
          if (code2 && code2.html) {
            finalHtml = code2.html;
          } else {
            finalHtml = editor.getHtml() + '<style>' + editor.getCss() + '</style>';
          }
        }
      } catch (err) {
        console.error('MJML Compile Error:', err);
        try {
          const code3 = editor.runCommand('mjml-get-code');
          finalHtml = code3 ? code3.html : '';
        } catch (err2) {
          finalHtml = editor.getHtml() + '<style>' + editor.getCss() + '</style>';
        }
      }

      if (finalHtml) {
        finalHtml = parsePreviewShortcodes(finalHtml);

        try {
          const restPath = (window as any).o100neData?.rest_path;
          if (restPath) {
             const res = await fetch(`${restPath.root}${restPath.base}/preview-render-html`, {
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
  };

  // MJML helper: css-class attribute is NOT a GrapesJS CSS class, so .find('.classname') and .closest('.classname') don't work.
  const closestByCssClass = (comp: any, className: string): any => {
    let curr = comp;
    while (curr) {
      const attrs = curr.getAttributes ? curr.getAttributes() : {};
      const cssClass = attrs['css-class'] || '';
      if (cssClass.split(' ').includes(className)) return curr;
      curr = curr.parent ? curr.parent() : null;
    }
    return null;
  };

  const findAllByCssClass = (root: any, className: string): any[] => {
    const results: any[] = [];
    const walk = (comp: any) => {
      const attrs = comp.getAttributes ? comp.getAttributes() : {};
      const cssClass = attrs['css-class'] || '';
      if (cssClass.split(' ').includes(className)) results.push(comp);
      if (comp.components) comp.components().forEach((child: any) => walk(child));
    };
    walk(root);
    return results;
  };

  // Update mj-text content and force canvas iframe DOM refresh
  // Uses components(html) so GrapesJS stores the HTML in its component tree.
  // This ensures the content survives re-renders triggered by style changes.
  // The MJML compiler won't crash because save/preview flows swap content
  // for shortcodes before invoking MJML compilation.
  const setMjTextContent = (textComp: any, html: string) => {
    if (!textComp) return;

    // Set via components so GrapesJS manages the DOM and content persists
    // through style-triggered re-renders
    textComp.components(html);
  };

  const DUMMY_ORDER_DATA = {
    order_id: 12345,
    order_number: '12345',
    subtotal: '$18.00',
    total: '$18.00',
    shipping_method: 'Free shipping',
    payment_method: 'Direct bank transfer',
    customer_note: 'Please deliver to the side door.',
    billing_address: 'John Doe<br/>Ap #867-859 Sit Rd.<br/>Azusa, NY 10001',
    billing_phone: '0123456789',
    billing_email: 'johndoe@domain.com',
    shipping_address: 'John Doe<br/>Ap #867-859 Sit Rd.<br/>Azusa, NY 10001',
    shipping_phone: '0123456789',
    item_totals: {
      cart_subtotal: { label: 'Subtotal:', value: '$18.00' },
      shipping: { label: 'Shipping:', value: 'Free shipping' },
      payment_method: { label: 'Payment method:', value: 'Direct bank transfer' },
      order_total: { label: 'Total:', value: '$18.00' }
    },
    items: [
      {
        name: 'Happy O100ne',
        qty: 2,
        price: '$18.00',
        sku: 'happy-01',
        image: 'https://via.placeholder.com/64x64?text=Product',
        refunded_qty: 0,
        purchase_note: 'Thank you for buying Happy O100ne!'
      }
    ]
  };

  const renderOrderTable = (component: any) => {
    const textComp = findMjText(component);
    if (!textComp) {
      console.error('[renderOrderTable] No text component found!');
      return;
    }

    const attrs = component.getAttributes();
    const showSku = attrs['data-show-sku'] !== 'false';
    const showPrice = attrs['data-show-price'] !== 'false';
    const showImage = attrs['data-show-image'] === 'true';
    const imageSize = attrs['data-image-size'] || '32';
    const removeLink = attrs['data-remove-link'] === 'true';
    const textProduct = attrs['data-text-product'] || 'Product';
    const textQty = attrs['data-text-qty'] || 'Qty';
    const textPrice = attrs['data-text-price'] || 'Price';

    const orderData = (window as any)._previewOrderData || DUMMY_ORDER_DATA;

    const rowsHTML = orderData.items.map((item: any) => {
      let skuHtml = (showSku && item.sku) ? `<br/><small style="color:#64748b;">(#${item.sku})</small>` : '';
      let priceHtml = showPrice ? `<td style="padding:12px 0; text-align:right; border-bottom:1px solid #ecedee; vertical-align:middle;">${item.price}</td>` : '';
      let productName = removeLink ? item.name : `<a href="#" style="color:#6A4BFF; text-decoration:none;">${item.name}</a>`;
      let imageHtml = showImage ? `<td width="${imageSize}" style="padding-right:10px; border-bottom:1px solid #ecedee; vertical-align:middle;"><img src="${item.image || 'https://via.placeholder.com/80x80?text=Img'}" width="${imageSize}" style="border-radius:4px;" /></td>` : '';

      return `<tr>${imageHtml}<td style="padding:12px 0; border-bottom:1px solid #ecedee; vertical-align:middle;">${productName}${skuHtml}</td><td style="padding:12px 0; border-bottom:1px solid #ecedee; text-align:center; vertical-align:middle;">${item.qty}</td>${priceHtml}</tr>`;
    }).join('');

    const priceHeaderHtml = showPrice ? `<th style="padding:10px 0; width:25%; text-align:right;">${textPrice}</th>` : '';
    const tableHTML = `<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px; color:#333;"><tr style="border-bottom:2px solid #ecedee;text-align:left;">${showImage ? `<th style="padding:10px 0; width:${imageSize}px;"></th>` : ''}<th style="padding:10px 0;">${textProduct}</th><th style="padding:10px 0; width:15%; text-align:center;">${textQty}</th>${priceHeaderHtml}</tr>${rowsHTML}</table>`;

    setMjTextContent(textComp, tableHTML);
  };

  
  const renderFullOrderTable = (component: any) => {
    const textComp = findMjText(component);
    if (!textComp) return;

    const attrs = component.getAttributes();
    const showSku = attrs['data-show-sku'] !== 'false';
    const showPrice = attrs['data-show-price'] !== 'false';
    const showImage = attrs['data-show-image'] === 'true';
    const imageSize = attrs['data-image-size'] || '32';
    const removeLink = attrs['data-remove-link'] === 'true';
    const textProduct = attrs['data-text-product'] || 'Product';
    const textQty = attrs['data-text-qty'] || 'Qty';
    const textPrice = attrs['data-text-price'] || 'Price';

    const labelOverrides: Record<string, string> = {
      cart_subtotal: attrs['data-label-cart_subtotal'] || '',
      shipping: attrs['data-label-shipping'] || '',
      tax: attrs['data-label-tax'] || '',
      fee: attrs['data-label-fee'] || '',
      discount: attrs['data-label-discount'] || '',
      order_total: attrs['data-label-order_total'] || ''
    };

    const orderData = (window as any)._previewOrderData || DUMMY_ORDER_DATA;

    const rowsHTML = orderData.items.map((item: any) => {
      let skuHtml = (showSku && item.sku) ? `<br/><small style="color:#64748b;">(#${item.sku})</small>` : '';
      let priceHtml = showPrice ? `<td style="padding:12px 0; text-align:right; border-bottom:1px solid #ecedee; vertical-align:middle;">${item.price}</td>` : '';
      let productName = removeLink ? item.name : `<a href="#" style="color:#6A4BFF; text-decoration:none;">${item.name}</a>`;
      let imageHtml = showImage ? `<td width="${imageSize}" style="padding-right:10px; border-bottom:1px solid #ecedee; vertical-align:middle;"><img src="${item.image || 'https://via.placeholder.com/80x80?text=Img'}" width="${imageSize}" style="border-radius:4px;" /></td>` : '';

      return `<tr>${imageHtml}<td style="padding:12px 0; border-bottom:1px solid #ecedee; vertical-align:middle;">${productName}${skuHtml}</td><td style="padding:12px 0; border-bottom:1px solid #ecedee; text-align:center; vertical-align:middle;">${item.qty}</td>${priceHtml}</tr>`;
    }).join('');

    let footersHTML = '';
    if (orderData.item_totals) {
      Object.keys(orderData.item_totals).forEach(key => {
        if (key === 'payment_method') return;
        let totalItem = orderData.item_totals[key];
        let label = labelOverrides[key] ? labelOverrides[key] + ':' : totalItem.label;
        let isTotal = key === 'order_total';
        let style = isTotal ? 'font-weight:bold; font-size:16px; padding:12px 0;' : 'padding:10px 0;';
        
        let colSpan = showImage ? 3 : 2;
        if (!showPrice) colSpan--;
        
        footersHTML += `<tr>
          <td colspan="${colSpan - (showPrice ? 0 : 1)}" style="${style} text-align:right; border-bottom:1px solid #ecedee; color:#64748b; padding-right:15px;">${label}</td>
          ${showPrice ? `<td style="${style} text-align:right; border-bottom:1px solid #ecedee; color:#0f172a;">${totalItem.value}</td>` : ''}
        </tr>`;
      });
    }

    const priceHeaderHtml = showPrice ? `<th style="padding:10px 0; width:25%; text-align:right;">${textPrice}</th>` : '';
    const tableHTML = `<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px; color:#333;"><tr style="border-bottom:2px solid #ecedee;text-align:left;">${showImage ? `<th style="padding:10px 0; width:${imageSize}px;"></th>` : ''}<th style="padding:10px 0;">${textProduct}</th><th style="padding:10px 0; width:15%; text-align:center;">${textQty}</th>${priceHeaderHtml}</tr>${rowsHTML}${footersHTML}</table>`;

    setMjTextContent(textComp, tableHTML);
  };

  const renderProductsTable = (component: any) => {
    const textComp = findMjText(component);
    if (!textComp) return;

    const attrs = component.getAttributes();
    const showSku = attrs['data-show-sku'] !== 'false';
    const showImage = attrs['data-show-image'] !== 'false';
    const showPrice = attrs['data-show-price'] !== 'false';
    const imageSize = attrs['data-image-size'] || '80';
    const removeLink = attrs['data-remove-link'] === 'true';
    const productType = attrs['data-product-type'] || 'newest';

    const columns = Math.max(1, parseInt(attrs['data-columns'] || '2', 10) || 2);
    const maxRows = Math.max(1, parseInt(attrs['data-max-rows'] || '2', 10) || 2);
    const maxNeeded = columns * maxRows;

    const orderData = (window as any)._previewOrderData || DUMMY_ORDER_DATA;

    let fetchPromise: Promise<any[]>;

    if (productType === 'cross_sells' || productType === 'up_sells') {
      const restPath = (window as any).o100neData?.rest_path || {};
      const orderId = orderData.order_id || 'sample_order';
      const url = `${restPath.root}${restPath.base}/product/cross-up-sells?order_id=${orderId}&linked_products_type=${productType}&max_products_displayed=${maxNeeded}`;
      fetchPromise = fetch(url, { headers: { 'X-WP-Nonce': restPath.nonce } })
        .then(res => res.json())
        .then(data => {
          if (Array.isArray(data)) {
            return data.map((p: any) => ({
              name: p.name,
              qty: 1,
              price: p.sale_price_html || p.regular_price_html || p.price || '',
              sku: p.sku || '',
              image: p.thumbnail_src || 'https://via.placeholder.com/80x80?text=Img',
              permalink: p.permalink || '#'
            }));
          }
          return [];
        }).catch(() => []);
    } else {
      const restPath = (window as any).o100neData?.rest_path || {};
      let url = `${restPath.root}${restPath.base}/product/featured?product_type=${productType}&number_of_products=${maxNeeded}`;
      if (productType === 'specific' && attrs['data-specific-ids']) {
         url += `&specific_ids=${attrs['data-specific-ids']}`;
      }
      fetchPromise = fetch(url, { headers: { 'X-WP-Nonce': restPath.nonce } })
        .then(res => res.json())
        .then(data => {
          if (Array.isArray(data)) {
            return data.map((p: any) => ({
              name: p.name,
              qty: 1,
              price: p.sale_price_html || p.regular_price_html || p.price || '',
              sku: p.sku || '',
              image: p.thumbnail_src || 'https://via.placeholder.com/80x80?text=Img',
              permalink: p.permalink || '#'
            }));
          }
          return [];
        }).catch(() => []);
    }

    fetchPromise.then((items: any[]) => {
      if (!items || items.length === 0) {
        items = [{ name: 'Dummy Product', qty: 1, price: '$0.00', image: 'https://via.placeholder.com/80x80?text=Img', sku: 'DUMMY' }];
      }

      const columns = Math.max(1, parseInt(attrs['data-columns'] || '2', 10) || 2);
      const maxRows = Math.max(1, parseInt(attrs['data-max-rows'] || '2', 10) || 2);
      const titleColor = attrs['data-title-color'] || '#3c434a';
      const titleSize = attrs['data-title-size'] || '15';
      const priceColor = attrs['data-price-color'] || '#3c434a';
      const priceSize = attrs['data-price-size'] || '15';
      const verticalDistance = attrs['data-vertical-distance'] || '10';
      const showCartBtn = attrs['data-show-cart-btn'] === 'true';

      const maxItems = columns * maxRows;
      items = items.slice(0, maxItems);

      let rowsHTML = '';
      for (let i = 0; i < items.length; i += columns) {
        let rowCells = '';
        for (let j = 0; j < columns; j++) {
          if (i + j < items.length) {
            const item = items[i + j];
            let skuHtml = (showSku && item.sku) ? `<div style="font-size:12px; color:#64748b; margin-top:2px;">SKU: ${item.sku}</div>` : '';
            let productName = removeLink ? item.name : `<a href="${item.permalink || '#'}" style="color:${titleColor}; text-decoration:none;">${item.name}</a>`;
            let imageHtml = showImage ? `<div style="margin-bottom:10px;"><img src="${item.image || 'https://via.placeholder.com/80x80?text=Img'}" width="100%" style="background:#e2e8f0; border-radius:4px; max-width:100%; height:auto; display:block;" /></div>` : '';
            let priceHtml = showPrice ? `<div style="font-size:${priceSize}px; color:${priceColor}; margin-bottom:10px;">${item.price}</div>` : '';
            let cartBtnHtml = showCartBtn ? `<div><a href="${item.permalink || '#'}" style="display:inline-block; padding:8px 16px; background:#6A4BFF; color:#fff; text-decoration:none; border-radius:4px; font-size:13px; font-weight:bold;">Add to cart</a></div>` : '';

            rowCells += `<td style="vertical-align:top; width:${100 / columns}%; padding: ${verticalDistance}px 10px; text-align:center;" data-gjs-selectable="false" data-gjs-hoverable="false" data-gjs-highlightable="false" data-gjs-droppable="false" data-gjs-draggable="false">
              ${imageHtml}
              <div style="font-size:${titleSize}px; color:${titleColor}; margin-bottom:5px;">${productName}</div>
              <div style="font-size:13px; color:#666; margin-bottom:5px;">Quantity: ${item.qty}</div>
              ${skuHtml}
              ${priceHtml}
              ${cartBtnHtml}
            </td>`;
          } else {
            rowCells += `<td style="width:${100 / columns}%; padding: ${verticalDistance}px 10px;" data-gjs-selectable="false" data-gjs-hoverable="false" data-gjs-highlightable="false" data-gjs-droppable="false" data-gjs-draggable="false"></td>`;
          }
        }
        rowsHTML += `<tr data-gjs-selectable="false" data-gjs-hoverable="false" data-gjs-highlightable="false" data-gjs-droppable="false" data-gjs-draggable="false">${rowCells}</tr>`;
      }

      setMjTextContent(textComp, `<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; table-layout: fixed;" data-gjs-selectable="false" data-gjs-hoverable="false" data-gjs-highlightable="false" data-gjs-droppable="false" data-gjs-draggable="false">${rowsHTML}</table>`);
    });
  };

  const updatePreviewData = () => {
    if (!editor) return;
    const wrapper = editor.getWrapper();
    if (!wrapper) return;

    const orderTables = findAllByCssClass(wrapper, 'woo-order-detail');
    const fullOrderTables = findAllByCssClass(wrapper, 'woo-full-order-detail');
    if (fullOrderTables.length > 0) fullOrderTables.forEach(renderFullOrderTable);
    if (orderTables.length > 0) orderTables.forEach(renderOrderTable);

    const productTables = findAllByCssClass(wrapper, 'woo-products');
    if (productTables.length > 0) productTables.forEach(renderProductsTable);

    const orderData = (window as any)._previewOrderData || DUMMY_ORDER_DATA;

    findAllByCssClass(wrapper, 'woo-billing-address').forEach((block: any) => {
      if (!orderData.billing_address) return;
      const textComp = findMjText(block);
      let info = orderData.billing_address;
      if (orderData.billing_phone) info += `<br/><a href="tel:${orderData.billing_phone}" style="color:inherit;text-decoration:none;">${orderData.billing_phone}</a>`;
      if (orderData.billing_email) info += `<br/><a href="mailto:${orderData.billing_email}" style="color:inherit;text-decoration:none;">${orderData.billing_email}</a>`;
      if (textComp) textComp.components(`<strong>Billing Address</strong><br/>${info}`);
    });

    findAllByCssClass(wrapper, 'woo-shipping-address').forEach((block: any) => {
      if (!orderData.shipping_address) return;
      const textComp = findMjText(block);
      let info = orderData.shipping_address;
      if (orderData.shipping_phone) info += `<br/><a href="tel:${orderData.shipping_phone}" style="color:inherit;text-decoration:none;">${orderData.shipping_phone}</a>`;
      if (textComp) textComp.components(`<strong>Shipping Address</strong><br/>${info}`);
    });

    findAllByCssClass(wrapper, 'woo-order-subtotal').forEach((block: any) => {
      if (!orderData.item_totals) return;
      const textComp = findMjText(block);
      if (!textComp) return;
      let subHtml = '<table width="100%" style="font-size:14px;color:#333;">';
      Object.keys(orderData.item_totals).forEach(key => {
        if (key === 'order_total' || key === 'payment_method') return;
        subHtml += `<tr><td style="padding:4px 0;text-align:left;width:60%;">${orderData.item_totals[key].label}</td><td style="padding:4px 0;text-align:right;width:40%;">${orderData.item_totals[key].value}</td></tr>`;
      });
      subHtml += '</table>';
      textComp.components(subHtml);
    });

    findAllByCssClass(wrapper, 'woo-order-total').forEach((block: any) => {
      if (!orderData.total) return;
      const tc = findMjText(block);
      if (tc) tc.components(`<table width="100%" style="font-size:16px;font-weight:bold;color:#333;"><tr><td style="padding:8px 0;text-align:left;width:60%;">Total</td><td style="padding:8px 0;text-align:right;width:40%;">${orderData.total}</td></tr></table>`);
    });

    findAllByCssClass(wrapper, 'woo-shipping-method').forEach((block: any) => {
      if (!orderData.shipping_method) return;
      const tc = findMjText(block);
      if (tc) tc.components(`<table width="100%" style="font-size:14px;color:#333;"><tr><td style="padding:4px 0;text-align:left;width:60%;">Shipping method</td><td style="padding:4px 0;text-align:right;width:40%;">${orderData.shipping_method}</td></tr></table>`);
    });

    findAllByCssClass(wrapper, 'woo-payment-method').forEach((block: any) => {
      if (!orderData.payment_method) return;
      const tc = findMjText(block);
      if (tc) tc.components(`<table width="100%" style="font-size:14px;color:#333;"><tr><td style="padding:4px 0;text-align:left;width:60%;">Payment method</td><td style="padding:4px 0;text-align:right;width:40%;">${orderData.payment_method}</td></tr></table>`);
    });

    findAllByCssClass(wrapper, 'woo-order-note').forEach((block: any) => {
      if (!orderData.customer_note) return;
      const tc = findMjText(block);
      if (tc) tc.components(`<table width="100%" style="font-size:14px;color:#333;"><tr><td style="padding:4px 0;text-align:left;width:60%;">Note</td><td style="padding:4px 0;text-align:right;width:40%;">${orderData.customer_note}</td></tr></table>`);
    });
  };

  const loadOrderData = async (orderId: string) => {
    setPreviewOrder(orderId);
    if (!editor) return;

    if (!orderId) {
      (window as any)._previewOrderData = DUMMY_ORDER_DATA;
      updatePreviewData();
      return;
    }

    try {
      const restPath = o100neData.rest_path || {};
      const url = `${restPath.root}${restPath.base}/order-preview/${orderId}`;
      const response = await fetch(url, {
        headers: { 'X-WP-Nonce': restPath.nonce }
      });
      const data = await response.json();

      if (data && data.success) {
        (window as any)._previewOrderData = data;
        updatePreviewData();
      } else {
        (window as any)._previewOrderData = DUMMY_ORDER_DATA;
        updatePreviewData();
      }
    } catch (err) {
      console.error(err);
      (window as any)._previewOrderData = DUMMY_ORDER_DATA;
      updatePreviewData();
    }

    // Update Preview HTML if modal is open
    setTimeout(async () => {
      if (!editor) return;
      let finalHtml = '';
      try {
        const code = editor.runCommand('mjml-code-to-html');
        if (code && code.html) {
          finalHtml = code.html;
        } else {
          const code2 = editor.runCommand('mjml-get-code');
          finalHtml = code2 ? code2.html : '';
        }
      } catch (err) {
        try {
          const code3 = editor.runCommand('mjml-get-code');
          finalHtml = code3 ? code3.html : '';
        } catch (err2) {
          finalHtml = editor.getHtml() + '<style>' + editor.getCss() + '</style>';
        }
      }

      if (finalHtml) {
        finalHtml = parsePreviewShortcodes(finalHtml);

        try {
          const restPath = (window as any).o100neData?.rest_path;
          if (restPath) {
             const res = await fetch(`${restPath.root}${restPath.base}/preview-render-html`, {
               method: 'POST',
               headers: {
                 'Content-Type': 'application/json',
                 'X-WP-Nonce': restPath.nonce
               },
               body: JSON.stringify({
                 html: finalHtml,
                 order_id: orderId
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

      setPreviewHtml(finalHtml || '<div style="padding:40px; text-align:center; color:#e53e3e;">Preview generation failed.</div>');
    }, 200);
  };

  useEffect(() => {
    if (!editorRef.current || isLoading) return;

    const e = grapesjs.init({
      container: editorRef.current,
      fromElement: false,
      height: '100%',
      width: '100%',
      storageManager: false,
      panels: { defaults: [] }, // Disable default floating panels
      plugins: [grapesjsMjml],
      pluginsOpts: {
        [grapesjsMjml as any]: {}
      },

      blockManager: { appendTo: '#blocks-container' },
      styleManager: { appendTo: '#styles-container' },
      layerManager: { appendTo: '#layers-container' },
      traitManager: { appendTo: '#traits-container' },
      deviceManager: {
        devices: [
          { id: 'desktop', name: 'Desktop', width: '' },
          { id: 'mobile', name: 'Mobile', width: '320px', widthMedia: '480px' },
        ]
      },
      colorPicker: {
        appendTo: 'body',
        showInput: true,
      },
      components: (window as any)._loadedTemplateMjml || getDefaultTemplate(id)
    });

    // Expose editor to window for debugging
    (window as any).__gjsEditor = e;

    // Disable native text drag-and-drop inside the RTE to prevent
    // accidental text moves when trying to select text
    e.on('rte:enable', () => {
      const iframeEl = e.Canvas?.getFrameEl?.();
      const iframeDoc = iframeEl?.contentDocument || iframeEl?.contentWindow?.document;
      if (iframeDoc) {
        iframeDoc.addEventListener('dragstart', (ev: Event) => {
          const sel = iframeDoc.getSelection?.();
          // Only block drag when there's a text selection (native text drag)
          if (sel && sel.toString().length > 0) {
            ev.preventDefault();
          }
        }, true);
      }
    });

    const getBlockLabel = (title: string, svgPath: string) => `
      <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; margin-bottom: 5px; fill: transparent !important;">
        ${svgPath}
      </svg>
      <div class="gjs-block-label">${title}</div>
    `;

    // Add WooCommerce Blocks — bare mj-text so they can be dropped into existing columns
    // GrapesJS-MJML auto-wraps in section+column when dropped at root level
    e.BlockManager.add('o100ne-loyalty-points', {
      label: getBlockLabel('Loyalty Points', '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>'),
      content: '<mj-text font-size="16px" color="#6A4BFF" css-class="woo-loyalty-points">Your Loyalty Points: <b>[o100_loyalty_points]</b></mj-text>',
      category: 'Order Elements'
    });

    
    e.BlockManager.add('o100ne-full-order-detail', {
      label: getBlockLabel('Order Details', '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line>'),
      content: `
        <mj-text font-size="14px" font-family="helvetica" color="#333333" css-class="woo-full-order-detail">
          <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tr style="border-bottom:2px solid #ecedee;text-align:left;">
              <th style="padding:10px 0; width:60%;">Product</th>
              <th style="padding:10px 0; width:15%; text-align:center;">Qty</th>
              <th style="padding:10px 0; width:25%; text-align:right;">Price</th>
            </tr>
            <tr>
              <td style="padding:12px 0; border-bottom:1px solid #ecedee;">Premium Item</td>
              <td style="padding:12px 0; border-bottom:1px solid #ecedee; text-align:center;">1</td>
              <td style="padding:12px 0; text-align:right; border-bottom:1px solid #ecedee;">$25.00</td>
            </tr>
            <tr>
              <td colspan="2" style="padding:10px 0; text-align:right; border-bottom:1px solid #ecedee; color:#64748b;">Subtotal:</td>
              <td style="padding:10px 0; text-align:right; border-bottom:1px solid #ecedee;">$25.00</td>
            </tr>
            <tr>
              <td colspan="2" style="padding:12px 0; text-align:right; font-weight:bold; font-size:16px; color:#64748b;">Total:</td>
              <td style="padding:12px 0; text-align:right; font-weight:bold; font-size:16px;">$25.00</td>
            </tr>
          </table>
        </mj-text>
      `,
      category: 'Order Elements'
    });

    e.BlockManager.add('o100ne-order-detail', {
      label: getBlockLabel('Order Items', '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line>'),
      content: `
        <mj-text font-size="14px" font-family="helvetica" color="#333333" css-class="woo-order-detail">
          <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tr style="border-bottom:2px solid #ecedee;text-align:left;">
              <th style="padding:10px 0; width:60%;">Product</th>
              <th style="padding:10px 0; width:15%; text-align:center;">Qty</th>
              <th style="padding:10px 0; width:25%; text-align:right;">Price</th>
            </tr>
            <tr>
              <td style="padding:12px 0; border-bottom:1px solid #ecedee;">Premium Item</td>
              <td style="padding:12px 0; border-bottom:1px solid #ecedee; text-align:center;">1</td>
              <td style="padding:12px 0; text-align:right; border-bottom:1px solid #ecedee;">$25.00</td>
            </tr>
          </table>
        </mj-text>
      `,
      category: 'Order Elements'
    });

    e.BlockManager.add('o100ne-order-subtotal', {
      label: getBlockLabel('Order subtotal', '<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>'),
      content: '<mj-text font-family="helvetica" font-size="14px" css-class="woo-order-subtotal">Subtotal: <strong>{order_subtotal}</strong></mj-text>',
      category: 'Order Elements'
    });

    e.BlockManager.add('o100ne-order-total', {
      label: getBlockLabel('Order total', '<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>'),
      content: '<mj-text font-family="helvetica" font-size="16px" font-weight="bold" css-class="woo-order-total">Total: {order_total}</mj-text>',
      category: 'Order Elements'
    });

    e.BlockManager.add('o100ne-shipping-method', {
      label: getBlockLabel('Shipping method', '<rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle>'),
      content: '<mj-text font-family="helvetica" font-size="14px" css-class="woo-shipping-method"><div style="margin-bottom:5px; font-weight:bold;">Shipment Method</div><div style="color:#666;">{shipping_method}</div></mj-text>',
      category: 'Order Elements'
    });

    e.BlockManager.add('o100ne-payment-method', {
      label: getBlockLabel('Payment method', '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line>'),
      content: '<mj-text font-family="helvetica" font-size="14px" css-class="woo-payment-method"><div style="margin-bottom:5px; font-weight:bold;">Payment Method</div><div style="color:#666;">{payment_method}</div></mj-text>',
      category: 'Order Elements'
    });

    e.BlockManager.add('o100ne-customer-note', {
      label: getBlockLabel('Customer note', '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>'),
      content: '<mj-text font-family="helvetica" font-size="14px" css-class="woo-customer-note"><div style="margin-bottom:5px; font-weight:bold;">Note</div><div style="color:#666;">{customer_note}</div></mj-text>',
      category: 'Order Elements'
    });

    e.BlockManager.add('o100ne-billing-address', {
      label: getBlockLabel('Billing address', '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>'),
      content: '<mj-text font-family="helvetica" font-size="14px" line-height="20px" css-class="woo-billing-address"><strong>Billing Address</strong><br/>{billing_first_name} {billing_last_name}<br/>{billing_address_1}<br/>{billing_city}, {billing_state} {billing_postcode}</mj-text>',
      category: 'Order Elements'
    });

    e.BlockManager.add('o100ne-shipping-address', {
      label: getBlockLabel('Shipping address', '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>'),
      content: '<mj-text font-family="helvetica" font-size="14px" line-height="20px" css-class="woo-shipping-address"><strong>Shipping Address</strong><br/>{shipping_first_name} {shipping_last_name}<br/>{shipping_address_1}<br/>{shipping_city}, {shipping_state} {shipping_postcode}</mj-text>',
      category: 'Order Elements'
    });

    e.BlockManager.add('o100ne-products', {
      label: getBlockLabel('Products', '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>'),
      content: `
        <mj-text font-family="helvetica" padding="10px" css-class="woo-products">
          <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tr>
              <td style="width:80px; padding-right:15px; vertical-align:top;"><img src="https://via.placeholder.com/80x80?text=Item" width="80" style="background:#e2e8f0; border-radius:4px;" /></td>
              <td style="vertical-align:top;"><div style="font-size:15px; color:#333; margin-bottom:5px;">{product_name}</div><div style="font-size:13px; color:#666;">Quantity: {product_qty}</div></td>
              <td style="vertical-align:top; text-align:right; font-size:15px; color:#333;">{product_price}</td>
            </tr>
          </table>
        </mj-text>
      `,
      category: 'Order Elements'
    });

    e.BlockManager.add('o100ne-coupon', {
      label: getBlockLabel('Coupon', '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line>'),
      content: '<mj-text font-family="helvetica" font-size="14px" css-class="woo-coupon">Coupon Applied: <strong>{coupon_code}</strong></mj-text>',
      category: 'Order Elements'
    });

    e.BlockManager.add('custom-heading', {
      label: getBlockLabel('Heading', '<polyline points="5 4 5 20"></polyline><polyline points="19 4 19 20"></polyline><line x1="5" y1="12" x2="19" y2="12"></line>'),
      content: '<mj-text font-family="helvetica" font-size="24px" font-weight="bold" color="#333333">Heading Text</mj-text>',
      category: 'Basic Elements'
    });

    e.BlockManager.add('o100ne-wc-hook', {
      label: getBlockLabel('WC Hook', '<polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline>'),
      content: '<mj-text font-family="helvetica" font-size="14px" color="#6A4BFF" align="center" padding="10px" css-class="woo-hook"><i>[woocommerce_hook]</i></mj-text>',
      category: 'Order Elements'
    });

    // Conditional Section — registered later in 'load' event to share Layout category model

    e.BlockManager.add('o100ne-exfood-meta', {
      label: getBlockLabel('Order100 Info', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>'),
      content: `
        <mj-section css-class="woo-exfood-meta" data-exfood-meta="true" exfood-show-method="true" exfood-show-prep="true" exfood-show-birthday="true">
          <mj-column>
            <mj-text font-family="helvetica" font-size="14px" line-height="24px" data-exfood-text="true">
              <div class="exfood-method" style="margin-bottom:8px;"><strong>Order Method:</strong> {o100_order_method}</div>
              <div class="exfood-prep" style="margin-bottom:8px;"><strong>Prep Time:</strong> {o100_prep_time}</div>
              <div class="exfood-birthday" style="margin-bottom:8px;"><strong>Birthday:</strong> {o100_birthday}</div>
            </mj-text>
          </mj-column>
        </mj-section>
      `,
      category: 'Order Elements'
    });

    // Add Shortcode Inserter to Rich Text Editor Toolbar (Renamed ID to bypass HMR cache)
    e.RichTextEditor.add('insert-vars', {
      icon: '<span style="font-size: 13px; color: #6A4BFF; font-weight: bold; white-space: nowrap; margin: 0 4px; line-height: 1;">{ } Vars</span>',
      attributes: { title: 'Insert Variable/Shortcode' },
      result: (rte: any) => {
        if (typeof (window as any).openO100neShortcodesModal === 'function') {
          (window as any).openO100neShortcodesModal(rte);
        }
      }
    });

    // Add Text Color and Font Size to Rich Text Editor Toolbar
    e.RichTextEditor.add('foreColor', {
      icon: '<div title="Text Color" style="display:flex;align-items:center;justify-content:center;padding:0 4px;"><input type="color" style="width:20px;height:20px;padding:0;border:none;background:transparent;cursor:pointer;" /></div>',
      event: 'input',
      result: (rte: any, action: any) => {
        const input = action.btn.querySelector('input');
        if (input && input.value) {
          rte.exec('foreColor', input.value);
        }
      }
    });

    e.RichTextEditor.add('fontSize', {
      icon: `<select class="gjs-field" title="Font Size" style="appearance:none;-webkit-appearance:none;width:auto;padding:0 8px;font-size:13px;font-weight:bold;color:#475569;margin:0;cursor:pointer;border:none;background:transparent;outline:none;">
              <option value="">Size ▾</option>
              <option value="12">12px</option>
              <option value="14">14px</option>
              <option value="16">16px</option>
              <option value="18">18px</option>
              <option value="20">20px</option>
              <option value="24">24px</option>
              <option value="32">32px</option>
              <option value="48">48px</option>
              <option value="64">64px</option>
              <option value="72">72px</option>
              <option value="96">96px</option>
            </select>`,
      event: 'change',
      result: (rte: any, action: any) => {
        const select = action.btn.querySelector('select');
        if (select && select.value) {
          const val = select.value;
          // Hack: Use max native size to generate a hook tag
          rte.exec('fontSize', '7');
          // Find the generated tags and convert them to exact pixel sizes
          const fonts = rte.el.querySelectorAll('font[size="7"]');
          fonts.forEach((f: any) => {
            f.removeAttribute('size');
            f.style.fontSize = val + 'px';
          });
          select.value = '';
        }
      }
    });

    // ─── Guide CSS string (shared between injection points) ───
    const GUIDE_CSS = `
      .o100ne-guide-section { outline: 1px dashed rgba(59,130,246,0.4) !important; outline-offset: -1px; }
      .o100ne-guide-column  { outline: 1px dotted rgba(34,197,94,0.4) !important; outline-offset: -1px; }
      .o100ne-guide-element { outline: 1px dotted rgba(249,115,22,0.3) !important; outline-offset: -1px; }
      .o100ne-conditional { position: relative !important; outline: 2px dashed #a78bfa !important; outline-offset: -1px; border-radius: 4px !important; }
      .o100ne-conditional::before { content: '🔀 IF: ' attr(data-condition-field) ' ' attr(data-condition-operator) ' ' attr(data-condition-value); position: absolute; top: -1px; left: -1px; background: linear-gradient(135deg,#7c3aed,#6366f1); color: #fff; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 0 0 4px 0; z-index: 10; white-space: nowrap; font-family: -apple-system,BlinkMacSystemFont,sans-serif; }
    `;

    // Helper: inject guide CSS into a document if not already done
    const injectGuideCSS = (doc: Document) => {
      if (!doc || doc.getElementById('o100ne-guide-styles')) return;
      const s = doc.createElement('style');
      s.id = 'o100ne-guide-styles';
      s.textContent = GUIDE_CSS;
      doc.head.appendChild(s);
    };

    // Helper: add guide CSS class to a component's DOM element
    const addGuideClass = (component: any) => {
      try {
        const type = component.get('type') || '';
        const el = component.getEl();
        if (!el) return;
        if (type === 'mj-section' || type === 'mj-wrapper') el.classList.add('o100ne-guide-section');
        else if (type === 'mj-column') el.classList.add('o100ne-guide-column');
        else if (['mj-text','mj-button','mj-image','mj-divider','mj-spacer'].includes(type)) el.classList.add('o100ne-guide-element');
      } catch (_) {}
    };

    // Inject guide class on every component mount
    e.on('component:mount', addGuideClass);

    // Also try canvas:frame:load for CSS injection
    e.on('canvas:frame:load', ({ window: fw }: any) => { try { injectGuideCSS(fw.document); } catch(_) {} });

    // Re-categorize blocks + register conditional + merge duplicate Layout via DOM
    e.on('load', () => {
      // 1. Register conditional block (will create a second "Layout" category — we merge below)
      const bm = e.Blocks || e.BlockManager;
      if (bm && !bm.get('o100ne-conditional-section')) {
        bm.add('o100ne-conditional-section', {
          label: getBlockLabel('Conditional', '<path d="M16 3h5v5"></path><line x1="21" y1="3" x2="14" y2="10"></line><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"></path>'),
          content: `<mj-section css-class="o100ne-conditional" data-condition-field="o100_order_method" data-condition-operator="equals" data-condition-value="delivery"><mj-column><mj-text font-family="helvetica" font-size="14px" color="#475569">This content shows only when condition is met. Drag components here.</mj-text></mj-column></mj-section>`,
          category: 'Layout'
        });
      }

      // 2. DOM merge: combine all duplicate "Layout" categories into the first one
      setTimeout(() => {
        const container = document.getElementById('blocks-container');
        if (!container) return;
        const allCats = container.querySelectorAll('.gjs-block-category');
        let firstLayout: Element | null = null;
        allCats.forEach(cat => {
          const titleEl = cat.querySelector('.gjs-title');
          if (titleEl && titleEl.textContent?.trim() === 'Layout') {
            if (!firstLayout) {
              firstLayout = cat;
            } else {
              // Move blocks from duplicate into first Layout
              const blocksWrap = cat.querySelector('.gjs-blocks-c');
              const firstBlocksWrap = firstLayout.querySelector('.gjs-blocks-c');
              if (blocksWrap && firstBlocksWrap) {
                while (blocksWrap.firstChild) {
                  firstBlocksWrap.appendChild(blocksWrap.firstChild);
                }
              }
              cat.remove(); // Remove the empty duplicate category
            }
          }
        });
      }, 200);

      // Hide blocks not needed for restaurant email workflows
      if (bm) {
        ['mj-raw', 'mj-hero'].forEach(blockId => {
          const block = bm.get(blockId);
          if (block) bm.remove(blockId);
        });
      }

      // Inject CSS into canvas iframe
      try {
        const canvasDoc = e.Canvas.getDocument();
        if (canvasDoc) injectGuideCSS(canvasDoc);
      } catch (_) {}

      // Apply guide classes and restore condition fields to all existing components
      const wrapper = e.DomComponents.getWrapper();
      if (wrapper) {
        const walkTree = (comp: any) => {
          addGuideClass(comp);
          // Restore condition fields from css-class encoded string
          const attrs = comp.getAttributes();
          const css = attrs['css-class'] || '';
          const match = css.match(/cond_o100_([A-Za-z0-9_-]+)/);
          if (match) {
            try {
              let base64 = match[1];
              base64 = base64.replace(/-/g, '+').replace(/_/g, '/');
              while (base64.length % 4) { base64 += '='; }
              const jsonStr = decodeURIComponent(Array.prototype.map.call(atob(base64), function(c: any) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
              }).join(''));
              const data = JSON.parse(jsonStr);
              comp.addAttributes({
                'data-condition-field': data.f,
                'data-condition-operator': data.o,
                'data-condition-value': data.v
              });
            } catch (err) {
              console.error('Failed to restore condition', err);
            }
          }
          comp.components().forEach(walkTree);
        };
        walkTree(wrapper);
      }
    });

    // Custom Image Uploader Modal to satisfy user requirement
    e.Commands.add('open-assets', {
      run(editor, sender, opts = {}) {
        const modal = editor.Modal;
        const target = opts.target || editor.getSelected();

        modal.setTitle('Select Image');
        modal.setContent(`
          <div style="padding: 20px;">
            <div style="margin-bottom: 20px;">
              <label style="display:block; margin-bottom: 8px; font-weight: 600; color: #334155;">Image URL</label>
              <input type="text" id="gjs-custom-url" placeholder="https://example.com/image.jpg" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; color: #334155;" />
            </div>
            
            <div style="margin-bottom: 20px;">
              <label style="display:block; margin-bottom: 8px; font-weight: 600; color: #334155;">Or Choose From</label>
              <div style="display: flex; gap: 10px;">
                <button type="button" id="gjs-wp-media-btn" style="flex: 1; padding: 14px; background: #6A4BFF; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                  📷 WordPress Media Library
                </button>
                <label for="gjs-custom-file" style="flex: 1; padding: 14px; background: #f1f5f9; color: #475569; border: 2px dashed #cbd5e1; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; text-align: center;">
                  📁 Upload File
                </label>
                <input type="file" id="gjs-custom-file" accept="image/*" style="display: none;" />
              </div>
            </div>

            <div id="gjs-upload-preview" style="display: none; margin-bottom: 20px; padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center;">
              <img id="gjs-upload-preview-img" style="max-width: 100%; max-height: 150px; border-radius: 4px;" />
              <p id="gjs-upload-preview-name" style="margin: 8px 0 0; font-size: 12px; color: #64748b;"></p>
            </div>
            
            <div style="text-align: right; border-top: 1px solid #e2e8f0; padding-top: 20px;">
              <button type="button" id="gjs-custom-confirm" style="background: #6A4BFF; color: #fff; padding: 10px 24px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">Confirm & Insert</button>
            </div>
          </div>
        `);
        modal.open();

        setTimeout(() => {
          // WordPress Media Library — close GrapesJS modal first to avoid CSS conflict
          document.getElementById('gjs-wp-media-btn')?.addEventListener('click', () => {
            // Open media library in a separate popup window to avoid GrapesJS CSS conflicts
            const pickerUrl = (window as any).o100neData?.urls?.media_picker_url || '';
            if (!pickerUrl) {
              showToast('Media picker URL not configured.', 'error');
              return;
            }
            
            const popup = window.open(
              pickerUrl,
              'wpMediaPicker',
              'width=1200,height=700,scrollbars=yes,resizable=yes'
            );

            if (!popup) {
              alert('Popup blocked. Please allow popups for this site.');
              return;
            }

            // Close GrapesJS modal
            modal.close();

            // Listen for message from popup
            const messageHandler = (event: MessageEvent) => {
              if (event.data && event.data.type === 'wp-media-selected') {
                const url = event.data.url || '';
                if (url && target) {
                  // mj-image stores src as a model property
                  target.set('src', url);
                  target.addAttributes({ src: url, alt: event.data.alt || '' });
                }
                window.removeEventListener('message', messageHandler);
              }
            };
            window.addEventListener('message', messageHandler);
          });

          // File upload preview
          document.getElementById('gjs-custom-file')?.addEventListener('change', (evt) => {
            const input = evt.target as HTMLInputElement;
            if (input.files && input.files.length > 0) {
              const file = input.files[0];
              const reader = new FileReader();
              reader.onload = (e) => {
                const previewDiv = document.getElementById('gjs-upload-preview');
                const previewImg = document.getElementById('gjs-upload-preview-img') as HTMLImageElement;
                const previewName = document.getElementById('gjs-upload-preview-name');
                if (previewDiv && previewImg) {
                  previewImg.src = e.target?.result as string;
                  previewDiv.style.display = 'block';
                  if (previewName) previewName.textContent = file.name;
                }
              };
              reader.readAsDataURL(file);
            }
          });

          // Confirm button
          document.getElementById('gjs-custom-confirm')?.addEventListener('click', () => {
            const urlInput = document.getElementById('gjs-custom-url') as HTMLInputElement;
            const fileInput = document.getElementById('gjs-custom-file') as HTMLInputElement;

            if (!target) {
              modal.close();
              return;
            }

            if (fileInput && fileInput.files && fileInput.files.length > 0) {
              const file = fileInput.files[0];
              const reader = new FileReader();
              reader.onload = (event) => {
                target.set('src', event.target?.result);
                modal.close();
              };
              reader.readAsDataURL(file);
            } else if (urlInput && urlInput.value.trim() !== '') {
              target.set('src', urlInput.value.trim());
              modal.close();
            } else {
              showToast('Please enter a URL, select from Media Library, or upload a file.', 'error');
            }
          });
        }, 100);
      }
    });

    e.on('component:add', (comp: any) => {
      setTimeout(() => {
        if (!comp || !comp.getAttributes) return;
        const attrs = comp.getAttributes();
        const cssClass = attrs['css-class'] || '';
        
        if (cssClass.includes('woo-products')) {
          renderProductsTable(comp);
        } else if (cssClass.includes('woo-order-detail')) {
          renderOrderTable(comp);
        } else if (cssClass.includes('woo-full-order-detail')) {
          renderFullOrderTable(comp);
          renderOrderTable(comp);
        } else if (cssClass.includes('woo-order-subtotal') || cssClass.includes('woo-shipping-address') || cssClass.includes('woo-billing-address')) {
          updatePreviewData();
        }
      }, 200);
    });

    setEditor(e);

    // Auto-switch to Styles tab when a component is selected
    e.on('component:selected', (comp) => {
      setActiveTab('styles');
      setSelectedComponent(comp);
    });

    e.on('component:deselected', () => {
      setSelectedComponent(null);
    });

    // Auto-select uploaded image and close modal (Single image upload flow)
    e.on('asset:add', (asset) => {
      const target = (e.AssetManager as any).getTarget();
      if (target) {
        target.set('src', asset.get('src'));
        e.Modal.close();
      }
    });

    e.on('load', () => {
      const blocks = e.BlockManager.getAll();
      blocks.forEach((block: any) => {
        const id = block.getId();
        // Skip our custom woo blocks as they are already categorized
        if (id.startsWith('o100ne-')) return;

        if (id.includes('column') || id.includes('wrapper') || id.includes('hero') || id.includes('section')) {
          block.set('category', { id: 'layout', label: 'Layout', open: false });
        } else {
          block.set('category', { id: 'basic-elements', label: 'Basic Elements', open: false });
        }
      });

      // Force re-render of the blocks panel
      const blocksContainer = document.getElementById('blocks-container');
      if (blocksContainer) {
        blocksContainer.innerHTML = '';
        blocksContainer.appendChild(e.BlockManager.render() as unknown as Node);
      }
    });

    e.on('change:changesCount', () => {
      setHasChanges(true);
    });

    return () => {
      e.destroy();
    };
  }, [id, isLoading]);

  const showToast = (message: string, type: 'success' | 'error' = 'success') => {
    if (typeof (window as any).o100ShowToast === 'function') {
      // Ensure the toast HTML exists
      if (!document.getElementById('o100-global-toast')) {
        const toastHTML = `
          <div id="o100-global-toast" style="position: fixed; bottom: -50px; right: 20px; background: #22c55e; color: #fff; padding: 12px 24px; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1000000; display: flex; align-items: center; gap: 8px; opacity: 0; transition: all 0.3s ease;">
            <span class="dashicons dashicons-yes-alt" style="font-size: 20px; width: 20px; height: 20px;"></span>
            <span class="toast-msg" style="font-size: 14px; font-weight: 500;"></span>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', toastHTML);
        // Add a minimal CSS rule to handle the "show" class used by o100ShowToast
        const style = document.createElement('style');
        style.innerHTML = '#o100-global-toast.show { bottom: 20px !important; opacity: 1 !important; }';
        document.head.appendChild(style);
      }
      (window as any).o100ShowToast(message, type);
    } else {
      const toast = document.createElement('div');
      toast.style.position = 'fixed';
      toast.style.bottom = '20px';
      toast.style.right = '20px';
      toast.style.padding = '12px 24px';
      toast.style.background = type === 'success' ? '#10b981' : '#ef4444';
      toast.style.color = '#fff';
      toast.style.borderRadius = '6px';
      toast.style.zIndex = '1000000';
      toast.textContent = message;
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 3000);
    }
  };

  const setDevice = (device: string) => editor?.Devices.select(device);
  const runCommand = (cmd: string) => editor?.runCommand(cmd);
  const undo = () => editor?.UndoManager.undo();
  const redo = () => editor?.UndoManager.redo();
  const clear = () => { if (confirm('Are you sure you want to clear the canvas?')) editor?.DomComponents.clear(); };

  return (
    <div className="o100ne-editor-wrap" style={{ display: 'flex', flexDirection: 'column', height: 'calc(100vh - 120px)', fontFamily: 'sans-serif' }}>
      <style>{`
        .gjs-rte-action { width: auto !important; min-width: 28px; padding: 0 4px !important; }
      `}</style>

      {/* Topbar */}
      <div style={{ minHeight: '60px', height: 'auto', background: '#fff', borderBottom: '1px solid #ddd', display: 'flex', alignItems: 'center', padding: '10px 20px', justifyContent: 'space-between', flexWrap: 'wrap', gap: '15px' }}>

        {/* LEFT: Template & Order Selection */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '15px' }}>
          <button type="button" onClick={() => {
            if (hasChanges && !window.confirm('You have unsaved changes. Are you sure you want to leave?')) return;
            navigate('/');
          }} style={{ background: 'transparent', border: 'none', padding: '0', cursor: 'pointer', fontSize: '20px', color: '#64748b' }} title="Back to Templates">
            <span className="dashicons dashicons-arrow-left-alt2"></span>
          </button>

          <select
            value={id}
            onChange={(e) => {
              window.location.hash = `/editor/${e.target.value}`;
              window.location.reload();
            }}
            style={{ padding: '8px 12px', borderRadius: '4px', border: '1px solid #cbd5e1', fontSize: '14px', width: '180px', outline: 'none' }}
          >
            {wcEmails.length > 0 && (
              <optgroup label="Admin Emails">
                {wcEmails.filter((email: any) => email.recipient === 'admin@store.com').map((email: any) => (
                  <option key={email.id} value={email.id}>{email.title}</option>
                ))}
              </optgroup>
            )}
            {wcEmails.length > 0 && (
              <optgroup label="Customer Emails">
                {wcEmails.filter((email: any) => email.recipient !== 'admin@store.com').map((email: any) => (
                  <option key={email.id} value={email.id}>{email.title}</option>
                ))}
              </optgroup>
            )}
            {wcEmails.length === 0 && <option value="new_order">New order</option>}
          </select>
        </div>

        {/* MIDDLE: Actions Bar */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
          <button type="button" onClick={clear} data-tooltip="Blank Canvas" className="o100ne-top-btn"><span className="dashicons dashicons-media-document"></span></button>
          <button type="button" onClick={() => setIsLibraryModalOpen(true)} data-tooltip="Import Template" className="o100ne-top-btn"><span className="dashicons dashicons-download"></span></button>
          <button type="button" data-tooltip="Reset" className="o100ne-top-btn"><span className="dashicons dashicons-update"></span></button>
          <div style={{ width: '1px', height: '20px', background: '#cbd5e1', margin: '0 5px' }}></div>
          <button type="button" onClick={undo} data-tooltip="Undo" className="o100ne-top-btn"><span className="dashicons dashicons-undo"></span></button>
          <button type="button" onClick={redo} data-tooltip="Redo" className="o100ne-top-btn"><span className="dashicons dashicons-redo"></span></button>
        </div>

        {/* RIGHT: Enable, Preview, Save */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '15px' }}>
          {/* Enable Toggle — activates/deactivates this template to replace WooCommerce default email */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <span style={{ fontSize: '13px', color: isTemplateEnabled ? '#6A4BFF' : '#94a3b8', fontWeight: 600, transition: 'color 0.2s' }}>
              {isTemplateEnabled ? 'Enabled' : 'Disabled'}
            </span>
            <label style={{ display: 'flex', alignItems: 'center', cursor: 'pointer' }}>
              <div style={{ position: 'relative' }} onClick={(evt) => {
                evt.preventDefault();
                if (!templateNumericId) return;
                const newStatus = isTemplateEnabled ? 'inactive' : 'active';
                const restPath = (window as any).o100neData?.rest_path || {};
                fetch(`${restPath.root}${restPath.base}/templates/change-status`, {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restPath.nonce },
                  body: JSON.stringify({ list_id: [templateNumericId], status: newStatus })
                })
                .then(r => r.json())
                .then(res => {
                  if (res.success) {
                    setIsTemplateEnabled(newStatus === 'active');
                  }
                })
                .catch(err => console.error('Failed to toggle template status:', err));
              }}>
                <div style={{ width: '36px', height: '20px', backgroundColor: isTemplateEnabled ? '#6A4BFF' : '#cbd5e1', borderRadius: '20px', transition: 'background-color 0.2s' }}></div>
                <div style={{ position: 'absolute', top: '3px', left: isTemplateEnabled ? '19px' : '3px', width: '14px', height: '14px', backgroundColor: 'white', borderRadius: '50%', transition: 'left 0.2s', boxShadow: '0 1px 3px rgba(0,0,0,0.2)' }}></div>
              </div>
            </label>
          </div>

          <button type="button" onClick={() => setIsShortcodesModalOpen(true)} style={{ background: '#f8fafc', color: '#475569', border: '1px solid #cbd5e1', padding: '8px 16px', borderRadius: '4px', cursor: 'pointer', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span className="dashicons dashicons-editor-code" style={{ fontSize: '16px', width: '16px', height: '16px' }}></span> Shortcodes
          </button>
          <button type="button" onClick={openPreviewModal} style={{ background: '#f8fafc', color: '#475569', border: '1px solid #cbd5e1', padding: '8px 16px', borderRadius: '4px', cursor: 'pointer', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span className="dashicons dashicons-visibility" style={{ fontSize: '16px', width: '16px', height: '16px' }}></span> Preview
          </button>
          <button type="button" onClick={() => setIsExportModalOpen(true)} style={{ background: '#f8fafc', color: '#475569', border: '1px solid #cbd5e1', padding: '8px 16px', borderRadius: '4px', cursor: 'pointer', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span className="dashicons dashicons-upload" style={{ fontSize: '16px', width: '16px', height: '16px' }}></span> Export
          </button>
          <button type="button"
            id="o100ne-save-btn"
            onClick={saveTemplate}
            disabled={isLoading || isSaving}
            className={isSaving ? 'loading' : ''}
          >
            <span className="dashicons dashicons-saved" style={{ fontSize: '16px', width: '16px', height: '16px' }}></span>
            {isSaving ? 'Saving...' : 'Save'}
          </button>
        </div>
      </div>

      {/* Editor Body */}
      <div style={{ display: 'flex', flex: 1, overflow: 'hidden' }}>

        {/* Canvas */}
        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', position: 'relative' }}>

          {/* GrapesJS Canvas */}
          <div ref={editorRef} style={{ flex: 1 }}></div>


        </div>

        {/* Right Sidebar */}
        <div style={{ width: '300px', background: '#fff', borderLeft: '1px solid #ddd', display: 'flex', flexDirection: 'column' }}>

          {/* Tabs */}
          <div style={{ display: 'flex', borderBottom: '1px solid #e2e8f0', background: '#f8fafc' }}>
            <button type="button"
              onClick={() => setActiveTab('blocks')}
              style={{
                flex: 1, padding: '15px 0', border: 'none', background: activeTab === 'blocks' ? '#ffffff' : 'transparent',
                borderBottom: activeTab === 'blocks' ? '2px solid #6A4BFF' : '2px solid transparent',
                color: activeTab === 'blocks' ? '#6A4BFF' : '#64748b', cursor: 'pointer', fontWeight: 600, fontSize: '14px'
              }}
            >
              Contents
            </button>
            <button type="button"
              onClick={() => setActiveTab('styles')}
              style={{
                flex: 1, padding: '15px 0', border: 'none', background: activeTab === 'styles' ? '#ffffff' : 'transparent',
                borderBottom: activeTab === 'styles' ? '2px solid #6A4BFF' : '2px solid transparent',
                color: activeTab === 'styles' ? '#6A4BFF' : '#64748b', cursor: 'pointer', fontWeight: 600, fontSize: '14px'
              }}
            >
              Settings
            </button>
            <button type="button"
              onClick={() => setActiveTab('global')}
              style={{
                flex: 1, padding: '15px 0', border: 'none', background: activeTab === 'global' ? '#ffffff' : 'transparent',
                borderBottom: activeTab === 'global' ? '2px solid #6A4BFF' : '2px solid transparent',
                color: activeTab === 'global' ? '#6A4BFF' : '#64748b', cursor: 'pointer', fontWeight: 600, fontSize: '14px'
              }}
            >
              Global
            </button>
          </div>

          {/* Tab Content */}
          <div style={{ flex: 1, overflowY: 'auto' }}>
            <div id="blocks-container" style={{ display: activeTab === 'blocks' ? 'block' : 'none' }}></div>
            <div style={{ display: activeTab === 'styles' ? 'block' : 'none' }}>

              {/* Component Traits (Translate Text / Options) */}
              <div className="o100ne-category open" style={{ borderBottom: '1px solid #e2e8f0' }}>
                <div className="o100ne-category-title" style={{ padding: '12px 15px', background: '#f8fafc', fontWeight: 600, color: '#334155', cursor: 'pointer' }}>
                  Component Options
                </div>

                {/* Show selected component name */}
                {selectedComponent && (
                  <div style={{ padding: '8px 15px', background: '#eef2ff', borderBottom: '1px solid #e2e8f0', fontSize: '12px', color: '#6A4BFF', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <span className="dashicons dashicons-screenoptions" style={{ fontSize: '14px', width: '14px', height: '14px' }}></span>
                    {(() => {
                      const cssClass = selectedComponent.getAttributes?.()?.['css-class'] || '';
                      if (cssClass.includes('woo-order-detail')) return '📋 Order Items';
                      if (cssClass.includes('woo-full-order-detail')) return '🧾 Order Details';
                      if (cssClass.includes('woo-products')) return '🛍️ Products Grid';
                      if (cssClass.includes('woo-order-subtotal')) return '💰 Order Subtotal';
                      if (cssClass.includes('woo-billing-address')) return '📬 Billing Address';
                      if (cssClass.includes('woo-shipping-address')) return '📦 Shipping Address';
                      if (cssClass.includes('woo-order-total')) return '💲 Order Total';
                      if (cssClass.includes('woo-shipping-method')) return '🚚 Shipping Method';
                      if (cssClass.includes('woo-payment-method')) return '💳 Payment Method';
                      if (cssClass.includes('woo-order-note')) return '📝 Order Note';
                      if (cssClass.includes('exfood-meta')) return '🍽️ Order100 Meta';
                      const parentCssClass = selectedComponent.parent?.()?.getAttributes?.()?.['css-class'] || '';
                      if (parentCssClass.includes('woo-order-detail')) return '📋 Order Items';
                      if (parentCssClass.includes('woo-full-order-detail')) return '🧾 Order Details';
                      if (parentCssClass.includes('woo-products')) return '🛍️ Products Grid';
                      if (parentCssClass.includes('woo-order-subtotal')) return '💰 Order Subtotal';
                      const type = selectedComponent.get('type') || 'component';
                      const typeMap: Record<string, string> = {
                        'mj-text': '📝 Text', 'mj-image': '🖼️ Image', 'mj-button': '🔘 Button',
                        'mj-divider': '➖ Divider', 'mj-spacer': '↕️ Spacer', 'mj-social': '🔗 Social',
                        'mj-section': '📐 Section', 'mj-column': '📏 Column', 'mj-hero': '🦸 Hero',
                      };
                      return typeMap[type] || type.replace('mj-', '').charAt(0).toUpperCase() + type.replace('mj-', '').slice(1);
                    })()}
                  </div>
                )}

                {/* Native GrapesJS Traits */}
                <div id="traits-container"></div>

                {/* Custom React-based Config for specific components */}
                {(() => {
                  if (!selectedComponent) return null;

                  const getParentWithAttr = (comp: any, attr: string) => {
                    let curr = comp;
                    while (curr) {
                      if (curr.getAttributes && curr.getAttributes()[attr]) return curr;
                      curr = curr.parent && curr.parent();
                    }
                    return null;
                  };

                  const exfoodComp = getParentWithAttr(selectedComponent, 'data-exfood-meta');

                  return (
                    <div key={sidebarKey} style={{ padding: '15px' }}>

                      {/* BUTTON OPTIONS */}
                      {selectedComponent.get('type') === 'mj-button' && (
                        <>
                          <h4 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#475569' }}>Button Settings</h4>
                          <div style={{ marginBottom: '10px' }}>
                            <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>Button Text</label>
                            <input
                              type="text"
                              defaultValue={selectedComponent.components().models[0]?.get('content') || ''}
                              onBlur={(e) => {
                                const txt = selectedComponent.components().models[0];
                                if (txt) txt.set('content', e.target.value);
                                else selectedComponent.components().add({ type: 'textnode', content: e.target.value });
                              }}
                              style={{ width: '100%', padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                            />
                          </div>
                          <div style={{ marginBottom: '10px' }}>
                            <LinkPicker
                              label="Link URL"
                              defaultValue={selectedComponent.getAttributes().href || ''}
                              onChange={(url) => selectedComponent.addAttributes({ href: url })}
                            />
                          </div>
                        </>
                      )}

                      {/* IMAGE OPTIONS */}
                      {selectedComponent.get('type') === 'mj-image' && (() => {
                        const imgAttrs = selectedComponent.getAttributes();
                        return (
                        <>
                          <h4 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#475569' }}>Image Settings</h4>

                          {/* Image URL Input */}
                          <div style={{ marginBottom: '10px' }}>
                            <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>Image URL</label>
                            <input
                              type="text"
                              defaultValue={imgAttrs.src || ''}
                              onBlur={(e) => {
                                selectedComponent.addAttributes({ src: e.target.value });
                                forceRefreshSidebar();
                              }}
                              style={{ width: '100%', padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                            />
                          </div>

                          {/* WordPress Media Library & Upload Buttons */}
                          <div style={{ marginBottom: '12px', display: 'flex', gap: '6px' }}>
                            <button type="button"
                              onClick={() => {
                                const pickerUrl = (window as any).o100neData?.urls?.media_picker_url || '';
                                if (!pickerUrl) { showToast('Media picker URL not configured.', 'error'); return; }
                                const popup = window.open(pickerUrl, 'wpMediaPicker', 'width=1200,height=700,scrollbars=yes,resizable=yes');
                                if (!popup) {
                                  showToast('Popup blocked. Please allow popups for this site.', 'error');
                                  return;
                                }
                                const comp = selectedComponent;
                                const handler = (event: MessageEvent) => {
                                  if (event.data && event.data.type === 'wp-media-selected') {
                                    const url = event.data.url || '';
                                    if (url) {
                                      comp.set('src', url);
                                      comp.addAttributes({ src: url, alt: event.data.alt || '' });
                                      forceRefreshSidebar();
                                    }
                                    window.removeEventListener('message', handler);
                                  }
                                };
                                window.addEventListener('message', handler);
                              }}
                              style={{
                                flex: 1, padding: '10px', background: '#6A4BFF', color: '#fff', border: 'none',
                                borderRadius: '6px', cursor: 'pointer', fontWeight: 600, fontSize: '13px',
                              }}
                            >
                              Media Library
                            </button>
                            <button type="button"
                              onClick={() => {
                                editor!.runCommand('open-assets', { target: selectedComponent });
                              }}
                              style={{
                                padding: '10px 14px', background: '#e2e8f0', color: '#475569', border: 'none',
                                borderRadius: '6px', cursor: 'pointer', fontWeight: 600, fontSize: '13px',
                              }}
                            >
                              Upload
                            </button>
                          </div>

                          {/* Link URL */}
                          <LinkPicker
                            label="Link URL (Optional)"
                            defaultValue={imgAttrs.href || ''}
                            onChange={(url) => selectedComponent.addAttributes({ href: url })}
                          />
                        </>
                        );
                      })()}

                      {/* SOCIAL OPTIONS */}
                      {selectedComponent.get('type') === 'mj-social' && (
                        <>
                          <h4 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#475569' }}>Social Links</h4>
                          {selectedComponent.components().models.map((child: any) => {
                            const attrs = child.getAttributes();
                            const currentName = attrs.name || attrs['mj-name'] || child.get('name') || 'facebook';

                            return (
                              <div key={child.getId()} style={{ marginBottom: '10px', padding: '10px', border: '1px solid #e2e8f0', borderRadius: '4px', background: '#f8fafc' }}>
                                <div style={{ display: 'flex', marginBottom: '8px', gap: '8px' }}>
                                  <select
                                    defaultValue={currentName}
                                    onChange={(e) => child.addAttributes({ name: e.target.value })}
                                    style={{ flex: 1, padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                                  >
                                    <option value="facebook">Facebook</option>
                                    <option value="twitter">Twitter</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="youtube">YouTube</option>
                                    <option value="pinterest">Pinterest</option>
                                  </select>
                                  <button type="button" onClick={() => child.remove()} style={{ background: '#fee2e2', color: '#ef4444', border: 'none', borderRadius: '4px', padding: '0 10px', cursor: 'pointer' }}>&times;</button>
                                </div>
                                <input
                                  type="text"
                                  placeholder="https://"
                                  defaultValue={attrs.href || '#'}
                                  onBlur={(e) => child.addAttributes({ href: e.target.value })}
                                  style={{ width: '100%', padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                                />
                              </div>
                            );
                          })}
                          <button type="button" onClick={() => {
                            selectedComponent.components().add({ type: 'mj-social-element', attributes: { name: 'facebook', href: '#' } });
                          }} style={{ width: '100%', padding: '8px', background: '#ffffff', border: '1px dashed #cbd5e1', borderRadius: '4px', cursor: 'pointer', color: '#64748b', fontWeight: 600 }}>
                            + Add Network
                          </button>
                        </>
                      )}

                      {/* EXFOOD META OPTIONS */}
                      {exfoodComp && (
                        <>
                          <h4 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#475569' }}>Order100 Data Fields</h4>

                          {[
                            { key: 'method', label: 'Order Method', placeholder: '{o100_order_method}' },
                            { key: 'prep', label: 'Prep Time', placeholder: '{o100_prep_time}' },
                            { key: 'birthday', label: 'Birthday Time', placeholder: '{o100_birthday}' }
                          ].map(field => {
                            const attrName = `exfood-show-${field.key}`;
                            const isChecked = exfoodComp.getAttributes()[attrName] !== 'false';

                            return (
                              <div key={field.key} style={{ marginBottom: '10px', display: 'flex', alignItems: 'center' }}>
                                <label style={{ display: 'flex', alignItems: 'center', cursor: 'pointer', fontSize: '13px', color: '#475569' }}>
                                  <input
                                    type="checkbox"
                                    defaultChecked={isChecked}
                                    onChange={(e) => {
                                      const checked = e.target.checked;
                                      exfoodComp.addAttributes({ [attrName]: checked ? 'true' : 'false' });

                                      // Rebuild HTML
                                      const textComp = exfoodComp.find('[data-exfood-text]')[0];
                                      if (textComp) {
                                        const attrs = exfoodComp.getAttributes();
                                        const showMethod = attrs['exfood-show-method'] !== 'false';
                                        const showPrep = attrs['exfood-show-prep'] !== 'false';
                                        const showBirthday = attrs['exfood-show-birthday'] !== 'false';

                                        let newContent = '';
                                        if (showMethod) newContent += '<div class="exfood-method" style="margin-bottom:8px;"><strong>Order Method:</strong> {o100_order_method}</div>';
                                        if (showPrep) newContent += '<div class="exfood-prep" style="margin-bottom:8px;"><strong>Prep Time:</strong> {o100_prep_time}</div>';
                                        if (showBirthday) newContent += '<div class="exfood-birthday" style="margin-bottom:8px;"><strong>Birthday:</strong> {o100_birthday}</div>';

                                        // If all empty, put a placeholder to keep component visible/selectable in editor
                                        if (!newContent) {
                                          newContent = '<div style="color:#94a3b8; font-style:italic;">[Order100 Meta Block: All fields hidden]</div>';
                                        }

                                        textComp.components(newContent);
                                      }
                                    }}
                                    style={{ marginRight: '8px' }}
                                  />
                                  Show {field.label}
                                </label>
                              </div>
                            );
                          })}
                        </>
                      )}

                      {/* CONDITIONAL SECTION OPTIONS — walks up parent chain so clicking any child shows it */}
                      {(() => {
                        // Walk up parent chain to find the conditional section ancestor
                        let condComp: any = null;
                        let walk: any = selectedComponent;
                        for (let depth = 0; depth < 6 && walk; depth++) {
                          const css = walk.getAttributes?.()?.['css-class'] || '';
                          if (css.includes('o100ne-conditional')) { condComp = walk; break; }
                          walk = walk.parent?.();
                        }
                        if (!condComp) return null;

                        const isSelf = condComp === selectedComponent;
                        const attrs = condComp.getAttributes();
                        const currentField = attrs['data-condition-field'] || 'o100_order_method';
                        const currentOp = attrs['data-condition-operator'] || 'equals';
                        const currentValue = attrs['data-condition-value'] || '';
                        const fieldDef = getConditionFieldDef(currentField);

                        // Use condComp.cid as key to force React to fully remount when switching between different conditional sections
                        const condKey = condComp.cid || condComp.getId?.() || 'cond';

                        return (
                          <div key={condKey}>
                            <h4 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#475569', display: 'flex', alignItems: 'center', gap: '6px' }}>
                              <span style={{ fontSize: '16px' }}>🔀</span> Condition Settings
                            </h4>

                            {/* Show a quick "select section" button when editing a child */}
                            {!isSelf && (
                              <button type="button"
                                onClick={() => { (window as any).__gjsEditor?.select(condComp); }}
                                style={{ width: '100%', padding: '7px 10px', marginBottom: '10px', background: 'linear-gradient(135deg, #7c3aed, #6366f1)', color: '#fff', border: 'none', borderRadius: '6px', cursor: 'pointer', fontWeight: 600, fontSize: '11px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '5px' }}
                              >
                                ⬆️ Select Conditional Section
                              </button>
                            )}

                            <div style={{ padding: '12px', background: 'linear-gradient(135deg, #faf5ff, #f0f9ff)', borderRadius: '8px', border: '1px solid #e9d5ff', marginBottom: '12px' }}>
                              <div style={{ fontSize: '11px', color: '#7c3aed', fontWeight: 600, marginBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.5px' }}>Show this section when:</div>

                              {/* Field */}
                              <div style={{ marginBottom: '8px' }}>
                                <label style={{ display: 'block', fontSize: '11px', color: '#64748b', marginBottom: '3px' }}>Field</label>
                                <select
                                  value={currentField}
                                  onChange={(ev) => {
                                    condComp.addAttributes({ 'data-condition-field': ev.target.value, 'data-condition-value': '' });
                                    forceRefreshSidebar();
                                  }}
                                  style={{ width: '100%', padding: '7px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '12px', background: '#fff' }}
                                >
                                  {CONDITION_FIELDS.map(group => (
                                    <optgroup key={group.group} label={group.group}>
                                      {group.fields.map(f => <option key={f.value} value={f.value}>{f.label}</option>)}
                                    </optgroup>
                                  ))}
                                </select>
                              </div>

                              {/* Operator */}
                              <div style={{ marginBottom: '8px' }}>
                                <label style={{ display: 'block', fontSize: '11px', color: '#64748b', marginBottom: '3px' }}>Operator</label>
                                <select
                                  value={currentOp}
                                  onChange={(ev) => {
                                    condComp.addAttributes({ 'data-condition-operator': ev.target.value });
                                    forceRefreshSidebar();
                                  }}
                                  style={{ width: '100%', padding: '7px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '12px', background: '#fff' }}
                                >
                                  {CONDITION_OPERATORS.map(op => <option key={op.value} value={op.value}>{op.label}</option>)}
                                </select>
                              </div>

                              {/* Value — dynamic based on field type */}
                              {currentOp !== 'is_empty' && currentOp !== 'is_not_empty' && (
                                <div style={{ marginBottom: '4px' }}>
                                  <label style={{ display: 'block', fontSize: '11px', color: '#64748b', marginBottom: '3px' }}>Value</label>
                                  {fieldDef?.type === 'select' && fieldDef.options ? (
                                    <select
                                      value={currentValue}
                                      onChange={(ev) => {
                                        condComp.addAttributes({ 'data-condition-value': ev.target.value });
                                        forceRefreshSidebar();
                                      }}
                                      style={{ width: '100%', padding: '7px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '12px', background: '#fff' }}
                                    >
                                      <option value="">— Select —</option>
                                      {fieldDef.options.map(o => <option key={o} value={o}>{o}</option>)}
                                    </select>
                                  ) : (
                                    <input
                                      type={fieldDef?.type === 'number' ? 'number' : 'text'}
                                      defaultValue={currentValue}
                                      onBlur={(ev) => {
                                        condComp.addAttributes({ 'data-condition-value': ev.target.value });
                                        forceRefreshSidebar();
                                      }}
                                      placeholder={fieldDef?.type === 'number' ? '0' : 'Enter value...'}
                                      style={{ width: '100%', padding: '7px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '12px', boxSizing: 'border-box' }}
                                    />
                                  )}
                                </div>
                              )}
                            </div>

                            <div style={{ fontSize: '11px', color: '#94a3b8', padding: '6px 8px', background: '#f8fafc', borderRadius: '4px', border: '1px solid #e2e8f0', lineHeight: '1.5' }}>
                              💡 This entire section will be hidden in the email if the condition is not met.
                            </div>
                          </div>
                        );
                      })()}

                      {/* WOOCOMMERCE ORDER DETAIL OPTIONS */}
                      {(() => {
                        // Only show if the selected component IS the woo-order-detail block or its direct mj-text child
                        const selfCss = selectedComponent.getAttributes?.()?.['css-class'] || '';
                        const parentCss = selectedComponent.parent?.()?.getAttributes?.()?.['css-class'] || '';
                        const isOrderDetail = selfCss.includes('woo-order-detail') || parentCss.includes('woo-order-detail');
                        const isFullOrderDetail = selfCss.includes('woo-full-order-detail') || parentCss.includes('woo-full-order-detail');
                        if (!isOrderDetail && !isFullOrderDetail) return null;
                        const orderDetailComp = selfCss.includes('woo-order-detail') || selfCss.includes('woo-full-order-detail') ? selectedComponent : selectedComponent.parent();
                        const attrs = orderDetailComp.getAttributes();
                        const isFull = isFullOrderDetail;
                        return (
                          <>
                            <h4 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#475569' }}>{isFull ? 'Order Details Options' : 'Order Items Options'}</h4>
                            <div style={{ marginBottom: '15px' }}>
                              <label style={{ display: 'flex', alignItems: 'center', fontSize: '13px', color: '#334155', cursor: 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={attrs['data-show-sku'] !== 'false'}
                                  onChange={(e) => {
                                    orderDetailComp.addAttributes({ 'data-show-sku': e.target.checked ? 'true' : 'false' });
                                    renderOrderTable(orderDetailComp);
                                    forceRefreshSidebar();
                                  }}
                                  style={{ marginRight: '8px' }}
                                />
                                Show SKU
                              </label>
                            </div>
                            <div style={{ marginBottom: '15px' }}>
                              <label style={{ display: 'flex', alignItems: 'center', fontSize: '13px', color: '#334155', cursor: 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={attrs['data-show-image'] === 'true'}
                                  onChange={(e) => {
                                    orderDetailComp.addAttributes({ 'data-show-image': e.target.checked ? 'true' : 'false' });
                                    renderOrderTable(orderDetailComp);
                                    forceRefreshSidebar();
                                  }}
                                  style={{ marginRight: '8px' }}
                                />
                                Show Image
                              </label>
                            </div>
                            {attrs['data-show-image'] === 'true' && (
                              <div style={{ marginBottom: '15px', paddingLeft: '24px' }}>
                                <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>Image Size (px)</label>
                                <input
                                  type="number"
                                  defaultValue={attrs['data-image-size'] || '32'}
                                  onChange={(e) => {
                                    orderDetailComp.addAttributes({ 'data-image-size': e.target.value });
                                    renderOrderTable(orderDetailComp);
                                  }}
                                  style={{ width: '100%', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                                />
                              </div>
                            )}
                            <div style={{ marginBottom: '15px' }}>
                              <label style={{ display: 'flex', alignItems: 'center', fontSize: '13px', color: '#334155', cursor: 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={attrs['data-remove-link'] === 'true'}
                                  onChange={(e) => {
                                    orderDetailComp.addAttributes({ 'data-remove-link': e.target.checked ? 'true' : 'false' });
                                    renderOrderTable(orderDetailComp);
                                    forceRefreshSidebar();
                                  }}
                                  style={{ marginRight: '8px' }}
                                />
                                Remove Product Link
                              </label>
                            </div>
                            <div style={{ marginBottom: '15px' }}>
                              <label style={{ display: 'flex', alignItems: 'center', fontSize: '13px', color: '#334155', cursor: 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={attrs['data-show-price'] !== 'false'}
                                  onChange={(e) => {
                                    orderDetailComp.addAttributes({ 'data-show-price': e.target.checked ? 'true' : 'false' });
                                    renderOrderTable(orderDetailComp);
                                    forceRefreshSidebar();
                                  }}
                                  style={{ marginRight: '8px' }}
                                />
                                Show Price Column
                              </label>
                            </div>

                            {isFull && (
                              <div style={{ marginTop: '15px', borderTop: '1px solid #e2e8f0', paddingTop: '15px' }}>
                                <h5 style={{ margin: '0 0 10px 0', fontSize: '13px', color: '#475569' }}>Footer Labels</h5>
                                {['cart_subtotal', 'shipping', 'tax', 'fee', 'discount', 'order_total'].map(key => (
                                  <div key={key} style={{ marginBottom: '10px' }}>
                                    <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px', textTransform: 'capitalize' }}>
                                      {key.replace('_', ' ')} Label
                                    </label>
                                    <input
                                      type="text"
                                      value={attrs[`data-label-${key}`] || ''}
                                      onChange={(e) => {
                                        orderDetailComp.addAttributes({ [`data-label-${key}`]: e.target.value });
                                        renderFullOrderTable(orderDetailComp);
                                        forceRefreshSidebar();
                                      }}
                                      style={{ width: '100%', padding: '6px 8px', borderRadius: '4px', border: '1px solid #cbd5e1', fontSize: '13px' }}
                                      placeholder={`Default (${key.replace('_', ' ')})`}
                                    />
                                  </div>
                                ))}
                              </div>
                            )}
                          </>
                        );
                      })()}

                      {/* WOOCOMMERCE PRODUCT OPTIONS */}
                      {(() => {
                        const productComp = closestByCssClass(selectedComponent, 'woo-products');
                        if (!productComp) return null;
                        const attrs = productComp.getAttributes();
                        return (
                          <>
                            <h4 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#475569' }}>Products Display Options</h4>
                            <div style={{ marginBottom: '15px' }}>
                              <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>Product Type</label>
                              <select
                                defaultValue={attrs['data-product-type'] || 'newest'}
                                onChange={(e) => {
                                  productComp.addAttributes({ 'data-product-type': e.target.value });
                                  renderProductsTable(productComp);
                                  forceRefreshSidebar();
                                }}
                                style={{ width: '100%', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                              >
                                <option value="featured">Featured</option>
                                <option value="newest">Newest</option>
                                <option value="on_sale">On Sale</option>
                                <option value="cross_sells">Cross-sells</option>
                                <option value="up_sells">Up-sells</option>
                                <option value="specific">Specific Products</option>
                              </select>
                            </div>

                            {attrs['data-product-type'] === 'specific' ? (
                              <div style={{ marginBottom: '15px' }}>
                                <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>Product IDs (comma separated)</label>
                                <input
                                  type="text"
                                  defaultValue={attrs['data-specific-ids'] || ''}
                                  placeholder="e.g. 12, 34, 56"
                                  onBlur={(e) => {
                                    productComp.addAttributes({ 'data-specific-ids': e.target.value });
                                    renderProductsTable(productComp);
                                  }}
                                  style={{ width: '100%', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                                />
                              </div>
                            ) : (
                              <div style={{ marginBottom: '15px' }}>
                                <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>Exclude products</label>
                                <input
                                  type="text"
                                  defaultValue={attrs['data-exclude-ids'] || ''}
                                  onBlur={(e) => {
                                    productComp.addAttributes({ 'data-exclude-ids': e.target.value });
                                    renderProductsTable(productComp);
                                  }}
                                  style={{ width: '100%', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                                />
                              </div>
                            )}

                            <div style={{ display: 'flex', gap: '10px', marginBottom: '15px' }}>
                              <div style={{ flex: 1 }}>
                                <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>Max row</label>
                                <select 
                                  defaultValue={attrs['data-max-rows'] || '2'}
                                  onChange={(e) => {
                                    productComp.addAttributes({ 'data-max-rows': e.target.value });
                                    renderProductsTable(productComp);
                                    forceRefreshSidebar();
                                  }}
                                  style={{ width: '100%', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                                >
                                  <option value="1">1</option>
                                  <option value="2">2</option>
                                  <option value="3">3</option>
                                  <option value="4">4</option>
                                </select>
                              </div>
                              <div style={{ flex: 1 }}>
                                <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>Column</label>
                                <select 
                                  defaultValue={attrs['data-columns'] || '2'}
                                  onChange={(e) => {
                                    productComp.addAttributes({ 'data-columns': e.target.value });
                                    renderProductsTable(productComp);
                                    forceRefreshSidebar();
                                  }}
                                  style={{ width: '100%', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                                >
                                  <option value="1">1</option>
                                  <option value="2">2</option>
                                  <option value="3">3</option>
                                </select>
                              </div>
                            </div>

                            <div style={{ marginBottom: '15px' }}>
                              <h4 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#475569' }}>Auto add to cart in product URL</h4>
                              <label style={{ display: 'flex', alignItems: 'center', fontSize: '13px', color: '#334155', cursor: 'pointer' }}>
                                <input
                                  type="checkbox"
                                  defaultChecked={attrs['data-add-to-cart-url'] === 'true'}
                                  onChange={(e) => {
                                    productComp.addAttributes({ 'data-add-to-cart-url': e.target.checked ? 'true' : 'false' });
                                    renderProductsTable(productComp);
                                  }}
                                  style={{ marginRight: '8px' }}
                                />
                                Enable
                              </label>
                            </div>
                            <div style={{ marginBottom: '15px' }}>
                              <label style={{ display: 'flex', alignItems: 'center', fontSize: '13px', color: '#334155', cursor: 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={attrs['data-show-image'] !== 'false'}
                                  onChange={(e) => {
                                    productComp.addAttributes({ 'data-show-image': e.target.checked ? 'true' : 'false' });
                                    renderProductsTable(productComp);
                                    forceRefreshSidebar();
                                  }}
                                  style={{ marginRight: '8px' }}
                                />
                                Show Image
                              </label>
                            </div>
                            {attrs['data-show-image'] !== 'false' && (
                              <div style={{ marginBottom: '15px', paddingLeft: '24px' }}>
                                <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>Image Size (px)</label>
                                <input
                                  type="number"
                                  defaultValue={attrs['data-image-size'] || '80'}
                                  onChange={(e) => {
                                    productComp.addAttributes({ 'data-image-size': e.target.value });
                                    renderProductsTable(productComp);
                                  }}
                                  style={{ width: '100%', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                                />
                              </div>
                            )}
                            <div style={{ marginBottom: '15px' }}>
                              <label style={{ display: 'flex', alignItems: 'center', fontSize: '13px', color: '#334155', cursor: 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={attrs['data-remove-link'] === 'true'}
                                  onChange={(e) => {
                                    productComp.addAttributes({ 'data-remove-link': e.target.checked ? 'true' : 'false' });
                                    renderProductsTable(productComp);
                                    forceRefreshSidebar();
                                  }}
                                  style={{ marginRight: '8px' }}
                                />
                                Remove Product Link
                              </label>
                            </div>
                            <div style={{ marginBottom: '15px' }}>
                              <label style={{ display: 'flex', alignItems: 'center', fontSize: '13px', color: '#334155', cursor: 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={attrs['data-show-sku'] !== 'false'}
                                  onChange={(e) => {
                                    productComp.addAttributes({ 'data-show-sku': e.target.checked ? 'true' : 'false' });
                                    renderProductsTable(productComp);
                                    forceRefreshSidebar();
                                  }}
                                  style={{ marginRight: '8px' }}
                                />
                                Show SKU
                              </label>
                            </div>
                            <div style={{ marginBottom: '15px' }}>
                              <label style={{ display: 'flex', alignItems: 'center', fontSize: '13px', color: '#334155', cursor: 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={attrs['data-show-price'] !== 'false'}
                                  onChange={(e) => {
                                    productComp.addAttributes({ 'data-show-price': e.target.checked ? 'true' : 'false' });
                                    renderProductsTable(productComp);
                                    forceRefreshSidebar();
                                  }}
                                  style={{ marginRight: '8px' }}
                                />
                                Show Price
                              </label>
                            </div>
                            <div style={{ fontSize: '13px', color: '#64748b', marginTop: '10px' }}>
                              Use the native Styles panel (paint brush icon) to adjust spacing, borders, fonts, and colors of individual elements.
                            </div>


                          </>
                        );
                      })()}

                      {/* OTHER WOOCOMMERCE ELEMENTS TRANSLATIONS */}
                      {(() => {
                        const types = [
                          { cls: 'woo-order-subtotal', label: 'Subtotal Text', default: 'Subtotal:', hook: '{order_subtotal}' },
                          { cls: 'woo-order-total', label: 'Total Text', default: 'Total:', hook: '{order_total}' },
                          { cls: 'woo-shipping-method', label: 'Shipping Title', default: 'Shipment Method', hook: '{shipping_method}', isBox: true },
                          { cls: 'woo-payment-method', label: 'Payment Title', default: 'Payment Method', hook: '{payment_method}', isBox: true },
                          { cls: 'woo-customer-note', label: 'Note Title', default: 'Note', hook: '{customer_note}', isBox: true },
                          { cls: 'woo-coupon', label: 'Coupon Text', default: 'Coupon Applied:', hook: '{coupon_code}' }
                        ];

                        const match = types.find(t => closestByCssClass(selectedComponent, t.cls));
                        if (!match) return null;

                        const comp = closestByCssClass(selectedComponent, match.cls);
                        const attrs = comp.getAttributes();

                        return (
                          <>
                            <h4 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#475569' }}>Translate Text</h4>
                            <div style={{ marginBottom: '10px' }}>
                              <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>{match.label}</label>
                              <input
                                type="text"
                                defaultValue={attrs['data-text-label'] || match.default}
                                onChange={(e) => {
                                  const val = e.target.value;
                                  comp.addAttributes({ 'data-text-label': val });
                                  const textComp = findMjText(comp) || (comp.get('type') === 'mj-text' ? comp : null);
                                  if (textComp) {
                                    const html = match.isBox
                                      ? `<div style="margin-bottom:5px; font-weight:bold;">${val}</div><div style="color:#666;">${match.hook}</div>`
                                      : `${val} <strong>${match.hook}</strong>`;
                                    textComp.components(html);
                                  }
                                }}
                                style={{ width: '100%', padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                              />
                            </div>
                          </>
                        );
                      })()}

                      {/* WOOCOMMERCE ADDRESSES */}
                      {(closestByCssClass(selectedComponent, 'woo-billing-address') || closestByCssClass(selectedComponent, 'woo-shipping-address')) && (
                        <>
                          <h4 style={{ margin: '0 0 10px 0', fontSize: '14px', color: '#475569' }}>Translate Headers</h4>
                          <div style={{ marginBottom: '10px' }}>
                            <label style={{ display: 'block', fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>Section Title</label>
                            <input
                              type="text"
                              placeholder="Address"
                              onBlur={(e) => {
                                const strong = selectedComponent.find('strong');
                                if (strong[0] && strong[0].components().models[0]) strong[0].components().models[0].set('content', e.target.value);
                              }}
                              style={{ width: '100%', padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '13px' }}
                            />
                          </div>
                        </>
                      )}

                      {/* FALLBACK FOR UNKNOWN BLOCKS */}
                      {(!['mj-button', 'mj-image', 'mj-social'].includes(selectedComponent.get('type')) &&
                        !closestByCssClass(selectedComponent, 'woo-order-detail') && !closestByCssClass(selectedComponent, 'woo-full-order-detail') && !closestByCssClass(selectedComponent, 'woo-products') && !closestByCssClass(selectedComponent, 'woo-order-subtotal') && !closestByCssClass(selectedComponent, 'woo-order-total') && !closestByCssClass(selectedComponent, 'woo-shipping-method') && !closestByCssClass(selectedComponent, 'woo-payment-method') && !closestByCssClass(selectedComponent, 'woo-customer-note') && !closestByCssClass(selectedComponent, 'woo-billing-address') && !closestByCssClass(selectedComponent, 'woo-shipping-address') && !closestByCssClass(selectedComponent, 'woo-coupon') && !exfoodComp) && (
                          <div style={{ fontSize: '13px', color: '#64748b' }}>
                            This component's content can be edited directly in the canvas (double-click text). Use the Styles panel to change its appearance.
                          </div>
                        )}

                    </div>
                  );
                })()}
              </div>

              {/* Standard Styles Panel */}
              <div id="styles-container"></div>
            </div>

            <div style={{ display: activeTab === 'global' ? 'block' : 'none' }}>
              {editor && <GlobalSettings editor={editor} />}
            </div>
          </div>

        </div>
      </div>

      {/* Shortcodes Modal */}
      {isShortcodesModalOpen && (
        <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(15, 23, 42, 0.75)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div style={{ background: '#fff', width: '90%', maxWidth: '800px', height: '80vh', borderRadius: '12px', display: 'flex', flexDirection: 'column', overflow: 'hidden', boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)' }}>

            {/* Header */}
            <div style={{ padding: '20px 24px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc' }}>
              <div>
                <h2 style={{ margin: 0, fontSize: '18px', color: '#1e293b', display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <span className="dashicons dashicons-editor-code" style={{ color: '#6A4BFF' }}></span>
                  Available Shortcodes
                </h2>
                {activeRte && <p style={{ margin: '4px 0 0 0', fontSize: '13px', color: '#10b981' }}>Click any variable to insert directly into your text editor.</p>}
              </div>
              <button type="button" onClick={() => { setIsShortcodesModalOpen(false); setActiveRte(null); }} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b' }}>
                <span className="dashicons dashicons-no-alt" style={{ fontSize: '20px' }}></span>
              </button>
            </div>

            {/* Body */}
            <div style={{ padding: '24px', flex: 1, overflowY: 'auto' }}>
              <div style={{ marginBottom: '20px', color: '#64748b', fontSize: '14px' }}>
                {activeRte ? 'Click an item below to insert it at your cursor position.' : 'Click on the copy icon to copy a shortcode to your clipboard, then paste it anywhere in your email text.'}
              </div>

              {SHORTCODE_CATEGORIES.map((category, idx) => {
                const isOpen = openShortcodeCategories.includes(idx);
                return (
                  <div key={idx} style={{ marginBottom: '24px' }}>
                    <h3
                      onClick={() => {
                        setOpenShortcodeCategories(prev =>
                          prev.includes(idx) ? prev.filter(i => i !== idx) : [...prev, idx]
                        );
                      }}
                      style={{ margin: '0 0 12px 0', fontSize: '15px', color: '#334155', borderBottom: '2px solid #e2e8f0', paddingBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.5px', cursor: 'pointer', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}
                    >
                      <span>{category.title}</span>
                      <span className={`dashicons dashicons-arrow-${isOpen ? 'up' : 'down'}-alt2`} style={{ fontSize: '16px', color: '#94a3b8' }}></span>
                    </h3>

                    {isOpen && (
                      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(350px, 1fr))', gap: '12px' }}>
                        {category.items.map((item, i) => (
                          <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '6px', padding: '8px 12px' }}>
                            <div>
                              <div style={{ fontSize: '13px', color: '#475569', marginBottom: '4px', fontWeight: 500 }}>{item.desc}</div>
                              <div style={{ fontSize: '12px', color: '#6A4BFF', fontFamily: 'monospace', background: '#eef2ff', padding: '2px 6px', borderRadius: '4px', display: 'inline-block' }}>
                                {item.code}
                              </div>
                            </div>
                            <button type="button"
                              onClick={(e) => {
                                if (activeRte) {
                                  const savedRange = (window as any)._o100neSavedRange;
                                  if (savedRange) {
                                    // Restore selection in the correct document (iframe)
                                    const savedDoc = (window as any)._o100neSavedDoc || document;
                                    const sel = savedDoc.defaultView?.getSelection() || window.getSelection();
                                    sel?.removeAllRanges();
                                    sel?.addRange(savedRange);
                                  }
                                  activeRte.insertHTML(item.code);
                                  setIsShortcodesModalOpen(false);
                                  setActiveRte(null);
                                  (window as any)._o100neSavedRange = null;
                                } else {
                                  navigator.clipboard.writeText(item.code);
                                  const icon = e.currentTarget.querySelector('span');
                                  if (icon) {
                                    icon.classList.remove('dashicons-admin-page');
                                    icon.classList.add('dashicons-yes');
                                    icon.style.color = '#10b981';
                                    setTimeout(() => {
                                      icon.classList.remove('dashicons-yes');
                                      icon.classList.add('dashicons-admin-page');
                                      icon.style.color = '#94a3b8';
                                    }, 2000);
                                  }
                                }
                              }}
                              style={{ background: 'none', border: 'none', cursor: 'pointer', padding: '8px', borderRadius: '4px' }}
                              title={activeRte ? "Insert Shortcode" : "Copy to clipboard"}
                            >
                              <span className={`dashicons ${activeRte ? 'dashicons-insert' : 'dashicons-admin-page'}`} style={{ color: activeRte ? '#6A4BFF' : '#94a3b8', transition: 'color 0.2s' }}></span>
                            </button>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>

          </div>
        </div>
      )}

      {/* Preview Modal */}
      {isPreviewOpen && (
        <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(15, 23, 42, 0.75)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div style={{ background: '#fff', width: '90%', maxWidth: '1000px', height: '90vh', borderRadius: '12px', display: 'flex', flexDirection: 'column', overflow: 'hidden', boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)' }}>

            {/* Modal Header */}
            <div style={{ padding: '20px 24px', borderBottom: '1px solid #e2e8f0', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
              <h2 style={{ margin: 0, fontSize: '20px', fontWeight: 600, color: '#1e293b' }}>Preview</h2>

              {/* Desktop/Mobile Toggles */}
              <div style={{ display: 'flex', gap: '10px' }}>
                <button type="button" style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px', borderRadius: '4px' }}><span className="dashicons dashicons-desktop" style={{ fontSize: '20px', color: '#64748b' }}></span></button>
                <button type="button" style={{ background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px', borderRadius: '4px' }}><span className="dashicons dashicons-smartphone" style={{ fontSize: '20px', color: '#64748b' }}></span></button>
              </div>

              <button type="button" onClick={() => setIsPreviewOpen(false)} style={{ background: 'transparent', border: 'none', cursor: 'pointer', color: '#94a3b8', padding: '4px' }}>
                <span className="dashicons dashicons-no-alt" style={{ fontSize: '24px' }}></span>
              </button>
            </div>

            {/* Modal Actions */}
            <div style={{ padding: '20px 24px', borderBottom: '1px solid #e2e8f0', display: 'flex', gap: '40px', background: '#f8fafc' }}>
              <div style={{ flex: 1 }}>
                <label style={{ display: 'block', fontSize: '14px', fontWeight: 600, color: '#475569', marginBottom: '8px' }}>Choose Order List</label>
                <select
                  value={previewOrder}
                  onChange={(e) => loadOrderData(e.target.value)}
                  style={{ width: '100%', padding: '10px 12px', borderRadius: '6px', border: '1px solid #cbd5e1', fontSize: '14px', outline: 'none', color: '#1e293b', background: '#fff' }}
                >
                  <option value="sample_order">Dummy Data</option>
                  {listOrders.map((order: any) => (
                    <option key={order.id} value={order.id}>{order.title}</option>
                  ))}
                </select>
              </div>
              <div style={{ flex: 1 }}>
                <label style={{ display: 'block', fontSize: '14px', fontWeight: 600, color: '#475569', marginBottom: '8px' }}>Test Email</label>
                <div style={{ display: 'flex', gap: '10px' }}>
                  <input
                    type="email"
                    value={testEmail}
                    onChange={(e) => setTestEmail(e.target.value)}
                    placeholder="Email"
                    style={{ flex: 1, padding: '10px 12px', borderRadius: '6px', border: '1px solid #cbd5e1', fontSize: '14px', outline: 'none' }}
                  />
                  <button type="button" onClick={async () => {
                    if (!testEmail) {
                      showToast('Please enter an email address', 'error');
                      return;
                    }
                    showToast('Sending test email...', 'success');
                    try {
                      const restPath = (window as any).o100neData?.rest_path || {};
                      const url = `${restPath.root}${restPath.base}/templates/send-test-email`;
                      const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                          'Content-Type': 'application/json',
                          'X-WP-Nonce': restPath.nonce
                        },
                        body: JSON.stringify({ email: testEmail, html: previewHtml })
                      });
                      const data = await response.json();
                      if (data && data.success) {
                        showToast('Email sent successfully!', 'success');
                      } else {
                        showToast(data.message || 'Failed to send email', 'error');
                      }
                    } catch (err) {
                      console.error(err);
                      showToast('Error sending email', 'error');
                    }
                  }} style={{ background: '#fff', border: '1px solid #cbd5e1', color: '#475569', padding: '0 16px', borderRadius: '6px', cursor: 'pointer', fontWeight: 500, display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Send
                  </button>
                </div>
              </div>
            </div>

            {/* Modal Canvas */}
            <div style={{ flex: 1, backgroundColor: '#f1f5f9', padding: '24px', overflowY: 'auto', display: 'flex', justifyContent: 'center' }}>
              <iframe style={{ width: '100%', maxWidth: '600px', backgroundColor: '#fff', minHeight: '100%', boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)', border: '1px solid #e2e8f0' }} srcDoc={previewHtml}>
              </iframe>
            </div>

          </div>
        </div>
      )}
      {/* Template Library Import Modal */}
      {isLibraryModalOpen && (
        <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(15, 23, 42, 0.75)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div style={{ background: '#fff', width: '90%', maxWidth: '800px', height: '80vh', borderRadius: '12px', display: 'flex', flexDirection: 'column', overflow: 'hidden', boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)' }}>

            {/* Header */}
            <div style={{ padding: '20px 24px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc' }}>
              <h2 style={{ margin: 0, fontSize: '18px', color: '#1e293b', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <span className="dashicons dashicons-images-alt2" style={{ color: '#6A4BFF' }}></span>
                Template Library
              </h2>
              <button type="button" onClick={() => setIsLibraryModalOpen(false)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b' }}>
                <span className="dashicons dashicons-no-alt" style={{ fontSize: '20px' }}></span>
              </button>
            </div>

            {/* Body */}
            <div style={{ padding: '24px', flex: 1, overflowY: 'auto' }}>
              <div style={{ marginBottom: '20px', color: '#64748b', fontSize: '14px' }}>
                Select a template from your library to import into the editor. Warning: This will overwrite your current canvas.
              </div>

              {templateLibraryList.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '40px 0', color: '#94a3b8' }}>
                  <span className="dashicons dashicons-book" style={{ fontSize: '48px', width: '48px', height: '48px', marginBottom: '16px', opacity: 0.5 }}></span>
                  <p>Your template library is empty.<br />Use the "Export" button to save templates here.</p>
                </div>
              ) : (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '20px' }}>
                  {templateLibraryList.map((tpl, i) => (
                    <div key={i} style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', background: '#fff', display: 'flex', flexDirection: 'column', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
                      <div style={{ height: '140px', background: '#f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#94a3b8' }}>
                        <span className="dashicons dashicons-format-image" style={{ fontSize: '48px', width: '48px', height: '48px', opacity: 0.3 }}></span>
                      </div>
                      <div style={{ padding: '16px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                        <div style={{ fontWeight: 600, color: '#1e293b', fontSize: '14px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                          {tpl.name}
                        </div>
                        <div style={{ fontSize: '12px', color: '#94a3b8' }}>
                          Saved: {tpl.date ? tpl.date.split(' ')[0] : 'Unknown'}
                        </div>
                        <div style={{ display: 'flex', gap: '8px', marginTop: 'auto' }}>
                          <button type="button" onClick={() => loadTemplateFromLibrary(tpl.mjml)} style={{ flex: 1, background: '#6A4BFF', color: '#fff', border: 'none', padding: '6px 0', borderRadius: '4px', cursor: 'pointer', fontSize: '13px', fontWeight: 500 }}>
                            Import
                          </button>
                          <button type="button" onClick={() => setTemplateToDelete({ id: tpl.id, name: tpl.name })} style={{ background: '#fee2e2', color: '#ef4444', border: 'none', padding: '6px 10px', borderRadius: '4px', cursor: 'pointer' }} title="Delete Template">
                            <span className="dashicons dashicons-trash" style={{ fontSize: '14px', width: '14px', height: '14px', marginTop: '2px' }}></span>
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

          </div>
        </div>
      )}
      {/* Template Delete Confirmation Modal */}
      {templateToDelete && (
        <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(15, 23, 42, 0.75)', zIndex: 10000, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div style={{ background: '#fff', width: '90%', maxWidth: '400px', borderRadius: '12px', padding: '24px', boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)' }}>
            <h3 style={{ margin: '0 0 16px 0', fontSize: '18px', color: '#1e293b' }}>Delete Template</h3>
            <p style={{ margin: '0 0 24px 0', fontSize: '14px', color: '#64748b' }}>
              Are you sure you want to delete the template <strong>"{templateToDelete.name}"</strong>? This action cannot be undone.
            </p>
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px' }}>
              <button type="button" 
                onClick={() => setTemplateToDelete(null)}
                style={{ background: '#f1f5f9', color: '#475569', border: 'none', padding: '10px 16px', borderRadius: '6px', cursor: 'pointer', fontSize: '14px', fontWeight: 500 }}
              >
                Cancel
              </button>
              <button type="button" 
                onClick={executeDeleteTemplate}
                style={{ background: '#ef4444', color: '#fff', border: 'none', padding: '10px 16px', borderRadius: '6px', cursor: 'pointer', fontSize: '14px', fontWeight: 500 }}
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Template Export Modal */}
      {isExportModalOpen && (
        <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(15, 23, 42, 0.75)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div style={{ background: '#fff', width: '90%', maxWidth: '400px', borderRadius: '12px', display: 'flex', flexDirection: 'column', overflow: 'hidden', boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)' }}>

            {/* Header */}
            <div style={{ padding: '20px 24px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc' }}>
              <h2 style={{ margin: 0, fontSize: '18px', color: '#1e293b', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <span className="dashicons dashicons-upload" style={{ color: '#6A4BFF' }}></span>
                Export to Library
              </h2>
              <button type="button" onClick={() => setIsExportModalOpen(false)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b' }}>
                <span className="dashicons dashicons-no-alt" style={{ fontSize: '20px' }}></span>
              </button>
            </div>

            {/* Body */}
            <div style={{ padding: '24px' }}>
              <div style={{ marginBottom: '20px', color: '#64748b', fontSize: '14px' }}>
                Save this template to your library so you can import and reuse it in other emails.
              </div>

              <div style={{ marginBottom: '20px' }}>
                <label style={{ display: 'block', marginBottom: '8px', fontSize: '14px', fontWeight: 500, color: '#334155' }}>
                  Template Name <span style={{ color: '#ef4444' }}>*</span>
                </label>
                <input
                  type="text"
                  value={exportTemplateName}
                  onChange={(e) => setExportTemplateName(e.target.value)}
                  placeholder="e.g. Summer Sale Promo"
                  style={{ width: '100%', padding: '10px 12px', border: '1px solid #cbd5e1', borderRadius: '6px', fontSize: '14px', outline: 'none' }}
                  autoFocus
                />
              </div>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                <button type="button" onClick={() => setIsExportModalOpen(false)} style={{ background: '#f1f5f9', color: '#475569', border: 'none', padding: '8px 16px', borderRadius: '6px', cursor: 'pointer', fontWeight: 500 }}>
                  Cancel
                </button>
                <button type="button" onClick={saveToTemplateLibrary} style={{ background: '#6A4BFF', color: '#fff', border: 'none', padding: '8px 16px', borderRadius: '6px', cursor: 'pointer', fontWeight: 500 }}>
                  Save Template
                </button>
              </div>
            </div>

          </div>
        </div>
      )}
    </div>
  );
};

const Accordion: React.FC<{ title: string, children: React.ReactNode, defaultOpen?: boolean }> = ({ title, children, defaultOpen = true }) => {
  const [isOpen, setIsOpen] = useState(defaultOpen);
  return (
    <div className={`o100ne-category ${isOpen ? 'open' : ''}`}>
      <div className="o100ne-category-title" onClick={() => setIsOpen(!isOpen)}>
        {title}
      </div>
      {isOpen && (
        <div className="o100ne-category-content">
          {children}
        </div>
      )}
    </div>
  );
};

const GlobalSettings: React.FC<{ editor: GjsEditor }> = ({ editor }) => {
  const [bgColor, setBgColor] = useState('#f4f4f4');
  const [contentWidth, setContentWidth] = useState('600px');

  // Text Appearance State
  const [textFont, setTextFont] = useState('Helvetica');
  const [textSize, setTextSize] = useState('13');
  const [textColor, setTextColor] = useState('#000000');
  const [textAlign, setTextAlign] = useState('left');

  // Button State
  const [btnFont, setBtnFont] = useState('Helvetica');
  const [btnSize, setBtnSize] = useState('13');
  const [btnColor, setBtnColor] = useState('#ffffff');
  const [btnBgColor, setBtnBgColor] = useState('#000000');

  useEffect(() => {
    setTimeout(() => {
      const body = editor.getWrapper()?.findType('mj-body')[0];
      if (body) {
        setBgColor(body.getAttributes()['background-color'] || '#f4f4f4');
        setContentWidth(body.getAttributes()['width'] || '600px');
      }
    }, 500);
  }, [editor]);

  const updateBodyAttr = (key: string, val: string) => {
    const body = editor.getWrapper()?.findType('mj-body')[0];
    if (body) {
      const attrs = body.getAttributes();
      body.setAttributes({ ...attrs, [key]: val });
    }
  };

  const applyGlobalStyle = (type: string, styles: any) => {
    const comps = editor.getWrapper()?.findType(type) || [];
    comps.forEach(c => {
      const current = c.getStyle();
      c.setStyle({ ...current, ...styles });
    });
  };

  const handleTextChange = (key: string, val: string) => {
    if (key === 'font-family') setTextFont(val);
    if (key === 'font-size') setTextSize(val);
    if (key === 'color') setTextColor(val);
    if (key === 'text-align') setTextAlign(val);
    applyGlobalStyle('mj-text', { [key]: key === 'font-size' ? `${val}px` : val });
  };

  const handleBtnChange = (key: string, val: string) => {
    if (key === 'font-family') setBtnFont(val);
    if (key === 'font-size') setBtnSize(val);
    if (key === 'color') setBtnColor(val);
    if (key === 'background-color') setBtnBgColor(val);
    applyGlobalStyle('mj-button', { [key]: key === 'font-size' ? `${val}px` : val });
  };

  return (
    <div style={{ padding: '0' }}>

      <Accordion title="General" defaultOpen={true}>
        <div style={{ marginBottom: '20px' }}>
          <label style={{ display: 'block', fontSize: '13px', fontWeight: 600, color: '#475569', marginBottom: '8px' }}>Background Color</label>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <input type="color" value={bgColor} onChange={e => { setBgColor(e.target.value); updateBodyAttr('background-color', e.target.value); }} style={{ width: '40px', height: '40px', padding: '0', border: '1px solid #cbd5e1', borderRadius: '4px', cursor: 'pointer' }} />
            <input type="text" value={bgColor} onChange={e => { setBgColor(e.target.value); updateBodyAttr('background-color', e.target.value); }} style={{ flex: 1, padding: '8px 12px', border: '1px solid #cbd5e1', borderRadius: '4px', color: '#334155', fontSize: '13px' }} />
          </div>
        </div>

        <div>
          <label style={{ display: 'block', fontSize: '13px', fontWeight: 600, color: '#475569', marginBottom: '8px' }}>Content Width</label>
          <input type="text" value={contentWidth} onChange={e => { setContentWidth(e.target.value); updateBodyAttr('width', e.target.value); }} placeholder="e.g. 600px" style={{ width: '100%', padding: '8px 12px', border: '1px solid #cbd5e1', borderRadius: '4px', color: '#334155', fontSize: '13px', boxSizing: 'border-box' }} />
        </div>
      </Accordion>

      <Accordion title="Text Appearance" defaultOpen={true}>
        <div style={{ marginBottom: '15px' }}>
          <label style={{ display: 'block', fontSize: '12px', color: '#475569', marginBottom: '4px' }}>Font Family</label>
          <select value={textFont} onChange={e => handleTextChange('font-family', e.target.value)} style={{ width: '100%', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px' }}>
            <option value="Helvetica">Helvetica</option>
            <option value="Arial">Arial</option>
            <option value="Times New Roman">Times New Roman</option>
            <option value="Verdana">Verdana</option>
          </select>
        </div>

        <div style={{ display: 'flex', gap: '15px', marginBottom: '15px' }}>
          <div style={{ flex: 1 }}>
            <label style={{ display: 'block', fontSize: '12px', color: '#475569', marginBottom: '4px' }}>Font Size</label>
            <div style={{ display: 'flex', border: '1px solid #cbd5e1', borderRadius: '4px', overflow: 'hidden' }}>
              <input type="number" value={textSize} onChange={e => handleTextChange('font-size', e.target.value)} style={{ width: '100%', border: 'none', padding: '6px', textAlign: 'center' }} />
              <span style={{ padding: '6px', background: '#f8fafc', color: '#64748b', fontSize: '12px', borderLeft: '1px solid #cbd5e1' }}>px</span>
            </div>
          </div>
          <div style={{ flex: 1 }}>
            <label style={{ display: 'block', fontSize: '12px', color: '#475569', marginBottom: '4px' }}>Font Color</label>
            <input type="color" value={textColor} onChange={e => handleTextChange('color', e.target.value)} style={{ width: '100%', height: '30px', border: '1px solid #cbd5e1', borderRadius: '4px', padding: 0 }} />
          </div>
        </div>

        <div>
          <label style={{ display: 'block', fontSize: '12px', color: '#475569', marginBottom: '4px' }}>Alignment</label>
          <div style={{ display: 'flex', border: '1px solid #cbd5e1', borderRadius: '4px', overflow: 'hidden' }}>
            <button type="button" onClick={() => handleTextChange('text-align', 'left')} style={{ flex: 1, padding: '6px', border: 'none', background: textAlign === 'left' ? '#e2e8f0' : '#fff', cursor: 'pointer' }}><span className="dashicons dashicons-editor-alignleft" style={{ fontSize: '16px', lineHeight: '20px' }}></span></button>
            <button type="button" onClick={() => handleTextChange('text-align', 'center')} style={{ flex: 1, padding: '6px', border: 'none', background: textAlign === 'center' ? '#e2e8f0' : '#fff', cursor: 'pointer', borderLeft: '1px solid #cbd5e1' }}><span className="dashicons dashicons-editor-aligncenter" style={{ fontSize: '16px', lineHeight: '20px' }}></span></button>
            <button type="button" onClick={() => handleTextChange('text-align', 'right')} style={{ flex: 1, padding: '6px', border: 'none', background: textAlign === 'right' ? '#e2e8f0' : '#fff', cursor: 'pointer', borderLeft: '1px solid #cbd5e1' }}><span className="dashicons dashicons-editor-alignright" style={{ fontSize: '16px', lineHeight: '20px' }}></span></button>
          </div>
        </div>
      </Accordion>

      <Accordion title="Button" defaultOpen={true}>
        <div style={{ marginBottom: '15px' }}>
          <label style={{ display: 'block', fontSize: '12px', color: '#475569', marginBottom: '4px' }}>Font Family</label>
          <select value={btnFont} onChange={e => handleBtnChange('font-family', e.target.value)} style={{ width: '100%', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px' }}>
            <option value="Helvetica">Helvetica</option>
            <option value="Arial">Arial</option>
            <option value="Times New Roman">Times New Roman</option>
            <option value="Verdana">Verdana</option>
          </select>
        </div>

        <div style={{ display: 'flex', gap: '15px', marginBottom: '15px' }}>
          <div style={{ flex: 1 }}>
            <label style={{ display: 'block', fontSize: '12px', color: '#475569', marginBottom: '4px' }}>Font Size</label>
            <div style={{ display: 'flex', border: '1px solid #cbd5e1', borderRadius: '4px', overflow: 'hidden' }}>
              <input type="number" value={btnSize} onChange={e => handleBtnChange('font-size', e.target.value)} style={{ width: '100%', border: 'none', padding: '6px', textAlign: 'center' }} />
              <span style={{ padding: '6px', background: '#f8fafc', color: '#64748b', fontSize: '12px', borderLeft: '1px solid #cbd5e1' }}>px</span>
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', gap: '15px' }}>
          <div style={{ flex: 1 }}>
            <label style={{ display: 'block', fontSize: '12px', color: '#475569', marginBottom: '4px' }}>Text Color</label>
            <input type="color" value={btnColor} onChange={e => handleBtnChange('color', e.target.value)} style={{ width: '100%', height: '30px', border: '1px solid #cbd5e1', borderRadius: '4px', padding: 0 }} />
          </div>
          <div style={{ flex: 1 }}>
            <label style={{ display: 'block', fontSize: '12px', color: '#475569', marginBottom: '4px' }}>Background</label>
            <input type="color" value={btnBgColor} onChange={e => handleBtnChange('background-color', e.target.value)} style={{ width: '100%', height: '30px', border: '1px solid #cbd5e1', borderRadius: '4px', padding: 0 }} />
          </div>
        </div>
      </Accordion>
    </div>
  );
};

const topBtnStyle = {
  background: 'transparent',
  border: 'none',
  cursor: 'pointer',
  padding: '6px',
  color: '#475569',
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'center',
  borderRadius: '4px'
};

export default Editor;
