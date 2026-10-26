<?php

if (!defined('SMF'))
	die('Hacking attempt...');


function better_chat_function_available($function)
{
	$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
	return function_exists($function) && !in_array($function, $disabled, true);
}

function better_chat_find_ffmpeg()
{
	global $modSettings;

	static $detected = null;
	if ($detected !== null)
		return $detected;

	$configured = !empty($modSettings['chat_ffmpeg_path']) ? trim($modSettings['chat_ffmpeg_path']) : '';
	if ($configured !== '' && is_file($configured) && is_executable($configured))
		return $detected = $configured;

	foreach (array('/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/local/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg') as $candidate)
		if (is_file($candidate) && is_executable($candidate))
			return $detected = $candidate;

	if (better_chat_function_available('exec')) {
		$output = array();
		$status = 1;
		@exec('command -v ffmpeg 2>/dev/null', $output, $status);

		if ($status === 0 && !empty($output[0])) {
			$candidate = trim($output[0]);
			if ($candidate !== '' && is_executable($candidate))
				return $detected = $candidate;
		}
	}

	return $detected = '';
}

function better_chat_cache_backend()
{
	global $modSettings;

	if (!empty($modSettings['cache_enable']) && function_exists('cache_get_data') && function_exists('cache_put_data'))
		return 'SMF cache API';

	if (function_exists('apcu_fetch') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN))
		return 'APCu';

	return '';
}

function better_chat_cache_get($key)
{
	$backend = better_chat_cache_backend();

	if ($backend === 'SMF cache API')
		return cache_get_data('better_chat_' . $key, 30);

	if ($backend === 'APCu') {
		$success = false;
		$value = apcu_fetch('better_chat_' . $key, $success);
		return $success ? $value : null;
	}

	return null;
}

function better_chat_cache_put($key, $value, $ttl = 15)
{
	$backend = better_chat_cache_backend();

	if ($backend === 'SMF cache API') {
		cache_put_data('better_chat_' . $key, $value, (int) $ttl);
		return true;
	}

	if ($backend === 'APCu')
		return (bool) apcu_store('better_chat_' . $key, $value, (int) $ttl);

	return false;
}






/**
 * Better Storage adapter helpers (test38).
 *
 * Storage integration is intentionally isolated from Better Chat's response/
 * asset hooks so raw endpoints such as SMF CAPTCHA remain untouched.
 */
function better_chat_storage_available()
{
    global $sourcedir, $modSettings;

    if (!function_exists('betterstorage_source_config') && is_file($sourcedir . '/BetterStorage.php'))
        require_once($sourcedir . '/BetterStorage.php');

    $available = function_exists('betterstorage_source_config')
        && function_exists('betterstorage_s3_put_file')
        && function_exists('betterstorage_s3_presign_get');

    if ($available && empty($modSettings['betterstorage_integration_chat']) && function_exists('updateSettings')) {
        updateSettings(array('betterstorage_integration_chat' => 1));
        $modSettings['betterstorage_integration_chat'] = 1;
    }

    return $available;
}

function better_chat_storage_config()
{
    if (better_chat_storage_available())
        return betterstorage_source_config('chat');
    return array('backend' => 'local');
}

function better_chat_storage_mime($path, $fallback = 'application/octet-stream')
{
    if (function_exists('finfo_open') && is_file($path)) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) @finfo_file($finfo, $path);
            @finfo_close($finfo);
            if ($mime !== '')
                return $mime;
        }
    }
    return (string) $fallback;
}

function better_chat_storage_commit_file($local_path, $scope, $name, $owner_id = 0, $reference_id = 0, $mime = '')
{
    global $boarddir;

    $name = basename((string) $name);
    $scope = $scope === 'support' ? 'support' : 'messages';
    if ($name === '' || !is_file($local_path))
        return false;

    $size = (int) @filesize($local_path);
    $mime = $mime !== '' ? $mime : better_chat_storage_mime($local_path);

    if (!better_chat_storage_available())
        return array('backend' => 'local', 'key' => 'chat_uploads/' . $name);

    $config = better_chat_storage_config();
    if (!empty($config['backend']) && $config['backend'] === 's3') {
        $relative = $scope . '/' . $name;
        $put = betterstorage_s3_put_file($config, $relative, $local_path, $mime);
        if (empty($put['ok'])) {
            log_error('[Better Chat] Better Storage S3 upload failed for ' . $name . ': ' . (!empty($put['error']) ? $put['error'] : 'Unknown error'), 'general');
            // Fail safe: keep the already-written local file and track it locally.
            if (function_exists('betterstorage_register_object'))
                betterstorage_register_object('chat', 'local', 'chat_uploads/' . $name, $size, $mime, (int) $owner_id, (int) $reference_id);
            return array('backend' => 'local', 'key' => 'chat_uploads/' . $name);
        }

        if (function_exists('betterstorage_register_object'))
            betterstorage_register_object('chat', 's3', $relative, $size, $mime, (int) $owner_id, (int) $reference_id);
        @unlink($local_path);
        return array('backend' => 's3', 'key' => $relative);
    }

    if (function_exists('betterstorage_register_object'))
        betterstorage_register_object('chat', 'local', 'chat_uploads/' . $name, $size, $mime, (int) $owner_id, (int) $reference_id);
    return array('backend' => 'local', 'key' => 'chat_uploads/' . $name);
}

function better_chat_storage_urls($scope, $name, $is_video = false)
{
    global $boarddir, $boardurl;

    $scope = $scope === 'support' ? 'support' : 'messages';
    $name = basename((string) $name);
    if ($name === '')
        return array('full' => '', 'thumb' => '');

    $thumb_name = $is_video ? 'thumb_' . $name . '.jpg' : 'thumb_' . $name;
    $local_full = $boardurl . '/chat_uploads/' . rawurlencode($name);
    $local_thumb_path = $boarddir . '/chat_uploads/' . $thumb_name;
    $local_thumb = is_file($local_thumb_path) ? ($boardurl . '/chat_uploads/' . rawurlencode($thumb_name)) : $local_full;

    if (!better_chat_storage_available())
        return array('full' => $local_full, 'thumb' => $local_thumb);

    $config = better_chat_storage_config();
    if (empty($config['backend']) || $config['backend'] !== 's3')
        return array('full' => $local_full, 'thumb' => $local_thumb);

    $full = betterstorage_s3_presign_get($config, $scope . '/' . $name, 300);
    $thumb = betterstorage_s3_presign_get($config, $scope . '/' . $thumb_name, 300);
    if ($full === '')
        $full = $local_full;
    if ($thumb === '')
        $thumb = $full;
    return array('full' => $full, 'thumb' => $thumb);
}

function better_chat_add_attachment_urls(&$row, $scope = 'messages')
{
    $body = isset($row['body']) ? (string) $row['body'] : '';
    if (preg_match('~^\[video_thumb\]([a-zA-Z0-9._-]+)\[/video_thumb\]$~', $body, $m)) {
        $urls = better_chat_storage_urls($scope, $m[1], true);
        $row['attachment_url'] = $urls['full'];
        $row['thumbnail_url'] = $urls['thumb'];
        $row['attachment_type'] = 'video';
    } elseif (preg_match('~^\[(?:img_thumb|support_img)\]([a-zA-Z0-9._-]+)\[/(?:img_thumb|support_img)\]$~', $body, $m)) {
        $urls = better_chat_storage_urls($scope, $m[1], false);
        $row['attachment_url'] = $urls['full'];
        $row['thumbnail_url'] = $urls['thumb'];
        $row['attachment_type'] = 'image';
    }
}

function better_chat_storage_delete_candidate($scope, $name)
{
    global $boarddir;

    $scope = $scope === 'support' ? 'support' : 'messages';
    $name = basename((string) $name);
    if ($name === '')
        return 0;

    $deleted = 0;
    $local = $boarddir . '/chat_uploads/' . $name;
    if (is_file($local) && @unlink($local))
        $deleted++;

    if (better_chat_storage_available()) {
        $config = better_chat_storage_config();
        $relative = $scope . '/' . $name;
        if (!empty($config['backend']) && $config['backend'] === 's3' && function_exists('betterstorage_s3_delete')) {
            $result = betterstorage_s3_delete($config, $relative);
            if (!empty($result['ok']))
                $deleted++;
        }
        if (function_exists('betterstorage_unregister_object')) {
            betterstorage_unregister_object('chat', 's3', $relative);
            betterstorage_unregister_object('chat', 'local', 'chat_uploads/' . $name);
        }
    }

    return $deleted;
}

/**
 * Better Chat moderation helpers.
 */
function better_chat_moderation_active_restrictions($id_member)
{
    global $smcFunc;

    $id_member = (int) $id_member;
    if ($id_member <= 0)
        return array();

    $now = time();
    $request = $smcFunc['db_query']('', '
        SELECT id_restriction, restriction_type, reason, id_moderator, created_at, expires_at
        FROM {db_prefix}chat_restrictions
        WHERE id_member = {int:id_member}
            AND revoked_at = 0
            AND (expires_at = 0 OR expires_at > {int:now})
        ORDER BY created_at DESC',
        array('id_member' => $id_member, 'now' => $now)
    );

    $rows = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
        $rows[] = $row;
    $smcFunc['db_free_result']($request);
    return $rows;
}

function better_chat_moderation_is_restricted($id_member, $scope)
{
    foreach (better_chat_moderation_active_restrictions($id_member) as $restriction) {
        $type = (string) $restriction['restriction_type'];
        if ($type === 'all' || $type === $scope)
            return $restriction;
    }
    return false;
}

function better_chat_moderation_denied_message($restriction, $label)
{
    if (empty($restriction))
        return '';

    $message = 'Your Better Chat access to ' . $label . ' is currently restricted.';
    if (!empty($restriction['expires_at']))
        $message .= ' This restriction expires ' . timeformat((int) $restriction['expires_at']) . '.';
    return $message;
}

function better_chat_moderation_duration_text($restriction)
{
    if (empty($restriction) || empty($restriction['expires_at']))
        return 'Permanent';

    $created = !empty($restriction['created_at']) ? (int) $restriction['created_at'] : time();
    $seconds = max(0, (int) $restriction['expires_at'] - $created);

    if ($seconds >= 86400 && $seconds % 86400 === 0) {
        $days = (int) ($seconds / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's');
    }
    if ($seconds >= 3600 && $seconds % 3600 === 0) {
        $hours = (int) ($seconds / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's');
    }
    if ($seconds >= 60) {
        $minutes = max(1, (int) round($seconds / 60));
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }
    return max(1, $seconds) . ' seconds';
}

function better_chat_moderation_restriction_payload($restriction, $label)
{
    if (empty($restriction))
        return null;

    return array(
        'id_restriction' => (int) $restriction['id_restriction'],
        'type' => (string) $restriction['restriction_type'],
        'label' => (string) $label,
        'reason' => (string) $restriction['reason'],
        'created_at' => (int) $restriction['created_at'],
        'expires_at' => (int) $restriction['expires_at'],
        'duration_text' => better_chat_moderation_duration_text($restriction),
        'expires_text' => !empty($restriction['expires_at']) ? timeformat((int) $restriction['expires_at']) : 'Permanent',
    );
}


/**
 * Write a Better Chat administrative audit event without storing message bodies.
 */
function better_chat_audit_log($event_type, $actor_member = 0, $subject_member = 0, $related_member = 0, $details = '')
{
    global $smcFunc;

    $details = trim((string) $details);
    if (strlen($details) > 1000)
        $details = substr($details, 0, 1000);

    $smcFunc['db_insert'](
        'insert',
        '{db_prefix}chat_audit_log',
        array(
            'event_type' => 'string',
            'id_actor' => 'int',
            'id_subject' => 'int',
            'id_related' => 'int',
            'details' => 'string',
            'created_at' => 'int',
        ),
        array((string) $event_type, (int) $actor_member, (int) $subject_member, (int) $related_member, $details, time()),
        array('id_log')
    );
}

/**
 * Stage 2A conversation foundation.
 *
 * Better Chat historically stores private messages as sender/recipient pairs in
 * chat_messages.  Private group chat needs a stable conversation identity and
 * participant list, but rewriting years of existing message rows during an
 * upgrade would be risky.  These helpers therefore create a lightweight
 * conversation shell and lazily map the existing chat_message IDs to it.
 *
 * One-to-one UI/API behavior remains unchanged in test35a.  Later Stage 2
 * passes can add participants and named groups on top of these tables.
 */
function better_chat_direct_conversation_key($member_a, $member_b)
{
    $member_a = (int) $member_a;
    $member_b = (int) $member_b;

    if ($member_a <= 0 || $member_b <= 0 || $member_a === $member_b)
        return '';

    $low = min($member_a, $member_b);
    $high = max($member_a, $member_b);
    return 'direct:' . $low . ':' . $high;
}

function better_chat_get_direct_conversation($member_a, $member_b, $create = true, $link_history = true)
{
    global $smcFunc;

    $member_a = (int) $member_a;
    $member_b = (int) $member_b;
    $direct_key = better_chat_direct_conversation_key($member_a, $member_b);
    if ($direct_key === '')
        return 0;

    $request = $smcFunc['db_query']('', '
        SELECT id_conversation
        FROM {db_prefix}chat_conversations
        WHERE direct_key = {string:direct_key}
        LIMIT 1',
        array('direct_key' => $direct_key)
    );
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    $id_conversation = !empty($row['id_conversation']) ? (int) $row['id_conversation'] : 0;

    if ($id_conversation <= 0 && $create) {
        $now = time();
        // "ignore" makes two simultaneous first messages harmless. The unique
        // direct_key guarantees a single conversation shell for the pair.
        $smcFunc['db_insert'](
            'ignore',
            '{db_prefix}chat_conversations',
            array(
                'conversation_type' => 'string',
                'direct_key' => 'string',
                'title' => 'string',
                'id_owner' => 'int',
                'created_at' => 'int',
                'updated_at' => 'int',
            ),
            array('direct', $direct_key, '', $member_a, $now, $now),
            array('direct_key')
        );

        $request = $smcFunc['db_query']('', '
            SELECT id_conversation
            FROM {db_prefix}chat_conversations
            WHERE direct_key = {string:direct_key}
            LIMIT 1',
            array('direct_key' => $direct_key)
        );
        $row = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);
        $id_conversation = !empty($row['id_conversation']) ? (int) $row['id_conversation'] : 0;
    }

    if ($id_conversation <= 0)
        return 0;

    $now = time();
    foreach (array($member_a, $member_b) as $member_id) {
        $smcFunc['db_insert'](
            'ignore',
            '{db_prefix}chat_conversation_members',
            array(
                'id_conversation' => 'int',
                'id_member' => 'int',
                'member_role' => 'string',
                'joined_at' => 'int',
                'left_at' => 'int',
                'cleared_through_id' => 'int',
                'last_read_id' => 'int',
            ),
            array($id_conversation, $member_id, $member_id === $member_a ? 'owner' : 'member', $now, 0, 0, 0),
            array('id_conversation', 'id_member')
        );
    }

    if ($link_history)
        better_chat_link_direct_conversation_history($id_conversation, $member_a, $member_b);

    return $id_conversation;
}

function better_chat_link_direct_conversation_history($id_conversation, $member_a, $member_b)
{
    global $smcFunc;

    $id_conversation = (int) $id_conversation;
    $member_a = (int) $member_a;
    $member_b = (int) $member_b;
    if ($id_conversation <= 0 || $member_a <= 0 || $member_b <= 0 || $member_a === $member_b)
        return 0;

    $request = $smcFunc['db_query']('', '
        SELECT m.id_chat
        FROM {db_prefix}chat_messages AS m
        LEFT JOIN {db_prefix}chat_conversation_messages AS cm ON (cm.id_chat = m.id_chat)
        WHERE cm.id_chat IS NULL
          AND ((m.id_member_from = {int:a} AND m.id_member_to = {int:b})
            OR (m.id_member_from = {int:b} AND m.id_member_to = {int:a}))
        ORDER BY m.id_chat ASC',
        array('a' => $member_a, 'b' => $member_b)
    );

    $rows = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
        $rows[] = array((int) $row['id_chat'], $id_conversation);
    $smcFunc['db_free_result']($request);

    if (!empty($rows)) {
        $smcFunc['db_insert'](
            'ignore',
            '{db_prefix}chat_conversation_messages',
            array('id_chat' => 'int', 'id_conversation' => 'int'),
            $rows,
            array('id_chat')
        );

        $smcFunc['db_query']('', '
            UPDATE {db_prefix}chat_conversations
            SET updated_at = {int:updated_at}
            WHERE id_conversation = {int:id_conversation}',
            array('updated_at' => time(), 'id_conversation' => $id_conversation)
        );
    }

    return count($rows);
}


function better_chat_get_conversation_for_member($id_conversation, $id_member, $include_left = false)
{
    global $smcFunc;

    $id_conversation = (int) $id_conversation;
    $id_member = (int) $id_member;
    if ($id_conversation <= 0 || $id_member <= 0)
        return false;

    $request = $smcFunc['db_query']('', '
        SELECT c.id_conversation, c.conversation_type, c.title, c.id_owner, c.created_at, c.updated_at,
               cm.member_role, cm.joined_at, cm.left_at, cm.cleared_through_id, cm.last_read_id
        FROM {db_prefix}chat_conversations AS c
        INNER JOIN {db_prefix}chat_conversation_members AS cm
            ON (cm.id_conversation = c.id_conversation)
        WHERE c.id_conversation = {int:id_conversation}
          AND cm.id_member = {int:id_member}' . (!$include_left ? '
          AND cm.left_at = 0' : '') . '
        LIMIT 1',
        array('id_conversation' => $id_conversation, 'id_member' => $id_member)
    );
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    return !empty($row) ? $row : false;
}

function better_chat_conversation_participants($id_conversation, $include_left = false)
{
    global $smcFunc;
    $id_conversation = (int) $id_conversation;
    if ($id_conversation <= 0) return array();

    $request = $smcFunc['db_query']('', '
        SELECT cm.id_member, cm.member_role, cm.joined_at, cm.left_at, mem.member_name, mem.real_name
        FROM {db_prefix}chat_conversation_members AS cm
        INNER JOIN {db_prefix}members AS mem ON (mem.id_member = cm.id_member)
        WHERE cm.id_conversation = {int:id_conversation}' . (!$include_left ? '
          AND cm.left_at = 0' : '') . '
        ORDER BY cm.joined_at ASC, mem.real_name ASC',
        array('id_conversation' => $id_conversation)
    );
    $rows = array();
    while ($row = $smcFunc['db_fetch_assoc']($request)) {
        $rows[] = array(
            'id' => (int) $row['id_member'],
            'name' => !empty($row['real_name']) ? (string) $row['real_name'] : (string) $row['member_name'],
            'username' => (string) $row['member_name'],
            'role' => (string) $row['member_role'],
            'joined_at' => (int) $row['joined_at'],
            'left_at' => (int) $row['left_at'],
        );
    }
    $smcFunc['db_free_result']($request);
    return $rows;
}

function better_chat_conversation_title($conversation, $participants = array(), $viewer_id = 0)
{
    if (!empty($conversation['title'])) return (string) $conversation['title'];
    $names = array();
    foreach ($participants as $participant) {
        if ((int) $participant['id'] === (int) $viewer_id) continue;
        $names[] = (string) $participant['name'];
    }
    if (empty($names)) return 'Private Group';
    $title = implode(', ', $names);
    if (strlen($title) > 72) $title = substr($title, 0, 69) . '...';
    return $title;
}

function better_chat_insert_conversation_message($id_conversation, $id_sender, $body)
{
    global $smcFunc;
    $id_conversation = (int) $id_conversation;
    if ($id_conversation <= 0) return 0;

    $smcFunc['db_insert']('insert', '{db_prefix}chat_messages',
        array('id_member_from' => 'int', 'id_member_to' => 'int', 'body' => 'string', 'log_time' => 'int'),
        array((int) $id_sender, -$id_conversation, (string) $body, time()), array('id_chat'));
    $id_chat = (int) $smcFunc['db_insert_id']('{db_prefix}chat_messages', 'id_chat');
    if ($id_chat > 0) {
        // test38d: A physical table reset can cause MySQL to reuse an old
        // chat_messages primary key. Clear any orphaned Better Reactions
        // object rows for that numeric ID before this new message is exposed.
        better_chat_delete_reactions_for_messages(array($id_chat));

        $smcFunc['db_insert']('ignore', '{db_prefix}chat_conversation_messages',
            array('id_chat' => 'int', 'id_conversation' => 'int'), array($id_chat, $id_conversation), array('id_chat'));
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}chat_conversations
            SET updated_at = {int:updated_at}
            WHERE id_conversation = {int:id_conversation}',
            array('updated_at' => time(), 'id_conversation' => $id_conversation));
    }
    return $id_chat;
}

function better_chat_private_group_payload($id_conversation, $viewer_id)
{
    $conversation = better_chat_get_conversation_for_member($id_conversation, $viewer_id);
    if (empty($conversation) || (string) $conversation['conversation_type'] !== 'group') return false;
    $participants = better_chat_conversation_participants($id_conversation);
    // Private groups are born by converting an existing two-person direct
    // conversation. Preserve the original pair in the payload so every client
    // can retire that old direct window when the conversation becomes a group.
    // Existing rows already retain joined_at, so this also works for groups
    // created by earlier test builds.
    $origin_member_ids = array();
    foreach ($participants as $participant) {
        $origin_member_ids[] = (int) $participant['id'];
        if (count($origin_member_ids) >= 2) break;
    }

    return array(
        'id' => (int) $id_conversation,
        'title' => better_chat_conversation_title($conversation, $participants, $viewer_id),
        'owner_id' => (int) $conversation['id_owner'],
        'participants' => $participants,
        'origin_member_ids' => $origin_member_ids,
        'updated_at' => (int) $conversation['updated_at'],
    );
}

function better_chat_convert_or_add_people($id_conversation, $actor_id, $member_ids)
{
    global $smcFunc;
    $id_conversation = (int) $id_conversation;
    $actor_id = (int) $actor_id;
    $conversation = better_chat_get_conversation_for_member($id_conversation, $actor_id);
    if (empty($conversation)) return array('ok' => false, 'message' => 'That private conversation is not available.');
    if (!in_array((string) $conversation['conversation_type'], array('direct', 'group'), true))
        return array('ok' => false, 'message' => 'That conversation cannot be changed.');

    $member_ids = array_values(array_unique(array_filter(array_map('intval', (array) $member_ids))));
    $member_ids = array_values(array_filter($member_ids, function ($id) use ($actor_id) { return $id > 0 && $id !== $actor_id; }));
    if (empty($member_ids)) return array('ok' => false, 'message' => 'Choose at least one member to add.');

    $existing_ids = array();
    foreach (better_chat_conversation_participants($id_conversation, true) as $row)
        if (empty($row['left_at'])) $existing_ids[(int) $row['id']] = true;

    $valid_rows = array();
    $presence_cutoff = time() - 45;
    $request = $smcFunc['db_query']('', '
        SELECT mem.id_member, mem.member_name, mem.real_name, mem.show_online, COALESCE(p.last_seen, 0) AS chat_last_seen
        FROM {db_prefix}members AS mem
            LEFT JOIN {db_prefix}chat_presence AS p ON (p.id_member = mem.id_member)
        WHERE mem.id_member IN ({array_int:member_ids})
        ORDER BY mem.real_name ASC', array('member_ids' => $member_ids));
    while ($row = $smcFunc['db_fetch_assoc']($request)) {
        $id = (int) $row['id_member'];
        if (isset($existing_ids[$id])) continue;

        $is_online = (int) $row['chat_last_seen'] >= $presence_cutoff;
        if (empty($row['show_online']) && empty($user_info['is_admin']))
            $is_online = false;
        if (!$is_online) continue;
        if (better_chat_moderation_is_restricted($id, 'private') || better_chat_moderation_is_restricted($id, 'all'))
            continue;

        $valid_rows[$id] = !empty($row['real_name']) ? (string) $row['real_name'] : (string) $row['member_name'];
    }
    $smcFunc['db_free_result']($request);
    if (empty($valid_rows)) return array('ok' => false, 'message' => 'Choose an online member who is available for private chat.');

    if ((string) $conversation['conversation_type'] === 'direct') {
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}chat_conversations
            SET conversation_type = {string:type}, direct_key = {string:group_key}, updated_at = {int:updated_at}
            WHERE id_conversation = {int:id_conversation}',
            array('type' => 'group', 'group_key' => 'group:' . $id_conversation, 'updated_at' => time(), 'id_conversation' => $id_conversation));
    }

    $now = time();
    foreach ($valid_rows as $id_member => $name) {
        $smcFunc['db_insert']('replace', '{db_prefix}chat_conversation_members',
            array('id_conversation' => 'int', 'id_member' => 'int', 'member_role' => 'string', 'joined_at' => 'int', 'left_at' => 'int', 'cleared_through_id' => 'int', 'last_read_id' => 'int'),
            array($id_conversation, (int) $id_member, 'member', $now, 0, 0, 0), array('id_conversation', 'id_member'));
    }

    $actor_name = 'A member';
    $request = $smcFunc['db_query']('', '
        SELECT real_name, member_name FROM {db_prefix}members WHERE id_member = {int:id_member} LIMIT 1',
        array('id_member' => $actor_id));
    if ($row = $smcFunc['db_fetch_assoc']($request))
        $actor_name = !empty($row['real_name']) ? (string) $row['real_name'] : (string) $row['member_name'];
    $smcFunc['db_free_result']($request);

    better_chat_insert_conversation_message($id_conversation, 0,
        '[[system]]' . $actor_name . ' added ' . implode(', ', array_values($valid_rows)) . ' to the conversation.');

    return array('ok' => true, 'conversation' => better_chat_private_group_payload($id_conversation, $actor_id));
}

