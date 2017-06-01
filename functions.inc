<?php
/**
*   Public API functions for the Membership plugin for glFusion.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2017 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.3
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**  Check and see if we need to load the plugin configuration */
if (!isset($_CONF_MEMBERSHIP['member_group'])) {
    require_once $_CONF['path_system'] . 'classes/config.class.php';
    $mem_config = config::get_instance();
    $_CONF_MEMBERSHIP = $mem_config->get_config('membership');
}

/** Include static plugin config values */
require_once 'membership.php';

/** Define the absolute path to the plugin's directory */
define('MEMBERSHIP_PI_PATH',
        $_CONF['path'] . 'plugins/' . $_CONF_MEMBERSHIP['pi_name']);
/** Define the base public url for the plugin */
define('MEMBERSHIP_PI_URL',
        $_CONF['site_url'] . '/' . $_CONF_MEMBERSHIP['pi_name']);
/** Define the administration url for the plugin */
define('MEMBERSHIP_ADMIN_URL',
        $_CONF['site_admin_url'] . '/plugins/' . $_CONF_MEMBERSHIP['pi_name']);

// Define types of membership activity
define('MEMBERSHIP_NEW', 1);
define('MEMBERSHIP_RENEW', 2);
define('MEMBERSHIP_STATUS_ACTIVE',  0);
define('MEMBERSHIP_STATUS_ENABLED', 1);
define('MEMBERSHIP_STATUS_ARREARS', 2);
define('MEMBERSHIP_STATUS_EXPIRED', 4);
define('MEMBERSHIP_STATUS_DROPPED', 128);
define('MEMBERSHIP_APP_NONE', 0);
define('MEMBERSHIP_APP_READ', 1);
define('MEMBERSHIP_APP_CREATE', 2);
define('MEMBERSHIP_APP_EDIT', 4);
define('MEMBERSHIP_APP_ALLACCESS', 7);
// Settings for whether a membership app is available and required
define('MEMBERSHIP_APP_DISABLED', 0);
define('MEMBERSHIP_APP_OPTIONAL', 1);
define('MEMBERSHIP_APP_REQUIRED', 2);

// define values for whether a user can purchase memberships
define('MEMBERSHIP_CANPURCHASE', 0);
define('MEMBERSHIP_NOPURCHASE', 1);
define('MEMBERSHIP_NEED_APP',   2);
define('MEMBERSHIP_NO_RENEWAL', 3);

// Code to identify messages saved by LGLIB_storeMessage()
define('MEMBERSHIP_MSG_EXPIRING', 'memb_msg_expiring');

// Define values for expiration notification method
define('MEMBERSHIP_NOTIFY_NONE', 0);
define('MEMBERSHIP_NOTIFY_EMAIL', 1);
define('MEMBERSHIP_NOTIFY_MESSAGE', 2);
define('MEMBERSHIP_NOTIFY_BOTH', 3);

/** Determine the correct language file, falling back to English as default. */
$langpath = MEMBERSHIP_PI_PATH . '/language';
$langfile = $_CONF['language'] . '.php';
if (!file_exists("$langpath/$langfile")) {
    $langfile = 'english.php';
}

/** Include plugin language file */
require_once "$langpath/$langfile";
if (file_exists("$langpath/custom/$langfile")) {
    /** Include custom language file, if any */
    include_once "$langpath/custom/$langfile";
}

// Import config.php last since the field definitions rely on the language.
if (file_exists(MEMBERSHIP_PI_PATH . '/config.php')) {
/** Include local config values and overrides */
    include_once MEMBERSHIP_PI_PATH . '/config.php';
}

// This is temporary, and will be included in glFusion 1.6.0
$_CONF_MEMBERSHIP['_is_uikit'] = $_SYSTEM['framework'] == 'uikit' ? true : false;
$_CONF_MEMBERSHIP['_iconset'] = $_CONF_MEMBERSHIP['_is_uikit'] ? 'uk-icon' : 'fa fa';

$_CONF_MEMBERSHIP['now'] = new Date('now', $_CONF['timezone']);
$_CONF_MEMBERSHIP['today'] = $_CONF_MEMBERSHIP['now']->format('Y-m-d', true);
// Save the date that the grace period ends.  If expiration is earlier than this,
// the membership has expired
$_CONF_MEMBERSHIP['dt_end_grace'] = date('Y-m-d',
        strtotime('-' . $_CONF_MEMBERSHIP['grace_days'] . ' day',
            strtotime($_CONF_MEMBERSHIP['today'])));
// Save the latest expiration date that qualifies for renewal. If expiration is
// after this date the user cannot renew yet.
$_CONF_MEMBERSHIP['dt_begin_renewal'] = date('Y-m-d',
        strtotime('+' . $_CONF_MEMBERSHIP['early_renewal'] . ' day',
            strtotime($_CONF_MEMBERSHIP['today'])));

