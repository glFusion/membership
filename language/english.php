<?php
/**
 * English language file for the Membership plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.0.3
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

$LANG_MEMBERSHIP = array(
'version'       => 'Version',
'menu_title'    => 'Membership',
'block_title'   => 'Membership Information',
'plans'         => 'Membership Plans',
'plan'          => 'Membership Plan',
'app_header'    => 'Member Profile',
'new_plan'      => 'New Plan',
'new_position'  => 'New Position',
'list_plans'    => 'List Plans',
'list_members'  => 'List Members',
'plan_info'     => 'Plan Information',
'plan_id'       => 'Plan ID',
'short_name'    => 'Short Name',
'price'         => 'Price',
'description'   => 'Description',
'period_start'  => 'Period Start',
'period'        => 'Period',
'new_purch'     => 'New Purchase',
'renewal'       => 'Renewal',
'any_period'    => 'Any Month',
'fee_schedule'  => 'Fee Schedule',
'fixed_fee'     => 'Online Processing Fee',
'linked_accounts' => 'Linked Accounts',
'update_relatives' => 'Update linked accounts',
'add_link'      => 'Add Linked Account',
'none'          => 'None',
'select'        => 'None',
'view_app'      => 'View Application',
'view'          => 'View',
'print'         => 'Print',
'exp_notice'    => 'Membership Expiration',
'you_expire'    => 'Your current membership (%s) expires %s.',
'curr_plan_expired' => 'Your current plan, expired %s',
'renew_within'  => 'You can renew your membership within %d days of expiration.',
//'must_login'    => 'Please <a href="%s">register</a> or <a href="%s">log in</a> to update your profile and purchase memberships.',
'must_login'    => 'Please <a href="' . $_CONF['site_url'] . '/users.php?mode=new">register</a> or <a href="' . $_CONF['site_url'] .
        '/users.php?mode=login" data-uk-modal="{target:\'#modalOpen\'}">log in</a> to update your profile and purchase memberships.',
'return_to_edit' => "To update your application, click <a href=\"{$_CONF['site_url']}/membership/index.php?editapp\">here</a>.",
'err_name'      => 'Invalid Plan Name',
'err_plan_id'   => 'Invalid Plan ID',
'admin_title'   => 'Membership Administration',
'error_saving'  => 'Errors encountered while saving the record:',
'submit'    => 'Submit',
'cancel'    => 'Cancel',
'reset'     => 'Reset Form',
'joined'    => 'Joined',
'expires'   => 'Expires',
'na'        => 'N/A',
'upd_links' => 'Update Links',
'q_del_item'    => 'Are you sure that you want to delete this item?',
'q_del_member'  => 'Are you sure that you want to delete these members? ' .
'The member will be unlinked from any linked accounts, and the membership record will be permanently deleted.',
'emancipate'    => 'Remove from family',
'log_expired'   => 'Expired membership for user %d (%s).',
'log_arrears'   => 'Membership for user %d (%s) is in arrears.',
'log_purged'    => 'Purged %d memberships expired over %d days ago.',
'enabled'       => 'Enabled',
'purch_access'  => 'Purchase allowed by',
'admins_only'   => 'Administrators Only',
'all_members'   => 'All Members',
'renew_all'     => 'Renew all selected items',
'renew'         => 'Renew',
'confirm_renew' => 'Are you sure you want to renew the selected memberships?',
'confirm_regen' => 'Are you sure you want to create new membership numbers for these accounts?',
'member_name'   => 'Member Name',
'name'          => 'Name',
'show_expired'  => 'Show Expired',
'current'       => 'Current',
'arrears'       => 'In Arrears',
'expired'       => 'Expired',
'notified'      => 'Notification Sent',
'update_of_plan' => 'Update of membership plan',
'succeeded'     => 'succeeded',
'failed'        => 'failed',
'your_info_updated' => 'Your information has been updated.',
'app_required'  => 'A completed membership profile is required.',
'please_complete_app' => 'Please take a moment to check that your <a href="%s">membership application</a> is current.',
'updating_member' => 'Updating member',
'member_stats'  => 'Member Stats',
'date'          => 'Date',
'pmt_method'    => 'Method',
'pmt_info'      => 'Pay and Renew',
'pmt_date'      => 'Pmt. Date',
'pmt_desc'      => 'Comment',
'pmt_amount'    => 'Amount Paid',
'transactions'  => 'Transactions',
'txn_id'        => 'Txn ID',
'check'         => 'Check',
'cash'          => 'Cash',
'cc'            => 'Credit Card',
'from'          => 'From',
'to'            => 'To',
'pmt_form'      => 'Remittance Coupon',
'pmt_instructions' => 'Please print this coupon and include it with your payment to be sure that we credit your membership properly.<br />Once your payment and application are processed, your website account will be updated to reflect your membership.',
'make_payable'  => 'Make your check or money order payable to &quot;%s&quot; and send it to',
'membership_fee' => 'Membership Fee',
'processing_fee' => 'Online Processing Fee',
'pay_by_check'  => 'Pay by Check',
'system_task'   => 'System Task',
'quickrenew'    => 'Quick Renewal',
'entered_by'    => 'Entered By',
'access_denied' => 'Access Denied',
'access_denied_msg' => 'You have reached a restricted area.',
'instr_app_form' => 'Update your profile information below.  After submitting your application, you will have the opportunity to select your membership type and pay online.',
'hdr_app_form'  => 'Membership Application',
'position'      => 'Position',
'application'   => 'Application',
'plan_list_app_footer' => 'Please <a href="%s">update your application</a> before creating or renewing a membership',
'current_user'  => 'Current Member',
'order'         => 'Order',
'show_vacant'   => 'Show Vacant?',
'position_type' => 'Position Type',
'move'          => 'Move Up/Down',
'contact_info'  => 'Contact Info',
'contact'       => 'Contact',
'vacant'        => 'Vacant',
'title_positionpage' => '%s Positions',
'select'        => 'Select',
'expand_pmt_div' => 'Expand and view payment options',
'collapse_pmt_div' => 'Collaps the quick-renewal section',

//'duration_type' => 'Duration Type',
//'show_in_block' => 'Show in Block',
//'at_registration' => 'At Registration',
//'taxable'       => 'Taxable',
//'day'           => 'Days',
//'week'          => 'Weeks',
//'month'         => 'Months',
//'year'          => 'Years',
//'fixed'         => 'Fixed',
'edit'          => 'Edit',
//'fixed_exp'     => 'Fixed Expiration',
//'quantity'      => 'Quantity',
'no_plan_avail' => 'No membership plans are available',
'fs_deleteplan' => 'Transfer Plan',
'transfer_plan' => 'Transfer members to',
'does_upd_links' => 'This plan will update all linked accounts',
'no_upd_links'  => 'Linked accounts will not be updated.',
'total' => 'Total',
'related_accounts'  => 'Related Accounts',
//'hlp_new_plan' => 'Create a new custom form definition.  The Order indicates where the item will appear on membership relative to other items, and may be changed later.',
//'hlp_reset_perms' => 'Reset all field permissions to their default values.',
'hlp_member_edit' => '<ul>' .
    '<li>Adding or removing links will add or remove <b>those</b> accounts from the family.</li>' .
    '<li>Checking the &quot;Remove from family&quot; checkbox will remove <b>this</b> account from the family and leave other linked accounts alone.</li>' .
    '<li>If the membership type is set to update linked accounts, then all currently-linked accounts will be updated when the profile is saved. Un-linked accounts are not affected.</li>' .
    '</ul>',
'hlp_transfer_plan' => 'Select a plan to which all members will be converted.  This is required before a plan can be deleted that has current members.',
'hlp_renew' => 'To add or renew a membership, set the expiration date above and add the payment information below.',
'hlp_show_vacant' => 'If checked, vacant positions will be included in the &quot;positions&quot; page and shown as &quotVacant&quot.',
'hlp_enabled' => 'Check or uncheck to enable or disable this item.',
'hlp_delete' => 'Click to delete this item.',

'msg_plan_has_members' => 'The selected plan has members and cannot be deleted without selecting a new plan for those members.',
'msg_plan_deleted' => 'Membership plan was deleted',
'msg_missing_id' => 'The ID is missing, no action taken',
'msg_unable_xfer_members' => 'An error occurred while transferring members to the new plan. Unable to delete the current plan',
'adm_listplans' => 'Create and Update membership plans.',
'adm_listmembers' => 'Edit memberships. To create a new membeship, visit the <a href="' .
    $_CONF['site_admin_url'] . '/user.php">user profile area</a>.',
'adm_listtrans' => 'List membership payment transactions.',
'adm_stats' => 'This list shows the total number of memberships that are current or in arrears (where the grace period has not passed). Only unique memberships are shown. Members that belong to the same membership are not counted separately.',
'adm_importform' => 'Import existing site users into the Membership plugin. Existing membership records are not affected.<br />Only one target membership plan is supported, and accounts are not linked.',
'new_acct_msg' => 'Thank you for registering! If you\'d like to join our organization, please click <a href="' . $_CONF['site_url'] . '/membership">here</a> to update your application and purchase a membership.',
'new_user_msg' => 'Thank you for registering on our site. Your site registration is free and allows you to access public content and forums. If you\'d like to become a member of the association, please <a href="' . $_CONF['site_url'] . '/users.php?mode=login">login</a> to complete your membership application.',
'required' => 'Required',
'renew_link' => 'Please click <a href="' . $_CONF['site_url'] . '/membership">here</a> to update your application and renew your membership.',
'blk_join_now' => 'Not a member? Join <a href="'.$_CONF['site_url'].'/membership">Here</a>!',
'blk_member_info' => 'Membership: %s<br />Expires: %s',
'agree_terms' => 'By checking this box and entering my initials, I confirm that I have read and agree to the terms and conditions',
'initials' => 'Initials',
//'terms_link' => 'Click <a href="%s" target="_new">here</a> to review.',
'terms_link' => 'Click <a href="#!agree_terms" onclick="popupWindow(\'%s\', \'Help\', 640, 480, 1)" class="toolbar">here</a> to review.',
'err_terms_accept' => 'You must accept the terms and conditions.',
'field_required' => 'This item cannot be left blank',
'mailchimp_subscribe' => 'Subscribe to our mailing list?',
'import_current' => 'Import Current Members',
'import_from_grp' => 'Import members from group',
'mem_number' => 'Membership Number',
'regen_mem_numbers' => 'Regen Mem Numbers',
'istrial' => 'Trial Membership?',
'trial' => 'Trial',
'item_updated' => 'Item has been updated.',
'item_nochange' => 'Item has not been changed.',
'rem_this_item' => 'Remove this item',
'positions' => 'Positions',
'import' => 'Import',
'date_selector' => 'Date Selector',
);

$LANG_MYACCOUNT['pe_membership'] = 'Membership';

$PLG_membership_MESSAGE01 = 'Thank you for your application.';
$PLG_membership_MESSAGE02 = 'The form contains missing or invalid fields';
$PLG_membership_MESSAGE3 = 'You cannot delete a plan that has members without selecting a new plan for those members.';
$PLG_membership_MESSAGE4 = 'Empty plan ID provided';
$PLG_membership_MESSAGE5 = 'Application has been updated.';
$PLG_membership_MESSAGE6 = 'There was an error updating the application.';

/** Language strings for the plugin configuration section */
$LANG_configsections['membership'] = array(
    'label' => 'Membership',
    'title' => 'Membership Configuration'
);

