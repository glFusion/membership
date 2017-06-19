<?php
/**
*   Configuration Defaults for the Membership plugin for glFusion.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2016 Lee Garner
*   @package    membership
*   @version    0.1.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*
*/


// This file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Membership plugin configuration defaults
*   @global array */
global $_MEMBERSHIP_DEFAULT;
$_MEMBERSHIP_DEFAULT = array(
    // Grace period after expiration date when membership will terminate
    'grace_days'    => 5,

    // After this number of days past expiration, a membership will be
    // considered "new". Used to determine when a previous member may take
    // advantage of discounted new membership, or loses discounted renewal
    'drop_days'     => 365,

    // Round a membership up to the end of the month?  e.g. if a member
    // purchases a one-year membership on July 15, it will expire on July 31
    // of the following year
    'expire_eom'    => 1,

    // Set the starting month for memberships. 0 = rolling (start anytime)
    'period_start'  => 0,

    // Maximum number of days before expiration that a subscription can be
    // renewed
    'early_renewal' => 45,

    // Days before expiration to notify subscribers.  -1 = "never"
    'notifydays'    => -1,

    // How to notify members of upcoming expiration.
    // 0 = none
    // 1 = email
    // 2 = login message
    // 3 = both
    'notifymethod'  => 0,

    // Log verbose debugging messages?
    'debug'         => 0,

    // Which glFusion blocks to show in our pages
    // 0 = none, 1 = left, 2 = right, 3 = both
    'displayblocks' => 3,

    // Application form ID
    //'app_form'      => '';

    // Allow members to view their applications? Puts a link in the profiles,
    // etc.
    'view_app'      => 1,

    // Enable or require an application?
    // 0 = No application used
    // 1 = Application available, a link to update shows on the plugin index
    // 2 = Required, the index page will redirect the use to update their app
    'require_app'   => 0,

    // Select the default member types to show in member listings
    // As long as one is selected the member list will show only those
    // member types. If none are selected, all site members are shown.
    'prflist_current' => 1,
    'prflist_arrears' => 0,
    'prflist_expired' => 0,

    // Default view for administrators. Any of the views in the admin index.php
    'adm_def_view'  => 'listplans',

    // Who should checks be made payable to, and where should they be sent?
    // remit_to can include HTML for formatting on the manual payment coupon.
    'payable_to'    => '',
    'remit_to'      => '',

    // Enable manual payments? If 0 then only the PayPal plugin can be used.
    'ena_checkpay'  => 1,

    // Requre applicants to accept terms & conditions?
    'terms_accept'  => 0,   // 0 = none, 1 = optional, 2 = required
    'terms_url'     => '',  // url to terms, can be internal or external

    // Manage Mediagallery user quotas?
    'manage_mg_quota' => 0, // default "no"
    'mg_quota_member' => 0,
    'mg_quota_nonmember' => 0,

    // Update Mailchimp mailing list?
    'update_maillist' => 0,

    // Enable integration with the Paypal plugin
    // If not enabled, only manual payments will be available
    // 0 - disabled, 1 = cart only, 2 = buy now + cart
    'enable_paypal' => 2,

    // Use membership numbers?
    'use_mem_number' => 0,
    // Format string for creating membership numbers. Just create as uid.
    'mem_num_fmr' => '%d',

    // Disable expired member accounts
    'disable_expired' => 0,

    // Redirect to another page after the "Add to Cart" button is clicked.
    // Requires Paypal integration.
    'redir_after_purchase' => '',
);