//if (!isset($_CONF_MEMBERSHIP['member_all_group']) ||
//        empty($_CONF_MEMBERSHIP['member_all_group'])) {
//    $_CONF_MEMBERSHIP['member_all_group'] = $_CONF_MEMBERSHIP['member_group'];
//}

// Import plugin-specific functions
function USES_membership_functions()
{   require_once MEMBERSHIP_PI_PATH . '/membership_functions.inc.php'; }

/** Import member class */
function USES_membership_class_membership()
{   require_once MEMBERSHIP_PI_PATH . '/classes/Membership.class.php'; }

/** Import membership plan class */
function USES_membership_class_plan()
{   require_once MEMBERSHIP_PI_PATH . '/classes/Plan.class.php'; }

function USES_membership_class_link()
{   require_once MEMBERSHIP_PI_PATH . '/classes/Link.class.php'; }

function USES_membership_class_app()
{   require_once MEMBERSHIP_PI_PATH . '/classes/App.class.php'; }

function USES_membership_class_position()
{   require_once MEMBERSHIP_PI_PATH . '/classes/Position.class.php'; }

// Determine if the paypal plugin is installed and integration is enabled
if (!is_array($_PLUGINS) || !in_array('paypal', $_PLUGINS)) {
    // No paypal integration enabled.  Maybe need to die here?
    $_CONF_MEMBERSHIP['enable_paypal'] = 0;
}

require_once MEMBERSHIP_PI_PATH . '/services.inc.php';

/**
*   Called by the plugin Editor to display the current plugin code version.
*   This may be different than the version installed and registered currently.
*   If newer then you may want to run the update.
*
*   @return   string  version number
*/
function plugin_chkVersion_membership()
{
    global $_CONF_MEMBERSHIP;
    return $_CONF_MEMBERSHIP['pi_version'];
}


/**
*   Returns the items for this plugin that should appear on the main menu.
*
*   @return    array   Associative array of menu items to be added to menu
*/
function plugin_getmenuitems_membership()
{
    global $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP;

    $icon = '&nbsp;<i class="' . $_CONF_MEMBERSHIP['_iconset'] . '-group"></i>';
    $menuitems = array(
        $LANG_MEMBERSHIP['block_title'] . $icon => MEMBERSHIP_PI_URL . '/index.php',
    );
    return $menuitems;
}


/**
*   This will put an option for membership administration in the command
*   and control area
*
*   @return   mixed   array(title, url, icon), or false when not allowed
*/
function plugin_cclabel_membership()
{
    global $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP;

    if (MEMBERSHIP_isAdmin()) {
        return array($LANG_MEMBERSHIP['menu_title'],
                MEMBERSHIP_ADMIN_URL . '/index.php',
                plugin_geticon_membership());
    } else {
        return false;
    }
}


/**
*   Put an option for membership management on the admin-only menu.
*   @return   mixed   array(title, url, num. banner), or void when not allowed
*/
function plugin_getadminoption_membership()
{
    global $_CONF, $LANG_MEMBERSHIP;

    if (MEMBERSHIP_isAdmin()) {
        return array($LANG_MEMBERSHIP['menu_title'],
                MEMBERSHIP_ADMIN_URL . '/index.php',
                );
    } else {
        return false;
    }
}


/**
*   Get the user menu option for this plugin.
*
*   @return array   Array of menu items.
*/
function plugin_getuseroption_membership()
{
    return '';
}


/**
*   Get additional CSS into the site header.
*   Checks first for a layout-specific version, then loads the default.
*
*   @return array   List of paths to Javascript files
*/
function plugin_getheadercss_membership()
{
    global $_CONF;

    $styles = array();
    $files = array('style.css');

    $defpath = MEMBERSHIP_PI_PATH . '/css';
    $layoutpath = $_CONF['path_layout'] . 'plugins/membership';
    foreach ($files as $file) {
        $styles[] = $defpath . '/' . $file;
        if (is_file($defpath . '/custom/' . $file)) {
            $styles[] = $defpath . '/custom/' . $file;
        }
        if (is_file($layoutpath . '/' . $file)) {
            $styles[] = $layoutpath . '/' . $file;
        }
    }
    return $styles;
}


/**
*   Update the Membership plugin
*   @return integer Number of message to display (true = generic success msg)
*/
function plugin_upgrade_membership()
{
    require_once MEMBERSHIP_PI_PATH . '/upgrade.inc.php';
    return MEMBERSHIP_do_upgrade();
}


/**
*   Returns the URL of the plugin's icon
*   @return   string      URL of the icon
*/
function plugin_geticon_membership ()
{
    global $_CONF;
    return MEMBERSHIP_PI_URL . '/images/membership.png';
}


