<?php
/**
 * Class to send notifications to members.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.2.3
 * @since       v0.2.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;

/**
 * Notification class.
 * @package shop
 */
class Notifier
{
    protected $message = '';
    protected $language = '';
    protected $uids = array();
    protected $is_manual = false;

    /**
     * Set the user IDs to notify.
     *
     * @param   array   $uids   Array of user IDs
     * @return  object  $this
     */
    public function withUids(array $uids) : self
    {
        $this->uids = $uids;
        return $this;
    }


    public function withManual(bool $flag) : self
    {
        $this->is_manual = $flag;
        return $this;
    }

}
