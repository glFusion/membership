<?php
/**
*   Service functions for the Membership plugin.
*   This file provides functions to be called by other plugins, such
*   as the PayPal plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2016 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
*   Get information about a specific item.
*
*   @param  array   $A          Item Info (pi_name, Plan ID, New/Renewal)
*   @param  array   &$output    Array to use for returned product info
*   @param  array   &$svc_msg   Not used
*   @return integer             PLG_RET_status
*/
function service_productinfo_membership($A, &$output, &$svc_msg)
{
    // $A param must be an array:
    //  0 => 'membership'
    //  1 => Plan ID, integer
    //  2 => 'renewal', other/missing = "new"
    if (!is_array($A)) return PLG_RET_ERROR;

    unset($A['gl_svc']);    // not used

    // Create a return array with values to be populated later
    $output = array('product_id' => implode(':', $A),
            'name' => 'Unknown',
            'short_description' => 'Unknown Membership Plan',
            'description'       => '',
            'price' => '0.00',
    );
    $retval = PLG_RET_OK;       // assume response will be OK

    if (isset($A[1]) && !empty($A[1])) {
        $P = new Membership\Plan($A[1]);
        if ($P->plan_id != '') {
            $isnew = isset($A[2]) && $A[2] == 'renewal' ? false : true;
            $output['short_description'] = $P->name;
            $output['name'] = 'Membership, ' . $P->plan_id; 
            $output['description'] = $P->description;
            $output['price'] = $P->price($isnew);
        } else {
            $retval = PLG_RET_ERROR;
        }
    }

    return $retval;
}


/**
*   Handle the purchase of a product via IPN message.
*
*   @param  array   $id     Array of (pi_name, category, item_id)
*   @param  array   $item   Array of item info for this purchase
*   @param  array   $ipn_data    All Paypal data from IPN
*   @return array           Array of item info, for notification
*/
function service_handlePurchase_membership($args, &$output, &$svc_msg)
{
    global $_TABLES, $_CONF_MEMBERSHIP;

    // Called by Paypal IPN, so $args should be an array, but just in case...
    if (!is_array($args)) return PLG_RET_ERROR;

    // Must have an item ID following the plugin name
    $item = $args['item'];
    $ipn_data = $args['ipn_data'];

    $id = explode(':', $item['item_id']);
    // item_id should be 'membership::plan_id', if no plan id return error
    if (!isset($id[1])) {
        return PLG_RET_ERROR;
    }
    // User ID is returned in the 'custom' field, so make sure it's numeric.
    if (is_numeric($ipn_data['custom']['uid']))
        $uid = (int)$ipn_data['custom']['uid'];
    else
        $uid = (int)DB_getItem($_TABLES['users'], 'email', $ipn_data['payer_email']);
    if ($uid < 2) {
        return PLG_RET_ERROR;
    }

    // Retrieve or create a membership record.
    $M = new Membership\Membership($uid);

    if ($M->Plan === NULL && $_CONF_MEMBERSHIP['use_mem_num'] == 2) {
        // New member, apply membership number if configured
        $M->mem_number = Membership\Membership::createMemberNumber($uid);
    }

    if ($M->plan_id != $id[1]) {
        // Changed membership plans
        $M->Plan = new Membership\Plan($id[1]);
    }

    if (!SEC_inGroup($M->Plan->grp_access, $uid)) {
        // Can't purchase a restricted membership
        return PLG_RET_NOACCESS;
    }

    $amount = (float)$ipn_data['pmt_gross'];
    if ($amount < $M->Price()) {    // insufficient funds
        MEMBERSHIP_auditLog('Insufficient funds for membership - ' . $ipn_data['txn_id'], true);
        return PLG_RET_ERROR;
    }

    // Initialize the return array
    $output = array('product_id' => implode(':', $id),
            'name' => $M->Plan->name,
            'short_description' => $M->Plan->name,
            'description' => $M->Plan->name,
            'price' =>  $amount,
            'expiration' => NULL,
            'download' => 0,
            'file' => '',
    );

    MEMBERSHIP_auditLog('Processing membership for ' . COM_getDisplayName($uid) . "($uid), plan {$id[1]}", true);
    if ($uid > 1) {
        $status = $M->Add($uid, $M->Plan->plan_id);
    } else {
        $status = false;
    }
    if ($status !== false) {
        // if purchase went ok, log the transaction and remove any
        // expiration message.
        $M->AddTrans($ipn_data['gw_name'], $ipn_data['pmt_gross'],
            $ipn_data['txn_id'], '', 0);
        // Not needed, added message removal to $M->Save()
        //LGLIB_deleteMessage($uid, MEMBERSHIP_MSG_EXPIRING);
    }
    return $status == true ? PLG_RET_OK : PLG_RET_ERROR;
}


