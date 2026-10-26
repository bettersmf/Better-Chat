# Better Chat

**A full-featured real-time chat system for SMF 2.1.x.**

Better Chat adds private chat, group chat, support/customer-service chat, uploads, reactions, moderation controls, restrictions, logging, and Better SMF integrations while keeping the experience native to SMF.

> **Keep SMF familiar. Make it better.**

## Features

- Real-time group chat
- Private one-to-one chat
- Private group conversations
- Messenger-style minimized chat bubbles
- Support / Customer Service mode
- On-duty staff support queue
- Accept, join, minimize, maximize, and end support sessions
- File and attachment uploads
- Better Reactions integration
- Group and private-chat reactions
- Message history and moderation logs
- Private-chat restrictions
- Group-chat restrictions
- Full chat suspension controls
- Restriction reasons and expiration handling
- Online presence and unread indicators
- Persistent minimized chat state
- Admin configuration and moderation tools
- Better Storage integration hooks
- Data-preserving uninstall behavior unless destructive cleanup is explicitly requested

## Current Version

**1.0.0**

Better Chat 1.0.0 is the first stable public release.

It promotes the extensively tested test38 development line after a final release audit covering session validation, upload security, install/uninstall symmetry, database cleanup, hook removal, Better Reactions lifecycle cleanup, and reused-message-ID hardening.

## Compatibility

- Simple Machines Forum **2.1.x**
- PHP 8.x friendly
- Designed to work with Curve2 and Better SMF themes
- Optional Better SMF integrations remain inactive when the corresponding MOD is not installed

## Support / Customer Service Mode

Better Chat can operate as a customer-service chat system where guests or members can open a support conversation with on-duty staff.

Support sessions include:

- staff availability
- support queue
- session acceptance
- joined-staff notices
- minimize/maximize behavior
- end-chat controls
- support history and logging

## Private & Group Chat

Private conversations use compact Messenger-style chat windows and bubbles.

Better Chat also supports private group conversations, allowing multiple members to participate in a dedicated conversation rather than forcing everything into the main group chat.

## Reactions

When Better Reactions & Likes is installed, chat messages can participate in the shared Better SMF reaction system.

Better Chat includes lifecycle cleanup so reactions are removed when the underlying chat message is permanently deleted. The 1.0.0 release also hardens newly inserted chat IDs against stale reaction rows attaching to reused numeric message IDs.

## Uploads & Storage

Chat attachments are supported directly and can participate in Better Storage integration when available.

The stable release includes SMF session validation for upload actions and normal chat sends.

## Moderation & Restrictions

Staff can restrict:

- private chat only
- group chat only
- all chat activity

Restrictions can include a reason and duration, and affected users receive clear UI feedback.

## Installation

1. Download the latest Better Chat package from the GitHub **Releases** section.
2. In SMF, open **Administration Center → Main → Package Manager → Download Packages**.
3. Upload the Better Chat ZIP.
4. Install the package normally.
5. Configure Better Chat from the SMF Administration Center.

## Uninstall Behavior

Better Chat is designed to preserve data unless destructive cleanup is explicitly requested.

When destructive cleanup is selected, the stable package removes Better Chat database tables, settings, hooks, admin icons, and related helper files.

## Better SMF Integrations

Better Chat is designed to work with other Better SMF MODs, including:

- Better Reactions & Likes
- Better Storage
- Better Portal
- Better Dark Mode / Theme Lab themes

## Support & More Better SMF MODs

Better Chat is part of the **Better SMF** project.

Visit **https://bettersmf.com/** for support, discussion, downloads, and the rest of the Better SMF MOD ecosystem.

## Development Philosophy

Better SMF MODs are built around a simple idea:

**Keep SMF familiar. Make it better.**

Better Chat follows that philosophy by adding a capable modern chat system without replacing the forum experience with a separate application.

## License

A project license will be published separately. Until then, all rights are reserved unless otherwise stated.
