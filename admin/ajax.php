<?php
//  $Id: ajax.php 25 2009-12-03 17:36:43Z root $
/**
*   Common admistrative AJAX functions.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2011 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.0.4
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Include required glFusion common functions */
require_once '../../../lib-common.php';

// This is for administrators only.  It's called by Javascript,
// so don't try to display a message
if (!MEMBERSHIP_isManager()) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the classifieds admin ajax function.");
    exit;
}

switch ($_GET['action']) {
case 'remlinkuser':
    USES_membership_class_link();
    MemberLink::RemLink($_GET['uid1'], $_GET['uid2']);
    break;

case 'addlinkuser':
    USES_membership_class_link();
    MemberLink::AddLink($_GET['uid1'], $_GET['uid2']);
    break;

case 'emancipate':
    USES_membership_class_link();
    MemberLink::Emancipate($_GET['uid1']);
    break;

case 'toggle':
    switch ($_GET['component']) {
    case 'enabled':

        switch ($_GET['type']) {
        case 'plan':
            USES_membership_class_plan();
            $newval = MembershipPlan::toggleEnabled($_REQUEST['oldval'], $_REQUEST['id']);
            break;

        case 'position':
            USES_membership_class_position();
            $newval = MemPosition::toggle($_REQUEST['oldval'], $_GET['component'], $_REQUEST['id']);
            break;

         default:
            exit;
        }
        break;

    case 'show_vacant':
        USES_membership_class_position();
        $newval = MemPosition::toggle($_REQUEST['oldval'], $_GET['component'], $_REQUEST['id']);
        break;

    default:
        exit;
    }

    $result = array(
            'newval' => $newval,
            'id' => $_GET['id'],
            'type' => $_GET['type'],
            'component' => $_GET['component'],
            'baseurl' => $_CONF['site_url'],
    );
    $result = json_encode($result);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    //A date in the past
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    echo $result;
    break;

}

?>