/**
*   Automatic uninstall function for plugins
*
*   This code is automatically uninstalling the plugin.
*   It passes an array to the core code function that removes
*   tables, groups, features and php blocks from the tables.
*   Additionally, this code can perform special actions that cannot be
*   foreseen by the core code (interactions with other plugins for example)
*
*   @return   array     Elements to be removed
*/
function plugin_autouninstall_membership ()
{
    global $_CONF_MEMBERSHIP;
    $out = array (
        // give the name of the tables, without $_TABLES[]
        'tables' => array(
                'membership_members',
                'membership_plans',
                'membership_links',
                'membership_log',
                'membership_trans',
                'membership_positions',
        ),
        // give the full name of the group, as in the db.
        // Don't remove the membership group, it should be left alone or
        // the site admin might lose track of current members.
        'groups' => array(
                'membership Admin',
                'membership Manage',
        ),
        // give the full name of the feature, as in the db
        'features' => array(
                'membership.admin',
                'membership.manage',
        ),
        // give the full name of the block, including 'phpblock_', etc
        'php_blocks' => array(),
        // give all vars with their name
        'vars'=> array()
    );
    return $out;
}


/**
*   Create the membership info block.
*
*   @return string      HTML for the block
*/
function phpblock_membership_memberinfo()
{
    global $_GROUPS, $_CONF_MEMBERSHIP;

    $retval = '';

    $curr = MEMBERSHIP_currentMembership();
    if (in_array($_GROUPS, $_CONF_MEMBERSHIP['member_group'])) {
        $retval .= sprinf($LANG_MEMBERSHIP['blk_member_info'],
            $curr->plan_id, $curr->expires);
    } else {
        $retval .= $LANG_MEMBERSHIP['blk_join_now'];
    }
    return $retval;
}


/**
*   Edit membership information in the user's profile.
*   This feature is only open to membership managers.  Users can't edit
*   their own.
*
*   @param  integer $uid        User ID being edited
*   @param  string  $panel
*   @param  string  $fieldset
*   @return string      HTML for edit form
*/
function plugin_profileedit_membership($uid, $panel, $fieldset)
{
    global $_CONF, $_USER;

    $retval = '';

    if (($panel == '' || $panel == 'useredit') && $fieldset == '') {
        USES_membership_class_membership();
        $M = new \Membership\Membership($uid);
        if (MEMBERSHIP_isManager()) {
            $retval .= $M->Editform();
        } else {
            $retval .= $M->ShowInfo('panel');
        }
    }
    return $retval;
}


/**
*   Save the membership information when edited via Account Settings.
*   Users can't edit their own membership info, only admins.
*   This function is used instead of plugin_profilesave_membership so admin
*   can update their own membership.
*
*   @param  integer $uid    User ID being saved
*/
function plugin_user_changed_membership($uid=0)
{
    // Only managers can update membership data.
    if (!MEMBERSHIP_isManager()) return;

    USES_membership_class_membership();
    $M = new \Membership\Membership($uid);
    $op = '';
    $need_save = false; // assume nothing to do
    $joined = isset($_POST['mem_joined']) ? $_POST['mem_joined'] : '';

    if ($M->isNew && !empty($_POST['mem_plan_id'])) {
        // A new member, previously with no plan
        $M->Add($uid, $_POST['mem_plan_id'], $_POST['mem_expires'], $joined);
        $op = 'created';
    } elseif (isset($_POST['mem_quickrenew'])) {
        // A renewing member via quick renewal
        $M->Renew($_POST);
        $op = 'renewed';
    } elseif (isset($_POST['mem_cancel'])) {
        // Cancel the membership
        Membership::Cancel($uid);
    } else {
        // Maybe just updating options
        $vars = array('notified', 'expires', 'joined', 'position', 'plan_id',
                'number', 'istrial');
        foreach ($vars as $var) {
            if (isset($_POST['mem_'.$var]) &&
                $_POST['mem_'.$var] != $_POST['mem_'.$var.'_orig']) {
                $need_save = true;
                $op = 'updated';
                break;
            }
        }

        // Check the linked accounts for changes. If changed, just call
        // the membership Save() function.
        if (!$need_save) {  // if we don't already need to save
            $new_links = \Membership\Membership::getRelatives($uid);
            $old_links = json_decode($_POST['mem_old_links'], true);
            if (is_array($old_links)) { // check for valid json
                foreach ($new_links as $uid=>$name) {
                    if (!isset($old_links[$uid])) {
                        $need_save = true;
                        break;
                    }
                }
                if (!$need_save) {
                    foreach ($old_links as $uid=>$name) {
                        if (!isset($new_links[$uid])) {
                            $need_save = true;
                        }
                    }
                }
            } else {
                // force save if links json is missing or bad
                $need_save = true;
            }
        }
    }

    // Perform a general save if required
    if ($need_save) {
        $M->Save($_POST);
    }

    // Log a transaction if any payment information was changed.
    foreach (array('mem_pmtdesc', 'mem_pmtamt', 'mem_pmttype') as $pmt) {
        if (!empty($_POST[$pmt])) {
            $M->AddTrans($_POST['mem_pmttype'], $_POST['mem_pmtamt'],
                $_POST['mem_pmtdesc']);
            break;
        }
    }

    // Log a mesage if any operation was done
    if ($op != '') {
        MEMBERSHIP_auditLog('Membership ' . $op . ' for ' .
            COM_getDisplayName($uid) . ' (' . $uid . ')');
    }
}