function better_chat_leave_private_group($id_conversation, $member_id)
{
    global $smcFunc;
    $conversation = better_chat_get_conversation_for_member((int) $id_conversation, (int) $member_id);
    if (empty($conversation) || (string) $conversation['conversation_type'] !== 'group')
        return array('ok' => false, 'message' => 'That private group is not available.');

    $name = 'A member';
    $request = $smcFunc['db_query']('', '
        SELECT real_name, member_name FROM {db_prefix}members WHERE id_member = {int:id_member} LIMIT 1',
        array('id_member' => (int) $member_id));
    if ($row = $smcFunc['db_fetch_assoc']($request))
        $name = !empty($row['real_name']) ? (string) $row['real_name'] : (string) $row['member_name'];
    $smcFunc['db_free_result']($request);

    $smcFunc['db_query']('', '
        UPDATE {db_prefix}chat_conversation_members
        SET left_at = {int:left_at}
        WHERE id_conversation = {int:id_conversation} AND id_member = {int:id_member}',
        array('left_at' => time(), 'id_conversation' => (int) $id_conversation, 'id_member' => (int) $member_id));
    better_chat_insert_conversation_message((int) $id_conversation, 0, '[[system]]' . $name . ' left the conversation.');
    return array('ok' => true);
}

function better_chat_rename_private_group($id_conversation, $member_id, $title)
{
    global $smcFunc;

    $id_conversation = (int) $id_conversation;
    $member_id = (int) $member_id;
    $conversation = better_chat_get_conversation_for_member($id_conversation, $member_id);
    if (empty($conversation) || (string) $conversation['conversation_type'] !== 'group')
        return array('ok' => false, 'message' => 'That private group is not available.');

    $title = html_entity_decode(strip_tags((string) $title), ENT_QUOTES, 'UTF-8');
    $title = preg_replace('~\\s+~u', ' ', trim($title));
    if (function_exists('mb_substr'))
        $title = mb_substr($title, 0, 60, 'UTF-8');
    else
        $title = substr($title, 0, 60);

    $smcFunc['db_query']('', '
        UPDATE {db_prefix}chat_conversations
        SET title = {string:title}, updated_at = {int:updated_at}
        WHERE id_conversation = {int:id_conversation}',
        array('title' => $title, 'updated_at' => time(), 'id_conversation' => $id_conversation));

    $name = 'A member';
    $request = $smcFunc['db_query']('', '
        SELECT real_name, member_name FROM {db_prefix}members WHERE id_member = {int:id_member} LIMIT 1',
        array('id_member' => $member_id));
    if ($row = $smcFunc['db_fetch_assoc']($request))
        $name = !empty($row['real_name']) ? (string) $row['real_name'] : (string) $row['member_name'];
    $smcFunc['db_free_result']($request);

    if ($title !== '')
        better_chat_insert_conversation_message($id_conversation, 0, '[[system]]' . $name . ' renamed the conversation to ' . $title . '.');
    else
        better_chat_insert_conversation_message($id_conversation, 0, '[[system]]' . $name . ' restored the automatic conversation name.');

    return array('ok' => true, 'conversation' => better_chat_private_group_payload($id_conversation, $member_id));
}

function better_chat_member_picker_search($term, $id_conversation, $viewer_id)
{
    global $smcFunc, $user_info;
    $term = trim((string) $term);
    $id_conversation = (int) $id_conversation;
    $viewer_id = (int) $viewer_id;
    if (strlen($term) < 2 || $id_conversation <= 0 || $viewer_id <= 0) return array();
    if (!better_chat_get_conversation_for_member($id_conversation, $viewer_id)) return array();

    $existing = array();
    foreach (better_chat_conversation_participants($id_conversation, true) as $participant) {
        if (empty($participant['left_at']))
            $existing[(int) $participant['id']] = true;
    }

    $rows = array();
    $cutoff = time() - 45;
    $request = $smcFunc['db_query']('', '
        SELECT mem.id_member, mem.member_name, mem.real_name, mem.show_online,
            COALESCE(p.last_seen, 0) AS chat_last_seen
        FROM {db_prefix}members AS mem
            LEFT JOIN {db_prefix}chat_presence AS p ON (p.id_member = mem.id_member)
        WHERE mem.id_member != {int:viewer}
          AND (mem.real_name LIKE {string:term} OR mem.member_name LIKE {string:term})
        ORDER BY mem.real_name ASC
        LIMIT 30', array('viewer' => $viewer_id, 'term' => '%' . $term . '%'));

    while ($row = $smcFunc['db_fetch_assoc']($request)) {
        $id = (int) $row['id_member'];
        $is_online = (int) $row['chat_last_seen'] >= $cutoff;
        if (empty($row['show_online']) && empty($user_info['is_admin']))
            $is_online = false;

        $private_restriction = better_chat_moderation_is_restricted($id, 'private');
        $full_restriction = better_chat_moderation_is_restricted($id, 'all');
        $restricted = !empty($private_restriction) || !empty($full_restriction);
        $in_conversation = isset($existing[$id]);
        $selectable = $is_online && !$restricted && !$in_conversation;

        $reason = '';
        if ($in_conversation) $reason = 'Already in conversation';
        elseif (!$is_online) $reason = 'Not online';
        elseif ($restricted) $reason = 'Private chat unavailable';

        $rows[] = array(
            'id' => $id,
            'name' => !empty($row['real_name']) ? (string) $row['real_name'] : (string) $row['member_name'],
            'username' => (string) $row['member_name'],
            'online' => $is_online,
            'selectable' => $selectable,
            'in_conversation' => $in_conversation,
            'reason' => $reason,
        );
        if (count($rows) >= 20) break;
    }
    $smcFunc['db_free_result']($request);
    return $rows;
}

function better_chat_private_cleared_through($id_member, $other_member)
{
    global $smcFunc;

    $request = $smcFunc['db_query']('', '
        SELECT cleared_through_id
        FROM {db_prefix}chat_private_state
        WHERE id_member = {int:id_member}
          AND other_member = {int:other_member}
        LIMIT 1',
        array('id_member' => (int) $id_member, 'other_member' => (int) $other_member)
    );
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    return !empty($row['cleared_through_id']) ? (int) $row['cleared_through_id'] : 0;
}

function better_chat_private_clear_for_member($id_member, $other_member)
{
    global $smcFunc;

    $id_member = (int) $id_member;
    $other_member = (int) $other_member;
    if ($id_member <= 0 || $other_member <= 0 || $id_member === $other_member)
        return 0;

    $request = $smcFunc['db_query']('', '
        SELECT MAX(id_chat) AS max_id
        FROM {db_prefix}chat_messages
        WHERE (id_member_from = {int:me} AND id_member_to = {int:other})
           OR (id_member_from = {int:other} AND id_member_to = {int:me})',
        array('me' => $id_member, 'other' => $other_member)
    );
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    $max_id = !empty($row['max_id']) ? (int) $row['max_id'] : 0;

    $smcFunc['db_insert'](
        'replace',
        '{db_prefix}chat_private_state',
        array('id_member' => 'int', 'other_member' => 'int', 'cleared_through_id' => 'int', 'updated_at' => 'int'),
        array(array($id_member, $other_member, $max_id, time())),
        array('id_member', 'other_member')
    );

    // Clearing a member's view also retires old unread signals so that the
    // same cleared conversation cannot immediately reopen itself.
    if ($max_id > 0) {
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}chat_messages
            SET is_read = 1
            WHERE id_member_from = {int:other}
              AND id_member_to = {int:me}
              AND id_chat <= {int:max_id}',
            array('other' => $other_member, 'me' => $id_member, 'max_id' => $max_id)
        );
    }

    return $max_id;
}

function better_chat_delete_private_attachments($bodies)
{
    $deleted = 0;
    foreach ((array) $bodies as $body) {
        if (!preg_match_all('~\[(img_thumb|video_thumb|support_img)\]([^\[]+)\[/\1\]~i', (string) $body, $matches, PREG_SET_ORDER))
            continue;
        foreach ($matches as $match) {
            $tag = strtolower((string) $match[1]);
            $name = basename(trim((string) $match[2]));
            if ($name === '')
                continue;
            $scope = $tag === 'support_img' ? 'support' : 'messages';
            $candidates = array($name, 'thumb_' . $name);
            if ($tag === 'video_thumb')
                $candidates[] = 'thumb_' . $name . '.jpg';
            foreach (array_unique($candidates) as $candidate)
                $deleted += better_chat_storage_delete_candidate($scope, $candidate);
        }
    }
    return $deleted;
}


/**
 * Load the Better Reactions object APIs needed for deletion cleanup.
 *
 * This intentionally ignores the Better Reactions enabled/disabled UI setting.
 * Permanent Better Chat deletion must remove stored chat_message reactions even
 * when reactions are temporarily disabled.
 */
function better_chat_reactions_cleanup_available()
{
    global $sourcedir;

    if (!function_exists('BetterReactions_GetObjectReactors') || !function_exists('BetterReactions_ClearObjectReaction')) {
        $file = $sourcedir . '/BetterReactions.php';
        if (is_file($file))
            require_once($file);
    }

    return function_exists('BetterReactions_GetObjectReactors')
        && function_exists('BetterReactions_ClearObjectReaction');
}

/**
 * Extract member IDs from Better Reactions reactor payloads without depending
 * on one exact client payload shape.
 */
function better_chat_reaction_member_ids($payload)
{
    $ids = array();

    $walk = function ($value) use (&$walk, &$ids) {
        if (!is_array($value))
            return;

        foreach (array('id_member', 'member_id', 'idMember') as $key) {
            if (isset($value[$key]) && (int) $value[$key] > 0)
                $ids[(int) $value[$key]] = (int) $value[$key];
        }

        foreach ($value as $child)
            if (is_array($child))
                $walk($child);
    };

    $walk($payload);
    return array_values($ids);
}

/**
 * Call BetterReactions_ClearObjectReaction using the public function's actual
 * parameter names. Better Reactions has evolved across dev builds, so this
 * avoids hard-coding one obsolete signature.
 */
function better_chat_clear_reaction_member($id_chat, $id_member, $reaction_key = '')
{
    if (!better_chat_reactions_cleanup_available())
        return false;

    try {
        $ref = new ReflectionFunction('BetterReactions_ClearObjectReaction');
        $args = array();

        foreach ($ref->getParameters() as $index => $param) {
            $name = strtolower($param->getName());

            if (strpos($name, 'content') !== false || $name === 'type' || strpos($name, 'object_type') !== false)
                $args[] = 'chat_message';
            elseif ((strpos($name, 'object') !== false && strpos($name, 'id') !== false) || in_array($name, array('id_object', 'object_id', 'id_chat'), true))
                $args[] = (int) $id_chat;
            elseif (strpos($name, 'member') !== false || in_array($name, array('id_user', 'user_id'), true))
                $args[] = (int) $id_member;
            elseif (strpos($name, 'reaction') !== false)
                $args[] = (string) $reaction_key;
            elseif (strpos($name, 'refresh') !== false || strpos($name, 'cache') !== false)
                $args[] = true;
            elseif ($param->isDefaultValueAvailable())
                $args[] = $param->getDefaultValue();
            else {
                if ($index === 0)
                    $args[] = 'chat_message';
                elseif ($index === 1)
                    $args[] = (int) $id_chat;
                elseif ($index === 2)
                    $args[] = (int) $id_member;
                elseif ($index === 3)
                    $args[] = (string) $reaction_key;
                else
                    return false;
            }
        }

        $result = call_user_func_array('BetterReactions_ClearObjectReaction', $args);
        return $result !== false;
    }
    catch (Throwable $e) {
        return false;
    }
}

/**
 * Remove all Better Reactions rows belonging to permanently deleted Better
 * Chat message objects. Returns the number of member/object reaction rows that
 * were successfully asked to clear.
 */
function better_chat_delete_reactions_for_messages($message_ids)
{
    $message_ids = array_values(array_unique(array_filter(array_map('intval', (array) $message_ids))));
    if (empty($message_ids) || !better_chat_reactions_cleanup_available())
        return 0;

    $registry = function_exists('BetterReactions_GetClientRegistry') ? BetterReactions_GetClientRegistry() : array();
    $types = !empty($registry['types']) && is_array($registry['types']) ? $registry['types'] : array();

    if (empty($types))
        $types = array(array('key' => 'like'));

    $cleared = 0;

    foreach ($message_ids as $id_chat) {
        foreach ($types as $type_key => $type) {
            if (is_array($type) && isset($type['key']))
                $reaction_key = (string) $type['key'];
            elseif (is_scalar($type))
                $reaction_key = (string) $type;
            elseif (is_string($type_key))
                $reaction_key = (string) $type_key;
            else
                $reaction_key = '';

            if ($reaction_key === '')
                continue;

            $last_signature = '';
            for ($pass = 0; $pass < 100; $pass++) {
                try {
                    $payload = BetterReactions_GetObjectReactors('chat_message', (int) $id_chat, $reaction_key, 50);
                }
                catch (Throwable $e) {
                    break;
                }

                $member_ids = better_chat_reaction_member_ids($payload);
                if (empty($member_ids))
                    break;

                sort($member_ids, SORT_NUMERIC);
                $signature = implode(',', $member_ids);
                if ($signature !== '' && $signature === $last_signature)
                    break;
                $last_signature = $signature;

                $progress = 0;
                foreach ($member_ids as $id_member) {
                    if (better_chat_clear_reaction_member($id_chat, $id_member, $reaction_key)) {
                        $progress++;
                        $cleared++;
                    }
                }

                if ($progress === 0)
                    break;
            }
        }
    }

    return $cleared;
}

function better_chat_admin_purge_private_conversation($member_a, $member_b)
{
    global $smcFunc, $user_info;

    $member_a = (int) $member_a;
    $member_b = (int) $member_b;
    if ($member_a <= 0 || $member_b <= 0 || $member_a === $member_b)
        return array('ok' => false, 'message' => 'Choose two different registered members.');

    $request = $smcFunc['db_query']('', '
        SELECT id_member, real_name
        FROM {db_prefix}members
        WHERE id_member IN ({array_int:ids})',
        array('ids' => array($member_a, $member_b))
    );
    $members = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
        $members[(int) $row['id_member']] = (string) $row['real_name'];
    $smcFunc['db_free_result']($request);
    if (count($members) !== 2)
        return array('ok' => false, 'message' => 'One or both members could not be found.');

    $request = $smcFunc['db_query']('', '
        SELECT id_chat, body
        FROM {db_prefix}chat_messages
        WHERE (id_member_from = {int:a} AND id_member_to = {int:b})
           OR (id_member_from = {int:b} AND id_member_to = {int:a})',
        array('a' => $member_a, 'b' => $member_b)
    );
    $ids = array();
    $bodies = array();
    while ($row = $smcFunc['db_fetch_assoc']($request)) {
        $ids[] = (int) $row['id_chat'];
        $bodies[] = (string) $row['body'];
    }
    $smcFunc['db_free_result']($request);

    $reaction_rows = better_chat_delete_reactions_for_messages($ids);
    better_chat_delete_private_attachments($bodies);

    $smcFunc['db_query']('', '
        DELETE FROM {db_prefix}chat_messages
        WHERE (id_member_from = {int:a} AND id_member_to = {int:b})
           OR (id_member_from = {int:b} AND id_member_to = {int:a})',
        array('a' => $member_a, 'b' => $member_b)
    );
    $smcFunc['db_query']('', '
        DELETE FROM {db_prefix}chat_private_state
        WHERE (id_member = {int:a} AND other_member = {int:b})
           OR (id_member = {int:b} AND other_member = {int:a})',
        array('a' => $member_a, 'b' => $member_b)
    );

    better_chat_audit_log(
        'private_purge',
        !empty($user_info['id']) ? (int) $user_info['id'] : 0,
        $member_a,
        $member_b,
        'Permanently purged ' . count($ids) . ' private chat message(s) between ' . $members[$member_a] . ' and ' . $members[$member_b]
            . '; removed ' . $reaction_rows . ' Better Reactions row(s).'
    );

    return array('ok' => true, 'count' => count($ids), 'reactions' => $reaction_rows, 'names' => array($members[$member_a], $members[$member_b]));
}

/**
 * Permanently clear only Community / Group Chat history.
 * Private conversations, private clear state, support sessions and audit rows are untouched.
 */
