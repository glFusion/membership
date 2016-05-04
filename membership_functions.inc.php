<?php
/**
*   Plugin-specific functions for the Membership plugin
*   Load by calling USES_membership_functions()
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Show the site header, with or without left blocks according to config.
*
*   @see    COM_siteHeader()
*   @param  string  $subject    Text for page title (ad title, etc)
*   @param  string  $meta       Other meta info
*   @return string              HTML for site header
*/
function MEMBERSHIP_siteHeader($subject='', $meta='')
{
    global $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

    $retval = '';

    $title = $LANG_MEMBERSHIP['blocktitle'];
    if ($subject != '')
        $title = $subject . ' : ' . $title;

    switch($_CONF_MEMBERSHIP['displayblocks']) {
    case 2:     // right only
    case 0:     // none
        $retval .= COM_siteHeader('none', $title, $meta);
        break;

    case 1:     // left only
    case 3:     // both
    default :
        $retval .= COM_siteHeader('menu', $title, $meta);
        break;
    }

    return $retval;

}


/**
*   Show the site footer, with or without right blocks according to config.
*
*   @see    COM_siteFooter()
*   @return string              HTML for site footer
*/
function MEMBERSHIP_siteFooter()
{
    global $_CONF_MEMBERSHIP;

    $retval = '';

    switch($_CONF_MEMBERSHIP['displayblocks']) {
    case 2 : // right only
    case 3 : // left and right
        $retval .= COM_siteFooter(true);
        break;

    case 0: // none
    case 1: // left only
    default :
        $retval .= COM_siteFooter();
        break;
    }

    return $retval;

}


