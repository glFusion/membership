<?php
/**
 * Class to handle integration with the Subscription plugin.
 * Used to get origin groups for importing into Membership.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership\Integrations;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Membership\Config;


/**
 * Subscription plugin integration functions.
 * @package membership
 */
class Mediagallery extends \Membership\Integration
{
    protected static $pi_name = 'mediagallery';


    /**
     * Manage user quotas in Mediagallery based on membership status.
     * Mediagallery doesn't have an API for this so the database must be
     * updated directly.
     *
     * @param   integer $uid    Member user ID
     * @param   integer $mem_status     Member status
     * @return  boolean     True on success, False on error
     */
    public static function manageQuota(int $uid, int $mem_status) : bool
    {
        global $_TABLES;

        if (!self::isEnabled()) {
            return false;
        }

        $db = Database::getInstance();
        $quota = (int)$db->getItem($_TABLES['mg_userprefs'], 'quota', array('uid' => $uid));
        if ($quota > 0) {
            $max = (int)Config::get('mg_quota_member');
            $min = (int)Config::get('mg_quota_nonmember');
            // sanity checking. Min must be positive to have an effect,
            // zero is unlimited. Max can be zero but otherwise must be > min
            if ($min < 1) $min = 1;
            if ($max == 0 || $min < $max) {
                switch ($mem_status) {
                case Status::ACTIVE:
                case Status::ARREARS:
                    $size = $max * 1048576;
                    break;
                default:
                    $size = $min * 1048576;
                    break;
                }
                if ($size != $quota) {
                    // Update the MG uerpref table with the new quota.
                    // Ignore errors, nothing to be done about them here.
                    try {
                        $db->conn->insert(
                            $_TABLES['mg_userprefs'],
                            array(`uid` => $uid, 'quota' => $size),
                            array(Database::INTEGER, Database::INTEGER)
                        );
                    } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
                        try {
                            $db->conn->update(
                                $_TABLES['mg_userprefs'],
                                array('quota' => $size),
                                array('uid' => $uid),
                                array(Database::INTEGER, Database::INTEGER)
                            );
                        } catch (\Exception $e) {
                            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                        }
                    } catch (\Exception $e) {
                        Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                        return false;
                    }
                }
            }
        }
        return true;
    }

}
