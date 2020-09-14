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
namespace Membership\Models;


/**
 * The state of a membership.
 * @package membership
 */
class Status
{
    /** Member is active (current).
     */
    public const ACTIVE = 0;

    /** Member is enabled. Unused.
     */
    public const ENABLED = 1;

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
     * Used with Mailchimp integration to set the group (segment) in the list.
     * The strings must match segments (groups) set up in Mailchimp.
     *
     * @return  string      List segment matching the membership status
     */
    function getSegment($status)
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

}