/**
*   Check that the required information has been entered when saving a user.
*   If the quickrenew checkbox is checked, a plan must also be checked or there
*   will be an error calculating the expiration.
*
*   @param  string  $mode   Type of save. Only 'useredit' is checked.
*   @param  string  $username   User login name. Not used.
*   @return mixed       Empty value on success, numeric message ID on failure
*/
function plugin_itemPreSave_membership($mode, $username)
{
    if ($mode == 'useredit' &&
            isset($_POST['mem_quickrenew']) && empty($_POST['mem_plan_id'])) {
        return 97;
    } else {
        return '';
    }
}


/**
*   Displays membership info in the user's profile.
*
*   @param integer  $uid    User ID whose profile is being displayed
*   @param object   &$T     Pointer to template object
*   @return string          HTML to include in profile page
*/
function plugin_profilevariablesdisplay_membership($uid, &$T)
{
    global $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP, $_USER;

    // non-admins can only see their own subscription.
    if ($uid != $_USER['uid'] && !MEMBERSHIP_isManager())
        return '';

    USES_membership_class_membership();
    $M = new \Membership\Membership($uid);
    $existingFields = $T->get_var('plugin_fields');
    $T->set_var('plugin_fields', $existingFields . $M->ShowInfo(false, $uid));
}


/**
*   Get the URL to the localized configuration help file.
*
*   @return array   Array of (url, window_type)
*/
function plugin_getconfigelementhelp_membership($option, $doclang='english')
{
    $doc_url = MEMBERSHIP_getDocURL('config.html', $doclang);
    if (empty($doc_url))
        return false;

    if (!empty($option)) {
        $file .= '#desc_' . $option;
    }

    return array($doc_url . $file, 0);
}


/**
*   Get additional Javascript into the site header.
*
*   @return array   List of paths to Javascript files
*/
function plugin_getheaderjs_membership()
{
    $pi_dir = MEMBERSHIP_PI_PATH . '/js/';
    $files = array(
        $pi_dir . 'util.js',
    );
    return $files;
}