function better_chat_admin_clear_group_history()
{
    global $smcFunc, $user_info;

    $request = $smcFunc['db_query']('', '
        SELECT id_chat, body
        FROM {db_prefix}chat_messages
        WHERE id_member_to = 0', array());
    $ids = array();
    $bodies = array();
    while ($row = $smcFunc['db_fetch_assoc']($request)) {
        $ids[] = (int) $row['id_chat'];
        $bodies[] = (string) $row['body'];
    }
    $smcFunc['db_free_result']($request);

    $reaction_rows = better_chat_delete_reactions_for_messages($ids);

    // Reuse the attachment cleanup routine: it only removes files explicitly
    // referenced by the messages being deleted.
    better_chat_delete_private_attachments($bodies);

    $smcFunc['db_query']('', '
        DELETE FROM {db_prefix}chat_messages
        WHERE id_member_to = 0', array());

    better_chat_audit_log(
        'group_history_clear',
        !empty($user_info['id']) ? (int) $user_info['id'] : 0,
        0,
        0,
        'Permanently cleared ' . count($ids) . ' Group Chat message(s); removed ' . $reaction_rows . ' Better Reactions row(s).'
    );

    return array('ok' => true, 'count' => count($ids), 'reactions' => $reaction_rows);
}

/**
 * Run Better Chat's scope-aware retention cleanup at most once per hour.
 *
 * Existing chat_purge_time is intentionally ignored here after migration;
 * granular retention settings are the only source of truth from test33c on.
 */
function better_chat_auto_prune_retention($force = false)
{
    global $smcFunc, $modSettings;

    $now = time();
    $last_run = !empty($modSettings['chat_retention_last_run']) ? (int) $modSettings['chat_retention_last_run'] : 0;
    if (!$force && $last_run > 0 && ($now - $last_run) < 3600)
        return false;

    // Claim this maintenance window before doing the work. If two page requests
    // arrive together, the second one sees the fresh timestamp on its next request.
    updateSettings(array('chat_retention_last_run' => $now));
    $modSettings['chat_retention_last_run'] = $now;

    $policies = array(
        'group' => !empty($modSettings['chat_retention_group_hours']) ? (int) $modSettings['chat_retention_group_hours'] : 0,
        'private' => !empty($modSettings['chat_retention_private_hours']) ? (int) $modSettings['chat_retention_private_hours'] : 0,
        'support' => !empty($modSettings['chat_retention_support_hours']) ? (int) $modSettings['chat_retention_support_hours'] : 0,
        'audit' => !empty($modSettings['chat_retention_audit_hours']) ? (int) $modSettings['chat_retention_audit_hours'] : 0,
    );

    $summary = array('group' => 0, 'private' => 0, 'support' => 0, 'audit' => 0, 'attachments' => 0, 'reactions' => 0);

    foreach (array('group', 'private') as $scope) {
        $hours = $policies[$scope];
        if ($hours <= 0)
            continue;

        $cutoff = $now - ($hours * 3600);
        $where = $scope === 'group' ? 'id_member_to = 0' : 'id_member_to > 0';

        $request = $smcFunc['db_query']('', '
            SELECT id_chat, body
            FROM {db_prefix}chat_messages
            WHERE ' . $where . '
              AND log_time < {int:cutoff}',
            array('cutoff' => $cutoff)
        );
        $ids = array();
        $bodies = array();
        while ($row = $smcFunc['db_fetch_assoc']($request)) {
            $ids[] = (int) $row['id_chat'];
            $bodies[] = (string) $row['body'];
        }
        $smcFunc['db_free_result']($request);

        if (!empty($ids)) {
            $summary['reactions'] += better_chat_delete_reactions_for_messages($ids);
            $summary['attachments'] += better_chat_delete_private_attachments($bodies);
            $smcFunc['db_query']('', '
                DELETE FROM {db_prefix}chat_messages
                WHERE ' . $where . '
                  AND log_time < {int:cutoff}',
                array('cutoff' => $cutoff)
            );
            $summary[$scope] = count($ids);
        }
    }

    // Support retention only removes completed history. Waiting or active support
    // sessions are never touched, regardless of age.
    if ($policies['support'] > 0) {
        $cutoff = $now - ($policies['support'] * 3600);
        $request = $smcFunc['db_query']('', '
            SELECT sm.id_message, sm.body
            FROM {db_prefix}chat_support_messages AS sm
            INNER JOIN {db_prefix}chat_support_sessions AS ss ON (ss.id_support = sm.id_support)
            WHERE ss.status = {string:closed}
              AND sm.log_time < {int:cutoff}',
            array('closed' => 'closed', 'cutoff' => $cutoff)
        );
        $support_ids = array();
        $support_bodies = array();
        while ($row = $smcFunc['db_fetch_assoc']($request)) {
            $support_ids[] = (int) $row['id_message'];
            $support_bodies[] = (string) $row['body'];
        }
        $smcFunc['db_free_result']($request);

        if (!empty($support_ids)) {
            $summary['attachments'] += better_chat_delete_private_attachments($support_bodies);
            $smcFunc['db_query']('', '
                DELETE FROM {db_prefix}chat_support_messages
                WHERE id_message IN ({array_int:ids})',
                array('ids' => $support_ids)
            );
            $summary['support'] = count($support_ids);
        }

        // Once a closed support session itself is beyond the retention window and
        // no retained messages remain, remove the empty session shell as well.
        $smcFunc['db_query']('', '
            DELETE FROM {db_prefix}chat_support_sessions
            WHERE status = {string:closed}
              AND updated_at < {int:cutoff}
              AND id_support NOT IN (
                  SELECT id_support FROM {db_prefix}chat_support_messages
              )',
            array('closed' => 'closed', 'cutoff' => $cutoff)
        );
    }

    if ($policies['audit'] > 0) {
        $cutoff = $now - ($policies['audit'] * 3600);
        $request = $smcFunc['db_query']('', '
            SELECT COUNT(*) AS total
            FROM {db_prefix}chat_audit_log
            WHERE created_at < {int:cutoff}',
            array('cutoff' => $cutoff)
        );
        $row = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);
        $summary['audit'] = !empty($row['total']) ? (int) $row['total'] : 0;
        if ($summary['audit'] > 0) {
            $smcFunc['db_query']('', '
                DELETE FROM {db_prefix}chat_audit_log
                WHERE created_at < {int:cutoff}',
                array('cutoff' => $cutoff)
            );
        }
    }

    $total = $summary['group'] + $summary['private'] + $summary['support'] + $summary['audit'];
    if ($total > 0 || $summary['attachments'] > 0) {
        better_chat_audit_log(
            'auto_prune',
            0,
            0,
            0,
            'Automatic retention cleanup: Group ' . $summary['group']
                . ', Private ' . $summary['private']
                . ', Support ' . $summary['support']
                . ', Audit ' . $summary['audit']
                . ', Better Reactions row(s) ' . $summary['reactions']
                . ', attachment file(s) ' . $summary['attachments'] . '.'
        );
    }

    return $summary;
}

function better_chat_reactions_available()
{
	global $sourcedir, $modSettings;

	if (empty($modSettings['better_reactions_enabled']))
		return false;

	if (!function_exists('BetterReactions_GetObjectSummary'))
	{
		$file = $sourcedir . '/BetterReactions.php';
		if (!is_file($file))
			return false;
		require_once($file);
	}

	return function_exists('BetterReactions_GetObjectSummary')
		&& function_exists('BetterReactions_SetObjectReaction')
		&& function_exists('BetterReactions_GetObjectReactors')
		&& function_exists('BetterReactions_GetClientRegistry');
}

function better_chat_reaction_registry()
{
	if (!better_chat_reactions_available())
		return array('enabled' => false, 'types' => array(), 'display' => array());

	$registry = BetterReactions_GetClientRegistry();
	return array(
		'enabled' => true,
		'types' => !empty($registry['types']) ? $registry['types'] : array(),
		'display' => !empty($registry['display']) ? $registry['display'] : array(),
	);
}

function better_chat_reaction_message_access($id_chat)
{
	global $smcFunc, $user_info;

	$id_chat = (int) $id_chat;
	if ($id_chat <= 0 || empty($user_info['id']))
		return false;

	$request = $smcFunc['db_query']('', '
		SELECT id_chat, id_member_from, id_member_to
		FROM {db_prefix}chat_messages
		WHERE id_chat = {int:id_chat}
		LIMIT 1',
		array('id_chat' => $id_chat)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (empty($row))
		return false;

	if ((int) $row['id_member_to'] === 0)
		return $row;

	$id_member = (int) $user_info['id'];
	if ((int) $row['id_member_to'] < 0) {
		$request = $smcFunc['db_query']('', '
			SELECT cm.id_conversation
			FROM {db_prefix}chat_conversation_messages AS cm
			INNER JOIN {db_prefix}chat_conversation_members AS cpm
				ON (cpm.id_conversation = cm.id_conversation)
			WHERE cm.id_chat = {int:id_chat}
			  AND cpm.id_member = {int:id_member}
			  AND cpm.left_at = 0
			LIMIT 1',
			array('id_chat' => $id_chat, 'id_member' => $id_member));
		$allowed = (bool) $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
		return $allowed ? $row : false;
	}

	return ((int) $row['id_member_from'] === $id_member || (int) $row['id_member_to'] === $id_member) ? $row : false;
}


function better_chat_support_mode()
{
	global $modSettings;
	$mode = !empty($modSettings['chat_mode']) ? (string) $modSettings['chat_mode'] : 'community';
	return in_array($mode, array('community', 'support', 'hybrid'), true) ? $mode : 'community';
}

function better_chat_support_enabled()
{
	return in_array(better_chat_support_mode(), array('support', 'hybrid'), true);
}

function better_chat_support_is_staff()
{
	global $user_info, $modSettings;
	if (empty($user_info['id']))
		return false;
	if (!empty($user_info['is_admin']))
		return true;

	$allowed = !empty($modSettings['chat_support_staff_groups']) ? preg_split('/\\s*,\\s*/', $modSettings['chat_support_staff_groups']) : array('1');
	$allowed = array_values(array_filter(array_map('intval', $allowed)));
	$groups = !empty($user_info['groups']) && is_array($user_info['groups']) ? array_map('intval', $user_info['groups']) : array();
	return !empty(array_intersect($allowed, $groups));
}

function better_chat_support_guest_token()
{
	if (empty($_SESSION['better_chat_support_guest_token'])) {
		try { $_SESSION['better_chat_support_guest_token'] = bin2hex(random_bytes(24)); }
		catch (Exception $e) { $_SESSION['better_chat_support_guest_token'] = sha1(uniqid('', true) . mt_rand()); }
	}
	return (string) $_SESSION['better_chat_support_guest_token'];
}

function better_chat_support_requester_where(&$params)
{
	global $user_info;
	if (!empty($user_info['id'])) {
		$params['requester_member'] = (int) $user_info['id'];
		return 'requester_member = {int:requester_member}';
	}
	$params['guest_token'] = better_chat_support_guest_token();
	return 'guest_token = {string:guest_token}';
}

function better_chat_support_get_session($id_support)
{
	global $smcFunc;
	$request = $smcFunc['db_query']('', '
		SELECT s.*, mem.real_name AS requester_name, staff.real_name AS staff_name
		FROM {db_prefix}chat_support_sessions AS s
		LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = s.requester_member)
		LEFT JOIN {db_prefix}members AS staff ON (staff.id_member = s.assigned_staff)
		WHERE s.id_support = {int:id_support}
		LIMIT 1', array('id_support' => (int) $id_support));
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);
	return $row ?: false;
}

function better_chat_support_can_access($session)
{
	global $user_info;
	if (empty($session)) return false;
	if (!empty($user_info['id'])) {
		$id = (int) $user_info['id'];
		return (int) $session['requester_member'] === $id || (int) $session['assigned_staff'] === $id || (!empty($user_info['is_admin']) && better_chat_support_is_staff());
	}
	return !empty($session['guest_token']) && hash_equals((string) $session['guest_token'], better_chat_support_guest_token());
}

function better_chat_support_payload($session)
{
	if (empty($session)) return null;
	return array(
		'id_support' => (int) $session['id_support'],
		'status' => (string) $session['status'],
		'requester_member' => (int) $session['requester_member'],
		'requester_name' => !empty($session['requester_member']) ? (!empty($session['requester_name']) ? $session['requester_name'] : 'Member') : (!empty($session['guest_name']) ? $session['guest_name'] : 'Guest'),
		'assigned_staff' => (int) $session['assigned_staff'],
		'staff_name' => !empty($session['staff_name']) ? $session['staff_name'] : '',
		'created_at' => (int) $session['created_at'],
		'updated_at' => (int) $session['updated_at'],
	);
}

function better_chat_support_process()
{
	global $smcFunc, $user_info, $modSettings;
	if (!better_chat_support_enabled())
		better_chat_json_response(array('status' => 'error', 'message' => 'Support chat is disabled.'));

	$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
	$action = $method === 'POST'
		? (isset($_POST['support_action']) ? (string) $_POST['support_action'] : '')
		: (isset($_GET['support_action']) ? (string) $_GET['support_action'] : 'status');

	// Polling is deliberately read-only. Never allow a GET refresh to replay a state-changing action.
	if ($method !== 'POST' && $action !== 'status')
		better_chat_json_response(array('status' => 'error', 'message' => 'Invalid support request method.'));
	if ($method === 'POST' && !in_array($action, array('request','claim','send','upload','close','duty'), true))
		better_chat_json_response(array('status' => 'error', 'message' => 'Invalid support action.'));
	$is_guest = empty($user_info['id']);
	$is_staff = better_chat_support_is_staff();

	if (!$is_guest && !$is_staff) {
		$support_restriction = better_chat_moderation_is_restricted((int) $user_info['id'], 'support');
		if ($support_restriction && in_array($action, array('request','send','upload'), true))
			better_chat_json_response(array('status'=>'error','message'=>better_chat_moderation_denied_message($support_restriction, 'Support')));
	}

	if ($is_guest && empty($modSettings['chat_support_allow_guests']))
		better_chat_json_response(array('status' => 'error', 'message' => 'Guest support is disabled.'));
	if (!$is_guest && !$is_staff && isset($modSettings['chat_support_allow_members']) && empty($modSettings['chat_support_allow_members']))
		better_chat_json_response(array('status' => 'error', 'message' => 'Member support is disabled.'));

	if (in_array($action, array('request','claim','send','upload','close','duty'), true))
		checkSession('post');

	if ($action === 'duty') {
		if (!$is_staff) better_chat_json_response(array('status' => 'error', 'message' => 'Staff only.'));
		$on = !empty($_POST['on_duty']) ? 1 : 0;
		$smcFunc['db_insert']('replace', '{db_prefix}chat_support_staff',
			array('id_member'=>'int','on_duty'=>'int','updated_at'=>'int'),
			array((int)$user_info['id'],$on,time()), array('id_member'));
		better_chat_json_response(array('status'=>'ok','on_duty'=>(bool)$on));
	}

	if ($action === 'request') {
		$params = array();
		$where = better_chat_support_requester_where($params);
		$request = $smcFunc['db_query']('', 'SELECT id_support FROM {db_prefix}chat_support_sessions WHERE ' . $where . ' AND status IN ({string:waiting},{string:active}) ORDER BY id_support DESC LIMIT 1', $params + array('waiting'=>'waiting','active'=>'active'));
		$existing = $smcFunc['db_fetch_assoc']($request); $smcFunc['db_free_result']($request);
		if (!empty($existing)) better_chat_json_response(array('status'=>'ok','session'=>better_chat_support_payload(better_chat_support_get_session((int)$existing['id_support']))));
		$guest_name = $is_guest ? trim(strip_tags((string)($_POST['guest_name'] ?? 'Guest'))) : '';
		if ($is_guest && $guest_name === '') $guest_name = 'Guest';
		if (strlen($guest_name) > 80) $guest_name = substr($guest_name, 0, 80);
		$smcFunc['db_insert']('insert', '{db_prefix}chat_support_sessions',
			array('requester_member'=>'int','guest_token'=>'string','guest_name'=>'string','assigned_staff'=>'int','status'=>'string','created_at'=>'int','updated_at'=>'int'),
			array($is_guest?0:(int)$user_info['id'],$is_guest?better_chat_support_guest_token():'',$guest_name,0,'waiting',time(),time()), array('id_support'));
		$id = (int) $smcFunc['db_insert_id']('{db_prefix}chat_support_sessions','id_support');
		better_chat_json_response(array('status'=>'ok','session'=>better_chat_support_payload(better_chat_support_get_session($id))));
	}

	if ($action === 'claim') {
		if (!$is_staff) better_chat_json_response(array('status'=>'error','message'=>'Staff only.'));
		$id = (int)($_POST['id_support'] ?? 0);
		$now = time();
		$smcFunc['db_query']('', 'UPDATE {db_prefix}chat_support_sessions SET assigned_staff={int:staff}, status={string:active}, updated_at={int:now} WHERE id_support={int:id} AND status={string:waiting}', array('staff'=>(int)$user_info['id'],'active'=>'active','now'=>$now,'id'=>$id,'waiting'=>'waiting'));

		// A support claim is a conversation event, not a normal staff message.
		// Only write it when this request actually transitioned waiting -> active.
		$claimed = !empty($smcFunc['db_affected_rows']) ? (int) $smcFunc['db_affected_rows']() : 1;
		if ($claimed > 0) {
			$staff_name = !empty($user_info['name']) ? (string) $user_info['name'] : 'Support';
			$smcFunc['db_insert']('insert', '{db_prefix}chat_support_messages',
				array('id_support'=>'int','sender_member'=>'int','sender_role'=>'string','body'=>'string','log_time'=>'int'),
				array($id,(int)$user_info['id'],'system',$staff_name . ' has joined the chat.',$now), array('id_message'));
		}

		better_chat_json_response(array('status'=>'ok','session'=>better_chat_support_payload(better_chat_support_get_session($id))));
	}


    if ($action === 'upload') {
        if (empty($modSettings['chat_support_allow_images']))
            better_chat_json_response(array('status'=>'error','message'=>'Support screenshot uploads are disabled.'));

        $id = (int)($_POST['id_support'] ?? 0);
        $session = better_chat_support_get_session($id);
        if (!better_chat_support_can_access($session) || $session['status'] !== 'active')
            better_chat_json_response(array('status'=>'error','message'=>'Support session is not active.'));

        if (empty($_FILES['file']) || !is_array($_FILES['file']))
            better_chat_json_response(array('status'=>'error','message'=>'No screenshot was received.'));

        $file = $_FILES['file'];
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK)
            better_chat_json_response(array('status'=>'error','message'=>'The screenshot upload did not complete.'));
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
            better_chat_json_response(array('status'=>'error','message'=>'The uploaded screenshot could not be verified.'));

        $image_input = !empty($modSettings['chat_allowed_extensions']) ? $modSettings['chat_allowed_extensions'] : 'jpg,jpeg,png,gif,webp';
        $image_allowed = array_values(array_filter(array_map('strtolower', array_map('trim', explode(',', $image_input)))));
        if (in_array('jpg', $image_allowed, true) && !in_array('jpeg', $image_allowed, true)) $image_allowed[] = 'jpeg';
        if (in_array('jpeg', $image_allowed, true) && !in_array('jpg', $image_allowed, true)) $image_allowed[] = 'jpg';

        $max_kb = !empty($modSettings['chat_max_filesize']) ? max(1, (int)$modSettings['chat_max_filesize']) : 5120;
        if ((int)$file['size'] <= 0 || (int)$file['size'] > ($max_kb * 1024))
            better_chat_json_response(array('status'=>'error','message'=>'The screenshot exceeds the configured image upload size limit.'));

        $image_info = @getimagesize($file['tmp_name']);
        if (empty($image_info['mime']))
            better_chat_json_response(array('status'=>'error','message'=>'The selected file is not a valid image.'));

        $mime_extensions = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        );
        if (!isset($mime_extensions[$image_info['mime']]))
            better_chat_json_response(array('status'=>'error','message'=>'That image format is not supported by Better Chat.'));
        $ext = $mime_extensions[$image_info['mime']];
        if (!in_array($ext, $image_allowed, true) && !($ext === 'jpg' && in_array('jpeg', $image_allowed, true)))
            better_chat_json_response(array('status'=>'error','message'=>'That image type is disabled in Better Chat settings.'));

        global $boarddir, $sourcedir;
        $upload_dir = $boarddir . '/chat_uploads';
        if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true))
            better_chat_json_response(array('status'=>'error','message'=>'Better Chat could not create its upload folder.'));
        if (!is_writable($upload_dir))
            better_chat_json_response(array('status'=>'error','message'=>'The Better Chat upload folder is not writable.'));
        if (!file_exists($upload_dir . '/index.php'))
            @file_put_contents($upload_dir . '/index.php', '<?php // Silence is golden ?>');

        try { $random = bin2hex(random_bytes(8)); }
        catch (Exception $e) { $random = str_replace('.', '', uniqid('', true)); }
        $file_name = time() . '_' . (!empty($user_info['id']) ? (int)$user_info['id'] : 0) . '_support_' . $random . '.' . $ext;
        $upload_path = $upload_dir . '/' . $file_name;
        $thumb_path = $upload_dir . '/thumb_' . $file_name;
        if (!move_uploaded_file($file['tmp_name'], $upload_path))
            better_chat_json_response(array('status'=>'error','message'=>'Better Chat could not save the screenshot.'));
        @chmod($upload_path, 0644);

        $thumb_created = false;
        if (file_exists($sourcedir . '/Subs-Graphics.php')) {
            require_once($sourcedir . '/Subs-Graphics.php');
            if (function_exists('createThumbnail')) {
                @createThumbnail($upload_path, 240, 240);
                foreach (array($upload_path . '_thumb', dirname($upload_path) . '/thumb_' . basename($upload_path)) as $candidate) {
                    if (file_exists($candidate)) {
                        if ($candidate !== $thumb_path) @rename($candidate, $thumb_path);
                        $thumb_created = file_exists($thumb_path);
                        break;
                    }
                }
            }
        }
        if ($thumb_created) @chmod($thumb_path, 0644);

        $sender_role = $is_staff && (int)$session['assigned_staff'] === (int)$user_info['id'] ? 'staff' : 'customer';
        $token = '[support_img]' . $file_name . '[/support_img]';
        $smcFunc['db_insert']('insert','{db_prefix}chat_support_messages',
            array('id_support'=>'int','sender_member'=>'int','sender_role'=>'string','body'=>'string','log_time'=>'int'),
            array($id,!empty($user_info['id'])?(int)$user_info['id']:0,$sender_role,$token,time()), array('id_message'));
        $id_message = (int) $smcFunc['db_insert_id']('{db_prefix}chat_support_messages', 'id_message');
        better_chat_storage_commit_file($upload_path, 'support', $file_name, !empty($user_info['id']) ? (int)$user_info['id'] : 0, $id_message, (string)$image_info['mime']);
        if ($thumb_created && is_file($thumb_path))
            better_chat_storage_commit_file($thumb_path, 'support', basename($thumb_path), !empty($user_info['id']) ? (int)$user_info['id'] : 0, $id_message, 'image/jpeg');
        $smcFunc['db_query']('', 'UPDATE {db_prefix}chat_support_sessions SET updated_at={int:now} WHERE id_support={int:id}', array('now'=>time(),'id'=>$id));
        better_chat_json_response(array('status'=>'ok','file'=>$file_name,'thumbnail'=>$thumb_created ? basename($thumb_path) : $file_name));
    }

	if ($action === 'send') {
		$id = (int)($_POST['id_support'] ?? 0); $session = better_chat_support_get_session($id);
		if (!better_chat_support_can_access($session) || $session['status'] !== 'active') better_chat_json_response(array('status'=>'error','message'=>'Support session is not active.'));
		$body = trim((string)($_POST['message'] ?? '')); if ($body === '') better_chat_json_response(array('status'=>'error','message'=>'Message is empty.'));

		// Idempotency guard: the browser assigns one nonce to one deliberate send.
		// If the same request is retried/replayed, return success without inserting again.
		$nonce = preg_replace('~[^a-zA-Z0-9._:-]~', '', (string)($_POST['client_nonce'] ?? ''));
		if ($nonce !== '') {
			if (!isset($_SESSION['better_chat_support_send_nonces']) || !is_array($_SESSION['better_chat_support_send_nonces']))
				$_SESSION['better_chat_support_send_nonces'] = array();
			$now = time();
			foreach ($_SESSION['better_chat_support_send_nonces'] as $seen_nonce => $seen_time)
				if ((int)$seen_time < $now - 600) unset($_SESSION['better_chat_support_send_nonces'][$seen_nonce]);
			if (isset($_SESSION['better_chat_support_send_nonces'][$nonce]))
				better_chat_json_response(array('status'=>'ok','duplicate'=>true));
			$_SESSION['better_chat_support_send_nonces'][$nonce] = $now;
		}

		$body = $smcFunc['htmlspecialchars']($body, ENT_QUOTES);
		$sender_role = $is_staff && (int)$session['assigned_staff'] === (int)$user_info['id'] ? 'staff' : 'customer';
		$smcFunc['db_insert']('insert','{db_prefix}chat_support_messages', array('id_support'=>'int','sender_member'=>'int','sender_role'=>'string','body'=>'string','log_time'=>'int'), array($id,!empty($user_info['id'])?(int)$user_info['id']:0,$sender_role,$body,time()), array('id_message'));
		$smcFunc['db_query']('', 'UPDATE {db_prefix}chat_support_sessions SET updated_at={int:now} WHERE id_support={int:id}', array('now'=>time(),'id'=>$id));
		better_chat_json_response(array('status'=>'ok'));
	}

	if ($action === 'close') {
		$id = (int)($_POST['id_support'] ?? 0); $session = better_chat_support_get_session($id);
		if (!better_chat_support_can_access($session)) better_chat_json_response(array('status'=>'error','message'=>'Not allowed.'));
		$smcFunc['db_query']('', 'UPDATE {db_prefix}chat_support_sessions SET status={string:closed}, updated_at={int:now} WHERE id_support={int:id}', array('closed'=>'closed','now'=>time(),'id'=>$id));
		better_chat_json_response(array('status'=>'ok'));
	}

	$on_duty = false;
	$queue = array();
	if ($is_staff) {
		$r=$smcFunc['db_query']('', 'SELECT on_duty FROM {db_prefix}chat_support_staff WHERE id_member={int:id} LIMIT 1', array('id'=>(int)$user_info['id']));
		$row=$smcFunc['db_fetch_assoc']($r); $smcFunc['db_free_result']($r); $on_duty=!empty($row['on_duty']);
		$r=$smcFunc['db_query']('', 'SELECT s.*, mem.real_name AS requester_name FROM {db_prefix}chat_support_sessions AS s LEFT JOIN {db_prefix}members AS mem ON (mem.id_member=s.requester_member) WHERE s.status={string:waiting} ORDER BY s.created_at ASC LIMIT 25', array('waiting'=>'waiting'));
		while($row=$smcFunc['db_fetch_assoc']($r)) $queue[] = better_chat_support_payload($row + array('staff_name'=>''));
		$smcFunc['db_free_result']($r);
	}

	$params=array(); $where=better_chat_support_requester_where($params);
	if ($is_staff) {
		$params=array('staff'=>(int)$user_info['id'],'active'=>'active');
		$r=$smcFunc['db_query']('', 'SELECT id_support FROM {db_prefix}chat_support_sessions WHERE assigned_staff={int:staff} AND status={string:active} ORDER BY updated_at DESC LIMIT 1',$params);
	} else {
		$params['waiting']='waiting'; $params['active']='active';
		$r=$smcFunc['db_query']('', 'SELECT id_support FROM {db_prefix}chat_support_sessions WHERE '.$where.' AND status IN ({string:waiting},{string:active}) ORDER BY id_support DESC LIMIT 1',$params);
	}
	$cur=$smcFunc['db_fetch_assoc']($r); $smcFunc['db_free_result']($r);
	$session = !empty($cur['id_support']) ? better_chat_support_get_session((int)$cur['id_support']) : false;
	$messages=array();
	if ($session && better_chat_support_can_access($session)) {
		$r=$smcFunc['db_query']('', 'SELECT id_message,sender_member,sender_role,body,log_time FROM {db_prefix}chat_support_messages WHERE id_support={int:id} ORDER BY id_message ASC LIMIT 100', array('id'=>(int)$session['id_support']));
		while($row=$smcFunc['db_fetch_assoc']($r)) {
            if ((string) $row['sender_role'] === 'system') {
                $row['body'] = htmlspecialchars((string) $row['body'], ENT_QUOTES, 'UTF-8');
            } elseif (preg_match('~^\[support_img\]([a-zA-Z0-9._-]+)\[/support_img\]$~', (string)$row['body'], $m)) {
                $safe_file = basename($m[1]);
                $urls = better_chat_storage_urls('support', $safe_file, false);
                $full = $urls['full'];
                $thumb = $urls['thumb'];
                $row['body'] = '<a class="better-support-image-link" href="' . htmlspecialchars($full, ENT_QUOTES) . '" target="_blank" rel="noopener"><img class="better-support-image" src="' . htmlspecialchars($thumb, ENT_QUOTES) . '" alt="Support screenshot" loading="lazy"></a>';
            } else {
                $row['body']=parse_bbc($row['body']);
            }
            $row['time_text']=timeformat((int)$row['log_time'],'%I:%M %p');
            $messages[]=$row;
        }
		$smcFunc['db_free_result']($r);
	}
	$support_restriction_payload = null;
	if (!$is_guest && !$is_staff) {
		$support_restriction = better_chat_moderation_is_restricted((int) $user_info['id'], 'support');
		$support_restriction_payload = better_chat_moderation_restriction_payload($support_restriction, 'Support');
	}
	better_chat_json_response(array('status'=>'ok','is_staff'=>$is_staff,'on_duty'=>$on_duty,'queue'=>$queue,'session'=>better_chat_support_payload($session),'messages'=>$messages,'restriction'=>$support_restriction_payload));
}

function chat_login_bootstrap_marker($member_name, $password = null, $cookie_time = null)
{
	// Called by SMF's integrate_login only after Login2 has accepted credentials.
	// Use a tiny non-sensitive, short-lived marker that the already-rendered guest
	// page can observe. The real SMF authentication cookie remains authoritative.
	if (headers_sent())
		return;

	$secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
	setcookie('better_chat_post_login', '1', time() + 60, '/', '', $secure, false);
}

