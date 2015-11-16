<?php
/**
*   Entry point to administration functions for the Forms plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2015 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*              GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion libraries */
require_once '../../../lib-common.php';
require_once '../../auth.inc.php';

// Make sure the plugin is installed and enabled
if (!in_array('membership', $_PLUGINS)) {
    COM_404();
}

$isAdmin = MEMBERSHIP_isManager() ? true : false;

// Only let admin users access this page
if (!$isAdmin) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to illegally access the Membership Admin page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR",1);
    $display = COM_siteHeader();
    $display .= COM_startBlock($LANG_PL00['access_denied']);
    $display .= $LANG_DQ00['access_denied_msg'];
    $display .= COM_endBlock();
    $display .= COM_siteFooter(true);
    echo $display;
    exit;
}

// Import administration functions
USES_lib_admin();
USES_membership_functions();
USES_membership_class_plan();
// This is used in several user list functions
USES_lglib_class_nameparser();

$content = '';

// Set view and action variables.  We use $action for things to do, and
// $view for the page to show.  $mode is often set by glFusion functions,
// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$action = $_CONF_MEMBERSHIP['adm_def_view'];
$expected = array(
    // Actions to perform
    'saveplan', 'deleteplan', 'renewmember', 'savemember',
    'renewbutton_x', 'deletebutton_x', 'renewform', 'saveposition',
    'reorderpos', 'importusers',
    // Views to display
    'editplan', 'listplans', 'listmembers', 'editmember', 'stats',
    'listtrans', 'positions',  'editpos', 'importform',
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
case 'importusers':
    require_once MEMBERSHIP_PI_PATH . '/import_members.php';
    $view = 'importform';
    $import_text .= MEMBERSHIP_import();
    break;

case 'quickrenew':
    USES_membership_class_membership();
    $M = new Membership($_POST['mem_uid']);
    $status = $M->Add($uid, $M->Plan->plan_id, 0);
    if ($status !== false) {
        $M->AddTrans('by admin', $_POST['mem_pmtamt']);
    }
    return $status == true ? PLG_RET_OK : PLG_RET_ERROR;

case 'savemember':
    // Call plugin API function to save the membership info, if changed.
    plugin_user_changed_membership($_POST['mem_uid']);
    echo COM_refresh(MEMBERSHIP_ADMIN_URL . '/index.php?listmembers');
    exit;
    break;

case 'deletebutton_x':
    if (is_array($_POST['delitem'])) {
        USES_membership_class_membership();
        foreach ($_POST['delitem'] as $mem_uid) {
            $status = Membership::Delete($mem_uid);
        }
    }
    echo COM_refresh(MEMBERSHIP_ADMIN_URL . '/index.php?listmembers');
    exit;

case 'renewbutton_x':
    if (is_array($_POST['delitem'])) {
        USES_membership_class_membership();
        foreach ($_POST['delitem'] as $mem_uid) {
            $M = new Membership($mem_uid);
            if ($M->isNew) continue;
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
        MembershipPlan::Delete($plan_id, $xfer_plan);
    }
    $view = 'listplans';
    break;

case 'saveplan':
    $plan_id = isset($_POST['old_plan_id']) ? $_POST['old_plan_id'] : '';
    $P = new MembershipPlan($plan_id);
    $status = $P->Save($_POST);
    if ($status == true) {
        $view = 'listplans';
    } else {
        $content .= MEMBERSHIP_adminMenu('editplan', '');
        $content .= $P->PrintErrors($LANG_MEMBERSHIP['error_saving']);
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'saveposition':
    $pos_id = isset($_POST['pos_id']) ? $_POST['pos_id'] : 0;
    USES_membership_class_position();
    $P = new MemPosition($pos_id);
    $status = $P->Save($_POST);
    if ($status == true) {
        $view = 'positions';
    } else {
        $content .= MEMBERSHIP_adminMenu('editpos', '');
        $content .= $P->Edit();
        $view = 'none';
    }
    break;

case 'reorderpos':
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $id = isset($_GET['id']) ? $_GET['id'] : 0;
    $where = isset($_GET['where']) ? $_GET['where'] : '';
    if ($type != '' && $id > 0 && $where != '') {
        USES_membership_class_position();
        $msg = MemPosition::Move($id, $type, $where);
    }
    $view = 'positions';
    break;

    
/*case 'update':
    // Save or update a membership record
    USES_membership_class_member();
    $M = new Member($id);
    $M->Save($_POST);
    $view = 'list';
    break;

case 'delete':
    // Delete a membership record.
    $id = (int)$_REQUEST['id'];     // May come in via $_GET
    DB_delete($_TABLES['membership_members'], 'id', $id);
    break;
*/
default:
    $view = $action;
    break;
}

// Select the page to display
switch ($view) {
case 'importform':
    //require_once MEMBERSHIP_PI_PATH . '/import_members.php';
    $content .= MEMBERSHIP_adminMenu('importform', '');
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
    ) );
    $LT->parse('import_form','form');
    $content .= $LT->finish($LT->get_var('import_form'));
    $content .= '<p>' . $import_text . '</p>';
    break;

case 'editmember':
    USES_membership_class_membership();
    $M = new Membership($actionval);
    $showexp = isset($_GET['showexp']) ? '?showexp' : '';
    $content .= MEMBERSHIP_adminMenu('listmembers', '');
    $content .= $M->Editform(MEMBERSHIP_ADMIN_URL . '/index.php' . $showexp);
    break;

case 'editplan':
    $plan_id = isset($_REQUEST['plan_id']) ? $_REQUEST['plan_id'] : '';
    USES_membership_class_plan();
    $P = new MembershipPlan($plan_id);
    $content .= MEMBERSHIP_adminMenu($view, '');
    $content .= $P->Edit();
    break;

case 'editpos':
    USES_membership_class_position();
    $P = new MemPosition($actionval);
    $content .= MEMBERSHIP_adminMenu($view, '');
    $content .= $P->Edit();
    break;

case 'listmembers':
    $content .= MEMBERSHIP_adminMenu($view, '');
    $content .= MEMBERSHIP_listMembers();
    break;

case 'stats':
    $content .= MEMBERSHIP_adminMenu($view, '');
    $content .= MEMBERSHIP_summaryStats();
    break;

case 'none':
    // In case any modes create their own content
    break;

case 'listtrans':
    $content .= MEMBERSHIP_adminMenu($view, '');
    $content .= MEMBERSHIP_listTrans();
    break;

case 'positions':
    if (isset($_POST['delbutton_x']) && is_array($_POST['delitem'])) {
        // Delete some checked attributes
        USES_membership_class_position();
        foreach ($_POST['delitem'] as $id) {
            MemPosition::Delete($id);
        }
    }
    $content .= MEMBERSHIP_adminMenu($view, '');
    $content .= MEMBERSHIP_listPositions();
    break;

case 'listplans':
default:
    $view = 'listplans';
    $content .= MEMBERSHIP_adminMenu($view, '');
    $content .= MEMBERSHIP_listPlans();
    break;

}
$output = MEMBERSHIP_siteHeader();
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
$output .= MEMBERSHIP_siteFooter();
echo $output;


