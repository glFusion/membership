<?php
/**
*   Common admistrative AJAX functions.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2009-2011 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.0
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
$result = array(
    'status' => 0,
    'statusMessage' => 'Undefined',
);
switch ($_POST['action']) {
case 'toggle':
    switch ($_POST['component']) {
    case 'enabled':

        switch ($_POST['type']) {
        case 'plan':
            $newval = \Membership\Plan::toggleEnabled($_POST['oldval'], $_POST['id']);
            break;

        case 'position':
            $newval = \Membership\Position::toggle($_POST['oldval'], $_POST['component'], $_POST['id']);
            break;

         default:
            exit;
        }
        break;

    case 'show_vacant':
        $newval = \Membership\Position::toggle($_POST['oldval'], $_POST['component'], $_POST['id']);
        break;

    default:
        exit;
    }

    $result = array(
        'newval'    => $newval,
        'id'        => $_POST['id'],
        'type'      => $_POST['type'],
        'component' => $_POST['component'],
        'statusMessage' => $newval != $_POST['oldval'] ? $LANG_MEMBERSHIP['item_updated'] :
                $LANG_MEMBERSHIP['item_nochange'],
    );
    break;

case 'pos_orderby_opts':
    $result = array(
        'options' => Membership\Position::getOrderbyOptions($_POST['pg_id'], $_POST['orderby']),
    );
    break;
}
$result = json_encode($result);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
//A date in the past
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
echo $result;

?>
