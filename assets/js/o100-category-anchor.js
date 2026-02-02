/**
 * Category Anchor Navigation (Robust v3)
 * Handles AJAX loading, location popups, and dynamic content centering.
 */
(function ($) {
    'use strict';

    var isScrolled = false;
    var maxRetries = 40; // 20 seconds total (500ms intervals)

    function sanitizeCategoryName(name) {
        if (!name) return '';
        // Remove parentheticals, trim, lowercase, replace non-alphanumeric with dashes
        return name.replace(/\(.*?\)/g, '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }

    function addCategoryAnchors() {
        // 1. Find and ID the main content headings
        const categoryElements = document.querySelectorAll('.exwf-mnheading .mn-namegroup span, .exwf-mnheading h3, .exwoofood-menu-content h2, .exwf-mnheading .mn-namegroup');

        categoryElements.forEach(el => {
            const categoryName = $(el).text().trim();
            if (categoryName) {
                const anchorId = sanitizeCategoryName(categoryName);
                if (!el.id || el.id !== anchorId) {
                    el.id = anchorId;
                }
            }
        });

        // 2. Add anchors to side menu items as well (Strategy fallback)
        $('.ex-menu-item').each(function () {
            const $item = $(this);
            const text = $item.find('span').first().text() || $item.text();
            if (text && !$item.attr('data-o100-anchor')) {
                const anchorId = sanitizeCategoryName(text);
                $item.attr('data-o100-anchor', anchorId);
            }
        });
    }

    function handleNavigation() {
        const targetId = window.location.hash.substring(1);
        if (!targetId || isScrolled) return;

        // Skip if location popup is blocking (polling will retry)
        const $popup = $('.ex-popup-location:visible');
        if ($popup.length) {
            console.log('[WFA] Location popup detected, deferring...');
            return;
        }

        console.log('[WFA] Navigating to:', targetId);

        // Strategy 1: Direct ID target (Main heading)
        let $target = $(`#${targetId}`);
        if ($target.length && $target.is(':visible')) {
            console.log('[WFA] Strategy 1: Found direct target by ID');
            performScroll($target);
            return;
        }

        // Strategy 2: Side menu click fallback
        let $sideMenuItem = $(`.ex-menu-item[data-o100-anchor="${targetId}"]`);
        if (!$sideMenuItem.length) {
            $('.ex-menu-item').each(function () {
                if (sanitizeCategoryName($(this).text()) === targetId) {
                    $sideMenuItem = $(this);
                    return false;
                }
            });
        }

        if ($sideMenuItem.length) {
            // Speedup: If already active, just scroll to it/content
            if ($sideMenuItem.hasClass('active')) {
                console.log('[WFA] Target already active, scrolling to side menu item');
                performScroll($sideMenuItem);
                return;
            }

            console.log('[WFA] Strategy 2: Clicking side menu item');
            $sideMenuItem.trigger('click');

            // Wait for content load (AJAX)
            setTimeout(() => {
                const $newTarget = $(`#${targetId}`);
                if ($newTarget.length && $newTarget.is(':visible')) {
                    performScroll($newTarget, true);
                } else {
                    console.log('[WFA] Heading not found after click, scrolling to side item');
                    performScroll($sideMenuItem, true);
                }
            }, 1200);
            return;
        }
    }

    function performScroll($target, isAjaxLoad = false) {
        if (isScrolled) return;

        // Final sanity check for visibility
        if (!$target.is(':visible')) return;

        isScrolled = true;
        console.log('[WFA] Performing scroll centering...');

        const headerHeight = getStickyHeaderHeight();
        const targetOffset = $target.offset().top;
        const targetHeight = $target.outerHeight();
        const windowHeight = $(window).height();

        // Formula: Center target in visible viewport area
        const scrollTop = (targetOffset + targetHeight / 2) - (headerHeight + (windowHeight - headerHeight) / 2);

        $('html, body').stop().animate({
            scrollTop: Math.max(0, scrollTop)
        }, 800);
    }

    function getStickyHeaderHeight() {
        let maxStickyHeight = 0;
        $('*').each(function () {
            const $el = $(this);
            const pos = $el.css('position');
            if ((pos === 'fixed' || pos === 'sticky') &&
                $el.outerWidth() > window.innerWidth * 0.8 &&
                $el.offset().top < window.pageYOffset + 10) {

                const h = $el.outerHeight();
                if (h > maxStickyHeight && h < 250) {
                    maxStickyHeight = h;
                }
            }
        });

        if (maxStickyHeight === 0) {
            const $header = $('.site-header, header.site-header');
            if ($header.length && $header.css('position') !== 'static') {
                maxStickyHeight = $header.outerHeight();
            }
        }
        return maxStickyHeight;
    }

    // --- Initialization ---

    $(document).ready(function () {
        addCategoryAnchors();
        startPolling();

        // 1. Mutation Observer for AJAX content
        const observer = new MutationObserver(() => {
            if (isScrolled) return;
            if (window.location.hash.substring(1)) {
                addCategoryAnchors();
                handleNavigation();
            }
        });

        const menuContainer = document.querySelector('#exwoofood-menu, .exwoofood-menu-content, body');
        if (menuContainer) {
            observer.observe(menuContainer, { childList: true, subtree: true });
        }
    });

    $(window).on('hashchange', function () {
        isScrolled = false;
        handleNavigation();
    });

    function startPolling() {
        let attempts = 0;
        const pollInterval = setInterval(() => {
            if (isScrolled || attempts >= maxRetries) {
                clearInterval(pollInterval);
                return;
            }
            addCategoryAnchors();
            handleNavigation();
            attempts++;
        }, 500);
    }

})(jQuery);



/* TS: 20260201215335 */
