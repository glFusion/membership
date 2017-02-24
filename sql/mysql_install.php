<?php
/**
*   Table definitions for the Membership plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2016 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** @global array $_TABLES */
global $_TABLES;

$_SQL['membership_members'] = "CREATE TABLE {$_TABLES['membership_members']} (
  `mem_uid` mediumint(8) unsigned NOT NULL,
  `mem_plan_id` varchar(40) NOT NULL,
  `mem_joined` date DEFAULT NULL,
  `mem_expires` date DEFAULT NULL,
  `mem_notified` int(1) NOT NULL DEFAULT '0',
  `mem_status` int(1) unsigned NOT NULL DEFAULT '1',
  `mem_guid` varchar(40) DEFAULT NULL,
  `mem_number` varchar(40) DEFAULT '',
  `mem_istrial` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`mem_uid`),
  KEY `plan_guid` (`mem_plan_id`,`mem_guid`)
)";

$_SQL['membership_plans'] = "CREATE TABLE `{$_TABLES['membership_plans']}` (
  `plan_id` varchar(40) NOT NULL,
  `name` varchar(40) NOT NULL,
  `description` text,
  `period_start` tinyint(2) NOT NULL DEFAULT '0',
  `fees` text,
  `enabled` tinyint(1) DEFAULT '1',
  `upd_links` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `grp_access` int(11) unsigned NOT NULL DEFAULT '2',
  PRIMARY KEY (`plan_id`)
)";

$_SQL['membership_links'] = "CREATE TABLE `{$_TABLES['membership_links']}` (
  `uid1` int(11) unsigned NOT NULL,
  `uid2` int(11) unsigned NOT NULL,
  UNIQUE KEY `uid` (`uid1`,`uid2`)
)";

$_SQL['membership_trans'] = "CREATE TABLE `{$_TABLES['membership_trans']}` (
  `tx_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tx_date` datetime NOT NULL,
  `tx_by` int(11) unsigned NOT NULL,
  `tx_uid` int(11) unsigned NOT NULL,
  `tx_planid` varchar(40) DEFAULT NULL,
  `tx_gw` varchar(40) DEFAULT NULL,
  `tx_amt` float(6,2) DEFAULT NULL,
  `tx_exp` date DEFAULT NULL,
  `tx_txn_id` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`tx_id`),
  KEY `uid_exp` (`tx_uid`,`tx_exp`),
  KEY `exp` (`tx_exp`)
)";

$_SQL['membership_log'] = "CREATE TABLE {$_TABLES['membership_log']} (
    id int(11) unsigned NOT NULL auto_increment,
    uid mediumint(11) unsigned NOT NULL,
    dt datetime NOT NULL,
    type varchar(255),
    data text,
  PRIMARY KEY (`id`),
  key `uid_dt` ( uid, dt )
)";

$_SQL['membership_positions'] = "CREATE TABLE {$_TABLES['membership_positions']} (
  id int(11) unsigned NOT NULL auto_increment,
  type varchar(255) NOT NULL,
  orderby int(11) unsigned default 10,
  descr varchar(255) NOT NULL DEFAULT '',
  contact varchar(255) NOT NULL DEFAULT '',
  uid mediumint(11) unsigned NOT NULL default 0,
  enabled tinyint(1) unsigned NOT NULL default 1,
  show_vacant tinyint(1) unsigned NOT NULL default 1,
  grp_id int(11) unsigned not null default 0,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`)
)";
 
$_UPGRADE_SQL = array(
'0.0.2' => array(
  "CREATE TABLE {$_TABLES['membership_log']} (
    id int(11) unsigned NOT NULL auto_increment,
    uid mediumint(11) unsigned NOT NULL,
    dt datetime NOT NULL,
    type varchar(255),
    data text,
    PRIMARY KEY (`id`),
    key `uid_dt` ( uid, dt )
    )",
  ),
'0.0.5' => array(
  "CREATE TABLE {$_TABLES['membership_positions']} (
    id int(11) unsigned NOT NULL auto_increment,
    type varchar(255) NOT NULL,
    orderby int(11) unsigned default 10,
    descr varchar(255) NOT NULL DEFAULT '',
    contact varchar(255) NOT NULL DEFAULT '',
    uid mediumint(11) unsigned NOT NULL default 0,
    enabled tinyint(1) unsigned NOT NULL default 1,
    show_vacant tinyint(1) unsigned NOT NULL default 1,
    PRIMARY KEY (`id`),
    KEY `uid` (`uid`)
    )",
    "INSERT INTO {$_TABLES['membership_positions']}
      (id, type, orderby, descr)  VALUES
        (0, 'Board', 10, 'President'),
        (0, 'Board', 20, 'Vice-President'),
        (0, 'Board', 30, 'Secretary'),
        (0, 'Board', 40, 'Treasurer')",
  ),
'0.0.6' => array(
  "ALTER TABLE {$_TABLES['membership_members']}
    DROP mem_position",
  "ALTER TABLE {$_TABLES['membership_positions']}
    ADD grp_id int(11) unsigned not null default 0",
  ),
'0.1.1' => array(
  "ALTER TABLE {$_TABLES['membership_members']}
    ADD mem_number varchar(40) DEFAULT '',
    ADD mem_istrial tinyint(1) unsigned default 0",
  ),
);

$_MEMBERSHIP_SAMPLEDATA = array(
    $_UPGRADE_SQL['0.0.5'][1],
);

?>