/**
*   Create a filter form and extract query parameters that this plugin
*   is responsible for.
*   'filter' and 'get' elements are returned in $output.
*
*   @param  array   $args       Array of arguments, including $_GET and $_POST
*   @param  mixed   &$output    Pointer to output variables.
*   @param  mixed   &$svc_msg   Service message (not used)
*   @return integer     Result status
*/
function service_profilefilter_membership($args, &$output, &$svc_msg)
{
    global $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP;

    // Non-managers don't get access to view other members' expiration
    if (!MEMBERSHIP_isManager()) return PLG_RET_NOACCESS;

    $opts = array(
        MEMBERSHIP_STATUS_ENABLED => $LANG_MEMBERSHIP['current'],
        MEMBERSHIP_STATUS_ARREARS => $LANG_MEMBERSHIP['arrears'],
        MEMBERSHIP_STATUS_EXPIRED => $LANG_MEMBERSHIP['expired'],
    );
    $output = array();
    // If posted variables are recieved, use them. Otherwise, use GET but only
    // if POST is empty. Otherwise the user may have just unchecked all the
    // options
    if (isset($args['post']['mem_exp_status_flag'])) {
        $exp_stat = $args['post']['mem_exp_status'];
    } elseif (empty($args['post']) && isset($args['get']['mem_exp_status'])) {
        $exp_stat = explode(',', $args['get']['mem_exp_status']);
    } else {
        // Use the default setting if no other options received
        $exp_stat = array();
        if ($_CONF_MEMBERSHIP['prflist_current'] == 1)
            $exp_stat[] = MEMBERSHIP_STATUS_ENABLED;
        if ($_CONF_MEMBERSHIP['prflist_arrears'] == 1)
            $exp_stat[] = MEMBERSHIP_STATUS_ARREARS;
        if ($_CONF_MEMBERSHIP['prflist_expired'] == 1)
            $exp_stat[] = MEMBERSHIP_STATUS_EXPIRED;
    }
    if (!is_array($exp_stat))
        $exp_stat = array();

    $get_parms = array();
    foreach ($opts as $stat=>$txt) {
        if (in_array($stat, $exp_stat)) {
            $sel =  'checked="checked"';
            $get_parms[] = $stat;
        } else {
            $sel = '';
        }
        $output['filter'] .= '<input type="checkbox" name="mem_exp_status[]" value="' .
                $stat . '" ' . $sel . ' />' . $txt . '&nbsp;';
    }
    $output['filter'] .= '<input type="hidden" name="mem_exp_status_flag" value="1" />';
    $output['get'] = 'mem_exp_status=' . implode(',', $get_parms);
    return PLG_RET_OK;
}


