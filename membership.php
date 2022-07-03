<?php
/**
 * Table definitions and other static config variables.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
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
$_TABLES['membership_messages'] = $prefix . 'messages';

use Membership\Config;
Config::set('pi_version', '0.3.2.2');
Config::set('gl_version', '2.0.0');