/**
*   Display the membership plans available.
*   Supports autotags in the plan_list.thtml template.
*
*   @param  boolean $allow_purchase True to display payment buttons
*   @param  boolean $have_app       True if the app has just been updated
*   @param  string  $show_plan      A single plan_id to show (selected on app)
*   @return string      HTML for product catalog.
*/
function MEMBERSHIP_PlanList($allow_purchase = true, $have_app = false, $show_plan = '')
{
    global $_TABLES, $_CONF, $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP,
            $_USER, $_PLUGINS, $_IMAGE_TYPE, $_GROUPS;

    $T = new Template(MEMBERSHIP_PI_PATH . '/templates');
    $T->set_file('planlist', 'plan_list.thtml');

    $custom = array();  // Holder for custom attributes
    $options = array();

    $sql_groups = array();
    foreach ($_GROUPS as $name=>$gid) {
        $sql_groups[] = $gid;
    }
    if (!empty($sql_groups)) {
        $sql_groups = implode(',', $sql_groups);
        $sql_groups = " AND access IN ($sql_groups)";
    } else {
        $sql_groups = '';
    }
    $sql = "SELECT plan_id
            FROM {$_TABLES['membership_plans']}
            WHERE enabled = 1 $sql_groups";
    if (!empty($show_plan)) {
        $sql .= " AND plan_id = '" . DB_escapeString($show_plan) . "'";
    }
    $result = DB_query($sql);
    if (!$result || DB_numRows($result) < 1) {
        $T->parse('output', 'planlist');
        $retval = $T->finish($T->get_var('output', 'planlist'));
        $retval .= '<p />' . $LANG_MEMBERSHIP['no_plans_avail'];
        return $retval;
    }

    USES_membership_class_membership();
    USES_membership_class_plan();
    $P = new MembershipPlan();
    $M = new Membership();

    if (COM_isAnonUser()) {
        // Anonymous must log in to purchase
        //$T->set_var('you_expire', $LANG_MEMBERSHIP['must_login']);
        //$login_url = "#\" onclick=\"Popup.showModal('loginform',null,null,{'screenColor':'#999999','screenOpacity':.6,'className':'piMembershipLoginForm'});return false;\"";
        $login_url = '#" onclick="document.getElementById(\'loginform\').style.display=\'block\';';
        $T->set_var('login_msg', sprintf($LANG_MEMBERSHIP['must_login'],
            $_CONF['site_url'] . '/users.php?mode=new', $login_url));
        /*$T->set_var('login_msg', sprintf($LANG_MEMBERSHIP['must_login'],
            $_CONF['site_url'] . '/users.php?mode=new',
            '#" onclick="document.getElementById(\'loginform\').style.display=\'block\';'));*/
        $T->set_var('exp_msg_class', 'alert');
        $T->set_var('login_form', SEC_loginform());
    } else {
        if ($M->isNew) {
            // New member, no expiration message
            $T->set_var('you_expire', '');
        } elseif ($M->expires >= $_CONF_MEMBERSHIP['today']) {
            // Let current members know when they expire
            $T->set_var('you_expire', sprintf($LANG_MEMBERSHIP['you_expire'],
                $M->planDescription(), $M->expires));
            if ($_CONF_MEMBERSHIP['early_renewal'] > 0) {
                $T->set_var('early_renewal', sprintf($LANG_MEMBERSHIP['renew_within'],
                    $_CONF_MEMBERSHIP['early_renewal']));
            }
            $T->set_var('exp_msg_class', 'info');
        }
        if ($_CONF_MEMBERSHIP['require_app'] > MEMBERSHIP_APP_DISABLED) {
            if ($_CONF_MEMBERSHIP['require_app'] == MEMBERSHIP_APP_OPTIONAL) {
                $T->set_var('app_msg',
                    sprintf($LANG_MEMBERSHIP['please_complete_app'], 
                            MEMBERSHIP_PI_URL . '/index.php?editapp'));
            } elseif ($_CONF_MEMBERSHIP['require_app'] == MEMBERSHIP_APP_REQUIRED
                    && !$have_app) {
                $T->set_var('app_msg',
                    sprintf($LANG_MEMBERSHIP['plan_list_app_footer'],
                            MEMBERSHIP_PI_URL . '/index.php?editapp'));
            }
            // Offer a link to return to update the application
            $T->set_var('footer', $LANG_MEMBERSHIP['return_to_edit']);
        }
    }

    $status = LGLIB_invokeService('paypal', 'getCurrency', array(),
                $currency, $svc_msg);
    if (empty($currency)) $currency = 'USD';
    $lang_price = $LANG_MEMBERSHIP['price'];

    $T->set_block('planlist', 'PlanBlock', 'PBlock');
    while ($A = DB_fetchArray($result, false)) {
        $P->Read($A['plan_id']);
        $description = $P->description;
        $price = $P->Price($M->isNew(), 'actual');
        $fee = $P->Fee();
        $price_total = $price + $fee;
        $buttons = '';
        if ($allow_purchase) {
            switch($M->CanPurchase()) {
            case MEMBERSHIP_CANPURCHASE:
                $exp_ts = strtotime($M->expires);
                $exp_format = strftime($_CONF['shortdate'], $exp_ts);
                $output = $P->MakeButton($price_total, $M->isNew(), MEMBERSHIP_PI_URL);
                if (!empty($output))
                    $buttons = implode('&nbsp;&nbsp;', $output);
                break;
            case MEMBERSHIP_NEED_APP:
                $buttons = sprintf($LANG_MEMBERSHIP['app_required'], MEMBERSHIP_PI_URL . '/app.php');
                break;
            default:
                $exp_format = '';
                $buttons = '';
            }
        }
        $T->set_var(array(
            'plan_id'   => $P->plan_id,
            'name'      => $P->name,
            'description' => PLG_replacetags($description),
            'exp_date'  => $exp_format,
            'price'     => COM_numberFormat($price_total, 2),
            'price_actual' => COM_numberFormat($price, 2),
            'fee' => $fee > 0 ? COM_numberFormat($fee, 2) : '',
            'encrypted' => '',
            'currency'  => $currency,
            'purchase_btn' => $buttons,
            'lang_price' => $lang_price,
        ) );

        $display .= $T->parse('PBlock', 'PlanBlock', true);
    }

    $T->parse('output', 'planlist');
    return PLG_replacetags($T->finish($T->get_var('output', 'planlist')));
}

?>
