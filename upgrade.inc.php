<?php
//  $Id: upgrade.inc.php 118 2015-01-05 18:20:06Z root $
/**
*   Upgrade routines for the Membership plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2016 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

// Required to get the config values
global $_CONF, $_CONF_MEMBERSHIP, $_DB_dbms, $_TABLES, $_SQL, $_UPGRADE_SQL;

/** Include database definitions */
require_once MEMBERSHIP_PI_PATH . '/sql/'. $_DB_dbms. '_install.php';

/**
*   Make the installation default values available to these functions.
*/
require_once MEMBERSHIP_PI_PATH . '/install_defaults.php';


/**
*   Perform the upgrade starting at the current version.
*
*   @param  string  $current_ver    Current installed version to be upgraded
*   @return integer                 Error code, 0 for success
*/
function MEMBERSHIP_do_upgrade($current_ver)
{
    $error = 0;

    if ($current_ver < '0.0.2') {
        // upgrade from 0.0.1 to 0.0.2
        COM_errorLog("Updating Plugin to 0.0.2");
        $error = MEMBERSHIP_do_upgrade_sql('0.0.2');
        if ($error)
            return $error;
    }

    if ($current_ver < '0.0.3') {
        // upgrade from 0.0.2 to 0.0.3
        COM_errorLog("Updating Plugin to 0.0.3");
        $error = MEMBERSHIP_upgrade_0_0_3();
        if ($error)
            return $error;
    }

    if ($current_ver < '0.0.4') {
        // upgrade from 0.0.3 to 0.0.4
        COM_errorLog("Updating Plugin to 0.0.4");
        $error = MEMBERSHIP_upgrade_0_0_4();
        if ($error)
            return $error;
    }

    if ($current_ver < '0.0.5') {
        // upgrade from 0.0.4 to 0.0.5
        COM_errorLog("Updating Plugin to 0.0.5");
        $error = MEMBERSHIP_do_upgrade_sql('0.0.5');
        if ($error)
            return $error;
    }

    if ($current_ver < '0.0.6') {
        // upgrade from 0.0.5 to 0.0.6
        COM_errorLog("Updating Plugin to 0.0.6");
        $error = MEMBERSHIP_do_upgrade_sql('0.0.6');
        if ($error)
            return $error;
    }

    if ($current_ver < '0.1.1') {
        $error = MEMBERSHIP_upgrade_0_1_1();
        if ($error)
            return $error;
    }

    return $error;

}


/**
*   Update to version 0.0.4
*   - Adds group for members in arrears
*
*   @return integer Status, always zero if no SQL changes
*/
function MEMBERSHIP_upgrade_0_0_4()
{
    global $_CONF_MEMBERSHIP, $_MEMBERSHIP_DEFAULT, $_TABLES;

    /*$c = config::get_instance();
    if ($c->group_exists($_CONF_MEMBERSHIP['pi_name'])) {
        // Subgroup - integrations
   //     $c->add('member_all_group', (int)$group_id,
    //            'select', 0, 10, 0, 15, true, $_CONF_MEMBERSHIP['pi_name']);
    }
    /* Rename the original members group to indicate it is for current members
    $res = (int)DB_getItem($_TABLES['groups'], 'grp_id', "grp_name='membership Members'");
    if ($res > 0) {
        DB_query("UPDATE {$_TABLES['groups']}
                SET grp_name='membership Members-Current'
                WHERE grp_id = $res");
    }
    */
    return MEMBERSHIP_do_upgrade_sql('0.0.4');

}


