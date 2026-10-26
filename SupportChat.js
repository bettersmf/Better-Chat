(function () {
    'use strict';

    function boot() {
        if (!window.jQuery || !window.better_chat_support || !window.better_chat_support.enabled) {
            return setTimeout(boot, 100);
        }

        const $ = window.jQuery;
        const cfg = window.better_chat_support;
        const url = cfg.script_url + '?action=chat_msg';
        const root = $('#better-support-root');
        const bubble = $('#better-support-bubble');
        const panel = $('#better-support-panel');
        const body = $('#better-support-body');
        const badge = $('#better-support-badge');
        let pollTimer = null;
        let lastMessageId = 0;
        let refreshInFlight = false;
        let sendInFlight = false;
        let mountedSupportSessionId = 0;
        const supportStateKey = 'better_chat_support_minimized';

        function positionSupportRoot() {
            const groupBubble = $('#better-chat-group-bubble');
            const groupBubbleVisible = groupBubble.length && groupBubble.is(':visible');

            // Hybrid-mode bubble rail authority. When the Messenger group bubble
            // is visible, anchor Support in the next vertical slot using the
            // bubble's actual rendered rectangle. This avoids Edge/Chrome drift
            // from separate hard-coded offsets and guarantees no overlap.
            if (groupBubbleVisible) {
                const rect = groupBubble[0].getBoundingClientRect();
                // Horizontal rail alignment is deliberately CSS-owned. The previous
                // computed-right value was correct mathematically, but the active theme's
                // generic button margins could still shift the Support button inside its
                // root. Lock the root to the same rail right edge and only compute the
                // vertical slot from the actual rendered Group bubble.
                const bottom = Math.max(10, Math.round(window.innerHeight - rect.top + 10));
                root.addClass('has-group-bubble').css({ bottom: bottom + 'px' });
            } else {
                root.removeClass('has-group-bubble').css({ right: '', bottom: '' });
            }

            // Let the private-message dock recalculate its first slot above
            // whichever Hybrid bubbles are currently visible.
            $(document).trigger('betterchat:supportstatechange');
        }

        function saveSupportWindowState(minimized) {
            try { localStorage.setItem(supportStateKey, minimized ? 'true' : 'false'); } catch (e) {}
        }

        function minimizeSupport() {
            panel.prop('hidden', true);
            bubble.removeClass('is-open');
            saveSupportWindowState(true);
            positionSupportRoot();
        }

        function restoreSupport(doRefresh) {
            panel.prop('hidden', false);
            bubble.addClass('is-open');
            saveSupportWindowState(false);
            setBadge(0);
            positionSupportRoot();
            if (doRefresh !== false) refresh();
        }

        function restoreSavedSupportWindowState() {
            let saved = null;
            try { saved = localStorage.getItem(supportStateKey); } catch (e) {}
            if (saved === 'false') {
                panel.prop('hidden', false);
                bubble.addClass('is-open');
            } else {
                panel.prop('hidden', true);
                bubble.removeClass('is-open');
            }
            positionSupportRoot();
        }

        function esc(v) {
            return String(v == null ? '' : v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function sessionData() {
            const data = {};
            if (cfg.session_var) data[cfg.session_var] = cfg.session_id;
            return data;
        }

        function post(action, extra) {
            return $.ajax({
                url: url,
                method: 'POST',
                dataType: 'json',
                data: Object.assign({ support_action: action }, sessionData(), extra || {})
            });
        }

        function getStatus() {
            return $.ajax({
                url: url,
                method: 'GET',
                dataType: 'json',
                cache: false,
                data: { support_action: 'status', _: Date.now() }
            });
        }

        function setBadge(count) {
            count = Math.max(0, parseInt(count || 0, 10));
            if (!count) badge.prop('hidden', true).text('0');
            else badge.prop('hidden', false).text(count > 99 ? '99+' : count);
        }

        // Support-only mode does not load BetterChat.js, so keep a tiny local
        // GIPHY picker fallback. Hybrid/community mode reuses the shared picker.
        let supportGiphyRequest = null;
        let supportGiphySearchTimer = null;
        let supportGiphySelect = null;
        let supportGiphyAnchor = null;

        function supportGiphyEscape(v) {
            return String(v == null ? '' : v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function supportGiphyClose() {
            $('#better-chat-giphy-picker').prop('hidden', true);
            supportGiphySelect = null;
            supportGiphyAnchor = null;
            if (supportGiphyRequest && typeof supportGiphyRequest.abort === 'function') supportGiphyRequest.abort();
            supportGiphyRequest = null;
        }

        function supportGiphyBuild() {
            if ($('#better-chat-giphy-picker').length) return;
            $('body').append('<div id="better-chat-giphy-picker" class="better-chat-giphy-picker" hidden>' +
                '<div class="better-chat-giphy-search-row"><input class="better-chat-giphy-search" type="search" maxlength="50" placeholder="Search GIFs…" autocomplete="off"><button type="button" class="better-chat-giphy-close" aria-label="Close GIF picker" title="Close">×</button></div>' +
                '<div class="better-chat-giphy-status">Trending</div><div class="better-chat-giphy-grid" role="list"></div>' +
                '<div class="better-chat-giphy-attribution">Powered by <strong>GIPHY</strong></div></div>');
        }

        function supportGiphyPosition(button) {
            const picker = $('#better-chat-giphy-picker');
            if (!picker.length || !button) return;
            supportGiphyAnchor = button;
            const node = picker[0];
            node.hidden = false;
            const b = button.getBoundingClientRect();
            const r = node.getBoundingClientRect();
            const gap = 14;
            const edge = 12;
            const left = Math.max(edge, Math.min(b.right - r.width, window.innerWidth - r.width - edge));
            let top = b.top - r.height - gap;
            if (top < edge) {
                const below = b.bottom + gap;
                top = (below + r.height <= window.innerHeight - edge) ? below : edge;
            }
            top = Math.max(edge, Math.min(top, window.innerHeight - r.height - edge));
            picker.css({ left: left + 'px', top: top + 'px' });
        }

        function supportGiphyLoad(term) {
            const q = String(term || '').trim().slice(0, 50);
            const picker = $('#better-chat-giphy-picker');
            const endpoint = q ? 'search' : 'trending';
            picker.find('.better-chat-giphy-status').text(q ? 'Searching “' + q + '”…' : 'Loading trending GIFs…');
            picker.find('.better-chat-giphy-grid').html('<div class="better-chat-giphy-empty">Loading…</div>');
            if (supportGiphyRequest && typeof supportGiphyRequest.abort === 'function') supportGiphyRequest.abort();
            const params = { api_key: String(cfg.giphy_api_key || '').trim(), limit: 20, rating: 'pg-13' };
            if (q) params.q = q;
            supportGiphyRequest = $.ajax({
                url: 'https://api.giphy.com/v1/gifs/' + endpoint,
                method: 'GET', dataType: 'json', data: params, timeout: 10000
            }).done(function(data) {
                const items = data && Array.isArray(data.data) ? data.data : [];
                picker.find('.better-chat-giphy-status').text(q ? 'Results for “' + q + '”' : 'Trending');
                if (!items.length) {
                    picker.find('.better-chat-giphy-grid').html('<div class="better-chat-giphy-empty">No GIFs found.</div>');
                    return;
                }
                const html = items.map(function(item) {
                    const images = item.images || {};
                    const preview = (images.fixed_width_small && (images.fixed_width_small.webp || images.fixed_width_small.url)) || (images.fixed_width && (images.fixed_width.webp || images.fixed_width.url)) || '';
                    const sendUrl = (images.fixed_width && images.fixed_width.url) || (images.original && images.original.url) || preview;
                    if (!preview || !sendUrl) return '';
                    const title = supportGiphyEscape(item.title || 'GIF');
                    return '<button type="button" class="better-chat-giphy-result" role="listitem" data-giphy-url="' + supportGiphyEscape(sendUrl) + '" data-giphy-title="' + title + '" title="' + title + '"><img src="' + supportGiphyEscape(preview) + '" alt="' + title + '" loading="lazy"></button>';
                }).join('');
                picker.find('.better-chat-giphy-grid').html(html || '<div class="better-chat-giphy-empty">No GIFs found.</div>');
            }).fail(function(xhr, status) {
                if (status === 'abort') return;
                picker.find('.better-chat-giphy-status').text('GIPHY unavailable');
                picker.find('.better-chat-giphy-grid').html('<div class="better-chat-giphy-empty">Could not load GIFs. Check the GIPHY API key in Better Chat settings.</div>');
            }).always(function(){ supportGiphyRequest = null; });
        }

        function openSupportGiphy(button, onSelect) {
            if (typeof window.betterChatOpenGiphyPicker === 'function') {
                window.betterChatOpenGiphyPicker(button, onSelect);
                return;
            }
            if (!cfg.giphy_enabled || !cfg.giphy_api_key) return;
            supportGiphyBuild();
            supportGiphySelect = onSelect;
            const picker = $('#better-chat-giphy-picker');
            picker.find('.better-chat-giphy-search').val('');
            supportGiphyPosition(button);
            supportGiphyLoad('');
            setTimeout(function(){ picker.find('.better-chat-giphy-search').trigger('focus'); }, 0);
        }

        $(document).on('input.betterSupportGiphy', '.better-chat-giphy-search', function(){
            if (typeof window.betterChatOpenGiphyPicker === 'function') return;
            const term = $(this).val();
            clearTimeout(supportGiphySearchTimer);
            supportGiphySearchTimer = setTimeout(function(){ supportGiphyLoad(term); }, 350);
        });
        $(document).on('click.betterSupportGiphy', '.better-chat-giphy-result', function(e){
            if (typeof window.betterChatOpenGiphyPicker === 'function') return;
            e.preventDefault(); e.stopPropagation();
            const cb = supportGiphySelect;
            const payload = { url: $(this).attr('data-giphy-url') || '', title: $(this).attr('data-giphy-title') || '' };
            supportGiphyClose();
            if (cb && payload.url) cb(payload);
        });
        $(document).on('click.betterSupportGiphy', '.better-chat-giphy-close', function(e){
            if (typeof window.betterChatOpenGiphyPicker === 'function') return;
            e.preventDefault(); e.stopPropagation(); supportGiphyClose();
        });

        function restrictionHtml(restriction) {
            if (!restriction) return '';
            return '<div id="better-support-restriction" class="better-support-restriction" role="status">' +
                '<strong>🔒 Support access restricted</strong>' +
                '<span><b>Reason:</b> ' + esc(restriction.reason || 'No reason provided.') + '</span>' +
                '<span><b>Duration:</b> ' + esc(restriction.duration_text || 'Until further notice') + '</span>' +
                '<span><b>Time remaining:</b> ' + esc(restriction.expires_at ? Math.max(0, Math.ceil((parseInt(restriction.expires_at, 10) - Date.now() / 1000) / 60)) + ' minute(s)' : 'Permanent') + '</span>' +
                '<span><b>Expires:</b> ' + esc(restriction.expires_text || 'Until further notice') + '</span>' +
            '</div>';
        }

        function applyConversationRestriction(restriction) {
            const notice = $('#better-support-restriction');
            const input = $('#better-support-input');
            const send = $('#better-support-send');
            const attach = $('#better-support-attach');
            const giphy = $('#better-support-giphy');
            const file = $('#better-support-file');
            if (restriction) {
                if (notice.length) notice.replaceWith(restrictionHtml(restriction));
                else $('#better-support-messages').before(restrictionHtml(restriction));
                input.prop('disabled', true).attr('placeholder', 'Support chat is restricted');
                send.prop('disabled', true);
                attach.prop('disabled', true);
                giphy.prop('disabled', true);
                file.prop('disabled', true);
            } else {
                notice.remove();
                input.prop('disabled', false).attr('placeholder', 'Type a message...');
                send.prop('disabled', false);
                attach.prop('disabled', false);
                giphy.prop('disabled', false);
                file.prop('disabled', false);
            }
        }

        function messageHtml(m) {
            if (m.sender_role === 'system') {
                return '<div class="better-support-system-event" role="status">' +
                    '<span class="better-support-system-line"></span>' +
                    '<span class="better-support-system-text">' + (m.body || '') + '</span>' +
                    '<span class="better-support-system-line"></span>' +
                '</div>';
            }

            const mine = cfg.is_staff ? m.sender_role === 'staff' : m.sender_role === 'customer';
            const isGiphy = /<img\b[^>]*src=["'][^"']*\.giphy\.com\//i.test(String(m.body || ''));
            return '<div class="better-support-msg ' + (mine ? 'is-mine' : 'is-theirs') + (isGiphy ? ' is-giphy' : '') + '">' +
                '<div class="better-support-msg-body">' + (m.body || '') + '</div>' +
                '<div class="better-support-msg-time">' + esc(m.time_text || '') + '</div>' +
            '</div>';
        }

        function render(data) {
            if (!data || data.status !== 'ok') {
                body.html('<div class="better-support-empty">Support is temporarily unavailable.</div>');
                return;
            }

            if (data.is_staff) {
                setBadge((data.queue || []).length);
                if (data.session && data.session.status === 'active') {
                    panel.removeClass('is-queue is-idle is-waiting').addClass('is-conversation');
                    renderConversation(data);
                    return;
                }
                panel.removeClass('is-conversation is-idle is-waiting').addClass('is-queue');
                let html = '<div class="better-support-staffbar"><label><input type="checkbox" id="better-support-duty" ' + (data.on_duty ? 'checked' : '') + '> On duty</label></div>';
                html += '<div class="better-support-section-title"><span>Waiting customers</span><span class="better-support-queue-count">' + ((data.queue || []).length) + '</span></div>';
                html += '<div class="better-support-queue">';
                if (!data.queue || !data.queue.length) {
                    html += '<div class="better-support-empty">No one is waiting right now.</div>';
                } else {
                    data.queue.forEach(function (q) {
                        html += '<div class="better-support-queue-item">' +
                            '<div class="better-support-queue-customer"><strong>' + esc(q.requester_name) + '</strong><span class="better-support-queue-status">Waiting for support</span></div>' +
                            '<button type="button" class="button better-support-queue-accept" data-id="' + q.id_support + '">Accept chat</button>' +
                        '</div>';
                    });
                }
                html += '</div>';
                body.html(html);
                mountedSupportSessionId = 0;
                return;
            }

            if (data.session && (data.session.status === 'waiting' || data.session.status === 'active')) {
                if (data.session.status === 'active') renderConversation(data);
                else { panel.removeClass('is-conversation is-queue is-idle').addClass('is-waiting'); mountedSupportSessionId = 0; body.html('<div class="better-support-waiting"><div class="better-support-pulse"></div><strong>You are in the support queue.</strong><span>A staff member will join you as soon as one is available.</span><button type="button" class="button" id="better-support-cancel">Cancel request</button></div>'); }
                return;
            }

            let guest = '';
            if (cfg.is_guest)
                guest = '<label class="better-support-guest-name">Your name<input type="text" id="better-support-guest-name" maxlength="80" placeholder="Guest"></label>';
            panel.removeClass('is-conversation is-queue is-waiting').addClass('is-idle');
            mountedSupportSessionId = 0;
            body.html('<div class="better-support-welcome"><div class="better-support-large-icon">💬</div><strong>How can we help?</strong><span>Start a private conversation with an available staff member.</span>' + guest + '<button type="button" class="button" id="better-support-request">Chat with Support</button></div>');
        }

        function renderConversation(data) {
            const session = data.session;
            const messages = data.messages || [];
            const partner = cfg.is_staff ? session.requester_name : (session.staff_name || 'Support');
            const sessionId = parseInt(session.id_support || 0, 10);
            const existingMessages = document.getElementById('better-support-messages');
            const existingComposer = document.getElementById('better-support-input');
            const sameMountedSession = mountedSupportSessionId === sessionId && existingMessages && existingComposer;

            // Mount the conversation shell only once per support session. AJAX polling must
            // never replace the composer node while the user is typing.
            if (!sameMountedSession) {
                let html = '<div class="better-support-conversation-head"><span><span class="better-support-live-dot"></span><span id="better-support-partner">' + esc(partner) + '</span></span><button type="button" id="better-support-end" class="better-support-primary-action">End chat</button></div>';
                html += '<div id="better-support-messages" class="better-support-messages"></div>';
                html += '<div class="better-support-compose-wrap"><div class="better-support-compose"><input type="text" id="better-support-input" placeholder="Type a message..." autocomplete="off">' +
                    (cfg.giphy_enabled ? '<button type="button" id="better-support-giphy" class="better-support-giphy" aria-label="Choose GIF" title="GIF">GIF</button>' : '') +
                    (cfg.support_images ? '<button type="button" id="better-support-attach" class="better-support-attach" aria-label="Attach screenshot" title="Attach screenshot">📎</button><input type="file" id="better-support-file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>' : '') +
                    '<button type="button" id="better-support-send" class="better-support-primary-action">Send</button></div><div id="better-support-upload-status" class="better-support-upload-status" hidden></div></div>';
                body.html(html);
                mountedSupportSessionId = sessionId;
            } else {
                $('#better-support-partner').text(partner);
            }

            const box = document.getElementById('better-support-messages');
            if (box) {
                const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 48;
                let messageMarkup = '';
                messages.forEach(function (m) { messageMarkup += messageHtml(m); });
                box.innerHTML = messageMarkup;
                if (nearBottom || !lastMessageId) box.scrollTop = box.scrollHeight;
            }

            applyConversationRestriction(data && data.restriction ? data.restriction : null);

            const latest = messages.length ? parseInt(messages[messages.length - 1].id_message || 0, 10) : 0;
            if (latest > lastMessageId && panel.prop('hidden')) setBadge(1);
            lastMessageId = Math.max(lastMessageId, latest);
        }

        function refresh() {
            if (refreshInFlight) return;
            refreshInFlight = true;
            getStatus().done(function (data) {
                render(data);
                try { if (typeof window.betterChatApplySupportThemeAuthority === 'function') window.betterChatApplySupportThemeAuthority(); } catch (e) {}
            }).always(function () {
                refreshInFlight = false;
                clearTimeout(pollTimer);
                pollTimer = setTimeout(refresh, panel.prop('hidden') ? 6000 : 3000);
            });
        }

        bubble.on('click', function () {
            restoreSupport(true);
        });
        $('#better-support-close').on('click', function (e) {
            e.stopPropagation();
            minimizeSupport();
        });
        $(document).on('click', '.better-support-header', function (e) {
            if ($(e.target).closest('button, a, input, select, textarea, label').length) return;
            minimizeSupport();
        });
        $(window).on('resize', positionSupportRoot);
        $(document).on('betterchat:groupstatechange', positionSupportRoot);

        $(document).on('change', '#better-support-duty', function () {
            post('duty', { on_duty: this.checked ? 1 : 0 }).always(refresh);
        });
        $(document).on('click', '#better-support-request', function () {
            post('request', { guest_name: $('#better-support-guest-name').val() || 'Guest' }).done(render).always(refresh);
        });
        $(document).on('click', '.better-support-queue-accept', function (e) {
            e.preventDefault();
            e.stopPropagation();
            post('claim', { id_support: $(this).data('id') }).done(render).always(refresh);
        });
        $(document).on('click', '#better-support-end, #better-support-cancel', function () {
            getStatus().done(function (data) {
                if (data.session) post('close', { id_support: data.session.id_support }).always(refresh);
            });
        });


        $(document).on('click', '#better-support-giphy', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!cfg.giphy_enabled || !cfg.giphy_api_key) return;
            const button = this;
            openSupportGiphy(button, function(payload) {
                const sessionId = mountedSupportSessionId;
                if (!sessionId || !payload || !payload.url || sendInFlight) return;
                sendInFlight = true;
                $('#better-support-send, #better-support-giphy').prop('disabled', true);
                const nonce = 'giphy-' + Date.now() + '-' + Math.random().toString(36).slice(2);
                post('send', { id_support: sessionId, message: '[img]' + payload.url + '[/img]', client_nonce: nonce })
                    .done(function(){ refresh(); })
                    .always(function(){ sendInFlight = false; $('#better-support-send, #better-support-giphy').prop('disabled', false); });
            });
        });

        $(document).on('click', '#better-support-attach', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const input = document.getElementById('better-support-file');
            if (input) input.click();
        });

        $(document).on('change', '#better-support-file', function () {
            const input = this;
            const file = input.files && input.files[0];
            const status = $('#better-support-upload-status');
            if (!file) return;
            if (!cfg.support_images) { input.value = ''; return; }
            if (!/^image\/(jpeg|png|gif|webp)$/i.test(file.type || '')) {
                status.removeClass('is-success').addClass('is-error').prop('hidden', false).text('Please choose a PNG, JPG, GIF, or WebP image.');
                input.value = '';
                return;
            }
            const maxBytes = Math.max(1, parseInt(cfg.max_image_kb || 5120, 10)) * 1024;
            if (file.size > maxBytes) {
                status.removeClass('is-success').addClass('is-error').prop('hidden', false).text('That screenshot exceeds the configured image size limit.');
                input.value = '';
                return;
            }

            getStatus().done(function (data) {
                if (!data.session || data.session.status !== 'active') return;
                const fd = new FormData();
                fd.append('support_action', 'upload');
                fd.append('id_support', String(data.session.id_support));
                fd.append('file', file);
                const sd = sessionData();
                Object.keys(sd).forEach(function (key) { fd.append(key, sd[key]); });

                status.removeClass('is-error is-success').prop('hidden', false).text('Uploading screenshot…');
                $('#better-support-attach').prop('disabled', true);
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                }).done(function (response) {
                    if (!response || response.status !== 'ok') {
                        status.addClass('is-error').text(response && response.message ? response.message : 'Screenshot upload failed.');
                        return;
                    }
                    status.addClass('is-success').text('Screenshot sent.');
                    input.value = '';
                    refresh();
                    window.setTimeout(function () { status.prop('hidden', true).removeClass('is-success is-error').text(''); }, 1200);
                }).fail(function (xhr) {
                    const message = xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Screenshot upload failed.';
                    status.addClass('is-error').text(message);
                }).always(function () {
                    $('#better-support-attach').prop('disabled', false);
                });
            });
        });

        function sendMessage() {
            if (sendInFlight) return;
            const input = $('#better-support-input');
            const message = String(input.val() || '').trim();
            if (!message) return;

            sendInFlight = true;
            const nonce = 'bc-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
            getStatus().done(function (data) {
                if (!data.session) return;
                post('send', { id_support: data.session.id_support, message: message, client_nonce: nonce }).done(function () {
                    const liveInput = $('#better-support-input');
                    if (String(liveInput.val() || '').trim() === message) liveInput.val('');
                    refresh();
                });
            }).always(function () {
                sendInFlight = false;
            });
        }
        $(document).on('click', '#better-support-send', sendMessage);
        $(document).on('keydown', '#better-support-input', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); sendMessage(); }
        });

        restoreSavedSupportWindowState();
        try { if (typeof window.betterChatApplySupportThemeAuthority === 'function') window.betterChatApplySupportThemeAuthority(); else root.css('visibility', 'visible'); } catch (e) { root.css('visibility', 'visible'); }
        refresh();
    }

    boot();
})();
