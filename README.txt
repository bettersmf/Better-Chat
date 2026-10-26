Better Chat 1.0.1 — Stable Release

Better Chat 1.0.1 is a maintenance release following a full stable-package audit.

Highlights:
- Real-time group chat, private chat, and private group conversations.
- Messenger-style minimized chat bubbles.
- Support / Customer Service mode with staff queue and session handling.
- Image/video uploads with server-side validation.
- Better Reactions lifecycle integration.
- Better Storage / S3 integration hooks.
- Moderation, restrictions, retention, logs, and full-page chat.
- Complete direct upgrade coverage from 1.0.0 and late test builds omitted by the previous stable manifest.
- Data-preserving uninstall behavior unless destructive cleanup is explicitly selected.
- No database schema changes.

Historical development notes follow below.

Better Chat 1.0.0-test34 — Full-Page Group Chat

- Adds a pop-out/open-full-page control to Community / Group Chat.
- New action=chat_full renders the same live Group Chat in a large dedicated SMF page.
- Reuses the proven chat_msg backend, reactions, uploads, presence, moderation and retention.
- Full-page chat ignores the floating widget's minimized state and stays expanded.
- Dedicated page is members-only and only available in Community/Hybrid modes.
- Floating/private/support chat behavior remains unchanged.

Better Chat 1.0.0-test33e — Better Reactions Lifecycle Cleanup

- Permanently deleted Group and Private chat messages now clear their Better Reactions `chat_message` reaction records first.
- Covers admin private purge, Group Chat history clear, automatic retention pruning, Clear All History, and destructive uninstall.
- Member-side private "Clear" remains view-only and intentionally preserves reactions because the underlying messages still exist.
- Cleanup remains available when reaction display is disabled, provided the Better Reactions object API is still installed.
- Uses Better Reactions public object APIs rather than hard-coding Better Reactions database table names.

Better Chat 1.0.0-test33d — History Cleanup Layout Polish

- Moves Clear All History from Uploads into History and Cleanup.
- No retention, pruning, upload, moderation, login, or suspension behavior changed.

Better Chat 1.0.0-test33c — Granular Auto-Prune & Retention Policies

Better Chat 1.0.0-test32w — Chrome Immediate Login Bootstrap

- Built from test32v.
- Guest login bridge now detects SMF login2 via form submit, click/Enter, fetch, and XMLHttpRequest/jQuery.
- Starts a short authentication probe as soon as the actual login request is observed.
- Reloads exactly once after SMF confirms authentication so Better Chat or the suspension indicator renders immediately.
- Keeps the test32v auth probe endpoint and all moderation/presence behavior unchanged.

Better Chat 1.0.0-test32v — AJAX Login Bridge Fix
====================================================
- Built from test32u.
- Handles SMF 2.1 AJAX login leaving the original guest page DOM in place.
- Guest pages get a dormant login bridge only when Community/Hybrid Chat is enabled.
- After a login form submits, the bridge probes Better Chat authentication and performs one clean reload only after SMF confirms the member session.
- Adds a read-only auth_probe response before the normal guest rejection.
- Full-page/non-AJAX login behavior is unchanged.

Better Chat 1.0.0-test32u — First Login Bootstrap Fix
=========================================================
- Built directly from test32s as requested.
- Uses SMF $user_info as the authoritative login identity during integrate_load_theme.
- Fixes the first rendered page after login being treated with stale theme-context user state.
- Uses authoritative member ID / guest state for Better Chat and Support bootstrap.
- Removes a duplicate Support <script> opener found in test32s.

Better Chat 1.0.0-test31a
Support bubble placement + header minimize polish.

Better Chat 1.0.0-test30b - Messenger State-Transition Reflow Fix

Better Chat 1.0.0-test1
========================

Foundation build combining the stronger Custom Chat MB feature set with a new
theme-adaptive presentation layer designed for SMF 2.1 and Theme Lab themes.

Test1 goals:
- Preserve public chat, online users, private chats, typing/read behavior,
  uploads, persistence, mobile behavior, purge/settings controls.
- Remove the hard-coded light-theme presentation.
- Sample the currently active SMF theme at runtime for surfaces, header,
  text, links, borders, inputs, buttons and radius.
