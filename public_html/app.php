<?php
/**
*   Membership Application.
*   Calls on the Forms plugin to provide a membership application
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner
*   @package    membership
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion libraries */
require_once '../lib-common.php';

// No application available for anonymous users
if (COM_isAnonUser()) COM_404();

USES_membership_functions();

// Clean $_POST and $_GET, in case magic_quotes_gpc is set
if (GVERSION < '1.3.0') {
    $_POST = LGLIB_stripslashes($_POST);
    $_GET = LGLIB_stripslashes($_GET);
}
$msg = '';
$expected = array(
    'prt', 'view', 'edit',
    'saveapp', 'cancelapp',
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

if (isset($_GET['uid']) && MEMBERSHIP_isManager()) {
    $uid = (int)$_GET['uid'];
    $_CONF_MEMBERSHIP['view_app'] = MEMBERSHIP_APP_ALLACCESS;
} else {
    $uid = (int)$_USER['uid'];
}

switch ($action) {
case 'saveapp':
    // Calls the Profile plugin to save the updated application.
    // If a user is editing their own app, and a purchase url is included,
    // then redirect to that url upon saving.
    if (!MEMBERSHIP_isManager()) $_POST['mem_uid'] = $_USER['uid'];
    $args = array(
        'uid'   => $_POST['mem_uid'],
        'data'  => $_POST,
    );
    $status = LGLIB_invokeService('profile', 'saveData', $args,
        $output, $svc_msg);
    if ($status == PLG_RET_OK) {
        LGLIB_storeMessage(array(
            'message' => $LANG_MEMBERSHIP['your_info_updated'],
        ) );
        if ($_POST['mem_uid'] == $_USER['uid'] && !empty($_POST['purch_url'])) {
            // only redirect members to purchase, not admins.
            USES_membership_class_membership();
            $M = new Membership();
            if ($M->canPurchase()) {
                echo COM_refresh($_POST['purch_url']);
                exit;
            }
        }
        $view = 'view';
    } else {
        // If an error occurred during saving, go back to editing.
        $view = 'edit';
    }
    break;
    
default:
    $view = $action;
    break;
}

switch ($view) {
case 'prt':
    // Create a printable view of the application
    $content .= displayApp($uid);
    if (empty($content)) COM_404();
    else echo $content;
    exit;
    break;

case 'view':
default:
    // Display the application within the normal glFusion site.
    //$content .= displayApp($uid);
    USES_membership_class_app();
    $content .= MembershipApp::Display();
    if (!empty($content)) {
        $content .= '<hr /><p>Click <a href="'.MEMBERSHIP_PI_URL . '/app.php?edit">here</a> to update your profile. Some fields can be updated only by an administrator.</p>';
        break;
    }   // else, if content is empty, an app wasn't found so fall through.
case 'edit':
    $status = LGLIB_invokeService('profile', 'renderForm',
                array('uid'=>$uid), $output, $svc_msg);
    if ($status == PLG_RET_OK && !empty($output)) {
        $T = new Template(MEMBERSHIP_PI_PATH . '/templates');
        $T->set_file('app', 'app_form.thtml');
        $T->set_var(array(
            'mem_uid'       => $uid,
            'purch_url'     => MEMBERSHIP_PI_URL . '/index.php',
            'profile_fields' => $output,
        ) );
        $T->parse('output', 'app');
        $content .= $T->finish($T->get_var('output'));
    }
    break;
}

$display = MEMBERSHIP_siteHeader();
$display .= LGLIB_showAllMessages(true);
if (!empty($msg))
    $display .= COM_showMessage($msg, $_CONF_MEMBERSHIP['pi_name']);
$display .= $content;
$display .= MEMBERSHIP_siteFooter();
echo $display;


function displayApp($uid = 0)
{
    global $_USER;

    USES_membership_class_app();
    $content = MembershipApp::Display($uid);
    if (empty($content)) COM_404();
    else return $content;
}

?>
