<?php
/**
 * Class to handle membership links.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;

/**
 * Class for a membership link between accounts.
 * @package membership
 */
class Link
{
    /** Cache tag added to all items cached by this class.
     * @const string */
    const CACHE_TAG = 'links';

    /**
     * Link membership accounts.
     * The new child is linked to all other children of the parent
     *
     * @param   integer $parent     Parent uid
     * @param   integer $child      Child uid being linked into the pool
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
        Cache::clear('links');
    }


    /**
     * Removes a child from the link pool.
     *
     * @param   integer $parent     Parent ID
     * @param   integer $child      Child being removed
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
        Cache::clear('links');
    }


    /**
     * Remove all family links for one account.
     * This is used on the account's membership page to remove it from the
     * family.  All links to and from this account are removed, but other
     * family links are unchanged.
     *
     * @param   integer $uid    User ID to remove
     */
    public static function Emancipate($uid)
    {
        global $_TABLES;

        $uid = (int)$uid;
        $sql = "DELETE FROM {$_TABLES['membership_links']} WHERE
                uid1 = '$uid' OR uid2 = '$uid'";
        DB_query($sql);
        Cache::clear('links');
    }


    /**
     * Get all accounts related to the specified account.
     *
     * @param   mixed   $uid    User ID
     * @return  array       Array of relatives (uid => username)
     */
    public static function getRelatives($uid)
    {
        global $_TABLES, $_USER;

        // If uid is empty, use the curent id
        $uid = (int)$uid;
        if ($uid < 1) return array();   // invalid user ID requested

        $cache_key = 'relatives_' . $uid;
        $relatives = Cache::get($cache_key);
        if ($relatives === NULL) {
            $relatives = array();
            $sql = "SELECT l.uid2, u.fullname, u.username
                    FROM {$_TABLES['membership_links']} l
                    LEFT JOIN {$_TABLES['users']} u
                    ON l.uid2 = u.uid
                    WHERE l.uid1 = $uid";
            //echo $sql;die;
            $res = DB_query($sql, 1);
            while ($A = DB_fetchArray($res, false)) {
                $relatives[$A['uid2']] = empty($A['fullname']) ?
                    $A['username'] : $A['fullname'];
            }
            Cache::set($cache_key, $relatives, self::CACHE_TAG);
        }
        return $relatives;
    }

}

?>
