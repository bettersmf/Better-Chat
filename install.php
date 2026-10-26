<?php
/**
 * install.php - Chat Mod Database Setup
 * Final Stable Build v1.0.1
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('Hacking attempt...');

if (!isset($smcFunc['db_create_table']))
	db_extend('packages');

global $smcFunc, $boarddir, $db_prefix;

$tables = array(
	array(
		'name' => $db_prefix . 'chat_messages',
		'columns' => array(
			array('name' => 'id_chat', 'type' => 'int', 'size' => 11, 'auto' => true),
			array('name' => 'id_member_from', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'id_member_to', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'body', 'type' => 'text'),
			array('name' => 'log_time', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'is_read', 'type' => 'tinyint', 'size' => 1, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_chat')),
		)
	),
	array(
		'name' => $db_prefix . 'chat_typing',
		'columns' => array(
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'last_typing', 'type' => 'int', 'size' => 10, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_member')),
		)
	),
	array(
		'name' => $db_prefix . 'chat_presence',
		'columns' => array(
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'last_seen', 'type' => 'int', 'size' => 10, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_member')),
			array('type' => 'index', 'columns' => array('last_seen')),
		)
	),
	array(
		'name' => $db_prefix . 'chat_support_staff',
		'columns' => array(
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'on_duty', 'type' => 'tinyint', 'size' => 1, 'default' => 0),
			array('name' => 'updated_at', 'type' => 'int', 'size' => 10, 'default' => 0),
		),
		'indexes' => array(array('type' => 'primary', 'columns' => array('id_member')))
	),
	array(
		'name' => $db_prefix . 'chat_support_sessions',
		'columns' => array(
			array('name' => 'id_support', 'type' => 'int', 'size' => 11, 'auto' => true),
			array('name' => 'requester_member', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'guest_token', 'type' => 'varchar', 'size' => 64, 'default' => ''),
			array('name' => 'guest_name', 'type' => 'varchar', 'size' => 80, 'default' => ''),
			array('name' => 'assigned_staff', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'status', 'type' => 'varchar', 'size' => 16, 'default' => 'waiting'),
			array('name' => 'created_at', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'updated_at', 'type' => 'int', 'size' => 10, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_support')),
			array('type' => 'index', 'columns' => array('status', 'updated_at')),
			array('type' => 'index', 'columns' => array('requester_member')),
			array('type' => 'index', 'columns' => array('assigned_staff')),
		)
	),
	array(
		'name' => $db_prefix . 'chat_restrictions',
		'columns' => array(
			array('name' => 'id_restriction', 'type' => 'int', 'size' => 11, 'auto' => true),
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'restriction_type', 'type' => 'varchar', 'size' => 16, 'default' => 'all'),
			array('name' => 'reason', 'type' => 'text'),
			array('name' => 'id_moderator', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'created_at', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'expires_at', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'revoked_at', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'id_revoked_by', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_restriction')),
			array('type' => 'index', 'columns' => array('id_member', 'restriction_type')),
			array('type' => 'index', 'columns' => array('expires_at', 'revoked_at')),
		)
	),
	array(
		'name' => $db_prefix . 'chat_private_state',
		'columns' => array(
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'other_member', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'cleared_through_id', 'type' => 'int', 'size' => 11, 'default' => 0),
			array('name' => 'updated_at', 'type' => 'int', 'size' => 10, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_member', 'other_member')),
			array('type' => 'index', 'columns' => array('other_member')),
		)
	),
	// test35a: conversation/participant foundation for future private group chats.
	// Existing one-to-one chat_messages remain untouched and are linked lazily
	// through chat_conversation_messages so upgrades do not rewrite history.
	array(
		'name' => $db_prefix . 'chat_conversations',
		'columns' => array(
			array('name' => 'id_conversation', 'type' => 'int', 'size' => 11, 'auto' => true),
			array('name' => 'conversation_type', 'type' => 'varchar', 'size' => 12, 'default' => 'direct'),
			array('name' => 'direct_key', 'type' => 'varchar', 'size' => 32, 'default' => ''),
			array('name' => 'title', 'type' => 'varchar', 'size' => 120, 'default' => ''),
			array('name' => 'id_owner', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'created_at', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'updated_at', 'type' => 'int', 'size' => 10, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_conversation')),
			array('type' => 'unique', 'columns' => array('direct_key')),
			array('type' => 'index', 'columns' => array('conversation_type', 'updated_at')),
		)
	),
	array(
		'name' => $db_prefix . 'chat_conversation_members',
		'columns' => array(
			array('name' => 'id_conversation', 'type' => 'int', 'size' => 11, 'default' => 0),
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'member_role', 'type' => 'varchar', 'size' => 12, 'default' => 'member'),
			array('name' => 'joined_at', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'left_at', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'cleared_through_id', 'type' => 'int', 'size' => 11, 'default' => 0),
			array('name' => 'last_read_id', 'type' => 'int', 'size' => 11, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_conversation', 'id_member')),
			array('type' => 'index', 'columns' => array('id_member', 'left_at')),
		)
	),
	array(
		'name' => $db_prefix . 'chat_conversation_messages',
		'columns' => array(
			array('name' => 'id_chat', 'type' => 'int', 'size' => 11, 'default' => 0),
			array('name' => 'id_conversation', 'type' => 'int', 'size' => 11, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_chat')),
			array('type' => 'index', 'columns' => array('id_conversation', 'id_chat')),
		)
	),
	array(
		'name' => $db_prefix . 'chat_audit_log',
		'columns' => array(
			array('name' => 'id_log', 'type' => 'int', 'size' => 11, 'auto' => true),
			array('name' => 'event_type', 'type' => 'varchar', 'size' => 32, 'default' => ''),
			array('name' => 'id_actor', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'id_subject', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'id_related', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'details', 'type' => 'text'),
			array('name' => 'created_at', 'type' => 'int', 'size' => 10, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_log')),
			array('type' => 'index', 'columns' => array('event_type', 'created_at')),
			array('type' => 'index', 'columns' => array('id_subject', 'created_at')),
		)
	),
	array(
		'name' => $db_prefix . 'chat_support_messages',
		'columns' => array(
			array('name' => 'id_message', 'type' => 'int', 'size' => 11, 'auto' => true),
			array('name' => 'id_support', 'type' => 'int', 'size' => 11, 'default' => 0),
			array('name' => 'sender_member', 'type' => 'mediumint', 'size' => 8, 'default' => 0),
			array('name' => 'sender_role', 'type' => 'varchar', 'size' => 12, 'default' => 'customer'),
			array('name' => 'body', 'type' => 'text'),
			array('name' => 'log_time', 'type' => 'int', 'size' => 10, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_message')),
			array('type' => 'index', 'columns' => array('id_support', 'id_message')),
		)
	)

);

foreach ($tables as $table)
	$smcFunc['db_create_table']($table['name'], $table['columns'], $table['indexes'], array(), 'update');

// test33c: migrate the legacy one-size-fits-all Community/Private retention
// value into the new granular policies without changing existing behavior.
$legacy_chat_purge_time = 168;
$request = $smcFunc['db_query']('', '
    SELECT value
    FROM {db_prefix}settings
    WHERE variable = {string:legacy_setting}
    LIMIT 1',
    array('legacy_setting' => 'chat_purge_time')
);
if ($row = $smcFunc['db_fetch_assoc']($request))
    $legacy_chat_purge_time = (int) $row['value'];
$smcFunc['db_free_result']($request);

$better_chat_defaults = array(
    'chat_enabled' => 1,
    'chat_notify_sound' => 1,
    'chat_allow_member_sound' => 1,
    'chat_notify_title' => 1,
    'chat_notify_title_text' => '{username} has sent you a new message...',
    'chat_bar_name' => 'Forum Chat',
    'chat_width' => 500,
    'chat_private_minimize_style' => 'bubbles',
    'chat_mode' => 'community',
    'chat_support_title' => 'Support',
    'chat_support_allow_members' => 1,
    'chat_support_allow_guests' => 0,
    'chat_support_staff_groups' => '1',
    'chat_support_allow_images' => 1,
    'chat_purge_time' => 168, // legacy value retained for backward compatibility
    'chat_retention_group_hours' => $legacy_chat_purge_time,
    'chat_retention_private_hours' => $legacy_chat_purge_time,
    'chat_retention_support_hours' => 0,
    'chat_retention_audit_hours' => 0,
    'chat_retention_last_run' => 0,
    'chat_max_filesize' => 5120,
    'chat_allowed_extensions' => 'jpg,jpeg,png,gif,webp',
    'chat_giphy_enabled' => 0,
    'chat_giphy_api_key' => '',
);

// Never overwrite preserved Better Chat settings during an upgrade or reinstall.
// Only seed defaults that do not already exist in the settings table.
$existing_chat_settings = array();
$request = $smcFunc['db_query']('', '
    SELECT variable
    FROM {db_prefix}settings
    WHERE variable IN ({array_string:variables})',
    array('variables' => array_keys($better_chat_defaults))
);
while ($row = $smcFunc['db_fetch_assoc']($request))
    $existing_chat_settings[(string) $row['variable']] = true;
$smcFunc['db_free_result']($request);

foreach (array_keys($existing_chat_settings) as $variable)
    unset($better_chat_defaults[$variable]);

if (!empty($better_chat_defaults))
    updateSettings($better_chat_defaults);

$upload_dir = $boarddir . '/chat_uploads';
if (!file_exists($upload_dir)) {
	if (mkdir($upload_dir, 0755, true)) {
		file_put_contents($upload_dir . '/index.php', '<?php // Silence is golden ?>');
		chmod($upload_dir, 0755);
	}
}


// Normalize Better Chat integration hooks.
// Older Custom Chat packages used the same ChatHandler.php functions, which can
// leave duplicate integrate_* entries behind when Better Chat is installed over them.
// Remove known legacy forms, then register exactly one current hook for each feature.
if (function_exists('remove_integration_function') && function_exists('add_integration_function')) {
	global $sourcedir;

	$hook_map = array(
		'integrate_load_theme' => 'chat_load_assets',
		'integrate_login' => 'chat_login_bootstrap_marker',
		'integrate_actions' => 'chat_register_action',
		'integrate_admin_areas' => 'chat_admin_areas',
	);

	foreach ($hook_map as $hook => $function) {
		$legacy_forms = array(
			$function,
			'ChatHandler.php|' . $function,
			$sourcedir . '/ChatHandler.php|' . $function,
		);

		foreach ($legacy_forms as $legacy_function)
			@remove_integration_function($hook, $legacy_function);

		@remove_integration_function($hook, $function, true, $sourcedir . '/ChatHandler.php');
		@add_integration_function($hook, $function, true, $sourcedir . '/ChatHandler.php');
	}
}

// test38a: advertise Better Chat as a live Better Storage integration.
// Match the proven Better Downloads handshake: load the API and only mark
// connected when Better Storage's source API is actually available.
if (is_file($sourcedir . '/BetterStorage.php'))
{
    require_once($sourcedir . '/BetterStorage.php');
    if (function_exists('betterstorage_source_config'))
        updateSettings(array('betterstorage_integration_chat' => 1));
}

// Stable-release cleanup: a few historical test upgrades copied these helper
// scripts into the forum root. Remove only files that clearly belong to
// Better Chat so unrelated files with generic names are never touched.
foreach (array(
    $boarddir . '/uninstall_hooks.php' => 'Always-run Better Chat hook cleanup',
    $boarddir . '/uninstall.php' => 'Chat Mod Database Cleanup',
) as $legacy_file => $signature) {
    if (!is_file($legacy_file))
        continue;

    $legacy_contents = @file_get_contents($legacy_file);
    if ($legacy_contents !== false && strpos($legacy_contents, $signature) !== false)
        @unlink($legacy_file);
}

?>
