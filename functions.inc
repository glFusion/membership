<?php
/**
 * Public API functions for the Membership plugin for glFusion.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

spl_autoload_register(function ($class)
{
    // project-specific namespace prefix
    $prefix = 'Membership\\';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/classes/';

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.class.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
use Membership\Config;
use Membership\Cache;
use Membership\Models\Request;
use glFusion\Log\Log;
use glFusion\Database\Database;

$level = Config::get('log_level', -1);
if ($level < 0) {
    $level = $_CONF['log_level'];
}
Log::config(
    Config::PI_NAME,
    array(
        'type'  => 'file',
        'path'  => $_CONF['path_log'],
        'file'  => Config::PI_NAME . '.log',
        'level' => $level,
    )
);

/** Include static plugin config values */
require_once 'membership.php';

/** Determine the correct language file, falling back to English as default. */
$langpath = __DIR__ . '/language';
$langfile = $_CONF['language'] . '.php';
if (!file_exists("$langpath/$langfile")) {
    $langfile = 'english_utf-8.php';
}

/** Include plugin language file */
require_once "$langpath/$langfile";
if (file_exists("$langpath/custom/$langfile")) {
    /** Include custom language file, if any */
    include_once "$langpath/custom/$langfile";
}

// Import config.php last since the field definitions rely on the language.
if (file_exists(__DIR__ . '/config.php')) {
/** Include local config values and overrides */
    include_once __DIR__ . '/config.php';
}

require_once __DIR__ . '/services.inc.php';

/**
 * Called by the plugin Editor to display the current plugin code version.
 * This may be different than the version installed and registered currently.
 * If newer then you may want to run the update.
 *
 * @return  string  version number
 */
function plugin_chkVersion_membership()
{
    return Config::get('pi_version');
}


/**
 * Returns the items for this plugin that should appear on the main menu.
 *
 * @return  array   Associative array of menu items to be added to menu
 */
function plugin_getmenuitems_membership()
{
    global $LANG_MEMBERSHIP;

    $icon = '&nbsp;<i class="uk-icon uk-icon-group"></i>';
    $menuitems = array(
        $LANG_MEMBERSHIP['block_title'] . $icon => Config::get('url') . '/index.php',
    );
    return $menuitems;
}


/**
 * This will put an option for membership administration in the command and control area.
 *
 * @return  mixed   array(title, url, icon), or false when not allowed
 */
function plugin_cclabel_membership()
{
    global $LANG_MEMBERSHIP;

    if (MEMBERSHIP_isManager()) {
        return array(
            $LANG_MEMBERSHIP['menu_title'],
            Config::get('admin_url') . '/index.php',
            plugin_geticon_membership()
        );
    } else {
        return false;
    }
}


/**
 * Put an option for membership management on the admin-only menu.
 *
 * @return  mixed   array(title, url, num. banner), or void when not allowed
 */
function plugin_getadminoption_membership()
{
    global $_CONF, $LANG_MEMBERSHIP;

    if (MEMBERSHIP_isManager()) {
        return array(
            $LANG_MEMBERSHIP['menu_title'],
            Config::get('admin_url') . '/index.php',
        );
    } else {
        return false;
    }
}


/**
 * Get the user menu option for this plugin.
 *
 * @return  array   Array of menu items.
 */
function plugin_getuseroption_membership()
{
    return '';
}


/**
 * Get additional CSS into the site header.
 * Checks first for a layout-specific version, then loads the default.
 *
 * @return  array   List of paths to Javascript files
 */
