<?php
/**
 * uninstall_hooks.php - Always-run Better Chat hook cleanup.
 *
 * This intentionally contains no destructive data cleanup. SMF executes this
 * on every uninstall so preserved-data uninstalls cannot leave stale hooks.
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
    require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
    die('Hacking attempt...');

global $sourcedir;

if (function_exists('remove_integration_function')) {
    $hook_map = array(
        'integrate_load_theme' => 'chat_load_assets',
		'integrate_login' => 'chat_login_bootstrap_marker',
        'integrate_actions' => 'chat_register_action',
        'integrate_admin_areas' => 'chat_admin_areas',
    );

    foreach ($hook_map as $hook => $function) {
        foreach (array(
            $function,
            'ChatHandler.php|' . $function,
            $sourcedir . '/ChatHandler.php|' . $function,
        ) as $legacy_function)
            @remove_integration_function($hook, $legacy_function);

        @remove_integration_function($hook, $function, true, $sourcedir . '/ChatHandler.php');
    }
}

if (function_exists('updateSettings'))
    updateSettings(array('betterstorage_integration_chat' => 0));

// Remove legacy root-level helper copies from older Better Chat test upgrades.
// Signature checks prevent deleting unrelated files that happen to use the same
// generic filenames.
global $boarddir;
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
