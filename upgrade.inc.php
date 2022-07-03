<?php
/**
 * Upgrade routines for the Membership plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

// Required to get the config values
global $_CONF, $_TABLES, $_UPGRADE_SQL;

use Membership\Config;
use glFusion\Database\Database;
use glFusion\Log\Log;

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
    global $_PLUGIN_INFO, $_UPGRADE_SQL, $_TABLES;

    $db = Database::getInstance();

    if (isset($_PLUGIN_INFO[Config::PI_NAME])) {
        if (is_array($_PLUGIN_INFO[Config::PI_NAME])) {
            // glFusion > 1.6.5
            $current_ver = $_PLUGIN_INFO[Config::PI_NAME]['pi_version'];
        } else {
            // legacy
            $current_ver = $_PLUGIN_INFO[Config::PI_NAME];
        }
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_membership();

    if (!COM_checkVersion($current_ver, '0.0.2')) {
        // upgrade from 0.0.1 to 0.0.2
        $current_ver = '0.0.2';
        Log::write('system', Log::INFO, "Updating Plugin to $current_ver");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERFSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.3')) {
        // upgrade from 0.0.2 to 0.0.3.
        $current_ver = '0.0.3';
        Log::write('system', Log::INFO, "Updating Plugin to $current_ver");
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
        Log::write('system', Log::INFO, "Updating Plugin to $current_ver");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.0.6')) {
        // upgrade from 0.0.5 to 0.0.6
        $current_ver = '0.0.6';
        Log::write('system', Log::INFO, "Updating Plugin to $current_ver");
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.1')) {
        $current_ver = '0.1.1';
        // Get the membership admin group ID if available
        // to set the access code for admin-only plans
        $gid = (int)$db->conn->getItem(
            $_TABLES['groups'],
            'grp_id',
            array('grp_name' => Config::PI_NAME. ' Admin')
        );
        if ($gid < 1) {
            $gid = 1;        // default to Root if group not found
        }

        // Admin-only changes from 0 to the admin GID
        $_UPGRADE_SQL[$current_ver][] = "UPDATE {$_TABLES['membership_plans']}
                SET access = $gid WHERE access = 0";
        if (!MEMBERSHIP_do_upgrade_sql($current_ver)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.1.2')) {
        $current_ver = '0.1.2';
        Log::write('system', Log::INFO, "Updating Plugin to $current_ver");
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
            $idx = 0;
            try {
                $stmt = $db->conn->executeQuery(
                    "SELECT id, type FROM {$_TABLES['membership_positions']}"
                );
                while ($A = $stmt->fetchAssociative()) {
                    $idx++;
                    $orderby = $idx * 10;
                    $_UPGRADE_SQL[$current_ver][] = "INSERT INTO {$_TABLES['membership_posgroups']}
                        (pg_id, pg_tag, pg_title, pg_orderby) VALUES
                        ($idx, '{$A['type']}', '{$A['type']}', $orderby)";
                }
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
            }
        } else {
            Log::write('system', Log::ERROR, "Membership 0.2.2, type column already converted");
        }
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

    if (!COM_checkVersion($current_ver, '0.2.3')) {
        $current_ver = '0.2.3';
        if (!MEMBERSHIP_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver, $dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.3.0')) {
        $current_ver = '0.3.0';
        if (!MEMBERSHIP_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!MEMBERSHIP_do_set_version($current_ver, $dvlp)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.0.0')) {
        $current_ver = '1.0.0';
        // Increase the notification count to include the final notification.
        if (!DB_checkTableExists('membership_messages')) {  // do only once
            $not_count = (int)Config::get('notifycount');
            if ($not_count > 0) {
                Config::write('notifycount', $not_count + 1);
            }
            $_UPGRADE_SQL[$current_ver][] = "UPDATE {$_TABLES['membership_members']}
                SET mem_notified = mem_notified + 1
                WHERE mem_notified > 0";
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
    _MEMB_remove_old_files();
    Membership\Cache::clear();
    Log::write('system', Log::INFO, "Successfully updated the " . Config::get('pi_display_name') . ' Plugin');
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
    global $_TABLES, $_UPGRADE_SQL;

    // If no sql statements passed in, return success
    if (!isset($_UPGRADE_SQL[$version]) || !is_array($_UPGRADE_SQL[$version])) {
        Log::write('system', Log::DEBUG, "No SQL update for $version");
        return true;
    }
    $db = Database::getInstance();
    if ($dvlp) {
        $ignored = ' - Ignored';
    } else {
        $ignored = '';
    }
    // Execute SQL now to perform the upgrade
    Log::write('system', Log::ERROR, "--Updating Membership to version $version");
    foreach ($_UPGRADE_SQL[$version] as $q) {
        Log::write('system', Log::ERROR, "Membership Plugin $version update: Executing SQL => $q");
        try {
            $db->conn->executeStatement($q);
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage() . $ignored);
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
    global $_TABLES;

    $db = Database::getInstance();

    // now update the current version number.
    try {
        $db->conn->update(
            $_TABLES['plugins'],
            array(
                'pi_version' => Config::get('pi_version'),
                'pi_gl_version' => Config::get('gl_version'),
                'pi_homepage' => Config::get('pi_url'),
            ),
            array('pi_name' => Config::PI_NAME),
            array(
                Database::STRING,
                Database::STRING,
                Database::STRING,
                Database::STRING,
            )
        );
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        return false;
    }
    return true;
}


/**
 * Check if a column exists in a table
 *
 * @param   string  $table      Table Key, defined in membership.php
 * @param   string  $col_name   Column name to check
 * @return  boolean     True if the column exists, False if not
 */