- Keep Better Chat CSS in the default theme so Theme Lab-generated themes do
  not need their own copied BetterChat.css.
- Remove dependency on the old attach.png image.

This is intentionally a test build. Existing chat tables are updated in place.


Test2 attachment restoration
----------------------------
- Restores public and private image attachment controls with delegated JS.
- Adds jpeg/webp support to the default extension set.
- Validates MIME type and upload size server-side.
- Re-creates/checks chat_uploads at upload time and reports permission errors.
- Returns real JSON success/error responses instead of silently reporting success.
- Displays upload progress/success/error feedback beside the compose field.
- Uses a thumbnail when available and falls back to the original image if thumbnail
  generation is unavailable.
- Adds an explicit upgrade path from 1.0.0-test1.


Test3 response-handling fix
---------------------------
- Fixes false "Upload failed" feedback when the image and thumbnail were
  actually uploaded successfully.
- AJAX endpoint now clears buffered markup before emitting JSON.
- Client parses the response defensively and distinguishes transport failures
  from response-format failures.
- Keeps Test2 attachment validation and Theme Lab styling intact.


Test4 duplicate-render + upload progress fix
--------------------------------------------
- Replaces text beside the paperclip with a real upload progress bar below
  the message input.
- Uses XHR upload progress for percentage feedback.
- Success/failure feedback now lives below the input and clears after success.
- Adds PHP and JavaScript one-time guards so Better Chat cannot render or boot
  twice in the same page request.
- Normalizes legacy Custom Chat integration hooks during install/upgrade.
  This addresses the duplicate white + themed chat windows seen after package
  installs and normal page/header navigation.
- Keeps the Test3 upload-response fix intact.


Test5 SMF AJAX ghost-window fix
-------------------------------
- Fixes the duplicate/ghost Forum Chat window that appeared after opening
  Profile, My Messages, Alerts, and during AJAX-driven package/admin actions.
- Better Chat now renders only for complete forum page requests.
- Explicitly skips SMF requests carrying X-SMF-AJAX, ;ajax, ;xml, and Better
  Chat's own action endpoint.
- Retains the Test4 upload progress bar and legacy-hook normalization.
- Restores an explicit application/json response header for chat JSON replies.


Test6 Theme Lab background bridge
---------------------------------
- Fixes chat header and own-message bubbles falling back to generic Curve2 blue.
- Theme bridge now captures both background-color and background-image.
- Theme Lab gradient/header/button treatments therefore carry into Better Chat.
- Keeps Test5 SMF AJAX ghost-window suppression and Test4 upload progress.


Test7 Smart Scroll
------------------
- Public group chat no longer snaps back to the bottom on every AJAX poll.
- If the reader is within 36px of the bottom, new messages continue to follow.
- If the reader scrolls upward, their historical reading position is preserved.
- Initial chat load still opens at the newest message.
- Private chat windows use the same smart-scroll behavior.
- Keeps Test6 Theme Lab colors, Test5 Casper fix, and Test4 upload progress.


Test8 Emoji Studio: Theme-aware Unicode picker, categories, search, recent emojis, cursor insertion, public/private chat.


Test9 Emoji Polish
------------------
- Emoji and attachment triggers now live inside the message input shell.
- Emoji picker closes immediately after an emoji is chosen and returns focus
  to the message field.
- Emoji category strip no longer needs a horizontal scrollbar.
- Emoji grid now scrolls vertically and includes bottom breathing room so the
  last row is never clipped.
- Adds deterministic bubble styling for Chromium/Edge instead of relying on
  fragile cascade/color-mix behavior for the core bubble surfaces.
- Keeps Test8 Emoji Studio, Test7 Smart Scroll, Theme Lab bridge, Casper fix,
  attachments, thumbnails and upload progress.


Test10 Emoji Scroll + Expanded Library
--------------------------------------
- Expands every emoji category substantially so categories contain real
  scrollable libraries instead of only a few visible rows.
- Gives the emoji grid an explicit vertical scroll viewport.
- Adds a visible themed scrollbar in Chromium/Edge/Chrome.
- Keeps Test9 integrated input icons, close-after-selection behavior and
  Edge bubble styling.


