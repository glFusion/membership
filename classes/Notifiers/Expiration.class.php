<?php
/**
 * Class to handle expiration notifications.
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
namespace Membership\Notifiers;
use Membership\Membership;
use Membership\Plan;
use Membership\Config;
use Membership\Status;
use Membership\Cache;
use Membership\User;
use Membership\Dates;
use Membership\Notifiers\Popup;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class for expiration notifications
 * @package membership
 */
class Expiration extends \Membership\BaseNotifier
{
    /**
     * Notify users that have memberships soon to expire.
     */
    public function Notify() : void
    {
        global $_TABLES, $_CONF, $LANG_MEMBERSHIP;

        $interval = (int)Config::get('notifydays');

        // Return if we're not configured to notify users.
        if (
            $interval < 0 ||
            Config::get('notifymethod') == Membership::NOTIFY_NONE
        ) {
            return;
        }

        $db = Database::getInstance();
        // By default only active members are notified.
        $stats = array(Status::ACTIVE);
        $qb = $db->conn->createQueryBuilder();
        try {
            $qb->select(
                'm.mem_uid', 'm.mem_notified', 'm.mem_expires', 'm.mem_plan_id',
                'u.email', 'u.username', 'u.fullname', 'u.language',
                'p.name', 'p.description'
            )
               ->from($_TABLES['membership_members'], 'm')
               ->leftJoin('m', $_TABLES['membership_plans'], 'p', 'p.plan_id=m.mem_plan_id')
               ->leftJoin('m', $_TABLES['users'], 'u', 'u.uid=m.mem_uid')
               ->where('m.mem_notified > 0');
            if (!empty($this->uids)) {
                // Force the notification and disregard the notification counter
                $qb->andWhere('m.mem_uid IN (:uids)')
                   ->setParameter('uids', $this->uids, Database::PARAM_INT_ARRAY);
            } else {
                // Get the members based on notification counter and expiration
                $qb->andWhere('m.mem_expires < DATE_ADD(:now, INTERVAL (m.mem_notified -1) * :interval DAY)')
                   ->andWhere('m.mem_status IN (:stat)')
                   ->setParameter('interval', $interval, Database::INTEGER)
                   ->setParameter('now', Dates::Today(), Database::INTEGER)
                   ->setParameter('stat', $stats, Database::PARAM_INT_ARRAY);
            }
            $data = $qb->execute()->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (empty($data)) {
            return;
        }

        $today = Dates::Today();
        $notified_ids = array();    // holds memberhsip IDs that get notified
        $T = new \Template(array(
            $_CONF['path_layout'] . 'email/',
            Config::get('pi_path') . '/templates/notify/',
        ) );
        $T->set_file(array(
            'html_msg' => 'mailtemplate_html.thtml',
            'text_msg' => 'mailtemplate_text.thtml',
            'message' => 'exp_message.thtml',
        ) );

        // Flag to get a payment button. If the first button is false,
        // the flag will be reset to avoid wasting cycles for subsequent
        // members.
        $get_pmt_btn = true;

        foreach ($data as $row) {
            if (Config::get('notifymethod') & Membership::NOTIFY_EMAIL) {
                // Create a notification email message.
                $username = COM_getDisplayName($row['mem_uid'], $row['fullname']);

                $P = Plan::getInstance($row['mem_plan_id']);
                if ($P->isNew() || !$P->notificationsEnabled()) {
                    // Do not send notifications for this plan
                    continue;
                }
                $is_expired = $row['mem_expires'] <= $today ? true : false;

                if ($get_pmt_btn) {
                    $args = array(
                        'custom'    => array('uid'   => $row['mem_uid']),
                        'amount' => $P->Price(false),
                        'item_number' => Config::PI_NAME . ':' . $P->getPlanID() .
                            ':renewal',
                        'item_name' => $P->getName(),
                        'btn_type' => 'buy_now',
                    );
                    $status = PLG_callFunctionForOnePlugin(
                        'service_genButton_shop',
                        array(
                            1 => $args,
                            2 => &$output,
                            3 => &$msg,
                        )
                    );
                    $button = ($status == PLG_RET_OK) ? $output[0] : '';
                    if (empty($button)) {
                        // Don't keep trying if buttons can't be obtained.
                        $get_pmt_btn = false;
                    }
                } else {
                    $button = '';
                }

                $fname = User::parseName($row['fullname'], 'F');
                $lname = User::parseName($row['fullname'], 'L');
                $dt = new \Date($row['mem_expires'], $_CONF['timezone']);
                $price = $P->Price($P->isNew());
                $price_txt = COM_numberFormat($price, 2);

                $T->set_var(array(
                    'site_name'     => $_CONF['site_name'],
                    'username'      => $username,
                    'pi_name'       => Config::PI_NAME,
                    'plan_id'       => $row['mem_plan_id'],
                    'plan_name'     => $row['name'],
                    'plan_dscp'     => $row['description'],
                    'detail_url'    => Config::get('url') .
                        '/index.php?detail=x&amp;plan_id=' .
                        urlencode($row['mem_plan_id']
                    ),
                    'buy_button'    => $button,
                    'exp_my'        => $dt->format('F, Y', true),
                    'exp_date'      => $dt->format($_CONF['shortdate'], true),
                    'firstname'     => $fname,
                    'lastname'      => $lname,
                    'fullname'      => $row['fullname'],
                    'is_expired'    => $is_expired,
                    'expire_eom'    => Config::get('expires_eom'),
                    'renewal_dues'  => $price_txt,
                    'currency'      => Plan::getCurrency(),
                ) );
                $T->parse('exp_msg', 'message');

                $html_content = $T->finish($T->get_var('exp_msg'));

                $T->set_block('html_msg', 'content', 'contentblock');
                $T->set_var('content_text', $html_content);
                $T->parse('contentblock', 'content');

                // Remove the button from the text version, HTML not supported.
                $T->unset_var('buy_button');
                $T->parse('exp_msg', 'message');
                $html_content = $T->finish($T->get_var('exp_msg'));
                $html2TextConverter = new \Html2Text\Html2Text($html_content);
                $text_content = $html2TextConverter->getText();
                $T->set_block('text_msg', 'contenttext', 'contenttextblock');
                $T->set_var('content_text', $text_content);
                $T->parse('contenttextblock', 'contenttext');

                $T->parse('output', 'html_msg');
                $html_msg = $T->finish($T->get_var('output'));
                $T->parse('textoutput', 'text_msg');
                $text_msg = $T->finish($T->get_var('textoutput'));

                Log::write(Config::PI_NAME, Log::INFO, "Notifying {$row['fullname']} at {$row['email']}");
                $msgData = array(
                    'htmlmessage' => $html_msg,
                    'textmessage' => $text_msg,
                    'subject' => $LANG_MEMBERSHIP['exp_notice'],
                    'from' => array(
                        'name' => $_CONF['site_name'],
                        'email' => $_CONF['noreply_mail'],
                    ),
                    'to' => array(
                        'name' => $row['fullname'],
                        'email' => $row['email'],
                    ),
                );
                COM_emailNotification($msgData);
            }

            /*if (Config::get('notifymethod') & Membership::NOTIFY_POPUP) {
                // Save a message for the next time they log in.
                $msg = sprintf(
                    $LANG_MEMBERSHIP['you_expire'],
                    $row['mem_plan_id'],
                    $row['mem_expires']
                ) . ' ' . $LANG_MEMBERSHIP['renew_link'];
                $expire_msg = date(
                    'Y-m-d',
                    strtotime(
                        '-' . Config::get('grace_days') . ' day',
                        strtotime($row['mem_expires'])
                    )
                );
                $Msg = new Popup;
                $Msg->withMessage($msg)
                    ->withExpires($expire_msg)
                    ->withUid($row['mem_uid'])
                    ->withPersists(true)
                    ->withPiCode(Membership::MSG_EXPIRING_CODE)
                    ->withUnique(true)
                    ->store();
            }*/

            // Record that we've notified this member
            $notified_ids[] = (int)$row['mem_uid'];
        }

        // Mark that the expiration notification has been sent, if not forced
        // or triggered by Membership::Expires() or Membership::Arrears().
        if (!$this->is_manual && !empty($notified_ids)) {
            try {
                $db->conn->executeStatement(
                    "UPDATE {$_TABLES['membership_members']}
                    SET mem_notified = mem_notified - 1
                    WHERE mem_uid IN (?)",
                    array($notified_ids),
                    array(Database::PARAM_INT_ARRAY)
                );
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            }
            Cache::clear('members');
        }
    }

}
