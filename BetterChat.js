/*
 * Better Chat theme bridge.
 * Samples the ACTIVE SMF theme rather than assuming Curve2/light colors.
 * This makes Better Chat automatically follow Theme Lab-published themes too.
 */
function betterChatThemeBridge() {
    const chatRoot = document.getElementById('smf-chat-bar');
    const supportRoot = document.getElementById('better-support-root');
    const root = chatRoot || supportRoot;
    if (!root) return;

    const docStyle = getComputedStyle(document.documentElement);
    const cssVar = (name) => {
        const value = docStyle.getPropertyValue(name);
        return value && value.trim() ? value.trim() : '';
    };

    const first = selectors => {
        for (const s of selectors) {
            const el = document.querySelector(s);
            if (el && !el.closest('#smf-chat-bar') && !el.closest('.private-chat-window') && !el.closest('#better-support-root')) return el;
        }
        return null;
    };
    const style = el => el ? getComputedStyle(el) : null;
    const val = (el, prop, fallback) => {
        const s = style(el);
        const v = s && s.getPropertyValue(prop);
        return v && v.trim() && v !== 'rgba(0, 0, 0, 0)' ? v.trim() : fallback;
    };
    const bgImage = el => {
        const s = style(el);
        const v = s && s.getPropertyValue('background-image');
        return v && v.trim() && v.trim() !== 'none' ? v.trim() : 'none';
    };
    const bgPaint = el => {
        const s = style(el);
        if (!s) return '';
        const color = s.getPropertyValue('background-color').trim();
        const image = s.getPropertyValue('background-image').trim();
        const position = s.getPropertyValue('background-position').trim();
        const size = s.getPropertyValue('background-size').trim();
        const repeat = s.getPropertyValue('background-repeat').trim();
        const attachment = s.getPropertyValue('background-attachment').trim();
        const origin = s.getPropertyValue('background-origin').trim();
        const clip = s.getPropertyValue('background-clip').trim();
        if (!color && (!image || image === 'none')) return '';
        let out = '';
        if (color) out += color + ' ';
        if (image && image !== 'none') {
            out += image;
            if (position) out += ' ' + position;
            if (size && size !== 'auto') out += ' / ' + size;
            if (repeat) out += ' ' + repeat;
            if (attachment) out += ' ' + attachment;
            if (origin) out += ' ' + origin;
            if (clip) out += ' ' + clip;
        }
        return out.trim();
    };

    /*
     * Test22: Theme Lab is authoritative when its generated variables exist.
     * Do not sample arbitrary first-page components for border/radius/colors:
     * those legitimately differ between Board Index, Admin, PM, Post, etc.
     */
    const hasThemeLab = !!(
        cssVar('--tl-surface') ||
        cssVar('--tl-surface-alt') ||
        cssVar('--tl-border') ||
        cssVar('--tl-primary')
    );

    let surfaceBg, altBg, headerBg, headerImage, textColor, mutedColor,
        linkColor, borderColor, inputBg, inputText, buttonBg, buttonImage,
        buttonText, headerFg, radius, headerPaint, buttonPaint, themeHeaderSource = null;

    if (hasThemeLab) {
        surfaceBg = cssVar('--tl-surface') || '#fff';
        altBg = cssVar('--tl-surface-alt') || surfaceBg;
        textColor = cssVar('--tl-text') || '#222';
        mutedColor = cssVar('--tl-muted-text') || cssVar('--tl-muted_text') || textColor;
        linkColor = cssVar('--tl-link') || cssVar('--tl-primary') || '#346';
        borderColor = cssVar('--tl-border') || '#bbb';
        inputBg = cssVar('--tl-input') || cssVar('--tl-surface-alt') || surfaceBg;
        inputText = cssVar('--tl-input-text') || textColor;

        /*
         * Test22: Theme Lab normalizes .cat_bar globally in the generated
         * stylesheet. It is therefore safe to use ONE stable cat_bar solely
         * for the header's visual paint (gradient/image/text), while keeping
         * surfaces, borders and inputs on deterministic Theme Lab variables.
         */
        const themeLabHeader = first([
            '#boardindex_table .cat_bar',
            '#main_content_section > .cat_bar',
            '#main_content_section .cat_bar',
            '#wrapper .cat_bar',
            '.cat_bar'
        ]);
        themeHeaderSource = themeLabHeader;
        const themeLabHeaderText = themeLabHeader
            && (themeLabHeader.querySelector('.catbg, h3, h4, .titlebg') || themeLabHeader);

        headerBg = val(themeLabHeader, 'background-color',
            cssVar('--tl-primary') || cssVar('--tl-accent') || '#557ea0');
        headerImage = bgImage(themeLabHeader);
        headerPaint = bgPaint(themeLabHeader);
        headerFg = val(themeLabHeaderText, 'color',
            cssVar('--tl-board-index-header-text') || cssVar('--tl-header-text') || '#fff');

        buttonBg = cssVar('--tl-button-background') || cssVar('--tl-primary') || headerBg;
        buttonImage = 'none';
        buttonPaint = '';
        buttonText = cssVar('--tl-button-text') || headerFg;

        radius = cssVar('--tl-radius') || cssVar('--tl-border-radius');
        if (!radius)
            radius = val(themeLabHeader, 'border-top-left-radius', '6px');
    } else {
        /*
         * Non-Theme-Lab fallback: sample stable structural elements in a fixed
         * priority order, never whichever .windowbg happens to appear first.
         */
        const body = first(['body']);
        const surface = first([
            '#main_content_section > .roundframe',
            '#main_content_section > .windowbg',
            '#main_content_section .windowbg',
            '#wrapper .windowbg'
        ]);
        const alt = first([
            '#main_content_section > .information',
            '#main_content_section .windowbg2',
            '#main_content_section .information',
            '#wrapper .windowbg2'
        ]);
        const header = first([
            '#main_content_section > .cat_bar',
            '#main_content_section .cat_bar',
            '#wrapper .cat_bar',
            '.cat_bar'
        ]);
        themeHeaderSource = header;
        const headerText = header && header.querySelector('h3, h4, .catbg, .titlebg') || header;
        const link = first(['#main_content_section a', '#wrapper a', 'a']);
        const input = first(['#main_content_section input[type="text"]', '#main_content_section input[type="search"]', '#main_content_section select', 'input[type="text"]']);
        const button = first(['#main_content_section .button', '#main_content_section .button_submit', '.button', 'input[type="submit"]']);

        surfaceBg = val(surface, 'background-color', val(body, 'background-color', '#fff'));
        altBg = val(alt, 'background-color', surfaceBg);
        headerBg = val(header, 'background-color', val(button, 'background-color', '#557ea0'));
        headerImage = bgImage(header);
        headerPaint = bgPaint(header);
        textColor = val(surface, 'color', val(body, 'color', '#222'));
        mutedColor = val(alt, 'color', textColor);
        linkColor = val(link, 'color', '#346');
        borderColor = val(surface, 'border-top-color', val(input, 'border-top-color', '#bbb'));
        inputBg = val(input, 'background-color', surfaceBg);
        inputText = val(input, 'color', textColor);
        buttonBg = val(button, 'background-color', headerBg);
        buttonImage = bgImage(button);
        buttonPaint = bgPaint(button);
        buttonText = val(button, 'color', val(headerText, 'color', '#fff'));
        headerFg = val(headerText, 'color', buttonText);
        radius = val(header, 'border-top-left-radius', val(surface, 'border-radius', val(input, 'border-radius', '6px')));
    }

    const vars = {
        '--better-chat-surface': surfaceBg,
        '--better-chat-surface-alt': altBg,
        '--better-chat-header': headerBg,
        '--better-chat-header-image': headerImage,
        '--better-chat-header-background': headerPaint || '',
        '--better-chat-header-text': headerFg,
        '--better-chat-text': textColor,
        '--better-chat-muted': mutedColor,
        '--better-chat-link': linkColor,
        '--better-chat-border': borderColor,
        '--better-chat-input': inputBg,
        '--better-chat-input-text': inputText,
        '--better-chat-button': buttonBg,
        '--better-chat-button-image': buttonImage,
        '--better-chat-button-background': buttonPaint || '',
        '--better-chat-button-text': buttonText,
        '--better-chat-accent': linkColor,
        '--better-chat-radius': radius
    };

    for (const [k, v] of Object.entries(vars)) {
        document.documentElement.style.setProperty(k, v);
    }

    [chatRoot, supportRoot].filter(Boolean).forEach(function(target) {
        target.style.setProperty('--bc-surface', surfaceBg);
        target.style.setProperty('--bc-surface-alt', altBg);
        target.style.setProperty('--bc-header', headerBg);
        target.style.setProperty('--bc-header-image', headerImage);
        target.style.setProperty('--bc-header-background', headerPaint || '');
        target.style.setProperty('--bc-header-text', headerFg);
        target.style.setProperty('--bc-text', textColor);
        target.style.setProperty('--bc-muted', mutedColor);
        target.style.setProperty('--bc-link', linkColor);
        target.style.setProperty('--bc-border', borderColor);
        target.style.setProperty('--bc-input', inputBg);
        target.style.setProperty('--bc-input-text', inputText);
        target.style.setProperty('--bc-button', buttonBg);
        target.style.setProperty('--bc-button-image', buttonImage);
        target.style.setProperty('--bc-button-background', buttonPaint || '');
        target.style.setProperty('--bc-button-text', buttonText);
        target.style.setProperty('--bc-accent', linkColor);
        target.style.setProperty('--bc-radius', radius);
    });

    /*
     * test31f: the floating Support header must use the live theme header's
     * actual computed paint. Theme Lab can build layered gradients/images that
     * are lossy when reconstructed through a shorthand CSS variable.
     */
    if (supportRoot && themeHeaderSource) {
        const supportHeader = supportRoot.querySelector('.better-support-header');
        const sourceStyle = getComputedStyle(themeHeaderSource);
        const sourceText = themeHeaderSource.querySelector('.catbg, h3, h4, .titlebg') || themeHeaderSource;
        const sourceTextStyle = getComputedStyle(sourceText);
        if (supportHeader) {
            ['background-color', 'background-image', 'background-position', 'background-size',
             'background-repeat', 'background-attachment', 'background-origin', 'background-clip']
                .forEach(function(prop) {
                    const value = sourceStyle.getPropertyValue(prop);
                    if (value && value.trim()) supportHeader.style.setProperty(prop, value.trim(), 'important');
                });
            const fg = sourceTextStyle.getPropertyValue('color');
            if (fg && fg.trim()) supportHeader.style.setProperty('color', fg.trim(), 'important');
        }
    }

    document.documentElement.dataset.betterChatThemeSource = hasThemeLab ? 'themelab' : 'sampled';
    return hasThemeLab;
}



/*
 * test31g: Support theme authority.
 * No palette guessing: copy the browser's computed paint from the live SMF
 * theme controls into the Support UI.
 */
function betterChatApplySupportThemeAuthority() {
    const support = document.getElementById('better-support-root');
    if (!support) return;

    const outside = (selector) => Array.from(document.querySelectorAll(selector)).find(el =>
        !el.closest('#smf-chat-bar') && !el.closest('.private-chat-window') && !el.closest('#better-support-root') && !el.closest('#better-chat-theme-probe')
    );

    // test31h: create deterministic, invisible SMF theme probes. This avoids
    // depending on whether the current page happens to contain windowbg2,
    // information, a themed text input, etc. The browser computes the active
    // theme against the real SMF classes, and Support copies those exact values.
    function ensureThemeProbe() {
        let probe = document.getElementById('better-chat-theme-probe');
        if (probe) return probe;

        probe = document.createElement('div');
        probe.id = 'better-chat-theme-probe';
        probe.setAttribute('aria-hidden', 'true');
        probe.style.cssText = 'position:fixed!important;left:-10000px!important;top:-10000px!important;width:320px!important;height:1px!important;overflow:hidden!important;visibility:hidden!important;pointer-events:none!important;z-index:-1!important;';
        probe.innerHTML =
            '<div class="cat_bar"><h3 class="catbg">Theme Probe</h3></div>' +
            '<div class="windowbg" data-bc-probe="surface">Surface</div>' +
            '<div class="windowbg2" data-bc-probe="alt">Alternate</div>' +
            '<div class="information" data-bc-probe="information">Information</div>' +
            '<input class="input_text" data-bc-probe="input" type="text" value="Theme Probe">' +
            '<button class="button" data-bc-probe="button" type="button">Theme Probe</button>';

        const host = document.getElementById('main_content_section') || document.getElementById('wrapper') || document.body;
        host.appendChild(probe);
        return probe;
    }

    const probe = ensureThemeProbe();
    const probeFind = selector => probe ? probe.querySelector(selector) : null;
    const copy = (source, targets, props) => {
        if (!source) return;
        const cs = getComputedStyle(source);
        targets.filter(Boolean).forEach(target => {
            props.forEach(prop => {
                const value = cs.getPropertyValue(prop);
                if (value && value.trim()) target.style.setProperty(prop, value.trim(), 'important');
            });
        });
    };

    const paintProps = [
        'background-color','background-image','background-position','background-size','background-repeat',
        'background-attachment','background-origin','background-clip','color','border-top-color','border-right-color',
        'border-bottom-color','border-left-color','border-top-style','border-right-style','border-bottom-style','border-left-style',
        'border-top-width','border-right-width','border-bottom-width','border-left-width'
    ];
    const boxProps = [
        'border-top-left-radius','border-top-right-radius','border-bottom-right-radius','border-bottom-left-radius','box-shadow'
    ];

    const headerSource = outside('.cat_bar') || probeFind('.cat_bar');
    const headerTextSource = headerSource && (headerSource.querySelector('.catbg, h3, h4, .titlebg') || headerSource);
    const surfaceSource = outside('.windowbg') || outside('.roundframe') || probeFind('[data-bc-probe="surface"]');
    const altSource = outside('.windowbg2') || outside('.information') || probeFind('[data-bc-probe="alt"]') || probeFind('[data-bc-probe="information"]') || surfaceSource;
    const buttonSource = outside('.button') || outside('input[type="submit"]') || probeFind('[data-bc-probe="button"]');
    const inputSource = outside('input.input_text') || outside('input[type="text"]') || outside('input[type="search"]') || probeFind('[data-bc-probe="input"]');

    const header = support.querySelector('.better-support-header');
    const headerButton = support.querySelector('#better-support-close');
    const panel = support.querySelector('.better-support-panel');
    const body = support.querySelector('.better-support-body');
    const messages = support.querySelector('.better-support-messages');

    copy(headerSource, [header], paintProps.concat(boxProps));
    copy(headerTextSource, [header, headerButton], ['color','text-shadow']);

    // Main support surfaces use the exact computed SMF window surface.
    copy(surfaceSource, [panel, body, messages], paintProps.concat(boxProps));

    // Secondary rows use the exact alternate/information surface, including
    // gradients/images if the active theme gives those classes any.
    support.querySelectorAll('.better-support-staffbar,.better-support-section-title,.better-support-conversation-head,.better-support-compose').forEach(el =>
        copy(altSource, [el], paintProps)
    );

    // Queue cards and incoming messages follow normal window content.
    support.querySelectorAll('.better-support-queue-item,.better-support-msg.is-theirs .better-support-msg-body,.better-support-welcome,.better-support-waiting,.better-support-empty').forEach(el =>
        copy(surfaceSource, [el], ['background-color','background-image','background-position','background-size','background-repeat','color','border-color'])
    );

    // Secondary/support utility controls may follow the live theme's normal button paint.
    // End Chat and Send are deliberately excluded: test31k gives those two actions
    // explicit PRIMARY authority from the active Theme Lab palette, not Curve2's .button.
    support.querySelectorAll('.button,.better-support-compose button:not(.better-support-primary-action),.better-support-queue-accept,#better-support-cancel,#better-support-request,.better-support-msg.is-mine .better-support-msg-body').forEach(el =>
        copy(buttonSource, [el], [
            'background-color','background-image','background-position','background-size','background-repeat',
            'color','border-top-color','border-right-color','border-bottom-color','border-left-color',
            'border-top-style','border-right-style','border-bottom-style','border-left-style',
            'border-top-width','border-right-width','border-bottom-width','border-left-width',
            'border-top-left-radius','border-top-right-radius','border-bottom-right-radius','border-bottom-left-radius','box-shadow','text-shadow'
        ])
    );

    // test31k: true primary actions. Theme Lab publishes the current theme palette as
    // CSS custom properties, so use those values directly. This avoids inheriting
    // Curve2's secondary/default .button paint when a generated theme is active.
    const themeRoot = getComputedStyle(document.documentElement);
    const themeBody = document.body ? getComputedStyle(document.body) : null;
    const themeValue = name => {
        const fromRoot = themeRoot.getPropertyValue(name).trim();
        if (fromRoot) return fromRoot;
        return themeBody ? themeBody.getPropertyValue(name).trim() : '';
    };
    const primary = themeValue('--tl-primary');
    const primaryText = themeValue('--tl-button-text') || themeValue('--tl-header-text');
    const primaryHover = themeValue('--tl-accent') || primary;
    const primaryRadius = themeValue('--tl-radius') || themeValue('--tl-border-radius');

    support.querySelectorAll('.better-support-primary-action').forEach(el => {
        // First copy only geometry/type/shadow from a real theme button when available.
        // Paint is then replaced by the current Theme Lab PRIMARY palette values.
        copy(buttonSource, [el], [
            'font-family','font-size','font-weight','line-height','padding-top','padding-right','padding-bottom','padding-left',
            'border-top-style','border-right-style','border-bottom-style','border-left-style',
            'border-top-width','border-right-width','border-bottom-width','border-left-width','box-shadow','text-shadow'
        ]);
        if (primary) {
            el.style.setProperty('background', primary, 'important');
            el.style.setProperty('background-image', 'none', 'important');
            el.style.setProperty('border-color', primary, 'important');
        }
        if (primaryText) el.style.setProperty('color', primaryText, 'important');
        if (primaryRadius) el.style.setProperty('border-radius', primaryRadius, 'important');
        if (primaryHover) el.style.setProperty('--better-support-primary-hover', primaryHover);
    });

    support.querySelectorAll('.better-support-compose input,.better-support-guest-name input').forEach(el =>
        copy(inputSource, [el], [
            'background-color','background-image','color','border-top-color','border-right-color','border-bottom-color','border-left-color',
            'border-top-style','border-right-style','border-bottom-style','border-left-style',
            'border-top-width','border-right-width','border-bottom-width','border-left-width',
            'border-top-left-radius','border-top-right-radius','border-bottom-right-radius','border-bottom-left-radius','box-shadow'
        ])
    );

    support.classList.add('better-support-ready');
    support.style.visibility = 'visible';
}
/*
 * Test22: synchronize Better Chat with late-arriving active-theme CSS.
 * Theme Lab's generated stylesheet can finish after Better Chat's script begins
 * on some SMF pages. Re-sample at controlled checkpoints instead of locking in
 * the fallback palette until the next full refresh.
 */
