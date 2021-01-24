<?php
/**
 * Service functions for the Membership plugin.
 * This file provides functions to be called by other plugins, such
 * as the Shop plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2016 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     0.1.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}

use Membership\Status;
use Membership\Plan;
use Membership\Membership;
use Membership\Logger;
use Membership\Dates;

/**
 * Get information about a specific item.
 *
 * @param   array   $A          Item Info (pi_name, Plan ID, New/Renewal)
 * @param   array   $output     Array reference to use for returned product info
 * @param   array   $svc_msg    Not used
 * @return  integer             PLG_RET_status
 */
function service_productinfo_membership($A, &$output, &$svc_msg)
{
    // $A param must be an array:
    //  'item_id' => array(
    //      0 => Plan ID, integer
    //      1 => 'renewal', other/missing = "new"
    //  ),
    //  'mods' => array(    // optional modifiers
    //      'uid' => user ID
    //  ),
    //  );
    if (!is_array($A) || !isset($A['item_id']) || !is_array($A['item_id'])) return PLG_RET_ERROR;
    unset($A['gl_svc']);    // not used

    $plan_id = $A['item_id'][0];
    $plan_mod = isset($A['item_id'][1]) ? $A['item_id'][1] : '';
    // Create a return array with values to be populated later
    $output = array(
        'product_id'        => 'membership:' . implode(':', $A['item_id']),
        'name'              => 'Unknown',
        'short_description' => 'Unknown Membership Plan',
        'short_dscp'        => 'Unknown Membership Plan',
        'description'       => '',
        'dscp'              => '',
        'price'             => '0.00',
        'fixed_q'           => 1,
        'url'               => '',
        'have_detail_svc' => true,  // Tell Shop to use it's detail page wrapper
    );
    $retval = PLG_RET_OK;       // assume response will be OK

    $P = new Plan($plan_id);
    if ($P->getPlanID() != '') {
        $isnew = $plan_mod == 'renewal' ? false : true;
        $output['short_description'] = $P->getName();
        $output['short_dscp'] = $P->getName();
        $output['name'] = 'Membership, ' . $P->getPlanID();
        $output['description'] = $P->getDscp();
        $output['dscp'] = $P->getDscp();
        $output['price'] = $P->Price($isnew);
    } else {
        $retval = PLG_RET_ERROR;
    }
    return $retval;
}
/**
 * Non-service function to get product information.
 *
 * @see     service_productinfo_membership()
 * @param   array   $args   Array of arguments.
 * @return  array   Array of product information.
 */
function plugin_productinfo_membership($args)
{
    $status = service_productinfo_membership($args, $output, $svc_msg);
    return $output;
}


/**
 * Handle the purchase of a product via IPN message.
 *
 * @param   array   $args       Array of arguments
 * @param   array   $output     Array reference for the output
 * @param   array   $svc_msg    Not used
 * @return  integer             PLG_RET_status
 */
