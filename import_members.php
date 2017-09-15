<?php
/**
*   Import current members from a glFusion group into the Membership table
*   Intended to be used only once after the Membership plugin is installed.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2015 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Import members into the Membership table
*
*   @return array   Array if (successes, failures)
*/
function MEMBERSHIP_import()
{
    global $_TABLES, $_CONF;

    $from_grp = (int)$_POST['from_group'];
    $exp = $_POST['expiration'];
    $plan_id = $_POST['plan'];
    $dt = new Date('now', $_CONF['timezone']);
    $today = $dt->format('Y-m-d');

    $sql = "SELECT ga.ug_uid, u.fullname
            FROM {$_TABLES['group_assignments']} ga
            LEFT JOIN {$_TABLES['users']} u ON u.uid = ga.ug_uid
            WHERE ga.ug_main_grp_id = $from_grp";
    $res = DB_query($sql);
    $successes = 0;
    $failures = 0;
    $existing = 0;
    $failed = '';
    while ($A = DB_fetchArray($res, false)) {
        $M = new Membership\Membership($A['ug_uid']);
        if ($M->plan_id !== '') {
            $existing++;
            continue;
        }
        $exp = $M->Add($A['ug_uid'], $plan_id, $exp, $today);
        if ($exp !== false) {
            $successes++;
        } else {
            $failures++;
            $failed .= $A['fullname'] . '<br />' . LB;
        }
    }
    return $existing . ' Existing Memberships<br />' . $successes . ' Successes<br />' . $failures . ' Failures:<br />' .
            $failed;

}
?>
