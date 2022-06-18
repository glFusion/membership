<?php
/**
 * Import current members from a glFusion group into the Membership table.
 * Intended to be used only once after the Membership plugin is installed.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2015-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership\Util;
use Membership\Models\Transaction;


class Importer
{
    public static function createImportTxn(int $uid, string $plan_id, string $type, ?string $expires=NULL) : Transaction
    {
        global $_USER, $LANG_MEMBERSHIP;

        $Txn = new Transaction;
        $Txn->withGateway('import')
            ->withUid($uid)
            ->withPlanId($plan_id)
            ->withAmount(0)
            ->withDoneBy($_USER['uid'])
            ->withExpiration($expires)
            ->withTxnId($LANG_MEMBERSHIP['txn_dscp_imported'] . ' ' . $type);
        $Txn->save();
        return $Txn;
    }
}