/**
*   Uses lib-admin to list the membership definitions and allow updating.
*
*   @return string HTML for the list
*/
function MEMBERSHIP_listMembers()
{
    global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_MEMBERSHIP, $_IMAGE_TYPE,
        $_CONF_MEMBERSHIP;

    $retval = '';

    $header_arr = array(
        array('text' => $LANG_ADMIN['edit'],
                'field' => 'edit', 'sort' => false),
        array('text' => $LANG_MEMBERSHIP['member_name'],
                'field' => 'fullname', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['linked_accounts'],
                'field' => 'links', 'sort' => false),
        array('text' => $LANG_MEMBERSHIP['plan'],
                'field' => 'plan', 'sort' => false),
        array('text' => $LANG_MEMBERSHIP['expires'],
                'field' => 'mem_expires', 'sort' => true),
    );
    $defsort_arr = array('field' => 'm.mem_expires', 'direction' => 'desc');
    if (isset($_REQUEST['showexp'])) {
        $frmchk = 'checked="checked"';
    } else {
        $frmchk = '';
        $exp_query = "AND m.mem_expires >= '{$_CONF_MEMBERSHIP['dt_end_grace']}'";
    }
    /*$full_name = "IF (u.fullname = '' OR u.fullname IS NULL,
                u.fullname, 
                CONCAT(SUBSTRING_INDEX(u.fullname,' ',-1), ', ',
                    SUBSTRING_INDEX(u.fullname,' ',1))) AS fullname,
                u.fullname AS realfullname";*/
    $query_arr = array('table' => 'membership_members',
        //'sql' => "SELECT m.*, u.username, $full_name, p.name as plan
        'sql' => "SELECT m.*, u.username, u.fullname, p.name as plan
                FROM {$_TABLES['membership_members']} m
                LEFT JOIN {$_TABLES['users']} u
                    ON u.uid = m.mem_uid
                LEFT JOIN {$_TABLES['membership_plans']} p
                    ON p.plan_id = m.mem_plan_id
                WHERE 1=1 $exp_query",
        'query_fields' => array('u.fullname', 'u.email'),
        'default_filter' => ''
    );
    $text_arr = array(
        'has_extras' => true,
        'form_url'  => MEMBERSHIP_ADMIN_URL . '/index.php?listmembers',
    );
    if (isset($_POST['showexp']) || (empty($_POST) && isset($_GET['showexp'])))
        $text_arr['form_url'] .= '&amp;showexp';
    $filter = '<input type="checkbox" name="showexp" ' . $frmchk . 
            ' onclick="javascript:submit();">' . $LANG_MEMBERSHIP['show_expired'] . '?';
    $options = array('chkdelete' => 'true', 'chkfield' => 'mem_uid',
        'chkactions' => '<input name="deletebutton" type="image" src="'
            . $_CONF['layout_url'] . '/images/admin/delete.' . $_IMAGE_TYPE
            . '" style="vertical-align:text-bottom;" title="' . $LANG_ADMIN['delete']
            . '" class="gl_mootip"'
            . ' onclick="return confirm(\'' . $LANG_MEMBERSHIP['q_del_member']
            . '\');" />&nbsp;'
            . $LANG_ADMIN['delete'] . '&nbsp;&nbsp;' .

            '<input name="renewbutton" type="image" src="'
            . MEMBERSHIP_PI_URL . '/images/renew.png'
            . '" style="vertical-align:text-bottom;" title="'
            . $LANG_MEMBERSHIP['renew_all'] 
            . '" class="gl_mootip"' 
            . ' onclick="return confirm(\'' . $LANG_MEMBERSHIP['confirm_renew']
            . '\');" />&nbsp;' . $LANG_MEMBERSHIP['renew'],
    );
     $retval .= ADMIN_list('membership', 'MEMBERSHIP_getField_member', 
                $header_arr, $text_arr, $query_arr, $defsort_arr, $filter, '',
                $options, $form_arr);
    return $retval;
}


