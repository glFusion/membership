<?php
//  $Id: link.class.php 28 2012-09-18 23:23:01Z root $
/**
*   Class to handle membership links.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/


/**
*   Class for a membership link between accounts
*   @package    membership
*/
class MemberLink
{
    /**
    *   Link membership accounts.
    *   The new child is linked to all other children of the parent
    *
    *   @param  integer $parent     Parent uid
    *   @param  integer $child      Child uid being linked into the pool
    */
    public static function AddLink($parent, $child)
    {
        global $_TABLES;

        $parent = (int)$parent;
        $child = (int)$child;

        // First link the new child to all other children
        $sql = "SELECT uid2 FROM {$_TABLES['membership_links']}
            WHERE uid1 = '{$parent}'";
        //echo $sql;die;
        $res = DB_query($sql, 1);
        $values = array("('$parent', '$child'), ('$child', '$parent')");
        while ($A = DB_fetchArray($res, false)) {
            $link = (int)$A['uid2'];
            $values[] = "('$child', '$link'), ('$link', '$child')";
        }
        $values_arr = implode(', ', $values);
        $sql = "INSERT IGNORE INTO {$_TABLES['membership_links']} (uid1, uid2)
                VALUES $values_arr";
        //echo $sql;die;
        DB_query($sql, 1);
    }


    /**
    *   Removes a child from the link pool.
    *
    *   @param  integer $parent     Parent ID
    *   @param  integer $child      Child being removed
    */
    public static function RemLink($parent, $child)
    {
        global $_TABLES;

        // Remove all links to child.
        $sql = "SELECT uid2 FROM {$_TABLES['membership_links']}
            WHERE uid1 = '{$parent}'";
        //echo $sql;die;
        $res = DB_query($sql, 1);
        $values = array("(uid1='$parent' AND uid2='$child') OR (uid1='$child' AND uid2='$parent')");
        while ($A = DB_fetchArray($res, false)) {
            $link = (int)$A['uid2'];
            $values[] = "(uid1='$child' AND uid2='$link') OR (uid1='$link' AND uid2='$child')";
        }
        $values_arr = implode(' OR ', $values);
        $sql = "DELETE FROM {$_TABLES['membership_links']} WHERE $values_arr";
        //echo $sql;die;
        DB_query($sql, 1);
    }


    /**
    *   Remove all family links for one account.
    *   This is used on the account's membership page to remove it from the
    *   family.  All links to and from this account are removed, but other
    *   family links are unchanged.
    *
    *   @param  integer $uid    User ID to remove
    */
    public static function Emancipate($uid)
    {
        global $_TABLES;

        $uid = (int)$uid;
        $sql = "DELETE FROM {$_TABLES['membership_links']} WHERE
                uid1 = '$uid' OR uid2 = '$uid'";
        DB_query($sql);
    }

}

?>