Test11 Private Notification Intelligence
----------------------------------------
- Fixes public/group chat header blinking because of new private messages.
  Public header now watches public message IDs only.
- Private chat headers blink only for new incoming private messages.
- Adds optional notification sound for private messages when the private chat
  is not focused or the browser page/tab is not focused.
- Adds optional browser-title blinking while the page is in the background:
  "Hey, you got a message from <name>!"
- Sound and title notifications can be enabled/disabled independently in the
  Better Chat admin settings.
- Notifications are deduplicated by private message ID so polling does not
  replay the same alert every few seconds.
- Returning focus restores the normal browser title.


Test12 Notification Controls + Admin Language
---------------------------------------------
- Admin can customize browser title alert text with {username}; default is
  "{username} has sent you a new message..."
- Adds a master notification-sound setting plus an option allowing members
  to choose their own sound preference.
- Members get a speaker toggle in group and private chat headers when allowed;
  preference is stored locally in that browser.
- Enabling sound from the header plays a short confirmation tone.
- Emoji and attachment actions are visually icon-only: no button box,
  border, shadow, hover fill, or radius.
- Message inputs reserve enough right-side space so text cannot run behind
  the emoji/attachment controls.
- Reworks the English admin language file so section headings, settings,
  purge choices, uploads, and history controls are language-backed.


Test13 Admin Language Load Fix
------------------------------
- Explicitly loads Better Chat language strings before admin settings are built.
- Adds safe English fallbacks for every Better Chat admin setting.
- Clear History callback independently loads its language strings.
- Test12 chat behavior is otherwise unchanged.


Test14 AJAX / Database Hardening
--------------------------------
- Prevents overlapping public polls when a previous AJAX request is still active.
- Prevents duplicate overlapping private polls for the same member.
- Polls multiple open private chats sequentially instead of firing them together.
- Private polls no longer repeat the forum-wide online-member query.
- Auto-purge maintenance runs only from the public poll.
- Replaces setInterval with request-aware scheduling: 5 seconds while visible,
  10 seconds in a background tab, quick refresh on return.
- Consolidates chat JSON exits through the clean Better Chat JSON responder.
- Reduces transient PHP/MySQL connection pressure that can surface as a false
  mysqli handle during aggressive polling.


Test15 Install Screen + Sound Control Fix
-----------------------------------------
- Better Chat is suppressed throughout SMF Package Manager, avoiding the temporary
  Curve2-styled, non-populated chat shell shown during install/extract/upgrade pages.
- Normal forum and admin pages continue to render Better Chat.
- Sound-toggle clicks no longer bubble into group/private header minimize behavior.
- Private chat headers use delegated toggle handling and explicitly ignore sound/close controls.
- Pointer/touch propagation guards stop synthesized mobile clicks from toggling chat state.
- Keeps Test14 AJAX/database hardening intact.


Test16 No-Flash Load
--------------------
- Better Chat is hidden at the HTML level before the browser can paint its
  unstyled/default shell.
- Persisted minimized/maximized state is applied while the widget is hidden.
- Theme Bridge and sound-button state are applied before the widget is revealed.
- The chat becomes visible on the next animation frame only after initialization.
- Prevents the split-second maximized, unstyled/default-font flash between pages
  and during refreshes.
- Keeps Test15 Package Manager suppression and sound-control event isolation.


Test17 Environment, Cache & Video Capabilities
----------------------------------------------
- Adds an Environment & Capabilities panel to Better Chat admin settings.
- Detects active SMF cache API plus Memcached, Memcache, Redis and APCu PHP support.
- Better Chat uses SMF's configured cache API when active; otherwise APCu is used
  when available. It does not guess Memcached/Redis server addresses merely because
  a PHP extension exists.
- Caches the online-user list for 15 seconds to reduce repeated database reads.
- Detects FFmpeg automatically from common paths or PATH, with an admin path override.
- Video attachments are exposed only when enabled, FFmpeg is detected, and PHP exec
  is available.
- Adds MP4/WebM attachment support, configurable video size/extensions, FFmpeg-based
  validation/poster generation, and inline HTML5 video playback.
- Keeps Test16 no-flash initialization and all prior notification/chat behavior.


