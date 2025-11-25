/**
 * SMS Notifications - Voxel UI Injection
 *
 * Injects SMS notification toggle into Voxel's App Events & Notifications UI.
 * Uses Voxel's native dynamic data editor (Voxel_Dynamic.edit).
 */
(function($) {
    'use strict';

    var config = window.vt_sms_config || {};
    var smsEvents = config.events || {};
    var ajaxUrl = config.ajax_url || '';
    var nonce = config.nonce || '';
    var isEnabled = config.enabled || false;
    var phoneConfigured = config.phone_configured || false;

    var toggleIdCounter = 0;

    function init() {
        console.log('[VT SMS] Initializing');
        observeNotificationPanels();
    }

    /**
     * Get dynamic tags from Voxel config for a specific event
     */
    function getDynamicTags(eventKey) {
        var vxConfig = document.querySelector('.vxconfig');
        if (vxConfig) {
            try {
                var cfg = JSON.parse(vxConfig.textContent);
                if (cfg.events && cfg.events[eventKey] && cfg.events[eventKey].dynamic_tags) {
                    return cfg.events[eventKey].dynamic_tags;
                }
            } catch (e) {
                console.log('[VT SMS] Error parsing config', e);
            }
        }
        return null;
    }

    /**
     * Open Voxel's native dynamic data editor
     */
    function openVoxelEditor(eventKey, dest, currentMsg, displayElement) {
        // Check if Voxel_Dynamic exists
        if (typeof Voxel_Dynamic === 'undefined' || typeof Voxel_Dynamic.edit !== 'function') {
            console.error('[VT SMS] Voxel_Dynamic.edit not available');
            alert('Dynamic tag editor not available. Please ensure you are on the App Events page.');
            return;
        }

        // Get dynamic tags for this event
        var dynamicTags = getDynamicTags(eventKey);
        if (!dynamicTags) {
            console.error('[VT SMS] No dynamic tags found for event:', eventKey);
            // Fallback to basic groups
            dynamicTags = {
                'user': { label: 'User', type: 'user' },
                'site': { label: 'Site', type: 'site' }
            };
        }

        // Call Voxel's native editor
        Voxel_Dynamic.edit(currentMsg || '', {
            groups: dynamicTags,
            onSave: function(newValue) {
                // Update the display (textarea)
                if (displayElement) {
                    displayElement.value = newValue || '';
                }

                // Update local state
                if (!smsEvents[eventKey]) smsEvents[eventKey] = {};
                if (!smsEvents[eventKey][dest]) smsEvents[eventKey][dest] = { enabled: true, message: '' };
                smsEvents[eventKey][dest].message = newValue;

                // Save to server
                saveEventSettings(eventKey, dest, true, newValue);
            }
        });
    }

    function observeNotificationPanels() {
        var app = document.getElementById('vx-app-events');
        if (!app) {
            setTimeout(observeNotificationPanels, 500);
            return;
        }
        setTimeout(injectAllToggles, 500);
        var obs = new MutationObserver(function() {
            clearTimeout(obs.timer);
            obs.timer = setTimeout(injectAllToggles, 100);
        });
        obs.observe(app, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
    }

    function injectAllToggles() {
        document.querySelectorAll('.ts-form-group.switch-slider').forEach(function(fg) {
            var lbl = fg.querySelector('label');
            if (!lbl || lbl.textContent.trim() !== 'Send email notification') return;

            var ctx = getEventContext(fg);
            if (!ctx) return;

            var parent = fg.parentNode;
            if (parent.querySelector('.vt-sms-toggle-wrapper[data-event-key="' + ctx.eventKey + '"][data-destination="' + ctx.destination + '"]')) return;

            injectSmsToggle(fg, ctx.eventKey, ctx.destination);
        });
    }

    function getEventContext(fg) {
        var cont = fg.closest('.single-field.open');
        if (!cont) return null;
        var ft = cont.querySelector('.field-type');
        if (!ft) return null;
        var tabs = cont.querySelector('.inner-tabs:not(.vertical-tabs)');
        if (!tabs) return null;
        var active = tabs.querySelector('li.current-item a');
        if (!active) return null;
        return {
            eventKey: ft.textContent.trim(),
            destination: active.textContent.trim().toLowerCase().replace('notify ', '').trim()
        };
    }

    function injectSmsToggle(after, ek, dest) {
        var cfg = getSmsConfig(ek, dest);
        var id = 'vt-sms-toggle-' + (++toggleIdCounter);

        var wrap = document.createElement('div');
        wrap.className = 'ts-form-group switch-slider x-col-12 vt-sms-toggle-wrapper';
        wrap.setAttribute('data-event-key', ek);
        wrap.setAttribute('data-destination', dest);

        var html = '<label>Send SMS notification</label><div class="onoffswitch">' +
            '<input type="checkbox" class="onoffswitch-checkbox vt-sms-cb" id="' + id + '"' +
            (cfg.enabled ? ' checked' : '') + (isEnabled ? '' : ' disabled') + '>' +
            '<label class="onoffswitch-label" for="' + id + '"></label></div>';

        if (!isEnabled) {
            html += '<p style="margin-top:8px;font-size:12px;color:#999;"><span style="color:#d63638;">SMS Notifications is disabled.</span> <a href="' + esc(config.settings_url) + '" style="color:#2271b1;">Configure in Voxel Toolkit</a></p>';
        } else if (!phoneConfigured) {
            html += '<p style="margin-top:8px;font-size:12px;color:#d63638;">No phone field selected. <a href="' + esc(config.settings_url) + '" style="color:#2271b1;">Select phone field</a></p>';
        }

        wrap.innerHTML = html;

        // Find insert point
        var ins = after;
        var sib = after.nextElementSibling;
        while (sib && sib.classList.contains('ts-form-group') && !sib.classList.contains('vt-sms-toggle-wrapper')) {
            var l = sib.querySelector('label');
            if (l && (l.textContent.indexOf('Email') !== -1 || l.textContent.indexOf('email') !== -1)) {
                ins = sib;
            } else break;
            sib = sib.nextElementSibling;
        }

        ins.parentNode.insertBefore(wrap, ins.nextSibling);

        if (cfg.enabled && isEnabled) {
            var mf = createMessageField(ek, dest, cfg.message, wrap);
            wrap.parentNode.insertBefore(mf, wrap.nextSibling);
        }

        wrap.querySelector('.vt-sms-cb').addEventListener('change', function() {
            handleToggle(ek, dest, this.checked, wrap);
        });
    }

    function createMessageField(ek, dest, msg, toggle) {
        var cont = document.createElement('div');
        cont.className = 'ts-form-group x-col-12 vt-sms-message-wrapper';
        cont.setAttribute('data-event-key', ek);
        cont.setAttribute('data-destination', dest);

        var ph = 'Click to add SMS message...';
        cont.innerHTML = '<label>SMS notification message</label>' +
            '<textarea class="vt-sms-msg min-scroll" readonly placeholder="' + esc(ph) + '" style="cursor:pointer;">' +
            esc(msg || '') + '</textarea>';

        var display = cont.querySelector('.vt-sms-msg');

        display.addEventListener('click', function(e) {
            e.preventDefault();
            var cur = smsEvents[ek] && smsEvents[ek][dest] ? smsEvents[ek][dest].message : '';
            openVoxelEditor(ek, dest, cur, display);
        });

        return cont;
    }

    function handleToggle(ek, dest, enabled, wrap) {
        var mw = wrap.nextElementSibling;
        var has = mw && mw.classList.contains('vt-sms-message-wrapper');

        if (enabled && isEnabled) {
            if (!has) {
                var cfg = getSmsConfig(ek, dest);
                var mf = createMessageField(ek, dest, cfg.message, wrap);
                wrap.parentNode.insertBefore(mf, wrap.nextSibling);
            }
        } else if (has) {
            mw.remove();
        }

        var msg = getSmsConfig(ek, dest).message || '';
        saveEventSettings(ek, dest, enabled, msg);
    }

    function getSmsConfig(ek, dest) {
        return (smsEvents[ek] && smsEvents[ek][dest]) ? smsEvents[ek][dest] : { enabled: false, message: '' };
    }

    function saveEventSettings(ek, dest, enabled, msg) {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'vt_save_sms_event_settings',
                nonce: nonce,
                event_key: ek,
                destination: dest,
                enabled: enabled ? 'true' : 'false',
                message: msg
            },
            success: function(r) {
                if (r.success) {
                    if (!smsEvents[ek]) smsEvents[ek] = {};
                    smsEvents[ek][dest] = { enabled: enabled, message: msg };
                    // No notice - toggle provides visual feedback
                } else {
                    showNotice(r.data.message || 'Error', 'error');
                }
            },
            error: function() { showNotice('Network error', 'error'); }
        });
    }

    function showNotice(msg, type) {
        var ex = document.querySelector('.vt-sms-notice');
        if (ex) ex.remove();
        var n = document.createElement('div');
        n.className = 'vt-sms-notice';
        n.style.cssText = 'position:fixed;bottom:20px;right:20px;padding:12px 20px;border-radius:4px;color:#fff;z-index:100002;font-size:14px;';
        n.style.backgroundColor = type === 'success' ? '#00a32a' : '#d63638';
        n.textContent = msg;
        document.body.appendChild(n);
        setTimeout(function() {
            n.style.opacity = '0';
            n.style.transition = 'opacity 0.3s';
            setTimeout(function() { n.remove(); }, 300);
        }, 2000);
    }

    function esc(t) {
        if (!t) return '';
        var d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    $(document).ready(function() {
        if (document.getElementById('vx-app-events')) init();
    });

})(jQuery);