function service_handlePurchase_membership($args, &$output, &$svc_msg)
{
    global $_TABLES, $_CONF_MEMBERSHIP;

    // Called by Shop IPN, so $args should be an array, but just in case...
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
    $M = new Membership($uid);

    if ($M->getPlanID() != $id[1]) {
        // Changed membership plans
        $M->setPlan($id[1]);
    }

    if ($M->getPlan() === NULL && MEMB_getVar($_CONF_MEMBERSHIP, 'use_mem_number', 'integer') == 2) {
        // New member, apply membership number if configured
        $M->setMemNumber(Membership::createMemberNumber($uid));
    }

    if (!SEC_inGroup($M->getPlan()->getGrpAccess(), $uid)) {
        // Can't purchase a restricted membership
        return PLG_RET_NOACCESS;
    }

    $amount = (float)$ipn_data['pmt_gross'];
    if ($amount < $M->Price()) {    // insufficient funds
        Logger::Audit('Insufficient funds for membership - ' . $ipn_data['txn_id'], true);
        return PLG_RET_ERROR;
    }

    // Initialize the return array
    $output = array('product_id' => implode(':', $id),
            'name' => $M->Plan->name,
            'short_description' => $M->getPlan()->getName(),
            'description' => $M->getPlan()->getName(),
            'price' =>  $amount,
            'expiration' => NULL,
            'download' => 0,
            'file' => '',
    );

    Logger::Audit(
        'Processing membership for ' . COM_getDisplayName($uid) . "($uid), plan {$id[1]}", true
    );
    if ($uid > 1) {
        $status = $M->Add($uid, $M->getPlanID());
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
 * Create a filter form and extract query parameters that this plugin is responsible for.
 * 'filter' and 'get' elements are returned in $output.
 *
 * @param   array   $args       Array of arguments, including $_GET and $_POST
 * @param   mixed   &$output    Pointer to output variables.
 * @param   mixed   &$svc_msg   Service message (not used)
 * @return  integer     Result status
 */
function service_profilefilter_membership($args, &$output, &$svc_msg)
{
    global $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP;

    // Non-managers don't get access to view other members' expiration
    if (!MEMBERSHIP_isManager()) return PLG_RET_PERMISSION_DENIED;

    $opts = array(
        Status::ACTIVE => $LANG_MEMBERSHIP['current'],
        Status::ARREARS => $LANG_MEMBERSHIP['arrears'],
        Status::EXPIRED => $LANG_MEMBERSHIP['expired'],
    );

    $output = array();
    // If posted variables are recieved, use them. Otherwise, use GET but only
    // if POST is empty. Otherwise the user may have just unchecked all the
    // options
    if (
        isset($args['post']['mem_exp_status_flag']) &&
        isset($args['post']['mem_exp_status'])
    ) {
        $exp_stat = $args['post']['mem_exp_status'];
    } elseif (empty($args['post']) && isset($args['get']['mem_exp_status'])) {
        $exp_stat = explode(',', $args['get']['mem_exp_status']);
    } elseif (isset($args['exp_stat']) && is_int($args['exp_stat'])) {
        $exp_stat = array();
        foreach ($opts as $key=>$txt) {
            if (($args['exp_stat'] & $key) == $key) {
                $exp_stat[] = $key;
            }
        }
    } else {
        // Use the default setting if no other options received
        $exp_stat = array();
        if ($_CONF_MEMBERSHIP['prflist_current'] == 1)
            $exp_stat[] = Status::ACTIVE;
        if ($_CONF_MEMBERSHIP['prflist_arrears'] == 1)
            $exp_stat[] = Status::ARREARS;
        //if ($_CONF_MEMBERSHIP['prflist_expired'] == 1)
        //    $exp_stat[] = Status::EXPIRED;
    }
    if (!is_array($exp_stat)) {
        $exp_stat = array();
    }

    $get_parms = array();
    $output['filter'] = '';
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
 * Get the query element needed when collecting data for the Profile plugin.
 * The $output array contains the field names, the SELECT and JOIN queries,
 * and the search fields for the ADMIN_list function.
 *
 * @param   array   $args       Post, Get, incl_exp_stat and incl_user_stat
 * @param   array   &$output    Pointer to output array
 * @param   array   &$svc_msg   Unused
 * @return  integer             Status code
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
    $incl_exp_stat = 0;

    // Does not support remote web services, must be local only.
    if ($args['gl_svc'] !== false) {
        return PLG_RET_PERMISSION_DENIED;
    }

    // Include the expiration status, if requested
    if (
        isset($args['post']['mem_exp_status_flag']) &&
        isset($args['post']['mem_exp_status'])
    ) {
        $exp_stat = $args['post']['mem_exp_status'];
    } elseif (empty($args['post']) && isset($args['get']['mem_exp_status'])) {
        $exp_stat = explode(',', $args['get']['mem_exp_status']);
    } elseif (isset($args['incl_exp_stat'])) {
        $exp_stat = array();
        if (is_int($args['incl_exp_stat'])) {
            foreach (array(
                Status::ACTIVE,
                Status::ARREARS,
                Status::EXPIRED,
            ) as $key) {
                if (($args['incl_exp_stat'] & $key) == $key) {
                    $exp_stat[] = $key;
                }
            }
        } elseif (is_array($args['incl_exp_stat'])) {
            if ($args['incl_exp_stat'] && Status::ACTIVE) {
                $exp_stat[] = Status::ACTIVE;
            }
            if ($args['incl_exp_stat'] && Status::ARREARS) {
                $exp_stat[] = Status::ARREARS;
            }
            if ($args['incl_exp_stat'] && Status::EXPIRED) {
                $exp_stat[] = Status::EXPIRED;
            }
        }
    }
    if (!empty($exp_stat)) {
        foreach ($exp_stat as $stat) {
            $incl_exp_stat += (int)$stat;
        }

        if ($incl_exp_stat > 0 && $incl_exp_stat < 7) {
            // Only create sql if filtering on some expiration status
            $grace = (int)$_CONF_MEMBERSHIP['grace_days'];
            $exp_arr = array();
            if ($incl_exp_stat & Status::ACTIVE == Status::ACTIVE) {
                $exp_arr[] = "$members.mem_expires >= '" . Dates::Today() . "'";
            }
            if ($incl_exp_stat & Status::ARREARS) {
                $exp_arr[] = "($members.mem_expires < '" . Dates::Today() . "'
                    AND $members.mem_expires >= '" . Dates::expGraceEnded() . "')";
            }
            if ($incl_exp_stat & Status::EXPIRED) {
                $exp_arr[] = "$members.mem_expires < '" . Dates::expGraceEnded() . "'";
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
                'title' => $LANG_MEMBERSHIP['short_name'],
            ),
            $pi . '_expires' => array(
                'field' => $members . '.mem_expires',
                'title' => $LANG_MEMBERSHIP['expires'],
                'perm'  => '0',
            ),
            $pi . '_joined' => array(
                'field' => $members . '.mem_joined',
                'title' => $LANG_MEMBERSHIP['joined'],
                'perm'  => '2',
            ),
            $pi . '_membertype' => array(
                'field' => $members . '.mem_plan_id',
                'title' => $LANG_MEMBERSHIP['plan'],
            ),
            $pi . '_position' => array(
                'field' => $positions . '.descr',
                'title' => $LANG_MEMBERSHIP['position'],
                'perm'  => '2',
            ),
            $pi . '_membernum' => array(
                'field' => $members . '.mem_number',
                'title' => $LANG_MEMBERSHIP['mem_number'],
                'perm'  => '2',
            ),
        ),

        'query' => "{$members}.mem_expires as {$pi}_expires,
                    {$members}.mem_joined as {$pi}_joined,
                    {$members}.mem_plan_id as {$pi}_membertype,
                    {$members}.mem_status as {$pi}_status,
                    {$members}.mem_number as {$pi}_membernum,
                    {$plans}.description AS {$pi}_description,
                    (SELECT group_concat(' ', {$positions}.descr)
                        FROM {$positions}
                        WHERE {$positions}.uid = u.uid
                        AND $positions.in_lists = 1) as {$pi}_position",

        'join' => "LEFT JOIN {$members} ON u.uid = {$members}.mem_uid
                LEFT JOIN {$plans} ON {$plans}.plan_id = {$members}.mem_plan_id",
                //LEFT JOIN {$positions} ON {$positions}.uid = u.uid AND {$positions}.in_lists = 1",

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
 * Callback to display the expiration date in profile listings.
 * Same parameters as the normal field display functions.
 * Expects $A['membership_exp_days'] to contain the number of days that this
 * membership has expired, with a negative number indicating that the
 * membership has not yet expired.
 *
 * @param   string  $fieldname  Name of field
 * @param   mixed   $fieldvalue Value of field
 * @param   array   $A          Array of all field name=>value
 * @param   array   $icon_arr   Array of icons
 * @param   array   $extras     Possible extra pass-through values
 * @return  string      HTML for field display
 */
function membership_profilefield_expires($fieldname, $fieldvalue, $A, $icon_arr,
    $extras)
{
    global $_CONF_MEMBERSHIP;

    if ($fieldvalue >= Dates::Today()) {
        $cls = 'member_current';
    } elseif ($fieldvalue >= Dates::expGraceEnded()) {
        $cls = 'member_arrears';
    } else {
        $cls = 'member_expired';
    }
    return '<span class="' . $cls . '">' . $fieldvalue . '</span>';
}


/**
 * Get the current membership status for a given user.
 * Keeps statuses in a static array so calling multiple times won't
 * cause multiple database queries.
 *
 * @param   array   $args       Argument array, 'uid' is optional user ID
 * @param   mixed   $output     Output value, array of status and expiration
 * @param   mixed   $svc_msg    Not used
 * @return  integer     Return status. Always OK since $output has default values
 */
function service_status_membership($args, &$output, &$svc_msg)
{
    global $_TABLES, $_USER, $_CONF_MEMBERSHIP;

    static $info = array();
    $uid = isset($args['uid']) ? (int)$args['uid'] : (int)$_USER['uid'];
    if (!isset($info[$uid])) {
        // Create user element and populate with default values
        $info[$uid] = array(
            'status' => Status::DROPPED,
            'joined' => '0000-00-00',
            'expires' => '0000-00-00',
            'plan' => '',
        );
        $Mem = Membership::getInstance($uid);
        if (!$Mem->isNew()) {
            if ($Mem->isCurrent()) {
                $info[$uid]['status'] = Status::ACTIVE;
            } elseif ($Mem->isExpired()) {
                $info[$uid]['status'] = Status::EXPIRED;
            } else {
                $info[$uid]['status'] = Status::ARREARS;
            }
            $info[$uid]['joined'] = $Mem->getJoined();
            $info[$uid]['expires'] = $Mem->getExpires();
            $info[$uid]['plan'] = $Mem->getPlan()->getName();
        }
    }

    $output = $info[$uid];
    return PLG_RET_OK;
}


/**
 * Find out if a given user is a member, current or in arrears.
 * Sets $output to boolean true if the user is an active
 * member, false if expired, in arrears or non-member.
 *
 * @see     service_ismember_membership()
 * @uses    service_status_membership()
 * @param   array   $args       Argument array
 * @param   mixed   $output     Output value, array of status and expiration
 * @param   mixed   $svc_msg    Not used
 * @return  integer Plugin return status
 */
function service_iscurrent_membership($args, &$output, &$svc_msg)
{
    $status = service_status_membership($args, $myout, $svc_msg);
    if ($status != PLG_RET_OK) return $status;

    if ($myout['status'] == Status::ACTIVE) {
        $output = true;
    } else {
        $output = false;
    }
    return PLG_RET_OK;
}


/**
 * Find out if a given user is a member, current or in arrears.
 * Sets $output to boolean true if the user is an active or in-arrears
 * member, false if expired or non-member.
 *
 * @see     service_iscurrent_membership()
 * @uses    service_status_membership()
 * @param   array   $args       Argument array
 * @param   mixed   $output     Output value, array of status and expiration
 * @param   mixed   $svc_msg    Not used
 * @return  integer Plugin return status
 */
function service_ismember_membership($args, &$output, &$svc_msg)
{
    $status = service_status_membership($args, $myout, $svc_msg);
    if ($status != PLG_RET_OK) return $status;

    if (
        $myout['status'] == Status::ACTIVE ||
        $myout['status'] == Status::ARREARS
    ) {
        $output = true;
    } else {
        $output = false;
    }
    return PLG_RET_OK;
}


/**
 * Get the mailing list segment or descriptive text for a member status.
 * Puts the text in $output as a single string.
 *
 * @uses    service_status_membership()
 * @param   array   $args       Argument array
 * @param   mixed   $output     Output value, array of status and expiration
 * @param   mixed   $svc_msg    Not used
 * @return  integer PLG_RET_OK
 */
function service_mailingSegment_membership($args, &$output, &$svc_msg)
{
    global $_TABLES;

    // Get the current statuses
    //$statuses = MEMBERSHIP_memberstatuses();

    // Set a default return value
    $output = Status::getSegment(Status::DROPPED);
    $uid = 0;

    if (isset($args['email']) && !empty($args['email'])) {
        $uid = (int)DB_getItem(
            $_TABLES['users'],
            'uid',
            "email = '" . DB_escapeString($args['email']) . "'"
        );
    } elseif (isset($args['uid']) && $args['uid'] > 1) {
        $uid = (int)$args['uid'];
    }

    if ($uid > 0) {
        $output = plugin_getiteminfo_membership('membership:' . $uid, 'id,merge_fields');
        /*
        $myargs = array('uid' => $uid);
        $code = service_status_membership($myargs, $myout, $svc_msg);
        if ($code == PLG_RET_OK && isset($statuses[$myout['status']])) {
            $output = Status::getSegment($myout['status']);
        }
         */
    }
    return PLG_RET_OK;
}


/**
 * Get the product detail page for a specific item.
 * Takes the item ID as a full shop-compatible ID (membership:plan_id:opts)
 * and creates the detail page for inclusion in the shop catalog.
 *
 * @param   array   $args   Array containing item_id=>subscription:id:opts
 * @param   mixed   $output Output holder variable
 * @param   string  $svc_msg    Service message (not used)
 * @return  integer         Status value
 */
function service_getDetailPage_membership($args, &$output, &$svc_msg)
{
    $output = '';
    if (!is_array($args) || !isset($args['item_id'])) {
        return PLG_RET_PRECONDITION_FAILED;
    }
    $item_info = explode(':', $args['item_id']);
    if (!isset($item_info[1]) || empty($item_info[1])) {    // missing item ID
        return PLG_RET_PRECONDITION_FAILED;
    }
    $output = Plan::listPlans($item_info[1]);
    return PLG_RET_OK;
}


?>
