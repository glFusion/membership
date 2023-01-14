<?php
/**
 * Import memberships from other user groups.
 * Supports importing from glFusion groups and subsciptions.
 * When importing from the Subscription plugin, the expiration is set to the
 * current subscription expiration unless overridden.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion libraries */
require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
use glFusion\Database\Database;
use glFusion\Log\Log;
use Membership\Config;
use Membership\Models\Request;

// Make sure both plugins are installed and enabled
if (!in_array('membership', $_PLUGINS) || !in_array('subscription', $_PLUGINS)) {
    COM_404();
}

// Only let admin users access this page
if (!MEMBERSHIP_isManager()) {
    // Someone is trying to illegally access this page
    Membership\Logger::System("Someone has tried to illegally access the Membership Admin page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR",1);
    COM_404();
    exit;
}

$Request = Request::getInstance();
$action = '';
$content = '';
$txt = '';
$expected = array(
    // Actions to perform
    'do_import',
    // Views to display
    'import_form',
);
foreach($expected as $provided) {
    if (isset($Request[$provided])) {
        $action = $provided;
        $actionval = $Request->getString($provided);
        break;
    }
}

switch ($action) {
case 'do_import':
    if (empty($Request->getString('plan'))) {
        COM_setMsg("A membership plan is required.");
        echo COM_refresh(Config::get('admin_url') . '/import.php');
    } else {
        switch ($Request->getString('import_type')) {
        case 'subscription':
            $sub_plan_id = $Request->getString('from_subscription');
            $txt = Membership\Util\Importers\Subscription::do_import(
                $sub_plan_id,
                $Request->getString('plan'),
                $Request->getString('expiration')
            );
            break;
        case 'glfusion':
            $gl_grp_id = $Request->getInt('from_glfusion');
            $txt = Membership\Util\Importers\glFusion::do_import(
                $gl_grp_id,
                $Request->getString('plan'),
                $Request->getString('expiration')
            );
            break;
        }
    }
    // Fall through to show the form with the output text below it.

case 'import_form':
default:
    $T = new Template(Config::get('pi_path') . '/templates/admin/');
    $T->set_file(array(
        'form' => 'import_form.thtml',
        'tips' => '../tooltipster.thtml',
    ) );
    $T->set_var(array(
        'plan_sel' => COM_optionList($_TABLES['membership_plans'], 'plan_id,short_name', '', 1),
        'glfusion_opts' => COM_optionList($_TABLES['groups'], 'grp_id,grp_name', '', 1),
        'doc_url' => MEMBERSHIP_getDocURL('import.html', $_CONF['language']),
        'output_text' => $txt,
    ) );
    if (isset($_PLUGIN_INFO['subscription'])) {
        $T->set_var(array(
            'subscription_opts' => COM_optionList($_TABLES['subscr_products'], 'item_id,short_description', '', 1),
        ) );
    }
    $T->parse('tooltipster_js', 'tips');
    $T->parse('output', 'form');
    $content .= $T->finish ($T->get_var('output'));
    break;
}
$output = \Membership\Menu::siteHeader();
$T = new Template(Config::get('pi_path') . 'templates');
$T->set_file('page', 'admin_header.thtml');
$T->set_var(array(
    'header'    => $LANG_MEMBERSHIP['admin_title'],
    'version'   => Config::get('pi_version'),
) );
$T->parse('output','page');
$output .= $T->finish($T->get_var('output'));
$output .= \Membership\Menu::Admin('import');
$output .= $content;
$output .= \Membership\Menu::siteFooter();
echo $output;