function plugin_getheadercss_membership()
{
    global $_CONF;

    $styles = array();
    $files = array('style.css');

    $defpath = __DIR__ . '/css';
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
 * Update the Membership plugin.
 *
 * @param   boolean $dvlp   True if this is a development update
 * @return  boolean     True on success, False on failure
 */
function plugin_upgrade_membership($dvlp=false)
{
    require_once __DIR__ . '/upgrade.inc.php';
    return MEMBERSHIP_do_upgrade($dvlp);
}


/**
 * Returns the URL of the plugin's icon.
 *
 * @return  string      URL of the icon
 */
function plugin_geticon_membership ()
{
    global $_CONF;
    return Config::get('url') . '/images/membership.png';
}


/**
 * Automatic uninstall function for plugins.
 *
 * This code is automatically uninstalling the plugin.
 * It passes an array to the core code function that removes
 * tables, groups, features and php blocks from the tables.
 * Additionally, this code can perform special actions that cannot be
 * foreseen by the core code (interactions with other plugins for example)
 *
 * @return  array     Elements to be removed
 */
function plugin_autouninstall_membership ()
{
    $out = array (
        // give the name of the tables, without $_TABLES[]
        'tables' => array(
                'membership_members',
                'membership_plans',
                'membership_links',
                'membership_log',
                'membership_trans',
                'membership_positions',
                'membership_posgroups',
                'membership_users',
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
 * Create the membership info block.
 *
 * @return  string      HTML for the block
 */
function phpblock_membership_memberinfo()
{
    global $_GROUPS, $LANG_MEMBERSHIP;

    $retval = '';

    $M = Membership\Membership::getCurrent();
    if (!$M->isNew()) {
        $retval .= sprintf(
            $LANG_MEMBERSHIP['blk_member_info'],
            $M->getPlan()->getShortName(), $M->getExpires()
        );
    } else {
        $retval .= $LANG_MEMBERSHIP['blk_join_now'];
    }
    return $retval;
}


/**
 * Edit membership information in the user's profile.
 * This feature is only open to membership managers.  Users can't edit
 * their own.
 *
 * @param   integer $uid        User ID being edited
 * @param   string  $panel
 * @param   string  $fieldset
 * @return  string      HTML for edit form
 */
function plugin_profileedit_membership($uid, $panel, $fieldset)
{
    global $_CONF, $_USER;

    $retval = '';

    if (($panel == '' || $panel == 'useredit') && $fieldset == '') {
        $M = Membership\Membership::getInstance($uid);
        if (MEMBERSHIP_isManager()) {
            $retval .= $M->Editform();
        } else {
            $retval .= $M->showInfo('panel');
        }
    }
    return $retval;
}


/**
 * Save the membership information when edited via Account Settings.
 * Users can't edit their own membership info, only admins.
 * This function is used instead of plugin_profilesave_membership so admin
 * can update their own membership.
 *
 * @param   integer $uid    User ID being saved
 */
function plugin_user_changed_membership($uid=0)
{
    global $_USER;

    // Only managers can update membership data.
    if (!MEMBERSHIP_isManager()) return;

    $Request = Request::getInstance();

    // First save the updated membership info.
    $M = Membership\Membership::getInstance($uid);
    $M->Save($Request);

    // Then see if there's a transaction to record.
    $pmt_amt = $Request->getFloat('mem_pmtamt');
    $pmt_dscp = $Request->getString('mem_pmtdesc');
    $pmt_type = $Request->getString('mem_pmttype');
    if (!empty($pmt_amt) || !empty($pmt_dscp) || !empty($pmt_type)) {
        $Txn = new Membership\Models\Transaction;
        $Txn->withGateway($pmt_type)
            ->withUid($uid)
            ->withPlanId($Request->getString('mem_plan_id'))
            ->withAmount($pmt_amt)
            ->withDoneBy((int)$_USER['uid'])
            ->withExpiration($Request->getString('mem_expires'))
            ->withTxnId($pmt_dscp);
        $Txn->save();
    }
}


/**
 * Displays membership info in the user's profile.
 *
 * @param   integer  $uid   User ID whose profile is being displayed
 * @return  string          HTML to include in profile page
 */
function plugin_profileblocksdisplay_membership($uid)
{
    global $_USER;

    // non-admins can only see their own subscription.
    if ($uid != $_USER['uid'] && !MEMBERSHIP_isManager()) {
        return '';
    }

    $M = Membership\Membership::getInstance($uid);
    return $M->showInfo(false, $uid);
}


/**
 * Get the URL to the localized configuration help file.
 *
 * @param   string  $option     Help option to display
 * @param   string  $doclang    Desired language
 * @return  array       Array of (url, window_type)
 */
function plugin_getconfigelementhelp_membership($option, $doclang='english')
{
    $doc_url = MEMBERSHIP_getDocURL('config.html', $doclang);
    if (empty($doc_url))
        return false;

    if (!empty($option)) {
        $doc_url .= '#desc_' . $option;
    }
    return array($doc_url, 0);
}


/**
 * Get additional Javascript into the site header.
 *
 * @return  array   List of paths to Javascript files
 */
function plugin_getheaderjs_membership()
{
    $pi_dir = __DIR__ . '/js/';
    $files = array(
        $pi_dir . 'util.js',
    );
    return $files;
}


/**
 * Get the link to a documentation file.
 * Returns the URL to the file, or to the directory if no file requested.
 *
 * @see     plugin_getconfigelementhelp_membership()
 * @param   string  $file       HTML file
 * @param   string  $doclang    Documentation language
 * @return  string              URL to document directory or file
 */
function MEMBERSHIP_getDocURL($file, $doclang='')
{
    global $_CONF;

    if ($doclang == '') $doclang = $_CONF['language'];
    $doc_path = $_CONF['path_html'] . Config::PI_NAME .
                "/docs/$doclang/";
    $doc_url = '';

    if ($file != '') {
        if (!is_file($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = Config::get('url') . "/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = Config::get('url') . "/docs/$doclang/";
    }

    return $doc_url;
}


/**
 * Display a formatted error message.
 * This returns the provided message formatted using glFusion's
 * typography options.
 *
 * @param   string  $msg    Error message to display
 * @param   string  $type   Type of message, used for style and header
 * @param   string  $header Optional header text.
 * @return  string          HTML for formatted error message
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
 * Configuration helper. Select form to use for membership application.
 *
 * @return  array   Array of form name & id
 */
function plugin_configmanager_select_app_form_id_membership()
{
    global $LANG_MEMBERSHIP;

    $status = PLG_invokeService('forms', 'getFormInfo',
        array(),
        $output,
        $msg
    );
    $A = array($LANG_MEMBERSHIP['none'] => '');
    if ($status == PLG_RET_OK) {
        foreach ($output as $form) {
            $A[$form['name']] = $form['id'];
        }
    }
    return $A;
}


/**
 * Configuration helper. Select group to which members are added.
 *
 * @uses    MEMBERSHIP_groupSelection()
 * @return  array   Array of groups
 */
function plugin_configmanager_select_member_group_membership()
{
    return MEMBERSHIP_groupSelection();
}


/**
 * Configuration helper. Select group to which members are added.
 *
 * @uses    MEMBERSHIP_groupSelection()
 * @return  array   Array of groups
 */
function plugin_configmanager_select_member_all_group_membership()
{
    return MEMBERSHIP_groupSelection();
}


/**
 * Configuration helper to select the plugin used for membrership applications.
 *
 * @return  array   Array of plugins
 */
function plugin_configmanager_select_app_provider_membership()
{
    global $LANG_MEMBERSHIP;

    $retval = array($LANG_MEMBERSHIP['none'] => '');
    foreach (Membership\App::supportedPlugins() as $pi_name) {
        $retval[ucfirst($pi_name)] = $pi_name;
    }
    return $retval;
}

/**
 * Get a selection of groups for current and arrears members.
 *
 * @see     plugin_configmanager_select_member_all_group_membership()
 * @see     plugin_configmanager_select_member_group_membership()
 * @return  array   Array of groups
 */
function MEMBERSHIP_groupSelection()
{
    global $_TABLES;

    $retval = array();
    $sql = "SELECT grp_id, grp_name FROM {$_TABLES['groups']}";
    $db = Database::getInstance();
    try {
        $data = $db->conn->executeQuery($sql)->fetchAll(Database::ASSOCIATIVE);
    } catch (\Throwable $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . '(): ' . $e->getMessage());
        $data = array();
    }
    foreach ($data as $A) {
        $A['grp_name'] = htmlspecialchars($A['grp_name']);
        $retval[$A['grp_name']] = $A['grp_id'];
    }
    return $retval;
}


/**
 * Perform scheduled functions, such as notifications and cancellations.
 *
 * @uses    Membership::Arrears()
 * @uses    Membership::Expire()
 */
function plugin_runScheduledTask_membership()
{
    global $_CONF, $_TABLES, $_VARS;

    // Run once per day. Maybe later there will be tasks to run more often.
    $today = $_CONF['_now']->format('Ymd', true);
    $lastrun = isset($_VARS['membership_lastrun']) ? $_VARS['membership_lastrun'] : '';
    if ($lastrun == $today) {
        return;
    }
    $db = Database::getInstance();
    try {
        $db->conn->executeStatement(
            "INSERT INTO {$_TABLES['vars']} VALUES('membership_lastrun', ?)
            ON DUPLICATE KEY UPDATE value = ?",
            array($today, $today),
            array(Database::STRING, Database::STRING)
        );
    } catch (\Exception $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
    }

    // Track number of affected memberships to see if cache clearing is needed.
    $affected = 0;

    // Expire memberships not renewed within the grace period.
    $affected += Membership\Membership::batchExpire();
    // Set members to "in arrears" if expired but within the grace period.
    $affected += Membership\Membership::batchArrears();
    // Set members to "dropped" if they've been expired for a long time.
    $affected += Membership\Membership::batchPurge();

    // For the current members that are left, notify of impending expiration.
    $Notifier = new Membership\Notifiers\Expiration;
    $Notifier->Notify();

    if ($affected) {
        Cache::clear('members');
    }
}


/**
 * Get mailing list merge fields.
 *
 * @param   integer $uid    Member user ID
 * @return  array       Array of name=>value pairs
 */
function plugin_getMergeFields_membership(int $uid, string $email=NULL)
{
    $properties = array(
        'id',
        'merge_fields',
    );
    $M = Membership\Membership::getInstance($uid);
    $output = $M->getItemInfo($properties);
    if (isset($output['merge_fields'])) {
        return $output['merge_fields'];
    } else {
        return array();
    }
}


/**
 * Provides the getItemInfo function for memberships.
 * The $id parameter may indicate a plan ID or a specific member ID,
 * depending on the fields in $options.
 *
 * @param   string  $id         Membership or Plan ID
 * @param   string  $what       Comma-separated list of fields to return
 * @param   integer $uid        User ID
 * @param   array   $options    Options include type of item being requested.
 * @return  array           Array of product information
 */
function plugin_getiteminfo_membership($id, $what, $uid=0, $options=array())
{
    $parts = explode(':', $id);
    $type = $parts[0];
    $id = isset($parts[1]) ? $parts[1] : 0;
    $properties = explode(',', $what);
    $retval = array();

    switch ($type) {
    case 'plan':
        if ($id != '*') {
            $plans = array(Membership\Plan::getInstance($id));
        } else {
            $plans = Membership\Plan::getPlans();
        }
        foreach ($plans as $plan) {
            $props = $plan->getItemInfo($properties, $options);
            $mapped = array();
            foreach ($props as $key => $value) {
                if ($id == '*') {
                    if ($value != '') {
                        $mapped[$key] = $value;
                    }
                } else {
                    $mapped[$key] = $value;
                }
            }

            if ($id == '*') {
                $retval[] = $mapped;
            } else {
                $retval = $mapped;
                break;
            }
        }
        break;
    case 'membership':
        if ($id == '*') return $retval; // getAll not supported
        $M = Membership\Membership::getInstance($id);
        $retval = $M->getItemInfo($properties, $options);
        break;
    }

    if (($id != '*') && (count($retval) == 1)) {
        $tRet = array_values($retval);
        $retval = $tRet[0];
    }
    return $retval;
}


/**
 * Check if the current user is allowed to manage memberships.
 *
 * @return  boolean     True is user is a manager, False if not
 */
function MEMBERSHIP_isManager()
{
    static $isManager = -1;     // static since this can be called many times
    if ($isManager === -1) {
        if (
            MEMBERSHIP_isAdmin() ||
            SEC_hasRights(Config::PI_NAME . '.manage')
        ) {
            $isManager = true;
        } else {
            $isManager = false;
        }
    }
    return $isManager;
}


/**
 * Check if the current user is allowed to administer the plugin.
 *
 * @return  boolean     True is user is an admin, False if not
 */
function MEMBERSHIP_isAdmin()
{
    static $isAdmin = -1;       // static since this can be called many times
    if ($isAdmin === -1) {
        if (SEC_hasRights(Config::PI_NAME . '.admin')) {
            $isAdmin = true;
        } else {
            $isAdmin = false;
        }
    }
    return $isAdmin;
}


/**
 * Actions to perform when a user account is created.
 * Sets a message to be displayed at first login encouraging a new user
 * to join the organization.
 *
 * @param   integer $uid    ID of new user
 */
function plugin_user_create_membership($uid)
{
    global $LANG_MEMBERSHIP;

    if (Config::get('first_login_msg') && !empty($LANG_MEMBERSHIP['new_acct_msg'])) {
        $Msg = new Membership\Notifiers\Popup;
        $Msg->withUid((int)$uid)
            ->withMessage($LANG_MEMBERSHIP['new_acct_msg'])
            ->withPersists(true)
            ->send();
    }
}


/**
 * Actions when a user account is deleted.
 * - Delete the membership completely.
 *
 * @param  integer $uid    User ID being deleted
 */
function plugin_user_delete_membership($uid)
{
    Log::write(Config::PI_NAME, Log::INFO,
        'Membership deleted for user ' . $uid . ' (' .
        COM_getDisplayName($uid) . ') due to account deletion.'
    );
    Membership\Membership::getInstance($uid)->Delete();
}


/**
 * Support merging local accounts into remote accounts.
 *
 * @param  integer $origUID    Original (remote) user ID
 * @param  integer $destUID    Merged (local) user ID
 */
function plugin_user_move_membership($origUID, $destUID) : void
{
    global $_TABLES;

    $origUID = (int)$origUID;
    $destUID = (int)$destUID;

    $db = Database::getInstance();
    try {
        $db->conn->executeQuery(
            "UPDATE {$_TABLES['membership_members']}
            SET mem_uid = ? WHERE mem_uid = ?",
            array($destUID, $origUID),
                array(Database::INTEGER, Database::INTEGER)
        );
    } catch (\Throwable $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . '(): ' . $e->getMessage());
    }
}


/**
 * Implements the [membership:] autotag.
 *
 * @param   string  $op         Name of operation
 * @param   string  $content    Content of the autotag
 * @param   string  $autotag    Complete autotag
 * @return  string              HTML replacement
 */
function plugin_autotags_membership($op, $content = '', $autotag = '')
{
    global $LANG_MEMBERSHIP;

    switch ($op) {
    case 'tagname':
        return 'membership';
        break;
    case 'tagusage':
        return array(array('namespace' => 'membership', 'usage' => 'description'));
        break;
    case 'desc':
        switch ($content) {
        case 'membership':
            return $LANG_MEMBERSHIP['at_dscp_' . $content];
            break;
        default:
            return '';
            break;
        }
        break;
    case 'parse':
        $HTML = '';     // holder for the autotag replacement
        $option = COM_applyFilter($autotag['parm1']);
        switch ($option) {
        case 'grouplist':
            $parms = explode(' ', COM_applyFilter($autotag['parm2']));
            $groupname = $parms[0];
            if (empty($groupname)) {
                return $content;
            }
            $show_title = true;
            for ($i = 1; $i < count($parms); $i++) {
                switch ($parms[$i]) {
                case 'notitle':
                    $show_title = false;
                    break;
                }
            }

            $GL = new Membership\GroupList($groupname);
            $HTML = $GL->showTitle($show_title)->Render();
            return str_replace($autotag['tagstr'],$HTML,$content);
            break;
        }
        break;
    default:
        return $content;
        break;
    }
}


/**
 * Return true to enable service functions.
 *
 * @return  boolean     True to enable web services
 */
function plugin_wsEnabled_membership()
{
    return true;
}


/**
 * Set template variables.
 *
 * @param   string  $templatename   Name of template, e.g. 'header'
 * @param   ref     $template       Reference to actual template
 */
function plugin_templateSetVars_membership($templatename, &$template)
{
    static $messages = NULL;

    // Get messages to display to the user and put them in the template
    // This can be called more than once per page display, so load
    // the messages only once.
    if ($templatename == 'footer') {
        // Have to do this during the 'footer' template to make sure it's
        // only done once.
        if ($messages === NULL) {
            $messages = Membership\Notifiers\Popup::showAll();
        }
        if (!empty($messages)) {
            $info_block = $template->get_var('info_block') . $messages;
            $template->set_var('info_block', $info_block);
        }
    }
}
