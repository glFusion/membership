<?php
/**
 *   Configuration Defaults for the Membership plugin for glFusion.
 *
 *   @author     Lee Garner <lee@leegarner.com>
 *   @copyright  Copyright (c) 2012-2018 Lee Garner
 *   @package    membership
 *   @version    0.2.0
 *   @license    http://opensource.org/licenses/gpl-2.0.php
 *               GNU Public License v2 or later
 *   @filesource
 *
 */


// This file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 * Membership default settings.
 *
 * Initial Installation Defaults used when loading the online configuration
 * records. These settings are only used during the initial installation
 * and not referenced any more once the plugin is installed.
 *
 * @global  array
 */
$membershipConfigData = array(
    array(
        'name' => 'sg_main',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'fs_main',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'member_group',
        'default_value' => '',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'membership',
    ),
    // Grace period after expiration date when membership will terminate
    array(
        'name' => 'grace_days',
        'default_value' => '5',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'membership',
    ),
    // After this number of days past expiration, a membership will be
    // considered "new". Used to determine when a previous member may take
    // advantage of discounted new membership, or loses discounted renewal
    array(
        'name' => 'drop_days',
        'default_value' => '365',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'membership',
    ),
    // Round a membership up to the end of the month?  e.g. if a member
    // purchases a one-year membership on July 15, it will expire on July 31
    // of the following year
    array(
        'name' => 'expires_eom',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 40,
        'set' => true,
        'group' => 'membership',
    ),
    // Maximum number of days before expiration that a subscription can be
    // renewed
    array(
        'name' => 'early_renewal',
        'default_value' => '45',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 50,
        'set' => true,
        'group' => 'membership',
    ),
    // Days before expiration to notify subscribers.  -1 = "never"
    array(
        'name' => 'notifydays',
        'default_value' => '-1',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'membership',
    ),
    // How to notify members of upcoming expiration.
    // 0 = none
    // 1 = email
    // 2 = login message
    // 3 = both
    array(
        'name' => 'notifymethod',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 18,
        'sort' => 70,
        'set' => true,
        'group' => 'membership',
    ),
    // Set the starting month for memberships. 0 = rolling (start anytime)
    array(
        'name' => 'period_start',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 14,
        'sort' => 80,
        'set' => true,
        'group' => 'membership',
    ),
    // Disable expired member accounts
    array(
        'name' => 'disable_expired',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 90,
        'set' => true,
        'group' => 'membership',
    ),
    // Which glFusion blocks to show in our pages
    // 0 = none, 1 = left, 2 = right, 3 = both
    array(
        'name' => 'displayblocks',
        'default_value' => 3,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 13,
        'sort' => 120,
        'set' => true,
        'group' => 'membership',
    ),
    // Which glFusion blocks to show in our pages
    // 0 = none, 1 = left, 2 = right, 3 = both
    array(
        'name' => 'debug',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 130,
        'set' => true,
        'group' => 'membership',
    ),
    // Default view for administrators. Any of the views in the admin index.php
    array(
        'name' => 'adm_def_view',
        'default_value' => 'listplans',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 15,
        'sort' => 140,
        'set' => true,
        'group' => 'membership',
    ),
    // Requre applicants to accept terms & conditions?
    // 0 = none, 1 = optional, 2 = required
    array(
        'name' => 'terms_accept',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 150,
        'set' => true,
        'group' => 'membership',
    ),
    // url to terms, can be internal or external
    array(
        'name' => 'terms_url',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 160,
        'set' => true,
        'group' => 'membership',
    ),
    // Use membership numbers?
    array(
        'name' => 'use_mem_number',
        'default_value' => 0,
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 170,
        'set' => true,
        'group' => 'membership',
    ),
    // Format string for creating membership numbers. Just create as uid.
    array(
        'name' => 'mem_num_fmt',
        'default_value' => '%d',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 180,
        'set' => true,
        'group' => 'membership',
    ),

    // Profile list options
    // Select the default member types to show in member listings
    // As long as one is selected the member list will show only those
    // member types. If none are selected, all site members are shown.
    array(
        'name' => 'fs_prflist',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 10,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'prflist_current',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 3,
        'sort' => 10,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'prflist_arrears',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 3,
        'sort' => 20,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'prflist_expired',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 10,
        'selection_array' => 3,
        'sort' => 30,
        'set' => true,
        'group' => 'membership',
    ),

    // Manual payment options
    array(
        'name' => 'fs_checkpay',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => NULL,
        'sort' => 20,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'ena_checkpay',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 3,
        'sort' => 10,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'payable_to',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'remit_to',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'membership',
    ),

    // Application options
    array(
        'name' => 'fs_app',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => NULL,
        'sort' => 30,
        'set' => true,
        'group' => 'membership',
    ),
    // Enable or require an application?
    // 0 = No application used
    // 1 = Application available, a link to update shows on the plugin index
    // 2 = Required, the index page will redirect the use to update their app
    array(
        'name' => 'require_app',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 16,
        'sort' => 10,
        'set' => true,
        'group' => 'membership',
    ),
    // Plugin used to provide application services
    array(
        'name' => 'app_provider',
        'default_value' => '',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 4,
        'sort' => 30,
        'set' => true,
        'group' => 'membership',
    ),
    // Application form ID if the Forms plugin is used
    array(
        'name' => 'app_form_id',
        'default_value' => 'pi_membership_app',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 30,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'membership',
    ),

    // Integrations Subgroup. Integrating with other plugins.
    array(
        'name' => 'sg_integrations',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 10,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'fs_mailchimp',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 10,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'membership',
    ),
    // Update Mailchimp mailing list?
    array(
        'name' => 'update_maillist',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 10,
        'set' => true,
        'group' => 'membership',
    ),
    // Mailchimp segments. Enter a segment name for Active, Arrears, Expired and Non-Membrers.
    array(
        'name' => 'segment_active',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'segment_arrears',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'segment_expired',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'membership',
    ),
    array(
        'name' => 'segment_dropped',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'membership',
    ),

    // Media Gallery integration
    array(
        'name' => 'fs_mediagallery',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 10,
        'fieldset' => 10,
        'selection_array' => NULL,
        'sort' => 10,
        'set' => true,
        'group' => 'membership',
    ),
    // Should the Mediagallery quota be updated with membership changes?
    array(
        'name' => 'manage_mg_quota',
        'default_value' => 0,
        'type' => 'select',
        'subgroup' => 10,
        'fieldset' => 10,
        'selection_array' => 3,
        'sort' => 10,
        'set' => true,
        'group' => 'membership',
    ),
    // MediaGallery quota for current members
    array(
        'name' => 'mg_quota_member',
        'default_value' => '0',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'membership',
    ),
    // MediaGallery quota for non-members
    array(
        'name' => 'mg_quota_nonmember',
        'default_value' => '0',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 10,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'membership',
    ),

    // Fieldset - Paypal plugin options
    array(
        'name' => 'fs_paypal',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 10,
        'fieldset' => 20,
        'selection_array' => NULL,
        'sort' => 20,
        'set' => true,
        'group' => 'membership',
    ),
    // Enable integration with the Paypal plugin
    // If not enabled, only manual payments will be available
    // 0 - disabled, 1 = cart only, 2 = buy now + cart
    array(
        'name' => 'enable_paypal',
        'default_value' => 2,
        'type' => 'select',
        'subgroup' => 10,
        'fieldset' => 20,
        'selection_array' => 20,
        'sort' => 10,
        'set' => true,
        'group' => 'membership',
    ),
    // Redirect to another page after the "Add to Cart" button is clicked.
    // Requires Paypal integration.
    array(
        'name' => 'redir_after_purchase',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 20,
        'set' => true,
        'group' => 'membership',
    ),
    // Currency to use if Paypal is *not* integrated.
    array(
        'name' => 'currency',
        'default_value' => 'USD',
        'type' => 'text',
        'subgroup' => 10,
        'fieldset' => 20,
        'selection_array' => 0,
        'sort' => 30,
        'set' => true,
        'group' => 'membership',
    ),
);


/**
 * Initialize Membership plugin configuration.
 *
 * @param   integer $group_id   Group ID to use as the plugin's admin group
 * @return  boolean             true: success; false: an error occurred
 */
function plugin_initconfig_membership($group_id = 0)
{
    global $membershipConfigData;

    $c = config::get_instance();
    if (!$c->group_exists('membership')) {
        USES_lib_install();
        foreach ($photoConfigData AS $cfgItem) {
            _addConfigItem($cfgItem);
        }
    }
    return true;
}

?>