/**
*   Uses lib-admin to list the membership definitions and allow updating.
*
*   @return string HTML for the list
*/
function MEMBERSHIP_listPlans()
{
    global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_MEMBERSHIP;

    $retval = '';


    $header_arr = array(
        array('text' => $LANG_ADMIN['edit'],
            'field' => 'edit', 'sort' => false),
        array('text' => 'ID',
            'field' => 'plan_id', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['short_name'],
            'field' => 'name', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['enabled'], 
                'field' => 'enabled', 'sort' => false,
                'align' => 'center'),
    );

    $defsort_arr = array('field' => 'plan_id', 'direction' => 'asc');

    $query_arr = array('table' => 'membership_plans',
        'sql' => "SELECT * 
                FROM {$_TABLES['membership_plans']} ",
        'query_fields' => array('name', 'description'),
        'default_filter' => ''
    );
    $text_arr = array(
        //'has_extras' => true,
        //'form_url'   => MEMBERSHIP_ADMIN_URL . '/index.php',
        'help_url'   => ''
    );

    $retval .= ADMIN_list('membership', 'MEMBERSHIP_getField_plan', 
                $header_arr, $text_arr, $query_arr, $defsort_arr, '', '',
                '', $form_arr);
    return $retval;
}


/**
*   Determine what to display in the admin list for each membership plan.
*
*   @param  string  $fieldname  Name of the field, from database
*   @param  mixed   $fieldvalue Value of the current field
*   @param  array   $A          Array of all name/field pairs
*   @param  array   $icon_arr   Array of system icons
*   @return string              HTML for the field cell
*/
function MEMBERSHIP_getField_plan($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG_ACCESS, $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP;

    $retval = '';

    $pi_admin_url = MEMBERSHIP_ADMIN_URL;
    switch($fieldname) {
    case 'edit':
        $retval = 
            COM_createLink(
                $icon_arr['edit'],
                MEMBERSHIP_ADMIN_URL . '/index.php?editplan=x&amp;plan_id=' .
                $A['plan_id']
            );
        break;

    case 'delete':
        // Deprecated
        if (!MembershipPlan::hasMembers($A['plan_id'])) {
            $retval = COM_createLink(
                "<img src=\"{$_CONF['layout_url']}/images/admin/delete.png\" 
                    height=\"16\" width=\"16\" border=\"0\"
                    onclick=\"return confirm('{$LANG_MEMBERSHIP['q_del_member']}');\"
                    >",
                MEMBERSHIP_ADMIN_URL . '/index.php?deleteplan=x&plan_id=' .
                $A['plan_id']
            );
        } else {
            $retval = '';
        }
       break;

    case 'enabled':
        if ($fieldvalue == 1) {
            $chk = ' checked="checked" ';
            $enabled = 1;
        } else {
            $chk = '';
            $enabled = 0;
        }
        $retval = "<input name=\"{$fieldname}_{$A['plan_id']}\" " .
                "id=\"{$fieldname}_{$A['plan_id']}\" ".
                "type=\"checkbox\" $chk " .
                "onclick='MEMB_toggle(this, \"{$A['plan_id']}\", \"plan\", \"{$fieldname}\", \"{$pi_admin_url}\");' />\n";

        /*$retval = 
                "<input name=\"{$fieldname}_{$A['id']}\" " .
                "type=\"checkbox\" $chk " .
                "onclick='MEMB_toggle(this, \"{$A['plan_id']}\", \"plan\", \"{$fieldname}\", \"{$pi_admin_url}\");' />\n";*/
        break;

    default:
        $retval = $fieldvalue;
        break;
    }

    return $retval;
}


