<?php
/**
 * Class to handle membership transactions.
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
namespace Membership\Models;
use Membership\Config;


/**
 * Class for membership transactions.
 * @package membership
 */
class MemberNumber
{
    const NONE = 0;
    const FREEFORM = 1;
    const AUTOGEN = 2;


    /**
     * Create a membership number.
     * Calls CUSTOM_createMemberNumber() if defined, otherwise
     * uses sprintf() and the member's uid to create the ID.
     *
     * @param   integer $uid    User ID or other numeric key
     * @return  string          Membership number
     */
    public static function create(int $uid) : string
    {
        if (function_exists('CUSTOM_createMemberNumber')) {
            $retval = CUSTOM_createMemberNumber($uid);
        } else {
            $fmt = Config::get('mem_num_fmt');
            if (empty($fmt)) {
                $fmt = '%04d';
            }
            $retval = sprintf($fmt, $uid);
        }
        return $retval;
    }


    public static function regen(array $uids) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT mem_uid, mem_number
                FROM {$_TABLES['membership_members']}
                WHERE mem_uid IN (?)",
                array($uids),
                array(Database::PARAM_INT_ARRAY)
            )->fetchAll();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, $e->getMessage());
            $data = array();
        }
        foreach ($data as $A) {
            $new_mem_num = self::create((int)$A['mem_uid']);
            if ($new_mem_num != $A['mem_number']) {
                try {
                    $db->conn->update(
                        $_TABLES['membership_members'],
                        array('mem_number' => $new_mem_num),
                        array('mem_uid' => $A['mem_uid']),
                        array(Database::STRING, Database::INTEGER)
                    );
                } catch (\Throwable $e) {
                    Log::write('system', Log::ERROR, $e->getMessage());
                    $data = array();
                }
            }
        }
    }

}
