<?php
/**
 * Class to handle date operations.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.2.2
 * @since       v0.2.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;


/**
 * Class to handle date operations.
 * @package membership
 */
class Dates
{
    /**
     * Shortcut to get the current date object.
     *
     * @return  object  Date object for current timestamp
     */
    public static function Now()
    {
        global $_CONF;
        return $_CONF['_now'];
    }


    /**
     * Shortcut function to get the SQL-formatted date for today.
     *
     * @return  string  Today's date as "YYYY-MM-DD"
     */
    public static function Today()
    {
        return self::Now()->format('Y-m-d', true);
    }


    /**
     * Get the latest expiration date that allows renewals.
     * This works with the early_renewal configuration to allow renewals
     * within X days of expiration.
     *
     * @return  object  Date object
     */
    public static function beginRenewal()
    {
        global $_CONF_MEMBERSHIP;

        $dt = clone self::Now();
        return $dt->add(new \DateInterval("P{$_CONF_MEMBERSHIP['early_renewal']}D"));
    }


    /**
     * Calculate and return the expiration date where the grace has ended.
     * This is the date after which memberships have truly expired.
     *
     * @return  object      Expiration date where grace period has ended.
     */
    public static function endGrace()
    {
        global $_CONF_MEMBERSHIP;

        $dt = clone self::Now();
        return $dt->sub(new \DateInterval("P{$_CONF_MEMBERSHIP['grace_days']}D"));
    }


    /**
     * Calculate one year from the supplied date, from today if not specified.
     *
     * @param   object  $dt     Date object to modify
     * @return  string          Date one year from $dt
     */
    public static function plusOneYear($dt = NULL)
    {
        $dt = clone self::Now();
        return $dt->add(new \DateInterval("P1Y"))->format('Y-m-d', true);
    }
}

?>