/**
*   Determine what to display in the admin list for each position.
*
*   @param  string  $fieldname  Name of the field, from database
*   @param  mixed   $fieldvalue Value of the current field
*   @param  array   $A          Array of all name/field pairs
*   @param  array   $icon_arr   Array of system icons
*   @return string              HTML for the field cell
*/
function MEMBERSHIP_getField_positions($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG_ACCESS, $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP;

    $retval = '';

    $pi_admin_url = MEMBERSHIP_ADMIN_URL;
    switch($fieldname) {
    case 'editpos':
        $retval = 
            COM_createLink(
                $icon_arr['edit'],
                MEMBERSHIP_ADMIN_URL . '/index.php?editpos=' . $A['id']
            );
        break;

    case 'move':
        $retval = '<a href="' . MEMBERSHIP_ADMIN_URL . '/index.php' . 
            "?type={$A['type']}&reorderpos=x&where=up&id={$A['id']}\">" .
            "<img src=\"" . $_CONF['layout_url'] . 
            '/images/up.png" height="16" width="16" border="0" /></a>'."\n";
        $retval .= '<a href="' . MEMBERSHIP_ADMIN_URL . '/index.php' . 
            "?type={$A['type']}&reorderpos=x&where=down&id={$A['id']}\">" .
            "<img src=\"" . $_CONF['layout_url'] .
            '/images/down.png" height="16" width="16" border="0" /></a>' . "\n";
        break;

    case 'deletepos':
        $retval = COM_createLink(
                "<img src=\"{$_CONF['layout_url']}/images/admin/delete.png\" 
                    height=\"16\" width=\"16\" border=\"0\"
                    onclick=\"return confirm('{$LANG_MEMBERSHIP['q_del_item']}');\"
                    >",
                MEMBERSHIP_ADMIN_URL . '/index.php?deletepos=' . $A['plan_id']
        );
       break;

    case 'type':
        $retval = COM_createLink($fieldvalue, MEMBERSHIP_PI_URL . '/group.php?type=' . $fieldvalue);
        break;

    case 'fullname':
        if ($A['uid'] == 0) {
            $retval = '<i>' . $LANG_MEMBERSHIP['vacant'] . '</i>';
        } else {
            $retval = $fieldvalue;
        }
        break;

    case 'enabled':
    case 'show_vacant':
        if ($fieldvalue == 1) {
            $chk = 'checked="checked"';
            $enabled = 1;
        } else {
            $chk = '';
            $enabled = 0;
        }
        $retval = "<input name=\"{$fieldname}_{$A['id']}\" " .
                "id=\"{$fieldname}_{$A['id']}\" ".
                "type=\"checkbox\" $chk " .
                "onclick='MEMB_toggle(this, \"{$A['id']}\", \"position\", \"{$fieldname}\", \"{$pi_admin_url}\");' />\n";
        break;

    default:
        $retval = $fieldvalue;
        break;
    }

    return $retval;
}


