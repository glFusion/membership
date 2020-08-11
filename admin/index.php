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

// Make sure the plugin is installed and enabled
if (!in_array('membership', $_PLUGINS)) {
    COM_404();
}

// Only let admin users access this page
if (!MEMBERSHIP_isManager()) {
    // Someone is trying to illegally access this page
    Membership\Logger::System("Someone has tried to illegally access the Membership Admin page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR",1);
    COM_404();
    exit;
}

$content = '';
$footer = '';

// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$action = $_CONF_MEMBERSHIP['adm_def_view'];
$expected = array(
    // Actions to perform
    'saveplan', 'deleteplan', 'renewmember', 'savemember',
    'renewbutton_x', 'deletebutton_x', 'renewform', 'saveposition',
    'savepg',
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
case 'genmembernum':
case 'regenbutton_x':
case 'regenbutton':
    // Generate membership numbers for all members
    // Only if configured and valid data is received in delitem variable.
    $view = 'listmembers';
    if (
        $_CONF_MEMBERSHIP['use_mem_number'] != 2 ||
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
        $new_mem_num = Membership\Membership::createMemberNumber($A['mem_uid']);
        if ($new_mem_num != $A['mem_number']) {
            $sql = "UPDATE {$_TABLES['membership_members']}
                    SET mem_number = '" . DB_escapeString($new_mem_num) . "'
                    WHERE mem_uid = '{$A['mem_uid']}'";
            DB_query($sql);
        }
    }
    COM_refresh(MEMBERSHIP_ADMIN_URL . '/index.php?' . $view);
    break;

case 'importusers':
    require_once MEMBERSHIP_PI_PATH . '/import_members.php';
    $view = 'importform';
    $footer .= MEMBERSHIP_import();
    break;

case 'quickrenew':
    $M = new Membership\Membership($_POST['mem_uid']);
    $status = $M->Add($uid, $M->Plan->plan_id, 0);
    return $status == true ? PLG_RET_OK : PLG_RET_ERROR;

case 'savemember':
    // Call plugin API function to save the membership info, if changed.
    plugin_user_changed_membership($_POST['mem_uid']);
    echo COM_refresh(MEMBERSHIP_ADMIN_URL . '/index.php?listmembers');
    exit;
    break;

case 'deletebutton_x':
case 'deletebutton':
    if (is_array($_POST['delitem'])) {
        foreach ($_POST['delitem'] as $mem_uid) {
            $status = Membership\Membership::Delete($mem_uid);
        }
    }
    echo COM_refresh(MEMBERSHIP_ADMIN_URL . '/index.php?listmembers');
    exit;

case 'renewbutton_x':
case 'renewbutton':
    if (is_array($_POST['delitem'])) {
        foreach ($_POST['delitem'] as $mem_uid) {
            $M = new Membership\Membership($mem_uid);
            $M->Renew();
        }
    }
    echo COM_refresh(MEMBERSHIP_ADMIN_URL . '/index.php?listmembers');
    exit;
    break;

case 'deleteplan':
    $plan_id = isset($_POST['plan_id']) ? $_POST['plan_id'] : '';
    $xfer_plan = isset($_POST['xfer_plan']) ? $_POST['xfer_plan'] : '';
    if (!empty($plan_id)) {
        Plan::Delete($plan_id, $xfer_plan);
    }
    $view = 'listplans';
    break;

case 'saveplan':
    $plan_id = isset($_POST['old_plan_id']) ? $_POST['old_plan_id'] : '';
    $P = new Membership\Plan($plan_id);
    $status = $P->Save($_POST);
    if ($status == true) {
        $view = 'listplans';
    } else {
        $content .= Membership\Menu::Admin('editplan');
        $content .= $P->PrintErrors($LANG_MEMBERSHIP['error_saving']);
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'savepg':
    $pg_id = isset($_POST['ppg_id']) ? $_POST['ppg_id'] : 0;
    $P = new Membership\PosGroup($pg_id);
    $status = $P->Save($_POST);
    if ($status == true) {
        COM_refresh(MEMBERSHIP_ADMIN_URL . '/index.php?posgroups');
        exit;
    } else {
        // Redisplay the edit form in case of error, keeping $_POST vars
        $content .= Membership\Menu::Admin('editpg');
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'saveposition':
    $pos_id = isset($_POST['pos_id']) ? $_POST['pos_id'] : 0;
    $P = new Membership\Position($pos_id);
    $status = $P->Save($_POST);
    if ($status == true) {
        COM_refresh(MEMBERSHIP_ADMIN_URL . '/index.php?positions');
        exit;
    } else {
        // Redisplay the edit form in case of error, keeping $_POST vars
        $content .= Membership\Menu::Admin('editpos');
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'reorderpg':
    $id = isset($_GET['id']) ? $_GET['id'] : 0;
    $where = $actionval;
    if ($id > 0 && $where != '') {
        $msg = Membership\PosGroup::Move($id, $where);
    }
    $view = 'posgroups';
    break;

case 'reorderpos':
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $id = isset($_GET['id']) ? $_GET['id'] : 0;
    $where = $actionval;
    if ($type != '' && $id > 0 && $where != '') {
        $msg = Membership\Position::Move($id, $type, $where);
    }
    $view = 'positions';
    break;

case 'deletepos':
    $P = new Membership\Position($actionval);
    $P->Delete();
    COM_refresh(MEMBERSHIP_ADMIN_URL . '/index.php?positions');
    exit;
    break;

default:
    $view = $action;
    break;
}

// Select the page to display
switch ($view) {
case 'importform':
    $content .= Membership\Menu::Admin('importform');
    $LT = new Template(MEMBERSHIP_PI_PATH . '/templates');
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
        'mem_admin_url' => MEMBERSHIP_ADMIN_URL,
    ) );
    $LT->parse('import_form','form');
    $content .= $LT->finish($LT->get_var('import_form'));
    break;

case 'editmember':
    $M = new Membership\Membership($actionval);
    $showexp = isset($_GET['showexp']) ? '?showexp' : '';
    $content .= Membership\Menu::Admin('listmembers');
    $content .= $M->Editform(MEMBERSHIP_ADMIN_URL . '/index.php' . $showexp);
    break;

case 'editplan':
    $plan_id = isset($_REQUEST['plan_id']) ? $_REQUEST['plan_id'] : '';
    $P = new Membership\Plan($plan_id);
    $content .= Membership\Menu::Admin($view);
    $content .= $P->Edit();
    break;

case 'editpos':
    $P = new Membership\Position($actionval);
    $content .= Membership\Menu::Admin($view);
    $content .= $P->Edit();
    break;

case 'editpg':
    $PG = new Membership\PosGroup($actionval);
    $content .= Membership\Menu::Admin($view);
    $content .= $PG->Edit();
    break;

case 'listmembers':
    $content .= Membership\Menu::Admin($view);
    $content .= Membership\Membership::adminList();
    break;

case 'stats':
    $content .= Membership\Menu::Admin($view);
    $content .= Membership\Membership::summaryStats();
    break;

case 'none':
    // In case any modes create their own content
    break;

case 'listtrans':
    $content .= Membership\Menu::Admin($view);
    $content .= Membership\Membership::listTrans();
    break;

case 'posgroups':
    $content .= Membership\Menu::Admin($view);
    $content .= Membership\Menu::adminPositions($view);
    $content .= Membership\PosGroup::adminList();
    break;

case 'positions':
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked attributes
        foreach ($_POST['delitem'] as $id) {
            Membership\Position::Delete($id);
        }
    }
    $content .= Membership\Menu::Admin($view);
    $content .= Membership\Menu::adminPositions($view);
    $content .= Membership\Position::adminList();
    break;

case 'listplans':
default:
    $view = 'listplans';
    $content .= Membership\Menu::Admin($view);
    $content .= Membership\Plan::adminList();
    break;

}
$output = Membership\Menu::siteHeader();
$T = new Template(MEMBERSHIP_PI_PATH . '/templates');
$T->set_file('page', 'admin_header.thtml');
$T->set_var(array(
    'header'    => $LANG_MEMBERSHIP['admin_title'],
    'version'   => $_CONF_MEMBERSHIP['pi_version'],
) );
$T->parse('output','page');
$output .= $T->finish($T->get_var('output'));
$output .= LGLIB_showAllMessages();
$output .= $content;
if ($footer != '') $output .= '<p>' . $footer . '</p>' . LB;
$output .= Membership\Menu::siteFooter();
echo $output;

?>
