<?php
/**
 * uninstall.php - Chat Mod Database Cleanup
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('Hacking attempt...');

if (!isset($smcFunc['db_drop_table']))
	db_extend('packages');

global $smcFunc, $db_prefix, $sourcedir, $boarddir;

// Before destructive uninstall removes chat_messages, delete Better Reactions
// rows for chat_message objects while the message IDs are still available.
if (!empty($sourcedir) && is_file($sourcedir . '/ChatHandler.php')) {
	require_once($sourcedir . '/ChatHandler.php');

	if (function_exists('better_chat_delete_reactions_for_messages')) {
		$request = $smcFunc['db_query']('', 'SELECT id_chat FROM {db_prefix}chat_messages', array());
		$message_ids = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$message_ids[] = (int) $row['id_chat'];
		$smcFunc['db_free_result']($request);

		better_chat_delete_reactions_for_messages($message_ids);
	}
}

$tables = array(
	$db_prefix . 'chat_messages',
	$db_prefix . 'chat_typing',
	$db_prefix . 'chat_presence',
	$db_prefix . 'chat_restrictions',
	$db_prefix . 'chat_private_state',
	$db_prefix . 'chat_conversation_messages',
	$db_prefix . 'chat_conversation_members',
	$db_prefix . 'chat_conversations',
	$db_prefix . 'chat_audit_log',
	$db_prefix . 'chat_support_messages',
	$db_prefix . 'chat_support_sessions',
	$db_prefix . 'chat_support_staff',
);

foreach ($tables as $table)
	$smcFunc['db_drop_table']($table, true); // true = ignore if table doesn't exist

// Remove settings added
$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable IN ({array_string:settings})',
	array(
		'settings' => array(
            'chat_enabled',
            'chat_notify_sound',
            'chat_allow_member_sound',
            'chat_notify_title',
            'chat_notify_title_text',
            'chat_bar_name',
            'chat_width',
            'chat_private_minimize_style',
            'chat_mode',
            'chat_support_title',
            'chat_support_allow_members',
            'chat_support_allow_guests',
            'chat_support_staff_groups',
            'chat_support_allow_images',
            'chat_purge_time',
            'chat_retention_group_hours',
            'chat_retention_private_hours',
            'chat_retention_support_hours',
            'chat_retention_audit_hours',
            'chat_retention_last_run',
            'chat_max_filesize',
            'chat_allowed_extensions',
            'chat_giphy_enabled',
            'chat_giphy_api_key',
            'chat_video_enabled',
            'chat_max_video_filesize',
            'chat_allowed_video_extensions',
            'chat_ffmpeg_path',
        ),
    )
);

// Nuke the uploads folder too - if you want to keep the uploads folder comment this out
$upload_dir = $boarddir . '/chat_uploads';
if (file_exists($upload_dir)) {
	$files = glob($upload_dir . '/*');
	foreach ($files as $file) {
		if (is_file($file))
			unlink($file);
	}
	rmdir($upload_dir);
}


?>