/**
*   Get the query element needed when collecting data for the Profile plugin.
*   The $output array contains the field names, the SELECT and JOIN queries,
*   and the search fields for the ADMIN_list function.
*
*   @param  array   $args       Post, Get, incl_exp_stat and incl_user_stat
*   @param  array   &$output    Pointer to output array
*   @param  array   &$svc_msg   Unused
*   @return integer             Status code
*/
function service_profilefields_membership($args, &$output, &$svc_msg)
{
    global $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP, $_TABLES;

    $pi = $_CONF_MEMBERSHIP['pi_name'];
    $plans = $_TABLES['membership_plans'];
    $members = $_TABLES['membership_members'];
    $positions = $_TABLES['membership_positions'];
    $where = '';
    $exp_stat = array();

    // Does not support remote web services, must be local only.
    if ($args['gl_svc'] !== false) return PLG_RET_PERMISSION_DENIED;

    // Include the expiration status, if requested
    if (isset($args['post']['mem_exp_status_flag']))
        $exp_stat = $args['post']['mem_exp_status'];
    elseif (empty($args['post']) && isset($args['get']['mem_exp_status']))
        $exp_stat = explode(',', $args['get']['mem_exp_status']);
    elseif (isset($args['incl_exp_stat'])) {
        if ($args['incl_exp_stat'] && MEMBERSHIP_STATUS_ENABLED)
            $exp_stat[] = MEMBERSHIP_STATUS_ENABLED;
        if ($args['incl_exp_stat'] && MEMBERSHIP_STATUS_ARREARS)
            $exp_stat[] = MEMBERSHIP_STATUS_ARREARS;
        if ($args['incl_exp_stat'] && MEMBERSHIP_STATUS_EXPIRED)
            $exp_stat[] = MEMBERSHIP_STATUS_EXPIRED;
    }
    if (!empty($exp_stat)) {
        foreach ($exp_stat as $stat) {
            $incl_exp_stat += (int)$stat;
        }

        if ($incl_exp_stat > 0 && $incl_exp_stat < 7) {
            // Only create sql if filtering on some expiration status
            $grace = (int)$_CONF_MEMBERSHIP['grace_days'];
            $exp_arr = array();
            if ($incl_exp_stat & MEMBERSHIP_STATUS_ENABLED == MEMBERSHIP_STATUS_ENABLED) {
                $exp_arr[] = "$members.mem_expires >= '{$_CONF_MEMBERSHIP['today']}'";
            }
            if ($incl_exp_stat & MEMBERSHIP_STATUS_ARREARS) {
                
                $exp_arr[] = "($members.mem_expires < '{$_CONF_MEMBERSHIP['today']}'
                    AND $members.mem_expires >= '{$_CONF_MEMBERSHIP['dt_end_grace']}')";
            }
            if ($incl_exp_stat & MEMBERSHIP_STATUS_EXPIRED) {
                $exp_arr[] = "$members.mem_expires < '{$_CONF_MEMBERSHIP['dt_end_grace']}'";
            }
            if (!empty($exp_arr)) {
                $where = "$members.mem_expires > '0000-00-00' AND (" .
                        implode(' OR ', $exp_arr) . ')';
            }
        }
    }

    $output = array(
        'names' => array(
            $pi . '_description' => array(
                'field' => $plans . '.name',
                'text' => $LANG_MEMBERSHIP['short_name'],
            ),
            $pi . '_expires' => array(
                'field' => $members . '.mem_expires',
                'text' => $LANG_MEMBERSHIP['expires'],
                'perm'  => '0',
            ),
            $pi . '_joined' => array(
                'field' => $members . '.mem_joined',
                'text' => $LANG_MEMBERSHIP['joined'],
                'perm'  => '2',
            ),
            $pi . '_membertype' => array(
                'field' => $members . '.mem_plan_id',
                'text' => $LANG_MEMBERSHIP['plan_id'],
            ),
            $pi . '_position' => array(
                'field' => $positions . '.descr',
                'text' => $LANG_MEMBERSHIP['position'],
                'perm'  => '2',
            ),
            $pi . '_membernum' => array(
                'field' => $members . '.mem_number',
                'text' => $LANG_MEMBERSHIP['mem_number'],
                'perm'  => '2',
            ),
        ),

        'query' => "{$members}.mem_expires as {$pi}_expires,
                    {$members}.mem_joined as {$pi}_joined,
                    {$members}.mem_plan_id as {$pi}_membertype,
                    {$members}.mem_status as {$pi}_status,
                    {$members}.mem_number as {$pi}_membernum,
                    {$positions}.descr as {$pi}_position,
                    {$plans}.description AS {$pi}_description",

        'join' => "LEFT JOIN {$members} ON u.uid = {$members}.mem_uid
                LEFT JOIN {$plans} ON {$plans}.plan_id = {$members}.mem_plan_id
                LEFT JOIN {$positions} ON {$positions}.uid = u.uid",

        'where' => $where,

        'search' => array($plans.'.description', $plans.'.name'),

        'f_info' => array(
            $pi . '_expires' => array(
                    'disp_func' => 'membership_profilefield_expires',
            ),
        ),
    );

    return PLG_RET_OK;
}


/**
*   Callback to display the expiration date in profile listings.
*   Same parameters as the normal field display functions.
*   Expects $A['membership_exp_days'] to contain the number of days that this
*   membership has expired, with a negative number indicating that the
*   membership has not yet expired.
*/
function membership_profilefield_expires($fieldname, $fieldvalue, $A, $icon_arr,
    $extras)
{
    global $_CONF_MEMBERSHIP;

    if ($fieldvalue >= $_CONF_MEMBERSHIP['today']) {
        $cls = 'member_current';
    } elseif ($fieldvalue >= $_CONF_MEMBERSHIP['dt_end_grace']) {
        $cls = 'member_arrears';
    } else {
        $cls = 'member_expired';
    }
    return '<span class="' . $cls . '">' . $fieldvalue . '</span>';
}