function betterChatSyncTheme(options) {
    options = options || {};
    const root = document.getElementById('smf-chat-bar') || document.getElementById('better-support-root');
    if (!root) return Promise.resolve(false);

    const waitForThemeLab = options.waitForThemeLab !== false;
    const maxWait = Number.isFinite(options.maxWait) ? options.maxWait : 1800;
    const started = Date.now();

    return new Promise(resolve => {
        const apply = () => {
            const hasThemeLab = !!betterChatThemeBridge();

            // If Theme Lab is present, we have the authoritative palette.
            if (hasThemeLab) {
                resolve(true);
                return;
            }

            // For non-Theme-Lab themes, or once the readiness window expires,
            // keep the stable sampled fallback from Test20/21.
            if (!waitForThemeLab || (Date.now() - started) >= maxWait) {
                resolve(false);
                return;
            }

            window.setTimeout(apply, 60);
        };

        apply();
    });
}

function betterChatInstallThemeResync() {
    if (window.__betterChatThemeResyncInstalled) return;
    window.__betterChatThemeResyncInstalled = true;

    const resync = () => {
        if (!document.getElementById('smf-chat-bar') && !document.getElementById('better-support-root')) return;
        betterChatThemeBridge();
    };

    // Browser load guarantees all ordinary linked stylesheets have completed.
    if (document.readyState === 'complete') {
        window.requestAnimationFrame(() => {
            resync();
            window.setTimeout(resync, 80);
            window.setTimeout(resync, 250);
        });
    } else {
        window.addEventListener('load', function() {
            resync();
            window.requestAnimationFrame(resync);
            window.setTimeout(resync, 80);
            window.setTimeout(resync, 250);
        }, { once: true });
    }

    // Catch dynamically-added/late stylesheet links as well.
    document.querySelectorAll('link[rel~="stylesheet"]').forEach(link => {
        if (link.dataset.betterChatWatched) return;
        link.dataset.betterChatWatched = '1';
        link.addEventListener('load', resync, { once: true });
    });

    // One final post-load safety sample for very slow/dynamic theme injection.
    window.setTimeout(resync, 700);
    window.setTimeout(resync, 1500);
}

var lastMsgId = localStorage.getItem('chat_last_msg_id') || 0;
var closedWindows = JSON.parse(localStorage.getItem('chat_closed_windows')) || [];
var lastSeenPrivateIds = JSON.parse(localStorage.getItem('chat_last_seen_private')) || {};

