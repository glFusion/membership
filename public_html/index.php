<?php
/**
*   Public entry point for the Membership plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner
*   @package    subscription
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/
namespace Membership;

/** Import core glFusion libraries */
require_once '../lib-common.php';

// If plugin is installed but not enabled, display an error and exit gracefully
if (!in_array('membership', $_PLUGINS)) {
    COM_404();
}

USES_membership_functions();

$content = '';
$expected = array(
    'saveapp', 'cancelapp',
    'prt', 'app', 'view', 'editapp', 'list', 'list1', 'pmtform', 'detail',
);
$action = '';
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

if (empty($action)) {
    if ($_CONF_MEMBERSHIP['require_app'] > MEMBERSHIP_APP_DISABLED) {
        $action = 'editapp';
    } else {
        $action = 'list';
    }
}

if (isset($_GET['uid']) && MEMBERSHIP_isManager()) {
    $uid = (int)$_GET['uid'];
    $_CONF_MEMBERSHIP['view_app'] = MEMBERSHIP_APP_ALLACCESS;
} else {
    $uid = (int)$_USER['uid'];
}

$pageTitle = $LANG_MEMBESHIP['plans'];  // Set basic page title
$allow_purchase = false;
$have_app = false;

switch ($action) {
case 'cancelapp':
    COM_refresh($_CONF['site_url']);
    exit;
case 'saveapp':
    USES_membership_class_app();
    $status = App::Save();
    if ($status == PLG_RET_OK) {
        LGLIB_storeMessage(array(
                'message' => $LANG_MEMBERSHIP['your_info_updated'],
        ) );
        if ($_POST['mem_uid'] == $_USER['uid'] && !empty($_POST['purch_url'])) {
            if (!empty($_POST['app_membership_type'])) {
                $url_extra = '&amp;plan_id=' . urlencode($_POST['app_membership_type']);
            } else {
                $url_extra = '';
            }
            // only redirect members to purchase, not admins.
            USES_membership_class_membership();
            $M = new Membership();
            if ($M->canPurchase() == MEMBERSHIP_CANPURCHASE) {
                echo COM_refresh($_POST['purch_url'] . $url_extra);
                exit;
            }
            if ($M->expires > $_CONF_MEMBERSHIP['today']) {
                LGLIB_storeMessage(array(
                    'message' => sprintf($LANG_MEMBERSHIP['you_expire'],
                            $M->Plan->plan_id, $M->expires),
                    'persist' =>  true
                ) );
            }
        }
        $view = 'app';
    } else {
        // If an error occurred during saving, go back to editing.
        $view = 'editapp';
    }
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'detail':
    if (!empty($_GET['plan_id'])) {
        USES_membership_class_plan();
        $P = new Plan($_GET['plan_id']);
        if ($P->plan_id == '') {
            $content .= COM_showMessageText($LANG_MEMBERSHIP['err_plan_id']);
            $content .= MEMBERSHIP_PlanList();
        } elseif ($P->hasErrors()) {
            $content .= COM_showMessageText($P->PrintErrors(), '', true);
        } else {
            $content .= $P->Detail();
        }
    } else {
        $content .= MEMBERSHIP_PlanList();
    }
    break;

case 'app':
case 'view':
    // Display the application within the normal glFusion site.
    USES_membership_class_app();
    $content .= App::Display($uid);
    if (!empty($content)) {
        $content .= '<hr /><p>Click <a href="'.MEMBERSHIP_PI_URL . '/index.php?edit">here</a> to update your profile. Some fields can be updated only by an administrator.</p>';
        break;
    }   // else, if content is empty, an app wasn't found so fall through.
case 'editapp':
    USES_membership_class_app();
    if (!COM_isAnonUser()) {
        $content .= App::Edit($uid);
    } else {
        LGLIB_storeMessage(array(
            'message' => $LANG_MEMBERSHIP['must_login'],
            'persist' => true
        ) );
        $content .= SEC_loginRequiredForm();
    }
    break;

case 'pmtform':
    USES_membership_class_membership();
    USES_membership_class_plan();
    $M = new Membership();
    $P = new Plan($_GET['plan_id']);
    if (!$P->isNew) {
        $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
        $T->set_file('pmt', 'pmt_form.thtml');
        $price_actual = $P->Price($M->isNew, 'actual');
        if ($_CONF_MEMBERSHIP['ena_checkpay'] == 2) {
            $fee = $P->Fee();
            $price_total = $price_actual + $fee;
        } else {
            $price_total = $price_actual;
            $fee = 0;
        }

        $T->set_var(array(
            'member_name'   => COM_getDisplayName($uid),
            'member_username' => $_USER['username'],
            'plan_name'     => $P->name,
            'price_total'   => sprintf('%4.2f', $price_total),
            'price_actual'  => sprintf('%4.2f', $price_actual),
            'pmt_fee'       => $fee > 0 ? sprintf('%4.2f', $fee) : '',
            'currency'      => $P->getCurrency(),
            'make_payable'  => sprintf($LANG_MEMBERSHIP['make_payable'], 
                    $_CONF_MEMBERSHIP['payable_to']),
            'remit_to'      => $_CONF_MEMBERSHIP['remit_to'],
            'site_name'     => $_CONF['site_name'],
            'site_slogan'   => $_CONF['site_slogan'],
            // language string included here to allow html
            'pmt_instructions' => $LANG_MEMBERSHIP['pmt_instructions'],
            'ena_checkpay'  => $_CONF_MEMBERSHIP['ena_checkpay'],
        ) );
        $T->parse('output', 'pmt');
        $content = $T->finish($T->get_var('output'));
        echo $content;
        exit;
    }
    break;

case 'list1':
    // Show the plan list when coming from the app submission
    $allow_purchase = true;
    $have_app = true;
    $show_plan = isset($_GET['plan_id']) ? $_GET['plan_id'] : '';
    $content .= MEMBERSHIP_PlanList($allow_purchase, $have_app, $show_plan);
    break;
case 'list':
default:
    // Show the plan list via direct entry.
    $allow_purchase = $_CONF_MEMBERSHIP['require_app'] < MEMBERSHIP_APP_REQUIRED ? true : false;
    $have_app = false;
    $show_plan = '';
    $content .= MEMBERSHIP_PlanList($allow_purchase, $have_app, $show_plan);
    break;
}

$display = MEMBERSHIP_siteHeader($pageTitle);
$display .= LGLIB_showAllMessages();
$display .= $content;
$display .= MEMBERSHIP_siteFooter();
echo $display;
exit;

?>