/**
*   Get the current membership status for a given user
*   Keeps statuses in a static array so calling multiple times won't
*   cause multiple database queries.
*
*   @param  array   $args   Argument array, 'uid' is optional user ID
*   @param  mixed   &$output    Output value, array of status and expiration
*   @param  mixed   &$svc_msg   Not used
*   @return integer Return status. Always OK since $output has default values
*/
function service_status_membership($args, &$output, &$svc_msg)
{
    global $_TABLES, $_USER, $_CONF_MEMBERSHIP;

    static $info = array();
    $uid = isset($args['uid']) ? (int)$args['uid'] : (int)$_USER['uid'];
    if (!isset($info[$uid])) {
        // Create user element and populate with default values
        $info[$uid] = array(
            'status' => MEMBERSHIP_STATUS_DROPPED,
            'joined' => '0000-00-00',
            'expires' => '0000-00-00',
            'plan' => '',
        );
        $res = DB_query("SELECT m.mem_status, m.mem_joined, m.mem_expires,
                            p.name AS plan_name
                        FROM {$_TABLES['membership_members']} m
                        LEFT JOIN {$_TABLES['membership_plans']} p
                            ON p.plan_id = m.mem_plan_id
                        WHERE mem_uid = $uid", 1);
        if ($res && DB_numRows($res) == 1) {
            $A = DB_fetchArray($res, false);
            if ($A['mem_expires'] > $_CONF_MEMBERSHIP['today']) {
                $info[$uid]['status'] = MEMBERSHIP_STATUS_ACTIVE;
            } elseif ($A['mem_expires'] < $_CONF_MEMBERSHIP['dt_end_grace']) {
                $info[$uid]['status'] = MEMBERSHIP_STATUS_EXPIRED;
            } else {
                $info[$uid]['status'] = MEMBERSHIP_STATUS_ARREARS;
            }
            $info[$uid]['joined'] = $A['mem_joined'];
            $info[$uid]['expires'] = $A['mem_expires'];
            $info[$uid]['plan'] = $A['plan_name'];
        }
    }

    $output = $info[$uid];
    return PLG_RET_OK;
}


/**
*   Get the member type strings "Current", "Former", etc for each status
*   Exposes MEMBERSHIP_membertypes() to other plugins.
*   Sets $output to an array of status=>string
*
*   @return integer PLG_RET_OK
*/
function service_membertypes_membership($args, &$output, &$svc_msg)
{
    $output = MEMBERSHIP_membertypes();
    return PLG_RET_OK;
}


/**
*   Find out if a given user is a member, current or in arrears
*   Sets $output to boolean true if the user is an active 
*   member, false if expired, in arrears or non-member.
*
*   @see    service_ismember_membership()
*   @uses   service_status_membership()
*   @return integer Plugin return status
*/
function service_iscurrent_membership($args, &$output, &$svc_msg)
{
    $status = service_status_membership($args, $myout, $svc_msg);
    if ($status != PLG_RET_OK) return $status;

    if ($myout['status'] == MEMBERSHIP_STATUS_ACTIVE) {
        $output = true;
    } else {
        $output = false;
    }
    return PLG_RET_OK;
}


/**
*   Find out if a given user is a member, current or in arrears
*   Sets $output to boolean true if the user is an active or in-arrears
*   member, false if expired or non-member.
*
*   @see    service_iscurrent_membership()
*   @uses   service_status_membership()
*   @return integer Plugin return status
*/
function service_ismember_membership($args, &$output, &$svc_msg)
{
    $status = service_status_membership($args, $myout, $svc_msg);
    if ($status != PLG_RET_OK) return $status;

    if ($myout['status'] == MEMBERSHIP_STATUS_ACTIVE ||
        $myout['status'] == MEMBERSHIP_STATUS_ARREARS) {
        $output = true;
    } else {
        $output = false;
    }
    return PLG_RET_OK;
}


/**
*   Get the mailing list segment or descriptive text for a member status
*   Puts the text in $output as a single string
*
*   @uses   service_status_membership()
*   @return integer PLG_RET_OK
*/
function service_mailingSegment_membership($args, &$output, &$svc_msg)
{
    global $_TABLES;

    // Get the current statuses
    $statuses = MEMBERSHIP_memberstatuses();

    // Set a default return value
    $output = $statuses[MEMBERSHIP_STATUS_DROPPED];
    $uid = 0;

    if (isset($args['email']) && !empty($args['email'])) {
        $uid = (int)DB_getItem($_TABLES['users'], 'uid',
                "email = '" . DB_escapeString($args['email']) . "'");
    } elseif (isset($args['uid']) && $args['uid'] > 1) {
        $uid = (int)$args['uid'];
    }

    if ($uid > 0) {
        $myargs = array('uid' => $uid);
        $code = service_status_membership($myargs, $myout, $svc_msg);
        if ($code == PLG_RET_OK && isset($statuses[$myout['status']])) {
            $output = $statuses[$myout['status']];
        }
    }
    
    return PLG_RET_OK;
}


?>