function _MEMBtableHasColumn($table, $col_name)
{
    global $_TABLES;

    $db = Database::getInstance();
    try {
        $data = $db->conn->executeQuery(
            "SHOW COLUMNS FROM {$_TABLES[$table]} LIKE ?",
            array($col_name),
            array(Database::STRING)
        )->fetchAssociative();
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        $data = false;
    }
    return !empty($data);
}


/**
 * Remove a file, or recursively remove a directory.
 *
 * @param   string  $dir    Directory name
 */
function _MEMB_rmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . '/' . $object)) {
                    _MEMB_rmdir($dir . '/' . $object);
                } else {
                    @unlink($dir . '/' . $object);
                }
            }
        }
        @rmdir($dir);
    } elseif (is_file($dir)) {
        @unlink($dir);
    }
}


/**
 * Remove deprecated files
 * Errors in unlink() and rmdir() are ignored.
 */
function _MEMB_remove_old_files()
{
    global $_CONF;

    $paths = array(
        // private/plugins/membership
        __DIR__ => array(
            // 0.2.3
            'language/english.php',
            // 0.3.0
            'templates/editmember.uikit.thtml',
            'templates/import_form.uikit.thtml',
            'templates/plan_form.uikit.thtml',
            'templates/position_form.uikit.thtml',
            'membership_functions.inc.php',
            // 1.0.0
            'import_members.php',
            'classes/Logger.class.php',
            'classes/Icon.class.php',
        ),
        // public_html/membership
        $_CONF['path_html'] . 'membership' => array(
            // 1.0.0
            'images/check_icon.jpg',
            'images/regen_mem_number.jpg',
            'images/renew.png',
            'images/required.png',
        ),
        // admin/plugins/membership
        $_CONF['path_html'] . 'admin/plugins/membership' => array(
            // 1.0.0
            'importsub.php',
        ),
    );

    foreach ($paths as $path=>$files) {
        foreach ($files as $file) {
            if (file_exists("$path/$file")) {
                Log::write('system', Log::ERROR, "removing $path/$file");
                _MEMB_rmdir("$path/$file");
            }
        }
    }
}
