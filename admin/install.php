<?php
/**
 * Installation routine for the Membership plugin for GLFusion
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/**
 *   Include required glFusion common functions
 */
require_once '../../../lib-common.php';
require_once $_CONF['path'].'/plugins/membership/autoinstall.php';
USES_lib_install();

// Only let Root users access this page
if (!SEC_inGroup('Root')) {
    // Someone is trying to illegally access this page
    glFusion\Log\Log::write('system', Log::ERROR, "Someone has tried to illegally access the membership install/uninstall page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR");
    $display = COM_siteHeader();
    $display .= COM_startBlock($LANG_MEMBERSHIP['access_denied']);
    $display .= $LANG_MEMBERSHIP['access_denied_msg'];
    $display .= COM_endBlock();
    $display .= COM_siteFooter(true);
    echo $display;
    exit;
}

/**
 * @global string $base_path
 */
$base_path  = "{$_CONF['path']}plugins/membership";

/**
 * Include required plugin common functions.
 */
require_once("$base_path/functions.inc");

/**
 * Main Function
 */
if (SEC_checkToken()) {
    if ($_GET['action'] == 'install') {
        if (plugin_install_membership()) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=44');
            exit;
        } else {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=72');
            exit;
        }
    } else if ($_GET['action'] == "uninstall") {
        USES_lib_plugin();
        if (PLG_uninstall('membership')) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=45');
            exit;
        } else {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=73');
            exit;
        }
    }
}

echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php');

?>
