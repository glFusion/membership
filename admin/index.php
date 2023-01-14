<?php
/**
 * Entry point to administration functions for the Membership plugin.
 *
 * @author     Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2012-2022 Lee Garner <lee@leegarner.com>
 * @package    membership
 * @version    v1.0.0
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
use Membership\Models\Transaction;
use Membership\Models\MemberNumber;
use Membership\Models\Request;
use glFusion\Database\Database;
use glFusion\Log\Log;

// Make sure the plugin is installed and enabled
if (!in_array('membership', $_PLUGINS)) {
    COM_404();
}

// Only let admin users access this page
if (!MEMBERSHIP_isManager()) {
    // Someone is trying to illegally access this page
    Log::write('system', Log::ERROR,
        "Someone has tried to illegally access the Membership Admin page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR"
    );
    COM_404();
    exit;
}

$Request = Request::getInstance();
$content = '';
$footer = '';
$db = Database::getInstance();

// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$action = Config::get('adm_def_view');
$expected = array(
    // Actions to perform
    'saveplan', 'deleteplan', 'savemember', 'createmember',
    'renewbutton_x', 'deletebutton_x', 'renewform', 'saveposition',
    'savepg', 'delpg', 'notify', 'quickrenew',
    'renewbutton', 'deletebutton', 'regenbutton',
    'reorderpos', 'importusers', 'genmembernum', 'regenbutton_x',
    'reorderpg', 'deletepos', 'deletepg', 'tx_save',
    // Views to display
    'editplan', 'listplans', 'listmembers', 'editmember', 'stats',
    'listtrans', 'positions', 'editpos',
    'posgroups', 'editpg', 'tx_edit',
    'importform',
);
list($action, $actionval) = $Request->getAction($expected, Config::get('adm_def_view'));

switch ($action) {
case 'notify':      // Force-send expiration reminders
    if (!empty($Request->getArray('delitem'))) {
        Membership::notifyExpiration($Request->getArray('delitem'), true);
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?listmembers');
    break;
case 'genmembernum':
case 'regenbutton_x':
case 'regenbutton':
    // Generate membership numbers for all members
    // Only if configured and valid data is received in delitem variable.
    $view = 'listmembers';
    if (
        Config::get('use_mem_number') != MemberNumber::AUTOGEN ||
        empty($Request->getArray('delitem'))
    ) {
        break;
    }

    MemberNumber::regen($Request->getArray('delitem'));
    echo COM_refresh(Config::get('admin_url') . '/index.php?' . $view);
    break;

case 'importusers':
    if (isset($Request['frm_group'])) {
        $msg = Membership\Util\Importers\glFusion::do_import(
            $Request->getInt('frm_group'),
            $Request->getInt('plan_id'),
            $Request->getString('exp')
        );
    } else {
        $msg = $LANG_MEMBERSHIP['no_import_grp'];
    }
    COM_setMsg($msg);
    echo COM_refresh(Config::get('admin_url') . '/index.php?importform');
    break;

case 'tx_save':
    $Txn = new Transaction($Request->getInt('tx_id'));
    if ($Txn->save($Request)) {
        if ($Request->getInt('renewing')) {
            $M = new Membership($Txn->getMemUid());
            if (!$M->isNew()) {
                $M->Renew($Txn);
            }
        }
        echo COM_refresh(Config::get('admin_url') . '/index.php?listtrans');
    } else {
        // Failed validation, re-display the editing form.
        COM_setMsg($Txn->getErrors(true), 'error', true);
        $content .= Menu::Admin('listtrans');
        $content .= $Txn->edit();
        $view = 'none';
    }
    break;

case 'quickrenew':
    $uid = $Request->getInt('mem_uid');
    if ($uid > 1) {
        $M = new Membership($uid);
        if (!$M->isNew()) {
            $Txn = new Transaction;
            $Txn->withGateway($Request->getString('mem_pmttype'))
                ->withUid($uid)
                ->withDoneBy((int)$_USER['uid'])
                ->withPlanId($Request->getString('mem_planid', $M->getPlanID()))
                ->withAmount($Request->getFloat('mem_pmtamt', 0))
                ->withTxnId($Request->getString('mem_pmtdesc'));
            // If a quickrenewal form is submitted, but the "not a renewal"
            //  checkbox is // checked, then this is just a manual transaction
            //  to be saved.
            if ($Request->getInt('mem_pmtnorenew')) {
                $Txn->withExpiration($M->getExpires()); // no change to expiration
                $status = $Txn->Save();
            } else {
                $status = $M->Renew($Txn);
            }
        }
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?editmember=' . $uid);
    break;

case 'savemember':
    // Call plugin API function to save the membership info, if changed.
    plugin_user_changed_membership($Request->getInt('mem_uid'));
    echo COM_refresh(Config::get('admin_url') . '/index.php?listmembers');
    break;

case 'createmember':
    // Create a new member from the membership form.
    // Creates separate member and transaction records and does not
    // calculate the expiration date, using the supplied date instead.
    $M = new Membership($Request->getInt('mem_uid'));
    if ($M->isNew()) {
        $M->setVars($Request);
        if (Config::get('use_mem_number') == MemberNumber::AUTOGEN) {
            // New member, apply membership number if configured
            $M->setMemNumber(MemberNumber::create($Request->getInt('mem_uid')));
        }
        $M->Save();

        $Txn = new Transaction;
        $Txn->withGateway($Request->getString('mem_pmttype', $LANG_MEMBERSHIP['manual_entry']))
            ->withUid($Request->getInt('mem_uid'))
            ->withAmount($Request->getFloat('mem_pmtamt'))
            ->withPlanId($Request->getInt('mem_plan_id'))
            ->withExpiration($Request->getString('mem_expires'))
            ->withTxnId($Request->getString('pmt_pmtdesc', $LANG_MEMBERSHIP['manual_entry']))
            ->Save();
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?listmembers');
    break;

case 'deletebutton_x':
case 'deletebutton':
    if (is_array($Request->getArray('delitem', NULL))) {
        foreach ($Reqeuest->getArray('delitem') as $mem_uid) {
            $status = Membership::Delete($mem_uid);
        }
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?listmembers');
    exit;

case 'renewbutton_x':
case 'renewbutton':
    if (is_array($Request->getArray('delitem', NULL))) {
        foreach ($Request->getArray('delitem') as $mem_uid) {
            $M = new Membership($mem_uid);
            $M->Renew();
        }
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?listmembers');
    exit;
    break;

case 'deleteplan':
    $plan_id = $Request->getInt('plan_id');
    $xfer_plan = $Request->getInt('xfer_plan');
    if (!empty($plan_id)) {
        Plan::Delete($plan_id, $xfer_plan);
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?listplans');
    break;

case 'saveplan':
    $P = new Plan($Request->getInt('plan_id'));
    $status = $P->Save($Request);
    if ($status == true) {
        echo COM_refresh(Config::get('admin_url') . '/index.php?listplans');
    } else {
        $content .= Menu::Admin('editplan');
        $content .= $P->PrintErrors($LANG_MEMBERSHIP['error_saving']);
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'savepg':
    $P = new PosGroup($Request->getInt('ppg_id'));
    $status = $P->Save($Request);
    if ($status == true) {
        echo COM_refresh(Config::get('admin_url') . '/index.php?posgroups');
        exit;
    } else {
        // Redisplay the edit form in case of error, keeping $_POST vars
        $content .= Menu::Admin('editpg');
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'delpg':
    $pg_id = (int)$actionval;
    if ($pg_id > 0 && PosGroup::Delete($pg_id)) {
        // noop
    } else {
        COM_setMsg($LANG_MEMBERSHIP['admin_error_occurred'], 'error');
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?posgroups');
    exit;
    break;

case 'saveposition':
    $P = new Position($Request->getInt('pos_id'));
    $status = $P->Save($Request);
    if ($status == true) {
        echo COM_refresh(Config::get('admin_url') . '/index.php?positions');
        exit;
    } else {
        // Redisplay the edit form in case of error, keeping $_POST vars
        $content .= Menu::Admin('editpos');
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'reorderpg':
    $id = $Request->getInt('id');
    $where = $actionval;
    if ($id > 0 && $where != '') {
        $msg = PosGroup::Move($id, $where);
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?posgroups');
    break;

case 'reorderpos':
    $type = $Request->getString('type');
    $id = $Request->getInt('id');
    $where = $actionval;
    if ($type != '' && $id > 0 && $where != '') {
        $msg = Position::Move($id, $type, $where);
    }
    echo COM_refresh(Config::get('admin_url') . '/index.php?positions');
    break;

case 'deletepos':
    $P = new Position($actionval);
    $P->Delete();
    echo COM_refresh(Config::get('admin_url') . '/index.php?positions');
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
    $plan_sel = Plan::optionList();
    $grp_options = COM_optionList($_TABLES['groups'], 'grp_id,grp_name', 1);
    $LT->set_var(array(
        'frm_grp_options' => $grp_options,
        'plan_sel'      => $plan_sel,
        'mem_admin_url' => Config::get('admin_url'),
    ) );
    $LT->parse('import_form','form');
    $content .= $LT->finish($LT->get_var('import_form'));
    break;

case 'tx_edit':
    $Txn = new Transaction((int)$actionval);
    $content .= Menu::Admin('listtrans');
    $content .= $Txn->edit();
    break;

case 'editmember':
    $M = new Membership($actionval);
    $showexp = isset($Request['showexp']) ? '?showexp' : '';
    $content .= Menu::Admin();
    $content .= $M->Editform(Config::get('admin_url') . '/index.php' . $showexp);
    break;

case 'editplan':
    $P = new Plan($actionval);
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
    $content .= COM_createLink(
        glFusion\FieldList::button(array(
            'style' => 'success',
            'text' => $LANG_MEMBERSHIP['new_member'],
        ) ),
        Config::get('admin_url') . '/index.php?editmember=0'
    );
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
    $content .= Transaction::adminList();
    break;

case 'posgroups':
    if (isset($Request['delbutton_x']) && is_array($Request->getArray('delitem', NULL))) {
        // Delete some checked attributes
        foreach ($Request->getArray('delitem') as $id) {
            PosGroup::Delete($id);
        }
    }
    $content .= Menu::Admin($view);
    $content .= Menu::adminPositions($view);
    $content .= PosGroup::adminList();
    break;

case 'positions':
    if (isset($Request['delbutton_x']) && is_array($Request->getArray('delitem'))) {
        // Delete some checked attributes
        foreach ($Request->getArray('delitem') as $id) {
            $P = new Position($id);
            $P->Delete();
        }
    }
    $content .= Menu::Admin($view);
    $content .= Menu::adminPositions($view);
    $content .= Position::adminList();
    break;

case 'none':
    // when content is set by the action
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