function chat_load_assets()
{
	static $better_chat_assets_loaded = false;

	if ($better_chat_assets_loaded)
		return;

	$better_chat_assets_loaded = true;

	global $context, $modSettings, $settings, $scripturl, $boardurl, $user_info;

	/*
	 * test37d: Better Chat must never inject page HTML/JS into raw SMF
	 * responses. In particular, action=verificationcode returns a binary
	 * CAPTCHA image. The guest login-marker bridge previously echoed a script
	 * into that GIF response, which made the CAPTCHA appear as a broken image.
	 *
	 * Keep this guard before cookies, retention maintenance, asset loading, and
	 * every echo in this hook. dlattach and chat_msg are included for the same
	 * reason: they are download/JSON endpoints, not normal rendered pages.
	 */
	$request_action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : (!empty($context['current_action']) ? (string) $context['current_action'] : '');
	$raw_response_actions = array('verificationcode', 'dlattach', 'chat_msg');

	if (in_array($request_action, $raw_response_actions, true))
		return;

	// test32u: integrate_load_theme can run while the theme-facing $context['user']
	// mirror is still settling immediately after login. $user_info is SMF's
	// authoritative authenticated identity at this point in the request.
	$is_guest = !empty($user_info['is_guest']);
	$member_id = !empty($user_info['id']) ? (int) $user_info['id'] : 0;

	// If a full authenticated navigation beat the guest watcher to the marker,
	// clear it server-side so it can never cause a later stale reload.
	if (!$is_guest && !headers_sent() && isset($_COOKIE['better_chat_post_login'])) {
		$secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
		setcookie('better_chat_post_login', '', time() - 3600, '/', '', $secure, false);
		unset($_COOKIE['better_chat_post_login']);
	}

	/*
	 * Better Chat belongs only in a complete rendered forum page.
	 *
	 * SMF's top Profile/Messages/Alerts menus and several admin/package actions
	 * load HTML fragments with requests carrying the X-SMF-AJAX header and/or
	 * the ;ajax request flag. integrate_load_theme still runs during those
	 * fragment requests. If we echo the chat there, SMF inserts an entire second
	 * fixed-position chat window into the existing page.
	 */
	$is_smf_ajax = !empty($_SERVER['HTTP_X_SMF_AJAX'])
		|| isset($_REQUEST['ajax'])
		|| isset($_REQUEST['xml'])
		|| (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'chat');

	$is_package_manager = !empty($_REQUEST['action'])
		&& $_REQUEST['action'] === 'admin'
		&& !empty($_REQUEST['area'])
		&& $_REQUEST['area'] === 'packages';

	// test34: the dedicated full-page Community Chat action renders its own
	// single chat canvas. Do not inject the floating Community/Support widgets
	// into that page as a second copy of Better Chat.
	$is_full_chat_page = (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'chat_full')
		|| (!empty($_GET['action']) && $_GET['action'] === 'chat_full')
		|| (!empty($context['current_action']) && $context['current_action'] === 'chat_full');

	// test33c: retention maintenance is tied to ordinary rendered page traffic,
	// not Community Chat polling. This keeps cleanup working in Support-only mode
	// and for sites where the Community window is normally minimized.
	if (!$is_smf_ajax && !$is_package_manager && !empty($modSettings['chat_enabled']))
		better_chat_auto_prune_retention(false);

	$chat_mode = better_chat_support_mode();
	$full_restriction = $member_id > 0 ? better_chat_moderation_is_restricted($member_id, 'all') : false;
	$community_enabled = in_array($chat_mode, array('community', 'hybrid'), true);
	$support_enabled = in_array($chat_mode, array('support', 'hybrid'), true);
	$show_community_chat = !$is_guest && $community_enabled;
	$guest_support_allowed = $support_enabled && !empty($modSettings['chat_support_allow_guests']);

	if ($is_smf_ajax || $is_package_manager || $is_full_chat_page || empty($modSettings['chat_enabled']))
		return;

	/*
	 * test32y: successful-login bootstrap uses SMF's integrate_login hook as
	 * the source of truth. The hook sets a short-lived marker cookie only after
	 * SMF has accepted the credentials. A guest page watches for that marker and
	 * performs one clean reload, guaranteeing the next render is authenticated.
	 * This replaces transport guessing around form/XHR/fetch login requests.
	 */
	if ($is_guest && $community_enabled) {
		echo '<script>(function(){if(window.__betterChatLoginMarkerBridge)return;window.__betterChatLoginMarkerBridge=true;var name="better_chat_post_login";function marked(){var parts=document.cookie?document.cookie.split(";"):[];for(var i=0;i<parts.length;i++){var p=parts[i].replace(/^\s+/,"");if(p.indexOf(name+"=")===0)return p.substring(name.length+1)==="1";}return false;}function clear(){document.cookie=name+"=; Max-Age=0; path=/; SameSite=Lax";}function check(){if(!marked())return;clear();window.location.reload();}check();window.setInterval(check,100);window.addEventListener("pageshow",check);document.addEventListener("visibilitychange",function(){if(!document.hidden)check();});})();</script>';
	}

	if ($is_guest && !$guest_support_allowed)
		return;

	$chat_width = !empty($modSettings['chat_width']) ? max(280, (int) $modSettings['chat_width']) : 500;
	$ffmpeg_path = better_chat_find_ffmpeg();
	$video_enabled = !empty($modSettings['chat_video_enabled']) && $ffmpeg_path !== '';
	$upload_accept = $video_enabled
		? 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm'
		: 'image/jpeg,image/png,image/gif,image/webp';
	$default_theme_url = !empty($settings['default_theme_url']) ? $settings['default_theme_url'] : $boardurl . '/Themes/default';
	$reaction_registry = better_chat_reaction_registry();

	if (function_exists('loadCSSFile'))
		loadCSSFile('BetterChat.css', array('default_theme' => true, 'force_current' => false, 'order_pos' => 9999, 'force_reload' => true), 'better_chat');

	if ($full_restriction) {
		$full_payload = better_chat_moderation_restriction_payload($full_restriction, 'Better Chat');
		$suspension_minimize_style = !empty($modSettings['chat_private_minimize_style']) && $modSettings['chat_private_minimize_style'] === 'bubbles' ? 'bubbles' : 'windows';
		// test32t: keep every suspension state completely out of layout until the
		// page load event confirms theme/CSS assets are ready. Critical fixed
		// positioning is also inline so Chrome can never paint the control at 0,0.
		echo '<div id="better-chat-full-suspension" class="better-chat-full-suspension" data-minimize-style="', $suspension_minimize_style, '" role="status" aria-live="polite" hidden style="display:none;visibility:hidden;opacity:0;pointer-events:none;position:fixed;right:18px;bottom:18px;z-index:99999">',
			'<button type="button" id="better-chat-suspension-minimize" class="better-chat-suspension-minimize" aria-label="Minimize suspension notice" title="Minimize">−</button>',
			'<div class="better-chat-restriction-title">🔒 Better Chat access suspended</div>',
			'<div class="better-chat-restriction-details"><strong>Reason:</strong> ', htmlspecialchars($full_payload['reason'], ENT_QUOTES), '</div>',
			'<div class="better-chat-restriction-details"><strong>Duration:</strong> ', htmlspecialchars($full_payload['duration_text'], ENT_QUOTES), '</div>',
			'<div class="better-chat-restriction-details"><strong>Expires:</strong> ', htmlspecialchars($full_payload['expires_text'], ENT_QUOTES), '</div>',
		'</div>',
		'<button type="button" id="better-chat-suspension-bubble" class="better-chat-suspension-bubble" aria-label="Restore suspension notice" title="Suspended" hidden style="display:none;visibility:hidden;opacity:0;pointer-events:none;position:fixed;right:18px;bottom:18px;z-index:99999">!</button>',
		'<button type="button" id="better-chat-suspension-compact" class="better-chat-suspension-compact better-chat-header chat_header" aria-label="Restore suspension notice" title="Restore suspension notice" hidden style="display:none;visibility:hidden;opacity:0;pointer-events:none;position:fixed;right:18px;bottom:18px;z-index:99999">Suspended</button>',
		'<noscript><style>#better-chat-full-suspension{display:block!important;visibility:visible!important;opacity:1!important;pointer-events:auto!important}</style></noscript>',
		'<script>(function(){',
			'var panel=document.getElementById("better-chat-full-suspension"),min=document.getElementById("better-chat-suspension-minimize"),bubble=document.getElementById("better-chat-suspension-bubble"),compact=document.getElementById("better-chat-suspension-compact");',
			'if(!panel)return;var mode=panel.getAttribute("data-minimize-style")||"windows";var key="betterChatFullSuspensionMinimized";',
			'function reveal(el){if(!el)return;el.hidden=false;el.style.display="";el.style.visibility="visible";el.style.opacity="1";el.style.pointerEvents="auto";}',
			'function conceal(el){if(!el)return;el.hidden=true;el.style.display="none";el.style.visibility="hidden";el.style.opacity="0";el.style.pointerEvents="none";}',
			'var ready=false,pending=false;',
			'function paint(v){pending=!!v;if(!ready)return;conceal(panel);conceal(bubble);conceal(compact);if(pending){if(mode==="bubbles")reveal(bubble);else reveal(compact);}else reveal(panel);}',
			'function setMinimized(v){pending=!!v;try{localStorage.setItem(key,pending?"1":"0");}catch(e){}paint(pending);}',
			'if(min)min.addEventListener("click",function(e){e.preventDefault();setMinimized(true);});',
			'if(bubble)bubble.addEventListener("click",function(e){e.preventDefault();setMinimized(false);});',
			'if(compact)compact.addEventListener("click",function(e){e.preventDefault();setMinimized(false);});',
			'try{pending=localStorage.getItem(key)==="1";}catch(e){pending=false;}',
			'function boot(){if(ready)return;ready=true;requestAnimationFrame(function(){requestAnimationFrame(function(){paint(pending);});});}',
			'if(document.readyState==="complete")boot();else window.addEventListener("load",boot,{once:true});',
			'var u=', json_encode($scripturl . '?action=chat_msg;presence_only=1'), ';function p(){fetch(u,{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.json();}).then(function(d){if(d&&d.suspended===false){try{localStorage.removeItem(key);}catch(e){}window.location.reload();}}).catch(function(){});}p();window.setInterval(p,10000);',
		'}());</script>';
		return;
	}

	if ($show_community_chat) echo '
	<div id="smf-chat-bar" class="better-chat" aria-busy="true" style="--better-chat-width:', $chat_width, 'px; visibility:hidden; opacity:0; pointer-events:none">
		<div id="chat-header" class="better-chat-header chat_header">
			<span><button type="button" id="mobile-user-toggle" class="better-chat-icon-button" aria-label="Show online users">☰</button> ',
				(!empty($modSettings['chat_bar_name']) ? htmlspecialchars($modSettings['chat_bar_name'], ENT_QUOTES) : 'Forum Chat'),
				'<span id="chat-online-count"></span></span>
			<span class="better-chat-header-actions">
				<a class="better-chat-popout" href="', $scripturl, '?action=chat_full" target="_blank" rel="noopener" aria-label="Open full-page group chat" title="Open full-page group chat">↗</a>
				<button type="button" class="better-chat-sound-toggle" aria-label="Toggle chat notification sound" title="Notification sound">🔊</button>
				<button type="button" id="chat-toggle" class="better-chat-icon-button" aria-label="Minimize chat">−</button>
			</span>
		</div>
		<div id="chat-content">
			<div id="chat-restriction-screen" class="better-chat-group-restriction-screen" role="status" aria-live="polite" hidden>
				<div id="chat-restriction" class="better-chat-restriction-notice better-chat-group-restriction"></div>
			</div>
			<div id="chat-main-row">
				<div id="chat-body"></div>
				<aside id="chat-user-list">
					<strong>Online</strong>
					<ul id="online-users"></ul>
				</aside>
			</div>
			<div id="chat-footer">
				<div class="better-chat-compose-wrap">
					<div class="better-chat-compose">
						<div class="better-chat-input-shell">
							<input type="text" id="chat-input" placeholder="Type a message..." autocomplete="off">
							<div class="better-chat-input-actions">
								<button type="button" class="better-chat-emoji-toggle" data-emoji-input="chat-input" aria-label="Choose emoji" title="Emoji">😀</button>
								', (!empty($modSettings['chat_giphy_enabled']) && !empty($modSettings['chat_giphy_api_key']) ? '<button type="button" class="better-chat-giphy-toggle" data-giphy-input="chat-input" aria-label="Choose GIF" title="GIF">GIF</button>' : ''), '
								<button type="button" class="better-chat-attach" data-file-target="chat-file-upload" aria-label="Attach image" title="Attach image">📎</button>
							</div>
						</div>
						<input type="file" id="chat-file-upload" accept="', $upload_accept, '" hidden>
					</div>
					<div class="better-chat-upload-progress" aria-live="polite" hidden>
						<div class="better-chat-upload-track"><span></span></div>
						<div class="better-chat-upload-label"></div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script>
		var chat_script_url = ', json_encode($scripturl), ';
		var chat_board_url = ', json_encode($boardurl), ';
		var chat_width = ', $chat_width, ';
		var smf_member_id = ', $member_id, ';
		var better_chat_notify_sound = ', !empty($modSettings['chat_notify_sound']) ? 'true' : 'false', ';
		var better_chat_allow_member_sound = ', !empty($modSettings['chat_allow_member_sound']) ? 'true' : 'false', ';
		var better_chat_notify_title = ', !empty($modSettings['chat_notify_title']) ? 'true' : 'false', ';
		var better_chat_notify_title_text = ', json_encode(!empty($modSettings['chat_notify_title_text']) ? $modSettings['chat_notify_title_text'] : '{username} has sent you a new message...'), ';
		var better_chat_private_minimize_style = ', json_encode(!empty($modSettings['chat_private_minimize_style']) ? $modSettings['chat_private_minimize_style'] : 'bubbles'), ';
		var better_chat_video_enabled = ', $video_enabled ? 'true' : 'false', ';
		var better_chat_giphy_enabled = ', (!empty($modSettings['chat_giphy_enabled']) && !empty($modSettings['chat_giphy_api_key'])) ? 'true' : 'false', ';
		var better_chat_giphy_api_key = ', json_encode(!empty($modSettings['chat_giphy_api_key']) ? trim((string) $modSettings['chat_giphy_api_key']) : ''), ';
		var better_chat_reactions = ', json_encode($reaction_registry), ';
		var better_chat_session_var = ', json_encode(!empty($context['session_var']) ? $context['session_var'] : ''), ';
		var better_chat_session_id = ', json_encode(!empty($context['session_id']) ? $context['session_id'] : ''), ';
	</script>
	<script src="', $boardurl, '/scripts/BetterChat.js?v=1.0.0-test38"></script>';

	if ($support_enabled) {
		$support_title = !empty($modSettings['chat_support_title']) ? $modSettings['chat_support_title'] : 'Support';
		$is_staff = better_chat_support_is_staff();
		echo '
		<div id="better-support-root" class="better-support-root" style="visibility:hidden" data-staff="', $is_staff ? '1' : '0', '">
			<button type="button" id="better-support-bubble" class="better-support-bubble" aria-label="Open ', htmlspecialchars($support_title, ENT_QUOTES), '" title="', htmlspecialchars($support_title, ENT_QUOTES), '">💬<span id="better-support-badge" hidden>0</span></button>
			<section id="better-support-panel" class="better-support-panel" hidden>
				<header class="better-support-header cat_bar"><strong class="catbg">', htmlspecialchars($support_title, ENT_QUOTES), '</strong><button type="button" id="better-support-close" aria-label="Minimize support">−</button></header>
				<div id="better-support-body" class="better-support-body"></div>
			</section>
		</div>
		<script>
		window.better_chat_support = ', json_encode(array(
			'enabled' => true,
			'mode' => $chat_mode,
			'is_guest' => $is_guest,
			'is_staff' => $is_staff,
			'title' => $support_title,
			'script_url' => $scripturl,
			'session_var' => !empty($context['session_var']) ? $context['session_var'] : '',
			'session_id' => !empty($context['session_id']) ? $context['session_id'] : '',
			'support_images' => !empty($modSettings['chat_support_allow_images']),
			'max_image_kb' => !empty($modSettings['chat_max_filesize']) ? (int)$modSettings['chat_max_filesize'] : 5120,
			'giphy_enabled' => !empty($modSettings['chat_giphy_enabled']) && !empty($modSettings['chat_giphy_api_key']),
			'giphy_api_key' => !empty($modSettings['chat_giphy_api_key']) ? trim((string)$modSettings['chat_giphy_api_key']) : '',
		)), ';
		</script>
		<script src="', $boardurl, '/scripts/SupportChat.js?v=1.0.0-test36"></script>';
	}
}


function better_chat_json_response($payload)
{
	// Chat actions are AJAX endpoints. Clear any buffered theme/debug output so
	// jQuery receives a clean JSON document instead of "JSON + stray markup".
	while (ob_get_level() > 0)
		@ob_end_clean();

	if (!headers_sent())
		header('Content-Type: application/json; charset=UTF-8');

	echo json_encode($payload);
	exit;
}

