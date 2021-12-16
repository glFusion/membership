<?php
/**
 * Provides automatic installation of the Membership plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2021 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.3.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** @global string $_DB_dbms */
global $_DB_dbms;

/** Include plugin functions */
require_once __DIR__ . '/functions.inc';

/** Include database definitions */
require_once __DIR__ . '/sql/'. $_DB_dbms. '_install.php';

use Membership\Config;

/** Plugin installation options
 * @global array $INSTALL_plugin['membership']
 */
$INSTALL_plugin['membership'] = array(
    'installer'     => array(
        'type'      => 'installer',
        'version'   => '1',
        'mode'      => 'install',
    ),

    'plugin' => array(
        'type'      => 'plugin',
        'name'      => Config::PI_NAME,
        'ver'       => Config::get('pi_version'),
        'gl_ver'    => Config::get('gl_version'),
        'url'       => Config::get('pi_url'),
        'display'   => Config::get('pi_display_name'),
    ),

    array(
        'type'  => 'table',
        'table' => $_TABLES['membership_members'],
        'sql'   => $_SQL['membership_members'],
    ),

    array(
        'type'  => 'table',
        'table' => $_TABLES['membership_plans'],
        'sql'   => $_SQL['membership_plans'],
    ),

    array(
        'type'  => 'table',
        'table' => $_TABLES['membership_links'],
        'sql'   => $_SQL['membership_links'],
    ),

    array(
        'type'  => 'table',
        'table' => $_TABLES['membership_trans'],
        'sql'   => $_SQL['membership_trans'],
    ),

    array(
        'type'  => 'table',
        'table' => $_TABLES['membership_positions'],
        'sql'   => $_SQL['membership_positions'],
    ),

    array(
        'type'  => 'table',
        'table' => $_TABLES['membership_posgroups'],
        'sql'   => $_SQL['membership_posgroups'],
    ),

    array(
        'type'  => 'table',
        'table' => $_TABLES['membership_log'],
        'sql'   => $_SQL['membership_log'],
    ),

    array(
        'type'  => 'table',
        'table' => $_TABLES['membership_users'],
        'sql'   => $_SQL['membership_users'],
    ),

    array(
        'type'      => 'feature',
        'feature'   => 'membership.admin',
        'desc'      => 'Membership Administration access',
        'variable'  => 'admin_feature_id',
    ),

    array(
        'type'      => 'feature',
        'feature'   => 'membership.manage',
        'desc'      => 'Membership Management access',
        'variable'  => 'manage_feature_id',
    ),

    array(
        'type'      => 'mapping',
        'findgroup' => 'Root',
        'feature'   => 'admin_feature_id',
        'log'       => 'Adding Admin feature to the admin group',
    ),

    array(
        'type'      => 'mapping',
        'findgroup' => 'Root',
        'feature'   => 'manage_feature_id',
        'log'       => 'Adding Manager feature to the admin group',
    ),
);

/**
* Puts the datastructures for this plugin into the glFusion database.
* Note: Corresponding uninstall routine is in functions.inc.
*
* @return   boolean True if successful False otherwise
*/
function plugin_install_membership()
{
    global $INSTALL_plugin;

    COM_errorLog("Attempting to install the " . Config::get('pi_display_name') . " plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[Config::PI_NAME]);
    if ($ret > 0) {
        return false;
    }

    return true;
}


/**
 * Loads the configuration records for the Online Config Manager.
 *
 * @return  boolean     true = proceed with install, false = an error occured
 */
function plugin_load_configuration_membership()
{
    global $_CONF, $_TABLES, $group_id;

    require_once __DIR__ . '/install_defaults.php';

    // Get the member group ID that was saved previously.
    $group_id = (int)DB_getItem(
        $_TABLES['groups'],
        'grp_id',
        "grp_name='" . Config::PI_NAME . " Members'"
    );
    return plugin_initconfig_membership($group_id);
}


/**
 * Create a membership form using the Forms plugin and initialize an empty log file.
 */
function plugin_postinstall_membership()
{
    global $_CONF, $LANG_MEMBERSHIP, $group_id,
        $_MEMBERSHIP_SAMPLEDATA;

    // Create the application form.  Set a form ID so the form won't be
    // recreated if the user reinstalls the plugin.
    $frm_id = 'pi_membership_app';

    $form = array(
        'id' => $frm_id,
        'name' => 'Membership Application',
        'introtext' => 'Please fill out the following information',
        'submit_msg' => '',
        'noaccess_msg' => '',
        'redirect' => $_CONF['site_url'] . '/membership/',
        'enabled' => '1',
        'onsubmit' => array (
            'store' => '1',
        ),
        'max_submit' => '',
        'owner_id' => '2',
        'group_id' => $group_id,
        'fill_gid' => '13',
        'results_gid' => $group_id,
    );
    $fields = array(
        0 => array(
            'frm_id' => $frm_id,
            'name' => 'name',
            'prompt' => 'Your Name',
            'type' => 'text',
            'size' => '40',
            'maxlength' => '255',
            'cols' => '',
            'rows' => '',
            'calc_type' => 'add',
            'valuestr' => '',
            'valuetext' => '',
            'defvalue' => '',
            'format' => '',
            'input_format' => '1',
            'autogen' => '0',
            'mask' => '',
            'enabled' => '1',
            'help_msg' => 'Please enter your name here',
            'fill_gid' => '13',
            'results_gid' => $group_id,
            'access' => '1',
            'orderby' => '10',
        ),
        1 => array(
            'frm_id' => $frm_id,
            'name' => 'address1',
            'prompt' => 'Address',
            'type' => 'text',
            'size' => '40',
            'maxlength' => '255',
            'cols' => '',
            'rows' => '',
            'calc_type' => 'add',
            'valuestr' => '',
            'valuetext' => '',
            'defvalue' => '',
            'format' => '',
            'input_format' => '1',
            'autogen' => '0',
            'mask' => '',
            'enabled' => '1',
            'help_msg' => '',
            'fill_gid' => '13',
            'results_gid' => $group_id,
            'access' => '1',
            'orderby' => '20',
        ),
        2 => array(
            'frm_id' => $frm_id,
            'name' => 'city',
            'prompt' => 'City',
            'type' => 'text',
            'size' => '40',
            'maxlength' => '255',
            'valuestr' => '',
            'valuetext' => '',
            'defvalue' => '',
            'format' => '',
            'input_format' => '1',
            'autogen' => '0',
            'mask' => '',
            'enabled' => '1',
            'help_msg' => '',
            'fill_gid' => '13',
            'results_gid' => $group_id,
            'access' => '1',
            'orderby' => '30',
        ),
    );

    /*$status = PLG_invokeService('forms', 'createForm',
        array(
            'form' => $form,
            'fields' => $fields,
        ),
        $output,
        $svc_msg
    );*/

    // Create an empty log file
    $filename = Config::PI_NAME . '.log';
    if (!file_exists($_CONF['path_log'] . $filename)) {
        $fp = fopen($_CONF['path_log'] . $filename, 'w+');
        if (!$fp) {
            COM_errorLog("Failed to create logfile $filename");
        } else {
            fwrite($fp, "*** Logfile Created ***\n");
        }
        if (!is_writable($_CONF['path_log'] . $filename)) {
            COM_errorLog("Can't write to $filename");
        }
    }

    if (is_array($_MEMBERSHIP_SAMPLEDATA)) {
        foreach ($_MEMBERSHIP_SAMPLEDATA as $sql) {
            DB_query($sql, 1);
            if (DB_error()) {
                COM_errorLog("Sample Data SQL Error: $sql");
            }
        }
    }

}