/**
*   Update to version 0.0.3
*   - Adds notifymethod configuration item
*   - Adds terms & conditions url and acceptance requirement options
*
*   @return integer Status, always zero if no SQL changes
*/
function MEMBERSHIP_upgrade_0_0_3()
{
    global $_CONF_MEMBERSHIP, $_MEMBERSHIP_DEFAULT;

    $c = config::get_instance();
    if ($c->group_exists($_CONF_MEMBERSHIP['pi_name'])) {
        $c->add('notifymethod', $_MEMBERSHIP_DEFAULT['notifymethod'],
                'select', 0, 10, 18, 65, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('terms_accept', $_MEMBERSHIP_DEFAULT['terms_accept'],
                'select', 0, 10, 16, 150, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('terms_url', $_MEMBERSHIP_DEFAULT['terms_url'],
                'text', 0, 10, 0, 160, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('sg_integrations', NULL, 'subgroup',
                20, 0, NULL, 0, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('fs_mailchimp', NULL, 'fieldset', 20, 10, NULL, 0, true,
                $_CONF_MEMBERSHIP['pi_name']);
        $c->add('update_maillist', $_MEMBERSHIP_DEFAULT['update_maillist'],
                'select', 20, 10, 3, 10, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('segment_active', $_MEMBERSHIP_DEFAULT['segment_active'],
                'text', 20, 10, 0, 20, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('segment_arrears', $_MEMBERSHIP_DEFAULT['segment_arrears'],
                'text', 20, 10, 0, 20, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('segment_expired', $_MEMBERSHIP_DEFAULT['segment_expired'],
                'text', 20, 10, 0, 30, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('segment_dropped', $_MEMBERSHIP_DEFAULT['segment_dropped'],
                'text', 20, 10, 0, 40, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('fs_mediagallery', NULL, 'fieldset', 20, 20, NULL, 0, true,
                $_CONF_MEMBERSHIP['pi_name']);
        $c->add('manage_mg_quota', $_MEMBERSHIP_DEFAULT['manage_mg_quota'],
                'select', 20, 20, 3, 10, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('mg_quota_member', $_MEMBERSHIP_DEFAULT['mg_quota_member'],
                'text', 20, 20, 0, 20, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('mg_quota_nonmember', $_MEMBERSHIP_DEFAULT['mg_quota_nonmember'],
                'text', 20, 20, 0, 30, true, $_CONF_MEMBERSHIP['pi_name']);
     }
    return 0;
}


/**
*   Upgrade to 0.1.1
*   Get the membership Admin group ID and update plan access
*   Add membership number options
*   Optionally disable "buy now" button
*/
function MEMBERSHIP_upgrade_0_1_1()
{
    global $_CONF_MEMBERSHIP, $_MEMBERSHIP_DEFAULT, $_TABLES;

    $error = MEMBERSHIP_do_upgrade_sql('0.1.1');
    if ($error) return $error;

    $c = config::get_instance();
    if ($c->group_exists($_CONF_MEMBERSHIP['pi_name'])) {
        // Fieldset - Paypal plugin options
        $c->add('fs_paypal', NULL, 'fieldset', 20, 30, NULL, 0, true,
                $_CONF_MEMBERSHIP['pi_name']);
        $c->add('allow_buy_now', $_MEMBERSHIP_DEFAULT['allow_buy_now'],
                'select', 20, 30, 3, 10, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('use_mem_number', $_MEMBERSHIP_DEFAULT['use_mem_number'],
                'select', 0, 10, 19, 170, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('mem_num_fmt', $_MEMBERSHIP_DEFAULT['mem_num_fmt'],
                'text', 0, 10, 0, 180, true, $_CONF_MEMBERSHIP['pi_name']);
        // onmenu config not needed, menu appearance based on rights
        $c->del('onmenu', $_CONF_MEMBERSHIP['pi_name']);
    }

    // Get the membership admin group ID if available
    // to set the access code for admin-only plans
    $gid = (int)DB_getItem($_TABLES['groups'], 'grp_id', 
            "grp_name='{$_CONF_MEMBERSHIP['pi_name']} Admin'");
    if ($gid < 1)
        $gid = 1;        // default to Root if group not found

    // Change the access code to use the glFusion group ID
    // Public changes from 1 to All Users
    DB_query("UPDATE {$_TABLES['membership_plans']}
                SET access = 2 WHERE access = 1", 1);
    // Admin-only changes from 0 to the admin GID
    DB_query("UPDATE {$_TABLES['membership_plans']}
                SET access = $gid WHERE access = 0", 1);
    return $error;
}

 
/**
*   Actually perform any sql updates.
*
*   @param  string  $version    Version being upgraded TO
*   @return integer         0 on success, 1 on failure.
*/
function MEMBERSHIP_do_upgrade_sql($version)
{
    global $_TABLES, $_CONF_MEMBERSHIP, $_UPGRADE_SQL;

    // If no sql statements passed in, return success
    if (!isset($_UPGRADE_SQL[$version]) || !is_array($_UPGRADE_SQL[$version])) {
        return 0;
    }
    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Membership to version $version");
    foreach ($_UPGRADE_SQL[$version] as $q) {
        COM_errorLOG("Membership Plugin $version update: Executing SQL => $q");
        DB_query($q, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Membership plugin update",1);
            return 1;
            break;
        }
    }
    return 0;
}

?>