function startChatLogic() {
    if (typeof chat_script_url === 'undefined') return;
    if (window.__betterChatStarted) return;
    window.__betterChatStarted = true;

    // Defensive cleanup for pages where an older package left duplicate hooks behind.
    const duplicateBars = document.querySelectorAll('#smf-chat-bar');
    duplicateBars.forEach((bar, index) => {
        if (index > 0) bar.remove();
    });

    const chatRoot = $('#smf-chat-bar');
    const fullPageMode = chatRoot.hasClass('better-chat-full-page') || window.better_chat_full_page === true;

    // test34a: a full-page chat owns the Community Chat canvas. If an older
    // asset injection or browser cache left floating Community/Support bubbles
    // in the DOM, remove them so the pop-out page cannot show duplicate chat UI.
    if (fullPageMode) {
        $('#better-chat-group-bubble, #better-support-root, #better-support-bubble').remove();
    }

    // Apply persisted visibility state while the entire widget is still hidden.
    // In Messenger mode a minimized group chat is represented by its own
    // primary chat bubble instead of leaving a collapsed header on screen.
    const groupStartsMinimized = !fullPageMode && localStorage.getItem('chat_minimized') === 'true';
    if (groupStartsMinimized) {
        $('#chat-content').prop('hidden', true).hide();
        $('#chat-toggle')
            .text('+')
            .attr('aria-label', 'Maximize chat')
            .attr('title', 'Maximize chat');

        if (String(window.better_chat_private_minimize_style || 'bubbles') === 'bubbles') {
            chatRoot.hide();
            betterChatShowGroupBubble();
        }
    } else {
        $('#chat-toggle')
            .text('−')
            .attr('aria-label', 'Minimize chat')
            .attr('title', 'Minimize chat');
    }

    betterChatUpdateSoundButtons();
    betterChatInstallThemeResync();

    /*
     * Test22: keep the widget hidden while Theme Lab gets a brief chance to
     * expose its generated variables. This prevents the generic-blue fallback
     * from becoming the visible first paint on slower pages.
     */
    betterChatSyncTheme({ waitForThemeLab: true, maxWait: 1800 }).then(function() {
        requestAnimationFrame(function() {
            chatRoot
                .attr('aria-busy', 'false')
                .css({
                    visibility: 'visible',
                    opacity: '1',
                    pointerEvents: 'auto'
                });
        });
    });

    const chatUrl = chat_script_url + '?action=chat_msg';


    // --- STAGE 3A: FULL-PAGE CONVERSATION HUB ---
    const betterChatHubUnread = {};
    const betterChatHubLastCounted = JSON.parse(localStorage.getItem('better_chat_hub_last_counted') || '{}');
    let betterChatHubActive = 'public:0';

    function betterChatHubKey(kind, id) {
        return String(kind || 'public') + ':' + parseInt(id || 0, 10);
    }

    function betterChatHubIsActive(kind, id) {
        return fullPageMode && betterChatHubActive === betterChatHubKey(kind, id);
    }

    function betterChatHubTab(kind, id) {
        const key = betterChatHubKey(kind, id);
        return $('#better-chat-hub-tab-list .better-chat-hub-tab').filter(function() {
            return betterChatHubKey($(this).attr('data-hub-kind'), $(this).attr('data-hub-id')) === key;
        }).first();
    }

    function betterChatHubRefreshMore() {
        if (!fullPageMode) return;
        const tabs = $('#better-chat-hub-tab-list .better-chat-hub-tab');
        const wrap = $('.better-chat-hub-more-wrap');
        const menu = $('.better-chat-hub-more-menu');
        tabs.removeClass('is-overflow').show();
        menu.empty().prop('hidden', true);
        wrap.prop('hidden', true);

        if (window.matchMedia && window.matchMedia('(max-width: 700px)').matches) return;
        if (tabs.length <= 5) return;

        const keep = new Set();
        const publicTab = tabs.filter('[data-hub-kind="public"]').first()[0];
        if (publicTab) keep.add(publicTab);
        const activeTab = tabs.filter('.is-active').first()[0];
        if (activeTab) keep.add(activeTab);
        tabs.each(function() {
            if (keep.size < 5) keep.add(this);
        });

        let hiddenUnread = 0;
        tabs.each(function() {
            if (keep.has(this)) return;
            const tab = $(this).addClass('is-overflow').hide();
            const kind = tab.attr('data-hub-kind');
            const id = parseInt(tab.attr('data-hub-id') || 0, 10);
            const label = tab.find('.better-chat-hub-tab-label').text() || 'Chat';
            const count = parseInt(betterChatHubUnread[betterChatHubKey(kind, id)] || 0, 10);
            hiddenUnread += count;
            menu.append(`<button type="button" class="better-chat-hub-more-item" data-hub-kind="${betterChatEscape(kind)}" data-hub-id="${id}"><span>${betterChatEscape(label)}</span>${count ? `<span class="better-chat-hub-tab-badge">${count > 99 ? '99+' : count}</span>` : ''}</button>`);
        });
        wrap.prop('hidden', menu.children().length === 0);
        const moreBadge = $('.better-chat-hub-more-badge');
        moreBadge.text(hiddenUnread > 99 ? '99+' : String(hiddenUnread)).prop('hidden', hiddenUnread === 0);
    }

    function betterChatHubEnsureTab(kind, id, title) {
        if (!fullPageMode) return $();
        id = parseInt(id || 0, 10);
        let tab = betterChatHubTab(kind, id);
        const safeTitle = String(title || (kind === 'public' ? 'Public Chat' : 'Chat'));
        if (!tab.length) {
            const marker = kind === 'group' ? '<span class="better-chat-hub-group-mark" aria-hidden="true">●●</span>' : (kind === 'direct' ? '<span class="online-dot" aria-hidden="true"></span>' : '');
            tab = $(`<button type="button" class="better-chat-hub-tab" data-hub-kind="${betterChatEscape(kind)}" data-hub-id="${id}" role="tab" aria-selected="false">${marker}<span class="better-chat-hub-tab-label"></span><span class="better-chat-hub-tab-badge" hidden>0</span></button>`);
            $('#better-chat-hub-tab-list').append(tab);
        }
        tab.find('.better-chat-hub-tab-label').text(safeTitle).attr('title', safeTitle);
        betterChatHubRefreshMore();
        return tab;
    }

    function betterChatHubSetUnread(kind, id, count) {
        if (!fullPageMode) return;
        const key = betterChatHubKey(kind, id);
        count = Math.max(0, parseInt(count || 0, 10));
        betterChatHubUnread[key] = count;
        const tab = betterChatHubTab(kind, id);
        const badge = tab.find('.better-chat-hub-tab-badge');
        badge.text(count > 99 ? '99+' : String(count)).prop('hidden', count === 0);
        tab.toggleClass('has-unread', count > 0);
        betterChatHubRefreshMore();
    }

    function betterChatHubTrackUnread(kind, id, messages, latestId) {
        if (!fullPageMode) return;
        const key = betterChatHubKey(kind, id);
        latestId = parseInt(latestId || 0, 10);
        if (!latestId) return;
        const previous = parseInt(betterChatHubLastCounted[key] || 0, 10);
        if (!previous && kind !== 'direct') {
            betterChatHubLastCounted[key] = latestId;
            localStorage.setItem('better_chat_hub_last_counted', JSON.stringify(betterChatHubLastCounted));
            return;
        }
        if (previous && latestId <= previous) return;

        if (betterChatHubIsActive(kind, id)) {
            betterChatHubSetUnread(kind, id, 0);
        } else {
            const unseen = (messages || []).filter(function(m) {
                const mid = parseInt(m.id_chat || 0, 10);
                const from = parseInt(m.id_member_from || 0, 10);
                if (mid <= previous || from === parseInt(smf_member_id, 10) || from === 0) return false;
                if (kind === 'direct')
                    return parseInt(m.id_member_to || 0, 10) === parseInt(smf_member_id, 10) && parseInt(m.is_read || 0, 10) === 0;
                return true;
            }).length;
            if (unseen > 0)
                betterChatHubSetUnread(kind, id, parseInt(betterChatHubUnread[key] || 0, 10) + unseen);
        }
        betterChatHubLastCounted[key] = latestId;
        localStorage.setItem('better_chat_hub_last_counted', JSON.stringify(betterChatHubLastCounted));
    }

    function betterChatHubActivate(kind, id, settleScroll) {
        if (!fullPageMode) return;
        id = parseInt(id || 0, 10);
        const key = betterChatHubKey(kind, id);
        betterChatHubActive = key;
        localStorage.setItem('better_chat_hub_active', key);

        $('#better-chat-hub-tab-list .better-chat-hub-tab').removeClass('is-active').attr('aria-selected', 'false');
        betterChatHubTab(kind, id).addClass('is-active').attr('aria-selected', 'true');
        betterChatHubSetUnread(kind, id, 0);

        $('#better-chat-hub-private-stage .private-chat-window').hide();
        if (kind === 'public') {
            $('#better-chat-hub-private-stage').prop('hidden', true);
            $('#chat-content').prop('hidden', false).show();
            const body = $('#chat-body');
            if (settleScroll !== false) betterChatScrollBodyToBottom(body, true);
        } else {
            $('#chat-content').prop('hidden', true).hide();
            $('#better-chat-hub-private-stage').prop('hidden', false);
            const win = kind === 'group' ? $('#pg-' + id) : $('#pc-' + id);
            win.attr('data-bubble-minimized', '0').show();
            if (kind === 'group') {
                $('#pg-body-' + id + ', #pg-ft-' + id + ', #pg-' + id + ' .better-chat-group-participants').show();
                fetchPrivateGroup(id).always(function() {
                    if (settleScroll !== false) betterChatScrollBodyToBottom($('#pg-body-' + id), true);
                });
            } else {
                $('#pc-body-' + id + ', #pc-ft-' + id).show();
                fetchPrivateChat(id).always(function() {
                    if (settleScroll !== false) betterChatScrollBodyToBottom($('#pc-body-' + id), true);
                });
            }
        }
        $('.better-chat-hub-more').attr('aria-expanded', 'false');
        $('.better-chat-hub-more-menu').prop('hidden', true);
        betterChatHubRefreshMore();
    }

    $(document).on('click', '.better-chat-hub-tab, .better-chat-hub-more-item', function(e) {
        if (!fullPageMode) return;
        e.preventDefault();
        betterChatHubActivate($(this).attr('data-hub-kind'), $(this).attr('data-hub-id'), true);
    });

    $(document).on('click', '.better-chat-hub-more', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const menu = $('.better-chat-hub-more-menu');
        const open = menu.prop('hidden');
        menu.prop('hidden', !open);
        $(this).attr('aria-expanded', open ? 'true' : 'false');
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.better-chat-hub-more-wrap').length) {
            $('.better-chat-hub-more').attr('aria-expanded', 'false');
            $('.better-chat-hub-more-menu').prop('hidden', true);
        }
    });


    // --- BETTER REACTIONS INTEGRATION (test24) ---
    const chatReactionsEnabled = !!(window.better_chat_reactions && better_chat_reactions.enabled && Array.isArray(better_chat_reactions.types));
    const chatReactionTypes = chatReactionsEnabled ? better_chat_reactions.types : [];
    const chatReactionDisplay = chatReactionsEnabled ? (better_chat_reactions.display || {}) : {};
    let betterChatReactionTray = null;
    let betterChatWhoPopover = null;
    let betterChatReactionCloseTimer = null;

    function betterChatEscape(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function betterChatReactionType(key) {
        return chatReactionTypes.find(type => String(type.key) === String(key)) || null;
    }

    function betterChatReactionIcon(type) {
        if (!type) return '<span class="better-chat-reaction-glyph">✨</span>';
        if (type.image)
            return '<img class="better-chat-reaction-image" src="' + betterChatEscape(type.image) + '" alt="">';
        return '<span class="better-chat-reaction-glyph">' + betterChatEscape(type.glyph || '✨') + '</span>';
    }

    function betterChatReactionSummaryHtml(summary) {
        if (!chatReactionsEnabled || chatReactionDisplay.showCounts === false) return '';
        const counts = summary && summary.counts ? summary.counts : {};
        return chatReactionTypes.map(type => {
            const count = parseInt(counts[type.key] || 0, 10);
            if (!count) return '';
            return '<button type="button" class="better-chat-reaction-count" data-reaction="' + betterChatEscape(type.key) + '" title="See who reacted with ' + betterChatEscape(type.label || type.key) + '">' + betterChatReactionIcon(type) + '<span>' + count + '</span></button>';
        }).join('');
    }

    function betterChatReactionRowHtml(messageId, summary) {
        if (!chatReactionsEnabled) return '';
        const mine = summary && summary.mine ? String(summary.mine) : '';
        const like = betterChatReactionType('like') || chatReactionTypes[0];
        const label = mine === 'like' ? 'Unlike' : 'Like';
        return '<div class="better-chat-reactions" data-message-id="' + parseInt(messageId, 10) + '">' +
            '<button type="button" class="better-chat-reaction-trigger" aria-pressed="' + (mine === 'like' ? 'true' : 'false') + '" title="' + label + '">' +
                betterChatReactionIcon(like) + '<span class="better-chat-reaction-like-text">' + label + '</span>' +
            '</button>' +
            '<div class="better-chat-reaction-summary">' + betterChatReactionSummaryHtml(summary) + '</div>' +
        '</div>';
    }

    function betterChatRenderReactionRow(row, summary) {
        if (!row || !chatReactionsEnabled) return;
        const mine = summary && summary.mine ? String(summary.mine) : '';
        const like = betterChatReactionType('like') || chatReactionTypes[0];
        const trigger = row.querySelector('.better-chat-reaction-trigger');
        if (trigger) {
            const label = mine === 'like' ? 'Unlike' : 'Like';
            trigger.innerHTML = betterChatReactionIcon(like) + '<span class="better-chat-reaction-like-text">' + label + '</span>';
            trigger.setAttribute('aria-pressed', mine === 'like' ? 'true' : 'false');
            trigger.title = label;
        }
        const host = row.querySelector('.better-chat-reaction-summary');
        if (host) host.innerHTML = betterChatReactionSummaryHtml(summary);
        row.dataset.mine = mine;
    }

    function betterChatReactionPost(messageId, reaction) {
        const payload = {
            reaction_action: 'react',
            message_id: parseInt(messageId, 10),
            reaction: reaction
        };
        if (typeof better_chat_session_var !== 'undefined' && better_chat_session_var)
            payload[better_chat_session_var] = better_chat_session_id;
        return $.post(chatUrl, payload);
    }

    function betterChatCloseReactionTray() {
        if (betterChatReactionCloseTimer) {
            clearTimeout(betterChatReactionCloseTimer);
            betterChatReactionCloseTimer = null;
        }
        if (betterChatReactionTray) {
            betterChatReactionTray.remove();
            betterChatReactionTray = null;
        }
    }

    function betterChatCloseWho() {
        if (betterChatWhoPopover) {
            betterChatWhoPopover.remove();
            betterChatWhoPopover = null;
        }
    }

    function betterChatPositionFloating(node, anchor) {
        const rect = anchor.getBoundingClientRect();
        const box = node.getBoundingClientRect();
        let left = rect.left + (rect.width / 2) - (box.width / 2);
        left = Math.max(8, Math.min(left, window.innerWidth - box.width - 8));
        let top = rect.top - box.height - 7;
        if (top < 8) top = Math.min(window.innerHeight - box.height - 8, rect.bottom + 7);
        node.style.left = Math.round(left) + 'px';
        node.style.top = Math.round(top) + 'px';
    }

    function betterChatOpenReactionTray(trigger) {
        if (!chatReactionsEnabled || !trigger) return;
        betterChatCloseReactionTray();
        betterChatCloseWho();
        const row = trigger.closest('.better-chat-reactions');
        if (!row) return;
        const messageId = parseInt(row.dataset.messageId, 10);
        const tray = document.createElement('div');
        tray.className = 'better-chat-reaction-tray';
        tray.setAttribute('role', 'menu');
        chatReactionTypes.forEach(type => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'better-chat-reaction-choice';
            button.dataset.reaction = type.key;
            button.title = type.label || type.key;
            button.setAttribute('aria-label', type.label || type.key);
            button.innerHTML = (chatReactionDisplay.showLabels === false ? '' : '<span class="better-chat-reaction-choice-label">' + betterChatEscape(type.label || type.key) + '</span>') + betterChatReactionIcon(type);
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                betterChatReactionPost(messageId, type.key).done(function(data) {
                    if (data && data.status === 'ok') betterChatRenderReactionRow(row, data.summary || {counts:{}, mine:''});
                });
                betterChatCloseReactionTray();
            });
            tray.appendChild(button);
        });
        document.body.appendChild(tray);
        betterChatReactionTray = tray;
        betterChatPositionFloating(tray, trigger);
        tray.addEventListener('mouseenter', function() {
            if (betterChatReactionCloseTimer) clearTimeout(betterChatReactionCloseTimer);
        });
        tray.addEventListener('mouseleave', function() {
            betterChatReactionCloseTimer = setTimeout(betterChatCloseReactionTray, 180);
        });
    }

    function betterChatOpenWho(button) {
        if (!button || chatReactionDisplay.showWho === false) return;
        const row = button.closest('.better-chat-reactions');
        if (!row) return;
        const messageId = parseInt(row.dataset.messageId, 10);
        const reaction = button.dataset.reaction || '';
        const type = betterChatReactionType(reaction);
        if (!messageId || !reaction) return;

        const payload = { reaction_action: 'reactors', message_id: messageId, reaction: reaction };
        if (typeof better_chat_session_var !== 'undefined' && better_chat_session_var)
            payload[better_chat_session_var] = better_chat_session_id;

        $.post(chatUrl, payload).done(function(data) {
            if (!data || data.status !== 'ok' || !data.reactors) return;
            betterChatCloseWho();
            betterChatCloseReactionTray();
            const pop = document.createElement('div');
            pop.className = 'better-chat-who-reacted';
            const header = document.createElement('div');
            header.className = 'better-chat-who-header';
            header.innerHTML = betterChatReactionIcon(type) + '<strong>' + betterChatEscape(type ? type.label : reaction) + '</strong>';
            pop.appendChild(header);
            const list = document.createElement('div');
            list.className = 'better-chat-who-list';
            (data.reactors.people || []).forEach(person => {
                const item = document.createElement(person.url ? 'a' : 'div');
                item.className = 'better-chat-who-person';
                item.textContent = person.name || 'Former member';
                if (person.url) item.href = person.url;
                list.appendChild(item);
            });
            const total = parseInt(data.reactors.total || 0, 10);
            if (total > (data.reactors.people || []).length) {
                const more = document.createElement('div');
                more.className = 'better-chat-who-more';
                more.textContent = 'and ' + (total - data.reactors.people.length) + ' more';
                list.appendChild(more);
            }
            pop.appendChild(list);
            document.body.appendChild(pop);
            betterChatWhoPopover = pop;
            betterChatPositionFloating(pop, button);
        });
    }

    if (chatReactionsEnabled && !window.__betterChatReactionsWired) {
        window.__betterChatReactionsWired = true;
        $(document).on('mouseenter', '.better-chat-reaction-trigger', function() {
            if (window.matchMedia && window.matchMedia('(hover: hover)').matches)
                betterChatOpenReactionTray(this);
        });
        $(document).on('mouseleave', '.better-chat-reaction-trigger', function() {
            if (betterChatReactionCloseTimer) clearTimeout(betterChatReactionCloseTimer);
            betterChatReactionCloseTimer = setTimeout(betterChatCloseReactionTray, 180);
        });
        $(document).on('click', '.better-chat-reaction-trigger', function(e) {
            e.preventDefault(); e.stopPropagation();
            const row = this.closest('.better-chat-reactions');
            if (!row) return;
            if (!window.matchMedia || !window.matchMedia('(hover: hover)').matches) {
                betterChatOpenReactionTray(this);
                return;
            }
            betterChatReactionPost(row.dataset.messageId, 'like').done(data => {
                if (data && data.status === 'ok') betterChatRenderReactionRow(row, data.summary || {counts:{},mine:''});
            });
        });
        $(document).on('click', '.better-chat-reaction-count', function(e) {
            e.preventDefault(); e.stopPropagation(); betterChatOpenWho(this);
        });
        if (chatReactionDisplay.whoHover !== false) {
            $(document).on('mouseenter', '.better-chat-reaction-count', function() { betterChatOpenWho(this); });
        }
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.better-chat-reaction-tray,.better-chat-who-reacted,.better-chat-reaction-trigger,.better-chat-reaction-count').length) {
                betterChatCloseReactionTray(); betterChatCloseWho();
            }
        });
        $(document).on('scroll', '#chat-body,.pc-body', function() { betterChatCloseReactionTray(); betterChatCloseWho(); });
        window.addEventListener('resize', function() { betterChatCloseReactionTray(); betterChatCloseWho(); });
    }

    // --- NOTIFICATION STATE ---
    const betterChatBaseTitle = document.title;
    let betterChatTitleTimer = null;
    const notifiedPrivateIds = {};
    let audioContext = null;

    function betterChatMemberSoundEnabled() {
        if (typeof better_chat_notify_sound !== 'undefined' && !better_chat_notify_sound)
            return false;

        if (typeof better_chat_allow_member_sound !== 'undefined' && better_chat_allow_member_sound) {
            const stored = localStorage.getItem('betterChatSoundEnabled');
            return stored === null ? true : stored === 'true';
        }

        return true;
    }

    function betterChatUpdateSoundButtons() {
        const allowed = typeof better_chat_notify_sound !== 'undefined'
            && better_chat_notify_sound
            && typeof better_chat_allow_member_sound !== 'undefined'
            && better_chat_allow_member_sound;

        $('.better-chat-sound-toggle').toggle(allowed);

        const enabled = betterChatMemberSoundEnabled();
        $('.better-chat-sound-toggle')
            .text(enabled ? '🔊' : '🔇')
            .attr('title', enabled ? 'Notification sound on' : 'Notification sound off')
            .attr('aria-label', enabled ? 'Turn notification sound off' : 'Turn notification sound on');
    }

    function betterChatPlayNotificationSound() {
        if (!betterChatMemberSoundEnabled())
            return;

        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;

            if (!audioContext)
                audioContext = new AudioCtx();

            const ctx = audioContext;
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.0001, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.06, ctx.currentTime + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.18);

            oscillator.connect(gain);
            gain.connect(ctx.destination);
            oscillator.start();
            oscillator.stop(ctx.currentTime + 0.2);
        } catch (e) {}
    }

    function betterChatStartTitleAlert(senderName) {
        if (typeof better_chat_notify_title !== 'undefined' && !better_chat_notify_title)
            return;

        if (betterChatTitleTimer)
            clearInterval(betterChatTitleTimer);

        const template = (typeof better_chat_notify_title_text !== 'undefined' && better_chat_notify_title_text)
            ? better_chat_notify_title_text
            : '{username} has sent you a new message...';
        const alertTitle = template.includes('{username}')
            ? template.replaceAll('{username}', senderName)
            : `${template} ${senderName}`;
        let showAlert = true;
        document.title = alertTitle;

        betterChatTitleTimer = setInterval(function() {
            document.title = showAlert ? betterChatBaseTitle : alertTitle;
            showAlert = !showAlert;
        }, 1000);
    }

    function betterChatStopTitleAlert() {
        if (betterChatTitleTimer) {
            clearInterval(betterChatTitleTimer);
            betterChatTitleTimer = null;
        }
        document.title = betterChatBaseTitle;
    }

    function betterChatPrivateNeedsAttention(userId) {
        const body = $(`#pc-body-${userId}`);
        const win = $(`#pc-${userId}`);
        const minimized = body.length ? body.is(':hidden') : true;
        const pageHidden = document.hidden || !document.hasFocus();
        const activeInput = document.activeElement && $(document.activeElement).hasClass('pc-input')
            && parseInt($(document.activeElement).data('to') || 0, 10) === parseInt(userId, 10);

        return minimized || pageHidden || !win.length || !activeInput;
    }

    function betterChatNotifyPrivate(userId, senderName, messageId) {
        if (!messageId || messageId <= (notifiedPrivateIds[userId] || 0))
            return;

        notifiedPrivateIds[userId] = messageId;

        if (betterChatPrivateNeedsAttention(userId)) {
            betterChatPlayNotificationSound();

            if (document.hidden || !document.hasFocus())
                betterChatStartTitleAlert(senderName);
        }
    }

    $(window).on('focus', betterChatStopTitleAlert);
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden)
            betterChatStopTitleAlert();
    });

    $(document).on('click', '.better-chat-sound-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        if (typeof better_chat_allow_member_sound === 'undefined' || !better_chat_allow_member_sound)
            return false;

        const next = !betterChatMemberSoundEnabled();
        localStorage.setItem('betterChatSoundEnabled', next ? 'true' : 'false');
        betterChatUpdateSoundButtons();

        if (next)
            betterChatPlayNotificationSound();

        return false;
    });

    $(document).on('pointerdown touchstart mousedown', '.better-chat-sound-toggle', function(e) {
        e.stopPropagation();
    });

    // --- HELPERS ---
    function betterChatUseMessengerMode() {
        return String(window.better_chat_private_minimize_style || 'bubbles') === 'bubbles';
    }

    function betterChatUsePrivateBubbles() {
        return betterChatUseMessengerMode();
    }

    function betterChatEnsureGroupBubble() {
        let bubble = $('#better-chat-group-bubble');
        if (bubble.length) return bubble;

        $('body').append(`
            <button type="button" id="better-chat-group-bubble" class="better-chat-group-bubble" aria-label="Open group chat" title="Open group chat">
                <span class="better-chat-group-bubble-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M4.25 4.5h15.5A2.25 2.25 0 0 1 22 6.75v8.5a2.25 2.25 0 0 1-2.25 2.25H10l-5.2 3.25c-.7.44-1.55-.07-1.55-.9V17.1A2.25 2.25 0 0 1 2 15.25v-8.5A2.25 2.25 0 0 1 4.25 4.5Z" fill="currentColor"/></svg>
                </span>
                <span class="better-chat-group-bubble-badge" hidden>0</span>
            </button>`);

        bubble = $('#better-chat-group-bubble');
        betterChatSetGroupBubbleUnread(parseInt(localStorage.getItem('chat_group_unread_count') || 0, 10));
        return bubble;
    }

    function betterChatSetGroupBubbleUnread(count) {
        count = Math.max(0, parseInt(count || 0, 10));
        localStorage.setItem('chat_group_unread_count', String(count));
        const badge = $('#better-chat-group-bubble .better-chat-group-bubble-badge');
        if (badge.length)
            badge.text(count > 99 ? '99+' : String(count)).prop('hidden', count === 0);
    }

    function betterChatShowGroupBubble() {
        if (!betterChatUseMessengerMode()) return;
        betterChatEnsureGroupBubble().show();
        $(document).trigger('betterchat:groupstatechange');
        betterChatAlignPrivateDock();
    }

    function betterChatHideGroupBubble() {
        $('#better-chat-group-bubble').hide();
        $(document).trigger('betterchat:groupstatechange');
        betterChatAlignPrivateDock();
    }

    function betterChatCountGroupUnread(messages, latestPublicId) {
        if (!betterChatUseMessengerMode() || localStorage.getItem('chat_minimized') !== 'true')
            return;

        let lastCounted = parseInt(localStorage.getItem('chat_group_last_counted_id') || 0, 10);
        if (!lastCounted) {
            localStorage.setItem('chat_group_last_counted_id', String(latestPublicId || 0));
            return;
        }

        const unseen = (messages || []).filter(m =>
            parseInt(m.id_member_to || 0, 10) === 0
            && parseInt(m.id_chat || 0, 10) > lastCounted
        ).length;

        if (unseen > 0) {
            const current = parseInt(localStorage.getItem('chat_group_unread_count') || 0, 10) || 0;
            betterChatSetGroupBubbleUnread(current + unseen);
        }

        if (latestPublicId > lastCounted)
            localStorage.setItem('chat_group_last_counted_id', String(latestPublicId));
    }

    function betterChatEnsurePrivateDock() {
        let dock = $('#better-chat-private-dock');
        if (!dock.length) {
            $('body').append('<div id="better-chat-private-dock" class="better-chat-private-dock" aria-label="Minimized private chats"></div>');
            dock = $('#better-chat-private-dock');
        }
        betterChatAlignPrivateDock();
        return dock;
    }

    function betterChatAlignPrivateDock() {
        const dock = $('#better-chat-private-dock');
        if (!dock.length) return;

        // test34d: the full-page Group Chat is a page canvas, not a floating
        // bottom-right panel. Keep Messenger-style minimized private chats on
        // the normal right-side bubble rail instead of pushing the dock above
        // the full height of the standalone chat.
        if (fullPageMode) {
            dock.css({ bottom: '14px', right: window.innerWidth <= 768 ? '10px' : '14px' });
            return;
        }

        let bottom = 14;
        const group = $('#smf-chat-bar');
        const groupBubble = $('#better-chat-group-bubble');
        const supportBubble = $('#better-support-bubble');

        if (group.length && group.is(':visible')) {
            bottom += Math.max(0, Math.ceil(group.outerHeight() || 0));
        } else {
            // In Messenger/Hybrid mode, derive the private dock from the
            // highest visible rail bubble (Group or Support) using its real
            // viewport rectangle. This keeps Group -> Support -> Private
            // ordering identical across browsers and zoom levels.
            let highestTop = window.innerHeight;
            [groupBubble, supportBubble].forEach(function(item) {
                if (item.length && item.is(':visible')) {
                    const rect = item[0].getBoundingClientRect();
                    highestTop = Math.min(highestTop, rect.top);
                }
            });

            if (highestTop < window.innerHeight)
                bottom = Math.max(14, Math.ceil(window.innerHeight - highestTop + 10));
        }

        dock.css('bottom', bottom + 'px');
    }

    // Support participates in the same Messenger bubble rail. Reflow private
    // bubbles immediately whenever the Support bubble opens/minimizes/moves.
    $(document).on('betterchat:supportstatechange', betterChatAlignPrivateDock);

    function betterChatPrivateBubble(userId, userName) {
        const dock = betterChatEnsurePrivateDock();
        let bubble = $('#better-chat-private-bubble-' + userId);
        if (bubble.length) return bubble;

        const safeName = $('<div>').text(userName || 'Member').html();
        const initial = $('<div>').html(safeName).text().trim().charAt(0).toUpperCase() || '?';
        bubble = $(`
            <button type="button" id="better-chat-private-bubble-${userId}" class="better-chat-private-bubble" data-user-id="${userId}" aria-label="Open chat with ${safeName}" title="${safeName}">
                <span class="better-chat-private-bubble-initial">${initial}</span>
                <span class="better-chat-private-bubble-badge" hidden>0</span>
            </button>`);
        dock.append(bubble);
        return bubble;
    }

    function betterChatSetPrivateBubbleUnread(userId, count) {
        const badge = $('#better-chat-private-bubble-' + userId + ' .better-chat-private-bubble-badge');
        if (!badge.length) return;
        count = Math.max(0, parseInt(count || 0, 10));
        badge.text(count > 99 ? '99+' : String(count)).prop('hidden', count === 0);
    }

    function betterChatIncrementPrivateBubbleUnread(userId, incomingId) {
        const bubble = $('#better-chat-private-bubble-' + userId);
        if (!bubble.length) return;

        const lastCounted = parseInt(bubble.attr('data-last-counted-id') || 0, 10);
        incomingId = parseInt(incomingId || 0, 10);
        if (!incomingId || incomingId <= lastCounted) return;

        const badge = bubble.find('.better-chat-private-bubble-badge');
        const current = parseInt(badge.text() || 0, 10) || 0;
        bubble.attr('data-last-counted-id', String(incomingId));
        betterChatSetPrivateBubbleUnread(userId, current + 1);
    }

    function betterChatMinimizePrivateToBubble(userId) {
        const win = $('#pc-' + userId);
        if (!win.length) return;

        const userName = win.data('username') || 'Member';
        win.attr('data-bubble-minimized', '1').hide();
        betterChatPrivateBubble(userId, userName);
        betterChatAlignPrivateDock();
        saveActiveWindows();
    }

    // Explicitly opening/restoring a conversation should land on the newest
    // message.  A single scrollTop assignment is not reliable because GIFs,
    // images, video posters and browser layout can change scrollHeight after the
    // window becomes visible. Keep the body pinned only during that short
    // restore/layout window; any real user interaction cancels the pin so normal
    // history reading is never dragged back to the bottom.
    let betterChatBottomPinSequence = 0;

    function betterChatScrollBodyToBottom(body, settleLayout = true) {
        const el = body && body[0];
        if (!el) return;

        const token = ++betterChatBottomPinSequence;
        body.data('better-chat-bottom-pin', token);

        const active = function() {
            return body.data('better-chat-bottom-pin') === token;
        };
        const cancel = function() {
            if (active()) body.removeData('better-chat-bottom-pin');
        };
        const apply = function() {
            if (!active() || !el.isConnected) return;
            el.scrollTop = el.scrollHeight;
        };

        // Wheel/touch/pointer interaction means the reader has taken control.
        body.off('.betterChatBottomPin').one('wheel.betterChatBottomPin touchstart.betterChatBottomPin pointerdown.betterChatBottomPin mousedown.betterChatBottomPin', cancel);

        window.requestAnimationFrame(function() {
            apply();
            window.requestAnimationFrame(apply);
        });

        if (!settleLayout) return;

        [40, 120, 280, 600].forEach(function(delay) {
            window.setTimeout(apply, delay);
        });

        // Late-loading media is the main cause of apparently random restore
        // positions. Re-pin when it finishes loading, but only while the restore
        // token is still active.
        body.find('img, video').each(function() {
            $(this).one('load.betterChatBottomPin loadedmetadata.betterChatBottomPin error.betterChatBottomPin', apply);
        });

        // End the automatic settle window. Normal AJAX refreshes then use the
        // smart-scroll logic below and preserve a reader who has scrolled up.
        window.setTimeout(function() {
            if (active()) {
                apply();
                body.removeData('better-chat-bottom-pin');
                body.off('.betterChatBottomPin');
            }
        }, 900);
    }

    function betterChatRestorePrivateFromBubble(userId) {
        const win = $('#pc-' + userId);
        if (!win.length) return;

        $('#better-chat-private-bubble-' + userId).remove();
        win.attr('data-bubble-minimized', '0').show();
        const body = $(`#pc-body-${userId}`);
        $(`#pc-body-${userId}, #pc-ft-${userId}`).show();
        $(`#pc-${userId} .pc-header`).removeClass('chat-header-blink');
        betterChatSetPrivateBubbleUnread(userId, 0);
        betterChatScrollBodyToBottom(body);
        if (!document.hidden && document.hasFocus())
            betterChatStopTitleAlert();
        realignWindows();
        saveActiveWindows();
    }

    function betterChatGroupBubbleInitials(group) {
        const participants = Array.isArray(group && group.participants) ? group.participants : [];
        const others = participants.filter(p => parseInt(p.id || 0, 10) !== parseInt(smf_member_id, 10));
        const names = others.map(p => String(p.name || '').trim()).filter(Boolean).slice(0, 3);
        if (!names.length) {
            const fallback = String((group && group.title) || 'Private Group').trim();
            names.push(fallback || 'Private Group');
        }
        return names.map(name => name.charAt(0).toUpperCase() || '?');
    }

    function betterChatPrivateGroupBubble(group) {
        if (!group || !group.id) return $();
        const conversationId = parseInt(group.id, 10);
        const dock = betterChatEnsurePrivateDock();
        let bubble = $('#better-chat-private-group-bubble-' + conversationId);
        const title = betterChatGroupTitle(group);
        const initials = betterChatGroupBubbleInitials(group);
        const stack = initials.map((initial, i) => `<span class="better-chat-private-group-bubble-initial better-chat-private-group-bubble-initial-${i + 1}">${betterChatEscape(initial)}</span>`).join('');
        if (!bubble.length) {
            bubble = $(`<button type="button" id="better-chat-private-group-bubble-${conversationId}" class="better-chat-private-bubble better-chat-private-group-bubble" data-conversation-id="${conversationId}" aria-label="Open ${betterChatEscape(title)}" title="${betterChatEscape(title)}"><span class="better-chat-private-group-bubble-stack" aria-hidden="true">${stack}</span><span class="better-chat-private-bubble-badge" hidden>0</span></button>`);
            dock.append(bubble);
        } else {
            bubble.attr('aria-label', 'Open ' + title).attr('title', title);
            bubble.find('.better-chat-private-group-bubble-stack').html(stack);
        }
        return bubble;
    }

    function betterChatSetPrivateGroupBubbleUnread(conversationId, count) {
        const badge = $('#better-chat-private-group-bubble-' + conversationId + ' .better-chat-private-bubble-badge');
        if (!badge.length) return;
        count = Math.max(0, parseInt(count || 0, 10));
        badge.text(count > 99 ? '99+' : String(count)).prop('hidden', count === 0);
    }

    function betterChatMinimizePrivateGroupToBubble(conversationId) {
        const win = $('#pg-' + conversationId);
        if (!win.length) return;
        const participants = win.data('participants') || [];
        const group = { id: conversationId, title: win.attr('data-group-title') || 'Private Group', participants: participants };
        win.attr('data-bubble-minimized', '1').hide();
        betterChatPrivateGroupBubble(group);
        betterChatAlignPrivateDock();
        saveActiveWindows();
    }

    function betterChatRestorePrivateGroupFromBubble(conversationId) {
        const win = $('#pg-' + conversationId);
        if (!win.length) return;
        $('#better-chat-private-group-bubble-' + conversationId).remove();
        win.attr('data-bubble-minimized', '0').show();
        const body = $('#pg-body-' + conversationId);
        $('#pg-body-' + conversationId + ', #pg-ft-' + conversationId + ', #pg-' + conversationId + ' .better-chat-group-participants').show();
        betterChatSetPrivateGroupBubbleUnread(conversationId, 0);
        betterChatScrollBodyToBottom(body);
        realignWindows();
        saveActiveWindows();
    }

    function saveActiveWindows() {
        let active = [];
        $('.private-chat-window').each(function() {
            const el = $(this);
            if (el.hasClass('private-group-chat-window')) {
                const id = parseInt(el.attr('data-conversation-id') || 0, 10);
                if (!id) return;
                active.push({ type: 'group', id: id, name: el.attr('data-group-title') || 'Private Group', minimized: el.attr('data-bubble-minimized') === '1' || el.find('.pg-body').is(':hidden') });
                return;
            }
            let userId = (el.attr('id') || '').replace('pc-', '');
            let userName = el.data('username');
            let isBubbleMinimized = el.attr('data-bubble-minimized') === '1';
            let isMinimized = isBubbleMinimized || el.find('.pc-body').is(':hidden');
            active.push({ type: 'direct', id: userId, name: userName, minimized: isMinimized });
        });
        localStorage.setItem('chat_active_sessions', JSON.stringify(active));
    }

    function restoreSessions() {
        let sessions = JSON.parse(localStorage.getItem('chat_active_sessions')) || [];
        sessions.forEach(s => {
            if (s.type === 'group') {
                betterChatOpenPrivateGroup({id: parseInt(s.id, 10), title: s.name || 'Private Group', participants: []}, true);
                if (s.minimized && !fullPageMode) {
                    if (betterChatUsePrivateBubbles())
                        betterChatMinimizePrivateGroupToBubble(parseInt(s.id, 10));
                    else
                        $(`#pg-body-${s.id}, #pg-ft-${s.id}, #pg-${s.id} .better-chat-group-participants`).hide();
                }
                return;
            }
            openPrivateChat(s.id, s.name, true);
            if (s.minimized && !fullPageMode) {
                if (betterChatUsePrivateBubbles())
                    betterChatMinimizePrivateToBubble(parseInt(s.id, 10));
                else
                    $(`#pc-body-${s.id}, #pc-ft-${s.id}`).hide();
            }
        });
        betterChatAlignPrivateDock();
    }

    $(document).on('click', '.better-chat-private-bubble', function(e) {
        e.preventDefault();
        e.stopPropagation();
        betterChatRestorePrivateFromBubble(parseInt($(this).data('user-id') || 0, 10));
    });

    $(document).on('click', '.better-chat-private-group-bubble', function(e) {
        e.preventDefault();
        e.stopPropagation();
        betterChatRestorePrivateGroupFromBubble(parseInt($(this).data('conversation-id') || 0, 10));
    });

    $(document).on('click', '#better-chat-group-bubble', function(e) {
        e.preventDefault();
        e.stopPropagation();
        setGroupChatMinimized(false, false);
    });

    // --- TOGGLE LOGIC ---
    // One shared state path for both the header and the dedicated +/- button.
    // The button uses a direct capture-phase listener so theme/header handlers
    // cannot swallow its click before Better Chat sees it.
    function setGroupChatMinimized(minimized, animate = true) {
        const content = $('#chat-content');
        const button = $('#chat-toggle');

        // test34: the dedicated full-page view is a permanent expanded canvas.
        // Never let the floating widget's persisted minimized state collapse it.
        if (fullPageMode) {
            betterChatHideGroupBubble();
            chatRoot.show();
            content.show();
            return;
        }

        content.stop(true, true);
        localStorage.setItem('chat_minimized', minimized ? 'true' : 'false');
        button
            .text(minimized ? '+' : '−')
            .attr('aria-label', minimized ? 'Maximize chat' : 'Minimize chat')
            .attr('title', minimized ? 'Maximize chat' : 'Minimize chat');

        if (betterChatUseMessengerMode()) {
            if (minimized) {
                // The user has seen everything up to this point. New public
                // messages begin counting only after the group window becomes a bubble.
                const baseline = parseInt(localStorage.getItem('chat_last_public_msg_id') || 0, 10);
                localStorage.setItem('chat_group_last_counted_id', String(baseline));
                betterChatSetGroupBubbleUnread(0);
                content.hide();
                chatRoot.hide();
                betterChatShowGroupBubble();
            } else {
                betterChatHideGroupBubble();
                chatRoot.show();
                content.show();
                betterChatSetGroupBubbleUnread(0);
                const baseline = parseInt(localStorage.getItem('chat_last_public_msg_id') || 0, 10);
                localStorage.setItem('chat_group_last_counted_id', String(baseline));
                $('#chat-header').removeClass('chat-header-blink');
            }
            // Reflow open private windows whenever the group chat changes
            // between its full panel and Messenger bubble. The visible anchor
            // width changes dramatically (for example 500px -> bubble width),
            // so leaving the old right offsets in place can make the restored
            // group panel overlap an already-open private conversation.
            betterChatAlignPrivateDock();
            window.requestAnimationFrame(function() {
                if (typeof window.realignWindows === 'function')
                    window.realignWindows();
            });
            return;
        }

        if (animate) {
            if (minimized)
                content.slideUp('fast');
            else
                content.slideDown('fast');
        } else {
            content.toggle(!minimized);
        }

        window.setTimeout(betterChatAlignPrivateDock, animate ? 220 : 0);
    }

    function toggleGroupChat() {
        setGroupChatMinimized(!$('#chat-content').is(':hidden'), true);
    }

    $(document).on('click touchstart', '#chat-header', function(e) {
        if (fullPageMode)
            return;
        if ($(e.target).closest('.better-chat-sound-toggle, #chat-toggle, .better-chat-header-actions').length)
            return;

        $(this).removeClass('chat-header-blink');
        if (e.type === 'touchstart') e.stopPropagation();
        toggleGroupChat();
    });

    const chatToggleButton = document.getElementById('chat-toggle');
    if (chatToggleButton && !chatToggleButton.dataset.betterChatToggleBound) {
        chatToggleButton.dataset.betterChatToggleBound = '1';
        chatToggleButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            $('#chat-header').removeClass('chat-header-blink');
            toggleGroupChat();
        }, true);
    }

    $(document).on('pointerdown touchstart mousedown', '#chat-toggle', function(e) {
        e.stopPropagation();
    });

    $(document).on('click touchstart', '.pc-header', function(e) {
        if (fullPageMode) return;
        if ($(e.target).closest('.better-chat-sound-toggle, .close-pc, .better-chat-header-actions').length)
            return;

        const userId = parseInt($(this).data('user-id') || 0, 10);
        if (!userId) return;

        if (e.type === 'touchstart') e.stopPropagation();
        window.togglePC(userId);
    });

    $(document).on('click touchstart', '#mobile-user-toggle', function(e) {
        e.stopPropagation(); 
        $('#chat-user-list').toggleClass('show-mobile');
    });
    
    // Bulletproof click handler for names
    $(document).on('click touchstart', '.user-item', function(e) {
    e.preventDefault();
    e.stopPropagation();
    if ($(this).hasClass('is-suspended') || $(this).attr('data-suspended') === '1')
        return;

    const userId = parseInt($(this).data('id') || 0, 10);
    if ($('#pc-' + userId).attr('data-bubble-minimized') === '1')
        betterChatRestorePrivateFromBubble(userId);
    else
        window.openPrivateChat(userId, $(this).data('name'));
    });


    // --- MESSAGE RENDERING ---
    // test35f: enlarge Unicode emoji-only messages without affecting normal text.
    function betterChatIsEmojiOnlyHtml(html) {
        if (typeof html !== 'string' || html === '' || /<(?:img|video|audio|iframe|object|svg)\b/i.test(html))
            return false;

        const probe = document.createElement('div');
        probe.innerHTML = html;
        let text = (probe.textContent || probe.innerText || '').trim();
        if (!text) return false;

        // Keycap emoji are ordinary digits/#/* joined to a keycap combining mark.
        text = text.replace(/[0-9#*]\uFE0F?\u20E3/g, '');
        text = text.replace(/\s/g, '');
        if (!text) return true;

        for (const ch of Array.from(text)) {
            const cp = ch.codePointAt(0);

            // Emoji joiners/selectors and skin-tone modifiers.
            if (cp === 0x200D || cp === 0xFE0E || cp === 0xFE0F ||
                (cp >= 0x1F3FB && cp <= 0x1F3FF))
                continue;

            // Main Unicode emoji/symbol blocks used by the Better Chat picker.
            if ((cp >= 0x1F000 && cp <= 0x1FAFF) ||
                (cp >= 0x2600 && cp <= 0x27BF) ||
                (cp >= 0x2300 && cp <= 0x23FF) ||
                (cp >= 0x2B00 && cp <= 0x2BFF) ||
                (cp >= 0x2190 && cp <= 0x21FF) ||
                (cp >= 0x2900 && cp <= 0x297F) ||
                cp === 0x00A9 || cp === 0x00AE || cp === 0x203C ||
                cp === 0x2049 || cp === 0x2122 || cp === 0x2139 ||
                cp === 0x3030 || cp === 0x303D || cp === 0x3297 || cp === 0x3299)
                continue;

            return false;
        }

        return true;
    }

    function betterChatIsGiphyOnlyHtml(html) {
        if (typeof html !== 'string' || html === '') return false;
        const probe = document.createElement('div');
        probe.innerHTML = html;
        if ((probe.textContent || '').trim() !== '') return false;
        const imgs = probe.querySelectorAll('img');
        if (imgs.length !== 1) return false;
        try {
            const host = new URL(imgs[0].src, window.location.href).hostname.toLowerCase();
            return host === 'giphy.com' || host.endsWith('.giphy.com');
        } catch (e) { return false; }
    }

    function buildMsg(m) {
    let b = m.body;
    if (typeof b === 'string' && b.indexOf('[[system]]') === 0) {
        return `<div class="better-chat-system-message" data-msgid="${m.id_chat}">${b.replace('[[system]]','')}</div>`;
    }
    if (b.includes('[video_thumb]')) {
        let f = b.replace('[video_thumb]', '').replace('[/video_thumb]', '');
        let full = m.attachment_url || `${chat_board_url}/chat_uploads/${f}`;
        let thumb = m.thumbnail_url || `${chat_board_url}/chat_uploads/thumb_${f}.jpg`;
        b = `<video class="better-chat-video" controls preload="metadata" poster="${thumb}"><source src="${full}">Your browser does not support embedded video.</video>`;
    } else if (b.includes('[img_thumb]')) {
        let f = b.replace('[img_thumb]', '').replace('[/img_thumb]', '');
        let full = m.attachment_url || `${chat_board_url}/chat_uploads/${f}`;
        let thumb = m.thumbnail_url || `${chat_board_url}/chat_uploads/thumb_${f}`;
        b = `<a href="${full}" target="_blank" rel="noopener"><img class="better-chat-thumb" src="${thumb}" onerror="this.onerror=null;this.src='${full}';" alt="Chat attachment" loading="lazy" /></a>`;
    }

    let isMe = (parseInt(m.id_member_from) === parseInt(smf_member_id));
    let sideClass = isMe ? 'bubble-me' : 'bubble-them';
    let status = (isMe && m.id_member_to > 0) ? (parseInt(m.is_read) === 1 ? ' <span class="chat-status">Seen</span>' : ' <span class="chat-status">Delivered</span>') : '';

    // CONSTRUCT THE BUBBLE HTML
    let html = `<div class="chat-msg-container ${sideClass}" data-msgid="${m.id_chat}">`;
    if (!isMe) html += `<span class="chat-username">${m.real_name}</span>`;
    const emojiOnlyClass = betterChatIsEmojiOnlyHtml(b) ? ' emoji-only' : '';
    const giphyOnlyClass = betterChatIsGiphyOnlyHtml(b) ? ' giphy-only' : '';
    html += `<div class="chat-bubble${emojiOnlyClass}${giphyOnlyClass}">${b}</div>`;
    html += betterChatReactionRowHtml(m.id_chat, m.reactions || {counts:{}, mine:''});
    html += `<span class="chat-time">${m.time_text}${status}</span>`;
    html += `</div>`;
    
    return html;
    }


    // --- WINDOW MANAGEMENT ---
    function betterChatRestrictionRemaining(restriction) {
        if (!restriction || !restriction.expires_at) return 'Permanent';
        const seconds = Math.max(0, parseInt(restriction.expires_at, 10) - Math.floor(Date.now() / 1000));
        if (seconds <= 0) return 'Less than a minute';
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.max(1, Math.floor((seconds % 3600) / 60));
        if (days > 0) return days + ' day' + (days === 1 ? '' : 's') + (hours ? ', ' + hours + ' hour' + (hours === 1 ? '' : 's') : '');
        if (hours > 0) return hours + ' hour' + (hours === 1 ? '' : 's') + (minutes ? ', ' + minutes + ' minute' + (minutes === 1 ? '' : 's') : '');
        return minutes + ' minute' + (minutes === 1 ? '' : 's');
    }

    function betterChatRestrictionMarkup(restriction, showRemaining) {
        if (!restriction) return '';
        const reason = restriction.reason ? betterChatEscape(String(restriction.reason)) : 'No reason provided.';
        const duration = restriction.duration_text ? betterChatEscape(String(restriction.duration_text)) : 'Until further notice';
        const expires = restriction.expires_text ? betterChatEscape(String(restriction.expires_text)) : 'Until further notice';
        const remaining = betterChatEscape(betterChatRestrictionRemaining(restriction));
        let html = '<div class="better-chat-restriction-title">🔒 ' + betterChatEscape(String(restriction.label || 'Chat')) + ' access restricted</div>' +
            '<div class="better-chat-restriction-details"><strong>Reason:</strong> ' + reason + '</div>';
        if (showRemaining)
            html += '<div class="better-chat-restriction-details"><strong>Time remaining:</strong> ' + remaining + '</div>';
        else
            html += '<div class="better-chat-restriction-details"><strong>Duration:</strong> ' + duration + '</div>';
        html += '<div class="better-chat-restriction-details"><strong>Expires:</strong> ' + expires + '</div>';
        return html;
    }

    function betterChatApplyPrivateRestriction(userId, restriction) {
        const win = $('#pc-' + userId);
        if (!win.length) return;
        const notice = win.find('.better-chat-restriction-notice').first();
        const footer = $('#pc-ft-' + userId);
        const input = footer.find('.pc-input');
        const controls = footer.find('button, input[type="file"]');

        if (restriction) {
            notice.html(betterChatRestrictionMarkup(restriction)).removeAttr('hidden').css('display', 'block');
            win.addClass('is-chat-restricted');
            input.prop('disabled', true).attr('placeholder', 'Private chat is restricted');
            controls.prop('disabled', true);
        } else {
            notice.empty().attr('hidden', 'hidden').css('display', 'none');
            win.removeClass('is-chat-restricted');
            input.prop('disabled', false).attr('placeholder', 'Type...');
            controls.prop('disabled', false);
        }
    }

    function betterChatApplyGroupRestriction(restriction) {
        const root = $('#smf-chat-bar');
        const body = $('#chat-body');
        const input = $('#chat-input');
        const footer = $('#chat-footer');
        if (!root.length || !body.length) return false;

        if (restriction) {
            // The normal message body is the one Group Chat canvas we know the
            // browser/theme already renders correctly. Make it the restriction
            // state instead of introducing a competing hidden/flex screen.
            body.html('<div id="chat-group-restriction-inline" class="better-chat-group-restriction-inline" role="status" aria-live="polite">' +
                betterChatRestrictionMarkup(restriction, true) + '</div>');
            root.addClass('is-chat-restricted');
            input.prop('disabled', true).attr('placeholder', 'Group chat is restricted');
            footer.find('button, input[type="file"]').prop('disabled', true);
            return true;
        }

        const wasRestricted = root.hasClass('is-chat-restricted');
        root.removeClass('is-chat-restricted');
        input.prop('disabled', false).attr('placeholder', 'Type a message...');
        footer.find('button, input[type="file"]').prop('disabled', false);
        if (wasRestricted)
            body.empty();
        return false;
    }

    window.openPrivateChat = function(userId, userName, isRestoring = false) {
        if (userId == smf_member_id) return;

        // If the window already exists, do not force a Messenger-style bubble
        // open merely because polling discovered another incoming message.
        if ($('#pc-' + userId).length > 0) {
            if ($('#pc-' + userId).attr('data-bubble-minimized') !== '1')
                $(`#pc-body-${userId}, #pc-ft-${userId}`).show();
            $('#chat-user-list').removeClass('show-mobile');
            realignWindows();
            return;
        }
        
        // Mobile Drawer Cleanup
        $('#chat-user-list').removeClass('show-mobile');

        let maxWin = fullPageMode ? 12 : 5;
        if ($('.private-chat-window').length >= maxWin) {
            const oldest = $('.private-chat-window').first();
            const oldestId = parseInt((oldest.attr('id') || '').replace('pc-', ''), 10);
            if (oldestId) $('#better-chat-private-bubble-' + oldestId).remove();
            oldest.remove();
        }

        let pcHtml = `
            <div id="pc-${userId}" class="private-chat-window" data-username="${userName}" data-bubble-minimized="0">
                <div class="pc-header" data-user-id="${userId}">
                    <span class="better-chat-private-title"><span class="online-dot"></span><span class="better-chat-private-name">${userName}</span></span>
                    <span class="better-chat-header-actions">
                        <button type="button" class="better-chat-add-people better-chat-header-icon" data-conversation-id="0" data-user-id="${userId}" aria-label="Add people to this conversation" title="Add person">+</button>
                        ${fullPageMode ? '' : `<a class="better-chat-popout-private better-chat-header-icon" href="${chat_script_url}?action=chat_full;private=${userId};private_name=${encodeURIComponent(userName)}" target="_blank" rel="noopener" aria-label="Open private chat full page" title="Open full page">↗</a>`}
                        <button type="button" class="better-chat-minimize-private better-chat-header-icon" data-user-id="${userId}" aria-label="Minimize private chat" title="Minimize">−</button>
                        <button type="button" class="better-chat-private-clear better-chat-header-icon" data-user-id="${userId}" aria-label="Clear this private conversation from my view" title="Clear chat from my view">⌫</button>
                        <button type="button" class="better-chat-sound-toggle better-chat-header-icon" aria-label="Toggle chat notification sound" title="Notification sound">🔊</button>
                        <button type="button" class="close-pc better-chat-header-icon" onclick="closePrivateChat(event, ${userId})" aria-label="Close private chat" title="Close">×</button>
                    </span>
                </div>
                <div class="better-chat-restriction-notice" role="status" hidden></div>
                <div class="pc-body" id="pc-body-${userId}"></div>
                <div class="pc-footer" id="pc-ft-${userId}">
                    <div class="better-chat-compose-wrap">
                        <div class="better-chat-compose">
                            <div class="better-chat-input-shell">
                                <input type="text" class="pc-input" data-to="${userId}" placeholder="Type..." />
                                <div class="better-chat-input-actions">
                                    <button type="button" class="better-chat-emoji-toggle" data-emoji-private="${userId}" aria-label="Choose emoji" title="Emoji">😀</button>
                                    ${window.better_chat_giphy_enabled ? `<button type="button" class="better-chat-giphy-toggle" data-giphy-private="${userId}" aria-label="Choose GIF" title="GIF">GIF</button>` : ''}
                                    <button type="button" class="better-chat-attach" data-file-target="pc-file-${userId}" aria-label="Attach image" title="Attach image">📎</button>
                                </div>
                            </div>
                            <input type="file" id="pc-file-${userId}" class="pc-file" data-to="${userId}" accept="${better_chat_video_enabled ? 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm' : 'image/jpeg,image/png,image/gif,image/webp'}" hidden />
                        </div>
                        <div class="better-chat-upload-progress" aria-live="polite" hidden>
                            <div class="better-chat-upload-track"><span></span></div>
                            <div class="better-chat-upload-label"></div>
                        </div>
                    </div>
                </div>
            </div>`;
        (fullPageMode ? $('#better-chat-hub-private-stage') : $('body')).append(pcHtml);
        if (fullPageMode) betterChatHubEnsureTab('direct', userId, userName);
        betterChatSyncTheme({ waitForThemeLab: false });
        betterChatUpdateSoundButtons();
        realignWindows();
        fetchPrivateChat(userId);
        if (!isRestoring) saveActiveWindows();
    };

    window.togglePC = function(userId) {
    if (betterChatUsePrivateBubbles()) {
        if ($(`#pc-${userId}`).attr('data-bubble-minimized') === '1')
            betterChatRestorePrivateFromBubble(userId);
        else
            betterChatMinimizePrivateToBubble(userId);
        return;
    }

    $(`#pc-body-${userId}, #pc-ft-${userId}`).slideToggle('fast', function() {
        $(`#pc-${userId} .pc-header`).removeClass('chat-header-blink');
        if (!document.hidden && document.hasFocus())
            betterChatStopTitleAlert();
        saveActiveWindows();
        realignWindows();
    });
    };


    window.closePrivateChat = function(event, userId) {
        event.stopPropagation();
        let lastIdInWin = $('#pc-body-' + userId + ' .chat-msg-container').last().data('msgid') || 0;
        lastSeenPrivateIds[userId] = parseInt(lastIdInWin);
        localStorage.setItem('chat_last_seen_private', JSON.stringify(lastSeenPrivateIds));
        if (!closedWindows.includes(userId)) closedWindows.push(userId);
        localStorage.setItem('chat_closed_windows', JSON.stringify(closedWindows));
        $('#pc-' + userId).remove();
        $('#better-chat-private-bubble-' + userId).remove();
        if (fullPageMode) {
            betterChatHubTab('direct', userId).remove();
            if (betterChatHubIsActive('direct', userId)) betterChatHubActivate('public', 0, false);
            else betterChatHubRefreshMore();
        }
        betterChatAlignPrivateDock();
        realignWindows();
        saveActiveWindows();
    };

    window.realignWindows = function() {
        if (window.innerWidth <= 768) {
            $('.private-chat-window').not('[data-bubble-minimized="1"]').css({ 'right': '0', 'left': '0', 'width': '100%', 'bottom': '0' });
        } else if (fullPageMode) {
            // test37: private conversations live inside the full-page hub canvas.
            // Clear any inline positioning left by a previous floating-page state.
            $('#better-chat-hub-private-stage .private-chat-window').css({ 'right': '', 'left': '', 'width': '', 'bottom': '' });
        } else {
            let baseRight;
            const groupBubble = $('#better-chat-group-bubble');
            const group = $('#smf-chat-bar');

            // In Messenger mode the minimized group chat no longer occupies its
            // configured panel width. Open private chats should therefore anchor
            // immediately to the left of the visible group bubble instead of
            // reserving (for example) a full 500px invisible chat panel.
            if (betterChatUseMessengerMode() && groupBubble.length && groupBubble.is(':visible')) {
                const bubbleRect = groupBubble[0].getBoundingClientRect();
                baseRight = Math.max(14, Math.ceil(window.innerWidth - bubbleRect.left + 12));
            } else if (group.length && group.is(':visible')) {
                const groupRect = group[0].getBoundingClientRect();
                baseRight = Math.max(14, Math.ceil(window.innerWidth - groupRect.left + 10));
            } else {
                const cw = (typeof chat_width !== 'undefined') ? parseInt(chat_width, 10) : 500;
                baseRight = cw + 40;
            }

            $('.private-chat-window').not('[data-bubble-minimized="1"]').each(function(i) {
                $(this).css({ 'right': ((i * 270) + baseRight) + 'px', 'left': 'auto', 'width': '260px', 'bottom': '0' });
            });
        }
        betterChatAlignPrivateDock();
    };


    // --- EMOJI STUDIO ---
    const bcEmoji = {
      recent: ['🕘','Recent',[]],
      faces: ['😀','Faces',[
        '😀','😃','😄','😁','😆','😅','😂','🤣','🥲','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙',
        '😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥸','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁',
        '☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥',
        '😓','🤗','🤔','🫣','🤭','🫢','🤫','🫡','🤥','😶','🫥','😐','🫤','😑','😬','🙄','😯','😦','😧',
        '😮','😲','🥱','😴','🤤','😪','😮‍💨','😵','😵‍💫','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤠',
        '😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖'
      ]],
      people: ['👋','People',[
        '👋','🤚','🖐️','✋','🖖','🫱','🫲','🫳','🫴','👌','🤌','🤏','✌️','🤞','🫰','🤟','🤘','🤙',
        '👈','👉','👆','🖕','👇','☝️','🫵','👍','👎','✊','👊','🤛','🤜','👏','🙌','🫶','👐','🤲','🤝',
        '🙏','✍️','💅','🤳','💪','🦾','🦿','🦵','🦶','👂','👃','🧠','🫀','🫁','🦷','🦴','👀','👁️',
        '👅','👄','🫦','🙋','🙆','🙅','🤷','🤦','🙇','💁','🙎','🙍','🧏','💆','💇','🧘','🛌','🕺','💃'
      ]],
      reactions: ['❤️','Reactions',[
        '❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❤️‍🔥','❤️‍🩹','❣️','💕','💞','💓','💗','💖','💘','💝',
        '💟','💯','💢','💥','💫','💦','💨','🕳️','💣','💬','👁️‍🗨️','🗨️','🗯️','💭','💤','🔥','✨','🌟','⭐','⚡',
        '🎉','🎊','🎈','🎁','🏆','🥇','🥈','🥉','✅','☑️','✔️','❌','❎','➕','➖','➗','✖️','‼️','⁉️','❓',
        '❔','❕','❗','⚠️','🚫','⛔','🛑','♻️','🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🟤'
      ]],
      animals: ['🐶','Animals',[
        '🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐻‍❄️','🐨','🐯','🦁','🐮','🐷','🐽','🐸','🐵','🙈','🙉','🙊',
        '🐒','🐔','🐧','🐦','🐤','🐣','🐥','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🪱','🐛','🦋','🐌',
        '🐞','🐜','🪰','🪲','🪳','🦟','🦗','🕷️','🦂','🐢','🐍','🦎','🐙','🦑','🦐','🦞','🦀','🐡','🐠','🐟',
        '🐬','🐳','🐋','🦈','🐊','🐅','🐆','🦓','🦍','🦧','🐘','🦛','🦏','🐪','🐫','🦒','🦘','🦬','🐃','🐂'
      ]],
      food: ['🍕','Food',[
        '🍏','🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🫐','🍈','🍒','🍑','🥭','🍍','🥥','🥝','🍅','🍆','🥑',
        '🥦','🥬','🥒','🌶️','🫑','🌽','🥕','🫒','🧄','🧅','🥔','🍠','🥐','🥯','🍞','🥖','🥨','🧀','🥚','🍳',
        '🧈','🥞','🧇','🥓','🥩','🍗','🍖','🌭','🍔','🍟','🍕','🫓','🥪','🥙','🧆','🌮','🌯','🫔','🥗','🥘',
        '🫕','🥫','🍝','🍜','🍲','🍛','🍣','🍱','🥟','🦪','🍤','🍙','🍚','🍘','🍥','🥠','🥮','🍢','🍡','🍧',
        '🍨','🍦','🥧','🧁','🍰','🎂','🍮','🍭','🍬','🍫','🍿','🍩','🍪','🌰','🥜','☕','🍵','🥤','🧋','🍺'
      ]],
      activities: ['⚽','Activities',[
        '⚽','🏀','🏈','⚾','🥎','🎾','🏐','🏉','🥏','🎱','🪀','🏓','🏸','🏒','🏑','🥍','🏏','🪃','🥅','⛳',
        '🪁','🏹','🎣','🤿','🥊','🥋','🎽','🛹','🛼','🛷','⛸️','🥌','🎿','⛷️','🏂','🪂','🏋️','🤼','🤸','⛹️',
        '🤺','🤾','🏌️','🏇','🧘','🏄','🏊','🤽','🚣','🧗','🚵','🚴','🏆','🥇','🥈','🥉','🏅','🎖️','🏵️','🎗️',
        '🎫','🎟️','🎪','🤹','🎭','🩰','🎨','🎬','🎤','🎧','🎼','🎹','🥁','🪘','🎷','🎺','🪗','🎸','🪕','🎻'
      ]],
      travel: ['🚗','Travel',[
        '🚗','🚕','🚙','🚌','🚎','🏎️','🚓','🚑','🚒','🚐','🛻','🚚','🚛','🚜','🏍️','🛵','🚲','🛴','🛹','🛼',
        '🚨','🚔','🚍','🚘','🚖','🛞','🚡','🚠','🚟','🚃','🚋','🚞','🚝','🚄','🚅','🚈','🚂','🚆','🚇','🚊',
        '🚉','✈️','🛫','🛬','🛩️','💺','🛰️','🚀','🛸','🚁','🛶','⛵','🚤','🛥️','🛳️','⛴️','🚢','⚓','🛟','⛽',
        '🚧','🚦','🚥','🗺️','🗿','🗽','🗼','🏰','🏯','🏟️','🎡','🎢','🎠','⛲','⛱️','🏖️','🏝️','🏜️','🌋','⛰️'
      ]],
      objects: ['💡','Objects',[
        '⌚','📱','📲','💻','⌨️','🖥️','🖨️','🖱️','🖲️','🕹️','🗜️','💽','💾','💿','📀','📼','📷','📸','📹','🎥',
        '📞','☎️','📟','📠','📺','📻','🎙️','🎚️','🎛️','🧭','⏱️','⏲️','⏰','🕰️','⌛','⏳','📡','🔋','🪫','🔌',
        '💡','🔦','🕯️','🪔','🧯','🛢️','💸','💵','💴','💶','💷','🪙','💰','💳','💎','⚖️','🪜','🧰','🪛','🔧',
        '🔨','⚒️','🛠️','⛏️','🪚','🔩','⚙️','🪤','🧱','⛓️','🧲','🔫','💣','🧨','🪓','🔪','🗡️','🛡️','🚬','⚰️'
      ]],
      symbols: ['🎉','Symbols',[
        '🏧','🚮','🚰','♿','🚹','🚺','🚻','🚼','🚾','🛂','🛃','🛄','🛅','⚠️','🚸','⛔','🚫','🚳','🚭','🚯',
        '🚱','🚷','📵','🔞','☢️','☣️','⬆️','↗️','➡️','↘️','⬇️','↙️','⬅️','↖️','↕️','↔️','↩️','↪️','⤴️','⤵️',
        '🔃','🔄','🔙','🔚','🔛','🔜','🔝','🛐','⚛️','🕉️','✡️','☸️','☯️','✝️','☦️','☪️','☮️','🕎','🔯','♈',
        '♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓','⛎','▶️','⏩','⏭️','⏯️','◀️','⏪','⏮️','🔼','⏫'
      ]]
    };
    const bcEmojiNames={'😂':'laugh tears lol','🤣':'laugh rofl','❤️':'heart love','👍':'thumb up like','👎':'thumb down dislike','🔥':'fire hot','🤜':'fist bump','🤛':'fist bump','🍕':'pizza','🚗':'car','💡':'idea','🎉':'party celebrate','💀':'skull dead','😎':'cool sunglasses','🤔':'think','😭':'cry'};
    let bcEmojiTarget=null, bcEmojiCat='faces';
    function bcRecent(){try{return JSON.parse(localStorage.getItem('betterChatRecentEmoji')||'[]').slice(0,16)}catch(e){return[]}}
    function bcRemember(x){let a=bcRecent().filter(e=>e!==x);a.unshift(x);try{localStorage.setItem('betterChatRecentEmoji',JSON.stringify(a.slice(0,16)))}catch(e){}}
    function bcBuildPicker(){
      if($('#better-chat-emoji-picker').length)return;
      const tabs=Object.entries(bcEmoji).map(([k,v])=>`<button type="button" class="better-chat-emoji-tab" data-cat="${k}" title="${v[1]}">${v[0]}</button>`).join('');
      $('body').append(`<div id="better-chat-emoji-picker" class="better-chat-emoji-picker" hidden><div class="better-chat-emoji-search-row"><input class="better-chat-emoji-search" type="search" placeholder="Search emoji…" autocomplete="off"><button type="button" class="better-chat-emoji-close">×</button></div><div class="better-chat-emoji-tabs">${tabs}</div><div class="better-chat-emoji-title"></div><div class="better-chat-emoji-grid"></div></div>`);
    }
    function bcItems(k){return k==='recent'?bcRecent():bcEmoji[k][2]}
    function bcRender(k,q=''){
      bcEmojiCat=k; let a=bcItems(k), p=$('#better-chat-emoji-picker'); q=q.trim().toLowerCase();
      if(q){a=[...new Set(Object.keys(bcEmoji).flatMap(bcItems))].filter(e=>(e+' '+(bcEmojiNames[e]||'')).toLowerCase().includes(q));}
      p.find('.better-chat-emoji-tab').removeClass('is-active').filter(`[data-cat="${k}"]`).addClass('is-active');
      p.find('.better-chat-emoji-title').text(q?'Search':bcEmoji[k][1]);
      p.find('.better-chat-emoji-grid').html(a.length?a.map(e=>`<button type="button" class="better-chat-emoji" data-emoji="${e}">${e}</button>`).join(''):'<div class="better-chat-emoji-empty">No emoji found</div>');
    }
    function bcClose(){ $('#better-chat-emoji-picker').prop('hidden',true); bcEmojiTarget=null; }
    function bcPosition(btn){let p=$('#better-chat-emoji-picker'),n=p[0];n.hidden=false;let b=btn.getBoundingClientRect(),r=n.getBoundingClientRect(),l=Math.max(8,Math.min(b.right-r.width,innerWidth-r.width-8)),t=b.top-r.height-8;if(t<8)t=b.bottom+8;p.css({left:l+'px',top:Math.max(8,t)+'px'});}
    function bcInsert(input,e){if(!input)return;let s=input.selectionStart??input.value.length,n=input.selectionEnd??s;input.value=input.value.slice(0,s)+e+input.value.slice(n);let x=s+e.length;input.focus();input.setSelectionRange&&input.setSelectionRange(x,x);bcRemember(e);}
    bcBuildPicker();
    $(document).on('click','.better-chat-emoji-toggle',function(e){e.preventDefault();e.stopPropagation();let p=$('#better-chat-emoji-picker');if(!p.prop('hidden')&&bcEmojiTarget){bcClose();return}let id=$(this).data('emoji-private'),gid=$(this).data('emoji-group');if(gid!==undefined)bcEmojiTarget=$(`.pg-input[data-conversation-id="${gid}"]`)[0];else bcEmojiTarget=id!==undefined?$(`.pc-input[data-to="${id}"]`)[0]:document.getElementById($(this).data('emoji-input'));p.find('.better-chat-emoji-search').val('');bcRender(bcRecent().length?'recent':'faces');bcPosition(this);});
    $(document).on('click','.better-chat-emoji-tab',function(e){e.stopPropagation();$('.better-chat-emoji-search').val('');bcRender($(this).data('cat'));});
    $(document).on('input','.better-chat-emoji-search',function(){bcRender(bcEmojiCat,$(this).val());});
    $(document).on('click','.better-chat-emoji',function(e){
        e.preventDefault();
        e.stopPropagation();
        const target = bcEmojiTarget;
        bcInsert(target,$(this).data('emoji'));
        bcClose();
        if (target) target.focus();
    });
    $(document).on('click','.better-chat-emoji-close',bcClose);
    $(document).on('click',function(e){if(!$(e.target).closest('#better-chat-emoji-picker,.better-chat-emoji-toggle').length)bcClose();});
    $(document).on('keydown',function(e){if(e.key==='Escape')bcClose();});

    // --- GIPHY GIF PICKER ---
    let bcGiphySelect = null;
    let bcGiphySearchTimer = null;
    let bcGiphyRequest = null;
    let bcGiphyAnchor = null;

    function betterChatGiphyEnabled() {
        return !!window.better_chat_giphy_enabled && !!String(window.better_chat_giphy_api_key || '').trim();
    }

    function betterChatBuildGiphyPicker() {
        if (!betterChatGiphyEnabled() || $('#better-chat-giphy-picker').length) return;
        $('body').append(`
            <div id="better-chat-giphy-picker" class="better-chat-giphy-picker" hidden>
                <div class="better-chat-giphy-search-row">
                    <input class="better-chat-giphy-search" type="search" maxlength="50" placeholder="Search GIFs…" autocomplete="off">
                    <button type="button" class="better-chat-giphy-close" aria-label="Close GIF picker" title="Close">×</button>
                </div>
                <div class="better-chat-giphy-status">Trending</div>
                <div class="better-chat-giphy-grid" role="list"></div>
                <div class="better-chat-giphy-attribution">Powered by <strong>GIPHY</strong></div>
            </div>`);
    }

    function betterChatCloseGiphy() {
        $('#better-chat-giphy-picker').prop('hidden', true);
        bcGiphySelect = null;
        bcGiphyAnchor = null;
        if (bcGiphyRequest && typeof bcGiphyRequest.abort === 'function') bcGiphyRequest.abort();
        bcGiphyRequest = null;
    }

    function betterChatPositionGiphy(btn) {
        const p = $('#better-chat-giphy-picker');
        if (!p.length || !btn) return;
        bcGiphyAnchor = btn;
        const node = p[0];
        node.hidden = false;
        const b = btn.getBoundingClientRect();
        const r = node.getBoundingClientRect();
        const gap = 14;
        const edge = 12;
        const left = Math.max(edge, Math.min(b.right - r.width, innerWidth - r.width - edge));

        // Chat controls live near the bottom of the viewport. Prefer the picker above
        // the control and always clamp it fully inside the visible window. Re-run
        // this after GIF results load because the picker grows once thumbnails arrive.
        let top = b.top - r.height - gap;
        if (top < edge) {
            const below = b.bottom + gap;
            top = (below + r.height <= innerHeight - edge) ? below : edge;
        }
        top = Math.max(edge, Math.min(top, innerHeight - r.height - edge));
        p.css({ left: left + 'px', top: top + 'px' });
    }

    function betterChatRenderGiphyResults(data, label) {
        const picker = $('#better-chat-giphy-picker');
        const grid = picker.find('.better-chat-giphy-grid');
        picker.find('.better-chat-giphy-status').text(label || 'GIFs');
        const items = data && Array.isArray(data.data) ? data.data : [];
        if (!items.length) {
            grid.html('<div class="better-chat-giphy-empty">No GIFs found.</div>');
            return;
        }
        const html = items.map(function(item) {
            const images = item.images || {};
            const preview = (images.fixed_width_small && (images.fixed_width_small.webp || images.fixed_width_small.url)) ||
                (images.fixed_width && (images.fixed_width.webp || images.fixed_width.url)) ||
                (images.original && (images.original.webp || images.original.url)) || '';
            const sendUrl = (images.fixed_width && images.fixed_width.url) ||
                (images.original && images.original.url) || preview;
            if (!preview || !sendUrl) return '';
            const title = betterChatEscape(item.title || 'GIF');
            return `<button type="button" class="better-chat-giphy-result" role="listitem" data-giphy-url="${betterChatEscape(sendUrl)}" data-giphy-title="${title}" title="${title}"><img src="${betterChatEscape(preview)}" alt="${title}" loading="lazy"></button>`;
        }).join('');
        grid.html(html || '<div class="better-chat-giphy-empty">No GIFs found.</div>');
        if (bcGiphyAnchor) requestAnimationFrame(function(){ betterChatPositionGiphy(bcGiphyAnchor); });
    }

    function betterChatLoadGiphy(term) {
        if (!betterChatGiphyEnabled()) return;
        const q = String(term || '').trim().slice(0, 50);
        const endpoint = q ? 'search' : 'trending';
        const picker = $('#better-chat-giphy-picker');
        picker.find('.better-chat-giphy-status').text(q ? `Searching “${q}”…` : 'Loading trending GIFs…');
        picker.find('.better-chat-giphy-grid').html('<div class="better-chat-giphy-empty">Loading…</div>');
        if (bcGiphyRequest && typeof bcGiphyRequest.abort === 'function') bcGiphyRequest.abort();
        const params = {
            api_key: String(window.better_chat_giphy_api_key || '').trim(),
            limit: 20,
            rating: 'pg-13'
        };
        if (q) params.q = q;
        bcGiphyRequest = $.ajax({
            url: `https://api.giphy.com/v1/gifs/${endpoint}`,
            method: 'GET',
            dataType: 'json',
            data: params,
            timeout: 10000
        }).done(function(data) {
            betterChatRenderGiphyResults(data, q ? `Results for “${q}”` : 'Trending');
        }).fail(function(xhr, status) {
            if (status === 'abort') return;
            picker.find('.better-chat-giphy-status').text('GIPHY unavailable');
            picker.find('.better-chat-giphy-grid').html('<div class="better-chat-giphy-empty">Could not load GIFs. Check the GIPHY API key in Better Chat settings.</div>');
        }).always(function() { bcGiphyRequest = null; });
    }

    window.betterChatOpenGiphyPicker = function(button, onSelect) {
        if (!betterChatGiphyEnabled() || !button || typeof onSelect !== 'function') return false;
        betterChatBuildGiphyPicker();
        bcClose();
        bcGiphySelect = onSelect;
        const picker = $('#better-chat-giphy-picker');
        picker.find('.better-chat-giphy-search').val('');
        betterChatPositionGiphy(button);
        betterChatLoadGiphy('');
        setTimeout(function(){ picker.find('.better-chat-giphy-search').trigger('focus'); }, 0);
        return true;
    };

    function betterChatSendGiphy(url, target) {
        if (!url || !target) return;
        const message = `[img]${url}[/img]`;
        const data = { send: 1, message: message, to: 0, conversation_id: 0 };
        if (target.type === 'private') data.to = parseInt(target.id || 0, 10);
        if (target.type === 'group') data.conversation_id = parseInt(target.id || 0, 10);
        if (window.better_chat_session_var && window.better_chat_session_id)
            data[window.better_chat_session_var] = window.better_chat_session_id;
        $.post(chatUrl, data, function(response) {
            if (!response || response.status === 'error') return;
            if (target.type === 'group') fetchPrivateGroup(data.conversation_id);
            else if (target.type === 'private') fetchPrivateChat(data.to);
            else fetchChat();
        }, 'json');
    }

    $(document).on('click', '.better-chat-giphy-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const button = this;
        const privateId = $(this).data('giphy-private');
        const groupId = $(this).data('giphy-group');
        let target = { type: 'public', id: 0 };
        if (groupId !== undefined) target = { type: 'group', id: parseInt(groupId || 0, 10) };
        else if (privateId !== undefined) target = { type: 'private', id: parseInt(privateId || 0, 10) };
        window.betterChatOpenGiphyPicker(button, function(payload) {
            betterChatSendGiphy(payload.url, target);
        });
    });

    $(document).on('input', '.better-chat-giphy-search', function() {
        const term = $(this).val();
        clearTimeout(bcGiphySearchTimer);
        bcGiphySearchTimer = setTimeout(function(){ betterChatLoadGiphy(term); }, 350);
    });

    $(document).on('click', '.better-chat-giphy-result', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const payload = { url: $(this).attr('data-giphy-url') || '', title: $(this).attr('data-giphy-title') || '' };
        const select = bcGiphySelect;
        betterChatCloseGiphy();
        if (select && payload.url) select(payload);
    });

    $(document).on('click', '.better-chat-giphy-close', function(e){ e.preventDefault(); e.stopPropagation(); betterChatCloseGiphy(); });
    $(document).on('click', function(e){ if (!$(e.target).closest('#better-chat-giphy-picker,.better-chat-giphy-toggle,#better-support-giphy').length) betterChatCloseGiphy(); });
    $(document).on('keydown', function(e){ if (e.key === 'Escape') betterChatCloseGiphy(); });

    // --- SMART SCROLL ---
    // Polling should follow new messages only while the reader is already near
    // the bottom. If they scroll upward to read history, preserve that position.
    const CHAT_BOTTOM_TOLERANCE = 36;
    let publicChatInitialized = false;
    const privateChatInitialized = {};

    function chatIsNearBottom(el) {
        if (!el) return true;
        return (el.scrollHeight - el.clientHeight - el.scrollTop) <= CHAT_BOTTOM_TOLERANCE;
    }

    function replaceChatHtmlPreservingScroll(body, html, forceBottom) {
        const el = body && body[0];
        if (!el) return;

        const fullPageBody = fullPageMode && (
            el.id === 'chat-body'
            || body.hasClass('pc-body')
            || body.hasClass('pg-body')
        );

        // test37c: do not rebuild an unchanged message tree. Firefox can move a
        // scroller by fractional layout/rounding amounts when identical media-
        // rich markup is destroyed and recreated on every poll. Keeping the DOM
        // intact when the rendered HTML did not change removes that refresh-only
        // layout churn entirely.
        if (fullPageBody && el.__betterChatRenderedHtml === html) {
            if (forceBottom) betterChatScrollBodyToBottom(body, true);
            return;
        }

        const wasNearBottom = chatIsNearBottom(el);
        const oldScrollTop = el.scrollTop;

        // When a reader is in history, preserve the actual message they are
        // looking at instead of reconstructing scrollTop from scrollHeight
        // deltas. The latter can drift in Firefox as image/font/layout metrics
        // round differently between DOM replacements.
        let anchorMsgId = '';
        let anchorOffset = 0;
        if (fullPageBody && !forceBottom && !wasNearBottom) {
            const children = Array.from(el.children || []);
            const anchor = children.find(function(child) {
                return child.hasAttribute('data-msgid')
                    && (child.offsetTop + child.offsetHeight) > oldScrollTop;
            });
            if (anchor) {
                anchorMsgId = anchor.getAttribute('data-msgid') || '';
                anchorOffset = anchor.offsetTop - oldScrollTop;
            }
        }

        const previousOverflowAnchor = el.style.overflowAnchor;
        if (fullPageBody) el.style.overflowAnchor = 'none';

        body.html(html);
        if (fullPageBody) el.__betterChatRenderedHtml = html;

        if (forceBottom || wasNearBottom) {
            betterChatScrollBodyToBottom(body, true);
        } else {
            const restore = function() {
                if (!el.isConnected) return;

                let targetScrollTop = oldScrollTop;
                if (anchorMsgId) {
                    const anchor = Array.from(el.children || []).find(function(child) {
                        return child.getAttribute && child.getAttribute('data-msgid') === anchorMsgId;
                    });
                    if (anchor)
                        targetScrollTop = Math.max(0, anchor.offsetTop - anchorOffset);
                }

                el.scrollTop = targetScrollTop;
            };

            restore();

            if (fullPageBody) {
                window.requestAnimationFrame(function() {
                    restore();
                    window.requestAnimationFrame(function() {
                        restore();
                        el.style.overflowAnchor = previousOverflowAnchor;
                    });
                });
            }
        }

        if (fullPageBody && (forceBottom || wasNearBottom)) {
            window.requestAnimationFrame(function() {
                el.style.overflowAnchor = previousOverflowAnchor;
            });
        }
    }

    // --- AJAX REQUEST HARDENING ---
    // Do not let a slow request stack behind the five-second timer.
    let publicPollInFlight = false;
    const privatePollInFlight = {};
    let privatePollCursor = 0;

    function fetchPrivateChatsSequentially() {
        const targets = [];

        $('.pc-input').each(function() {
            const id = parseInt($(this).data('to') || 0, 10);
            if (id && !targets.includes(id))
                targets.push(id);
        });

        if (!targets.length)
            return $.Deferred().resolve().promise();

        if (privatePollCursor >= targets.length)
            privatePollCursor = 0;

        const ordered = targets.slice(privatePollCursor).concat(targets.slice(0, privatePollCursor));
        privatePollCursor = (privatePollCursor + 1) % targets.length;

        let chain = $.Deferred().resolve().promise();

        ordered.forEach(function(userId) {
            chain = chain.then(function() {
                return fetchPrivateChat(userId);
            });
        });

        return chain;
    }

    // --- AJAX ---
    function fetchChat() {
        if (publicPollInFlight)
            return $.Deferred().resolve().promise();

        publicPollInFlight = true;

        return $.getJSON(chatUrl, function(data) {
            const groupRestricted = betterChatApplyGroupRestriction(data && data.restriction ? data.restriction : null);
            // Dedicated receiver-side PM discovery. This is intentionally
            // independent of the mixed public-message LIMIT so a new private
            // window cannot be starved by busy group chat traffic.
            if (Array.isArray(data.incoming_private)) {
                [...data.incoming_private].reverse().forEach(pm => {
                    const senderId = parseInt(pm.id_member_from || 0, 10);
                    const messageId = parseInt(pm.id_chat || 0, 10);
                    if (!senderId || senderId === parseInt(smf_member_id, 10)) return;

                    const lastSeenForUser = parseInt(lastSeenPrivateIds[senderId] || 0, 10);
                    if ($('#pc-' + senderId).length === 0 && messageId > lastSeenForUser) {
                        closedWindows = closedWindows.filter(id => parseInt(id, 10) !== senderId);
                        localStorage.setItem('chat_closed_windows', JSON.stringify(closedWindows));
                        openPrivateChat(senderId, pm.real_name || 'Member');
                    }
                });
            }

            if (Array.isArray(data.private_groups)) {
                data.private_groups.forEach(group => {
                    const id = parseInt(group.id || 0, 10);
                    const latestId = parseInt(group.latest_id || 0, 10);
                    if (!id) return;
                    if ($('#pg-' + id).length) {
                        $('#pg-' + id + ' .pg-title').text(betterChatGroupTitle(group));
                        return;
                    }
                    const lastSeen = parseInt(privateGroupLastSeen[id] || group.last_read_id || 0, 10);
                    if (fullPageMode) {
                        const hubKey = betterChatHubKey('group', id);
                        if (!parseInt(betterChatHubLastCounted[hubKey] || 0, 10) && parseInt(group.last_read_id || 0, 10) > 0)
                            betterChatHubLastCounted[hubKey] = parseInt(group.last_read_id || 0, 10);
                    }
                    if (!closedPrivateGroups.includes(id) || latestId > lastSeen)
                        betterChatOpenPrivateGroup(group);
                });
            }

            if (data.messages && data.messages.length > 0) {
                var latestIdFromServer = parseInt(data.messages[0].id_chat);
                const latestPublicMessage = data.messages.find(m => parseInt(m.id_member_to || 0, 10) === 0);
                const latestPublicId = latestPublicMessage ? parseInt(latestPublicMessage.id_chat, 10) : 0;
                const lastPublicId = parseInt(localStorage.getItem('chat_last_public_msg_id') || 0, 10);
                if (fullPageMode && latestPublicId > 0) betterChatHubTrackUnread('public', 0, data.messages.filter(m => parseInt(m.id_member_to || 0, 10) === 0), latestPublicId);

                if ($('#chat-content').is(':hidden') && lastPublicId > 0 && latestPublicId > lastPublicId) {
                    if (betterChatUseMessengerMode())
                        betterChatCountGroupUnread(data.messages, latestPublicId);
                    else
                        $('#chat-header').addClass('chat-header-blink');
                }

                if (latestPublicId > 0) {
                    localStorage.setItem('chat_last_public_msg_id', latestPublicId);
                    if (!$('#chat-content').is(':hidden')) {
                        localStorage.setItem('chat_group_last_counted_id', String(latestPublicId));
                        betterChatSetGroupBubbleUnread(0);
                    }
                }

                [...data.messages].reverse().forEach(m => {
                    if (m.id_member_to == smf_member_id) {
                        let senderId = parseInt(m.id_member_from);
                        let lastSeenForUser = lastSeenPrivateIds[senderId] || 0;
                        if ($('#pc-' + senderId).length === 0 && (!closedWindows.some(id => parseInt(id, 10) === senderId) || parseInt(m.id_chat) > lastSeenForUser)) {
                            closedWindows = closedWindows.filter(id => parseInt(id, 10) !== senderId);
                            localStorage.setItem('chat_closed_windows', JSON.stringify(closedWindows));
                            openPrivateChat(senderId, m.real_name);
                        }
                    }
                });

                if (!groupRestricted) {
                    let mHtml = '';
                    [...data.messages].reverse().forEach(m => { if(m.id_member_to == 0) mHtml += buildMsg(m); });
                    const publicBody = $('#chat-body');
                    betterChatCloseReactionTray(); betterChatCloseWho();
                    replaceChatHtmlPreservingScroll(publicBody, mHtml, !publicChatInitialized);
                    publicChatInitialized = true;
                    lastMsgId = latestIdFromServer;
                    localStorage.setItem('chat_last_msg_id', lastMsgId);
                }
            }
if (data.online) {
    let uHtml = '';
    data.online.forEach(u => {
        const isSuspended = !!u.suspended;
        const stateClass = isSuspended ? ' is-group-restricted is-suspended' : (u.group_restricted ? ' is-group-restricted' : '');
        const stateTitle = isSuspended ? 'Suspended' : (u.group_restricted ? 'Group Chat restricted' : '');
        const titleAttr = stateTitle ? ` title="${betterChatEscape(stateTitle)}"` : '';
        const suspendedAttr = isSuspended ? ' data-suspended="1" aria-disabled="true"' : '';
        uHtml += `<li class="user-item${stateClass}" data-id="${u.id}" data-name="${betterChatEscape(u.name)}"${suspendedAttr}${titleAttr}><span class="online-dot"></span> ${betterChatEscape(u.name)}</li>`;
    });
    $('#online-users').html(uHtml);
    $('#chat-online-count').text(` (${data.online.length})`);
}
        }).always(function() {
            publicPollInFlight = false;
        }).then(function() {
            return fetchPrivateChatsSequentially();
        }).then(function() {
            return fetchPrivateGroupsSequentially();
        });
    }

    function fetchPrivateChat(userId) {
        userId = parseInt(userId || 0, 10);

        if (!userId || privatePollInFlight[userId])
            return $.Deferred().resolve().promise();

        privatePollInFlight[userId] = true;

        const markRead = !fullPageMode || betterChatHubIsActive('direct', userId);
        return $.getJSON(chatUrl + '&target=' + userId + '&mark_read=' + (markRead ? '1' : '0'), function(data) {
            const conversationId = parseInt(data && data.conversation_id ? data.conversation_id : 0, 10);
            if (conversationId > 0) {
                $(`#pc-${userId}`).attr('data-conversation-id', String(conversationId));
                $(`#pc-${userId} .better-chat-add-people`).attr('data-conversation-id', String(conversationId));
            }
            betterChatApplyPrivateRestriction(userId, data && data.restriction ? data.restriction : null);
            if (data.messages && data.messages.length > 0) {
                let latestId = parseInt(data.messages[0].id_chat);
                let body = $(`#pc-body-${userId}`);
                if (fullPageMode) {
                    betterChatHubEnsureTab('direct', userId, $(`#pc-${userId}`).data('username') || 'Private Chat');
                    betterChatHubTrackUnread('direct', userId, data.messages, latestId);
                }

                const newestIncoming = data.messages.find(m =>
                    parseInt(m.id_member_from || 0, 10) === parseInt(userId, 10)
                    && parseInt(m.id_member_to || 0, 10) === parseInt(smf_member_id, 10)
                );
                const incomingId = newestIncoming ? parseInt(newestIncoming.id_chat, 10) : 0;
                const incomingName = newestIncoming && newestIncoming.real_name
                    ? newestIncoming.real_name
                    : ($(`#pc-${userId}`).data('username') || 'someone');

                if (body.is(':hidden')) {
                    if (incomingId > (lastSeenPrivateIds[userId] || 0)) {
                        $(`#pc-${userId} .pc-header`).addClass('chat-header-blink');
                        if ($(`#pc-${userId}`).attr('data-bubble-minimized') === '1')
                            betterChatIncrementPrivateBubbleUnread(userId, incomingId);
                        betterChatNotifyPrivate(userId, incomingName, incomingId);
                    }
                } else {
                    if (incomingId > (lastSeenPrivateIds[userId] || 0)) {
                        if (document.hidden || !document.hasFocus() || betterChatPrivateNeedsAttention(userId))
                            betterChatNotifyPrivate(userId, incomingName, incomingId);
                    }

                    if (!document.hidden && document.hasFocus()) {
                        lastSeenPrivateIds[userId] = latestId;
                        localStorage.setItem('chat_last_seen_private', JSON.stringify(lastSeenPrivateIds));
                        $(`#pc-${userId} .pc-header`).removeClass('chat-header-blink');
                    }
                }
                let mHtml = '';
                [...data.messages].reverse().forEach(m => { mHtml += buildMsg(m); });
                betterChatCloseReactionTray(); betterChatCloseWho();
                replaceChatHtmlPreservingScroll(body, mHtml, !privateChatInitialized[userId]);
                privateChatInitialized[userId] = true;
            } else {
                const body = $(`#pc-body-${userId}`);
                if (body.length) body.empty();
                privateChatInitialized[userId] = true;
            }
        }).always(function() {
            privatePollInFlight[userId] = false;
        });
    }

    // --- STAGE 2B: PRIVATE GROUP CONVERSATIONS ---
    const privateGroupInitialized = {};
    const privateGroupPollInFlight = {};
    const privateGroupLastSeen = JSON.parse(localStorage.getItem('chat_private_group_last_seen') || '{}');
    let closedPrivateGroups = JSON.parse(localStorage.getItem('chat_closed_private_groups') || '[]').map(v => parseInt(v, 10)).filter(Boolean);
    let memberPickerConversation = 0;
    let memberPickerSelected = new Map();
    let memberPickerTimer = null;

    function betterChatGroupTitle(group) {
        return group && group.title ? String(group.title) : 'Private Group';
    }

    function betterChatEnsureMemberPicker() {
        if ($('#better-chat-member-picker').length) return;
        $('body').append(`
            <div id="better-chat-member-picker" class="better-chat-member-picker" hidden>
                <div class="better-chat-member-picker-card" role="dialog" aria-modal="true" aria-labelledby="better-chat-member-picker-title">
                    <div class="better-chat-member-picker-head">
                        <strong id="better-chat-member-picker-title">Add people</strong>
                        <button type="button" class="better-chat-member-picker-close" aria-label="Close">×</button>
                    </div>
                    <div class="better-chat-member-picker-body">
                        <input type="search" class="better-chat-member-search" placeholder="Search members by name..." autocomplete="off" />
                        <div class="better-chat-member-selected" hidden></div>
                        <div class="better-chat-member-results"><div class="smalltext">Type at least 2 characters to search.</div></div>
                    </div>
                    <div class="better-chat-member-picker-foot">
                        <button type="button" class="button better-chat-member-picker-cancel">Cancel</button>
                        <button type="button" class="button better-chat-member-picker-add" disabled>Add to Conversation</button>
                    </div>
                </div>
            </div>`);
    }

    function betterChatRenderSelectedMembers() {
        const wrap = $('#better-chat-member-picker .better-chat-member-selected');
        wrap.empty();
        memberPickerSelected.forEach((m, id) => {
            wrap.append(`<button type="button" class="better-chat-member-chip" data-id="${id}" title="Remove ${betterChatEscape(m.name)}">${betterChatEscape(m.name)} ×</button>`);
        });
        wrap.prop('hidden', memberPickerSelected.size === 0);
        $('#better-chat-member-picker .better-chat-member-picker-add').prop('disabled', memberPickerSelected.size === 0);
    }

    function betterChatCloseMemberPicker() {
        memberPickerConversation = 0;
        memberPickerSelected.clear();
        $('#better-chat-member-picker').prop('hidden', true);
    }

    function betterChatOpenMemberPicker(conversationId) {
        conversationId = parseInt(conversationId || 0, 10);
        if (!conversationId) {
            window.alert('Better Chat is still opening that conversation. Try Add People again in a moment.');
            return;
        }
        betterChatEnsureMemberPicker();
        memberPickerConversation = conversationId;
        memberPickerSelected.clear();
        betterChatRenderSelectedMembers();
        const picker = $('#better-chat-member-picker');
        picker.find('.better-chat-member-search').val('');
        picker.find('.better-chat-member-results').html('<div class="smalltext">Type at least 2 characters to search.</div>');
        picker.prop('hidden', false);
        window.setTimeout(() => picker.find('.better-chat-member-search').trigger('focus'), 20);
    }

    function betterChatSearchMembers(term) {
        term = String(term || '').trim();
        const results = $('#better-chat-member-picker .better-chat-member-results');
        if (term.length < 2) {
            results.html('<div class="smalltext">Type at least 2 characters to search.</div>');
            return;
        }
        results.html('<div class="smalltext">Searching...</div>');
        $.getJSON(chatUrl + '&member_search=1&conversation_id=' + encodeURIComponent(memberPickerConversation) + '&term=' + encodeURIComponent(term), function(data) {
            const rows = data && Array.isArray(data.members) ? data.members : [];
            if (!rows.length) {
                results.html('<div class="smalltext">No matching members.</div>');
                return;
            }
            let html = '';
            rows.forEach(m => {
                const id = parseInt(m.id || 0, 10);
                if (!id || memberPickerSelected.has(id)) return;
                const label = betterChatEscape(m.name || 'Member');
                const username = m.username && m.username !== m.name ? ` <span class="smalltext">@${betterChatEscape(m.username)}</span>` : '';
                const online = !!m.online;
                const selectable = !!m.selectable;
                const reason = m.reason ? `<span class="better-chat-member-state">${betterChatEscape(m.reason)}</span>` : '';
                const disabled = selectable ? '' : ' disabled aria-disabled="true"';
                const statusTitle = selectable ? 'Online - click to add' : betterChatEscape(m.reason || (online ? 'Unavailable' : 'Not online'));
                html += `<button type="button" class="better-chat-member-result${selectable ? ' is-online' : ' is-disabled'}" data-id="${id}" data-name="${label}" title="${statusTitle}"${disabled}><span class="better-chat-member-status-dot ${online ? 'is-online' : 'is-offline'}" aria-hidden="true"></span><span class="better-chat-member-result-name">${label}${username}</span>${reason}</button>`;
            });
            results.html(html || '<div class="smalltext">No more matching members.</div>');
        });
    }

    function betterChatOpenPrivateGroup(group, isRestoring = false) {
        if (!group || !group.id) return;
        const conversationId = parseInt(group.id, 10);
        const title = betterChatGroupTitle(group);

        // test36c: every member must converge on ONE window when a direct chat
        // becomes a private group.  On the non-adding participant the old direct
        // window may not yet carry data-conversation-id, so use the original
        // two-member pair supplied by the server as the authoritative fallback.
        const originIds = Array.isArray(group.origin_member_ids)
            ? group.origin_member_ids.map(id => parseInt(id || 0, 10)).filter(Boolean)
            : [];
        const me = parseInt(smf_member_id || 0, 10);
        if (originIds.includes(me)) {
            originIds.forEach(function(memberId) {
                if (!memberId || memberId === me) return;
                $('#pc-' + memberId).remove();
                $('#better-chat-private-bubble-' + memberId).remove();
                if (fullPageMode) betterChatHubTab('direct', memberId).remove();
                closedWindows = closedWindows.filter(id => parseInt(id || 0, 10) !== memberId);
                delete privateChatInitialized[memberId];
            });
            localStorage.setItem('chat_closed_windows', JSON.stringify(closedWindows));
        }

        // A direct conversation becomes the group conversation in-place.
        // Polling can discover the new group before the Add People POST callback
        // returns, so retire the old direct window BEFORE checking whether the
        // group window already exists. This prevents the same conversation from
        // appearing twice (one direct window plus one group window).
        $('.private-chat-window[id^="pc-"]').each(function() {
            const direct = $(this);
            if (parseInt(direct.attr('data-conversation-id') || 0, 10) !== conversationId) return;
            const userId = parseInt((direct.attr('id') || '').replace('pc-', ''), 10) || 0;
            direct.remove();
            if (userId) $('#better-chat-private-bubble-' + userId).remove();
        });

        // Remove any stale direct-session entry for this converted conversation
        // before persisting the new group state.
        try {
            const sessions = JSON.parse(localStorage.getItem('chat_active_sessions') || '[]');
            const filtered = sessions.filter(s => !(s && s.type === 'direct' && $('#pc-' + parseInt(s.id || 0, 10)).length === 0));
            localStorage.setItem('chat_active_sessions', JSON.stringify(filtered));
        } catch (e) {}

        if ($('#pg-' + conversationId).length) {
            $('#pg-' + conversationId).attr('data-group-title', title).data('participants', Array.isArray(group.participants) ? group.participants : []);
            $('#pg-' + conversationId + ' .pg-title').text(title).attr('title', title);
            if (fullPageMode) betterChatHubEnsureTab('group', conversationId, title);
            if ($('#better-chat-private-group-bubble-' + conversationId).length) betterChatPrivateGroupBubble(group);
            betterChatAlignPrivateDock();
            realignWindows();
            saveActiveWindows();
            return;
        }

        const participants = Array.isArray(group.participants) ? group.participants : [];
        const participantNames = participants.map(p => p.name).filter(Boolean).join(', ');
        const html = `
            <div id="pg-${conversationId}" class="private-chat-window private-group-chat-window" data-conversation-id="${conversationId}" data-group-title="${betterChatEscape(title)}" data-bubble-minimized="0">
                <div class="pc-header pg-header" data-conversation-id="${conversationId}">
                    <span class="better-chat-private-title" title="${betterChatEscape(title)}"><span class="online-dot"></span> <span class="pg-title">${betterChatEscape(title)}</span></span>
                    <span class="better-chat-header-actions better-chat-private-group-actions">
                        <button type="button" class="better-chat-add-people better-chat-header-icon" data-conversation-id="${conversationId}" aria-label="Add people" title="Add person">+</button>
                        <button type="button" class="better-chat-rename-group better-chat-header-icon" data-conversation-id="${conversationId}" aria-label="Rename conversation" title="Rename conversation">✎</button>
                        ${fullPageMode ? '' : `<a class="better-chat-popout-private better-chat-header-icon" href="${chat_script_url}?action=chat_full;conversation=${conversationId};conversation_name=${encodeURIComponent(title)}" target="_blank" rel="noopener" aria-label="Open private group full page" title="Open full page">↗</a>`}
                        <button type="button" class="better-chat-minimize-group better-chat-header-icon" data-conversation-id="${conversationId}" aria-label="Minimize private group" title="Minimize">−</button>
                        <button type="button" class="better-chat-sound-toggle better-chat-header-icon" aria-label="Toggle chat notification sound" title="Notification sound">🔊</button>
                        <button type="button" class="better-chat-leave-group better-chat-header-icon" data-conversation-id="${conversationId}" aria-label="Leave conversation" title="Leave conversation">↪</button>
                        <button type="button" class="close-pg better-chat-header-icon" data-conversation-id="${conversationId}" aria-label="Close private group" title="Close">×</button>
                    </span>
                </div>
                <div class="better-chat-group-participants" title="${betterChatEscape(participantNames)}">${betterChatEscape(participantNames)}</div>
                <div class="better-chat-restriction-notice" role="status" hidden></div>
                <div class="pc-body pg-body" id="pg-body-${conversationId}"></div>
                <div class="pc-footer pg-footer" id="pg-ft-${conversationId}">
                    <div class="better-chat-compose-wrap">
                        <div class="better-chat-compose">
                            <div class="better-chat-input-shell">
                                <input type="text" class="pg-input" data-conversation-id="${conversationId}" placeholder="Type..." />
                                <div class="better-chat-input-actions">
                                    <button type="button" class="better-chat-emoji-toggle" data-emoji-group="${conversationId}" aria-label="Choose emoji" title="Emoji">😀</button>
                                    ${window.better_chat_giphy_enabled ? `<button type="button" class="better-chat-giphy-toggle" data-giphy-group="${conversationId}" aria-label="Choose GIF" title="GIF">GIF</button>` : ''}
                                    <button type="button" class="better-chat-attach" data-file-target="pg-file-${conversationId}" aria-label="Attach image" title="Attach image">📎</button>
                                </div>
                            </div>
                            <input type="file" id="pg-file-${conversationId}" class="pg-file" data-conversation-id="${conversationId}" accept="${better_chat_video_enabled ? 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm' : 'image/jpeg,image/png,image/gif,image/webp'}" hidden />
                        </div>
                        <div class="better-chat-upload-progress" aria-live="polite" hidden>
                            <div class="better-chat-upload-track"><span></span></div>
                            <div class="better-chat-upload-label"></div>
                        </div>
                    </div>
                </div>
            </div>`;
        (fullPageMode ? $('#better-chat-hub-private-stage') : $('body')).append(html);
        if (fullPageMode) betterChatHubEnsureTab('group', conversationId, title);
        $('#pg-' + conversationId).data('participants', participants);
        betterChatSyncTheme({ waitForThemeLab: false });
        betterChatUpdateSoundButtons();
        closedPrivateGroups = closedPrivateGroups.filter(id => id !== conversationId);
        localStorage.setItem('chat_closed_private_groups', JSON.stringify(closedPrivateGroups));
        realignWindows();
        fetchPrivateGroup(conversationId);
        if (!isRestoring) saveActiveWindows();
    }

    window.openPrivateGroupChat = betterChatOpenPrivateGroup;

    function betterChatApplyPrivateGroupRestriction(conversationId, restriction) {
        const win = $('#pg-' + conversationId);
        if (!win.length) return;
        const notice = win.find('.better-chat-restriction-notice').first();
        const footer = $('#pg-ft-' + conversationId);
        if (restriction) {
            notice.html(betterChatRestrictionMarkup(restriction)).removeAttr('hidden').css('display', 'block');
            footer.find('input,button').prop('disabled', true);
        } else {
            notice.empty().attr('hidden', 'hidden').css('display', 'none');
            footer.find('input,button').prop('disabled', false);
        }
    }

    function fetchPrivateGroup(conversationId) {
        conversationId = parseInt(conversationId || 0, 10);
        if (!conversationId || privateGroupPollInFlight[conversationId]) return $.Deferred().resolve().promise();
        privateGroupPollInFlight[conversationId] = true;
        const markRead = !fullPageMode || betterChatHubIsActive('group', conversationId);
        return $.getJSON(chatUrl + '&conversation_id=' + conversationId + '&mark_read=' + (markRead ? '1' : '0'), function(data) {
            if (!data || data.status === 'error') {
                if (data && data.message) console.warn(data.message);
                return;
            }
            const group = data.conversation || {};
            const title = betterChatGroupTitle(group);
            $('#pg-' + conversationId).attr('data-group-title', title).data('participants', Array.isArray(group.participants) ? group.participants : []);
            $('#pg-' + conversationId + ' .pg-title').text(title).attr('title', title);
            if (fullPageMode) betterChatHubEnsureTab('group', conversationId, title);
            const names = Array.isArray(group.participants) ? group.participants.map(p => p.name).filter(Boolean) : [];
            const peopleLabel = names.length + (names.length === 1 ? ' person' : ' people');
            $('#pg-' + conversationId + ' .better-chat-group-participants').html(`<span class="better-chat-group-count">${peopleLabel}</span><span class="better-chat-group-member-list">${betterChatEscape(names.join(' · '))}</span>`).attr('title', names.join(', '));
            if ($('#better-chat-private-group-bubble-' + conversationId).length) betterChatPrivateGroupBubble(group);
            betterChatApplyPrivateGroupRestriction(conversationId, data.restriction || null);

            const messages = Array.isArray(data.messages) ? data.messages : [];
            const body = $('#pg-body-' + conversationId);
            if (messages.length) {
                const latestId = parseInt(messages[0].id_chat || 0, 10);
                if (fullPageMode) betterChatHubTrackUnread('group', conversationId, messages, latestId);
                let html = '';
                [...messages].reverse().forEach(m => html += buildMsg(m));
                betterChatCloseReactionTray(); betterChatCloseWho();
                replaceChatHtmlPreservingScroll(body, html, !privateGroupInitialized[conversationId]);
                privateGroupInitialized[conversationId] = true;
                const bubbleMinimized = $('#pg-' + conversationId).attr('data-bubble-minimized') === '1';
                if (bubbleMinimized) {
                    const bubble = $('#better-chat-private-group-bubble-' + conversationId);
                    const lastCounted = parseInt(bubble.attr('data-last-counted-id') || privateGroupLastSeen[conversationId] || 0, 10);
                    const unseen = messages.filter(m => parseInt(m.id_chat || 0, 10) > lastCounted && parseInt(m.id_member_from || 0, 10) !== parseInt(smf_member_id, 10) && parseInt(m.id_member_from || 0, 10) !== 0).length;
                    if (unseen > 0) {
                        const badge = bubble.find('.better-chat-private-bubble-badge');
                        const current = parseInt(badge.text() || 0, 10) || 0;
                        betterChatSetPrivateGroupBubbleUnread(conversationId, current + unseen);
                    }
                    bubble.attr('data-last-counted-id', String(latestId));
                } else {
                    privateGroupLastSeen[conversationId] = latestId;
                    localStorage.setItem('chat_private_group_last_seen', JSON.stringify(privateGroupLastSeen));
                }
            } else {
                body.empty();
                privateGroupInitialized[conversationId] = true;
            }
        }).always(function() { privateGroupPollInFlight[conversationId] = false; });
    }

    function fetchPrivateGroupsSequentially() {
        const ids = [];
        $('.private-group-chat-window').each(function() {
            const id = parseInt($(this).attr('data-conversation-id') || 0, 10);
            if (id && !ids.includes(id)) ids.push(id);
        });
        let chain = $.Deferred().resolve().promise();
        ids.forEach(id => { chain = chain.then(() => fetchPrivateGroup(id)); });
        return chain;
    }

    function betterChatOpenRenameGroup(conversationId) {
        conversationId = parseInt(conversationId || 0, 10);
        if (!conversationId) return;
        const current = $('#pg-' + conversationId).attr('data-group-title') || '';
        $('#better-chat-group-name-dialog').remove();
        $('body').append(`<div id="better-chat-group-name-dialog" class="better-chat-group-name-dialog"><div class="better-chat-group-name-card" role="dialog" aria-modal="true" aria-labelledby="better-chat-group-name-heading"><div class="better-chat-group-name-head"><strong id="better-chat-group-name-heading">Conversation name</strong><button type="button" class="better-chat-group-name-close" title="Close" aria-label="Close">×</button></div><div class="better-chat-group-name-body"><label for="better-chat-group-name-input">Group name</label><input id="better-chat-group-name-input" type="text" maxlength="60" value="${betterChatEscape(current)}" autocomplete="off"><div class="smalltext">Leave it blank to use the participant names automatically.</div></div><div class="better-chat-group-name-foot"><button type="button" class="button better-chat-group-name-auto">Automatic</button><button type="button" class="button better-chat-group-name-cancel">Cancel</button><button type="button" class="button primary_button better-chat-group-name-save" data-conversation-id="${conversationId}">Save</button></div></div></div>`);
        setTimeout(() => $('#better-chat-group-name-input').trigger('focus').select(), 0);
    }

    function betterChatCloseRenameGroup() { $('#better-chat-group-name-dialog').remove(); }

    $(document).on('click', '.better-chat-rename-group', function(e) {
        e.preventDefault(); e.stopPropagation();
        betterChatOpenRenameGroup(parseInt($(this).attr('data-conversation-id') || 0, 10));
    });
    $(document).on('click', '.better-chat-group-name-close, .better-chat-group-name-cancel', betterChatCloseRenameGroup);
    $(document).on('click', '.better-chat-group-name-dialog', function(e) { if (e.target === this) betterChatCloseRenameGroup(); });
    $(document).on('click', '.better-chat-group-name-auto', function() { $('#better-chat-group-name-input').val(''); });
    $(document).on('keydown', '#better-chat-group-name-input', function(e) {
        if (e.key === 'Escape') { e.preventDefault(); betterChatCloseRenameGroup(); }
        if (e.key === 'Enter') { e.preventDefault(); $('.better-chat-group-name-save').trigger('click'); }
    });
    $(document).on('click', '.better-chat-group-name-save', function() {
        const button = $(this);
        const id = parseInt(button.attr('data-conversation-id') || 0, 10);
        const payload = { rename_conversation: 1, conversation_id: id, title: $('#better-chat-group-name-input').val() || '' };
        if (typeof better_chat_session_var !== 'undefined' && better_chat_session_var) payload[better_chat_session_var] = better_chat_session_id;
        button.prop('disabled', true);
        $.post(chatUrl, payload, function(response) {
            if (!response || response.status !== 'ok') {
                window.alert(response && response.message ? response.message : 'Better Chat could not rename that conversation.');
                button.prop('disabled', false);
                return;
            }
            betterChatCloseRenameGroup();
            if (response.conversation) betterChatOpenPrivateGroup(response.conversation);
            fetchPrivateGroup(id);
        }, 'json').fail(function() { button.prop('disabled', false); });
    });

    $(document).on('click', '.better-chat-add-people', function(e) {
        e.preventDefault(); e.stopPropagation();
        betterChatOpenMemberPicker(parseInt($(this).attr('data-conversation-id') || 0, 10));
    });
    $(document).on('input', '#better-chat-member-picker .better-chat-member-search', function() {
        clearTimeout(memberPickerTimer);
        const term = $(this).val();
        memberPickerTimer = setTimeout(() => betterChatSearchMembers(term), 180);
    });
    $(document).on('click', '.better-chat-member-result', function() {
        const id = parseInt($(this).attr('data-id') || 0, 10);
        const name = $(this).text().trim().replace(/\s+@.*$/, '');
        if (id) memberPickerSelected.set(id, {id, name});
        $(this).remove();
        betterChatRenderSelectedMembers();
    });
    $(document).on('click', '.better-chat-member-chip', function() {
        memberPickerSelected.delete(parseInt($(this).attr('data-id') || 0, 10));
        betterChatRenderSelectedMembers();
    });
    $(document).on('click', '.better-chat-member-picker-close, .better-chat-member-picker-cancel', function(e) {
        e.preventDefault(); betterChatCloseMemberPicker();
    });
    $(document).on('click', '#better-chat-member-picker', function(e) {
        if (e.target === this) betterChatCloseMemberPicker();
    });
    $(document).on('click', '.better-chat-member-picker-add', function() {
        if (!memberPickerConversation || !memberPickerSelected.size) return;
        const payload = { add_people: 1, conversation_id: memberPickerConversation, members: Array.from(memberPickerSelected.keys()) };
        if (typeof better_chat_session_var !== 'undefined' && better_chat_session_var)
            payload[better_chat_session_var] = better_chat_session_id;
        const btn = $(this).prop('disabled', true).text('Adding...');
        $.post(chatUrl, payload, function(response) {
            if (!response || response.status !== 'ok') {
                window.alert(response && response.message ? response.message : 'Better Chat could not add those members.');
                btn.prop('disabled', false).text('Add to Conversation');
                return;
            }
            betterChatCloseMemberPicker();
            if (response.conversation) betterChatOpenPrivateGroup(response.conversation);
        }, 'json').fail(function() {
            window.alert('Better Chat could not add those members.');
            btn.prop('disabled', false).text('Add to Conversation');
        });
    });

    $(document).on('click', '.better-chat-minimize-private', function(e) {
        e.preventDefault(); e.stopPropagation();
        const userId = parseInt($(this).attr('data-user-id') || 0, 10);
        if (userId) window.togglePC(userId);
    });

    function betterChatTogglePrivateGroup(conversationId) {
        const id = parseInt(conversationId || 0, 10);
        if (!id) return;

        // Respect the same minimize mode used by one-to-one private chats.
        // In Messenger/bubble mode there should never be an intermediate
        // collapsed header bar: any minimize action goes straight to a bubble.
        if (betterChatUsePrivateBubbles()) {
            if ($('#pg-' + id).attr('data-bubble-minimized') === '1')
                betterChatRestorePrivateGroupFromBubble(id);
            else
                betterChatMinimizePrivateGroupToBubble(id);
            return;
        }

        $('#pg-body-' + id + ', #pg-ft-' + id + ', #pg-' + id + ' .better-chat-group-participants').slideToggle('fast', function() {
            realignWindows();
            saveActiveWindows();
        });
    }

    $(document).on('click', '.better-chat-minimize-group', function(e) {
        e.preventDefault(); e.stopPropagation();
        betterChatTogglePrivateGroup($(this).attr('data-conversation-id'));
    });

    // Use one activation event only. Chromium/Edge on touch-capable Windows
    // can emit touchstart followed by a synthetic click, which toggled twice
    // (bubble -> restored/collapsed) and made group minimization look random.
    // A normal click is emitted for mouse, pen and touch activation.
    $(document).on('click', '.pg-header', function(e) {
        if (fullPageMode) return;
        if ($(e.target).closest('.better-chat-header-actions').length) return;
        e.preventDefault();
        const id = parseInt($(this).attr('data-conversation-id') || 0, 10);
        if (!id) return;
        betterChatTogglePrivateGroup(id);
    });
    $(document).on('click', '.close-pg', function(e) {
        e.preventDefault(); e.stopPropagation();
        const id = parseInt($(this).attr('data-conversation-id') || 0, 10);
        if (!id) return;
        if (!closedPrivateGroups.includes(id)) closedPrivateGroups.push(id);
        localStorage.setItem('chat_closed_private_groups', JSON.stringify(closedPrivateGroups));
        $('#pg-' + id).remove(); $('#better-chat-private-group-bubble-' + id).remove();
        if (fullPageMode) {
            betterChatHubTab('group', id).remove();
            if (betterChatHubIsActive('group', id)) betterChatHubActivate('public', 0, false);
            else betterChatHubRefreshMore();
        }
        betterChatAlignPrivateDock(); realignWindows(); saveActiveWindows();
    });
    $(document).on('click', '.better-chat-leave-group', function(e) {
        e.preventDefault(); e.stopPropagation();
        const id = parseInt($(this).attr('data-conversation-id') || 0, 10);
        if (!id || !window.confirm('Leave this private group conversation?')) return;
        const payload = { leave_conversation: 1, conversation_id: id };
        if (typeof better_chat_session_var !== 'undefined' && better_chat_session_var)
            payload[better_chat_session_var] = better_chat_session_id;
        $.post(chatUrl, payload, function(response) {
            if (!response || response.status !== 'ok') {
                window.alert(response && response.message ? response.message : 'Better Chat could not leave that conversation.');
                return;
            }
            $('#pg-' + id).remove();
            $('#better-chat-private-group-bubble-' + id).remove();
            if (fullPageMode) {
                betterChatHubTab('group', id).remove();
                if (betterChatHubIsActive('group', id)) betterChatHubActivate('public', 0, false);
                else betterChatHubRefreshMore();
            }
            betterChatAlignPrivateDock();
            if (!closedPrivateGroups.includes(id)) closedPrivateGroups.push(id);
            localStorage.setItem('chat_closed_private_groups', JSON.stringify(closedPrivateGroups));
            realignWindows(); saveActiveWindows();
        }, 'json');
    });

    // --- LISTENERS ---
    $(document).on('click', '.better-chat-private-clear', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const userId = parseInt($(this).data('user-id') || 0, 10);
        if (!userId) return;
        const name = $(`#pc-${userId}`).data('username') || 'this member';
        if (!window.confirm(`Clear your private chat with ${name} from your view? This does not delete their copy.`))
            return;

        const payload = { private_clear: 1, target: userId };
        if (typeof better_chat_session_var !== 'undefined' && better_chat_session_var)
            payload[better_chat_session_var] = better_chat_session_id;

        $.post(chatUrl, payload, function(response) {
            if (!response || response.status !== 'ok') {
                window.alert(response && response.message ? response.message : 'Better Chat could not clear this conversation.');
                return;
            }
            $(`#pc-body-${userId}`).empty();
            privateChatInitialized[userId] = true;
            lastSeenPrivateIds[userId] = Math.max(parseInt(lastSeenPrivateIds[userId] || 0, 10), parseInt(response.cleared_through || 0, 10));
            localStorage.setItem('chat_last_seen_private', JSON.stringify(lastSeenPrivateIds));
            betterChatSetPrivateBubbleUnread(userId, 0);
        }, 'json');
    });

    $(document).on('keydown', '#chat-input, .pc-input, .pg-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            let input = $(this);
            let to = input.hasClass('pc-input') ? parseInt(input.data('to') || 0, 10) : 0;
            let conversationId = input.hasClass('pg-input') ? parseInt(input.data('conversation-id') || 0, 10) : 0;
            if (input.val().trim() !== '') {
                const sendPayload = { send: 1, message: input.val(), to: to, conversation_id: conversationId };
                if (typeof better_chat_session_var !== 'undefined' && better_chat_session_var)
                    sendPayload[better_chat_session_var] = better_chat_session_id;
                $.post(chatUrl, sendPayload, function(response) {
                    if (response && response.status === 'error') {
                        if (conversationId > 0) fetchPrivateGroup(conversationId);
                        else if (to > 0) fetchPrivateChat(to); else fetchChat();
                        return;
                    }
                    input.val('');
                    if (conversationId > 0) fetchPrivateGroup(conversationId);
                    else if (to > 0) fetchPrivateChat(to); else fetchChat();
                }, 'json');
            }
        }
    });

    // Attachment buttons intentionally use delegated JS instead of inline onclick
    // so CSP/theme changes cannot break the file chooser.
    $(document).on('click', '.better-chat-attach[data-file-target]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const target = document.getElementById($(this).attr('data-file-target'));
        if (target) target.click();
    });

    $(document).on('change', '#chat-file-upload, .pc-file, .pg-file', function() {
        const input = $(this);
        const file = input.prop('files')[0];
        const to = input.hasClass('pc-file') ? parseInt(input.data('to') || 0, 10) : 0;
        const conversationId = input.hasClass('pg-file') ? parseInt(input.data('conversation-id') || 0, 10) : 0;
        const wrap = input.closest('.better-chat-compose-wrap');
        const progress = wrap.find('.better-chat-upload-progress');
        const track = progress.find('.better-chat-upload-track span');
        const label = progress.find('.better-chat-upload-label');

        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('upload', '1');
        formData.append('to', String(to));
        formData.append('conversation_id', String(conversationId));
        if (typeof better_chat_session_var !== 'undefined' && better_chat_session_var)
            formData.append(better_chat_session_var, better_chat_session_id);

        progress.removeClass('is-error is-success').prop('hidden', false);
        track.css('width', '0%');
        label.text('Uploading 0%');
        input.prop('disabled', true);

        $.ajax({
            url: chatUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'text',
            xhr: function() {
                const xhr = $.ajaxSettings.xhr();

                if (xhr.upload) {
                    xhr.upload.addEventListener('progress', function(event) {
                        if (!event.lengthComputable) return;

                        const percent = Math.max(0, Math.min(100, Math.round((event.loaded / event.total) * 100)));
                        track.css('width', percent + '%');
                        label.text('Uploading ' + percent + '%');
                    });
                }

                return xhr;
            }
        }).done(function(rawResponse) {
            let response = rawResponse;

            if (typeof rawResponse === 'string') {
                const trimmed = rawResponse.trim();

                try {
                    response = JSON.parse(trimmed);
                } catch (e) {
                    const jsonStart = trimmed.indexOf('{');
                    const jsonEnd = trimmed.lastIndexOf('}');

                    if (jsonStart !== -1 && jsonEnd > jsonStart) {
                        try {
                            response = JSON.parse(trimmed.substring(jsonStart, jsonEnd + 1));
                        } catch (ignored) {}
                    }
                }
            }

            if (!response || typeof response !== 'object' || response.status !== 'ok') {
                const message = response && response.message
                    ? response.message
                    : 'Upload completed, but Better Chat could not read the server response.';

                progress.addClass('is-error');
                track.css('width', '100%');
                label.text(message);
                return;
            }

            progress.addClass('is-success');
            track.css('width', '100%');
            label.text('Upload complete');
            input.val('');

            if (conversationId > 0)
                fetchPrivateGroup(conversationId);
            else if (to > 0)
                fetchPrivateChat(to);

            fetchChat();

            window.setTimeout(function() {
                progress.fadeOut(180, function() {
                    progress
                        .removeClass('is-error is-success')
                        .prop('hidden', true)
                        .show();
                    track.css('width', '0%');
                    label.text('');
                });
            }, 1100);
        }).fail(function(xhr) {
            let message = 'Upload request failed.';
            const raw = (xhr && xhr.responseText) ? xhr.responseText.trim() : '';

            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            } else if (raw) {
                try {
                    const parsed = JSON.parse(raw);
                    if (parsed && parsed.message)
                        message = parsed.message;
                } catch (ignored) {}
            }

            progress.addClass('is-error');
            track.css('width', '100%');
            label.text(message);
        }).always(function() {
            input.prop('disabled', false);
        });
    });

    $(window).on('resize', function() { realignWindows(); betterChatAlignPrivateDock(); if (fullPageMode) betterChatHubRefreshMore(); });
    restoreSessions();
    if (fullPageMode) {
        $('#better-chat-private-dock').remove();
        const initialPrivate = parseInt(window.better_chat_full_initial_private || 0, 10);
        const initialGroup = parseInt(window.better_chat_full_initial_group || 0, 10);
        if (initialPrivate > 0) {
            const name = String(window.better_chat_full_initial_private_name || 'Private Chat');
            openPrivateChat(initialPrivate, name, true);
            betterChatHubActivate('direct', initialPrivate, true);
        } else if (initialGroup > 0) {
            const name = String(window.better_chat_full_initial_group_name || 'Private Group');
            betterChatOpenPrivateGroup({id: initialGroup, title: name, participants: []}, true);
            betterChatHubActivate('group', initialGroup, true);
        } else {
            const saved = String(localStorage.getItem('better_chat_hub_active') || 'public:0');
            const parts = saved.split(':');
            const kind = parts[0] || 'public';
            const id = parseInt(parts[1] || 0, 10);
            if (kind === 'public' || betterChatHubTab(kind, id).length)
                betterChatHubActivate(kind, id, false);
            else
                betterChatHubActivate('public', 0, false);
        }
    }
    let betterChatPollTimer = null;

    function scheduleBetterChatPoll(delay) {
        if (betterChatPollTimer)
            clearTimeout(betterChatPollTimer);

        betterChatPollTimer = setTimeout(function() {
            fetchChat().always(function() {
                scheduleBetterChatPoll(document.hidden ? 10000 : 5000);
            });
        }, delay);
    }

    fetchChat().always(function() {
        scheduleBetterChatPoll(document.hidden ? 10000 : 5000);
    });

    document.addEventListener('visibilitychange', function() {
        scheduleBetterChatPoll(document.hidden ? 10000 : 1000);
    });

}

function checkJQuery() { if (window.jQuery) startChatLogic(); else setTimeout(checkJQuery, 100); }
checkJQuery();


// test31h: rerun deterministic Support theme authority after late theme styles settle.
(function () {
    const apply = () => {
        try { betterChatThemeBridge(); } catch (e) {}
        try { betterChatApplySupportThemeAuthority(); } catch (e) {}
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply, { once: true });
    else apply();
    window.addEventListener('load', apply, { once: true });
    setTimeout(apply, 150);
    setTimeout(apply, 700);
})();
