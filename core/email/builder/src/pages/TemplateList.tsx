import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

const getRestConfig = () => {
  const data = (window as any).o100neData?.rest_path || {};
  return { root: data.root || '/wp-json/', nonce: data.nonce || '' };
};

interface TemplateItem {
  id: number;
  name: string;
  title: string;
  type: string;
  status: string;
  elements_type?: string;
  support_info?: { status: string };
}

const TemplateList: React.FC = () => {
  const [templates, setTemplates] = useState<TemplateItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const { root, nonce } = getRestConfig();
    fetch(`${root}o100ne/v1/templates`, {
      headers: nonce ? { 'X-WP-Nonce': nonce } : {},
    })
      .then((res) => res.json())
      .then((data: TemplateItem[]) => {
        // Sort: active first, then by name
        const sorted = data.sort((a, b) => {
          if (a.status === 'active' && b.status !== 'active') return -1;
          if (a.status !== 'active' && b.status === 'active') return 1;
          return (a.title || a.name || '').localeCompare(b.title || b.name || '');
        });
        setTemplates(sorted);
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, []);

  const getStatusBadge = (tpl: TemplateItem) => {
    const isEditable = !tpl.support_info || tpl.support_info.status === 'already_supported';
    if (!isEditable) {
      return <span style={{ color: '#94a3b8', fontSize: '13px' }}>Not supported</span>;
    }
    if (tpl.status === 'active') {
      return <span style={{ color: '#16a34a', fontWeight: 500 }}>Active</span>;
    }
    return <span style={{ color: '#94a3b8' }}>Inactive</span>;
  };

  const getTypeBadge = (tpl: TemplateItem) => {
    const label = tpl.elements_type === 'mjml' ? 'MJML' : tpl.type || 'WooCommerce';
    const bg = tpl.elements_type === 'mjml' ? '#0ea5e9' : '#6A4BFF';
    return (
      <span style={{ background: bg, color: '#fff', padding: '4px 10px', borderRadius: '12px', fontSize: '12px', fontWeight: 500 }}>
        {label}
      </span>
    );
  };

  if (loading) {
    return (
      <div style={{ padding: '40px', textAlign: 'center', color: '#64748b' }}>
        Loading templates…
      </div>
    );
  }

  return (
    <div style={{ padding: '20px', fontFamily: 'sans-serif' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2 style={{ fontSize: '24px', margin: 0 }}>Email Templates</h2>
        <button style={{ padding: '8px 16px', background: '#6A4BFF', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer' }}>
          Settings
        </button>
      </div>

      <div style={{ background: '#fff', border: '1px solid #E2E8F0', borderRadius: '8px', overflow: 'hidden' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
          <thead>
            <tr style={{ background: '#F8FAFC', borderBottom: '1px solid #E2E8F0' }}>
              <th style={{ padding: '16px', fontWeight: 600, color: '#475569' }}>Template Name</th>
              <th style={{ padding: '16px', fontWeight: 600, color: '#475569' }}>Type</th>
              <th style={{ padding: '16px', fontWeight: 600, color: '#475569' }}>Status</th>
              <th style={{ padding: '16px', fontWeight: 600, color: '#475569' }}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {templates.map((tpl) => {
              const isEditable = !tpl.support_info || tpl.support_info.status === 'already_supported';
              return (
                <tr key={tpl.name || tpl.id} style={{ borderBottom: '1px solid #E2E8F0' }}>
                  <td style={{ padding: '16px', fontWeight: 500 }}>
                    {isEditable ? (
                      <Link to={`/editor/${tpl.name}`} style={{ color: '#6A4BFF', textDecoration: 'none' }}>
                        {tpl.title || tpl.name}
                      </Link>
                    ) : (
                      <span style={{ color: '#94a3b8' }}>{tpl.title || tpl.name}</span>
                    )}
                  </td>
                  <td style={{ padding: '16px' }}>{getTypeBadge(tpl)}</td>
                  <td style={{ padding: '16px' }}>{getStatusBadge(tpl)}</td>
                  <td style={{ padding: '16px' }}>
                    {isEditable ? (
                      <Link
                        to={`/editor/${tpl.name}`}
                        style={{ padding: '6px 12px', border: '1px solid #E2E8F0', borderRadius: '4px', textDecoration: 'none', color: '#475569', fontSize: '14px' }}
                      >
                        Edit
                      </Link>
                    ) : (
                      <span style={{ color: '#cbd5e1', fontSize: '14px' }}>—</span>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default TemplateList;
