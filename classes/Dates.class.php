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
    public static function plusRenewal()
    {
        global $_CONF_MEMBERSHIP;

        static $retval = NULL;
        if ($retval === NULL) {
            $days = $_CONF_MEMBERSHIP['early_renewal'];
            if ($days > 0) {
                $retval = self::add("P{$days}D")->format('Y-m-d');
            } else {
                $retval = self::Today();
            }
        }
        return $retval;
    }


    /**
     * Calculate and return the expiration date where the grace has ended.
     * This is the date after which memberships have truly expired.
     *
     * @return  object      Expiration date where grace period has ended.
     */
    public static function expGraceEnded()
    {
        global $_CONF_MEMBERSHIP;
        static $retval = NULL;

        if ($retval === NULL) {
            $days = (int)$_CONF_MEMBERSHIP['grace_days'];
            if ($days > 0) {
                $retval = self::sub("P{$days}D")->format('Y-m-d');
            } else {
                $retval = self::Today();
            }
        }
        return $retval;
    }


    /**
     * Get the expiration date which would be now + notification interval.
     * Returns 9999-12-31 if notification is disabled.
     *
     * @return  string      Date as YYYY-MM-DD
     */
    public static function plusNotify()
    {
        global $_CONF_MEMBERSHIP;

        static $retval = NULL;
        if ($retval === NULL) {
            $days = (int)$_CONF_MEMBERSHIP['notifydays'];
            if ($days > -1) {
                $retval = self::add("P{$days}D")->format('Y-m-d');
            } else {
                $retval = '9999-12-31';
            }
        }
        return $retval;
    }


    /**
     * Calculate one year from the supplied date, from today if not specified.
     *
     * @param   object  $dt     Date object to modify
     * @return  string          Date one year from $dt
     */
    public static function plusOneYear($dt = NULL)
    {
        static $retval = NULL;
        if ($retval === NULL) {
            $retval = self::add("P1Y")->format('Y-m-d', true);
        }
        return $retval;
    }


    /**
     * Add some interval to the current date.
     *
     * @param   string  $interval   Interval string, e.g. "P10D"
     * @param   object  $dtobj      Optional date object, default = Now()
     * @return      Updated date object
     */
    public static function add($interval, $dtobj = NULL)
    {
        $dtobj = clone self::Now();
        return $dtobj->add(new \DateInterval($interval));
    }


    /**
     * Subtract some interval from the current date.
     *
     * @param   string  $interval   Interval string, e.g. "P10D"
     * @param   object  $dtobj      Optional date object, default = Now()
     * @return      Updated date object
     */
    public static function sub($interval, $dtobj = NULL)
    {
        $dtobj = clone self::Now();
        return $dtobj->sub(new \DateInterval($interval));
    }

}

?>
