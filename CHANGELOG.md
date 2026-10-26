# Better Chat Changelog

## 1.0.0

First stable public release.

Promoted from the extensively tested test38 development line after a final release audit.

### Major features

- Real-time group chat
- Private one-to-one chat
- Private group conversations
- Messenger-style minimized chat bubbles
- Support / Customer Service mode
- Staff support queue and session acceptance
- Uploads and attachments
- Better Reactions integration
- Moderation logs and chat history
- Private/group/full-chat restrictions
- Presence and unread indicators
- Better Storage integration hooks
- Data-preserving uninstall behavior

### Stable-release hardening

- Added SMF session validation to normal message sends
- Added SMF session validation to attachment uploads
- Added matching JavaScript session-token handling
- Verified install/uninstall integration-hook symmetry
- Completed destructive cleanup coverage for Better Chat database tables
- Completed destructive cleanup coverage for Better Chat settings
- Included private-group conversation tables in destructive cleanup
- Included Giphy-related settings in destructive cleanup
- Added Better Chat admin-icon cleanup on uninstall
- Retained CAPTCHA/raw-response protection
- Retained Better Storage handshake behavior
- Retained Better Reactions permanent-delete cleanup

### Reaction lifecycle / ID-reuse hardening

The final test38d pass prevents a newly inserted chat message from inheriting a stale Better Reactions row belonging to an older deleted message that previously used the same numeric chat ID.

Coverage includes:

- main group-chat messages
- direct/private messages
- attachment/upload messages
- private-group conversation messages

## test38 development line

The test38 series focused on release stabilization, reaction lifecycle correctness, moderation polish, support/private-group behavior, and final compatibility hardening leading into 1.0.0.
