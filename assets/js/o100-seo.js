/**
 * WFA Smart SEO – Admin JS
 * Handles Scan/Preview (dry-run) and One-Click Fix (batch processing)
 */
(function ($) {
  'use strict';

  if (typeof o100SeoData === 'undefined') return;

  var nonce = o100SeoData.nonce || '';
  var ajaxUrl = o100SeoData.ajaxUrl || ajaxurl;
  var BATCH_SIZE = 10;

  // ─── Step Navigation ──────────────────────────────────────────
  $(document).on('click', '.o100-seo-step-link', function (e) {
    e.preventDefault();
    var step = $(this).data('step');
    // Update nav
    $('.o100-seo-step-link').removeClass('is-active');
    $(this).addClass('is-active');
    // Update panels
    $('.o100-seo-step-panel').removeClass('is-active');
    $('.o100-seo-step-panel[data-step="' + step + '"]').addClass('is-active');
  });

  /**
   * Get rule value for a feature
   */
  function getRule(feature) {
    if (feature === 'title_desc') {
      return ''; // title_desc uses split fields, sent separately
    }
    var $input = $('[name="o100_seo_' + feature + '_rule"]');
    return $input.val() || '';
  }

  /**
   * Check if force overwrite is checked
   */
  function isForce(feature) {
    // Always force — user's checkbox selection IS the override decision
    return true;
  }

  // ─── Preview (Dry Run) ────────────────────────────────────────

  $(document).on('click', '.o100-seo-scan-btn', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var feature = $btn.data('feature');
    var $preview = $('.o100-preview-' + feature);
    var $tbody = $preview.find('.o100-preview-body');

    $btn.prop('disabled', true).text('Scanning...');
    $tbody.empty();
    $preview.hide();

    var postData = {
      action: 'o100_seo_scan',
      nonce: nonce,
      feature: feature,
      rule: getRule(feature),
      force: 'true'
    };

    // Send split rules for title_desc
    if (feature === 'title_desc') {
      postData.title_rule = $('[name="o100_seo_title_rule"]').val() || '';
      postData.desc_rule = $('[name="o100_seo_desc_rule"]').val() || '';
    }

    $.post(ajaxUrl, postData, function (res) {
      $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Preview');

      if (!res.success) {
        alert('Error: ' + (res.data || 'Unknown'));
        return;
      }

      var total = res.data.total;
      var preview = res.data.preview || [];
      var updateCount = 0, keepCount = 0;

      if (preview.length === 0) {
        $tbody.append('<tr><td colspan="6">No products found.</td></tr>');
      } else {
        for (var i = 0; i < preview.length; i++) {
          var p = preview[i];
          var currentDisplay = p.current || '<em style="color:#999;">empty</em>';
          var newDisplay = p.generated || '<em style="color:#999;">empty</em>';
          var statusDisplay = '';
          var isKeep = (p.status === 'keep');
          var isSkip = (p.status === 'skip');
          var checked = (isKeep || isSkip) ? '' : 'checked';

          if (feature === 'image_rename') {
            currentDisplay = p.current + (p.gallery_count > 0 ? ' (+' + p.gallery_count + ' gallery)' : '');
            newDisplay = p.generated;
          }

          // Status badges
          if (isSkip) {
            statusDisplay = '<span style="background:#f0b849;color:#333;padding:2px 8px;border-radius:3px;font-size:11px;">No Image</span>';
          } else if (feature === 'focus_keyword' && p.status) {
            if (isKeep) {
              statusDisplay = '<span style="background:#2271b1;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">Keep</span>';
              keepCount++;
            } else {
              statusDisplay = '<span style="background:#46b450;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">Update</span>';
              updateCount++;
              if (p.dedup) {
                statusDisplay += ' <span style="background:#f0b849;color:#333;padding:2px 8px;border-radius:3px;font-size:11px;">Dedup</span>';
              }
            }
          } else if (p.status === 'keep') {
            statusDisplay = '<span style="background:#2271b1;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">Keep</span>';
          } else if (p.status === 'update') {
            statusDisplay = '<span style="background:#46b450;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">Update</span>';
          } else {
            var changed = (p.current !== p.generated);
            statusDisplay = changed ? '<span style="color:#46b450;">&#x2713;</span>' : '<span style="color:#999;">&mdash;</span>';
          }

          var rowStyle = isSkip ? 'background:#fff5f5; opacity:0.6;' : (isKeep ? 'background:#f7f7f7; opacity:0.7;' : ((p.current !== p.generated) ? 'background:#f0fff0;' : ''));

          // Make the 'New' column editable via an input field
          var safeNewVal = $('<span>').text(p.generated || '').html();
          var editableNew = '<input type="text" class="o100-seo-edit-new" ' +
            'value="' + safeNewVal + '" ' +
            'data-original="' + safeNewVal + '" ' +
            'style="width:100%;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;font-weight:600;" ' +
            'title="Edit to customize this value before Apply">';

          $tbody.append(
            '<tr style="' + rowStyle + '" data-pid="' + p.id + '">' +
            '<td><input type="checkbox" class="o100-seo-row-check" value="' + p.id + '" ' + checked + '></td>' +
            '<td>' + p.id + '</td>' +
            '<td>' + $('<span>').text(p.title).html() + '</td>' +
            '<td>' + currentDisplay + '</td>' +
            '<td>' + editableNew + '</td>' +
            '<td>' + statusDisplay + '</td>' +
            '</tr>'
          );
        }
      }

      // Reset select-all checkbox
      $preview.find('.o100-seo-select-all').prop('checked', true);
      updateSummary($preview, total);
      $preview.slideDown(200);
    });
  });

  // ─── Dynamic summary counter ─────────────────────────────────
  function updateSummary($preview, total) {
    var checked = $preview.find('.o100-seo-row-check:checked:not(:disabled)').length;
    var skipped = $preview.find('.o100-seo-row-check:disabled').length;
    var unchecked = total - checked - skipped;
    var html = '<strong>Total: ' + total + ' products</strong> &mdash; ';
    html += '<span style="color:#46b450;">' + checked + ' selected to update</span>';
    if (unchecked > 0) {
      html += ', <span style="color:#6b7280;">' + unchecked + ' will keep</span>';
    }
    if (skipped > 0) {
      html += ', <span style="color:#999;">' + skipped + ' skipped</span>';
    }
    $preview.find('.o100-summary-text').html(html);
  }

  // ─── Select All checkbox ──────────────────────────────────────
  $(document).on('change', '.o100-seo-select-all', function () {
    var checked = $(this).prop('checked');
    var $preview = $(this).closest('.o100-seo-preview');
    $preview.find('.o100-seo-row-check:not(:disabled)').prop('checked', checked);
    var total = $preview.find('.o100-seo-row-check').length;
    updateSummary($preview, total);
  });

  // ─── Individual checkbox change ──────────────────────────────
  $(document).on('change', '.o100-seo-row-check', function () {
    var $preview = $(this).closest('.o100-seo-preview');
    var total = $preview.find('.o100-seo-row-check').length;
    updateSummary($preview, total);
  });

  // ─── Help tooltip toggle ──────────────────────────────────────
  $(document).on('click', '.o100-seo-help-toggle', function () {
    $(this).closest('.o100-seo-step-header').find('.o100-seo-help-panel').slideToggle(200);
  });

  // ─── One-Click Fix (Batch Processing) ──────────────────────────

  $(document).on('click', '.o100-seo-fix-btn', function (e) {
    e.preventDefault();
    var feature = $(this).data('feature');
    var isRisk = $(this).data('risk');
    var $btn = $(this);

    if (isRisk) {
      var msgs = {
        slug: '⚠️ WARNING: Slug Modification\n\nModifying product URLs may temporarily impact SEO rankings.\nSystem will auto-create 301 redirects via Rank Math.\n\nPlease ensure you have database backups.\nContinue?',
        image_rename: '⚠️ WARNING: Image File Rename\n\nThis will physically rename image files on disk and update all thumbnails + database references.\n\nPlease backup your wp-content/uploads directory first.\nContinue?'
      };
      var msg = msgs[feature] || '⚠️ This will modify product data. Make sure you have a backup.\n\nContinue?';
      if (!confirm(msg)) {
        return;
      }
    }

    $btn.prop('disabled', true).text('Processing...');

    // Get checked product IDs from preview table if available
    var $preview = $('.o100-preview-' + feature);
    var checkedIds = [];
    var overrides = {};
    $preview.find('.o100-seo-row-check:checked').each(function () {
      var pid = parseInt($(this).val());
      checkedIds.push(pid);
      // Check if user manually edited the 'New' value
      var $row = $(this).closest('tr');
      var $editInput = $row.find('.o100-seo-edit-new');
      if ($editInput.length) {
        var currentVal = $editInput.val();
        var originalVal = $editInput.data('original');
        if (currentVal !== originalVal) {
          overrides[pid] = currentVal;
        }
      }
    });

    // If no preview or no checkboxes, scan first to get IDs
    if (checkedIds.length === 0) {
      var scanData = {
        action: 'o100_seo_scan',
        nonce: nonce,
        feature: feature,
        rule: getRule(feature)
      };
      if (feature === 'title_desc') {
        scanData.title_rule = $('[name="o100_seo_title_rule"]').val() || '';
        scanData.desc_rule = $('[name="o100_seo_desc_rule"]').val() || '';
      }
      $.post(ajaxUrl, scanData, function (res) {
        if (!res.success) {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Apply');
          alert('Scan failed: ' + (res.data || 'Unknown'));
          return;
        }
        startBatchProcess(feature, res.data.ids, $btn);
      });
    } else {
      startBatchProcess(feature, checkedIds, $btn, overrides);
    }
  });

  function startBatchProcess(feature, ids, $btn, overrides) {
    overrides = overrides || {};
    var total = ids.length;

    if (total === 0) {
      $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Apply');
      alert('No products selected to process.');
      return;
    }

    var $preview = $('.o100-preview-' + feature);
    var $progress = $('.o100-progress-' + feature);
    var $fill = $progress.find('.o100-progress-fill');
    var $text = $progress.find('.o100-progress-text');
    $progress.show();
    $fill.css('width', '0%');
    $text.text('0 / ' + total + ' Products');

    var processed = 0;
    var totalChanged = 0;

    function processBatch(startIndex) {
      var batch = ids.slice(startIndex, startIndex + BATCH_SIZE);
      if (batch.length === 0) {
        $fill.css('width', '100%');
        $text.text(total + ' / ' + total + ' Products — ' + totalChanged + ' modified ✅');
        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Apply');

        // Enable Revert button
        if (totalChanged > 0) {
          var $revert = $btn.closest('.o100-seo-action-buttons').find('.o100-seo-revert-btn');
          $revert.prop('disabled', false);
        }
        return;
      }

      // Collect overrides for this batch
      var batchOverrides = {};
      for (var j = 0; j < batch.length; j++) {
        if (overrides[batch[j]]) {
          batchOverrides[batch[j]] = overrides[batch[j]];
        }
      }

      var postData = {
        action: 'o100_seo_fix_batch',
        nonce: nonce,
        feature: feature,
        rule: getRule(feature),
        force: isForce(feature) ? 'true' : 'false',
        product_ids: batch,
        overrides: batchOverrides
      };

      if (feature === 'slug') {
        postData.slug_filter = $('[name="o100_seo_slug_filter"]').is(':checked') ? 'true' : 'false';
      }

      $.post(ajaxUrl, postData, function (res) {
        if (res.success) {
          processed += batch.length;
          totalChanged += (res.data.processed || 0);
          var pct = Math.round((processed / total) * 100);
          $fill.css('width', pct + '%');
          $text.text(processed + ' / ' + total + ' Products — ' + totalChanged + ' modified');

          // Update preview rows for changed products
          var changedIds = res.data.changed_ids || [];
          for (var i = 0; i < changedIds.length; i++) {
            var $row = $preview.find('tr[data-pid="' + changedIds[i] + '"]');
            if ($row.length) {
              var newVal = $row.find('td:nth-child(5)').html();
              // Update CURRENT column to show new value
              $row.find('td:nth-child(4)').html(newVal);
              // Update STATUS to Done badge
              $row.find('td:nth-child(6)').html(
                '<span style="background:#46b450;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">Done ✓</span>'
              );
              // Style the row as completed
              $row.css({
                'background': '#f0fdf4',
                'opacity': '1'
              });
              // Uncheck and disable checkbox
              $row.find('.o100-seo-row-check').prop('checked', false).prop('disabled', true);
            }
          }
        }
        processBatch(startIndex + BATCH_SIZE);
      }).fail(function () {
        processed += batch.length;
        processBatch(startIndex + BATCH_SIZE);
      });
    }

    processBatch(0);
  }

  // ─── Revert handler ──────────────────────────────────────────
  $(document).on('click', '.o100-seo-revert-btn', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var feature = $btn.data('feature');

    if (!confirm('⚠️ Revert all changes made by the last Apply?\n\nThis will restore all modified products to their original values.\n\nContinue?')) {
      return;
    }

    $btn.prop('disabled', true).text('Reverting...');

    $.post(ajaxUrl, {
      action: 'o100_seo_revert',
      nonce: nonce,
      feature: feature
    }, function (res) {
      if (res.success) {
        alert('✅ ' + res.data.message);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-undo"></span> Revert');
        // Refresh preview
        var $scanBtn = $btn.closest('.o100-seo-step-panel').find('.o100-seo-scan-btn');
        if ($scanBtn.length) {
          $scanBtn.trigger('click');
        }
      } else {
        alert('Error: ' + (res.data || 'Revert failed'));
        $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> Revert');
      }
    }).fail(function () {
      alert('AJAX error during revert');
      $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> Revert');
    });
  });

})(jQuery);


/* TS: 20260104170421 */

/* TS: 20260126181332 */