/**
*   Determine what to display in the admin list for each form.
*
*   @param  string  $fieldname  Name of the field, from database
*   @param  mixed   $fieldvalue Value of the current field
*   @param  array   $A          Array of all name/field pairs
*   @param  array   $icon_arr   Array of system icons
*   @return string              HTML for the field cell
*/
function MEMBERSHIP_getField_member($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG_ACCESS, $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP, $_TABLES;

    static $link_names = array();
    $retval = '';
    $pi_admin_url = MEMBERSHIP_ADMIN_URL;

    switch($fieldname) {
    case 'edit':
        $showexp = isset($_POST['showexp']) ? '&amp;showexp' : '';
        $retval = 
            COM_createLink(
                $icon_arr['edit'],
                MEMBERSHIP_ADMIN_URL . '/index.php?editmember=' . $A['mem_uid'] . $showexp
                //"{$_CONF['site_admin_url']}/user.php?edit=x&amp;uid={$A['mem_uid']}"
            );
        break;

    case 'tx_fullname':
        //$retval = COM_createLink($fieldvalue,
        //        $_CONF['site_url'] . '/users.php?mode=profile&uid=' . $A['tx_uid']);
        $retval = COM_createLink($fieldvalue,
                MEMBERSHIP_ADMIN_URL . '/index.php?listtrans&amp;uid=' . $A['tx_uid']);
        break;

    case 'fullname':
        if (!isset($link_names[$A['mem_uid']])) {
            //$link_names[$A['mem_uid']] = MEMBER_CreateNameLink($A['mem_uid'], $A['fullname']);
            $link_names[$A['mem_uid']] = MEMBER_CreateNameLink($A['mem_uid'], $A['fullname']);
        }
        $retval = $link_names[$A['mem_uid']];
        break;

    case 'links':
        $sql = "SELECT uid2 FROM {$_TABLES['membership_links']}
            WHERE uid1={$A['mem_uid']}";
        $res = DB_query($sql, 1);
        $L = array();
        while ($B = DB_fetchArray($res, false)) {
            if (!isset($link_names[$B['uid2']])) {
                $link_names[$B['uid2']] = MEMBER_CreateNameLink($B['uid2']);
            }
            $L[] = $link_names[$B['uid2']];
        }
        if (!empty($L)) {
            $retval = implode('; ', $L);
        }
        break;

    case 'id':
        return $A['id'];
        break;

    case 'mem_expires':
        if ($fieldvalue >= $_CONF_MEMBERSHIP['today']) {
            $status = 'current';
        } elseif ($fieldvalue >= $_CONF_MEMBERSHIP['dt_end_grace']) {
            $status = 'arrears';
        } else {
            $status = 'expired';
        }
        $retval = "<span class=\"member_$status\">{$fieldvalue}</span>";
        break;

    case 'email':
        $retval = empty($fieldvalue) ? '' :
                "<a href=\"mailto:$fieldvalue\">$fieldvalue</a>";
        break;

    case 'tx_by':
        if ($fieldvalue == 0) {
            $retval = $LANG_MEMBERSHIP['system_task'];
        } else {
            $retval = COM_getDisplayName($fieldvalue);
        }
        break;

    case 'tx_txn_id':
        $non_gw = array('', 'cc', 'check', 'cash');
        if (!empty($fieldvalue) && !in_array($A['tx_gw'], $non_gw)) {
            $retval = COM_createLink($fieldvalue, $_CONF['site_admin_url'] .
                '/plugins/paypal/index.php?ipnlog=x&op=single&txn_id=' .
                urlencode($fieldvalue));
        } else {
            $retval = $fieldvalue;
        }
        break;

    /*case 'action':
        $retval = '<select name="action"
            onchange="javascript: document.location.href=\'' .
            MEMBERSHIP_ADMIN_URL . '/index.php?frm_id=' . $A['id'] . 
            '&mode=\'+this.options[this.selectedIndex].value">'. "\n";
        $retval .= '<option value="">--Select Action--</option>'. "\n";
        $retval .= '<option value="preview">Preview</option>'. "\n";
        $retval .= '<option value="results">Results</option>'. "\n";
        $retval .= '<option value="export">Export CSV</option>'. "\n";
        $retval .= "</select>\n";
        break;*/

    default:
        $retval = $fieldvalue;

    }

    return $retval;
}


