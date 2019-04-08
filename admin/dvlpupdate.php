<?php
/**
 * Apply updates to the Membership plugin during development.
 * Calls upgrade function with "ignore_errors" set so repeated SQL statements
 * won't cause functions to abort.
 *
 * Only updates from the previous released version.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.2.0
 * @since       v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

require_once '../../../lib-common.php';
if (!SEC_inGroup('Root')) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to access the Membership Development Code Upgrade Routine without proper permissions.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: " . $_SERVER['REMOTE_ADDR'],1);
    COM_404();
    exit;
}
if (function_exists('CACHE_clear')) {
    CACHE_clear();
}
\Membership\Cache::clear();

// Force the plugin version to the previous version and do the upgrade
$_PLUGIN_INFO['membership']['pi_version'] = '0.1.3';
plugin_upgrade_membership(true);

// need to clear the template cache so do it here
if (function_exists('CACHE_clear')) {
    CACHE_clear();
}
\Membership\Cache::clear();
header('Location: '.$_CONF['site_admin_url'].'/plugins.php?msg=600');
exit;

?>
