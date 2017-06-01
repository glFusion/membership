<?php
/**
*   Table definitions and other static config variables.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2017 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Global array of table names from glFusion
*   @global array $_TABLES
*/
global $_TABLES;

/**
*   Global table name prefix
*   @global string $_DB_table_prefix
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

/**
*   Global configuration array
*   @global array $_CONF_MEMBERSHIP
*/
global $_CONF_MEMBERSHIP;
$_CONF_MEMBERSHIP['pi_name']           = 'membership';
$_CONF_MEMBERSHIP['pi_version']        = '0.2.0';
$_CONF_MEMBERSHIP['gl_version']        = '1.6.0';
$_CONF_MEMBERSHIP['pi_url']            = 'http://www.leegarner.com';
$_CONF_MEMBERSHIP['pi_display_name']   = 'Membership';

?>
