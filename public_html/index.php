<?php
/**
 * Public entry point for the Membership plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2022 Lee Garner
 * @package     membership
 * @version     v1.0.0
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
use Membership\Config;
use Membership\Models\Request;

$content = '';
$expected = array(
    'saveapp', 'cancelapp',
    'prt', 'app', 'view', 'editapp', 'list', 'list1', 'pmtform', 'detail',
);
$Request = Request::getInstance();
list($action, $actionval) = $Request->getAction($expected, 'list');

if (isset($Request['uid']) && MEMBERSHIP_isManager()) {
    $uid = $Request->getInt('uid');
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
    $status = Membership\App::getInstance()->Save($Request);
    if ($status == PLG_RET_OK) {
        $purch_url = $Request->getString('purch_url');
        COM_setMsg($LANG_MEMBERSHIP['your_info_updated'], 'success');
        if ($Request->getInt('mem_uid') == $_USER['uid'] && !empty($purch_url)) {
            if (!empty($Request->getString('app_membership_type'))) {
                $url_extra = '&amp;plan_id=' . $Request->getInt('app_membership_type');
            } else {
                $url_extra = '';
            }
            // only redirect members to purchase, not admins.
            $M = new Membership\Membership();
            if ($M->canPurchase() == Membership\Membership::CANPURCHASE) {
                echo COM_refresh($purch_url . $url_extra);
                exit;
            }
            if ($M->getExpires() > Membership\Dates::Today()) {
                COM_setMsg(
                    sprintf($LANG_MEMBERSHIP['you_expire'], $M->getPlan()->getPlanID(), $M->getExpires()),
                    'success'
                );
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
    if (!empty($Request->getInt('plan_id'))) {
        $P = new Membership\Plan($Request->getInt('plan_id'));
        if ($P->getPlanID() == '') {
            $content .= COM_showMessageText($LANG_MEMBERSHIP['err_plan_id']);
            $content .= Membership\Plan::listPlans();
        } elseif ($P->hasErrors()) {
            $content .= COM_showMessageText($P->PrintErrors(), '', true);
        } else {
            $content .= $P->Detail();
        }
    } else {
        $content .= Membership\Plan::listPlans();
    }
    break;

case 'app':
case 'view':
    // Display the application within the normal glFusion site.
    $content .= Membership\App::getInstance($uid)->Display();
    if (!empty($content)) {
        $content .= sprintf(
            $LANG_MEMBERSHIP['click_to_update_app'],
            Config::get('url') . '/app.php?editapp',
        );
        break;
    }   // else, if content is empty, an app wasn't found so fall through.
case 'editapp':
    if (!COM_isAnonUser()) {
        $F = Membership\App::getInstance($uid);
        if (!$F->isValidForm()) {
            glFusion\Log\Log::write(
                Config::PI_NAME, Log::ERROR,
                "Membership: Application form invalid - " . print_r($F,true)
            );
            COM_404();
            exit;
        }
        $content .= $F->Edit();
    } else {
        $content .= SEC_loginRequiredForm();
    }
    break;

case 'prt':
    // Create a printable view of the application
    $content .= Membership\App::getInstance($uid)->Display();
    if (empty($content)){
        COM_404();
    } else {
        echo $content;
        exit;
    }
    break;

case 'pmtform':
    $M = Membership\Membership::getInstance();
    $P = Membership\Plan::getInstance($Request->getInt('plan_id'));
    if (!$P->isNew() && $P->canPurchase()) {
        $T = new Template(Config::get('pi_path') . 'templates');
        $T->set_file('pmt', 'pmt_form.thtml');
        $price_actual = $P->Price($M->isNew(), 'actual');
        if (Config::get('ena_checkpay') == 2) {
            $fee = $P->Fee();
            $price_total = $price_actual + $fee;
        } else {
            $price_total = $price_actual;
            $fee = 0;
        }

        $T->set_var(array(
            'member_name'   => COM_getDisplayName($uid),
            'member_username' => $_USER['username'],
            'mem_number'    => $M->getMemNumber(),
            'plan_name'     => $P->getShortName(),
            'price_total'   => sprintf('%4.2f', $price_total),
            'price_actual'  => sprintf('%4.2f', $price_actual),
            'pmt_fee'       => $fee > 0 ? sprintf('%4.2f', $fee) : '',
            'currency'      => $P->getCurrency(),
            'make_payable'  => sprintf(
                $LANG_MEMBERSHIP['make_payable'],
                Config::get('payable_to')
            ),
            'remit_to'      => Config::get('remit_to'),
            'site_name'     => $_CONF['site_name'],
            'site_slogan'   => $_CONF['site_slogan'],
            // language string included here to allow html
            'pmt_instructions' => $LANG_MEMBERSHIP['pmt_instructions'],
            'ena_checkpay'  => Config::get('ena_checkpay'),
        ) );
        $T->parse('output', 'pmt');
        $content = $T->finish($T->get_var('output'));
        echo $content;
        exit;
    } else {
        COM_404();
    }
    break;

case 'list1':
    // Show the plan list when coming from the app submission
    $show_plan = $Request->getInt('plan_id');
    $content .= Membership\Plan::listPlans($show_plan);
    break;

case 'list':
default:
    // Show the plan list via direct entry.
    $content .= Membership\Plan::listPlans();
    break;
}

$display = Membership\Menu::siteHeader($pageTitle);
$display .= $content;
$display .= Membership\Menu::siteFooter();
echo $display;
exit;