function ChatProcess()
{
    global $smcFunc, $context, $user_info, $modSettings, $sourcedir, $boarddir, $boardurl, $txt;

    if (!empty($_REQUEST['support_action']))
        better_chat_support_process();

    if (!empty($user_info['is_guest']))
        better_chat_json_response(array('status' => 'error', 'message' => 'Guest'));

    // A fully suspended member still counts as present while their suspension
    // notice is open. This presence-only heartbeat is deliberately permitted
    // before the full-suspension gate, but exposes no messages or chat actions.
    if (!empty($_REQUEST['presence_only'])) {
        if (!empty($user_info['id'])) {
            $smcFunc['db_insert'](
                'replace',
                '{db_prefix}chat_presence',
                array('id_member' => 'int', 'last_seen' => 'int'),
                array(array((int) $user_info['id'], time())),
                array('id_member')
            );
        }

        $presence_full_restriction = !empty($user_info['id']) ? better_chat_moderation_is_restricted((int) $user_info['id'], 'all') : false;
        better_chat_json_response(array(
            'status' => 'ok',
            'suspended' => !empty($presence_full_restriction),
        ));
    }

    $better_chat_all_restriction = better_chat_moderation_is_restricted((int) $user_info['id'], 'all');
    if ($better_chat_all_restriction)
        better_chat_json_response(array('status'=>'error','message'=>better_chat_moderation_denied_message($better_chat_all_restriction, 'Better Chat')));

    // --- 2B. PRIVATE GROUP MEMBER PICKER / MEMBERSHIP ACTIONS ---
    if (!empty($_GET['member_search'])) {
        $conversation_id = !empty($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : 0;
        $term = isset($_GET['term']) ? (string) $_GET['term'] : '';
        better_chat_json_response(array(
            'status' => 'ok',
            'members' => better_chat_member_picker_search($term, $conversation_id, (int) $user_info['id']),
        ));
    }

    if (!empty($_POST['add_people'])) {
        checkSession('post');
        $conversation_id = !empty($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
        $members = isset($_POST['members']) ? (array) $_POST['members'] : array();
        $result = better_chat_convert_or_add_people($conversation_id, (int) $user_info['id'], $members);
        if (empty($result['ok']))
            better_chat_json_response(array('status' => 'error', 'message' => !empty($result['message']) ? $result['message'] : 'Better Chat could not add those members.'));
        better_chat_json_response(array('status' => 'ok', 'conversation' => $result['conversation']));
    }

    if (!empty($_POST['leave_conversation'])) {
        checkSession('post');
        $conversation_id = !empty($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
        $result = better_chat_leave_private_group($conversation_id, (int) $user_info['id']);
        if (empty($result['ok']))
            better_chat_json_response(array('status' => 'error', 'message' => !empty($result['message']) ? $result['message'] : 'Better Chat could not leave that conversation.'));
        better_chat_json_response(array('status' => 'ok'));
    }

    // --- BETTER REACTIONS BRIDGE ---
    if (!empty($_POST['reaction_action'])) {
        checkSession('post');

        if (!better_chat_reactions_available())
            better_chat_json_response(array('status' => 'error', 'message' => 'Better Reactions is not available.'));

        $id_chat = !empty($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
        $message_row = better_chat_reaction_message_access($id_chat);
        if (empty($message_row))
            better_chat_json_response(array('status' => 'error', 'message' => 'That chat message is not available.'));

        if ($_POST['reaction_action'] === 'react') {
            $reaction = isset($_POST['reaction']) ? strtolower(trim((string) $_POST['reaction'])) : '';
            $result = BetterReactions_SetObjectReaction('chat_message', $id_chat, $reaction, (int) $user_info['id'], true);
            if (empty($result['ok']))
                better_chat_json_response(array('status' => 'error', 'message' => 'That reaction is not available.'));

            better_chat_json_response(array(
                'status' => 'ok',
                'summary' => $result['summary'],
            ));
        }

        if ($_POST['reaction_action'] === 'reactors') {
            $reaction = isset($_POST['reaction']) ? strtolower(trim((string) $_POST['reaction'])) : '';
            if ($reaction === '')
                better_chat_json_response(array('status' => 'error', 'message' => 'Reaction not found.'));

            better_chat_json_response(array(
                'status' => 'ok',
                'reactors' => BetterReactions_GetObjectReactors('chat_message', $id_chat, $reaction, 50),
            ));
        }

        better_chat_json_response(array('status' => 'error', 'message' => 'Unknown reaction action.'));
    }

    // --- 1. UPLOAD LOGIC ---
    if (isset($_FILES['file']) && isset($_POST['upload'])) {
        checkSession('post');
        $image_input = !empty($modSettings['chat_allowed_extensions'])
            ? $modSettings['chat_allowed_extensions']
            : 'jpg,jpeg,png,gif,webp';
        $image_allowed = array_values(array_filter(array_map('strtolower', array_map('trim', explode(',', $image_input)))));

        if (in_array('jpg', $image_allowed, true) && !in_array('jpeg', $image_allowed, true))
            $image_allowed[] = 'jpeg';
        if (in_array('jpeg', $image_allowed, true) && !in_array('jpg', $image_allowed, true))
            $image_allowed[] = 'jpg';

        $video_input = !empty($modSettings['chat_allowed_video_extensions'])
            ? $modSettings['chat_allowed_video_extensions']
            : 'mp4,webm';
        $video_allowed = array_values(array_filter(array_map('strtolower', array_map('trim', explode(',', $video_input)))));

        $file = $_FILES['file'];

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK)
            better_chat_json_response(array('status' => 'error', 'message' => 'The upload did not complete.'));

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
            better_chat_json_response(array('status' => 'error', 'message' => 'The uploaded file could not be verified.'));

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $normalized_ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $is_video = in_array($ext, $video_allowed, true);
        $is_image = in_array($ext, $image_allowed, true) || in_array($normalized_ext, $image_allowed, true);

        if (!$is_image && !$is_video)
            better_chat_json_response(array('status' => 'error', 'message' => 'That file type is not allowed.'));

        if ($is_video) {
            if (empty($modSettings['chat_video_enabled']))
                better_chat_json_response(array('status' => 'error', 'message' => 'Video attachments are disabled.'));

            $ffmpeg_path = better_chat_find_ffmpeg();
            if ($ffmpeg_path === '')
                better_chat_json_response(array('status' => 'error', 'message' => 'Video attachments require FFmpeg, but FFmpeg is not available.'));
        }

        $max_kb = $is_video
            ? (!empty($modSettings['chat_max_video_filesize']) ? max(1, (int) $modSettings['chat_max_video_filesize']) : 25600)
            : (!empty($modSettings['chat_max_filesize']) ? max(1, (int) $modSettings['chat_max_filesize']) : 5120);

        if ((int) $file['size'] <= 0 || (int) $file['size'] > ($max_kb * 1024))
            better_chat_json_response(array('status' => 'error', 'message' => 'The attachment exceeds the configured upload size limit.'));

        if ($is_image) {
            $image_info = @getimagesize($file['tmp_name']);
            if (empty($image_info['mime']))
                better_chat_json_response(array('status' => 'error', 'message' => 'The selected file is not a valid image.'));

            $mime_extensions = array(
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            );

            if (!isset($mime_extensions[$image_info['mime']]))
                better_chat_json_response(array('status' => 'error', 'message' => 'That image format is not supported by Better Chat.'));

            $ext = $mime_extensions[$image_info['mime']];

            if (!in_array($ext, $image_allowed, true) && !($ext === 'jpg' && in_array('jpeg', $image_allowed, true)))
                better_chat_json_response(array('status' => 'error', 'message' => 'That image type is disabled in Better Chat settings.'));
        } else {
            $mime = '';
            if (function_exists('finfo_open')) {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime = (string) @finfo_file($finfo, $file['tmp_name']);
                    @finfo_close($finfo);
                }
            }

            $video_mimes = array(
                'mp4' => array('video/mp4', 'application/mp4'),
                'webm' => array('video/webm', 'application/octet-stream'),
            );

            if (!isset($video_mimes[$ext]) || ($mime !== '' && !in_array($mime, $video_mimes[$ext], true)))
                better_chat_json_response(array('status' => 'error', 'message' => 'That video format could not be verified.'));
        }

        $upload_dir = $boarddir . '/chat_uploads';

        if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true))
            better_chat_json_response(array('status' => 'error', 'message' => 'Better Chat could not create its upload folder.'));

        if (!is_writable($upload_dir))
            better_chat_json_response(array('status' => 'error', 'message' => 'The Better Chat upload folder is not writable.'));

        if (!file_exists($upload_dir . '/index.php'))
            @file_put_contents($upload_dir . '/index.php', '<?php // Silence is golden ?>');

        try {
            $random = bin2hex(random_bytes(8));
        } catch (Exception $e) {
            $random = str_replace('.', '', uniqid('', true));
        }

        $fileName = time() . '_' . (int) $user_info['id'] . '_' . $random . '.' . $ext;
        $uploadPath = $upload_dir . '/' . $fileName;
        $thumbPath = $is_video
            ? $upload_dir . '/thumb_' . $fileName . '.jpg'
            : $upload_dir . '/thumb_' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath))
            better_chat_json_response(array('status' => 'error', 'message' => 'Better Chat could not save the uploaded attachment.'));

        @chmod($uploadPath, 0644);

        $thumb_created = false;

        if ($is_image && file_exists($sourcedir . '/Subs-Graphics.php')) {
            require_once($sourcedir . '/Subs-Graphics.php');

            if (function_exists('createThumbnail')) {
                @createThumbnail($uploadPath, 180, 180);
                $candidates = array(
                    $uploadPath . '_thumb',
                    dirname($uploadPath) . '/thumb_' . basename($uploadPath),
                );

                foreach ($candidates as $candidate) {
                    if (file_exists($candidate)) {
                        if ($candidate !== $thumbPath)
                            @rename($candidate, $thumbPath);

                        $thumb_created = file_exists($thumbPath);
                        break;
                    }
                }
            }
        } elseif ($is_video) {
            $ffmpeg_path = better_chat_find_ffmpeg();

            if ($ffmpeg_path === '' || !better_chat_function_available('exec')) {
                @unlink($uploadPath);
                better_chat_json_response(array('status' => 'error', 'message' => 'FFmpeg is present but PHP command execution is unavailable.'));
            }

            $command = escapeshellarg($ffmpeg_path)
                . ' -y -hide_banner -loglevel error -ss 00:00:01 -i '
                . escapeshellarg($uploadPath)
                . ' -frames:v 1 -vf '
                . escapeshellarg('scale=320:-2:force_original_aspect_ratio=decrease')
                . ' '
                . escapeshellarg($thumbPath)
                . ' 2>&1';

            $output = array();
            $status = 1;
            @exec($command, $output, $status);

            if ($status !== 0 || !file_exists($thumbPath) || filesize($thumbPath) <= 0) {
                @unlink($uploadPath);
                @unlink($thumbPath);
                better_chat_json_response(array('status' => 'error', 'message' => 'FFmpeg could not verify or preview that video.'));
            }

            $thumb_created = true;
        }

        if ($thumb_created)
            @chmod($thumbPath, 0644);

        $to = !empty($_POST['to']) ? (int) $_POST['to'] : 0;
        $conversation_id = !empty($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
        if ($conversation_id > 0) {
            $conversation = better_chat_get_conversation_for_member($conversation_id, (int) $user_info['id']);
            if (empty($conversation) || (string) $conversation['conversation_type'] !== 'group') {
                @unlink($uploadPath);
                if (!empty($thumbPath)) @unlink($thumbPath);
                better_chat_json_response(array('status' => 'error', 'message' => 'That private group is not available.'));
            }
        } elseif ($to > 0) {
            better_chat_get_direct_conversation((int) $user_info['id'], $to, true, true);
        }

        $upload_scope = ($to > 0 || $conversation_id > 0) ? 'private' : 'group';
        $upload_restriction = better_chat_moderation_is_restricted((int) $user_info['id'], $upload_scope);
        if ($upload_restriction) {
            @unlink($uploadPath);
            if (!empty($thumbPath)) @unlink($thumbPath);
            better_chat_json_response(array('status'=>'error','message'=>better_chat_moderation_denied_message($upload_restriction, $upload_scope === 'private' ? 'Private Chat' : 'Group Chat')));
        }
        $body = $is_video
            ? '[video_thumb]' . $fileName . '[/video_thumb]'
            : '[img_thumb]' . $fileName . '[/img_thumb]';

        if ($conversation_id > 0) {
            $id_chat = better_chat_insert_conversation_message($conversation_id, (int) $user_info['id'], $body);
        } else {
            $smcFunc['db_insert'](
                'insert',
                '{db_prefix}chat_messages',
                array('id_member_from' => 'int', 'id_member_to' => 'int', 'body' => 'string', 'log_time' => 'int'),
                array($user_info['id'], $to, $body, time()),
                array('id_chat')
            );
            $id_chat = (int) $smcFunc['db_insert_id']('{db_prefix}chat_messages', 'id_chat');
            if ($id_chat > 0)
                better_chat_delete_reactions_for_messages(array($id_chat));
        }

        $main_mime = $is_video ? (!empty($mime) ? $mime : 'video/' . $ext) : (!empty($image_info['mime']) ? (string)$image_info['mime'] : 'image/' . $ext);
        better_chat_storage_commit_file($uploadPath, 'messages', $fileName, (int)$user_info['id'], $id_chat, $main_mime);
        if ($thumb_created && is_file($thumbPath))
            better_chat_storage_commit_file($thumbPath, 'messages', basename($thumbPath), (int)$user_info['id'], $id_chat, 'image/jpeg');

        better_chat_json_response(array(
            'status' => 'ok',
            'file' => $fileName,
            'type' => $is_video ? 'video' : 'image',
            'thumbnail' => $thumb_created ? basename($thumbPath) : $fileName,
        ));
    }

    // --- 1B. PRIVATE CHAT CLEAR-MY-VIEW ---
    if (isset($_POST['private_clear'])) {
        checkSession('post');
        $other_member = !empty($_POST['target']) ? (int) $_POST['target'] : 0;
        if ($other_member <= 0 || $other_member === (int) $user_info['id'])
            better_chat_json_response(array('status' => 'error', 'message' => 'That private conversation is not available.'));

        $cleared_through = better_chat_private_clear_for_member((int) $user_info['id'], $other_member);
        better_chat_json_response(array('status' => 'ok', 'cleared_through' => $cleared_through));
    }

    // --- 2. SEND LOGIC ---
    if (isset($_POST['send']) && !empty($_POST['message'])) {
        checkSession('post');
        $message = $smcFunc['htmlspecialchars']($_POST['message'], ENT_QUOTES);
        $to = !empty($_POST['to']) ? (int) $_POST['to'] : 0;
        $conversation_id = !empty($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;

        if ($conversation_id > 0) {
            $conversation = better_chat_get_conversation_for_member($conversation_id, (int) $user_info['id']);
            if (empty($conversation) || (string) $conversation['conversation_type'] !== 'group')
                better_chat_json_response(array('status' => 'error', 'message' => 'That private group is not available.'));
            $send_restriction = better_chat_moderation_is_restricted((int) $user_info['id'], 'private');
            if ($send_restriction)
                better_chat_json_response(array('status'=>'error','message'=>better_chat_moderation_denied_message($send_restriction, 'Private Chat')));
            better_chat_insert_conversation_message($conversation_id, (int) $user_info['id'], $message);
            better_chat_json_response(array('status' => 'ok'));
        }

        if ($to > 0)
            better_chat_get_direct_conversation((int) $user_info['id'], $to, true, true);
        $send_scope = $to > 0 ? 'private' : 'group';
        $send_restriction = better_chat_moderation_is_restricted((int) $user_info['id'], $send_scope);
        if ($send_restriction)
            better_chat_json_response(array('status'=>'error','message'=>better_chat_moderation_denied_message($send_restriction, $to > 0 ? 'Private Chat' : 'Group Chat')));
        $smcFunc['db_insert']('insert', '{db_prefix}chat_messages',
            array('id_member_from' => 'int', 'id_member_to' => 'int', 'body' => 'string', 'log_time' => 'int'),
            array($user_info['id'], $to, $message, time()), array('id_chat')
        );
        $id_chat = (int) $smcFunc['db_insert_id']('{db_prefix}chat_messages', 'id_chat');
        if ($id_chat > 0)
            better_chat_delete_reactions_for_messages(array($id_chat));

        better_chat_json_response(array('status' => 'ok'));
    }

    // --- 2C. PRIVATE GROUP NAME ---
    if (!empty($_POST['rename_conversation'])) {
        checkSession('post');
        $conversation_id = !empty($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
        $title = isset($_POST['title']) ? (string) $_POST['title'] : '';
        $result = better_chat_rename_private_group($conversation_id, (int) $user_info['id'], $title);
        if (empty($result['ok']))
            better_chat_json_response(array('status' => 'error', 'message' => !empty($result['message']) ? $result['message'] : 'Better Chat could not rename that conversation.'));
        better_chat_json_response(array('status' => 'ok', 'conversation' => $result['conversation']));
    }

    // --- 2B. FETCH PRIVATE GROUP CONVERSATION ---
    $requested_conversation_id = isset($_REQUEST['conversation_id']) ? (int) $_REQUEST['conversation_id'] : 0;
    $mark_read = !isset($_REQUEST['mark_read']) || (int) $_REQUEST['mark_read'] !== 0;
    if ($requested_conversation_id > 0) {
        $group = better_chat_private_group_payload($requested_conversation_id, (int) $user_info['id']);
        if (empty($group))
            better_chat_json_response(array('status' => 'error', 'message' => 'That private group is not available.'));

        $conversation = better_chat_get_conversation_for_member($requested_conversation_id, (int) $user_info['id']);
        $cleared_through = !empty($conversation['cleared_through_id']) ? (int) $conversation['cleared_through_id'] : 0;
        $request = $smcFunc['db_query']('', '
            SELECT m.id_chat, m.body, COALESCE(mem.real_name, {string:system_name}) AS real_name,
                   m.id_member_to, m.id_member_from, m.log_time, m.is_read
            FROM {db_prefix}chat_conversation_messages AS cm
            INNER JOIN {db_prefix}chat_messages AS m ON (m.id_chat = cm.id_chat)
            LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member_from)
            WHERE cm.id_conversation = {int:id_conversation}
              AND m.id_chat > {int:cleared_through}
            ORDER BY m.id_chat DESC
            LIMIT 30',
            array('id_conversation' => $requested_conversation_id, 'cleared_through' => $cleared_through, 'system_name' => 'System'));

        $group_messages = array();
        $today = strtotime('today');
        $yesterday = strtotime('yesterday');
        $latest_id = 0;
        while ($row = $smcFunc['db_fetch_assoc']($request)) {
            $latest_id = max($latest_id, (int) $row['id_chat']);
            better_chat_add_attachment_urls($row, 'messages');
            $row['body'] = parse_bbc($row['body']);
            if ($row['log_time'] >= $today)
                $row['time_text'] = timeformat($row['log_time'], '%I:%M %p');
            elseif ($row['log_time'] >= $yesterday)
                $row['time_text'] = 'Yesterday ' . timeformat($row['log_time'], '%I:%M %p');
            else
                $row['time_text'] = timeformat($row['log_time'], '%b %d, %I:%M %p');
            $group_messages[] = $row;
        }
        $smcFunc['db_free_result']($request);

        if (better_chat_reactions_available() && !empty($group_messages)) {
            $ids = array();
            foreach ($group_messages as $row) $ids[] = (int) $row['id_chat'];
            $summaries = BetterReactions_GetObjectSummary('chat_message', $ids);
            foreach ($group_messages as &$row)
                $row['reactions'] = isset($summaries[(int) $row['id_chat']]) ? $summaries[(int) $row['id_chat']] : array('counts' => array(), 'mine' => '');
            unset($row);
        }

        if ($latest_id > 0 && $mark_read) {
            $smcFunc['db_query']('', '
                UPDATE {db_prefix}chat_conversation_members
                SET last_read_id = {int:last_read}
                WHERE id_conversation = {int:id_conversation} AND id_member = {int:id_member}',
                array('last_read' => $latest_id, 'id_conversation' => $requested_conversation_id, 'id_member' => (int) $user_info['id']));
        }

        $restriction = better_chat_moderation_is_restricted((int) $user_info['id'], 'private');
        better_chat_json_response(array(
            'status' => 'ok',
            'conversation' => $group,
            'messages' => $group_messages,
            'restriction' => better_chat_moderation_restriction_payload($restriction, 'Private Chat'),
        ));
    }

    // --- 3. FETCH MESSAGES ---
    $target = isset($_REQUEST['target']) ? (int) $_REQUEST['target'] : 0;
    $private_conversation_id = $target > 0
        ? better_chat_get_direct_conversation((int) $user_info['id'], $target, true, true)
        : 0;

    $private_cleared_through = $target > 0
        ? better_chat_private_cleared_through((int) $user_info['id'], $target)
        : 0;

    // Mark as read for Private Chat. Full-page hub background polling passes
    // mark_read=0 so unread tabs remain authoritative until actually viewed.
    if ($target > 0 && $mark_read) {
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}chat_messages 
            SET is_read = 1 
            WHERE id_member_from = {int:target} AND id_member_to = {int:me} AND is_read = 0', 
            array('target' => $target, 'me' => $user_info['id'])
        );
    }

    $request = $smcFunc['db_query']('', '
        SELECT m.id_chat, m.body, mem.real_name, m.id_member_to, m.id_member_from, m.log_time, m.is_read
        FROM {db_prefix}chat_messages AS m
        LEFT JOIN {db_prefix}members AS mem ON (m.id_member_from = mem.id_member)
        WHERE ' . ($target > 0 ? 
            '((m.id_member_from = {int:me} AND m.id_member_to = {int:target}) OR (m.id_member_from = {int:target} AND m.id_member_to = {int:me})) AND m.id_chat > {int:cleared_through}' : 
            '(m.id_member_to = 0 OR m.id_member_to = {int:me})') . '
          AND NOT EXISTS (
              SELECT 1
              FROM {db_prefix}chat_conversation_messages AS bcm
              INNER JOIN {db_prefix}chat_conversations AS bc
                  ON (bc.id_conversation = bcm.id_conversation)
              WHERE bcm.id_chat = m.id_chat
                AND bc.conversation_type = {string:group_type}
          )
        ORDER BY m.id_chat DESC LIMIT 20',
        array('me' => $user_info['id'], 'target' => $target, 'cleared_through' => $private_cleared_through, 'group_type' => 'group')
    );

    $messages = array();
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');

    while ($row = $smcFunc['db_fetch_assoc']($request)) {
        better_chat_add_attachment_urls($row, 'messages');
        $row['body'] = parse_bbc($row['body']);
        if ($row['log_time'] >= $today)
            $row['time_text'] = timeformat($row['log_time'], '%I:%M %p');
        elseif ($row['log_time'] >= $yesterday)
            $row['time_text'] = 'Yesterday ' . timeformat($row['log_time'], '%I:%M %p');
        else
            $row['time_text'] = timeformat($row['log_time'], '%b %d, %I:%M %p');

        $messages[] = $row;
    }
    $smcFunc['db_free_result']($request);

    if (better_chat_reactions_available() && !empty($messages)) {
        $reaction_ids = array();
        foreach ($messages as $message_row)
            $reaction_ids[] = (int) $message_row['id_chat'];

        $reaction_summaries = BetterReactions_GetObjectSummary('chat_message', $reaction_ids);
        foreach ($messages as &$message_row)
            $message_row['reactions'] = isset($reaction_summaries[(int) $message_row['id_chat']])
                ? $reaction_summaries[(int) $message_row['id_chat']]
                : array('counts' => array(), 'mine' => '');
        unset($message_row);
    }

    // --- 4. BETTER CHAT PRESENCE / ONLINE LIST ---
    // Better Chat tracks its own live presence instead of inferring it from
    // SMF's last_login or log_online state. A member is considered online in
    // Better Chat while their public chat heartbeat is still arriving.
    $online_users = array();

    if ($target === 0) {
        $now = time();

        // The public poll is the heartbeat. REPLACE keeps one row per member.
        if (!empty($user_info['id'])) {
            $smcFunc['db_insert'](
                'replace',
                '{db_prefix}chat_presence',
                array('id_member' => 'int', 'last_seen' => 'int'),
                array(array((int) $user_info['id'], $now)),
                array('id_member')
            );
        }

        // Five-second foreground polls and ten-second background polls make a
        // 45-second window generous enough for browser throttling without
        // leaving closed sessions hanging around for long.
        $presence_cutoff = $now - 45;

        $request_online = $smcFunc['db_query']('', '
            SELECT p.id_member, mem.real_name, mem.show_online
            FROM {db_prefix}chat_presence AS p
                INNER JOIN {db_prefix}members AS mem ON (mem.id_member = p.id_member)
            WHERE p.last_seen >= {int:cutoff}
            ORDER BY mem.real_name ASC',
            array('cutoff' => $presence_cutoff)
        );

        while ($row = $smcFunc['db_fetch_assoc']($request_online)) {
            if (!empty($row['show_online']) || !empty($user_info['is_admin'])) {
                // Presence remains authoritative and untouched. Moderation is
                // layered on only after a member has already qualified as live.
                $group_restriction = better_chat_moderation_is_restricted((int) $row['id_member'], 'group');
                $full_restriction = better_chat_moderation_is_restricted((int) $row['id_member'], 'all');
                $online_users[] = array(
                    'id' => (int) $row['id_member'],
                    'name' => $row['real_name'],
                    'group_restricted' => !empty($group_restriction) || !empty($full_restriction),
                    'suspended' => !empty($full_restriction),
                );
            }
        }

        $smcFunc['db_free_result']($request_online);

        // Cheap stale-row housekeeping; this never affects the live cutoff.
        if (($now % 60) < 6) {
            $smcFunc['db_query']('', '
                DELETE FROM {db_prefix}chat_presence
                WHERE last_seen < {int:stale}',
                array('stale' => $now - 3600)
            );
        }
    }

    // --- 5. INCOMING PRIVATE CHAT SIGNAL ---
    // Receiver-side private-window discovery must not depend on the mixed
    // 20-message public payload. Return a small recipient-only unread queue
    // so new private conversations can open reliably even during busy group chat.
    $incoming_private = array();
    if ($target === 0) {
        $request_private = $smcFunc['db_query']('', '
            SELECT m.id_chat, m.id_member_from, mem.real_name
            FROM {db_prefix}chat_messages AS m
            LEFT JOIN {db_prefix}members AS mem ON (m.id_member_from = mem.id_member)
            WHERE m.id_member_to = {int:me}
                AND m.is_read = 0
                AND NOT EXISTS (
                    SELECT 1
                    FROM {db_prefix}chat_private_state AS ps
                    WHERE ps.id_member = {int:me}
                      AND ps.other_member = m.id_member_from
                      AND m.id_chat <= ps.cleared_through_id
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM {db_prefix}chat_conversation_messages AS bcm
                    INNER JOIN {db_prefix}chat_conversations AS bc
                        ON (bc.id_conversation = bcm.id_conversation)
                    WHERE bcm.id_chat = m.id_chat
                      AND bc.conversation_type = {string:group_type}
                )
            ORDER BY m.id_chat DESC
            LIMIT 10',
            array('me' => (int) $user_info['id'], 'group_type' => 'group')
        );

        while ($row = $smcFunc['db_fetch_assoc']($request_private)) {
            $incoming_private[] = array(
                'id_chat' => (int) $row['id_chat'],
                'id_member_from' => (int) $row['id_member_from'],
                'real_name' => !empty($row['real_name']) ? $row['real_name'] : 'Member',
            );
        }
        $smcFunc['db_free_result']($request_private);
    }

    // --- 5B. ACTIVE PRIVATE GROUP DISCOVERY ---
    $private_groups = array();
    if ($target === 0) {
        $request_groups = $smcFunc['db_query']('', '
            SELECT c.id_conversation, c.title, c.id_owner, c.updated_at, cm.last_read_id,
                   COALESCE(MAX(cmsg.id_chat), 0) AS latest_id
            FROM {db_prefix}chat_conversation_members AS cm
            INNER JOIN {db_prefix}chat_conversations AS c ON (c.id_conversation = cm.id_conversation)
            LEFT JOIN {db_prefix}chat_conversation_messages AS cmsg ON (cmsg.id_conversation = c.id_conversation)
            WHERE cm.id_member = {int:id_member}
              AND cm.left_at = 0
              AND c.conversation_type = {string:type}
            GROUP BY c.id_conversation, c.title, c.id_owner, c.updated_at, cm.last_read_id
            ORDER BY c.updated_at DESC
            LIMIT 12',
            array('id_member' => (int) $user_info['id'], 'type' => 'group'));
        while ($row = $smcFunc['db_fetch_assoc']($request_groups)) {
            $payload = better_chat_private_group_payload((int) $row['id_conversation'], (int) $user_info['id']);
            if (empty($payload)) continue;
            $payload['latest_id'] = (int) $row['latest_id'];
            $payload['last_read_id'] = (int) $row['last_read_id'];
            $private_groups[] = $payload;
        }
        $smcFunc['db_free_result']($request_groups);
    }

    // --- 6. RETENTION MAINTENANCE ---
    // test33c moved retention to the full-page bootstrap so it also runs in
    // Support-only mode and no longer depends on Community Chat polling.

    // --- 7. MODERATION STATE + FINAL JSON OUTPUT ---
    $restriction_scope = $target > 0 ? 'private' : 'group';
    $restriction_label = $target > 0 ? 'Private Chat' : 'Group Chat';
    $active_restriction = better_chat_moderation_is_restricted((int) $user_info['id'], $restriction_scope);

    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'messages' => $messages,
        'conversation_id' => (int) $private_conversation_id,
        'online' => $online_users,
        'incoming_private' => $incoming_private,
        'private_groups' => $private_groups,
        'restriction' => better_chat_moderation_restriction_payload($active_restriction, $restriction_label),
    ));
    die();
}

function chat_admin_settings_page() {
    global $context, $scripturl, $sourcedir, $txt;
    loadLanguage('SmfCustomChat');
    $context['page_title'] = !empty($txt['chat_admin_title']) ? $txt['chat_admin_title'] : 'Chat Settings';
	require_once($sourcedir . '/ManageServer.php');
	loadTemplate('Admin');
	$context['sub_template'] = 'chat_settings';
	if (isset($_GET['op']) && $_GET['op'] === 'clear') ChatClearMessages();
	$config_vars = array();
	chat_admin_settings($config_vars);
	if (isset($_GET['save'])) { checkSession(); saveDBSettings($config_vars); redirectexit('action=admin;area=better_chat;sa=settings'); }
	$context['post_url'] = $scripturl . '?action=admin;area=better_chat;sa=settings;save';
	prepareDBSettingContext($config_vars);
}

