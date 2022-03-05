<?php
/**
 * Entry point to administration functions for the Membership plugin.
 *
 * @author     Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2012-2020 Lee Garner <lee@leegarner.com>
 * @package    membership
 * @version    v0.2.0
 * @license    http://opensource.org/licenses/gpl-2.0.php
 *             GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion libraries */
require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
use Membership\Config;
use Membership\Plan;
use Membership\Membership;
use Membership\Menu;
use Membership\Position;
use Membership\PosGroup;
use Membership\Logger;

// Make sure the plugin is installed and enabled
if (!in_array('membership', $_PLUGINS)) {
    COM_404();
}

// Only let admin users access this page
if (!MEMBERSHIP_isManager()) {
    // Someone is trying to illegally access this page
    Logger::System("Someone has tried to illegally access the Membership Admin page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR",1);
    COM_404();
    exit;
}

$content = '';
$footer = '';

// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$action = Config::get('adm_def_view');
$expected = array(
    // Actions to perform
    'saveplan', 'deleteplan', 'savemember',
    'renewbutton_x', 'deletebutton_x', 'renewform', 'saveposition',
    'savepg', 'notify', 'quickrenew',
    'renewbutton', 'deletebutton', 'regenbutton',
    'reorderpos', 'importusers', 'genmembernum', 'regenbutton_x',
    'reorderpg', 'deletepos', 'deletepg',
    // Views to display
    'editplan', 'listplans', 'listmembers', 'editmember', 'stats',
    'listtrans', 'positions', 'editpos',
    'posgroups', 'editpg',
    'importform',
);
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
        $action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}