Test18 Header Minimize Button Fix
---------------------------------
- Restores the dedicated group-chat minimize/maximize button.
- #chat-toggle now has its own isolated click handler instead of being ignored by
  the group-header handler with no follow-up action.
- Button changes from minus to plus while minimized and back to minus when open.
- ARIA label/title follow the current state.
- Header clicks and button clicks keep localStorage minimized state synchronized.
- Pointer/touch propagation remains isolated from the sound button/header.


Test22 Header Minimize Capture Fix
----------------------------------
- Replaces the competing group-header/button toggle paths with one shared toggle routine.
- Binds #chat-toggle directly in the capture phase so header/theme event handlers cannot
  swallow the click before Better Chat receives it.
- Keeps + / minus text, tooltip, ARIA label and localStorage state synchronized for both
  button clicks and normal group-header clicks.
- Preserves the sound-button isolation and all Test18 behavior.


1.0.0-test24 - Better Reactions Integration
- Adds optional Better Reactions & Likes integration with no hard dependency.
- Chat messages are first-class `chat_message` reaction objects.
- Supports Like and all enabled custom reactions with one active reaction per member/message.
- Adds hover/touch reaction picker, reaction count pills, and topic-style Who Reacted panels.
- Reaction overlays portal outside public/private chat scrollers to prevent clipping.
- Private-message reaction access is restricted to the two conversation participants.
- Falls back to normal Better Chat when Better Reactions is unavailable or disabled.


1.0.0-test26 - Reliable Incoming Private Window Discovery
- Adds a dedicated recipient-only unread private-message signal to the public poll.
- New private windows no longer depend on the mixed 20-message group/private payload.
- Prevents busy group chat or reaction summary work from starving receiver-side PM popups.
- Normalizes stored closed-window member IDs before comparisons.
- Keeps the original mixed-payload auto-open logic as a compatibility fallback.


1.0.0-test28 - Composer Action Clearance
- Increases the protected right-side text inset in public and private chat inputs.
- Prevents typed text from flowing beneath the emoji and attachment controls.
- Keeps existing composer icon placement and behavior unchanged.


Test30a Messenger private-window anchor fix
-------------------------------------------
- When the Messenger group chat is minimized to its bubble, open private-chat windows now anchor immediately to the left of that bubble.
- When the full group chat is open, private-chat windows continue to anchor immediately to the left of the full group panel.
- Multiple private windows continue stacking leftward from whichever group-chat state is actually visible.


Test31 - Support / Customer Service foundation
- Admin operating modes: Community, Support, Hybrid.
- Optional member and guest support access.
- Configurable support staff membergroups; administrators always qualify.
- Staff On Duty toggle and waiting-customer queue.
- Claim, private support conversation, send, poll, close/cancel flow.
- Guest support uses a server-side session token and optional display name; no account or email required.
- Support runs alongside Messenger/community chat without changing test30b window reflow behavior.


Test31c: Support polling is read-only, send requests are guarded against replay, and Support-only mode now receives the same active-theme palette bridge as group/private chat.

Test31d: Support conversation polling keeps the composer DOM mounted so typed text/focus survive refreshes; composer layout is locked to input + natural-width Send button.

Test31e: Support panel gets full floating-window border/radius, exact sampled theme background paint, and a hardened flex composer so Send remains fully visible.


1.0.0-test31i
- Support chat screenshot uploads (image-only, admin-toggleable).
- Preserved-data uninstall keeps Better Chat settings/tables/uploads.
- Hook cleanup always runs, preventing stale integrations when data is preserved.
- Reinstall/upgrade seeds only missing defaults and never overwrites preserved settings.


Test31j Support composer button parity
- Support Send is a native SMF .button control with no Better Chat color override.
- Screenshot attachment is icon-only, matching the regular Better Chat composer.
- Theme Lab/theme CSS owns Support button color, gradient, border and hover state directly.


1.0.0-test31k
- End Chat and Send are explicit Support primary actions.
- Theme Lab themes source those two actions from the active theme's --tl-primary palette token and current theme text/radius settings, not Curve2/default .button paint.
- Hover/focus uses the active theme accent token.
- Other Support controls remain unchanged.


