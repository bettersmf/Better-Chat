# Better Chat Changelog

## 1.0.1

Maintenance and stable-release audit update.

### Upgrade coverage

- Added direct upgrade support from Better Chat 1.0.0.
- Added direct upgrade support for late test builds omitted by the 1.0.0 manifest: test36, test36a, test36c, test36d, test37, test37d, and test38a.
- No database schema changes.

### Verified

- Message-send and upload session validation
- Upload MIME and size validation
- Private-group membership enforcement
- Support-session permission/session checks
- Better Reactions lifecycle cleanup and reused-message-ID hardening
- Better Storage/S3 integration
- Moderation, restrictions, retention, and history cleanup
- Install/uninstall hook symmetry and complete destructive cleanup coverage
- Data-preserving uninstall behavior

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
- Completed destructive cleanup coverage for Better Chat database tables and settings
- Included private-group conversation tables and Giphy-related settings in destructive cleanup
- Added Better Chat admin-icon cleanup on uninstall
- Retained CAPTCHA/raw-response protection
- Retained Better Storage handshake behavior
- Retained Better Reactions permanent-delete cleanup

### Reaction lifecycle / ID-reuse hardening

The final test38d pass prevents newly inserted chat messages from inheriting stale Better Reactions rows belonging to older deleted messages that previously used the same numeric chat ID.