/**
*   Create the admin menu at the top of the list and form pages.
*
*   @return string      HTML for admin menu section
*/
function MEMBERSHIP_adminMenu($mode='', $help_text = '')
{
    global $_CONF, $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP, $LANG01, $isAdmin;

    $help_text = isset($LANG_MEMBERSHIP['adm_' . $mode]) ?
            $LANG_MEMBERSHIP['adm_' . $mode] : '';

    $menu_arr = array(
        array('url' => MEMBERSHIP_ADMIN_URL . '/index.php?listplans=x',
            'text' => $LANG_MEMBERSHIP['list_plans']),
        array('url' => MEMBERSHIP_ADMIN_URL . '/index.php?listmembers',
            'text' => $LANG_MEMBERSHIP['list_members']),
        array('url' => MEMBERSHIP_ADMIN_URL . '/index.php?listtrans',
            'text' => $LANG_MEMBERSHIP['transactions']),
        array('url' => MEMBERSHIP_ADMIN_URL . '/index.php?stats',
            'text' => $LANG_MEMBERSHIP['member_stats']),
        array('url' => MEMBERSHIP_ADMIN_URL . '/index.php?positions',
            'text' => 'Positions'),
        array('url' => MEMBERSHIP_ADMIN_URL . '/index.php?importform',
            'text' => 'Import'),
        array('url' => $_CONF['site_admin_url'],
            'text' => $LANG01[53]),
    );
    switch($mode) {
    case 'listplans':
        $menu_arr[] = array('url' => MEMBERSHIP_ADMIN_URL . '/index.php?editplan=x',
            'text' => "<span class=\"piMembershipAdminNewItem\">{$LANG_MEMBERSHIP['new_plan']}</span>");
        break;
    case 'positions':
        $menu_arr[] = array('url' => MEMBERSHIP_ADMIN_URL . '/index.php?editpos=0',
            'text' => "<span class=\"piMembershipAdminNewItem\">{$LANG_MEMBERSHIP['new_position']}</span>");
        break;
    }

    $retval = ADMIN_createMenu($menu_arr, $help_text, plugin_geticon_membership());
    return $retval;
}


function MEMBER_CreateNameLink($uid, $fullname='')
{
    global $_CONF;

    if ($fullname == '') {
        $fullname = COM_getDisplayName($uid);
    }
    $fullname = NameParser::LCF($fullname);
        /*$spc = strrpos($fullname, ' ');
        if ($spc > 0) {
            $lname = substr($fullname, $spc + 1);
            $fname = substr($fullname, 0, $spc);
            $fullname = $lname . ', ' . $fname;
        }*/
    //}
    $retval = '<span rel="rel_' . $uid .
        '" onmouseover="MEM_highlight(' . $uid . 
        ',1);" onmouseout="MEM_highlight(' . $uid . ',0);">' .
        COM_createLink($fullname,
        $_CONF['site_url'] . '/users.php?mode=profile&uid=' . $uid)
        . '</span>';
    return $retval;
}