/**
*   Initialize Membership plugin configuration
*
*   @param  integer $group_id   Group ID to use as the plugin's admin group
*   @return boolean             true: success; false: an error occurred
*/
function plugin_initconfig_membership($group_id = 0)
{
    global $_CONF, $_CONF_MEMBERSHIP, $_MEMBERSHIP_DEFAULT;

    $c = config::get_instance();

    if (!$c->group_exists($_CONF_MEMBERSHIP['pi_name'])) {

        $c->add('sg_main', NULL, 'subgroup', 0, 0, NULL, 0, true, 
                $_CONF_MEMBERSHIP['pi_name']);

        // Main options
        $c->add('fs_main', NULL, 'fieldset', 0, 10, NULL, 0, true, 
                $_CONF_MEMBERSHIP['pi_name']);
        // default member group gets passed in from plugin_install_ function
        $c->add('member_group', (int)$group_id,
                'select', 0, 10, 0, 10, true, $_CONF_MEMBERSHIP['pi_name']);
        //$c->add('member_all_group', (int)$group_id,
        //        'select', 0, 10, 0, 15, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('grace_days', $_MEMBERSHIP_DEFAULT['grace_days'],
                'text', 0, 10, 0, 20, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('drop_days', $_MEMBERSHIP_DEFAULT['drop_days'],
                'text', 0, 10, 0, 30, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('expire_eom', $_MEMBERSHIP_DEFAULT['expire_eom'],
                'select', 0, 10, 3, 40, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('early_renewal', $_MEMBERSHIP_DEFAULT['early_renewal'],
                'text', 0, 10, 0, 50, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('notifydays', $_MEMBERSHIP_DEFAULT['notifydays'],
                'text', 0, 10, 0, 60, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('notifymethod', $_MEMBERSHIP_DEFAULT['notifymethod'],
                'select', 0, 10, 18, 65, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('period_start', $_MEMBERSHIP_DEFAULT['period_start'],
                'select', 0, 10, 14, 70, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('disable_expired', $_MEMBERSHIP_DEFAULT['disable_expired'],
                'select', 0, 10, 3, 80, true, $_CONF_MEMBERSHIP['pi_name']);
        // If using the Forms plugin, maybe a later option.
        //$c->add('app_form', $_MEMBERSHIP_DEFAULT['app_form'],
        //        'select', 0, 10, 0, 80, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('require_app', $_MEMBERSHIP_DEFAULT['require_app'],
                'select', 0, 10, 16, 90, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('view_app', $_MEMBERSHIP_DEFAULT['view_app'],
                'select', 0, 10, 3, 100, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('displayblocks', $_MEMBERSHIP_DEFAULT['displayblocks'],
                'select', 0, 10, 13, 110, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('debug', $_MEMBERSHIP_DEFAULT['debug'],
                'select', 0, 10, 3, 130, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('adm_def_view', $_MEMBERSHIP_DEFAULT['adm_def_view'],
                'select', 0, 10, 15, 140, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('terms_accept', $_MEMBERSHIP_DEFAULT['terms_accept'],
                'select', 0, 10, 16, 150, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('terms_url', $_MEMBERSHIP_DEFAULT['terms_url'],
                'text', 0, 10, 0, 160, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('use_mem_number', $_MEMBERSHIP_DEFAULT['use_mem_number'],
                'select', 0, 10, 19, 170, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('mem_num_fmt', $_MEMBERSHIP_DEFAULT['mem_num_fmt'],
                'text', 0, 10, 0, 180, true, $_CONF_MEMBERSHIP['pi_name']);

        // Profile list options
        $c->add('fs_prflist', NULL, 'fieldset', 0, 20, NULL, 0, true, 
                $_CONF_MEMBERSHIP['pi_name']);
        $c->add('prflist_current', $_MEMBERSHIP_DEFAULT['prflist_current'],
                'select', 0, 20, 3, 120, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('prflist_arrears', $_MEMBERSHIP_DEFAULT['prflist_arrears'],
                'select', 0, 20, 3, 120, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('prflist_expired', $_MEMBERSHIP_DEFAULT['prflist_expired'],
                'select', 0, 20, 3, 120, true, $_CONF_MEMBERSHIP['pi_name']);

        // Manual payment options
        $c->add('fs_checkpay', NULL, 'fieldset', 0, 30, NULL, 0, true, 
                $_CONF_MEMBERSHIP['pi_name']);
        $c->add('ena_checkpay', $_MEMBERSHIP_DEFAULT['ena_checkpay'],
                'select', 0, 30, 17, 10, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('payable_to', $_MEMBERSHIP_DEFAULT['payable_to'],
                'text', 0, 30, 0, 20, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('remit_to', $_MEMBERSHIP_DEFAULT['remit_to'],
                'text', 0, 30, 0, 30, true, $_CONF_MEMBERSHIP['pi_name']);

        // Subgroup - integrations
        $c->add('sg_integrations', NULL, 'subgroup',
                20, 0, NULL, 0, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('fs_mailchimp', NULL, 'fieldset', 20, 10, NULL, 0, true,
                $_CONF_MEMBERSHIP['pi_name']);
        $c->add('update_maillist', $_MEMBERSHIP_DEFAULT['update_maillist'],
                'select', 20, 10, 3, 10, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('segment_active', $_MEMBERSHIP_DEFAULT['segment_active'],
                'text', 20, 10, 0, 20, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('segment_arrears', $_MEMBERSHIP_DEFAULT['segment_arrears'],
                'text', 20, 10, 0, 20, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('segment_expired', $_MEMBERSHIP_DEFAULT['segment_expired'],
                'text', 20, 10, 0, 30, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('segment_dropped', $_MEMBERSHIP_DEFAULT['segment_dropped'],
                'text', 20, 10, 0, 40, true, $_CONF_MEMBERSHIP['pi_name']);

        $c->add('fs_mediagallery', NULL, 'fieldset', 20, 20, NULL, 0, true,
                $_CONF_MEMBERSHIP['pi_name']);
        $c->add('manage_mg_quota', $_MEMBERSHIP_DEFAULT['manage_mg_quota'],
                'select', 20, 20, 3, 10, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('mg_quota_member', $_MEMBERSHIP_DEFAULT['mg_quota_member'],
                'text', 20, 20, 0, 20, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('mg_quota_nonmember', $_MEMBERSHIP_DEFAULT['mg_quota_nonmember'],
                'text', 20, 20, 0, 30, true, $_CONF_MEMBERSHIP['pi_name']);

        // Fieldset - Paypal plugin options
        $c->add('fs_paypal', NULL, 'fieldset', 20, 30, NULL, 0, true,
                $_CONF_MEMBERSHIP['pi_name']);
        $c->add('enable_paypal', $_MEMBERSHIP_DEFAULT['enable_paypal'],
                'select', 20, 30, 20, 10, true, $_CONF_MEMBERSHIP['pi_name']);
        $c->add('redir_after_purchase', $_MEMBERSHIP_DEFAULT['redir_after_purchase'],
                'text', 20, 30, 0, 20, true, $_CONF_MEMBERSHIP['pi_name']);
     }

     return true;
}

?>
