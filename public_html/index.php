<?php
/**
 * Public entry point for the Membership plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012 Lee Garner
 * @package     subscription
 * @version     v0.0.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

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
    if (\Membership\App::getInstance($_USER['uid'])->Validate() > 0) {
    //if ($_CONF_MEMBERSHIP['require_app'] > MEMBERSHIP_APP_DISABLED) {
        $action = 'editapp';
    } else {
        $action = 'list';
    }
}

if (isset($_GET['uid']) && MEMBERSHIP_isManager()) {
    $uid = (int)$_GET['uid'];
    //$_CONF_MEMBERSHIP['view_app'] = MEMBERSHIP_APP_ALLACCESS;
} else {
    $uid = (int)$_USER['uid'];
}

$pageTitle = $LANG_MEMBERSHIP['plans'];  // Set basic page title
$allow_purchase = false;
$have_app = false;

switch ($action) {
case 'cancelapp':
    COM_refresh($_CONF['site_url']);
    exit;
case 'saveapp':
    $status = \Membership\App::getInstance()->Save();
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
            $M = new \Membership\Membership();
            if ($M->canPurchase() == MEMBERSHIP_CANPURCHASE) {
                echo COM_refresh($_POST['purch_url'] . $url_extra);
                exit;
            }
            if ($M->expires > MEMBERSHIP_today()) {
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
        $P = new \Membership\Plan($_GET['plan_id']);
        if ($P->plan_id == '') {
            $content .= COM_showMessageText($LANG_MEMBERSHIP['err_plan_id']);
            $content .= \Membership\Plan::listPlans();
        } elseif ($P->hasErrors()) {
            $content .= COM_showMessageText($P->PrintErrors(), '', true);
        } else {
            $content .= $P->Detail();
        }
    } else {
        $content .= \Membership\Plan::listPlans();
    }
    break;

case 'app':
case 'view':
    // Display the application within the normal glFusion site.
    $content .= \Membership\App::getInstance($uid)->Display();
    if (!empty($content)) {
        $content .= '<hr /><p>Click <a href="'.MEMBERSHIP_PI_URL . '/index.php?edit">here</a> to update your profile. Some fields can be updated only by an administrator.</p>';
        break;
    }   // else, if content is empty, an app wasn't found so fall through.
case 'editapp':
    if (!COM_isAnonUser()) {
        $content .= \Membership\App::getInstance($uid)->Edit();
    } else {
        $content .= SEC_loginRequiredForm();
    }
    break;

case 'pmtform':
    $M = \Membership\Membership::getInstance();
    $P = \Membership\Plan::getInstance($_GET['plan_id']);
    if (!$P->isNew) {
        $T = new Template(MEMBERSHIP_PI_PATH . '/templates');
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
            'mem_number'    => $M->mem_number,
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
    $show_plan = isset($_GET['plan_id']) ? $_GET['plan_id'] : '';
    $content .= \Membership\Plan::listPlans($show_plan);
    break;

case 'list':
default:
    // Show the plan list via direct entry.
    $content .= \Membership\Plan::listPlans();
    break;
}

$display = \Membership\siteHeader($pageTitle);
$display .= LGLIB_showAllMessages();
$display .= $content;
$display .= \Membership\siteFooter();
echo $display;
exit;

?>