1.0.0-test31l
- Adds a professional support-system event when staff accepts a waiting support request.
- The accepted staff display name is shown as: {username} has joined the chat.
- System events render centered with subtle divider lines rather than as normal message bubbles.

1.0.0-test31m
- Support staff queue now uses a compact auto-height panel instead of reserving full conversation height.
- Waiting customers render as proper queue cards with a dedicated Accept chat action.
- Support panel expands to conversation height only after a session is accepted.


1.0.0-test31n
- Customer support waiting state now opens at the full standard 500px Support height immediately after a request is created.
- Staff idle/waiting queue remains compact and auto-height.
- Active support conversations remain full-height.
- Separates customer waiting state from generic idle state so the compact queue CSS cannot bleed into the requester window.


1.0.0-test31q
- Hybrid Messenger bubble rail is now deterministic across browsers.
- Group Chat bubble owns the bottom slot.
- Support bubble anchors directly above the rendered Group Chat bubble.
- Minimized private chats stack above the highest visible Group/Support bubble.
- Bubble positioning uses actual viewport rectangles instead of browser-sensitive parallel offsets.


Better Chat 1.0.0-test32 - Moderation & Abuse Control Foundation
---------------------------------------------------------------
- Adds Admin > Chat Moderation as a dedicated abuse-management area.
- Scoped restrictions: Group Chat, Private Chat, Support, or full Better Chat suspension.
- Timed penalties: 15 minutes, 1 hour, 24 hours, 7 days, 30 days, or permanent.
- Internal moderator reason/note required for every restriction.
- Active restriction list with one-click revoke.
- Moderation history records applying moderator, timestamps, expiry, revoke status, and revoking moderator.
- Server-side enforcement prevents bypass through JavaScript or direct endpoint requests.
- Full suspension suppresses Better Chat UI for the restricted member.
- Support bans prevent customer support requests/messages/uploads while preserving normal forum access.
- Group/private restrictions block sending and attachment uploads only in their assigned scope.
- Restrictions are stored in chat_restrictions and follow Better Chat's preserve-data uninstall architecture.


Better Chat 1.0.0-test32a - Unified Admin Navigation
----------------------------------------------------
- Replaced separate Better Chat Settings and Chat Moderation admin entries with one Better Chat parent area.
- Added SMF-native Better Chat subsections: Settings and Moderation.
- Updated settings, cleanup, moderation apply, and revoke URLs to stay inside the Better Chat parent area.
- No moderation enforcement or chat behavior changed in this pass.


Better Chat 1.0.0-test32m - Rollback Baseline
- Restores Group Chat and online-member behavior to the last known-good test32g code.
- Removes all Group Chat polling/member-list experiments introduced in test32h through test32k.
- Keeps the existing admin/moderation foundation and test32g restriction-screen code unchanged.
- This build is intentionally a stabilization baseline before the Group Chat restriction notice is revisited.


Better Chat 1.0.0-test32n - Restriction & Ban Presentation
-----------------------------------------------------------
- Built directly on the green test32m Better Chat live-presence baseline.
- Group Chat restrictions render directly inside the proven #chat-body canvas.
- Group message rendering pauses while restricted; Online presence remains active.
- Restricted Group Chat members are visually grayed in the Online list without changing the presence query.
- Private restriction notice handling uses explicit hidden-attribute removal/addition.
- Support restriction notices include remaining-time information.
- Full Better Chat suspension now renders a clear reason/duration/expiry notice instead of silently removing Chat.

--- 1.0.0-test33 ---
- Added participant-owned private chat Clear control. Clearing affects only that member's view.
- Added chat_private_state so cleared conversations stay hidden until a new message arrives.
- Added Better Chat administrative audit log (no private message bodies).
- Logs now records restriction apply/revoke, full history clears, and permanent private-chat purges.
- Added Admin Logs private-chat purge workflow with two-member live lookup and explicit confirmation.
- Permanent private purge deletes both participants' message rows, participant clear-state rows, and Better Chat-uploaded attachments referenced by that conversation.


