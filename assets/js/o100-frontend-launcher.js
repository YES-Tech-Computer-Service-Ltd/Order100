/**
 * Order100 Native Frontend Launcher
 */
document.addEventListener('DOMContentLoaded', function() {
    var container = document.getElementById('wll-site-launcher');

    var state = {
        config: null,
        isOpen: false,
        activePanel: 'main' // main, earn, redeem
    };

    // Expose for backend preview
    window.O100FrontendLauncher = {
        init: function() {
            container = document.getElementById('wll-site-launcher');
            if (typeof wll_localize_data !== 'undefined' && wll_localize_data.ajax_url && container) {
                fetchConfig();
            }
        },
        render: function(customConfig) {
            container = document.getElementById('wll-site-launcher');
            if (!container) return;
            if (customConfig) state.config = customConfig;
            if (state.config) render();
        },
        setState: function(key, value) {
            state[key] = value;
        },
        getState: function() {
            return state;
        }
    };

    // Initialize frontend natively
    window.O100FrontendLauncher.init();

    function fetchConfig() {
        var formData = new FormData();
        formData.append('action', 'wll_get_launcher_popup_details');
        
        fetch(wll_localize_data.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                state.config = data.data;
                render();
            }
        })
        .catch(err => console.error('O100 Launcher init error:', err));
    }

    function getIconClass(type) {
        var map = {
            'gift': 'dashicons-pressthis',
            'star': 'dashicons-star-filled',
            'trophy': 'dashicons-awards',
            'medal': 'dashicons-saved',
            'crown': 'dashicons-superhero',
            'heart': 'dashicons-heart',
            'custom': 'dashicons-format-image'
        };
        return map[type] || 'dashicons-star-filled';
    }

    function render() {
        var cfg = state.config;
        var design = cfg.design || {};
        var content = cfg.content || {};
        var isMember = cfg.is_member || false;
        
        var themeColor = design.colors?.theme?.primary || '#4F47EB';
        var triggerBg = design.colors?.launcher?.background || '#4F47EB';
        var triggerText = design.colors?.launcher?.text || '#FFFFFF';
        
        var triggerSettings = cfg.launcher || cfg.icon?.launcher || {};
        var btnStyle = triggerSettings.appearance?.selected || 'icon_with_text';
        var btnIconType = triggerSettings.appearance?.icon?.icon || 'star';
        var btnLabel = triggerSettings.appearance?.text || 'Rewards';
        var position = triggerSettings.placement?.position || 'right';

        // Visibility Check
        var viewOption = triggerSettings.placement?.view_option || 'mobile_and_desktop';
        var isMobile = window.innerWidth <= 768;
        if (viewOption === 'do_not_show') return;
        if (viewOption === 'mobile_only' && !isMobile) return;
        if (viewOption === 'desktop_only' && isMobile) return;

        container.className = position === 'right' ? 'o100-pos-right' : 'o100-pos-left';
        
        var cData = isMember ? content.member : content.guest;
        
        var welcomeTitle = isMember ? cData?.banner?.texts?.welcome : cData?.welcome?.texts?.title;
        if (!welcomeTitle) welcomeTitle = isMember ? 'Welcome back, {user_name}!' : 'Welcome';

        // Parse placeholder
        var userName = (typeof wll_localize_data !== 'undefined' && wll_localize_data.user_name) ? wll_localize_data.user_name : 'Guest';
        welcomeTitle = welcomeTitle.replace('{user_name}', userName).replace('{user_email}', '');

        var welcomeDesc = isMember ? '' : cData?.welcome?.texts?.description;
        if (typeof welcomeDesc !== 'string' && !isMember) {
            welcomeDesc = 'Sign up to earn points';
        }

        var earnTitle = cData?.points?.earn?.title || 'Earn Points';
        var redeemTitle = cData?.points?.redeem?.title || 'Redeem Points';

        var logoDisplay = design.logo?.is_show === 'show' && design.logo?.image ? 'flex' : 'none';
        var logoImgSrc = design.logo?.image || '';

        // Trigger Button HTML
        var triggerHtml = '';
        var iconClass = getIconClass(btnIconType);
        
        var tStyle = `background-color: ${triggerBg}; color: ${triggerText};`;
        if (btnStyle === 'icon_only') {
            tStyle += ' width: 60px; height: 60px; border-radius: 50%; padding: 0;';
            triggerHtml = `<span class="dashicons ${iconClass} o100-launcher-trigger-icon"></span>`;
        } else if (btnStyle === 'text_only') {
            tStyle += ' height: 48px; border-radius: 30px; padding: 0 20px;';
            triggerHtml = `<span class="o100-launcher-trigger-text">${btnLabel}</span>`;
        } else {
            tStyle += ' height: 48px; border-radius: 30px; padding: 0 20px 0 15px;';
            triggerHtml = `
                <span class="dashicons ${iconClass} o100-launcher-trigger-icon" style="font-size:20px; width:20px; height:20px; margin-right:8px;"></span>
                <span class="o100-launcher-trigger-text">${btnLabel}</span>
            `;
        }

        // Main Panel HTML
        var footerHtml = '';
        var referralHtml = '';

        if (!isMember) {
            var btnText = content.guest?.welcome?.button?.text || 'Join Now';
            var btnUrl = content.guest?.welcome?.button?.url || '#';
            var signinText = content.guest?.welcome?.texts?.sign_in || 'Sign in';
            var signinUrl = content.guest?.welcome?.texts?.sign_in_url || '#';
            var haveAccountText = content.guest?.welcome?.texts?.have_account || 'Already have an account?';
            
            footerHtml = `
                <div class="o100-launcher-footer">
                    <a href="${btnUrl}" class="o100-launcher-btn" style="background-color: ${themeColor}; display:block; text-align:center; text-decoration:none;">${btnText}</a>
                    <div style="margin-top:15px; font-size:13px; color:#64748b; text-align:center;">
                        ${haveAccountText} <a href="${signinUrl}" style="color:${themeColor}; text-decoration:none; font-weight:600;">${signinText}</a>
                    </div>
                </div>
            `;
        } else {
            var pts = cfg.available_point || 0;
            var ptsLabel = content.member?.banner?.texts?.points_label || 'Points';
            footerHtml = `
                <div class="o100-launcher-footer" style="background: linear-gradient(135deg, ${themeColor}15 0%, ${themeColor}05 100%); padding: 18px 20px; border-top: 1px solid #f1f5f9; text-align: center;">
                    <div style="font-size: 30px; font-weight: 800; color: ${themeColor}; line-height: 1;">${pts}</div>
                    <div style="font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 6px;">${ptsLabel}</div>
                </div>
            `;

            if (cData?.referrals?.is_referral_action_available) {
                var refUrl = cData?.referrals?.referral_url || '';
                var refTitle = cData?.referrals?.title || 'Refer and earn';
                var refDesc = cData?.referrals?.description || 'Refer your friends and earn rewards. Your friend can get a reward as well!';
                
                var socialsHtml = '';
                var socialList = cData?.referrals?.social_share_list || [];
                if (socialList.length > 0) {
                    var iconsHtml = socialList.map(s => {
                        var actionStr = s.action_type ? s.action_type.replace('_share', '') : '';
                        var dashiconMap = {
                            'facebook': 'dashicons-facebook-alt',
                            'whatsapp': 'dashicons-whatsapp',
                            'email': 'dashicons-email-alt',
                            'linkedin': 'dashicons-linkedin',
                            'telegram': 'dashicons-testimonial'
                        };
                        
                        if (actionStr === 'twitter') {
                            return `<a href="${s.url}" target="_blank" style="color:${themeColor}; text-decoration:none; display:inline-flex; align-items:center;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </a>`;
                        } else {
                            var iconClass = dashiconMap[actionStr] || 'dashicons-share';
                            return `<a href="${s.url}" target="_blank" style="color:${themeColor}; text-decoration:none;"><span class="dashicons ${iconClass}"></span></a>`;
                        }
                    }).join('');
                    socialsHtml = `<div style="display:flex; justify-content:center; gap:12px; margin-top:12px;">${iconsHtml}</div>`;
                }

                referralHtml = `
                    <div class="o100-launcher-referral-card" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:15px; margin-top:15px;">
                        <div style="font-weight:600; font-size:14px; margin-bottom:5px; color:#1e293b;">${refTitle}</div>
                        <div style="color:#64748b; font-size:12px; margin-bottom:12px;">${refDesc}</div>
                        <div style="display:flex; border:1px solid #cbd5e1; border-radius:6px; overflow:hidden; background:#fff;">
                            <input type="text" value="${refUrl}" readonly class="o100-launcher-ref-input" style="flex:1; border:none; padding:8px 10px; font-size:12px; color:#475569; outline:none; background:transparent; min-width: 0;">
                            <div class="o100-launcher-ref-copy" style="background:${themeColor}; color:#fff; width:36px; flex-shrink:0; display:flex; align-items:center; justify-content:center; cursor:pointer;" title="Copy Link">
                                <span class="dashicons dashicons-admin-page" style="font-size:16px; width:16px; height:16px;"></span>
                            </div>
                        </div>
                        ${socialsHtml}
                    </div>
                `;
            }
        }

        var html = `
            <!-- Panel -->
            <div class="o100-launcher-panel ${state.isOpen ? 'is-open' : ''}">
                <div class="o100-launcher-header" style="background-color: ${themeColor};">
                    <div class="o100-launcher-close">✕</div>
                    <div class="o100-launcher-logo" style="display: ${logoDisplay};">
                        <img src="${logoImgSrc}" alt="Logo">
                    </div>
                    <h3 class="o100-launcher-title">${welcomeTitle}</h3>
                    <p class="o100-launcher-desc">${welcomeDesc}</p>
                </div>
                <div class="o100-launcher-body">
                    <div class="o100-launcher-card" data-action="earn">
                        <div class="o100-launcher-card-icon" style="color: ${themeColor};"><span class="dashicons dashicons-money-alt"></span></div>
                        <div class="o100-launcher-card-content">
                            <div class="o100-launcher-card-title">${earnTitle}</div>
                            <div class="o100-launcher-card-desc">Complete tasks to earn points</div>
                        </div>
                        <div class="o100-launcher-card-arrow">›</div>
                    </div>
                    <div class="o100-launcher-card" data-action="redeem">
                        <div class="o100-launcher-card-icon" style="color: ${themeColor};"><span class="dashicons dashicons-tickets-alt"></span></div>
                        <div class="o100-launcher-card-content">
                            <div class="o100-launcher-card-title">${redeemTitle}</div>
                            <div class="o100-launcher-card-desc">Use points for discounts</div>
                        </div>
                        <div class="o100-launcher-card-arrow">›</div>
                    </div>
                    ${referralHtml}
                </div>
                ${footerHtml}

                <!-- Secondary Panel -->
                <div class="o100-launcher-secondary-panel">
                    <div class="o100-launcher-header" style="background-color: ${themeColor}; padding: 20px; display:flex; align-items:center; justify-content:space-between;">
                        <div class="o100-launcher-back" id="o100-sec-back">←</div>
                        <h3 class="o100-launcher-title" style="margin:0; font-size:16px; flex:1;" id="o100-sec-title">Title</h3>
                        <div class="o100-launcher-close">✕</div>
                    </div>
                    <div class="o100-launcher-body" id="o100-sec-body">
                        <!-- Dynamic Content -->
                    </div>
                </div>

                <!-- Tertiary Panel -->
                <div class="o100-launcher-tertiary-panel">
                    <div class="o100-launcher-header" style="background-color: ${themeColor}; padding: 20px; display:flex; align-items:center; justify-content:space-between;">
                        <div class="o100-launcher-back" id="o100-ter-back">←</div>
                        <h3 class="o100-launcher-title" style="margin:0; font-size:16px; flex:1;" id="o100-ter-title">Detail</h3>
                        <div class="o100-launcher-close">✕</div>
                    </div>
                    <div class="o100-launcher-body" id="o100-ter-body" style="background: #f8fafc; height:100%;">
                        <!-- Detailed Content -->
                    </div>
                </div>
            </div>

            <!-- Trigger -->
            <div class="o100-launcher-trigger" style="${tStyle}">
                ${triggerHtml}
            </div>
        `;

        container.innerHTML = html;
        bindEvents();
    }

    function bindEvents() {
        var trigger = container.querySelector('.o100-launcher-trigger');
        var panel = container.querySelector('.o100-launcher-panel');
        var closes = container.querySelectorAll('.o100-launcher-close');
        var secBackBtn = container.querySelector('#o100-sec-back');
        var terBackBtn = container.querySelector('#o100-ter-back');
        
        var secPanel = container.querySelector('.o100-launcher-secondary-panel');
        var terPanel = container.querySelector('.o100-launcher-tertiary-panel');
        
        var earnBtn = container.querySelector('.o100-launcher-card[data-action="earn"]');
        var redeemBtn = container.querySelector('.o100-launcher-card[data-action="redeem"]');

        trigger.addEventListener('click', function() {
            state.isOpen = !state.isOpen;
            if (state.isOpen) {
                panel.classList.add('is-open');
                trigger.style.display = 'none';
            }
        });

        closes.forEach(c => {
            c.addEventListener('click', function() {
                state.isOpen = false;
                panel.classList.remove('is-open');
                trigger.style.display = 'flex';
                secPanel.classList.remove('is-active');
                terPanel.classList.remove('is-active');
            });
        });

        secBackBtn.addEventListener('click', function() {
            secPanel.classList.remove('is-active');
        });
        
        terBackBtn.addEventListener('click', function() {
            terPanel.classList.remove('is-active');
        });

        if (earnBtn) {
            earnBtn.addEventListener('click', function() {
                openSecondary('Earn Points', 'wll_get_guest_earn_points', state.config.is_member ? 'wll_get_member_earn_points' : 'wll_get_guest_earn_points');
            });
        }

        if (redeemBtn) {
            redeemBtn.addEventListener('click', function() {
                openSecondary('Redeem Points', 'wll_get_guest_redeem_rewards', state.config.is_member ? 'wll_get_member_redeem_rewards' : 'wll_get_guest_redeem_rewards');
            });
        }

        var copyBtn = container.querySelector('.o100-launcher-ref-copy');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                var input = container.querySelector('.o100-launcher-ref-input');
                if (input) {
                    input.select();
                    document.execCommand('copy');
                    
                    var oldColor = this.style.backgroundColor;
                    this.style.backgroundColor = '#10b981'; // Green for success
                    this.innerHTML = '<span class="dashicons dashicons-yes" style="font-size:16px; width:16px; height:16px;"></span>';
                    
                    var that = this;
                    setTimeout(function() {
                        that.style.backgroundColor = oldColor;
                        that.innerHTML = '<span class="dashicons dashicons-admin-page" style="font-size:16px; width:16px; height:16px;"></span>';
                    }, 2000);
                }
            });
        }
    }

    function fetchAction(action, callback) {
        if (window.O100FrontendLauncher && window.O100FrontendLauncher.mockData && window.O100FrontendLauncher.mockData[action]) {
            // Use mock data for backend preview
            callback(window.O100FrontendLauncher.mockData[action]);
            return;
        }

        var formData = new FormData();
        formData.append('action', action);
        if (state.config.nonces && state.config.nonces.render_page_nonce) {
            formData.append('wll_nonce', state.config.nonces.render_page_nonce);
        }

        if (typeof wll_localize_data === 'undefined' || !wll_localize_data.ajax_url) {
            callback(null);
            return;
        }

        fetch(wll_localize_data.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                var items = data.data;
                if (items.earn_points) items = items.earn_points;
                else if (items.redeem_data) items = items.redeem_data;
                else if (items.redeem_coupons) items = items.redeem_coupons;
                else if (items.reward_opportunity) items = items.reward_opportunity;
                
                callback(items);
            } else {
                callback(null);
            }
        })
        .catch(err => {
            callback(null);
        });
    }

    function openSecondary(title, actionGuest, actionMember) {
        var secPanel = container.querySelector('.o100-launcher-secondary-panel');
        var titleEl = document.getElementById('o100-sec-title');
        var bodyEl = document.getElementById('o100-sec-body');
        
        // Remove padding if it's Redeem Points to allow full-width tabs
        if (title === 'Redeem Points' || title === 'Redeem') {
            titleEl.innerText = 'Redeem';
            bodyEl.style.padding = '0';
            secPanel.classList.add('is-active');
            renderRedeemTabs(bodyEl);
            return;
        }

        titleEl.innerText = title;
        bodyEl.style.padding = '20px';
        secPanel.classList.add('is-active');
        
        bodyEl.innerHTML = '<div class="o100-loader" style="padding:20px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg></div>';

        var action = state.config.is_member ? actionMember : actionGuest;
        fetchAction(action, function(items) {
            renderSecondaryItems(bodyEl, items, title);
        });
    }

    function renderRedeemTabs(container) {
        var themeColor = state.config.design?.colors?.theme?.primary || '#4F47EB';
        var isMember = state.config.is_member;

        var html = `
            <div style="display:flex; background:#fff; border-bottom:1px solid #e2e8f0;">
                <div class="o100-tab-opp" style="flex:1; text-align:center; padding:15px 10px; cursor:pointer; font-weight:600; color:${themeColor}; border-bottom:2px solid ${themeColor}; font-size:14px; transition:all 0.2s;">Rewards Opportunities</div>
                <div class="o100-tab-my" style="flex:1; text-align:center; padding:15px 10px; cursor:pointer; font-weight:600; color:#64748b; border-bottom:2px solid transparent; font-size:14px; transition:all 0.2s;">My Rewards</div>
            </div>
            <div id="o100-redeem-content" style="padding:20px; background:#F5F5F5; flex:1; overflow-y:auto;"></div>
        `;
        container.innerHTML = html;

        var tabOpp = container.querySelector('.o100-tab-opp');
        var tabMy = container.querySelector('.o100-tab-my');
        var contentEl = container.querySelector('#o100-redeem-content');

        tabOpp.addEventListener('click', function() {
            tabOpp.style.color = themeColor;
            tabOpp.style.borderBottomColor = themeColor;
            tabMy.style.color = '#64748b';
            tabMy.style.borderBottomColor = 'transparent';
            loadRedeemTab('opportunities', contentEl, themeColor, isMember);
        });

        tabMy.addEventListener('click', function() {
            tabMy.style.color = themeColor;
            tabMy.style.borderBottomColor = themeColor;
            tabOpp.style.color = '#64748b';
            tabOpp.style.borderBottomColor = 'transparent';
            loadRedeemTab('my_rewards', contentEl, themeColor, isMember);
        });

        // Load initial
        loadRedeemTab('opportunities', contentEl, themeColor, isMember);
    }

    function loadRedeemTab(tabName, container, themeColor, isMember) {
        container.innerHTML = '<div class="o100-loader" style="padding:20px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg></div>';

        if (tabName === 'opportunities') {
            var action = isMember ? 'wll_get_reward_opportunity_rewards' : 'wll_get_guest_redeem_rewards';
            fetchAction(action, function(items) {
                renderSecondaryItems(container, items, 'Rewards Opportunities');
            });
        } else if (tabName === 'my_rewards') {
            if (!isMember) {
                container.innerHTML = `
                    <div style="padding:40px 20px; text-align:center;">
                        <div style="font-size:40px; color:#cbd5e1; margin-bottom:15px;"><span class="dashicons dashicons-lock" style="font-size:40px; width:40px; height:40px;"></span></div>
                        <div style="color:#64748b; margin-bottom:20px; font-size:14px;">Please login to view your rewards.</div>
                        <a href="${wll_localize_data.login_url || '#'}" style="display:inline-block; padding:10px 24px; background:${themeColor}; color:#fff; text-decoration:none; border-radius:6px; font-weight:600; font-size:14px;">Sign In</a>
                    </div>
                `;
                return;
            }

            var html = `
                <div style="display:flex; margin-bottom:15px; border-radius:8px; background:#e2e8f0; padding:4px;">
                    <div class="o100-subtab-rewards" style="flex:1; text-align:center; padding:8px; background:#fff; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px; color:#1e293b; box-shadow:0 1px 3px rgba(0,0,0,0.1); transition:all 0.2s;">Rewards</div>
                    <div class="o100-subtab-coupons" style="flex:1; text-align:center; padding:8px; cursor:pointer; font-weight:600; font-size:13px; color:#64748b; background:transparent; box-shadow:none; transition:all 0.2s;">Coupons</div>
                </div>
                <div id="o100-my-rewards-content"></div>
            `;
            container.innerHTML = html;

            var subRewards = container.querySelector('.o100-subtab-rewards');
            var subCoupons = container.querySelector('.o100-subtab-coupons');
            var subContent = container.querySelector('#o100-my-rewards-content');

            subRewards.addEventListener('click', function() {
                subRewards.style.background = '#fff';
                subRewards.style.color = '#1e293b';
                subRewards.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
                
                subCoupons.style.background = 'transparent';
                subCoupons.style.color = '#64748b';
                subCoupons.style.boxShadow = 'none';
                
                loadSubTab('rewards', subContent);
            });

            subCoupons.addEventListener('click', function() {
                subCoupons.style.background = '#fff';
                subCoupons.style.color = '#1e293b';
                subCoupons.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
                
                subRewards.style.background = 'transparent';
                subRewards.style.color = '#64748b';
                subRewards.style.boxShadow = 'none';
                
                loadSubTab('coupons', subContent);
            });

            loadSubTab('rewards', subContent);
        }
    }

    function loadSubTab(tabName, container) {
        container.innerHTML = '<div class="o100-loader" style="padding:20px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg></div>';
        if (tabName === 'rewards') {
            fetchAction('wll_get_member_redeem_rewards', function(items) {
                renderSecondaryItems(container, items, 'Rewards');
            });
        } else if (tabName === 'coupons') {
            fetchAction('wll_get_member_redeem_coupons', function(items) {
                renderSecondaryItems(container, items, 'Coupons');
            });
        }
    }

    function renderSecondaryItems(container, items, type) {
        if (!items || items.length === 0) {
            var emptyText = type === 'Coupons' ? 'No coupons found!' : 'No items found.';
            var emptyIcon = type === 'Coupons' ? 'dashicons-tickets-alt' : 'dashicons-warning';
            container.innerHTML = `
                <div style="padding:40px 20px; text-align:center;">
                    <div style="font-size:40px; color:#cbd5e1; margin-bottom:15px;"><span class="dashicons ${emptyIcon}" style="font-size:40px; width:40px; height:40px;"></span></div>
                    <div style="color:#64748b; font-size:14px;">${emptyText}</div>
                </div>
            `;
            return;
        }

        var themeColor = state.config.design?.colors?.theme?.primary || '#4F47EB';
        
        container.innerHTML = ''; // clear

        items.forEach((item, index) => {
            var icon = 'dashicons-star-filled';
            if (type === 'Earn Points') {
                if (item.action_type === 'point_for_purchase') icon = 'dashicons-cart';
                else if (item.action_type === 'signup') icon = 'dashicons-id-alt';
                else if (item.action_type === 'referral') icon = 'dashicons-groups';
                else if (item.action_type === 'facebook_share') icon = 'dashicons-facebook-alt';
                else if (item.action_type === 'twitter_share') icon = 'dashicons-twitter';
                else if (item.action_type === 'birthday') icon = 'dashicons-buddicons-groups';
            } else if (type === 'Coupons') {
                icon = 'dashicons-tickets-alt';
            } else {
                icon = 'dashicons-products'; // Default for rewards
            }

            var titleText = item.title || item.name || 'Item';
            var subtitleText = '';
            
            if (type === 'Earn Points') {
                subtitleText = item.campaign_title_discount || item.sub_title || '';
            } else if (type === 'Coupons') {
                subtitleText = item.coupon_code || '';
            } else {
                subtitleText = item.cost ? item.cost + ' Points' : '';
            }

            var pts = item.points || '';
            var pointsHtml = pts ? `<div style="color:${themeColor}; font-weight:bold; font-size:14px; margin-top:4px;">${pts} Points</div>` : '';
            
            var actionTextHtml = '';
            if (item.action_text && type !== 'Earn Points') {
                 actionTextHtml = `<div style="color:${themeColor}; font-weight:bold; font-size:14px; margin-top:4px;">${item.action_text}</div>`;
            }

            var div = document.createElement('div');
            div.className = 'o100-launcher-card';
            div.style.cssText = 'background:#fff; border-radius:8px; padding:15px; margin-bottom:10px; display:flex; align-items:center; gap:15px; box-shadow:0 2px 5px rgba(0,0,0,0.02); cursor:pointer;';
            div.innerHTML = `
                <div style="color:${themeColor}; font-size:24px; width:40px; height:40px; background:#f8fafc; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                    <span class="dashicons ${icon}"></span>
                </div>
                <div style="flex:1;">
                    <div style="font-weight:600; font-size:15px; margin-bottom:2px; color:#1e293b;">${titleText}</div>
                    <div style="color:#64748b; font-size:13px;">${subtitleText}</div>
                    ${pointsHtml}
                    ${actionTextHtml}
                </div>
                <div class="o100-launcher-card-arrow">›</div>
            `;

            div.addEventListener('click', function() {
                openTertiary(item, type, themeColor, icon);
            });

            container.appendChild(div);
        });
    }

    function openTertiary(item, type, themeColor, icon) {
        var terPanel = container.querySelector('.o100-launcher-tertiary-panel');
        var titleEl = document.getElementById('o100-ter-title');
        var bodyEl = document.getElementById('o100-ter-body');
        
        var titleText = item.title || item.name || 'Detail';
        titleEl.innerText = titleText;
        terPanel.classList.add('is-active');

        var fullDesc = item.description || item.reward_details || '';
        if (!fullDesc && type === 'Earn Points') {
            fullDesc = item.campaign_title_discount || '';
        }

        var actionHtml = '';
        var actionText = item.button_text || '';
        
        // If it's a redeem item, maybe show "Redeem" button
        if (type !== 'Earn Points') {
            actionText = 'Redeem';
        }

        if (item.action_type === 'birthday') {
            var existingDate = item.birth_date || ''; // YYYY-MM-DD
            var allowEdit = state.config.is_edit_after_birth_day_date === 'yes';

            if (existingDate && !allowEdit) {
                // Already set and not allowed to edit
                actionHtml = `
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:15px; margin-top:15px; text-align:center;">
                        <div style="font-size:14px; color:#64748b; margin-bottom:5px;">Your Birthday</div>
                        <div style="font-size:18px; font-weight:600; color:#1e293b;">${existingDate}</div>
                        <div style="font-size:12px; color:#94a3b8; margin-top:10px;">We'll send you a surprise!</div>
                    </div>
                `;
            } else {
                actionText = item.update_text || 'Save';
                var dateInputHtml = `<input type="date" id="o100-birthday-input" value="${existingDate}" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-top:15px; font-size:14px;">`;
                
                actionHtml = `
                    ${dateInputHtml}
                    <button class="o100-launcher-btn o100-birthday-save" style="background:${themeColor}; width:100%; padding:14px; margin-top:15px; font-size:16px; border-radius:8px; border:none; color:#fff; cursor:pointer; font-weight:600;">
                        ${actionText}
                    </button>
                `;
            }
        } else if (actionText) {
            actionHtml = `
                <button class="o100-launcher-btn" style="background:${themeColor}; width:100%; padding:14px; margin-top:20px; font-size:16px; border-radius:8px; border:none; color:#fff; cursor:pointer; font-weight:600;">
                    ${actionText}
                </button>
            `;
        }

        bodyEl.innerHTML = `
            <div style="padding: 30px 20px; text-align:center;">
                <div style="color:${themeColor}; font-size:40px; width:70px; height:70px; background:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px auto; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
                    <span class="dashicons ${icon}" style="font-size:32px; width:32px; height:32px;"></span>
                </div>
                <h2 style="font-size:20px; font-weight:700; margin:0 0 10px 0; color:#1e293b;">${titleText}</h2>
                <div style="font-size:14px; color:#64748b; line-height:1.5;">${fullDesc}</div>
                ${actionHtml}
            </div>
        `;

        if (item.action_type === 'birthday') {
            var saveBtn = bodyEl.querySelector('.o100-birthday-save');
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    handleBirthdaySave(item);
                });
            }
        }
    }

    function handleBirthdaySave(item) {
        var dateInput = document.getElementById('o100-birthday-input');
        if (!dateInput || !dateInput.value) {
            showToast('Please select a valid date.', 'error');
            return;
        }
        var newDate = dateInput.value;

        if (!state.config.is_member) {
            // Guest logic
            document.cookie = "o100_pending_birthday=" + newDate + "; path=/; max-age=86400"; // 1 day
            showConfirm('You need to log in or register an account to save your birthday. Proceed to the login/registration page?', function() {
                window.location.href = wll_localize_data.login_url || '/my-account/';
            });
            return;
        }

        // Member logic: AJAX to backend
        var btn = document.querySelector('.o100-birthday-save');
        var oldText = btn.innerText;
        btn.innerText = 'Saving...';
        btn.disabled = true;

        var formData = new FormData();
        formData.append('action', 'o100_save_birthday');
        formData.append('birthday', newDate);
        if (state.config.nonces && state.config.nonces.render_page_nonce) {
            formData.append('nonce', state.config.nonces.render_page_nonce);
        }

        fetch(wll_localize_data.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.innerText = oldText;
            btn.disabled = false;
            
            if (!data.success) {
                var errorMsg = data.data || 'Failed to save birthday.';
                if (typeof errorMsg === 'object') {
                    errorMsg = errorMsg.message || JSON.stringify(errorMsg);
                }
                showToast(errorMsg, 'error');
                return;
            }
            
            var resData = data.data;
            if (resData.status === 'identical') {
                showToast(resData.message || 'You have already set this birthday.', 'info');
            } else if (resData.status === 'not_allowed') {
                showToast(resData.message || 'Sorry, you cannot modify your birthday once it has been set.', 'error');
            } else if (resData.status === 'confirm') {
                showConfirm(resData.message || 'Are you sure you want to overwrite your birthday? You can only receive a reward once per year.', function() {
                    // Force save
                    forceSaveBirthday(newDate, btn, oldText);
                });
            } else if (resData.status === 'success') {
                showToast('Birthday saved successfully!', 'success');
                item.birth_date = newDate;
            }
        })
        .catch(err => {
            btn.innerText = oldText;
            btn.disabled = false;
            showToast('A network error occurred.', 'error');
        });
    }

    function forceSaveBirthday(newDate, btn, oldText) {
        btn.innerText = 'Saving...';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', 'o100_save_birthday');
        formData.append('birthday', newDate);
        formData.append('force', '1');
        if (state.config.nonces && state.config.nonces.render_page_nonce) {
            formData.append('nonce', state.config.nonces.render_page_nonce);
        }

        fetch(wll_localize_data.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.innerText = oldText;
            btn.disabled = false;
            if (data.success && data.data && data.data.status === 'success') {
                showToast('Birthday saved successfully!', 'success');
            } else {
                var errData = data.data || 'Failed to save birthday.';
                if (typeof errData === 'object') {
                    errData = errData.message || JSON.stringify(errData);
                }
                showToast(errData, 'error');
            }
        });
    }

    function showToast(message, type = 'success') {
        var toast = document.getElementById('o100-launcher-toast');
        if (toast) toast.remove();
        
        toast = document.createElement('div');
        toast.id = 'o100-launcher-toast';
        
        var bgColor = type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#f59e0b');
        var icon = type === 'success' ? 'dashicons-saved' : (type === 'error' ? 'dashicons-warning' : 'dashicons-info');
        
        toast.innerHTML = `
            <div style="background:${bgColor}; color:#fff; padding:12px 20px; border-radius:8px; display:flex; align-items:center; gap:10px; box-shadow:0 10px 25px rgba(0,0,0,0.2); font-size:14px; font-weight:500; min-width:250px; transform:translateY(100%); opacity:0; transition:all 0.3s ease;">
                <span class="dashicons ${icon}" style="font-size:18px; width:18px; height:18px;"></span>
                <span>${message}</span>
            </div>
        `;
        toast.style.cssText = 'position:fixed; bottom:20px; right:20px; z-index:999999;';
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.firstElementChild.style.transform = 'translateY(0)';
            toast.firstElementChild.style.opacity = '1';
        }, 10);
        
        setTimeout(() => {
            if (toast && toast.firstElementChild) {
                toast.firstElementChild.style.transform = 'translateY(100%)';
                toast.firstElementChild.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }
        }, 3000);
    }

    function showConfirm(message, onConfirm) {
        var overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999999; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 0.2s;';
        
        var modal = document.createElement('div');
        modal.style.cssText = 'background:#fff; border-radius:12px; padding:24px; max-width:320px; width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); transform:scale(0.95); transition:transform 0.2s;';
        
        var themeColor = state.config.design?.colors?.theme?.primary || '#4F47EB';
        
        modal.innerHTML = `
            <div style="font-size:18px; font-weight:600; color:#1e293b; margin-bottom:10px;">Confirm Action</div>
            <div style="font-size:14px; color:#64748b; margin-bottom:24px; line-height:1.5;">${message}</div>
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button class="o100-cancel-btn" style="padding:10px 16px; border-radius:6px; border:1px solid #cbd5e1; background:#fff; color:#475569; font-weight:500; cursor:pointer;">Cancel</button>
                <button class="o100-confirm-btn" style="padding:10px 16px; border-radius:6px; border:none; background:${themeColor}; color:#fff; font-weight:500; cursor:pointer;">Confirm</button>
            </div>
        `;
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        setTimeout(() => {
            overlay.style.opacity = '1';
            modal.style.transform = 'scale(1)';
        }, 10);
        
        var close = () => {
            overlay.style.opacity = '0';
            modal.style.transform = 'scale(0.95)';
            setTimeout(() => overlay.remove(), 200);
        };
        
        modal.querySelector('.o100-cancel-btn').addEventListener('click', close);
        modal.querySelector('.o100-confirm-btn').addEventListener('click', () => {
            close();
            onConfirm();
        });
    }

});

/* TS: 20260117162749 */
