<?php
/**
 * Membership Application.
 * Calls on the Forms plugin to provide a membership application
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

// No application available for anonymous users
if (COM_isAnonUser()) COM_404();

use Membership\Config;
use Membership\Models\Request;

$Request = Request::getInstance();

$msg = '';
$content = '';
$expected = array(
    'prt', 'view', 'edit',
    'saveapp', 'cancelapp',
);
$action = 'edit';
foreach($expected as $provided) {
    if (isset($Request[$provided])) {
        $action = $provided;
        $actionval = $Request[$provided];
        break;
    }
}

if (isset($Request['uid']) && MEMBERSHIP_isManager()) {
    $uid = $Request->getInt('uid');
} else {
    $uid = (int)$_USER['uid'];
}

switch ($action) {
case 'saveapp':
    // Calls the Profile plugin to save the updated application.
    // If a user is editing their own app, and a purchase url is included,
    // then redirect to that url upon saving.
    if (!MEMBERSHIP_isManager()) {
        $Request['mem_uid'] = $_USER['uid'];
    }
    break;

default:
    $view = $action;
    break;
}

switch ($view) {
case 'prt':
    // Create a printable view of the application
    $content = \Membership\App::getInstance($uid)->Display();
    if (empty($content)) {
        COM_404();
    } else {
        echo $content;
    }
    exit;
    break;

case 'view':
default:
    // Display the application within the normal glFusion site.
    $content .= \Membership\App::getInstance($uid)->Display();
    if (!empty($content)) {
        $content .= sprintf(
            $LANG_MEMBERSHIP['click_to_update_app'],
            Config::get('url') . '/app.php?editapp',
        );
        break;
    }   // else, if content is empty, an app wasn't found so fall through.
case 'edit':
    $content = \Membership\App::getInstance($uid)->Edit();
    break;
}

$display = \Membership\Menu::siteHeader();
if (!empty($msg)) {
    $display .= COM_showMessage($msg, Config::PI_NAME);
}
$display .= $content;
$display .= \Membership\Menu::siteFooter();
echo $display;