--- 1.0.0-test33a ---
- Polished Admin -> Better Chat -> Logs with action type, activity scope, member/moderator and date filters.
- Added 50-row pagination to administrative audit events.
- Added Group Chat-only retention summary and a dedicated audited Clear Group Chat History action.
- Group-only clear preserves private conversations, private clear-state records, support sessions and audit history.
- Group/private activity and support session metadata honor the shared member/date filters.
- Private message bodies remain excluded from the Logs page.


--- 1.0.0-test33b ---
- Better Chat Logs now render actor/member references as clickable SMF profile links.
- Each member reference includes an [activity] shortcut into an exact member-scoped Logs view.
- Added member activity summary cards for Group, Private sent/received, Support, audit involvement and active restrictions.
- Added human-readable audit summaries while retaining the original audit detail text.
- Exact member activity views filter Community/Private activity, audit actions and Support metadata without loading message bodies.


TEST35A - PRIVATE GROUP CONVERSATION FOUNDATION
------------------------------------------------
This build introduces the underlying conversation/participant model needed for future private group chats while deliberately preserving the proven one-to-one private chat UI. Existing private history is not rewritten during install; it is linked lazily the first time a direct conversation is used.


Better Chat 1.0.0-test35e - Stage 2C Private Group Polish
Custom private-group names, compact participant presentation, and Messenger-style minimized private-group bubbles with stacked initials.


Better Chat 1.0.0-test35f - Emoji-Only Message Sizing
------------------------------------------------------
Unicode emoji-only messages now render at a larger size. Messages that mix emoji with ordinary text keep the normal message size.


Better Chat 1.0.0-test36b - GIPHY Picker Viewport Position Fix
---------------------------------------------
Optional GIPHY integration can be enabled in Better Chat Settings. Enter a GIPHY Web API key, enable the picker, and members receive a GIF button in supported chat composers. The picker includes Trending, Search, and Powered by GIPHY attribution.


Better Chat 1.0.0-test36c - Private Group Conversion Sync + Composer Fix
- Original direct window now converts cleanly to the private group for both original participants.
- Converted group history no longer reopens a duplicate direct window.
- Private-group composer now matches the public/direct GIF-era action-rail spacing.


Better Chat 1.0.0-test36e - Restore-to-Latest + Edge Minimize Fix
- Restored private/private-group bubbles open at the newest message.
- Smart polling still preserves manual scroll-up position.
- Edge touch/click double-toggle on private-group headers removed.


Better Chat 1.0.0-test36f - Deterministic Restore-to-Latest Scroll
- Explicit chat opens/restores now settle at the newest message even across page navigation and late media layout.
- Manual history scrolling cancels the temporary bottom pin and remains preserved during polling.


Better Chat 1.0.0-test37 - Stage 3A Full-Page Conversation Hub
- Adds Public Chat/direct/private-group tabs to the existing full-page Better Chat view.
- Adds unread badges, desktop More overflow, mobile horizontal tab scrolling, and private-chat pop-out links.
- Full-page background polling preserves unread state until the user opens that conversation.


Better Chat 1.0.0-test37a - Full-Page Single-Conversation + Header Icon Fix

- Full-page hub now hides Public Chat completely whenever a private/direct or private-group tab is selected.
- Switching back to Public Chat restores the public canvas cleanly.
- Header action symbols are forced to the configured header text color in normal, visited, hover, focus, and active states.
- Prevents previously-clicked header links/icons from turning dark or theme-link colored.


Better Chat 1.0.0-test37b - Firefox Full-Page Scroll Stability

- Prevents Firefox native scroll anchoring from fighting Better Chat during full-page AJAX refreshes.
- Public, direct, and private-group full-page message panes keep an exact stable scroll position between polls.
- Chrome/Edge behavior and normal floating chat windows are unchanged.



Better Chat 1.0.0-test37d - Raw Response / CAPTCHA Safety
- Prevents Better Chat from injecting HTML/JavaScript into SMF verification CAPTCHA image responses.
- Skips Better Chat page output on verificationcode, dlattach, and chat_msg raw endpoints.
- Retains the test37c Firefox deterministic message-anchor scroll fix.


Better Chat 1.0.0-test38a - Better Storage Handshake Fix
- Repairs Better Storage Connected status registration.
- CAPTCHA/raw-response guard remains unchanged.