function template_chat_admin_nav($active = 'settings')
{
    global $scripturl, $txt;

    $items = array(
        'settings' => !empty($txt['chat_admin_settings_tab']) ? $txt['chat_admin_settings_tab'] : 'Settings',
        'moderation' => !empty($txt['chat_moderation_tab']) ? $txt['chat_moderation_tab'] : 'Moderation',
        'logs' => !empty($txt['chat_logs_tab']) ? $txt['chat_logs_tab'] : 'Logs',
    );

    echo '<div class="buttonlist" style="margin:0 0 14px 0;display:flex;gap:8px;flex-wrap:wrap">';
    foreach ($items as $sa => $label) {
        $class = $sa === $active ? 'button active' : 'button';
        echo '<a class="', $class, '" href="', $scripturl, '?action=admin;area=better_chat;sa=', $sa, '">', htmlspecialchars($label, ENT_QUOTES), '</a>';
    }
    echo '</div>';
}

function template_chat_settings()
{
    template_chat_admin_nav('settings');
    template_show_settings();
}

function chat_admin_settings(&$config_vars) {
	global $txt;

	// SMF can call this admin hook before Better Chat's language file is loaded.
	loadLanguage('SmfCustomChat');

	// Never allow a missing translation to produce anonymous admin controls.
	$chat_txt_fallbacks = array(
		'chat_admin_general' => 'General',
		'chat_enabled' => 'Enable Better Chat',
		'chat_bar_name' => 'Group chat title',
		'chat_width' => 'Chat window width (px)',
		'chat_admin_private_chats' => 'Private Chats',
		'chat_private_minimize_style' => 'Minimized private chats',
		'chat_private_minimize_windows' => 'Compact windows',
		'chat_private_minimize_bubbles' => 'Messenger-style bubbles',
		'chat_admin_support' => 'Support / Customer Service',
		'chat_mode' => 'Operating mode',
		'chat_mode_community' => 'Community chat',
		'chat_mode_support' => 'Support / Customer Service',
		'chat_mode_hybrid' => 'Hybrid (community + support)',
		'chat_support_title' => 'Support chat title',
		'chat_support_allow_members' => 'Allow members to request support',
		'chat_support_allow_guests' => 'Allow guests to request support',
		'chat_support_staff_groups' => 'Support staff membergroup IDs',
		'chat_support_staff_groups_subtext' => 'Comma-separated SMF membergroup IDs. Administrators are always treated as support staff.',
		'chat_support_allow_images' => 'Allow screenshot uploads in support chats',
		'chat_support_allow_images_subtext' => 'Image-only. Uses the Better Chat image size and extension limits below.',
		'chat_admin_notifications' => 'Notifications',
		'chat_notify_sound' => 'Enable private-message notification sounds',
		'chat_allow_member_sound' => 'Allow members to turn notification sounds on or off',
		'chat_notify_title' => 'Blink the browser title for new private messages while the page is in the background',
		'chat_notify_title_text' => 'Browser title notification text',
		'chat_notify_title_text_subtext' => 'Use {username} where the sender name should appear. Default: {username} has sent you a new message...',
		'chat_admin_history' => 'History and Cleanup',
		'chat_purge_time' => 'Legacy automatic purge interval',
		'chat_retention_group_hours' => 'Keep Group Chat history for',
		'chat_retention_private_hours' => 'Keep Private Chat history for',
		'chat_retention_support_hours' => 'Keep closed Support history for',
		'chat_retention_audit_hours' => 'Keep Better Chat audit logs for',
		'chat_retention_help' => 'Retention runs automatically about once per hour when the forum receives normal page traffic. Active/waiting Support sessions are never pruned.',
		'chat_purge_never' => 'Never (keep all)',
		'chat_purge_1_hour' => '1 hour',
		'chat_purge_12_hours' => '12 hours',
		'chat_purge_1_day' => '24 hours (1 day)',
		'chat_purge_3_days' => '72 hours (3 days)',
		'chat_purge_1_week' => '168 hours (1 week)',
		'chat_purge_30_days' => '30 days',
		'chat_purge_90_days' => '90 days',
		'chat_purge_180_days' => '180 days',
		'chat_purge_1_year' => '1 year',
		'chat_admin_uploads' => 'Uploads',
		'chat_max_filesize' => 'Maximum image size (KB)',
		'chat_allowed_extensions' => 'Allowed image extensions',
		'chat_clear_history' => 'Clear chat history',
		'chat_clear_all_history' => 'Clear All History',
		'chat_clear_confirm' => 'Wipe all Better Chat messages and uploaded chat files?',
		'chat_admin_capabilities' => 'Environment & Capabilities',
		'chat_capabilities' => 'Detected server capabilities',
		'chat_video_enabled' => 'Enable video attachments when FFmpeg is available',
		'chat_max_video_filesize' => 'Maximum video size (KB)',
		'chat_allowed_video_extensions' => 'Allowed video extensions',
		'chat_ffmpeg_path' => 'FFmpeg path override',
		'chat_ffmpeg_path_subtext' => 'Leave blank for automatic detection. Example: /usr/bin/ffmpeg',
		'chat_admin_giphy' => 'GIFs (GIPHY)',
		'chat_giphy_enabled' => 'Enable GIPHY GIF picker',
		'chat_giphy_api_key' => 'GIPHY Web API key',
		'chat_giphy_api_key_subtext' => 'Create a Web API key in the GIPHY developer dashboard. The key is sent to the browser because GIPHY requires Trending requests to run client-side.',
	);

	foreach ($chat_txt_fallbacks as $chat_txt_key => $chat_txt_value)
		if (!isset($txt[$chat_txt_key]) || $txt[$chat_txt_key] === '')
			$txt[$chat_txt_key] = $chat_txt_value;

	$config_vars[] = array('title', 'chat_admin_general');
	$config_vars[] = array('check', 'chat_enabled');
	$config_vars[] = array('text', 'chat_bar_name');
	$config_vars[] = array('int', 'chat_width');

	$config_vars[] = array('title', 'chat_admin_private_chats');
	$config_vars[] = array('select', 'chat_private_minimize_style', array(
		'windows' => $txt['chat_private_minimize_windows'],
		'bubbles' => $txt['chat_private_minimize_bubbles'],
	));

	$config_vars[] = array('title', 'chat_admin_support');
	$config_vars[] = array('select', 'chat_mode', array(
		'community' => $txt['chat_mode_community'],
		'support' => $txt['chat_mode_support'],
		'hybrid' => $txt['chat_mode_hybrid'],
	));
	$config_vars[] = array('text', 'chat_support_title');
	$config_vars[] = array('check', 'chat_support_allow_members');
	$config_vars[] = array('check', 'chat_support_allow_guests');
	$config_vars[] = array('text', 'chat_support_staff_groups', 'subtext' => $txt['chat_support_staff_groups_subtext']);
	$config_vars[] = array('check', 'chat_support_allow_images', 'subtext' => $txt['chat_support_allow_images_subtext']);

	$config_vars[] = array('title', 'chat_admin_notifications');
	$config_vars[] = array('check', 'chat_notify_sound');
	$config_vars[] = array('check', 'chat_allow_member_sound');
	$config_vars[] = array('check', 'chat_notify_title');
	$config_vars[] = array('text', 'chat_notify_title_text', 'subtext' => $txt['chat_notify_title_text_subtext']);

	$config_vars[] = array('title', 'chat_admin_history');
	$retention_options = array(
		0 => $txt['chat_purge_never'],
		1 => $txt['chat_purge_1_hour'],
		12 => $txt['chat_purge_12_hours'],
		24 => $txt['chat_purge_1_day'],
		72 => $txt['chat_purge_3_days'],
		168 => $txt['chat_purge_1_week'],
		720 => $txt['chat_purge_30_days'],
		2160 => $txt['chat_purge_90_days'],
		4320 => $txt['chat_purge_180_days'],
		8760 => $txt['chat_purge_1_year'],
	);
	$config_vars[] = array('select', 'chat_retention_group_hours', $retention_options, 'subtext' => $txt['chat_retention_help']);
	$config_vars[] = array('select', 'chat_retention_private_hours', $retention_options);
	$config_vars[] = array('select', 'chat_retention_support_hours', $retention_options);
	$config_vars[] = array('select', 'chat_retention_audit_hours', $retention_options);
	$config_vars[] = array('callback', 'chat_clear_button');

	$config_vars[] = array('title', 'chat_admin_capabilities');
	$config_vars[] = array('callback', 'chat_capabilities');

	$config_vars[] = array('title', 'chat_admin_uploads');
	$config_vars[] = array('int', 'chat_max_filesize');
	$config_vars[] = array('text', 'chat_allowed_extensions');
	$config_vars[] = array('check', 'chat_video_enabled');
	$config_vars[] = array('int', 'chat_max_video_filesize');
	$config_vars[] = array('text', 'chat_allowed_video_extensions');
	$config_vars[] = array('text', 'chat_ffmpeg_path', 'subtext' => $txt['chat_ffmpeg_path_subtext']);

	$config_vars[] = array('title', 'chat_admin_giphy');
	$config_vars[] = array('check', 'chat_giphy_enabled');
	$config_vars[] = array('text', 'chat_giphy_api_key', 'subtext' => $txt['chat_giphy_api_key_subtext']);
}


function template_callback_chat_capabilities()
{
	global $modSettings;

	$ffmpeg = better_chat_find_ffmpeg();
	$cache_backend = better_chat_cache_backend();

	$rows = array(
		'SMF cache API' => (!empty($modSettings['cache_enable']) && function_exists('cache_get_data') && function_exists('cache_put_data'))
			? 'Available and enabled'
			: 'Not enabled',
		'Memcached extension' => class_exists('Memcached') ? 'Available' : 'Not detected',
		'Memcache extension' => class_exists('Memcache') ? 'Available' : 'Not detected',
		'Redis extension' => class_exists('Redis') ? 'Available' : 'Not detected',
		'APCu' => function_exists('apcu_fetch') ? 'Available' : 'Not detected',
		'Better Chat cache backend' => $cache_backend !== '' ? $cache_backend : 'Database only',
		'FFmpeg' => $ffmpeg !== '' ? 'Available: ' . htmlspecialchars($ffmpeg, ENT_QUOTES) : 'Not detected',
		'PHP command execution' => better_chat_function_available('exec') ? 'Available' : 'Disabled',
		'Video attachments' => (!empty($modSettings['chat_video_enabled']) && $ffmpeg !== '' && better_chat_function_available('exec'))
			? 'Ready'
			: 'Unavailable or disabled',
	);

	echo '<dt><strong>Server checks:</strong></dt><dd><div class="information">';

	foreach ($rows as $label => $value)
		echo '<div style="display:flex;justify-content:space-between;gap:20px;padding:4px 0;border-bottom:1px solid rgba(128,128,128,.2)"><span>', htmlspecialchars($label, ENT_QUOTES), '</span><strong>', $value, '</strong></div>';

	echo '</div></dd>';
}

function template_callback_chat_clear_button() {
	global $scripturl, $context, $txt;

	loadLanguage('SmfCustomChat');
	if (empty($txt['chat_clear_history'])) $txt['chat_clear_history'] = 'Clear chat history';
	if (empty($txt['chat_clear_all_history'])) $txt['chat_clear_all_history'] = 'Clear All History';
	if (empty($txt['chat_clear_confirm'])) $txt['chat_clear_confirm'] = 'Wipe all Better Chat messages and uploaded chat files?';
	echo '<dt><strong>', $txt['chat_clear_history'], ':</strong></dt><dd><a href="', $scripturl, '?action=admin;area=better_chat;sa=settings;op=clear;', $context['session_var'], '=', $context['session_id'], '" class="button_submit" onclick="return confirm(\'', addslashes($txt['chat_clear_confirm']), '\')">', $txt['chat_clear_all_history'], '</a></dd>';
}

function ChatClearMessages() {
	global $smcFunc, $boarddir;
	isAllowedTo('admin_forum'); checkSession('get');

	$request = $smcFunc['db_query']('', 'SELECT id_chat FROM {db_prefix}chat_messages', array());
	$message_ids = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$message_ids[] = (int) $row['id_chat'];
	$smcFunc['db_free_result']($request);

	$reaction_rows = better_chat_delete_reactions_for_messages($message_ids);

    $body_request = $smcFunc['db_query']('', 'SELECT body FROM {db_prefix}chat_messages', array());
    $attachment_bodies = array();
    while ($body_row = $smcFunc['db_fetch_assoc']($body_request)) $attachment_bodies[] = (string)$body_row['body'];
    $smcFunc['db_free_result']($body_request);
    better_chat_delete_private_attachments($attachment_bodies);

	$smcFunc['db_query']('', 'TRUNCATE TABLE {db_prefix}chat_messages', array());
	$smcFunc['db_query']('', 'TRUNCATE TABLE {db_prefix}chat_private_state', array());
	better_chat_audit_log('all_history_clear', !empty($GLOBALS['user_info']['id']) ? (int)$GLOBALS['user_info']['id'] : 0, 0, 0, 'Cleared all Better Chat community/private history and uploaded chat files; removed ' . $reaction_rows . ' Better Reactions row(s).');
	$files = glob($boarddir . '/chat_uploads/*');
	foreach($files as $file) if(is_file($file)) @unlink($file);
	redirectexit('action=admin;area=better_chat;sa=settings');
}


function better_chat_admin_member_search()
{
    global $smcFunc;

    isAllowedTo('admin_forum');
    $term = trim((string) ($_GET['term'] ?? ''));
    $rows = array();

    if (strlen($term) >= 2) {
        $request = $smcFunc['db_query']('', '
            SELECT id_member, member_name, real_name
            FROM {db_prefix}members
            WHERE real_name LIKE {string:term}
               OR member_name LIKE {string:term}
            ORDER BY real_name ASC
            LIMIT 12', array('term' => '%' . $term . '%'));
        while ($row = $smcFunc['db_fetch_assoc']($request)) {
            $rows[] = array(
                'id' => (int) $row['id_member'],
                'name' => (string) $row['real_name'],
                'username' => (string) $row['member_name'],
            );
        }
        $smcFunc['db_free_result']($request);
    }

    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('members' => $rows));
    die();
}

