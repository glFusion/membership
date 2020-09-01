<?php
/**
 * Upgrade routines for the Membership plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

// Required to get the config values
global $_CONF, $_CONF_MEMBERSHIP, $_TABLES, $_UPGRADE_SQL;

/** Include database definitions */
require_once __DIR__ . '/sql/mysql_install.php';

/**
 * Perform the upgrade starting at the current version.
 *
 * @param   boolean $dvlp   True to ignore errors and continue
 * @return  boolean     True on success, False on failure
 */
function MEMBERSHIP_do_upgrade($dvlp=false)
{
    global $_PLUGIN_INFO, $_CONF_MEMBERSHIP, $_UPGRADE_SQL, $_TABLES;

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
        Membership\Logger::System("Updating Plugin to $current_ver");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERFSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.3')) {
        // upgrade from 0.0.2 to 0.0.3.
        $current_ver = '0.0.3';
        Membership\Logger::System("Updating Plugin to $current_ver");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERFSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.4')) {
        // upgrade from 0.0.3 to 0.0.4
        $current_ver = '0.0.4';
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.5')) {
        // upgrade from 0.0.4 to 0.0.5
        $current_ver = '0.0.5';
        Membership\Logger::System("Updating Plugin to $current_ver");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.6')) {
        // upgrade from 0.0.5 to 0.0.6
        $current_ver = '0.0.6';
        Membership\Logger::System("Updating Plugin to $current_ver");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.1')) {
        $current_ver = '0.1.1';
        // Get the membership admin group ID if available
        // to set the access code for admin-only plans
        $gid = (int)DB_getItem($_TABLES['groups'], 'grp_id',
                "grp_name='{$_CONF_MEMBERSHIP['pi_name']} Admin'");
        if ($gid < 1)
            $gid = 1;        // default to Root if group not found

        // Admin-only changes from 0 to the admin GID
        $_UPGRADE_SQL[$current_ver][] = "UPDATE {$_TABLES['membership_plans']}
                SET access = $gid WHERE access = 0";
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.2')) {
        $current_ver = '0.1.2';
        Membership\Logger::System("Updating Plugin to $current_ver");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.0')) {
        $current_ver = '0.2.0';
        if (!MEMBERSHIP_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver, $dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.2')) {
        $current_ver = '0.2.2';
        if (_MEMBtableHasColumn('membership_positions', 'type')) {
            // If the old text "type" column is still in the positions table,
            // get all the types and create position group records from them.
            $res = DB_query("SELECT id, type FROM {$_TABLES['membership_positions']}");
            $idx = 0;
            while ($A = DB_query($res, true)) {
                $idx++;
                $orderby = $idx * 10;
                $_UPGRADE_SQL[$current_ver][] = "INSERT INTO {$_TABLES['membership_posgroups']}
                    (pg_id, pg_tag, pg_title, pg_orderby) VALUES
                    ($idx, '{$A['type']}', '{$A['type']}', $orderby)";
            }
        } else {
            COM_errorLog("Membership 0.2.2, type column already converted");
        }
        if (!MEMBERSHIP_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver, $dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.3.0')) {
        $current_ver = '0.3.0';
        if (!_MEMBtableHasColumn('membership_plans', 'notify_exp')) {
            // If adding the notification count, change the existing flag values
            $_UPGRADE_SQL[$current_ver][] = "UPDATE {$_TABLES['membership_members']}
                SET mem_notified = 2 WHERE mem_notified = 0";
            $_UPGRADE_SQL[$current_ver][] = "UPDATE {$_TABLES['membership_members']}
                SET mem_notified = 0 WHERE mem_notified = 1";
            $_UPGRADE_SQL[$current_ver][] = "UPDATE {$_TABLES['membership_members']}
                SET mem_notified = 1 WHERE mem_notified = 2";
        }
        if (!MEMBERSHIP_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver, $dvlp)) return false;
    }

    // Final version update to catch updates that don't go through
    // any of the update functions, e.g. code-only updates
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    } 

    // Update the plugin configuration
    USES_lib_install();
    global $membershipConfigData;
    require_once __DIR__ . '/install_defaults.php';
    _update_config('membership', $membershipConfigData);

    Membership\Logger::System("Successfully updated the {$_CONF_MEMBERSHIP['pi_display_name']} Plugin", 1);
    return true;
}


/**
 * Actually perform any sql updates.
 *
 * @param   string  $version    Version being upgraded TO
 * @param   boolean $dvlp       True to ignore SQL errors
 * @return  boolean     True on success, False on failure
 */
function MEMBERSHIP_do_upgrade_sql($version, $dvlp=false)
{
    global $_TABLES, $_CONF_MEMBERSHIP, $_UPGRADE_SQL;

    // If no sql statements passed in, return success
    if (!isset($_UPGRADE_SQL[$version]) || !is_array($_UPGRADE_SQL[$version])) {
        Membership\Logger::System("No SQL update for $current_ver");
        return true;
    }
    $sql_err_msg = 'SQL Error during Membership plugin update';
    if ($dvlp) {
        $sql_err_msg .= ' - Ignored';
    }
    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Membership to version $version");
    foreach ($_UPGRADE_SQL[$version] as $q) {
        COM_errorLOG("Membership Plugin $version update: Executing SQL => $q");
        DB_query($q, '1');
        if (DB_error()) {
            Membership\Logger::System($sql_err_msg, 1);
            if (!$dvlp) return false;
        }
    }
    return true;
}


/**
 * Update the plugin version number in the database.
 * Called at each version upgrade to keep up to date with
 * successful upgrades.
 *
 * @param   string  $ver    New version to set
 * @return  boolean         True on success, False on failure
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
        Membership\Logger::System("Error updating the {$_CONF_MEMBERSHIP['pi_display_name']} Plugin version",1);
        return false;
    } else {
        return true;
    }
}


/**
 * Check if a column exists in a table
 *
 * @param   string  $table      Table Key, defined in shop.php
 * @param   string  $col_name   Column name to check
 * @return  boolean     True if the column exists, False if not
 */
function _MEMBtableHasColumn($table, $col_name)
{
    global $_TABLES;

    $col_name = DB_escapeString($col_name);
    $res = DB_query("SHOW COLUMNS FROM {$_TABLES[$table]} LIKE '$col_name'");
    return DB_numRows($res) == 0 ? false : true;
}

?>
