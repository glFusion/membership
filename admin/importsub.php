<?php
/**
*   Import subscription records into the Membership plugin.
*   - Membership expirations are set to the subscription expiration
*   - Subscription::Cancel is first called to remove group membership, in case
*       the Membership plugin uses a different group
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion libraries */
require_once '../../../lib-common.php';
require_once '../../auth.inc.php';

// Make sure both plugins are installed and enabled
if (!in_array('membership', $_PLUGINS) || !in_array('subscription', $_PLUGINS)) {
    COM_404();
}

// Only let admin users access this page
if (!MEMBERSHIP_isAdmin()) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to illegally access the Membership Admin page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR",1);
    COM_404();
}

// Import administration functions
//USES_lib_admin();
USES_membership_functions();

// Set view and action variables.  We use $action for things to do, and
// $view for the page to show.  $mode is often set by glFusion functions,
// so we'll check for it and see if we should use it, but by using $action
// and $view we don't tend to conflict with glFusion's $mode.
$action = '';
$content = '';
$expected = array(
    // Actions to perform
    'do_import',
    // Views to display
    'import_form',
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

switch ($action) {
case 'do_import':
    $plans = $_POST['sub'];
    $res = DB_query("SELECT id, item_id, uid, expiration
        FROM {$_TABLES['subscr_subscriptions']}");
    USES_membership_class_membership();
    USES_subscription_class_subscription();
    while ($A = DB_fetchArray($res, false)) {
        $content .= "Converting {$A['item_id']} for {$A['uid']} to {$plans[$A['item_id']]}<br />";
        // Cancel the subscription, which will remove the group membership.
        // It should be added back right away by the membership.
        Subscription::Cancel($A['id'], true);
        $M = new Membership($A['uid']);
        $M->plan_id = $plans[$A['item_id']];
        $M->expires = $A['expiration'];
        if ($_CONF_MEMBERSHIP['use_mem_num'] == 2) {
            // Generate a membership number
            $M->mem_number = Membership::createMemberNumber($uid);
        }
        $M->Save();
    }
    $view = 'none';
    break;

default:
    $view = $action;
    break;
}

// Select the page to display
switch ($view) {
case 'import_form':
default:
    $content .= '<h1>Import Subscriptions</h1>'.
        '<p>This function will import records from the Subscription plugin into the Membership plugin, and will remove the old Subscription records.</p>' . LB;
    $content .= '<p><span class="alert">Be sure that you have a good backup of your database!</span></p>' . LB ;
    $content .= '<p>For each Subscription type shown, select the Membership Plan that will take its place. Multiple Subscription types can be consolidated into a single Membership plan.</p><hr/>' . LB;
    $content .= '<form id="sub_import" action="' . MEMBERSHIP_ADMIN_URL .
        '/importsub.php" method="post">';
    $content .= '<table border="0" width="80%"><tr><th>Subscription Plan</th>
<th>Membership Plan</th></tr>' . LB;
    $mres = DB_query("SELECT plan_id FROM {$_TABLES['membership_plans']}");
    $m_opts = '';
    while ($A = DB_fetchArray($mres, false)) {
        $m_opts .= '<option value="' . $A['plan_id'] . '">' . $A['plan_id'] . '</option>';
    }
    $res = DB_query("SELECT item_id,name FROM {$_TABLES['subscr_products']}");
    while ($A = DB_fetchArray($res, false)) {
        $content .= '<tr><td>' . $A['name'] . '</td><td><select name="sub[' .
            $A['item_id'] . ']">' . $m_opts . '</select></td></tr>' . LB;
    }
    $content .= '</table>';
    $content .= '<input type="submit" name="do_import" value="Submit" />';
    $content .= '</form>';
    break;

case 'none':
    break;
}
$output = MEMBERSHIP_siteHeader();
$output .= $content;
$output .= MEMBERSHIP_siteFooter();
echo $output;

?>
