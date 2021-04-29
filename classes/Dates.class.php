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
        static $retval = NULL;
        if ($retval === NULL) {
            $days = Config::get('early_renewal');
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
        static $retval = NULL;

        if ($retval === NULL) {
            $days = (int)Config::get('grace_days');
            if ($days > 0) {
                $retval = self::sub("P{$days}D")->format('Y-m-d');
            } else {
                $retval = self::Today();
            }
        }
        return $retval;
    }


    /**
     * Add some interval to the current date.
     *
     * @param   string  $interval   Interval string, e.g. "P10D"
     * @param   object  $dt         Optional date object, default = Now()
     * @return      Updated date object
     */
    public static function add($interval, $dt = NULL)
    {
        if ($dt === NULL) {
            $dtobj = clone self::Now();
        } else {
            $dtobj = clone $dt;
        }
        return $dtobj->add(new \DateInterval($interval));
    }


    /**
     * Subtract some interval from the current date.
     *
     * @param   string  $interval   Interval string, e.g. "P10D"
     * @param   object  $dtobj      Optional date object, default = Now()
     * @return      Updated date object
     */
    public static function sub($interval, $dt = NULL)
    {
        if ($dt === NULL) {
            $dtobj = clone self::Now();
        } else {
            $dtobj = clone $dt;
        }
        return $dtobj->sub(new \DateInterval($interval));
    }


    /**
     * Get the next expiration date.
     * If memberships are rolling and can be started in any month,
     * then just add a year to today.
     * If memberships are for a fixed period, like July - June, then
     * get the month & day from this year or next
     *
     * @param   string  $exp    Current expiration date, default = today
     * @return  string      New Expiration date (YYYY-MM-DD)
     */
    public static function calcExpiration($exp = '')
    {
        if ($exp == '') {
            $exp = Dates::Today();
        }

        // If a rolling membership period, just add a year to today or
        // the current expiration, whichever is greater.
        if (Config::get('period_start') == 0) {
            // Check if within the grace period.
            if ($exp < Dates::expGraceEnded()) {
                $exp = Dates::Today();
            }
            list($exp_year, $exp_month, $exp_day) = explode('-', $exp);
            $exp_year++;
            if (Config::get('expires_eom')) {
                $exp_day = cal_days_in_month(CAL_GREGORIAN, $exp_month, $exp_year);
            }
        } else {
            // If there's a fixed month for renewal, check if the membership
            // is expired. If so, get the most recent past expiration date and
            // add a year. If not yet expired, add a year to the current
            // expiration.
            list($year, $month, $day) = explode('-', $exp);
            list($c_year, $c_month, $c_day) =
                    explode('-', Dates::Today());
            $exp_month = Config::get('period_start') - 1;
            if ($exp_month == 0) $exp_month = 12;
            $exp_year = $year;
            if ($exp <= Dates::Today()) {
                if ($exp_month > $c_month)
                    $exp_year = $c_year - 1;
            }
            $exp_year++;
            $exp_day = cal_days_in_month(CAL_GREGORIAN, $exp_month, $exp_year);
        }
        return sprintf('%d-%02d-%02d', $exp_year, $exp_month, $exp_day);
    }


    public static function values2object($date, $time)
    {
        global $_CONF;

        if (empty($time)) {
            $time = '00:00:00';
        }
        $date = trim($date) . ' ' . $time;
        $retval = new \Date($date, $_CONF['timezone']);
    }

}