$LANG_configsubgroups['membership'] = array(
    'sg_main' => 'Main Settings',
    'sg_integrations' => 'Integrations',
);

$LANG_fs['membership'] = array(
    'fs_main' => 'General Settings',
    'fs_prflist' => 'Member List Options',
    'fs_checkpay' => 'Manual Payment Options',
    'fs_mailchimp' => 'Mailchimp',
    'fs_mediagallery' => 'MediaGallery',
    'fs_shop' => 'Shop Plugin',
    'fs_app' => 'Applications',
);

$LANG_confignames['membership'] = array(
    'period_start' => 'Membership Period Start',
    'trial_days' => 'Trial Membership Days',
    'member_group' => 'Current Member Group',
    'member_all_group' => 'All Members Group (incl. arrears)',
    'displayblocks'  => 'Display glFusion Blocks',
    'grace_days' => 'Grace days before disabling membership',
    'drop_days' => 'Days after expiration when member will be dropped',
    'expire_eom' => 'Round expiration up to end of month?',
    'early_renewal' => 'Days before expiration that renewal is allowed',
    'notifydays' => 'Days before expiration to notify members',
    'notifymethod' => 'Method of expiration notifications',
    'debug' => 'Log debugging messages?',
    'onmenu' => 'Show on glFusion main menu',
    'enabled' => 'Plan is enabled',
    'app_form' => 'Application Form',
    'view_app' => 'Allow members to view their application?',
    'require_app' => 'Enable membership application?',
    'prflist_current' => 'Show current members?',
    'prflist_arrears' => 'Show members in arrears?',
    'prflist_expired' => 'Show expired members?',
    'ena_checkpay' => 'Enable manual payments?',
    'payable_to' => 'Make checks payable to:',
    'remit_to' => 'Remittance Address',
    'adm_def_view' => 'Default Administrative View',
    'terms_accept' => 'Require acceptance of terms?',
    'terms_url' => 'URL to terms and conditions',
    'update_maillist' => 'Update mailing list segment?',
    'segment_active' => 'Segment for active members',
    'segment_arrears' => 'Segment for members in arrears',
    'segment_expired' => 'Segment for expired members',
    'segment_dropped' => 'Segment for non-members',
    'manage_mg_quota' => 'Manage the user Album quota?',
    'mg_quota_member' => 'User Album quota for members (MB)',
    'mg_quota_nonmember' => 'User Album quota for non-members (MB)',
    'enable_shop' => 'Enable Shop Payments?',
    'mem_num_fmt' => 'Format for membership numbers',
    'use_mem_number' => 'Use membership numbers?',
    'disable_expired' => 'Disable account upon expiration',
    'redir_after_purchase' => 'Redirect URL after purchase',
    'currency' => 'Currency if Shop is <b>not</b> integrated',
    'app_provider' => 'Plugin providing application',
    'app_form_id' => 'Application form ID if Forms plugin is used',
    'expires_eom' => 'Memberships expire at end of month?',
);

