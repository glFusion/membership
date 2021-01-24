<?php
/**
 * Define member statuses.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.3.0
 * @since       v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;


/**
 * The state of a membership.
 * @package membership
 */
class Status
{
    /** Member is active (current).
     */
    public const ACTIVE = 1;

    /** Member is in arrears.
     * Expired, but within the grace period for renewal.
     */
    public const ARREARS = 2;

    /** Member has expired past the grace period.
     */
    public const EXPIRED = 4;

    /** Member has been dropped and can no longer renew.
     */
    public const DROPPED = 128;

    /**
     * Get the mailing list segment for different member statuses.
     * Used with Mailer integration to set the group (segment) in the list.
     * The strings must match segments (groups) set up in Mailer.
     *
     * @return  string      List segment matching the membership status
     */
    public static function getSegment($status)
    {
        global $_CONF_MEMBERSHIP;

        $retval = '';
        switch ($status) {
        case self::ACTIVE:
            $retval = $_CONF_MEMBERSHIP['segment_active'];
            break;
        case self::ARREARS:
            $retval = $_CONF_MEMBERSHIP['segment_arrears'];
            break;
        case self::EXPIRED:
            $retval = $_CONF_MEMBERSHIP['segment_expired'];
            break;
        case self::DROPPED:
            $retval = $_CONF_MEMBERSHIP['segment_dropped'];
            break;
        }
        return $retval;
    }


    public static function getTags($segment)
    {
        global $_CONF_MEMBERSHIP;

        $tags = array();
        foreach (array(
            'segment_active', 'segment_arrears', 'segment_expired', 'segment_dropped'
            ) as $key
        ) {
            if (!empty($_CONF_MEMBERSHIP[$key])) {
                $tags[$_CONF_MEMBERSHIP[$key]] = 'inactive';
            }
        }
        if (!empty($segment) && isset($tags[$segment])) {
            $tags[$segment] = 'active';
        }
        return $tags;
    }


    /**
     * Get parameters for Mailer to update tags or merge fields.
     * Returns a merge field name=>value if a field name is configured,
     * otherwise returns an array of tags.
     *
     * @param   integer $status     Membership status
     * @return  array       Array of merge field or tag values
     */
    public static function getMergeFields($status)
    {
        global $_CONF_MEMBERSHIP;

        $retval = array();
        $segment = self::getSegment($status);
        if (
            !empty($_CONF_MEMBERSHIP['merge_fldname']) &&
            !empty($segment)
        ) {
            $retval = array(
                $_CONF_MEMBERSHIP['merge_fldname'] => $segment,
            );
        }
        return $retval;
    }


    /**
     * Get the status value from the expiration date.
     *
     * @param   string  $exp_date   Expiration date YYYY-MM-DD
     * @return  integer         Membership status value
     */
    public static function fromExpiration($exp_date)
    {
        if ($exp_date >= Dates::Today()) {
            $retval = self::ACTIVE;
        } elseif ($exp_date > Dates::expGraceEnded()) {
            $retval = self::ARREARS;
        } else {
            $retval = self::EXPIRED;
        }
        return $retval;
    }

}
