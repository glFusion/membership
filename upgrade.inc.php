<?php
/**
*   Upgrade routines for the Membership plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2017 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.2
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
*   @return boolean     True on success, False on failure
*/
function MEMBERSHIP_do_upgrade()
{
    global $_PLUGIN_INFO, $_CONF_MEMBERSHIP;

    if (isset($_PLUGIN_INFO[$_CONF_MEMBERSHIP['pi_name']])) {
        if (is_array($_PLUGIN_INFO[$_CONF_MEMBERSHIP['pi_name']])) {
            // glFusion > 1.6.5
            $current_ver = $_PLUGIN_INFO[$_CONF_MEMBERSHIP['pi_name']]['pi_version'];
        } else {
            // legacy
            $current_ver = $_PLUGIN_INFO[$_CONF_MEMBERSHIP['pi_name']];
        }
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_membership();

    if (!COM_checkVersion($current_ver, '0.0.2')) {
        // upgrade from 0.0.1 to 0.0.2
        $current_ver = '0.0.2';
        COM_errorLog("Updating Plugin to 0.0.2");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERFSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.3')) {
        // upgrade from 0.0.2 to 0.0.3
        $current_ver = '0.0.3';
        if (!MEMBERSHIP_upgrade_0_0_3()) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.4')) {
        // upgrade from 0.0.3 to 0.0.4
        $current_ver = '0.0.4';
        if (!MEMBERSHIP_upgrade_0_0_4()) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.5')) {
        // upgrade from 0.0.4 to 0.0.5
        $current_ver = '0.0.5';
        COM_errorLog("Updating Plugin to 0.0.5");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.6')) {
        // upgrade from 0.0.5 to 0.0.6
        $current_ver = '0.0.6';
        COM_errorLog("Updating Plugin to $current_ver");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.1')) {
        $current_ver = '0.1.1';
        if (!MEMBERSHIP_upgrade_0_1_1()) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.2')) {
        $current_ver = '0.1.2';
        COM_errorLog("Updating Plugin to $current_ver");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.0')) {
        $current_ver = '0.2.0';
        $c = config::get_instance();
        if ($c->group_exists($_CONF_MEMBERSHIP['pi_name'])) {
            $c->add('redir_after_purchase', $_MEMBERSHIP_DEFAULT['redir_after_purchase'],
                'text', 20, 30, 0, 20, true, $_CONF_MEMBERSHIP['pi_name']);
        }
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    // Final version update to catch updates that don't go through
    // any of the update functions, e.g. code-only updates
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    } 
    COM_errorLog("Successfully updated the {$_CONF_MEMBERSHIP['pi_display_name']} Plugin", 1);
    return true;
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

    COM_errorLog("Updating Plugin to 0.0.4");
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
    if (!MEMBERSHIP_do_upgrade_sql('0.0.4')) return false;
    return MEMBERSHIP_do_set_version('0.0.3');
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

    COM_errorLog("Updating Plugin to 0.0.3");
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
    return MEMBERSHIP_do_set_version('0.0.3');
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

    COM_errorLog("Updating Plugin to 0.0.1");
    if (!MEMBERSHIP_do_upgrade_sql('0.1.1')) return false;

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
        $c->add('disable_expired', $_MEMBERSHIP_DEFAULT['disable_expired'],
                'select', 0, 10, 3, 80, true, $_CONF_MEMBERSHIP['pi_name']);
        // onmenu config not needed, menu appearance based on rights
        $c->del('onmenu', $_CONF_MEMBERSHIP['pi_name']);
    }

    // Change the yes/now allow_buy_now to a multichoice enable_paypal.
    if ($_CONF_MEMBERSHIP['allow_buy_now'] == 0) {
        $value = 1;     // no buy now, change to cart only
    } else {
        $value = 2;     // buy_now ok, change to buy_now + cart
    }
    $c->add('enable_paypal', $_MEMBERSHIP_DEFAULT['enable_paypal'],
            'select', 20, 30, 20, 10, true, $_CONF_MEMBERSHIP['pi_name']);
    $c->del('allow_buy_now', $_CONF_MEMBERSHIP['pi_name']);

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
    return MEMBERSHIP_do_set_version('0.0.3');
}

/**
*   Upgrade to 0.1.2
*   Expande and rename access to grp_access for plans
*/
function MEMBERSHIP_upgrade_0_1_2()
{
    global $_CONF_MEMBERSHIP, $_MEMBERSHIP_DEFAULT, $_TABLES;

    COM_errorLog("Updating Plugin to 0.0.3");
    DB_query("ALTER TABLE {$_TABLES['membership_plans']}
            CHANGE access grp_access int(11) unsigned not null default 2");
    return true;
}


/**
*   Actually perform any sql updates.
*
*   @param  string  $version    Version being upgraded TO
*   @return boolean     True on success, False on failure
*/
function MEMBERSHIP_do_upgrade_sql($version)
{
    global $_TABLES, $_CONF_MEMBERSHIP, $_UPGRADE_SQL;

    COM_errorLog("Updating Plugin to $current_ver");
    // If no sql statements passed in, return success
    if (!isset($_UPGRADE_SQL[$version]) || !is_array($_UPGRADE_SQL[$version])) {
        COM_errorLog("No SQL update for $current_ver");
        return true;
    }
    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Membership to version $version");
    foreach ($_UPGRADE_SQL[$version] as $q) {
        COM_errorLOG("Membership Plugin $version update: Executing SQL => $q");
        DB_query($q, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Membership plugin update",1);
            return false;
            break;
        }
    }
    return true;
}


/**
*   Update the plugin version number in the database.
*   Called at each version upgrade to keep up to date with
*   successful upgrades.
*
*   @param  string  $ver    New version to set
*   @return boolean         True on success, False on failure
*/
function MEMBERSHIP_do_set_version($ver)
{
    global $_TABLES, $_CONF_MEMBERSHIP;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '{$_CONF_MEMBERSHIP['pi_version']}',
            pi_gl_version = '{$_CONF_MEMBERSHIP['gl_version']}',
            pi_homepage = '{$_CONF_MEMBERSHIP['pi_url']}'
        WHERE pi_name = '{$_CONF_MEMBERSHIP['pi_name']}'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("Error updating the {$_CONF_MEMBERSHIP['pi_display_name']} Plugin version",1);
        return false;
    } else {
        return true;
    }
}

?>
