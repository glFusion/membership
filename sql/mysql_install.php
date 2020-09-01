<?php
/**
 * Table definitions for the Membership plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php 
 *              GNU Public License v2 or later
 * @filesource
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
  `mem_istrial` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`mem_uid`),
  KEY `plan_guid` (`mem_plan_id`,`mem_guid`),
  KEY `mem_guid` (`mem_guid`)
) ENGINE=MyISAM";

$_SQL['membership_plans'] = "CREATE TABLE `{$_TABLES['membership_plans']}` (
  `plan_id` varchar(40) NOT NULL,
  `name` varchar(40) NOT NULL,
  `description` text,
  `period_start` tinyint(2) NOT NULL DEFAULT '0',
  `fees` text,
  `enabled` tinyint(1) DEFAULT '1',
  `upd_links` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `notify_exp` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `grp_access` int(11) unsigned NOT NULL DEFAULT '2',
  PRIMARY KEY (`plan_id`)
) ENGINE=MyISAM";

$_SQL['membership_links'] = "CREATE TABLE `{$_TABLES['membership_links']}` (
  `uid1` int(11) unsigned NOT NULL,
  `uid2` int(11) unsigned NOT NULL,
  UNIQUE KEY `uid` (`uid1`,`uid2`)
) ENGINE=MyISAM";

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
) ENGINE=MyISAM";

$_SQL['membership_log'] = "CREATE TABLE {$_TABLES['membership_log']} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` mediumint(11) unsigned NOT NULL,
  `dt` datetime NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `data` text,
  PRIMARY KEY (`id`),
  KEY `uid_dt` (`uid`,`dt`)
) ENGINE=MyISAM";

$_SQL['membership_posgroups'] = "CREATE TABLE `{$_TABLES['membership_posgroups']}` (
  `pg_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pg_tag` varchar(40) NOT NULL DEFAULT '',
  `pg_title` varchar(80) NOT NULL DEFAULT '',
  `pg_orderby` int(4) NOT NULL DEFAULT '9999',
  PRIMARY KEY (`pg_id`),
  UNIQUE KEY `pg_tag` (`pg_tag`),
  KEY `pg_orderby` (`pg_orderby`)
) ENGINE=MyISAM";

$_SQL['membership_positions'] = "CREATE TABLE {$_TABLES['membership_positions']} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pg_id` int(11) unsigned NOT NULL DEFAULT 0,
  `orderby` int(11) NOT NULL DEFAULT '9999',
  `descr` varchar(255) NOT NULL DEFAULT '',
  `contact` varchar(255) NOT NULL DEFAULT '',
  `uid` mediumint(11) unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `show_vacant` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `in_lists` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `grp_id` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM";

$_SQL['membership_users'] = "CREATE TABLE `{$_TABLES['membership_users']}` (
  `uid` int(11) unsigned NOT NULL,
  `terms_accept` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM";

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
            ) ENGINE=MyISAM",
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
            ) ENGINE=MyISAM",
        "INSERT INTO {$_TABLES['membership_positions']}
            (id, type, orderby, descr)  VALUES
            (0, 'Board', 10, 'President'),
            (0, 'Board', 20, 'Vice-President'),
            (0, 'Board', 30, 'Secretary'),
            (0, 'Board', 40, 'Treasurer')",
    ),
    '0.0.6' => array(
        "ALTER TABLE {$_TABLES['membership_members']} DROP mem_position",
        "ALTER TABLE {$_TABLES['membership_positions']} ADD grp_id int(11) unsigned not null default 0",
    ),
    '0.1.1' => array(
        "ALTER TABLE {$_TABLES['membership_members']}
            ADD mem_number varchar(40) DEFAULT '',
            ADD mem_istrial tinyint(1) unsigned default 0",
        "UPDATE {$_TABLES['membership_plans']} SET access = 2 WHERE access = 1",
    ),
    '0.1.2' => array(
        "ALTER TABLE {$_TABLES['membership_plans']}
            CHANGE access grp_access int(11) unsigned not null default 2",
    ),
    '0.2.0' => array(
        "ALTER TABLE {$_TABLES['membership_members']} ADD KEY (mem_guid)",
        "CREATE TABLE `{$_TABLES['membership_users']}` (
            `uid` int(11) unsigned NOT NULL,
            `terms_accept` int(11) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`uid`)
        ) ENGINE=MyISAM",
        "UPDATE {$_TABLES['conf_values']} SET name='enable_shop'
            WHERE group_name='membership' AND name='enable_paypal'",
    ),
    '0.2.2' => array(
        "ALTER TABLE {$_TABLES['membership_positions']}
            ADD `in_lists` tinyint(1) unsigned NOT NULL DEFAULT '0' AFTER `show_vacant`",
        "ALTER TABLE {$_TABLES['membership_positions']}
            ADD `pg_id` int(11) unsigned NOT NULL DEFAULT '0' AFTER `id`",
        "ALTER TABLE {$_TABLES['membership_plans']}
            ADD `notify_exp` tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER `upd_links`",
        "CREATE TABLE `{$_TABLES['membership_posgroups']}` (
          `pg_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `pg_tag` varchar(40) NOT NULL DEFAULT '',
          `pg_title` varchar(80) NOT NULL DEFAULT '',
          `pg_orderby` int(4) NOT NULL DEFAULT '9999',
          PRIMARY KEY (`pg_id`),
          UNIQUE KEY `pg_tag` (`pg_tag`),
          KEY `pg_orderby` (`pg_orderby`)
        ) ENGINE=MyISAM",
        "ALTER TABLE {$_TABLES['membership_positions']} DROP `type`";
    ),
);

$_MEMBERSHIP_SAMPLEDATA = array(
    $_UPGRADE_SQL['0.0.5'][1],
);

?>