switch ($action) {
case 'notify':      // Force-send expiration reminders
    if (isset($_POST['delitem']) && !empty($_POST['delitem'])) {
        Membership::notifyExpiration($_POST['delitem'], true);
    }
    COM_refresh(Config::get('admin_url') . '/index.php?listmembers');
    break;
case 'genmembernum':
case 'regenbutton_x':
case 'regenbutton':
    // Generate membership numbers for all members
    // Only if configured and valid data is received in delitem variable.
    $view = 'listmembers';
    if (
        Config::get('use_mem_number') != 2 ||
        !is_array($_POST['delitem']) ||
        empty($_POST['delitem'])
    ) {
        break;
    }

    $members = implode(',', $_POST['delitem']);
    $sql = "SELECT mem_uid, mem_number
            FROM {$_TABLES['membership_members']}
            WHERE mem_uid in ($members)";
    $res = DB_query($sql, 1);
    while ($A = DB_fetchArray($res, false)) {
        $new_mem_num = Membership::createMemberNumber($A['mem_uid']);
        if ($new_mem_num != $A['mem_number']) {
            $sql = "UPDATE {$_TABLES['membership_members']}
                    SET mem_number = '" . DB_escapeString($new_mem_num) . "'
                    WHERE mem_uid = '{$A['mem_uid']}'";
            DB_query($sql);
        }
    }
    COM_refresh(Config::get('admin_url') . '/index.php?' . $view);
    break;

case 'importusers':
    require_once Config::get('pi_path') . 'import_members.php';
    $view = 'importform';
    $footer .= MEMBERSHIP_import();
    break;

case 'quickrenew':
    $M = new Membership($_POST['mem_uid']);
    if (!$M->isNew()) {
        $status = $M->Renew($_POST);
    }
    COM_refresh(Config::get('admin_url') . '/index.php?editmember=' . $_POST['mem_uid']);
    break;

case 'savemember':
    // Call plugin API function to save the membership info, if changed.
    plugin_user_changed_membership($_POST['mem_uid']);
    echo COM_refresh(Config::get('admin_url') . '/index.php?listmembers');
    exit;
    break;

case 'deletebutton_x':
case 'deletebutton':
    if (is_array($_POST['delitem'])) {
        foreach ($_POST['delitem'] as $mem_uid) {
            $status = Membership::Delete($mem_uid);
        }
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?listmembers');
    exit;

case 'renewbutton_x':
case 'renewbutton':
    if (is_array($_POST['delitem'])) {
        foreach ($_POST['delitem'] as $mem_uid) {
            $M = new Membership($mem_uid);
            $M->Renew();
        }
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?listmembers');
    exit;
    break;

case 'deleteplan':
    $plan_id = isset($_POST['plan_id']) ? $_POST['plan_id'] : '';
    $xfer_plan = isset($_POST['xfer_plan']) ? $_POST['xfer_plan'] : '';
    if (!empty($plan_id)) {
        Plan::Delete($plan_id, $xfer_plan);
    }
    COM_refresh(Config::get('admin_url') . '/index.php?listplans');
    break;

case 'saveplan':
    $plan_id = isset($_POST['old_plan_id']) ? $_POST['old_plan_id'] : '';
    $P = new Plan($plan_id);
    $status = $P->Save($_POST);
    if ($status == true) {
        COM_refresh(Config::get('admin_url') . '/index.php?listplans');
    } else {
        $content .= Menu::Admin('editplan');
        $content .= $P->PrintErrors($LANG_MEMBERSHIP['error_saving']);
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'savepg':
    $pg_id = isset($_POST['ppg_id']) ? $_POST['ppg_id'] : 0;
    $P = new PosGroup($pg_id);
    $status = $P->Save($_POST);
    if ($status == true) {
        COM_refresh(Config::get('admin_url') . '/index.php?posgroups');
        exit;
    } else {
        // Redisplay the edit form in case of error, keeping $_POST vars
        $content .= Menu::Admin('editpg');
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'saveposition':
    $pos_id = isset($_POST['pos_id']) ? $_POST['pos_id'] : 0;
    $P = new Position($pos_id);
    $status = $P->Save($_POST);
    if ($status == true) {
        COM_refresh(Config::get('admin_url') . '/index.php?positions');
        exit;
    } else {
        // Redisplay the edit form in case of error, keeping $_POST vars
        $content .= Menu::Admin('editpos');
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'reorderpg':
    $id = isset($_GET['id']) ? $_GET['id'] : 0;
    $where = $actionval;
    if ($id > 0 && $where != '') {
        $msg = PosGroup::Move($id, $where);
    }
    COM_refresh(Config::get('admin_url') . '/index.php?posgroups');
    break;

case 'reorderpos':
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $id = isset($_GET['id']) ? $_GET['id'] : 0;
    $where = $actionval;
    if ($type != '' && $id > 0 && $where != '') {
        $msg = Position::Move($id, $type, $where);
    }
    COM_refresh(Config::get('admin_url') . '/index.php?positions');
    break;

case 'deletepos':
    $P = new Position($actionval);
    $P->Delete();
    COM_refresh(Config::get('admin_url') . '/index.php?positions');
    exit;
    break;

default:
    $view = $action;
    break;
}

// Select the page to display
switch ($view) {
case 'importform':
    $content .= Menu::Admin('importform');
    $LT = new Template(Config::get('pi_path') . 'templates');
    $LT->set_file('form', 'import_form.thtml');
    if (isset($import_success)) {
        $content .= "Imported $successes successfully<br />\n";
        $content .= "$import_failures failed<br />\n";
    }
    $sql = "SELECT plan_id, name
        FROM {$_TABLES['membership_plans']}";
    $res = DB_query($sql);
    $plan_sel = '';
    while ($A = DB_fetchArray($res, false)) {
        $plan_sel .= '<option value="' . $A['plan_id'] . '">' . $A['name'] .
                '</option>' . LB;
    }
    $groups = MEMBERSHIP_groupSelection();
    $grp_options = '';
    foreach ($groups as $grp_name=>$grp_id) {
        $grp_options .= '<option value="' . $grp_id . '">' . $grp_name . "</option>\n";
    }
    $LT->set_var(array(
        'frm_grp_options' => $grp_options,
        'plan_sel'      => $plan_sel,
        'mem_admin_url' => Config::get('admin_url'),
    ) );
    $LT->parse('import_form','form');
    $content .= $LT->finish($LT->get_var('import_form'));
    break;

case 'editmember':
    $M = new Membership($actionval);
    $showexp = isset($_GET['showexp']) ? '?showexp' : '';
    $content .= Menu::Admin('listmembers');
    $content .= $M->Editform(Config::get('admin_url') . '/index.php' . $showexp);
    break;

case 'editplan':
    $plan_id = isset($_REQUEST['plan_id']) ? $_REQUEST['plan_id'] : '';
    $P = new Plan($plan_id);
    $content .= Menu::Admin($view);
    $content .= $P->Edit();
    break;

case 'editpos':
    $P = new Position($actionval);
    $content .= Menu::Admin($view);
    $content .= $P->Edit();
    break;

case 'editpg':
    $PG = new PosGroup($actionval);
    $content .= Menu::Admin($view);
    $content .= $PG->Edit();
    break;

case 'listmembers':
    $content .= Menu::Admin($view);
    $content .= Membership::adminList();
    break;

case 'stats':
    $content .= Menu::Admin($view);
    $content .= Membership::summaryStats();
    break;

case 'none':
    // In case any modes create their own content
    break;

case 'listtrans':
    $content .= Menu::Admin($view);
    $content .= Membership::listTrans();
    break;

case 'posgroups':
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked attributes
        foreach ($_POST['delitem'] as $id) {
            PosGroup::Delete($id);
        }
    }
    $content .= Menu::Admin($view);
    $content .= Menu::adminPositions($view);
    $content .= PosGroup::adminList();
    break;

case 'positions':
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked attributes
        foreach ($_POST['delitem'] as $id) {
            $P = new Position($id);
            $P->Delete();
        }
    }
    $content .= Menu::Admin($view);
    $content .= Menu::adminPositions($view);
    $content .= Position::adminList();
    break;

case 'listplans':
default:
    $view = 'listplans';
    $content .= Menu::Admin($view);
    $content .= Plan::adminList();
    break;

}
$output = Menu::siteHeader();
$T = new Template(Config::get('pi_path') . 'templates');
$T->set_file('page', 'admin_header.thtml');
$T->set_var(array(
    'header'    => $LANG_MEMBERSHIP['admin_title'],
    'version'   => Config::get('pi_version'),
) );
$T->parse('output','page');
$output .= $T->finish($T->get_var('output'));
$output .= $content;
if ($footer != '') $output .= '<p>' . $footer . '</p>' . LB;
$output .= Menu::siteFooter();
echo $output;