function MEMBERSHIP_summaryStats()
{
    global $_CONF_MEMBERSHIP, $_TABLES;

    // The brute-force way to get summary stats.  There must be a better way.
    $sql = "SELECT DISTINCT(mem_guid), mem_plan_id, mem_expires
            FROM {$_TABLES['membership_members']}
            WHERE mem_expires > '{$_CONF_MEMBERSHIP['dt_end_grace']}'";
//echo $sql;die;
    $rAll = DB_query($sql);
    $stats = array();
    $template = array('current' => 0, 'arrears' => 0);
    while ($A = DB_fetchArray($rAll, false)) {
        if (!isset($stats[$A['mem_plan_id']]))
            $stats[$A['mem_plan_id']] = $template;
        if ($A['mem_expires'] >= $_CONF_MEMBERSHIP['today']) {
            $stats[$A['mem_plan_id']]['current']++;
        } else {
            $stats[$A['mem_plan_id']]['arrears']++;
        }
    }

    $T = new Template(MEMBERSHIP_PI_PATH . '/templates');
    $T->set_file('stats', 'admin_stats.thtml');
    $T->set_block('stats', 'statrow', 'srow');
    $linetotal = 0;
    $tot_current = 0;
    $tot_arrears = 0;
    $gtotal = 0;
    foreach ($stats as $plan_id=>$data) {
        $linetotal = $data['current'] + $data['arrears'];
        $tot_current += $data['current'];
        $tot_arrears += $data['arrears'];
        $gtotal += $linetotal;
        $T->set_var(array(
            'plan'          => $plan_id,
            'num_current'   => $data['current'],
            'num_arrears'   => $data['arrears'],
            'line_total'    => $linetotal,
        ) );
        $T->parse('srow', 'statrow', true);
    }
    $T->set_var(array(
        'tot_current'   => $tot_current,
        'tot_arrears'   => $tot_arrears,
        'grand_total'   => $gtotal,
    ) );
    $T->parse('output', 'stats');   
    return $T->get_var('output');
}