// Note: entries 0, 1, and 12 are the same as in $LANG_configselects['Core']
$LANG_configselects['membership'] = array(
    0 => array('True' => 1, 'False' => 0),
    1 => array('True' => TRUE, 'False' => FALSE),
    3 => array('Yes' => 1, 'No' => 0),
    4 => array('--None--' => '', 'Forms' => 'forms', 'Profile' => 'profile'),
    12 => array('No access' => 0, 'Read-Only' => 2, 'Read-Write' => 3),
    13 => array('None' => 0, 'Left' => 1, 'Right' => 2, 'Both' => 3),
    14 => array(
        '00-Rolling' => 0, '01-January' => 1, '02-February' => 2,
        '03-March' => 3, '04-April' => 4, '05-May' => 5, '06-June' => 6,
        '07-July' => 7, '08-August' => 8, '09-September' => 9,
        '10-October' => 10, '11-November' => 11, '12-December' => 12,
    ),
    15 => array(
        'Plan List' => 'listplans', 'Member List' => 'listmembers',
        'Transactions' => 'listtrans', 'Member Stats' => 'stats',
    ),
    16 => array('Not Used' => 0, 'Optional' => 1, 'Required' => 2),
    17 => array(
        'Yes- Add Processing Fee' => 2,
        'Yes - No Processing Fee' => 1,
        'No' => 0,
    ),
    18 => array(
        '0 = None' => 0,
        '1 - Via Email' => 1,
        '2 - Via Login Message' => 2,
        '3 - Both' => 3,
    ),
    19 => array('Not Used' => 0, 'Free-Form' => 1, 'Auto-Gen' => 2),
    20 => array('Disabled' => 0, 'Cart Only' => 1, 'Buy Now + Cart' => 2),
);

?>