function better_chat_admin_active_members()
{
    global $smcFunc;

    $members = array();
    $now = time();

    // Members SMF currently considers online.
    $request = $smcFunc['db_query']('', '
        SELECT mem.id_member, mem.member_name, mem.real_name, MAX(lo.log_time) AS last_seen
        FROM {db_prefix}log_online AS lo
        INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
        WHERE lo.id_member > 0
          AND lo.log_time >= {int:cutoff}
        GROUP BY mem.id_member, mem.member_name, mem.real_name
        ORDER BY last_seen DESC
        LIMIT 30', array('cutoff' => $now - 900));
    while ($row = $smcFunc['db_fetch_assoc']($request)) {
        $id = (int) $row['id_member'];
        $members[$id] = array(
            'id' => $id,
            'name' => (string) $row['real_name'],
            'username' => (string) $row['member_name'],
            'online' => true,
            'chatting' => false,
            'last_activity' => (int) $row['last_seen'],
        );
    }
    $smcFunc['db_free_result']($request);

    // Anyone who has sent a group/private chat message recently.
    $request = $smcFunc['db_query']('', '
        SELECT mem.id_member, mem.member_name, mem.real_name, MAX(msg.log_time) AS last_chat
        FROM {db_prefix}chat_messages AS msg
        INNER JOIN {db_prefix}members AS mem ON (mem.id_member = msg.id_member_from)
        WHERE msg.id_member_from > 0
          AND msg.log_time >= {int:cutoff}
        GROUP BY mem.id_member, mem.member_name, mem.real_name
        ORDER BY last_chat DESC
        LIMIT 30', array('cutoff' => $now - 1800));
    while ($row = $smcFunc['db_fetch_assoc']($request)) {
        $id = (int) $row['id_member'];
        if (!isset($members[$id])) {
            $members[$id] = array(
                'id' => $id,
                'name' => (string) $row['real_name'],
                'username' => (string) $row['member_name'],
                'online' => false,
                'chatting' => true,
                'last_activity' => (int) $row['last_chat'],
            );
        } else {
            $members[$id]['chatting'] = true;
            $members[$id]['last_activity'] = max($members[$id]['last_activity'], (int) $row['last_chat']);
        }
    }
    $smcFunc['db_free_result']($request);

    // Include recent registered support participants too.
    $request = $smcFunc['db_query']('', '
        SELECT mem.id_member, mem.member_name, mem.real_name, MAX(sm.log_time) AS last_chat
        FROM {db_prefix}chat_support_messages AS sm
        INNER JOIN {db_prefix}members AS mem ON (mem.id_member = sm.sender_member)
        WHERE sm.sender_member > 0
          AND sm.log_time >= {int:cutoff}
        GROUP BY mem.id_member, mem.member_name, mem.real_name
        ORDER BY last_chat DESC
        LIMIT 30', array('cutoff' => $now - 1800));
    while ($row = $smcFunc['db_fetch_assoc']($request)) {
        $id = (int) $row['id_member'];
        if (!isset($members[$id])) {
            $members[$id] = array(
                'id' => $id,
                'name' => (string) $row['real_name'],
                'username' => (string) $row['member_name'],
                'online' => false,
                'chatting' => true,
                'last_activity' => (int) $row['last_chat'],
            );
        } else {
            $members[$id]['chatting'] = true;
            $members[$id]['last_activity'] = max($members[$id]['last_activity'], (int) $row['last_chat']);
        }
    }
    $smcFunc['db_free_result']($request);

    uasort($members, function ($a, $b) {
        if ($a['chatting'] !== $b['chatting']) return $a['chatting'] ? -1 : 1;
        if ($a['online'] !== $b['online']) return $a['online'] ? -1 : 1;
        return $b['last_activity'] <=> $a['last_activity'];
    });

    return array_slice(array_values($members), 0, 30);
}

function chat_admin_moderation_page()
{
    global $context, $scripturl, $smcFunc, $txt, $user_info;

    isAllowedTo('admin_forum');
    loadLanguage('SmfCustomChat');
    loadTemplate('Admin');
    $context['page_title'] = !empty($txt['chat_moderation_title']) ? $txt['chat_moderation_title'] : 'Better Chat Moderation';
    $context['sub_template'] = 'chat_moderation';

    if (isset($_POST['chat_moderation_apply'])) {
        checkSession('post');
        $member_input = trim((string) ($_POST['member'] ?? ''));
        $selected_member = (int) ($_POST['id_member'] ?? 0);
        $id_member = 0;

        if ($selected_member > 0) {
            $request = $smcFunc['db_query']('', 'SELECT id_member FROM {db_prefix}members WHERE id_member={int:id} LIMIT 1', array('id'=>$selected_member));
        } elseif ($member_input !== '' && ctype_digit($member_input)) {
            $request = $smcFunc['db_query']('', 'SELECT id_member FROM {db_prefix}members WHERE id_member={int:id} LIMIT 1', array('id'=>(int)$member_input));
        } else {
            $request = $smcFunc['db_query']('', 'SELECT id_member FROM {db_prefix}members WHERE real_name={string:name} OR member_name={string:name} ORDER BY id_member ASC LIMIT 1', array('name'=>$member_input));
        }
        if (isset($request)) {
            $row = $smcFunc['db_fetch_assoc']($request);
            $smcFunc['db_free_result']($request);
            if (!empty($row)) $id_member = (int) $row['id_member'];
        }

        $type = isset($_POST['restriction_type']) ? (string)$_POST['restriction_type'] : '';
        $allowed_types = array('group','private','support','all');
        $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (strlen($reason) > 500) $reason = substr($reason, 0, 500);

        if ($id_member <= 0)
            $context['chat_moderation_error'] = 'Member not found. Choose a suggestion, an active member, or enter an exact username/member ID.';
        elseif (!in_array($type, $allowed_types, true))
            $context['chat_moderation_error'] = 'Choose a valid restriction type.';
        elseif ($reason === '')
            $context['chat_moderation_error'] = 'Enter an internal moderation reason.';
        else {
            $expires = $duration > 0 ? time() + $duration : 0;
            $smcFunc['db_insert']('insert', '{db_prefix}chat_restrictions',
                array('id_member'=>'int','restriction_type'=>'string','reason'=>'string','id_moderator'=>'int','created_at'=>'int','expires_at'=>'int','revoked_at'=>'int','id_revoked_by'=>'int'),
                array($id_member,$type,$reason,(int)$user_info['id'],time(),$expires,0,0), array('id_restriction'));
            better_chat_audit_log('restriction_apply', (int)$user_info['id'], $id_member, 0, $type . ': ' . $reason);
            redirectexit('action=admin;area=better_chat;sa=moderation;applied=1');
        }
    }

    if (isset($_POST['chat_moderation_revoke'])) {
        checkSession('post');
        $id_restriction = (int)($_POST['id_restriction'] ?? 0);
        if ($id_restriction > 0) {
            $request = $smcFunc['db_query']('', 'SELECT id_member, restriction_type FROM {db_prefix}chat_restrictions WHERE id_restriction={int:id} LIMIT 1', array('id'=>$id_restriction));
            $restriction_row = $smcFunc['db_fetch_assoc']($request);
            $smcFunc['db_free_result']($request);
            $smcFunc['db_query']('', 'UPDATE {db_prefix}chat_restrictions SET revoked_at={int:now}, id_revoked_by={int:mod} WHERE id_restriction={int:id} AND revoked_at=0', array('now'=>time(),'mod'=>(int)$user_info['id'],'id'=>$id_restriction));
            if (!empty($restriction_row))
                better_chat_audit_log('restriction_revoke', (int)$user_info['id'], (int)$restriction_row['id_member'], 0, (string)$restriction_row['restriction_type']);
        }
        redirectexit('action=admin;area=better_chat;sa=moderation');
    }

    $now = time();
    $request = $smcFunc['db_query']('', '
        SELECT r.*, mem.real_name, moderator.real_name AS moderator_name, revoker.real_name AS revoker_name
        FROM {db_prefix}chat_restrictions AS r
        LEFT JOIN {db_prefix}members AS mem ON (mem.id_member=r.id_member)
        LEFT JOIN {db_prefix}members AS moderator ON (moderator.id_member=r.id_moderator)
        LEFT JOIN {db_prefix}members AS revoker ON (revoker.id_member=r.id_revoked_by)
        ORDER BY r.created_at DESC
        LIMIT 200', array());
    $rows = array();
    while ($row=$smcFunc['db_fetch_assoc']($request)) {
        $row['active'] = empty($row['revoked_at']) && (empty($row['expires_at']) || (int)$row['expires_at'] > $now);
        $rows[]=$row;
    }
    $smcFunc['db_free_result']($request);
    $context['chat_moderation_rows']=$rows;
    $context['chat_moderation_active_members'] = better_chat_admin_active_members();
}

function template_chat_moderation()
{
    global $context, $scripturl;

    $labels = array(
        'group' => 'Group Chat restriction',
        'private' => 'Private Chat restriction',
        'support' => 'Support ban',
        'all' => 'Full Better Chat suspension',
    );
    $durations = array(
        900 => '15 minutes',
        3600 => '1 hour',
        86400 => '24 hours',
        604800 => '7 days',
        2592000 => '30 days',
        0 => 'Permanent',
    );

    template_chat_admin_nav('moderation');
    echo '<div class="cat_bar"><h3 class="catbg">Better Chat Moderation</h3></div>';
    echo '<div class="information">Apply restrictions only to Better Chat. Forum posting and normal SMF permissions are not changed.</div>';
    if (!empty($_GET['applied'])) echo '<div class="infobox">Restriction applied.</div>';
    if (!empty($context['chat_moderation_error'])) echo '<div class="errorbox">', htmlspecialchars($context['chat_moderation_error'], ENT_QUOTES), '</div>';

    echo '<div class="windowbg better-chat-admin-moderation"><form method="post" action="', $scripturl, '?action=admin;area=better_chat;sa=moderation" id="better-chat-moderation-form">';
    echo '<dl class="settings better-chat-moderation-settings"><dt><strong>Member</strong><span class="smalltext">Start typing a username/display name, or choose an active member below.</span></dt><dd>';
    echo '<div style="position:relative;max-width:520px"><input type="text" id="better-chat-member-search" name="member" autocomplete="off" required style="width:100%"><input type="hidden" id="better-chat-member-id" name="id_member" value="0"><div id="better-chat-member-suggestions" class="windowbg" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:9999;border:1px solid rgba(128,128,128,.35);box-shadow:0 6px 18px rgba(0,0,0,.14);max-height:260px;overflow:auto"></div></div>';
    echo '</dd><dt><strong>Online / recently chatting</strong><span class="smalltext">Click a member to select them.</span></dt><dd><div style="display:flex;gap:6px;flex-wrap:wrap">';
    $active_members = !empty($context['chat_moderation_active_members']) ? $context['chat_moderation_active_members'] : array();
    if (empty($active_members)) {
        echo '<span class="smalltext">No registered members are currently online or recently active in Better Chat.</span>';
    } else {
        foreach ($active_members as $member) {
            $tag = !empty($member['chatting']) ? 'Chatting' : 'Online';
            echo '<button type="button" class="button better-chat-member-pick" data-member-id="',(int)$member['id'],'" data-member-name="',htmlspecialchars($member['name'],ENT_QUOTES),'">',htmlspecialchars($member['name'],ENT_QUOTES),' <span class="smalltext">(', $tag, ')</span></button>';
        }
    }
    echo '</div></dd>';
    echo '<dt><strong>Restriction</strong></dt><dd><select name="restriction_type">';
    foreach ($labels as $value=>$label) echo '<option value="',$value,'">',$label,'</option>';
    echo '</select></dd><dt><strong>Duration</strong></dt><dd><select name="duration">';
    foreach ($durations as $value=>$label) echo '<option value="',(int)$value,'">',$label,'</option>';
    echo '</select></dd><dt><strong>Internal reason / moderator note</strong></dt><dd><textarea name="reason" rows="3" maxlength="500" required></textarea></dd></dl>';
    echo '<div class="righttext"><button class="button" type="submit" name="chat_moderation_apply" value="1">Apply restriction</button></div>';
    echo '<input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"></form></div>';

    $rows = !empty($context['chat_moderation_rows']) ? $context['chat_moderation_rows'] : array();
    echo '<div class="cat_bar"><h3 class="catbg">Active Restrictions</h3></div><div class="windowbg">';
    $active = array_values(array_filter($rows, function($r){ return !empty($r['active']); }));
    if (empty($active)) echo '<p class="centertext">No active Better Chat restrictions.</p>';
    else {
        echo '<div class="table_grid"><table class="table_grid"><thead><tr class="title_bar"><th>Member</th><th>Restriction</th><th>Reason</th><th>Applied by</th><th>Expires</th><th></th></tr></thead><tbody>';
        foreach ($active as $row) {
            $expires = empty($row['expires_at']) ? 'Permanent' : timeformat((int)$row['expires_at']);
            echo '<tr class="windowbg"><td><strong>',htmlspecialchars($row['real_name'] ?: ('Member #'.$row['id_member']),ENT_QUOTES),'</strong><br><span class="smalltext">#',(int)$row['id_member'],'</span></td><td>',htmlspecialchars($labels[$row['restriction_type']] ?? $row['restriction_type'],ENT_QUOTES),'</td><td>',nl2br(htmlspecialchars($row['reason'],ENT_QUOTES)),'</td><td>',htmlspecialchars($row['moderator_name'] ?: ('#'.$row['id_moderator']),ENT_QUOTES),'</td><td>',$expires,'</td><td><form method="post" action="',$scripturl,'?action=admin;area=better_chat;sa=moderation"><input type="hidden" name="id_restriction" value="',(int)$row['id_restriction'],'"><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><button class="button" type="submit" name="chat_moderation_revoke" value="1">Revoke</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    echo '<div class="cat_bar"><h3 class="catbg">Moderation History</h3></div><div class="windowbg">';
    if (empty($rows)) echo '<p class="centertext">No moderation history yet.</p>';
    else {
        echo '<div class="table_grid"><table class="table_grid"><thead><tr class="title_bar"><th>Member</th><th>Restriction</th><th>Applied</th><th>Moderator</th><th>Status</th><th>Reason</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            if (!empty($row['active'])) $status='Active';
            elseif (!empty($row['revoked_at'])) $status='Revoked'.(!empty($row['revoker_name']) ? ' by '.htmlspecialchars($row['revoker_name'],ENT_QUOTES) : '');
            else $status='Expired';
            echo '<tr class="windowbg"><td>',htmlspecialchars($row['real_name'] ?: ('Member #'.$row['id_member']),ENT_QUOTES),'</td><td>',htmlspecialchars($labels[$row['restriction_type']] ?? $row['restriction_type'],ENT_QUOTES),'</td><td>',timeformat((int)$row['created_at']),'</td><td>',htmlspecialchars($row['moderator_name'] ?: ('#'.$row['id_moderator']),ENT_QUOTES),'</td><td>',$status,'</td><td>',nl2br(htmlspecialchars($row['reason'],ENT_QUOTES)),'</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    $search_url = $scripturl . '?action=admin;area=better_chat;sa=member_search;term=';
    echo '<script>(function(){const input=document.getElementById("better-chat-member-search"),hidden=document.getElementById("better-chat-member-id"),box=document.getElementById("better-chat-member-suggestions");if(!input||!hidden||!box)return;let timer=null,controller=null;function selectMember(id,name){hidden.value=id;input.value=name;box.style.display="none";box.innerHTML="";}document.querySelectorAll(".better-chat-member-pick").forEach(function(btn){btn.addEventListener("click",function(){selectMember(this.dataset.memberId,this.dataset.memberName);input.focus();});});input.addEventListener("input",function(){hidden.value="0";clearTimeout(timer);const term=this.value.trim();if(term.length<2){box.style.display="none";box.innerHTML="";return;}timer=setTimeout(function(){if(controller)controller.abort();controller=new AbortController();fetch(',json_encode($search_url),'+encodeURIComponent(term),{credentials:"same-origin",signal:controller.signal}).then(r=>r.json()).then(function(data){box.innerHTML="";const members=(data&&data.members)||[];if(!members.length){box.innerHTML="<div class=\\"smalltext\\" style=\\"padding:9px 10px\\">No matching members.</div>";box.style.display="block";return;}members.forEach(function(m){const b=document.createElement("button");b.type="button";b.className="button";b.style.cssText="display:block;width:100%;text-align:left;border-radius:0;margin:0";b.textContent=m.name+(m.username&&m.username!==m.name?" (@"+m.username+")":"")+"  #"+m.id;b.addEventListener("click",function(){selectMember(m.id,m.name);});box.appendChild(b);});box.style.display="block";}).catch(function(e){if(e.name!=="AbortError")box.style.display="none";});},180);});document.addEventListener("click",function(e){if(e.target!==input&&!box.contains(e.target))box.style.display="none";});})();</script>';
}

function chat_admin_logs_page()
{
    global $context, $scripturl, $smcFunc, $txt;

    isAllowedTo('admin_forum');
    loadLanguage('SmfCustomChat');
    loadTemplate('Admin');
    $context['page_title'] = !empty($txt['chat_logs_title']) ? $txt['chat_logs_title'] : 'Better Chat Logs';
    $context['sub_template'] = 'chat_logs';

    if (isset($_POST['chat_purge_private'])) {
        checkSession('post');
        $member_a = (int) ($_POST['member_a_id'] ?? 0);
        $member_b = (int) ($_POST['member_b_id'] ?? 0);
        $result = better_chat_admin_purge_private_conversation($member_a, $member_b);
        if (!empty($result['ok']))
            redirectexit('action=admin;area=better_chat;sa=logs;purged=' . (int) $result['count']);
        $context['chat_logs_error'] = !empty($result['message']) ? $result['message'] : 'The private conversation could not be purged.';
    }

    if (isset($_POST['chat_clear_group'])) {
        checkSession('post');
        $result = better_chat_admin_clear_group_history();
        redirectexit('action=admin;area=better_chat;sa=logs;group_cleared=' . (int) $result['count']);
    }

    // Shared log filters. Message bodies are never queried or displayed here.
    $event_types = array('restriction_apply', 'restriction_revoke', 'private_purge', 'group_history_clear', 'all_history_clear', 'auto_prune');
    $event = isset($_GET['event']) && in_array((string) $_GET['event'], $event_types, true) ? (string) $_GET['event'] : '';
    $member = isset($_GET['member']) ? trim((string) $_GET['member']) : '';
    $member_id = isset($_GET['member_id']) ? (int) $_GET['member_id'] : 0;
    $date_from = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
    $scope = isset($_GET['scope']) && in_array((string) $_GET['scope'], array('all', 'group', 'private'), true) ? (string) $_GET['scope'] : 'all';

    $from_ts = $date_from !== '' ? strtotime($date_from . ' 00:00:00') : 0;
    $to_ts = $date_to !== '' ? strtotime($date_to . ' 23:59:59') : 0;
    if ($from_ts === false) $from_ts = 0;
    if ($to_ts === false) $to_ts = 0;

    $context['chat_logs_filters'] = array(
        'event' => $event,
        'member' => $member,
        'member_id' => $member_id,
        'date_from' => $date_from,
        'date_to' => $date_to,
        'scope' => $scope,
    );

    // Optional exact-member activity view. This is metadata only; message bodies are never loaded.
    $context['chat_member_activity_summary'] = null;
    if ($member_id > 0) {
        $request = $smcFunc['db_query']('', '
            SELECT id_member, member_name, real_name
            FROM {db_prefix}members
            WHERE id_member = {int:member_id}
            LIMIT 1', array('member_id' => $member_id));
        $member_row = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);

        if (!empty($member_row)) {
            $summary = array(
                'id_member' => (int)$member_row['id_member'],
                'member_name' => (string)$member_row['member_name'],
                'real_name' => (string)$member_row['real_name'],
                'group_sent' => 0,
                'private_sent' => 0,
                'private_received' => 0,
                'support_sessions' => 0,
                'audit_actions' => 0,
                'active_restrictions' => 0,
                'last_chat' => 0,
            );

            $request = $smcFunc['db_query']('', '
                SELECT
                    SUM(CASE WHEN id_member_from = {int:member_id} AND id_member_to = 0 THEN 1 ELSE 0 END) AS group_sent,
                    SUM(CASE WHEN id_member_from = {int:member_id} AND id_member_to > 0 THEN 1 ELSE 0 END) AS private_sent,
                    SUM(CASE WHEN id_member_to = {int:member_id} THEN 1 ELSE 0 END) AS private_received,
                    MAX(CASE WHEN id_member_from = {int:member_id} OR id_member_to = {int:member_id} THEN log_time ELSE 0 END) AS last_chat
                FROM {db_prefix}chat_messages
                WHERE id_member_from = {int:member_id} OR id_member_to = {int:member_id}',
                array('member_id' => $member_id));
            $counts = $smcFunc['db_fetch_assoc']($request) ?: array();
            $smcFunc['db_free_result']($request);
            foreach (array('group_sent','private_sent','private_received','last_chat') as $key)
                $summary[$key] = !empty($counts[$key]) ? (int)$counts[$key] : 0;

            $request = $smcFunc['db_query']('', '
                SELECT COUNT(*) AS total
                FROM {db_prefix}chat_support_sessions
                WHERE requester_member = {int:member_id} OR assigned_staff = {int:member_id}',
                array('member_id' => $member_id));
            $row = $smcFunc['db_fetch_assoc']($request);
            $smcFunc['db_free_result']($request);
            $summary['support_sessions'] = !empty($row['total']) ? (int)$row['total'] : 0;

            $request = $smcFunc['db_query']('', '
                SELECT COUNT(*) AS total
                FROM {db_prefix}chat_audit_log
                WHERE id_actor = {int:member_id} OR id_subject = {int:member_id} OR id_related = {int:member_id}',
                array('member_id' => $member_id));
            $row = $smcFunc['db_fetch_assoc']($request);
            $smcFunc['db_free_result']($request);
            $summary['audit_actions'] = !empty($row['total']) ? (int)$row['total'] : 0;

            $now = time();
            $request = $smcFunc['db_query']('', '
                SELECT COUNT(*) AS total
                FROM {db_prefix}chat_restrictions
                WHERE id_member = {int:member_id}
                  AND revoked_at = 0
                  AND (expires_at = 0 OR expires_at > {int:now})',
                array('member_id' => $member_id, 'now' => $now));
            $row = $smcFunc['db_fetch_assoc']($request);
            $smcFunc['db_free_result']($request);
            $summary['active_restrictions'] = !empty($row['total']) ? (int)$row['total'] : 0;

            $context['chat_member_activity_summary'] = $summary;
        }
    }

    // Group history summary for the safe, group-only retention control.
    $request = $smcFunc['db_query']('', '
        SELECT COUNT(*) AS total, MIN(log_time) AS oldest, MAX(log_time) AS newest
        FROM {db_prefix}chat_messages
        WHERE id_member_to = 0', array());
    $context['chat_group_history_stats'] = $smcFunc['db_fetch_assoc']($request) ?: array('total'=>0,'oldest'=>0,'newest'=>0);
    $smcFunc['db_free_result']($request);

    // Recent community/private activity (metadata only).
    $activity_where = array('1=1');
    $activity_params = array();
    if ($scope === 'group') $activity_where[] = 'msg.id_member_to = 0';
    elseif ($scope === 'private') $activity_where[] = 'msg.id_member_to > 0';
    if ($from_ts) { $activity_where[] = 'msg.log_time >= {int:activity_from}'; $activity_params['activity_from'] = (int)$from_ts; }
    if ($to_ts) { $activity_where[] = 'msg.log_time <= {int:activity_to}'; $activity_params['activity_to'] = (int)$to_ts; }
    if ($member_id > 0) {
        $activity_where[] = '(msg.id_member_from = {int:activity_member_id} OR msg.id_member_to = {int:activity_member_id})';
        $activity_params['activity_member_id'] = $member_id;
    }
    elseif ($member !== '') {
        $activity_where[] = '(sender.real_name LIKE {string:activity_member} OR sender.member_name LIKE {string:activity_member} OR recipient.real_name LIKE {string:activity_member} OR recipient.member_name LIKE {string:activity_member})';
        $activity_params['activity_member'] = '%' . $member . '%';
    }
    $activity = array();
    $request = $smcFunc['db_query']('', '
        SELECT msg.id_chat, msg.id_member_from, msg.id_member_to, msg.log_time,
               sender.real_name AS sender_name, recipient.real_name AS recipient_name
        FROM {db_prefix}chat_messages AS msg
        LEFT JOIN {db_prefix}members AS sender ON (sender.id_member = msg.id_member_from)
        LEFT JOIN {db_prefix}members AS recipient ON (recipient.id_member = msg.id_member_to)
        WHERE ' . implode(' AND ', $activity_where) . '
        ORDER BY msg.log_time DESC
        LIMIT 100', $activity_params);
    while ($row = $smcFunc['db_fetch_assoc']($request)) $activity[] = $row;
    $smcFunc['db_free_result']($request);
    $context['chat_log_activity'] = $activity;

    // Filterable/paginated administrative audit trail.
    $audit_where = array('1=1');
    $audit_params = array();
    if ($event !== '') { $audit_where[] = 'l.event_type = {string:audit_event}'; $audit_params['audit_event'] = $event; }
    if ($from_ts) { $audit_where[] = 'l.created_at >= {int:audit_from}'; $audit_params['audit_from'] = (int)$from_ts; }
    if ($to_ts) { $audit_where[] = 'l.created_at <= {int:audit_to}'; $audit_params['audit_to'] = (int)$to_ts; }
    if ($member_id > 0) {
        $audit_where[] = '(l.id_actor = {int:audit_member_id} OR l.id_subject = {int:audit_member_id} OR l.id_related = {int:audit_member_id})';
        $audit_params['audit_member_id'] = $member_id;
    }
    elseif ($member !== '') {
        $audit_where[] = '(actor.real_name LIKE {string:audit_member} OR actor.member_name LIKE {string:audit_member} OR subject.real_name LIKE {string:audit_member} OR subject.member_name LIKE {string:audit_member} OR related.real_name LIKE {string:audit_member} OR related.member_name LIKE {string:audit_member})';
        $audit_params['audit_member'] = '%' . $member . '%';
    }

    $request = $smcFunc['db_query']('', '
        SELECT COUNT(*) AS total
        FROM {db_prefix}chat_audit_log AS l
        LEFT JOIN {db_prefix}members AS actor ON (actor.id_member = l.id_actor)
        LEFT JOIN {db_prefix}members AS subject ON (subject.id_member = l.id_subject)
        LEFT JOIN {db_prefix}members AS related ON (related.id_member = l.id_related)
        WHERE ' . implode(' AND ', $audit_where), $audit_params);
    $count_row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    $audit_total = !empty($count_row['total']) ? (int)$count_row['total'] : 0;
    $per_page = 50;
    $audit_page = max(1, isset($_GET['audit_page']) ? (int)$_GET['audit_page'] : 1);
    $audit_pages = max(1, (int)ceil($audit_total / $per_page));
    if ($audit_page > $audit_pages) $audit_page = $audit_pages;
    $audit_start = ($audit_page - 1) * $per_page;
    $audit_params['audit_start'] = $audit_start;
    $audit_params['audit_limit'] = $per_page;

    $audit = array();
    $request = $smcFunc['db_query']('', '
        SELECT l.*, actor.real_name AS actor_name, subject.real_name AS subject_name, related.real_name AS related_name
        FROM {db_prefix}chat_audit_log AS l
        LEFT JOIN {db_prefix}members AS actor ON (actor.id_member = l.id_actor)
        LEFT JOIN {db_prefix}members AS subject ON (subject.id_member = l.id_subject)
        LEFT JOIN {db_prefix}members AS related ON (related.id_member = l.id_related)
        WHERE ' . implode(' AND ', $audit_where) . '
        ORDER BY l.created_at DESC, l.id_log DESC
        LIMIT {int:audit_start}, {int:audit_limit}', $audit_params);
    while ($row = $smcFunc['db_fetch_assoc']($request)) $audit[] = $row;
    $smcFunc['db_free_result']($request);
    $context['chat_audit_rows'] = $audit;
    $context['chat_audit_paging'] = array('page'=>$audit_page,'pages'=>$audit_pages,'total'=>$audit_total);

    // Support session metadata follows the same member/date filters.
    $support_where = array('1=1');
    $support_params = array();
    if ($from_ts) { $support_where[] = 's.updated_at >= {int:support_from}'; $support_params['support_from'] = (int)$from_ts; }
    if ($to_ts) { $support_where[] = 's.updated_at <= {int:support_to}'; $support_params['support_to'] = (int)$to_ts; }
    if ($member_id > 0) {
        $support_where[] = '(s.requester_member = {int:support_member_id} OR s.assigned_staff = {int:support_member_id})';
        $support_params['support_member_id'] = $member_id;
    }
    elseif ($member !== '') {
        $support_where[] = '(requester.real_name LIKE {string:support_member} OR requester.member_name LIKE {string:support_member} OR staff.real_name LIKE {string:support_member} OR staff.member_name LIKE {string:support_member} OR s.guest_name LIKE {string:support_member})';
        $support_params['support_member'] = '%' . $member . '%';
    }
    $support = array();
    $request = $smcFunc['db_query']('', '
        SELECT s.*, requester.real_name AS requester_name, staff.real_name AS staff_name
        FROM {db_prefix}chat_support_sessions AS s
        LEFT JOIN {db_prefix}members AS requester ON (requester.id_member = s.requester_member)
        LEFT JOIN {db_prefix}members AS staff ON (staff.id_member = s.assigned_staff)
        WHERE ' . implode(' AND ', $support_where) . '
        ORDER BY s.updated_at DESC
        LIMIT 100', $support_params);
    while ($row = $smcFunc['db_fetch_assoc']($request)) $support[] = $row;
    $smcFunc['db_free_result']($request);
    $context['chat_log_support'] = $support;
}

function template_chat_logs()
{
    global $context, $scripturl;

    $filters = !empty($context['chat_logs_filters']) ? $context['chat_logs_filters'] : array('event'=>'','member'=>'','member_id'=>0,'date_from'=>'','date_to'=>'','scope'=>'all');
    $esc = function($value) { return htmlspecialchars((string)$value, ENT_QUOTES); };
    $member_link = function($id, $name) use ($scripturl, $esc) {
        $id = (int)$id;
        $label = $name !== '' ? $name : ($id > 0 ? 'Member #' . $id : 'System');
        if ($id <= 0)
            return $esc($label);
        return '<a href="' . $scripturl . '?action=profile;u=' . $id . '">' . $esc($label) . '</a> <a class="smalltext" title="View this member in Better Chat Logs" href="' . $scripturl . '?action=admin;area=better_chat;sa=logs;member_id=' . $id . '">[activity]</a>';
    };
    $audit_summary = function($row) {
        $actor = !empty($row['actor_name']) ? $row['actor_name'] : (!empty($row['id_actor']) ? 'Member #' . (int)$row['id_actor'] : 'System');
        $subject = !empty($row['subject_name']) ? $row['subject_name'] : (!empty($row['id_subject']) ? 'Member #' . (int)$row['id_subject'] : '');
        $related = !empty($row['related_name']) ? $row['related_name'] : (!empty($row['id_related']) ? 'Member #' . (int)$row['id_related'] : '');
        switch ((string)$row['event_type']) {
            case 'restriction_apply': return $actor . ' applied a Better Chat restriction to ' . ($subject !== '' ? $subject : 'a member') . '.';
            case 'restriction_revoke': return $actor . ' revoked a Better Chat restriction for ' . ($subject !== '' ? $subject : 'a member') . '.';
            case 'private_purge': return $actor . ' permanently purged a private conversation' . ($subject !== '' && $related !== '' ? ' between ' . $subject . ' and ' . $related : '') . '.';
            case 'group_history_clear': return $actor . ' permanently cleared Group Chat history.';
            case 'auto_prune': return 'Better Chat automatically applied the configured retention policy.';
            case 'all_history_clear': return $actor . ' permanently cleared Better Chat community/private history.';
            default: return ucfirst(str_replace('_', ' ', (string)$row['event_type'])) . '.';
        }
    };

    template_chat_admin_nav('logs');
    echo '<div class="cat_bar"><h3 class="catbg">Better Chat Logs</h3></div>';
    echo '<div class="information">Moderation, activity and retention controls for Better Chat. Message bodies are never displayed on this page.</div>';
    if (isset($_GET['purged'])) echo '<div class="infobox">Private conversation permanently purged. ', (int)$_GET['purged'], ' message(s) removed.</div>';
    if (isset($_GET['group_cleared'])) echo '<div class="infobox">Group Chat history permanently cleared. ', (int)$_GET['group_cleared'], ' message(s) removed.</div>';
    if (!empty($context['chat_logs_error'])) echo '<div class="errorbox">', $esc($context['chat_logs_error']), '</div>';

    if (!empty($context['chat_member_activity_summary'])) {
        $m = $context['chat_member_activity_summary'];
        echo '<div class="cat_bar"><h3 class="catbg">Member Activity: ', $esc($m['real_name'] ?: $m['member_name']), '</h3></div><div class="windowbg">';
        echo '<p><strong>', $member_link($m['id_member'], $m['real_name'] ?: $m['member_name']), '</strong> &nbsp; <span class="smalltext">@', $esc($m['member_name']), ' · Member #', (int)$m['id_member'], '</span></p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">';
        foreach (array('Group messages'=>$m['group_sent'],'Private sent'=>$m['private_sent'],'Private received'=>$m['private_received'],'Support sessions'=>$m['support_sessions'],'Audit actions'=>$m['audit_actions'],'Active restrictions'=>$m['active_restrictions']) as $label=>$value)
            echo '<div class="information" style="margin:0"><strong>', (int)$value, '</strong><br><span class="smalltext">', $esc($label), '</span></div>';
        echo '</div><p class="smalltext" style="margin-top:10px">Last Better Chat message activity: ', !empty($m['last_chat']) ? timeformat((int)$m['last_chat']) : 'None recorded', '.</p>';
        echo '<p><a class="button" href="', $scripturl, '?action=admin;area=better_chat;sa=logs">Clear member activity view</a></p></div>';
    }

    echo '<div class="cat_bar"><h3 class="catbg">Log Filters</h3></div><div class="windowbg">';
    echo '<form method="get" action="', $scripturl, '"><input type="hidden" name="action" value="admin"><input type="hidden" name="area" value="better_chat"><input type="hidden" name="sa" value="logs">';
    if (!empty($filters['member_id'])) echo '<input type="hidden" name="member_id" value="', (int)$filters['member_id'], '">';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end">';
    echo '<label><strong>Action type</strong><br><select name="event" style="width:100%"><option value="">All actions</option>';
    $event_labels = array('restriction_apply'=>'Restriction applied','restriction_revoke'=>'Restriction revoked','private_purge'=>'Private chat purged','group_history_clear'=>'Group history cleared','all_history_clear'=>'All history cleared','auto_prune'=>'Automatic retention cleanup');
    foreach ($event_labels as $value=>$label) echo '<option value="',$esc($value),'"',($filters['event']===$value?' selected':''),'>',$esc($label),'</option>';
    echo '</select></label>';
    echo '<label><strong>Activity scope</strong><br><select name="scope" style="width:100%"><option value="all"',($filters['scope']==='all'?' selected':''),'>Group + Private</option><option value="group"',($filters['scope']==='group'?' selected':''),'>Group only</option><option value="private"',($filters['scope']==='private'?' selected':''),'>Private only</option></select></label>';
    echo '<label><strong>Member / moderator</strong><br><input type="text" name="member" value="',$esc($filters['member']),'" placeholder="Name or username" style="width:100%"></label>';
    echo '<label><strong>From</strong><br><input type="date" name="date_from" value="',$esc($filters['date_from']),'" style="width:100%"></label>';
    echo '<label><strong>Through</strong><br><input type="date" name="date_to" value="',$esc($filters['date_to']),'" style="width:100%"></label>';
    echo '<div><button class="button" type="submit">Apply filters</button> <a class="button" href="',$scripturl,'?action=admin;area=better_chat;sa=logs">Reset</a></div></div></form></div>';

    echo '<div class="cat_bar"><h3 class="catbg">Group Chat Retention</h3></div>';
    $stats = !empty($context['chat_group_history_stats']) ? $context['chat_group_history_stats'] : array('total'=>0,'oldest'=>0,'newest'=>0);
    echo '<div class="windowbg"><p>This control clears <strong>Group Chat only</strong>. Private conversations, participant clear-state records, support sessions and audit logs are left alone.</p>';
    echo '<p><strong>Current Group Chat history:</strong> ',(int)$stats['total'],' message(s)';
    if (!empty($stats['oldest'])) echo ' &nbsp; <span class="smalltext">Oldest: ',timeformat((int)$stats['oldest']),'</span>';
    if (!empty($stats['newest'])) echo ' &nbsp; <span class="smalltext">Newest: ',timeformat((int)$stats['newest']),'</span>';
    echo '</p><form method="post" action="',$scripturl,'?action=admin;area=better_chat;sa=logs" id="better-chat-group-clear-form"><div class="righttext"><button class="button" type="submit" name="chat_clear_group" value="1"',empty($stats['total'])?' disabled':'','>Clear Group Chat History</button></div><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"></form></div>';

    echo '<div class="cat_bar"><h3 class="catbg">Private Chat Retention</h3></div>';
    echo '<div class="windowbg"><p><strong>Member clear:</strong> either participant can clear a private conversation from only their own view. That does not erase the other participant\'s history.</p>';
    echo '<p><strong>Administrator purge:</strong> permanently deletes the conversation for both participants. This action is recorded below and cannot be undone.</p>';
    echo '<form method="post" action="', $scripturl, '?action=admin;area=better_chat;sa=logs" id="better-chat-private-purge-form">';
    echo '<div style="display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr);gap:14px;max-width:900px">';
    foreach (array('a' => 'First member', 'b' => 'Second member') as $key => $label) {
        echo '<div><label for="better-chat-purge-', $key, '"><strong>', $label, '</strong></label><div style="position:relative;margin-top:5px"><input type="text" id="better-chat-purge-', $key, '" class="better-chat-purge-member" data-key="', $key, '" autocomplete="off" style="width:100%"><input type="hidden" id="better-chat-purge-', $key, '-id" name="member_', $key, '_id" value="0"><div id="better-chat-purge-', $key, '-suggestions" class="windowbg" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:9999;border:1px solid rgba(128,128,128,.35);box-shadow:0 6px 18px rgba(0,0,0,.14);max-height:240px;overflow:auto"></div></div></div>';
    }
    echo '</div><div class="righttext" style="margin-top:12px"><button class="button" type="submit" name="chat_purge_private" value="1">Permanently purge private chat</button></div>';
    echo '<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '"></form></div>';

    echo '<div class="cat_bar"><h3 class="catbg">Moderation / Administrative Actions</h3></div><div class="windowbg">';
    $audit = !empty($context['chat_audit_rows']) ? $context['chat_audit_rows'] : array();
    if (empty($audit)) echo '<p class="centertext">No Better Chat administrative actions match these filters.</p>';
    else {
        echo '<div class="table_grid"><table class="table_grid"><thead><tr class="title_bar"><th>Action</th><th>Actor</th><th>Member(s)</th><th>When</th><th>Details</th></tr></thead><tbody>';
        foreach ($audit as $row) {
            $subjects = array();
            if (!empty($row['id_subject'])) $subjects[] = $member_link((int)$row['id_subject'], (string)($row['subject_name'] ?? ''));
            if (!empty($row['id_related'])) $subjects[] = $member_link((int)$row['id_related'], (string)($row['related_name'] ?? ''));
            echo '<tr class="windowbg"><td><strong>',$esc($event_labels[$row['event_type']] ?? ucfirst(str_replace('_',' ',(string)$row['event_type']))),'</strong><br><span class="smalltext">',$esc($audit_summary($row)),'</span></td><td>',$member_link((int)$row['id_actor'], (string)($row['actor_name'] ?? '')),'</td><td>',!empty($subjects)?implode(' / ',$subjects):'—','</td><td>',timeformat((int)$row['created_at']),'</td><td>',$esc((string)$row['details']),'</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    $paging = !empty($context['chat_audit_paging']) ? $context['chat_audit_paging'] : array('page'=>1,'pages'=>1,'total'=>0);
    if ($paging['pages'] > 1) {
        $base = $scripturl . '?action=admin;area=better_chat;sa=logs;event=' . urlencode($filters['event']) . ';scope=' . urlencode($filters['scope']) . ';member=' . urlencode($filters['member']) . ';member_id=' . (int)($filters['member_id'] ?? 0) . ';date_from=' . urlencode($filters['date_from']) . ';date_to=' . urlencode($filters['date_to']);
        echo '<div class="pagesection" style="margin-top:12px"><span class="smalltext">', (int)$paging['total'], ' action(s) &nbsp; Page ',(int)$paging['page'],' of ',(int)$paging['pages'],'</span><div class="buttonlist">';
        if ($paging['page'] > 1) echo '<a class="button" href="',$base,';audit_page=',(int)$paging['page']-1,'">Previous</a>';
        if ($paging['page'] < $paging['pages']) echo '<a class="button" href="',$base,';audit_page=',(int)$paging['page']+1,'">Next</a>';
        echo '</div></div>';
    }
    echo '</div>';

    echo '<div class="cat_bar"><h3 class="catbg">Recent Community / Private Activity</h3></div><div class="windowbg">';
    $activity = !empty($context['chat_log_activity']) ? $context['chat_log_activity'] : array();
    if (empty($activity)) echo '<p class="centertext">No chat activity matches these filters.</p>';
    else {
        echo '<div class="table_grid"><table class="table_grid"><thead><tr class="title_bar"><th>Member</th><th>Activity</th><th>When</th></tr></thead><tbody>';
        foreach ($activity as $row) {
            $sender = $row['sender_name'] ?: ('Member #' . (int)$row['id_member_from']);
            if (empty($row['id_member_to']))
                $activity_text = 'Group Chat';
            else
                $activity_text = 'Private Chat to ' . $member_link((int)$row['id_member_to'], (string)($row['recipient_name'] ?: ('Member #' . (int)$row['id_member_to'])));
            echo '<tr class="windowbg"><td>',$member_link((int)$row['id_member_from'], (string)$sender),'</td><td>',empty($row['id_member_to'])?$esc($activity_text):$activity_text,'</td><td>',timeformat((int)$row['log_time']),'</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    echo '<div class="cat_bar"><h3 class="catbg">Support Session Log</h3></div><div class="windowbg">';
    $support = !empty($context['chat_log_support']) ? $context['chat_log_support'] : array();
    if (empty($support)) echo '<p class="centertext">No support sessions match these filters.</p>';
    else {
        echo '<div class="table_grid"><table class="table_grid"><thead><tr class="title_bar"><th>Session</th><th>Customer</th><th>Staff</th><th>Status</th><th>Opened</th><th>Last activity</th></tr></thead><tbody>';
        foreach ($support as $row) {
            $customer = !empty($row['requester_member']) ? $member_link((int)$row['requester_member'], (string)($row['requester_name'] ?: ('Member #' . (int)$row['requester_member']))) : $esc(!empty($row['guest_name']) ? $row['guest_name'] . ' (Guest)' : 'Guest');
            $staff = !empty($row['assigned_staff']) ? $member_link((int)$row['assigned_staff'], (string)($row['staff_name'] ?: ('Member #' . (int)$row['assigned_staff']))) : 'Unassigned';
            echo '<tr class="windowbg"><td>#',(int)$row['id_support'],'</td><td>',$customer,'</td><td>',$staff,'</td><td>',$esc(ucfirst((string)$row['status'])),'</td><td>',timeformat((int)$row['created_at']),'</td><td>',timeformat((int)$row['updated_at']),'</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    $search_url = $scripturl . '?action=admin;area=better_chat;sa=member_search;term=';
    echo '<script>(function(){var url=',json_encode($search_url),';function wire(key){var input=document.getElementById("better-chat-purge-"+key),hidden=document.getElementById("better-chat-purge-"+key+"-id"),box=document.getElementById("better-chat-purge-"+key+"-suggestions");if(!input||!hidden||!box)return;var timer=null,controller=null;function choose(m){hidden.value=m.id;input.value=m.name;box.style.display="none";box.innerHTML="";}input.addEventListener("input",function(){hidden.value="0";clearTimeout(timer);var term=this.value.trim();if(term.length<2){box.style.display="none";return;}timer=setTimeout(function(){if(controller)controller.abort();controller=new AbortController();fetch(url+encodeURIComponent(term),{credentials:"same-origin",signal:controller.signal}).then(function(r){return r.json();}).then(function(data){box.innerHTML="";var rows=(data&&data.members)||[];rows.forEach(function(m){var b=document.createElement("button");b.type="button";b.className="button";b.style.cssText="display:block;width:100%;text-align:left;border-radius:0;margin:0";b.textContent=m.name+(m.username&&m.username!==m.name?" (@"+m.username+")":"")+"  #"+m.id;b.addEventListener("click",function(){choose(m);});box.appendChild(b);});if(!rows.length)box.innerHTML="<div class=\\"smalltext\\" style=\\"padding:9px 10px\\">No matching members.</div>";box.style.display="block";}).catch(function(e){if(e.name!=="AbortError")box.style.display="none";});},180);});document.addEventListener("click",function(e){if(e.target!==input&&!box.contains(e.target))box.style.display="none";});}wire("a");wire("b");var privateForm=document.getElementById("better-chat-private-purge-form");if(privateForm)privateForm.addEventListener("submit",function(e){var a=document.getElementById("better-chat-purge-a-id").value,b=document.getElementById("better-chat-purge-b-id").value;if(!a||a==="0"||!b||b==="0"||a===b){e.preventDefault();alert("Choose two different members from the suggestions.");return;}if(!confirm("Permanently delete this private conversation for BOTH members? This cannot be undone."))e.preventDefault();});var groupForm=document.getElementById("better-chat-group-clear-form");if(groupForm)groupForm.addEventListener("submit",function(e){if(!confirm("Permanently clear GROUP CHAT history only? Private chats, support sessions and audit logs will be kept."))e.preventDefault();});})();</script>';
}

/**
 * Dedicated full-page Community / Group Chat.
 *
 * This intentionally reuses the existing chat_msg endpoint and BetterChat.js
 * client so messages, uploads, presence, reactions, restrictions, sound and
 * retention all stay on the same proven backend as the floating window.
 */
function better_chat_full_page()
{
    global $context, $modSettings, $scripturl, $user_info;

    if (empty($modSettings['chat_enabled']))
        fatal_error('Better Chat is currently disabled.', false);

    $chat_mode = better_chat_support_mode();
    if (!in_array($chat_mode, array('community', 'hybrid'), true))
        fatal_error('Community Chat is not enabled.', false);

    if (!empty($user_info['is_guest'])) {
        redirectexit('action=login;return_to=' . urlencode($scripturl . '?action=chat_full'));
        return;
    }

    // Keep retention maintenance behavior consistent with an ordinary rendered
    // forum page even though chat_load_assets intentionally skips this action.
    better_chat_auto_prune_retention(false);

    $member_id = !empty($user_info['id']) ? (int) $user_info['id'] : 0;
    $full_restriction = $member_id > 0 ? better_chat_moderation_is_restricted($member_id, 'all') : false;

    $context['page_title'] = !empty($modSettings['chat_bar_name'])
        ? $modSettings['chat_bar_name']
        : 'Forum Chat';

    if (function_exists('loadCSSFile'))
        loadCSSFile('BetterChat.css', array('default_theme' => true, 'force_current' => false, 'order_pos' => 9999, 'force_reload' => true), 'better_chat_full');

    // test34b: Let SMF own the normal page lifecycle. Calling template_header()
    // and template_footer() here caused the page to render once manually and
    // then a second time when index.php called obExit(), which produced
    // "Unable to load the 'main' template." on the standalone chat action.
    // test34c: the full-page sub-template lives in this source file so it is
    // available regardless of custom-theme template paths or package-manager
    // file-copy state. Let SMF render it through the normal page lifecycle.
    $context['sub_template'] = 'better_chat_full';

    $context['better_chat_full'] = array(
        'member_id' => $member_id,
        'initial_private' => !empty($_REQUEST['private']) ? (int) $_REQUEST['private'] : 0,
        'initial_private_name' => !empty($_REQUEST['private_name']) ? trim((string) $_REQUEST['private_name']) : '',
        'initial_group' => !empty($_REQUEST['conversation']) ? (int) $_REQUEST['conversation'] : 0,
        'initial_group_name' => !empty($_REQUEST['conversation_name']) ? trim((string) $_REQUEST['conversation_name']) : '',
        'restriction' => $full_restriction ? better_chat_moderation_restriction_payload($full_restriction, 'Better Chat') : false,
        'video_enabled' => false,
        'upload_accept' => 'image/jpeg,image/png,image/gif,image/webp',
        'reactions' => better_chat_reaction_registry(),
    );

    if (!$full_restriction) {
        $ffmpeg_path = better_chat_find_ffmpeg();
        $video_enabled = !empty($modSettings['chat_video_enabled']) && $ffmpeg_path !== '';
        $context['better_chat_full']['video_enabled'] = $video_enabled;
        $context['better_chat_full']['upload_accept'] = $video_enabled
            ? 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm'
            : 'image/jpeg,image/png,image/gif,image/webp';
    }
}


/**
 * Full-page chat sub-template.
 *
 * Kept in ChatHandler.php deliberately so custom themes cannot mask it and
 * upgrades do not depend on a separate theme template file being copied.
 */
function template_better_chat_full()
{
    global $context, $modSettings, $scripturl, $boardurl;

    $full = !empty($context['better_chat_full']) ? $context['better_chat_full'] : array();
    $title = !empty($context['page_title']) ? $context['page_title'] : 'Forum Chat';

    echo '<div class="better-chat-full-page-shell">';

    if (!empty($full['restriction'])) {
        $payload = $full['restriction'];
        echo '<div class="cat_bar"><h3 class="catbg">', htmlspecialchars($title, ENT_QUOTES), '</h3></div>',
            '<div class="windowbg better-chat-full-page-restricted">',
                '<div class="better-chat-restriction-title">🔒 Better Chat access suspended</div>',
                '<div class="better-chat-restriction-details"><strong>Reason:</strong> ', htmlspecialchars($payload['reason'], ENT_QUOTES), '</div>',
                '<div class="better-chat-restriction-details"><strong>Duration:</strong> ', htmlspecialchars($payload['duration_text'], ENT_QUOTES), '</div>',
                '<div class="better-chat-restriction-details"><strong>Expires:</strong> ', htmlspecialchars($payload['expires_text'], ENT_QUOTES), '</div>',
            '</div>';
        echo '</div>';
        return;
    }

    $member_id = !empty($full['member_id']) ? (int) $full['member_id'] : 0;
    $video_enabled = !empty($full['video_enabled']);
    $upload_accept = !empty($full['upload_accept']) ? $full['upload_accept'] : 'image/jpeg,image/png,image/gif,image/webp';
    $reaction_registry = !empty($full['reactions']) ? $full['reactions'] : array();
    $initial_private = !empty($full['initial_private']) ? (int) $full['initial_private'] : 0;
    $initial_private_name = !empty($full['initial_private_name']) ? (string) $full['initial_private_name'] : '';
    $initial_group = !empty($full['initial_group']) ? (int) $full['initial_group'] : 0;
    $initial_group_name = !empty($full['initial_group_name']) ? (string) $full['initial_group_name'] : '';

    echo '
    <div id="smf-chat-bar" class="better-chat better-chat-full-page" aria-busy="true" style="visibility:hidden;opacity:0;pointer-events:none">
        <div id="chat-header" class="better-chat-header chat_header">
            <span><button type="button" id="mobile-user-toggle" class="better-chat-icon-button" aria-label="Show online users">☰</button> ',
                htmlspecialchars($title, ENT_QUOTES),
                '<span id="chat-online-count"></span></span>
            <span class="better-chat-header-actions">
                <button type="button" class="better-chat-sound-toggle" aria-label="Toggle chat notification sound" title="Notification sound">🔊</button>
                <a class="better-chat-popout better-chat-popout-return" href="', $scripturl, '" aria-label="Return to forum" title="Return to forum">×</a>
            </span>
        </div>
        <div id="better-chat-hub-tabs" class="better-chat-hub-tabs" role="tablist" aria-label="Chat conversations">
            <div id="better-chat-hub-tab-list" class="better-chat-hub-tab-list">
                <button type="button" class="better-chat-hub-tab is-active" data-hub-kind="public" data-hub-id="0" role="tab" aria-selected="true">
                    <span class="better-chat-hub-tab-label">Public Chat</span><span class="better-chat-hub-tab-badge" hidden>0</span>
                </button>
            </div>
            <div class="better-chat-hub-more-wrap" hidden>
                <button type="button" class="better-chat-hub-more" aria-haspopup="true" aria-expanded="false">More ▾ <span class="better-chat-hub-more-badge" hidden>0</span></button>
                <div class="better-chat-hub-more-menu" hidden></div>
            </div>
        </div>
        <div id="chat-content">
            <div id="chat-restriction-screen" class="better-chat-group-restriction-screen" role="status" aria-live="polite" hidden>
                <div id="chat-restriction" class="better-chat-restriction-notice better-chat-group-restriction"></div>
            </div>
            <div id="chat-main-row">
                <div id="chat-body"></div>
                <aside id="chat-user-list">
                    <strong>Online</strong>
                    <ul id="online-users"></ul>
                </aside>
            </div>
            <div id="chat-footer">
                <div class="better-chat-compose-wrap">
                    <div class="better-chat-compose">
                        <div class="better-chat-input-shell">
                            <input type="text" id="chat-input" placeholder="Type a message..." autocomplete="off">
                            <div class="better-chat-input-actions">
                                <button type="button" class="better-chat-emoji-toggle" data-emoji-input="chat-input" aria-label="Choose emoji" title="Emoji">😀</button>
                                ', (!empty($modSettings['chat_giphy_enabled']) && !empty($modSettings['chat_giphy_api_key']) ? '<button type="button" class="better-chat-giphy-toggle" data-giphy-input="chat-input" aria-label="Choose GIF" title="GIF">GIF</button>' : ''), '
                                <button type="button" class="better-chat-attach" data-file-target="chat-file-upload" aria-label="Attach image" title="Attach image">📎</button>
                            </div>
                        </div>
                        <input type="file" id="chat-file-upload" accept="', htmlspecialchars($upload_accept, ENT_QUOTES), '" hidden>
                    </div>
                    <div class="better-chat-upload-progress" aria-live="polite" hidden>
                        <div class="better-chat-upload-track"><span></span></div>
                        <div class="better-chat-upload-label"></div>
                    </div>
                </div>
            </div>
        </div>
        <div id="better-chat-hub-private-stage" class="better-chat-hub-private-stage" hidden></div>
    </div>
    </div>
    <script>
        var chat_script_url = ', json_encode($scripturl), ';
        var chat_board_url = ', json_encode($boardurl), ';
        var chat_width = 1000;
        var smf_member_id = ', $member_id, ';
        var better_chat_full_page = true;
        var better_chat_full_initial_private = ', $initial_private, ';
        var better_chat_full_initial_private_name = ', json_encode($initial_private_name), ';
        var better_chat_full_initial_group = ', $initial_group, ';
        var better_chat_full_initial_group_name = ', json_encode($initial_group_name), ';
        var better_chat_notify_sound = ', !empty($modSettings['chat_notify_sound']) ? 'true' : 'false', ';
        var better_chat_allow_member_sound = ', !empty($modSettings['chat_allow_member_sound']) ? 'true' : 'false', ';
        var better_chat_notify_title = ', !empty($modSettings['chat_notify_title']) ? 'true' : 'false', ';
        var better_chat_notify_title_text = ', json_encode(!empty($modSettings['chat_notify_title_text']) ? $modSettings['chat_notify_title_text'] : '{username} has sent you a new message...'), ';
        var better_chat_private_minimize_style = ', json_encode(!empty($modSettings['chat_private_minimize_style']) ? $modSettings['chat_private_minimize_style'] : 'bubbles'), ';
        var better_chat_video_enabled = ', $video_enabled ? 'true' : 'false', ';
        var better_chat_giphy_enabled = ', (!empty($modSettings['chat_giphy_enabled']) && !empty($modSettings['chat_giphy_api_key'])) ? 'true' : 'false', ';
        var better_chat_giphy_api_key = ', json_encode(!empty($modSettings['chat_giphy_api_key']) ? trim((string) $modSettings['chat_giphy_api_key']) : ''), ';
        var better_chat_reactions = ', json_encode($reaction_registry), ';
        var better_chat_session_var = ', json_encode(!empty($context['session_var']) ? $context['session_var'] : ''), ';
        var better_chat_session_id = ', json_encode(!empty($context['session_id']) ? $context['session_id'] : ''), ';
    </script>
    <script src="', $boardurl, '/scripts/BetterChat.js?v=1.0.0-test38"></script>';
}


function chat_register_action(&$actionArray)
{
    $actionArray['chat_msg'] = array('ChatHandler.php', 'ChatProcess');
    $actionArray['chat_full'] = array('ChatHandler.php', 'better_chat_full_page');
}

function chat_admin_page()
{
    $sa = isset($_REQUEST['sa']) ? (string) $_REQUEST['sa'] : 'settings';

    if ($sa === 'member_search')
        return better_chat_admin_member_search();

    if ($sa === 'moderation')
        return chat_admin_moderation_page();

    if ($sa === 'logs')
        return chat_admin_logs_page();

    return chat_admin_settings_page();
}

function chat_admin_areas(&$admin_areas)
{
    global $txt;

    loadLanguage('SmfCustomChat');

    $admin_areas['config']['areas']['better_chat'] = array(
        'label' => !empty($txt['chat_admin_parent']) ? $txt['chat_admin_parent'] : 'Better Chat',
        'function' => 'chat_admin_page',
        'icon' => 'better_chat.png',
        'permission' => array('admin_forum'),
        // Better Chat renders its own Settings / Moderation / Logs navigation.
        // Do not also register these as SMF native subsections: some custom
        // themes assume native subsection tab_data exists and will emit
        // GenericMenu warnings when a mod routes its own subactions.
    );
}
?>