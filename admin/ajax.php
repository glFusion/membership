<?php
/**
 * Common admistrative AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.3.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion common functions */
require_once '../../../lib-common.php';

// This is for administrators only.  It's called by Javascript,
// so don't try to display a message
if (!MEMBERSHIP_isManager()) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the classifieds admin ajax function.");
    exit;
}

use Membership\Plan;
use Membership\Position;

$Request = \Membership\Models\Request::getInstance();
$result = array(
    'status' => 0,
    'statusMessage' => 'Undefined',
);

switch ($Request->getString('action')) {
case 'toggle':
    $component = $Request->getString('component');
    $type = $Request->getString('type');
    $oldval = $Request->getInt('oldval');
    switch ($component) {
    case 'enabled':
        switch ($Request->getString('type')) {
        case 'plan':
            $id = $Request->getInt('id');
            $newval = Plan::toggleEnabled($oldval, $id);
            break;

        case 'position':
            $id = $Request->getInt('id');
            $newval = Position::toggle($oldval, $component, $id);
            break;

         default:
            exit;
        }
        break;

    case 'show_vacant':
        $id = $Request->getInt('id');
        $newval = Position::toggle($oldval, $component, $id);
        break;

    default:
        exit;
    }

    $result = array(
        'newval'    => $newval,
        'id'        => $id,
        'type'      => $type,
        'component' => $component,
        'statusMessage' => $newval != $oldval ? $LANG_MEMBERSHIP['item_updated'] :
                $LANG_MEMBERSHIP['item_nochange'],
    );
    break;

case 'pos_orderby_opts':
    $result = array(
        'options' => Position::getOrderbyOptions(
            $Request->getInt('pg_id'),
            $Request->getSring('orderby'),
        )
    );
    break;
}
$result = json_encode($result);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
//A date in the past
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo $result;

