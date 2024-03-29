<?php
/**
 * Table definitions and other static config variables.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2021 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.3.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/**
 * Global array of table names from glFusion.
 * @global array $_TABLES
 */
global $_TABLES;

/**
 * Global table name prefix.
 * @global string $_DB_table_prefix
 */
global $_DB_table_prefix;
$prefix = $_DB_table_prefix . 'membership_';
$_TABLES['membership_members']  = $prefix . 'members';
$_TABLES['membership_plans']    = $prefix . 'plans';
$_TABLES['membership_links']    = $prefix . 'links';
$_TABLES['membership_trans']    = $prefix . 'trans';
$_TABLES['membership_newusers'] = $prefix . 'newusers';
$_TABLES['membership_log']      = $prefix . 'log';
$_TABLES['membership_positions'] = $prefix . 'positions';
$_TABLES['membership_posgroups'] = $prefix . 'pos_groups';
$_TABLES['membership_users']    = $prefix . 'users';

use Membership\Config;
Config::set('pi_version', '0.3.1');
Config::set('gl_version', '1.7.8');
Config::set('pi_display_name', 'Membership');
Config::set('pi_url', 'http://www.leegarner.com');
