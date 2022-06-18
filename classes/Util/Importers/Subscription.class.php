<?php
/**
 * Import subscription records into the Membership plugin.
 * - Membership expirations are set to the subscription expiration
 * - Subscription::Cancel is first called to remove group membership, in case
 *   the Membership plugin uses a different group
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership\Util\Importers;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Membership\Membership;
use Membership\Plan;

class Subscription extends \Membership\Util\Importer
{
    public static function do_import(int $sub_plan_id, string $mem_plan_id, ?string $exp=NULL) : string
    {
        $Plan = Plan::getInstance($mem_plan_id);
        if ($Plan->isNew()) {
            return 'Error: Invalid plan selected.';
        }
        $Subs = PLG_callFunctionForOnePlugin('plugin_getsubscribers_subscription', array(1=>$sub_plan_id));
        $successes = 0;
        $failures = 0;
        $existing = 0;
        $failed = '';
        if (!empty($Subs)) {
            foreach ($Subs as $Sub) {
                $uid = $Sub->getUid();
                $M = Membership::getInstance($uid);
                if ($M->getPlanId() !== '') {
                    $existing++;
                    continue;
                }
                if (empty($exp)) {
                    $exp = $Sub->getExpiration();
                }
                $Txn = self::createImportTxn($uid, $mem_plan_id, 'Subscription', $exp);
                $M->setPlan($mem_plan_id)->setExpires($exp);
                if ($_CONF_MEMBERSHIP['use_mem_num'] == 2) {
                    // Generate a membership number
                    $M->setMemNumber(Membership::createMemberNumber($Sub->getUid()));
                }
                $exp = $M->Add($Txn);
                if ($exp !== false) {
                    $successes++;
                } else {
                    $failures++;
                    $failed .= COM_getDisplayName($uid) . ' (' . $uid . ')<br />' . LB;
                }
            }
        }

        return $existing . ' Existing Memberships<br />' . $successes .
            ' Successes<br />' . $failures . ' Failures:<br />' .
            $failed;
    }

}