function MEMBERSHIP_listTrans()
{
    global $_TABLES, $LANG_MEMBERSHIP, $_CONF;

    if (isset($_POST['tx_from']) && !empty($_POST['tx_from'])) {
        $from_sql = "AND tx_date >= '" . DB_escapeString($_POST['tx_from']) . "'";
        $tx_from = $_POST['tx_from'];
    } else {
        $tx_from = '';
        $from_sql = '';
    }
    if (isset($_POST['tx_to']) && !empty($_POST['tx_to'])) {
        $to_sql = "AND tx_date <= '" . DB_escapeString($_POST['tx_to']) . "'";
        $tx_to = $_POST['tx_to'];
    } else {
        $tx_to = '';
        $to_sql = '';
    }
    if (isset($_GET['uid']) && !empty($_GET['uid'])) {
        $user_sql = 'AND tx_uid = ' . (int)$_GET['uid'];
    } else {
        $user_sql = '';
    }

    $query_arr = array('table' => 'membership_trans',
        'sql' => "SELECT tx.*, u.fullname as tx_fullname
                FROM {$_TABLES['membership_trans']} tx
                LEFT JOIN {$_TABLES['users']} u
                    ON u.uid = tx.tx_uid
                WHERE 1=1 $from_sql $to_sql $user_sql",
        'query_fields' => array('u.fullname'),
        'default_filter' => ''
    );
    $defsort_arr = array(
        'field' => 'tx_date',
        'direction' => 'DESC',
    );
    $text_arr = array(
        'has_extras' => true,
        'form_url'  => MEMBERSHIP_ADMIN_URL . '/index.php?listtrans',
    );
    $filter = $LANG_MEMBERSHIP['from'] . 
        ': <input id="f_tx_from" type="text" size="10" name="tx_from" value="' .
            $tx_from . '" />' .
          '<img src="' . $_CONF['site_url'] . '/images/datepicker.jpg" 
            id="dtfrom_trigger"
            style="cursor: pointer;"
            alt="Date Selector"
            title="Date Selector"
            />&nbsp;&nbsp;' .
        $LANG_MEMBERSHIP['to'] .
        ': <input id="f_tx_to" type="text" size="10" name="tx_to" value="' .
             $tx_to .'" />' .
          '<img src="' . $_CONF['site_url'] . '/images/datepicker.jpg" 
            id="dtto_trigger"
            style="cursor: pointer;"
            alt="Date Selector"
            title="Date Selector"
            />&nbsp;' .
        '<script type="text/javascript">
    Calendar.setup({
        inputField     :    "f_tx_from",
        ifFormat       :    "%Y-%m-%d",
        showsTime      :    false,
        timeFormat     :    "24",
        button          :   "dtfrom_trigger"
    });
    Calendar.setup({
        inputField     :    "f_tx_to",
        ifFormat       :    "%Y-%m-%d",
        showsTime      :    false,
        timeFormat     :    "24",
        button          :   "dtto_trigger"
    });
    </script>';
    $header_arr = array(
        array('text' => $LANG_MEMBERSHIP['date'],
                'field' => 'tx_date', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['entered_by'],
                'field' => 'tx_by', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['member_name'],
                'field' => 'tx_fullname', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['plan'],
                'field' => 'tx_planid', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['expires'],
                'field' => 'tx_exp', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['pmt_method'],
                'field' => 'tx_gw', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['txn_id'],
                'field' => 'tx_txn_id', 'sort' => true),
    );
    $retval .= ADMIN_list('membership', 'MEMBERSHIP_getField_member', 
                $header_arr, $text_arr, $query_arr, $defsort_arr, $filter, '',
                '', $form_arr);
    return $retval;
}


/**
*   Displays the list of committee and board positions
*
*   @return string  HTML string containing the contents of the ipnlog
*/
function MEMBERSHIP_listPositions()
{
    global $_CONF, $_TABLES, $LANG_MEMBERSHIP, $_USER, $LANG_ADMIN;

    $sql = "SELECT p.*,u.fullname
            FROM {$_TABLES['membership_positions']} p
            LEFT JOIN {$_TABLES['users']} u
            ON u.uid = p.uid 
            WHERE 1=1 ";

    $header_arr = array(
        array('text' => 'ID', 
                'field' => 'id', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['edit'],
                'field' => 'editpos', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_MEMBERSHIP['move'],
                'field' => 'move', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_MEMBERSHIP['enabled'],
                'field' => 'enabled', 'sort' => false,
                'align' => 'center'),
        array('text' => $LANG_MEMBERSHIP['position_type'],
                'field' => 'type', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['description'],
                'field' => 'descr', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['current_user'], 
                'field' => 'fullname', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['order'],
                'field' => 'orderby', 'sort' => true),
        array('text' => $LANG_MEMBERSHIP['show_vacant'],
                'field' => 'show_vacant', 'sort' => true),
        array('text' => $LANG_ADMIN['delete'],
                'field' => 'deletepos', 'sort' => 'false',
                'align' => 'center'),
    );

    $query_arr = array('table' => 'membership_positions',
        'sql' => $sql,
        'query_fields' => array('u.fullname', 'p.descr'),
        'default_filter' => ''
    );
    $defsort_arr = array(
            'field' => 'type,orderby',
            'direction' => 'ASC'
    );
/*
    $filter = '';

    $query_arr = array();

    $text_arr = array(
        'has_extras' => true,
        'form_url' => MEMBERSHIP_ADMIN_URL . '/index.php?attributes=x',
    );

    $options = array('chkdelete' => true, 'chkfield' => 'attr_id');
    if (!isset($_REQUEST['query_limit']))
        $_GET['query_limit'] = 20;
*/
    $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
    $display .= ADMIN_list('membership', 'MEMBERSHIP_getField_positions',
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, '');

    $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
    return $display;

}

?>