/**
*   Get the link to a documentation file.
*   Returns the URL to the file, or to the directory if no file requested.
*
*   @see    plugin_getconfigelementhelp_membership()
*   @param  string  $file       HTML file
*   @param  string  $doclang    Documentation language
*   @return string              URL to document directory or file
*/
function MEMBERSHIP_getDocURL($file, $doclang='')
{
    global $_CONF, $_CONF_MEMBERSHIP;

    if ($doclang == '') $doclang = $_CONF['language'];
    $doc_path = $_CONF['path_html'] . $_CONF_MEMBERSHIP['pi_name'] .
                "/docs/$doclang/";
    $doc_url = '';

    if ($file != '') {
        if (!is_file($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = MEMBERSHIP_PI_URL . "/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = MEMBERSHIP_PI_URL . "/docs/$doclang/";
    }

    return $doc_url;
}


/**
*   Display a formatted error message.
*   This returns the provided message formatted using glFusion's
*   typography options.
*
*   @param  string $msg     Error message to display
*   @param  string $type    Type of message, used for style and header
*   @param  string $header  Optional header text.
*   @return string          HTML for formatted error message
*/
function MEMBERSHIP_errorMessage($msg, $type = '', $header = '')
{
    global $LANG_MEMBERSHIP;

    // If no header text specified, we'll put in a default.
    // Also validate the message type in this switch.
    switch (strtolower($type)) {
    case 'info':
        $class = 'info';
        if ($header == '') $header = $LANG_MEMBERSHIP['info'];
        break;

    case 'note':
    case 'warn':
        $class = 'note';
        if ($header == '') $header = $LANG_MEMBERSHIP['warning'];
        break;

    case 'alert':
    default:
        $class = 'alert';
        if ($header == '') $header = $LANG_MEMBERSHIP['alert'];
        break;
    }

    return "<span class=\"$class\">"
            . COM_startBlock($header)
            . $msg
            . COM_endBlock()
            . '</span>';
}


/**
*   Configuration helper. Select form to use for membership application.
*
*   @return array Array of form name & id
*/
function plugin_configmanager_select_app_form_membership()
{
    global $_TABLES, $LANG_MEMBERSHIP;

    $status = LGLIB_invokeService('forms', 'getFormInfo', array(),
            $output, $msg);
    $A = array($LANG_MEMBERSHIP['none'] => '');
    if ($status == PLG_RET_OK) {
        foreach ($output as $form) {
            $A[$form['name']] = $form['id'];
        }
    }
    return $A;
}


/**
*   Configuration helper. Select group to which members are added.
*
*   @return array Array of groups
*/
function plugin_configmanager_select_member_group_membership()
{
    return MEMBERSHIP_groupSelection();
}


/**
*   Configuration helper. Select group to which members are added.
*
*   @return array Array of groups
*/
function plugin_configmanager_select_member_all_group_membership()
{
    return MEMBERSHIP_groupSelection();
}


/**
*   Get a selection of groups for current and arrears members
*
*   @return array   Array of groups
*/
function MEMBERSHIP_groupSelection()
{
    global $_TABLES;

    $A = array();
    $query = "SELECT grp_id, grp_name FROM {$_TABLES['groups']}";
    $result = DB_query($query);
    while ($row = DB_fetchArray($result)) {
        $row['grp_name'] = htmlspecialchars($row['grp_name']);
        $A[$row['grp_name']] = $row['grp_id'];
    }
    return $A;
}


/**
*   Perform scheduled functions, such as notifications and cancellations.
*
*   @uses   Membership::Arrears()
*   @uses   Membership::Expire()
*/
function plugin_runScheduledTask_membership()
{
    global $_TABLES, $_CONF_MEMBERSHIP, $_CONF, $LANG_MEMBERSHIP;

    // Cancel memberships not renewed within the grace period
    // Should already be in arrears, but catch active ones also
    $stat = MEMBERSHIP_STATUS_ENABLED . ',' . MEMBERSHIP_STATUS_ACTIVE . ',' .
            MEMBERSHIP_STATUS_ARREARS;
    $sql = "SELECT m.mem_uid, m.mem_expires, u.fullname
            FROM {$_TABLES['membership_members']} m
            LEFT JOIN {$_TABLES['users']} u
                ON u.uid = m.mem_uid
            WHERE mem_status IN ($stat)
            AND mem_expires < '{$_CONF_MEMBERSHIP['dt_end_grace']}'";
    //COM_errorLog($sql);
    $r = DB_query($sql, 1);
    if ($r) {
        USES_membership_class_membership();
        while ($row = DB_fetchArray($r, false)) {
            Membership::Expire($row['mem_uid'], true);
            MEMBERSHIP_auditLog(sprintf($LANG_MEMBERSHIP['log_expired'],
                $row['mem_uid'], $row['fullname']), true);
        }
    }

    // Set overdue memberships to "in arrears". Runs nearly the same query
    // as the expiration above since expired members now have their statuses
    // changed to "expired"
    $stat = MEMBERSHIP_STATUS_ENABLED . ',' . MEMBERSHIP_STATUS_ACTIVE;
    $sql = "SELECT m.mem_uid, m.mem_expires, u.fullname
            FROM {$_TABLES['membership_members']} m
            LEFT JOIN {$_TABLES['users']} u
                ON u.uid = m.mem_uid
            WHERE mem_status IN ($stat)
            AND mem_expires < '{$_CONF_MEMBERSHIP['today']}'";
    //COM_errorLog($sql);
    $r = DB_query($sql, 1);
    if ($r) {
        USES_membership_class_membership();
        while ($row = DB_fetchArray($r, false)) {
            Membership::Arrears($row['mem_uid'], true);
            MEMBERSHIP_auditLog(sprintf($LANG_MEMBERSHIP['log_arrears'],
                $row['mem_uid'], $row['fullname']), true);
        }
    }

    // Purge old membership records.
    $days = (int)$_CONF_MEMBERSHIP['drop_days'];
    if ($days > -1) {
        $sql = "UPDATE {$_TABLES['membership_members']}
            SET mem_status = " . MEMBERSHIP_STATUS_DROPPED . " WHERE
            '{$_CONF_MEMBERSHIP['today']}' > (expires + interval $days DAY)";
        $res = DB_query($sql, 1);
        $num = DB_affectedRows($res);
        MEMBERSHIP_auditLog(sprintf($LANG_MEMBERSHIP['log_dropped'],
                $num, $days), true);
    }

    // For the memberships that are left, notify the members of
    // impending expiration.
    MEMBERSHIP_notifyExpiration();
}


/**
*   Notify users that have memberships soon to expire.
*   This is in functions.inc so it can be called from runscheduledTask.
*/
function MEMBERSHIP_notifyExpiration()
{
    global $_TABLES, $_CONF, $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP, $_USER;

    // Return if we're not configured to notify users.
    if ($_CONF_MEMBERSHIP['notifydays'] < 0 ||
        $_CONF_MEMBERSHIP['notifymethod'] == MEMBERSHIP_NOTIFY_NONE) {
        return;
    }
    $exp_check = date('Y-m-d',
        strtotime('+' . $_CONF_MEMBERSHIP['notifydays'] . ' day',
            strtotime($_CONF_MEMBERSHIP['today'])));
    $stat = MEMBERSHIP_STATUS_ACTIVE . ',' . MEMBERSHIP_STATUS_ENABLED;
    $sql = "SELECT m.mem_uid, m.mem_notified,
                p.plan_id, p.name, p.description,
                u.email, m.mem_expires
            FROM {$_TABLES['membership_members']} m
            LEFT JOIN {$_TABLES['membership_plans']} p
                ON p.plan_id = m.mem_plan_id
            LEFT JOIN {$_TABLES['users']} u
                ON u.uid = m.mem_uid
            WHERE m.mem_notified = 0
            AND m.mem_status IN ($stat)
            AND m.mem_expires < '$exp_check'";
    //COM_errorLog($sql);
    $r = DB_query($sql);
    if (!$r)
        return;

    // Expiration notifications are likely to be done in large batches.
    // Create an array of plans to avoid having to instantiate a plan for
    // each member.
    $Plans = array();

    $template_base = MEMBERSHIP_PI_PATH . '/templates/notify';
    $notified_ids = array();    // holds memberhsip IDs that get notified

    while ($row = DB_fetchArray($r, false)) {

        if ($_CONF_MEMBERSHIP['notifymethod'] & MEMBERSHIP_NOTIFY_EMAIL) {
            // Create a notification email message.
            $username = COM_getDisplayName($row['mem_uid']);
            if (!isset($Plans[$row['plan_id']])) {
                $Plans[$row['plan_id']] = new \Membership\Plan($row['plan_id']);
            }
            $P = $Plans[$row['plan_id']];   // shortcut variable

            $args = array(
                'custom'    => array('uid'   => $row['mem_uid']),
                'amount' => $P->Price(false),
                'item_number' => $_CONF_MEMBERSHIP['pi_name'] . ':' . $P->plan_id .
                        ':renewal',
                'item_name' => $P->name,
                'btn_type' => 'buy_now',
            );
            $status = LGLIB_invokeService('paypal', 'genButton', $args, $output, $msg);
            $button = ($status == PLG_RET_OK) ? $output[0] : '';

            $T = new Template($template_base);
            $T->set_file('message', 'expiration.thtml');
            $T->set_var(array(
                'site_name'     => $_CONF['site_name'],
                'username'      => $username,
                'pi_name'       => $_CONF_MEMBERSHIP['pi_name'],
                'product'       => $row['name'],
                'item_id'       => $row['plan_id'],
                'short_description' => $row['description'],
                'detail_url'    => MEMBERSHIP_PI_URL .
                        '/index.php?detail=x&amp;plan_id=' .
                        urlencode($row['plan_id']),
                'buy_button'    => $button,
            ) );

            $T->parse('output','message');
            $message = $T->finish($T->get_var('output'));
            COM_mail(
                "$username <{$row['email']}>",
                "{$LANG_MEMBERSHIP['exp_notice']}",
                $message,
                "{$_CONF['site_name']} <{$_CONF['site_mail']}>",
                true
            );
        }

        if ($_CONF_MEMBERSHIP['notifymethod'] & MEMBERSHIP_NOTIFY_MESSAGE) {
            // Save a message for the next time they log in.
            $msg = sprintf($LANG_MEMBERSHIP['you_expire'],
                    $row['plan_id'], $row['mem_expires']) .
                    ' ' . $LANG_MEMBERSHIP['renew_link'];
            $expire_msg = date('Y-m-d',
                strtotime('-' . $_CONF_MEMBERSHIP['grace_days'] . ' day',
                    strtotime($row['mem_expires'])));
            LGLIB_storeMessage(array(
                'message' => $msg,
                'expires' => $expire_msg,
                'uid' => $row['mem_uid'],
                'persist' => true,
                'pi_code' => MEMBERSHIP_MSG_EXPIRING,
                'use_sess_id' => false)
            );
        }

        // Record that we've notified this member
        $notified_ids[] = $row['mem_uid'];
    }

    // Mark that the expiration notification has been sent.
    if (!empty($notified_ids)) {
        $ids = implode(',', $notified_ids);
        $sql = "UPDATE {$_TABLES['membership_members']}
                SET mem_notified=1
                WHERE mem_uid IN ($ids)";
        DB_query($sql, 1);
        if (DB_error()) {
            COM_errorLog("membership: error executing $sql");
        }
    }
}


/**
*   Check if the current user is allowed to manage memberships
*
*   @return boolean     True is user is a manager, False if not
*/
function MEMBERSHIP_isManager()
{
    global $_CONF_MEMBERSHIP;

    static $isManager = -1;     // static since this can be called many times
    if ($isManager === -1) {
        if (MEMBERSHIP_isAdmin() ||
                SEC_hasRights($_CONF_MEMBERSHIP['pi_name'] . '.manage')) {
            $isManager = true;
        } else {
            $isManager = false;
        }
    }
    return $isManager;
}


/**
*   Check if the current user is allowed to administer the plugin
*
*   @return boolean     True is user is an admin, False if not
*/
function MEMBERSHIP_isAdmin()
{
    global $_CONF_MEMBERSHIP;

    static $isAdmin = -1;       // static since this can be called many times
    if ($isAdmin === -1) {
        if (SEC_hasRights($_CONF_MEMBERSHIP['pi_name'] . '.admin')) {
            $isAdmin = true;
        } else {
            $isAdmin = false;
        }
    }
    return $isAdmin;
}


/**
*   Log activity to a plugin-specific log file.
*   Message includes the user who initiated the action, unless it's a
*   system-generated event such as a scheduled task.
*
*   @param  string  $logentry   Text to log
*   @param  boolean $system     True if this is a system-generated entry
*/
function MEMBERSHIP_auditLog($logentry = '', $system=false)
{
    global $_CONF, $_CONF_MEMBERSHIP, $_USER, $LANG01, $LANG_MEMBERSHIP;

    if ($logentry == '')
        return '';

    // A little sanitizing
    $logentry = str_replace(
            array('<?', '?>'),
            array('(@', '@)'),
            $logentry);

    $timestamp = strftime('%c');
    $logfile = $_CONF['path_log'] . $_CONF_MEMBERSHIP['pi_name'] . '.log';

    // Can't open the log file?  Return an error
    if (!$file = fopen($logfile, 'a+')) {
        return $LANG01[33] . $logfile . ' (' . $timestamp . ')<br />' . LB;
    }

    if ($system == false) {
        // Get the user name if it's not anonymous
        if (isset($_USER['uid'])) {
            $byuser = $_USER['uid'] . '-'.
                    COM_getDisplayName($_USER['uid'],
                        $_USER['username'], $_USER['fullname']);
        } else {
            $byuser = 'anon';
        }
        $byuser .= '@' . $_SERVER['REMOTE_ADDR'];
    } else {
        $byuser = $LANG_MEMBERSHIP['system_task'];
    }

    // Write the log entry to the file
    fputs($file, "$timestamp ($byuser) - $logentry\n");
}


/**
*   Send debug info to the error log.
*
*   @param  string  $msg    Text message to display, may be blank.
*/
function MEMBERSHIP_debug($msg)
{
    global $_CONF_MEMBERSHIP;
    if ($_CONF_MEMBERSHIP['debug']) {
        COM_errorLog('MEMBERSHIP DEBUG: ' . $msg);
    }
}


/**
*   Actions to perform when a user account is created.
*   Sets a message to be displayed at first login encouraging a new user
*   to join the organization.
*   To disable, make $LANG_MEMBERSHIP['new_acct_msg'] empty.
*
*   @param  integer $uid    ID of new user
*/
function plugin_user_create_membership($uid)
{
    global $LANG_MEMBERSHIP;

    $uid = (int)$uid;
    if (empty($LANG_MEMBERSHIP['new_acct_msg']) || $uid < 3) return;
    LGLIB_storeMessage(array(
            'message' => $LANG_MEMBERSHIP['new_acct_msg'],
            'uid' => $row['mem_uid'],
            'persist' => true,
            'use_sess_id' => false,
        ) );
}


/**
*   Actions when a user account is deleted.
*   Delete the membership completely, and delete from the newusers table in case
*   they never logged in, just to keep it clean.
*
*   @param  integer $uid    User ID being deleted
*/
function plugin_user_delete_membership($uid)
{
    global $_TABLES;

    USES_membership_class_membership();
    MEMBERSHIP_auditLog('Membership deleted for user ' . $uid . ' (' .
        COM_getDisplayName($uid) . ') due to account deletion.');

    \Membership\Membership::Delete($uid);
    // In case the member never logged in
    //DB_delete($_TABLES['membership_newusers'], 'uid', $uid);
}


/**
*   Support merging local accounts into remote accounts
*
*   @param  integer $origUID    Original (remote) user ID
*   @param  integer $destUID    Merged (local) user ID
*/
function plugin_user_move_membership($origUID, $destUID)
{
    global $_TABLES;

    $origUID = (int)$origUID;
    $destUID = (int)$destUID;

    DB_query("UPDATE {$_TABLES['membership_members']}
            SET uid = $destUID WHERE uid = $origUID", 1);
}


/**
*   Implements the [membership:] autotag.
*
*   @param  string  $op         Name of operation
*   @param  string  $content    Content of the autotag
*   @param  string  $autotag    Complete autotag
*   @return string              HTML replacement
*/
function plugin_autotags_membership($op, $content = '', $autotag = '')
{
    global $_TABLES, $LANG_MEMBERSHIP;

    if ($op == 'tagname' ) {
        return 'membership';
    } else if ($op == 'parse') {

        $HTML = '';
        $option = COM_applyFilter($autotag['parm1']);
        switch ($option) {
        case 'grouplist':
            $parms = explode(' ', COM_applyFilter($autotag['parm2']));
            $groupname = $parms[0];
            if (empty($groupname)) return $content;
            $show_title = true;
            for ($i = 1; $i < count($parms); $i++) {
                switch ($parms[$i]) {
                case 'notitle':
                    $show_title = false;
                    break;
                }
            }

            USES_lib_user();

            $sql = "SELECT p.*,u.username,u.fullname,u.email
                FROM {$_TABLES['membership_positions']} p
                LEFT JOIN {$_TABLES['users']} u
                    ON u.uid = p.uid
                WHERE p.type ='" .
                DB_escapeString($groupname) . "'
                ORDER BY p.orderby";
            //echo $sql;die;
            $res = DB_query($sql);

            $T = new Template(MEMBERSHIP_PI_PATH . '/templates');
            $T ->set_file(array(
                'groups' => 'groups.thtml',
            ));

            $T->set_var('list_name', $poslist);

            while ($A = DB_fetchArray($res, false)) {
                $T->set_block('groups', 'userRow', 'uRow');
                if ($A['uid'] == 0) {    // vacant position
                    $user_img = '';
                    $show_vacant = $A['show_vacant'] ? 'true' : '';
                    $username = '';
                } else {
                    $user_img = USER_getPhoto($A['uid']);
                    $username = COM_getDisplayName($A['uid'], $A['username'],
                                    $A['fullname']);
                    $show_vacant = '';
                }
                $T->set_var(array(
                    'title' => $show_title ?
                        sprintf($LANG_MEMBERSHIP['title_positionpage'], $groupname) : '',
                    'position'  => $A['descr'],
                    'user_name' => $username,
                    'comment'   => trim($A['comment']),
                    'show_vacant' => $show_vacant,
                    'user_img'  => $user_img,
                    'user_email' => empty($A['contact']) ?
                        $LANG_MEMBERSHIP['contact'] : $A['contact'],
                    'uid'       => $A['uid'],
                ) );
                $T->parse('uRow', 'userRow', true);
            }
            $T->parse('output', 'groups');
            $HTML = $T->finish($T->get_var('output'));
            return str_replace($autotag['tagstr'],$HTML,$content);
            break;
        }
    } else {
        return $content;
    }
}


/**
*   Get the current membership info.
*   Uses a static variable for efficiency
*
*   @return object  Membership object
*/
function MEMBERSHIP_currentMembership()
{
    static $current = NULL;

    if ($current === NULL) {
        USES_membership_class_membership();
        $current = new \Membership\Membership();
    }
    return $current;
}


/**
*   Get the description for different member types.
*   Used with Mailchimp integration to set the group (segment) in the list.
*   The strings must match segments (groups) set up in Mailchimp.
*
*   @return array   array(int=>string) of membership statuses
*/
function MEMBERSHIP_memberstatuses()
{
    global $_CONF_MEMBERSHIP;
    $types = array(
        MEMBERSHIP_STATUS_ACTIVE => $_CONF_MEMBERSHIP['segment_active'],
        MEMBERSHIP_STATUS_ARREARS => $_CONF_MEMBERSHIP['segment_arrears'],
        MEMBERSHIP_STATUS_EXPIRED => $_CONF_MEMBERSHIP['segment_expired'],
        MEMBERSHIP_STATUS_DROPPED => $_CONF_MEMBERSHIP['segment_dropped'],
    );
    return $types;
}


/**
*   Helper function to get a template object.
*
*   @param  string  $name   Base name of template, e.g. "editor"
*   @param  string  $id     ID of template, e.g. "editform"
*   @param  string  $path   Optional path if not "templates/"
*   @return string      Full filename, e.g. "editor.uikit.thtml"
*/
function MEMBERSHIP_getTemplate($name, $id, $path='')
{
    global $_CONF_MEMBERSHIP;

    // May use "switch" later, for now there are only two options
    $tpltype = $_CONF_MEMBERSHIP['_is_uikit'] ? '.uikit' : '';
    $filename = "$name$tpltype.thtml";
    if (substr($path, 0, 9) != 'templates') {
        $path = 'templates/' . $path;
    }
    if (!file_exists(MEMBERSHIP_PI_PATH . "/$path/$filename")) {
        $filename = "$name.thtml";
    }
    $T = new Template(MEMBERSHIP_PI_PATH . '/' . $path);
    $T->set_file($id, $filename);
    return $T;
}


/**
*   Get an icon from the CSS icon set, either UIkit or FontAwesome
*
*   @param  string  $name   Base name of icon, e.g. "edit" for "uk-icon-edit"
*   @param  string  $style  Style name from local CSS
*   @return string      Class names for icon
*/
function MEM_getIcon($name, $style='info')
{
    global $_CONF_MEMBERSHIP;

    if ($style != '')
        $style = ' mbr-icon-' . $style;
    return $_CONF_MEMBERSHIP['_iconset'] . '-' . $name . $style;
}

function MEMBERSHIP_iconPrefix()
{
    global $_CONF_MEMBERSHIP;
    return $_CONF_MEMBERSHIP['_is_uikit'] ? 'uk-icon-' : 'fa fa-icon-';
}

?>
