<?php
/**
 * Provides automatic installation of the Membership plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2016 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.0.1
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
require_once $_CONF['path'].'plugins/membership/functions.inc';

/** Include database definitions */
require_once $_CONF['path'].'plugins/membership/sql/'. $_DB_dbms. '_install.php';

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
        'name'      => $_CONF_MEMBERSHIP['pi_name'],
        'ver'       => $_CONF_MEMBERSHIP['pi_version'],
        'gl_ver'    => $_CONF_MEMBERSHIP['gl_version'],
        'url'       => $_CONF_MEMBERSHIP['pi_url'],
        'display'   => $_CONF_MEMBERSHIP['pi_display_name'],
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
        'type'      => 'group',
        'group'     => 'membership Admin',
        'desc'      => 'Users in this group can administer the Membership plugin',
        'variable'  => 'admin_group_id',
        'admin'     => true,
        'addroot'   => true,
    ),

    array(
        'type'      => 'group',
        'group'     => 'membership Manage',
        'desc'      => 'Users in this group can manage memberships',
        'variable'  => 'manage_group_id',
        'admin'     => true,
        'addroot'   => true,
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
        'group'     => 'admin_group_id',
        'feature'   => 'admin_feature_id',
        'log'       => 'Adding Admin feature to the admin group',
    ),

    array(
        'type'      => 'mapping',
        'group'     => 'manage_group_id',
        'feature'   => 'manage_feature_id',
        'log'       => 'Adding Manager feature to the admin group',
    ),

);
// A little trickery. If the plugin has been installed, the default membership
// group name left as "membership Members", then uninstalled, the group is
// left in place since it might still be used for access control for members.
// This causes an error if the plugin is later reinstalled, so we need to check
// for the existence of the membership group before trying to create it.
$c = DB_count($_TABLES['groups'], 'grp_name', 'membership Members');
if ($c == 0) {
    $INSTALL_plugin['membership'][] = array(
        'type'      => 'group',
        'group'     => 'membership Members',
        'desc'      => 'Members are added to this group by default',
        'variable'  => 'members_group_id',
        'admin'     => false,
        'addroot'   => false,
    );
}


/**
* Puts the datastructures for this plugin into the glFusion database.
* Note: Corresponding uninstall routine is in functions.inc.
*
* @return   boolean True if successful False otherwise
*/
function plugin_install_membership()
{
    global $INSTALL_plugin, $_CONF_MEMBERSHIP;

    COM_errorLog("Attempting to install the {$_CONF_MEMBERSHIP['pi_display_name']} plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$_CONF_MEMBERSHIP['pi_name']]);
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
    global $_CONF, $_CONF_MEMBERSHIP, $_TABLES, $group_id;

    require_once MEMBERSHIP_PI_PATH . '/install_defaults.php';

    // Get the member group ID that was saved previously.
    $group_id = (int)DB_getItem($_TABLES['groups'], 'grp_id',
            "grp_name='{$_CONF_MEMBERSHIP['pi_name']} Members'");

    return plugin_initconfig_membership($group_id);
}


/**
 * Create a membership form using the Forms plugin and initialize an empty log file.
 */
function plugin_postinstall_membership()
{
    global $_CONF, $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP, $group_id,
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
        'redirect' => MEMBERSHIP_PI_URL,
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
    $filename = $_CONF_MEMBERSHIP['pi_name'] . '.log';
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

?>
