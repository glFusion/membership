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
namespace Membership\Util\Importers;
use glFusion\Database\Database;
use glFusion\Log\Log;
use Membership\Membership;


class glFusion extends \Membership\Util\Importer
{
    /**
     * Import site members into the Membership table.
     *
     * @return  string  Results text
     */
    public static function do_import(int $grp_id, string $plan_id, string $exp) : string
    {
        global $_TABLES, $_CONF;

        $today = $_CONF['_now']->format('Y-m-d');
        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        try {
            $data = $qb->select('ga.ug_uid', 'u.fullname')
                       ->from($_TABLES['group_assignments'], 'ga')
                       ->leftJoin('ga', $_TABLES['users'], 'u', 'u.uid = ga.ug_uid')
                       ->where('ga.ug_main_grp_id = ?')
                       ->setParameter(0, $grp_id, Database::INTEGER)
                       ->execute()
                       ->fetchAll(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, $e->getMessage());
            $data = array();
        }
        $successes = 0;
        $failures = 0;
        $existing = 0;
        $failed = '';
        foreach ($data as $A) {
            $M = Membership::getInstance($A['ug_uid']);
            if ($M->getPlanId() !== '') {
                $existing++;
                continue;
            }
            $Txn = self::createImportTxn($A['ug_uid'], $plan_id, 'glFusion', $exp);
            $exp = $M->setPlan($plan_id)->Add($Txn);
            if ($exp !== false) {
                $successes++;
            } else {
                $failures++;
                $failed .= $A['fullname'] . '(' . $A['ug_uid'] . ')<br />' . LB;
            }
        }

        return $existing . ' Existing Memberships<br />' . $successes .
            ' Successes<br />' . $failures . ' Failures:<br />' .
            $failed;
    }

}

