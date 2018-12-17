<?php
/**
 * Class to handle membership records.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2018 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.2.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;

/**
 * Class for a membership record.
 * @package membership
 */
class Membership
{
    /** Local properties accessed via `__set()` and `__get()`.
     * @var array */
    var $properties = array();

    /** Flag to indicate that this is a new record.
     * @var boolean */
    var $isNew;

    /** Membership plan related to this membership.
     * @var object */
    var $Plan;

    /**
     * Constructor.
     * Create a members object for the specified user ID,
     * or the current user if none specified.  If a key is requested,
     * then just build the members for that key (requires a $uid).
     *
     * @param   integer $uid    Optional user ID
     */
    public function __construct($uid=0)
    {
        global $_USER, $_CONF_MEMBERSHIP;

        if ($uid == 0) $uid = (int)$_USER['uid'];
        $this->uid = $uid;
        $this->isNew = true;
        $this->plan_id = '';
        $this->Plan = NULL;
        $this->status = MEMBERSHIP_STATUS_DROPPED;
        $this->expires = MEMBERSHIP_today();
        $this->joined = $this->expires;
        $this->paid = '';
        $this->notified = 0;
        $this->old_status = $this->status;
        $this->mem_number = '';
        $this->istrial = 0;
        $this->guid = '';
        if ($this->uid > 1 && $this->Read($this->uid)) {
            $this->isNew = false;
        }
    }


    /**
     * Set a local property.
     *
     * @param   string  $key    Name of property to set
     * @param   mixed   $value  Value to set
     */
    public function __set($key, $value)
    {
        global $LANG_MEMBERSHIP;

        switch ($key) {
        case 'plan_id':
            $this->properties[$key] = COM_sanitizeId($value, false);
            break;

        case 'notified':
        case 'istrial':
            $this->properties[$key] = $value == 1 ? 1 : 0;
            break;

        case 'uid':
        case 'status':
        case 'old_status':
            $this->properties[$key] = (int)$value;
            break;

        case 'joined':
        case 'expires':
        case 'paid':
        case 'mem_number':
        case 'guid':
            $this->properties[$key] = trim($value);
            break;
        }

    }


    /**
     * Return a property's value, or NULL if not set.
     *
     * @param   string  $name   Name of property to get
     * @return  mixed   Value of property identified as $name
     */
    public function __get($name)
    {
        if (isset($this->properties[$name])) {
            return $this->properties[$name];
        } else {
            return NULL;
        }
    }


    /**
     * Get an instance of a specific membership.
     *
     * @param   integer $uid    User ID to retrieve, default=current user
     * @return  object      Membership object
     */
    public static function getInstance($uid = 0)
    {
        global $_USER;

        if ($uid == 0) $uid = $_USER['uid'];
        $uid = (int)$uid;
        if ($uid > 1) {
            $cache_key = 'member_' . $uid;
            $retval = Cache::get($cache_key);
            if ($retval === NULL) {
                $retval = new self($uid);
                Cache::set($cache_key, $retval, 'members');
            }
        } else {
            $retval = new self();
        }
        return $retval;
    }


    /**
     * Read all members variables into the $items array.
     * Set the $uid paramater to read another user's membership into
     * the current object instance.
     *
     * @param   integer $uid    User ID
     */
    public function Read($uid = 0)
    {
        global $_TABLES;

        if ($uid > 0) $this->uid = $uid;

        $sql = "SELECT *
            FROM {$_TABLES['membership_members']}
            WHERE mem_uid = '{$this->uid}'";
        //echo $sql;die;
        $res1 = DB_query($sql);
        if (!$res1 || DB_numRows($res1) != 1) {
            $this->Plan = NULL;
            return false;
        } else {
            $A = DB_fetchArray($res1, false);
            if (!empty($A)) {
                $this->SetVars($A);
                $this->Plan = new Plan($this->plan_id);
                return true;
            } else {
                return false;
            }
        }
    }


    /**
     * Set all the object variables from an array, either $_POST or DB record.
     *
     * @param   array   $A      Array of values
     */
    public function SetVars($A)
    {
        if (!is_array($A))
            return false;

        if (isset($A['mem_uid'])) {
            // Will be set via DB read, probably not via form
            $this->uid      = $A['mem_uid'];
        }
        if (isset($A['mem_paid'])) $this->paid = $A['mem_paid'];
        if (isset($A['mem_joined'])) $this->joined = $A['mem_joined'];
        if (isset($A['mem_expires'])) $this->expires = $A['mem_expires'];
        if (isset($A['mem_plan_id'])) $this->plan_id = $A['mem_plan_id'];
        if (isset($A['mem_status'])) $this->status = $A['mem_status'];
        if (isset($A['mem_notified'])) $this->notified = $A['mem_notified'];
        if (isset($A['mem_number'])) $this->mem_number = $A['mem_number'];
        if (isset($A['mem_istrial'])) $this->istrial = $A['mem_istrial'];
        // This will never come from a form:
        if (isset($A['mem_guid'])) $this->guid = $A['mem_guid'];
    }


    /**
     * Retrieve a membership plan and associate it with this membership.
     *
     * @param   string  $plan_id    ID of plan to associate
     * @return  boolean     True if a plan was read, false if not (invalid plan)
     */
    public function SetPlan($plan_id)
    {
        $P = new Plan($plan_id);
        if ($P->plan_id == '') {
            return false;
        } else {
            $this->Plan = $P;
            $this->plan_id = $P->plan_id;
            return true;
        }
    }


    /**
     * Create the edit member for all the members variables.
     * Checks the type of edit being done to select the right template.
     *
     * @param   string  $action_url Form action url, empty if within profile editing
     * @return  string          HTML for edit member
     */
    public function EditForm($action_url = '')
    {
        global $_CONF, $_USER, $_TABLES, $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP;

        $T = MEMBERSHIP_getTemplate(array(
            'editmember' => 'editmember',
            'tips'  => 'tooltipster',
        ) );
        $T->set_var(array(
            'my_uid'    => $this->uid,
            'joined'    => $this->joined,
            'expires'   => $this->expires,
            'hlp_member_edit' => $LANG_MEMBERSHIP['hlp_member_edit'],
            'doc_url'       => MEMBERSHIP_getDocURL('edit_member.html',
                                            $_CONF['language']),
            //'viewApp'   => self::hasApp($this->uid) ? 'true' : '',
            'viewApp'   => 'true',
            'notified_chk' => $this->notified == 1 ? 'checked="checked"' : '',
            'notified_orig' => $this->notified == 1 ? 1 : 0,
            'plan_id_orig' => $this->plan_id,
            'is_member' => $this->isNew ? '' : 'true',
            'pmt_date'  => $_CONF_MEMBERSHIP['now']->Format('Y-m-d', true),
            'mem_number' => $this->mem_number,
            'use_mem_number' => $_CONF_MEMBERSHIP['use_mem_number'] ? 'true' : '',
            'mem_istrial' => $this->istrial,
            'mem_istrial_chk' => $this->istrial ? 'checked="checked"' : '',
            'iconset' => $_CONF_MEMBERSHIP['_iconset'],
        ) );
        if ($action_url != '') {
            $T->set_var(array(
                'standalone' => 'true',
                'member_name' => COM_getDisplayName($this->uid),
                'action_url' => $action_url,
            ) );
        }

        $family_plans = array();
        $T->set_block('editmember', 'PlanBlock', 'planrow');
        $Plans = Plan::getPlans();
        foreach ($Plans as $P) {
            if ($this->plan_id == $P->plan_id) {
                $sel = 'selected="selected"';
                if ($P->upd_links) {
                    $T->set_var('upd_link_text', $LANG_MEMBERSHIP['does_upd_links']);
                } else {
                    $T->set_var('upd_link_text', $LANG_MEMBERSHIP['no_upd_links']);
                }
            } else {
                $sel = '';
            }
            $T->set_var(array(
                'plan_sel'  => $sel,
                'plan_id'   => $P->plan_id,
                'plan_name' => $P->name,
            ) );
            $T->parse('planrow', 'PlanBlock', true);
            if ($P->upd_links == 1) $family_ids[] = '"'. $P->plan_id . '"';
        }
        $family_plans = empty($family_ids) ? '' : implode(',', $family_ids);
        $T->set_var('family_plans', $family_plans);

        $relatives = $this->getLinks();

        // Put the relatives into an array to track if any links change.
        // Since links are done via ajax we have to check the db against
        // the original links to see if any have changed.
        $old_links = json_encode($relatives);
        $T->set_var('old_links', $old_links);

        $T->set_block('editmember', 'LinkBlock', 'linkrow');
        $i = 0;
        $link_ids = array();
        foreach ($relatives as $key=>$name) {
            $T->set_var(array(
                'idx'       => $i++,
                'uid'       => $key,
                'uname'     => $name,
            ) );
            $T->parse('linkrow', 'LinkBlock', true);
            $link_ids[] = $key;
        }

        $sql = "SELECT uid, username, fullname
            FROM {$_TABLES['users']} WHERE uid > 1
            AND uid <> '{$this->uid}'";
        if (!empty($link_ids))
            $sql .= ' AND uid NOT IN (' . implode(',', $link_ids) . ')';
        $sql .= ' ORDER BY fullname';
        //echo $sql;die;
        $res = DB_query($sql, 1);
        $T->set_block('editmember', 'linkSelect', 'linksel');
        while ($A = DB_fetchArray($res, false)) {
            $T->set_var(array(
                'link_id'   => $A['uid'],
                'link_name' => empty($A['fullname']) ? $A['username'] : $A['fullname'],
            ) );
            $T->parse('linksel', 'linkSelect', true);
        }
        $T->parse('tooltipster_js', 'tips');
        $T->parse('output', 'editmember');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save a membership, either an update or new online purchase.
     *
     * @param   array   $A      Optional array of values to set
     * @return  boolean     Status, true for success, false for failure
     */
    public function Save($A = '')
    {
        global $_TABLES, $_CONF_MEMBERSHIP;

        $old_status = $this->status;  // track original status
        if (is_array($A) && !empty($A)) {
            if ($A['mem_plan_id'] == '') {
                // remove membership, leave record
                self::Cancel($this->uid);
                return true;        // cancellation is a valid operation
            }
            $this->SetVars($A);
        }

        // Cannot save a membership for Anonymous
        if ($this->uid < 2) {
            return false;
        }

        // The first thing is to check to see if we're removing this account
        // from the family so we don't update other members incorrectly
        if (isset($_POST['emancipate']) && $_POST['emancipate'] == 1) {
            self::remLink($this->uid);
        }

        $this->Plan = new Plan($this->plan_id);
        if ($this->Plan->plan_id == '')
            return false;       // invalid plan requested

        // Date has been updated with a later date. If updated to an earlier
        // date then the expiration/arrears will be handled by
        // runScheduledTask
        if ($this->expires > MEMBERSHIP_today()) {
            $this->status = MEMBERSHIP_STATUS_ACTIVE;
        }

        // If this plan updates linked accounts, get all the accounts.
        if ($this->Plan->upd_links) {
            $accounts = $this->getLinks();
            $accounts[$this->uid] = '';
            Cache::clear('members');
        } else {
            $accounts = array($this->uid => '');
        }
        $this->joined = DB_escapeString($this->joined);
        $this->expires = DB_escapeString($this->expires);

        // Create a guid (just an md5()) for the membership.
        // Only for memberships that don't already have one, e.g. new.
        if ($this->guid == '') {
            $this->guid = self::_makeGuid($this->uid);
        }
        USES_lib_user();

        foreach ($accounts as $key => $name) {

            $this->joined = DB_escapeString($this->joined);
            $this->expires = DB_escapeString($this->expires);

            // Add this user to the membership group
            MEMBERSHIP_debug("Adding user $key to group {$_CONF_MEMBERSHIP['member_group']}");
            // Create membership number if not already defined for the account
            // Include trailing comma, be sure to place it appropriately in
            // the sql statement that follows
            if ($_CONF_MEMBERSHIP['use_mem_number'] &&
                    $this->isNew && $this->mem_number == '') {
                $this->mem_number = self::createMemberNumber($key);
            }

            $sql = "INSERT INTO {$_TABLES['membership_members']} SET
                        mem_uid = '{$key}',
                        mem_plan_id = '" . DB_escapeString($this->plan_id) ."',
                        mem_joined = '" . DB_escapeString($this->joined) ."',
                        mem_expires = '" . DB_escapeString($this->expires) ."',
                        mem_status = {$this->status},
                        mem_guid = '{$this->guid}',
                        mem_number = '" . DB_EscapeString($this->mem_number) . "',
                        mem_notified = {$this->notified},
                        mem_istrial = {$this->istrial}
                    ON DUPLICATE KEY UPDATE
                        mem_plan_id = '" . DB_escapeString($this->plan_id) . "',
                        mem_expires = '" . DB_escapeString($this->expires) . "',
                        mem_status = {$this->status},
                        mem_guid = '{$this->guid}',
                        mem_number = '" . DB_EscapeString($this->mem_number) . "',
                        mem_notified = {$this->notified},
                        mem_istrial = {$this->istrial}";
            //echo $sql;die;
            //COM_errorLog($sql);
            DB_query($sql, 1);
            if (DB_error()) {
                COM_errorLog(__CLASS__ . '::Save() sql error: ' . $sql);
            }

            // Add the member to the groups if the status has changed,
            // and the status is active. If the expiration was set to a past
            // date then the status and group changes will be handled by
            // runScheduledTask
            if ($this->status == MEMBERSHIP_STATUS_ACTIVE) {
                USER_addGroup($_CONF_MEMBERSHIP['member_group'], $key);
            }
            self::updatePlugins('membership:' . $key, $old_status, $this->status);
        }

        // If this is a payment transaction, as opposed to a simple edit,
        // log the transaction info.
        // This only logs transactions for profile updates; Paypal
        // transactions are logged by the handlePurchase service function.
        $pmt_type = MEMB_getVar($A, 'mem_pmttype');
        $pmt_amt = MEMB_getVar($A, 'mem_pmtamt', 'float', 0);
        $quickrenew = MEMB_getVar($A, 'mem_quickrenew', 'integer', 0);
        if (!empty($pmt_type) || $pmt_amt > 0 || $quickrenew == 1) {
            $this->AddTrans($A['mem_pmttype'], $A['mem_pmtamt'],
                        $A['mem_pmtdesc']);
        }

        // Remove the renewal popup message
        LGLIB_deleteMessage($this->uid, MEMBERSHIP_MSG_EXPIRING);
        Cache::clear('members');

        return true;
    }   // function Save


    /**
     * Update a membership status. Currently only cancels or deletes members.
     *
     * @param   integer $uid            User ID of member being removed
     * @param   boolean $inc_relatives  True to include relatives
     * @param   integer $old_status     Original status being changed, for logging
     * @param   integer $new_status     New status value to set
     * @return  boolean     True on success, False on error
     */
    private static function _UpdateStatus($uid, $inc_relatives, $old_status, $new_status)
    {
        global $_TABLES, $_CONF_MEMBERSHIP;

        $uid = (int)$uid;
        if ($uid < 2) return false;
        $Mem = self::getInstance($uid);
        $new_status = (int)$new_status;
        USES_lib_user();

        // Remove the member from the membership group
        $groups = array();
        $dt_sql = '';
        switch ($new_status) {
        case MEMBERSHIP_STATUS_EXPIRED:
            if (!empty($_CONF_MEMBERSHIP['member_group'])) {
                $groups[] = $_CONF_MEMBERSHIP['member_group'];
            }
            $dt_sql = ", mem_expires = '" . MEMBERSHIP_today() . "'";
            break;
        }
        // Set membership status
        $sql = "UPDATE {$_TABLES['membership_members']} SET
                mem_status = $new_status
                $dt_sql
                WHERE mem_uid = $uid";
        //echo $sql;die;
        DB_query($sql, 1);

        // Remove this member from the membership groups
        foreach ($groups as $group) {
            USER_delGroup($group, $uid);
        }
        //self::updaePlugins($uid, $old_status, $new_status);

        // Now do the same thing for all the relatives.
        if ($inc_relatives) {
            $relatives = $Mem->getLinks();
            foreach ($relatives as $key => $name) {
                foreach ($groups as $group) {
                    USER_delGroup($group, $key);
                }
                DB_query("UPDATE {$_TABLES['membership_members']} SET
                        mem_status = $new_status
                        $dt_sql
                        WHERE mem_uid = $key", 1);
            }
            self::updatePlugins('membership:' . $key, $old_status, $new_status);
        }
        return true;
    }


    /**
     * Cancel a membership.
     * Called when the administrator removes a membership plan from a
     * member's profile.
     *
     * @param   integer $uid    User ID to cancel
     * @param   boolean $cancel_relatives   True to cancel linked accounts
     */
    public static function Cancel($uid=0, $cancel_relatives=true)
    {
        self::Expire($uid, $cancel_relatives);
    }


    /**
     * Expire a membership.
     * Called from plugin_runScheduledTask when the membership has expired.
     * Assume the current status is "Active" to force status-change operations.
     *
     * @param   integer $uid    User ID to cancel
     * @param   boolean $cancel_relatives   True to cancel linked accounts
     */
    public static function Expire($uid=0, $cancel_relatives=true)
    {
        // Remove this member from any club positions held
        $positions = Position::getMemberPositions($uid);
        if (!empty($positions)) {
            foreach ($positions as $pos_id) {
                $P = new Position($pos_id);
                $P->setMember(0);
            }
        }
        // Disable the account if so configured
        self::_disableAccount($uid);

        self::_UpdateStatus($uid, $cancel_relatives,
                MEMBERSHIP_STATUS_ACTIVE, MEMBERSHIP_STATUS_EXPIRED);
    }


    /**
     * Set a member to "in arrears"
     * Called from plugin_runScheduledTask when the membership is overdue.
     * Assume the current status is "Active" to force status-change operations.
     *
     * @param   integer $uid    User ID to cancel
     * @param   boolean $cancel_relatives   True to cancel linked accounts
     */
    public static function Arrears($uid=0, $cancel_relatives=true)
    {
        self::_UpdateStatus($uid, $cancel_relatives,
                MEMBERSHIP_STATUS_ACTIVE, MEMBERSHIP_STATUS_ARREARS);
    }


    /**
     * Add a new membership record, or extend an existing one.
     * Used by Paypal processing to automatically add or update a membership.
     *
     * @uses    self::Save()
     * @param   integer $uid        User ID
     * @param   string  $plan_id    Plan item ID
     * @param   integer $exp        Expiration date
     * @param   integer $joined     Date joined
     * @return  mixed       Expiration date, or false in case of error
     */
    public function Add($uid = '', $plan_id = '', $exp = '', $joined = '')
    {
        global $_CONF_MEMBERSHIP;

        if ($uid != '') {
            $this->Read($uid);
        }
        if (!empty($plan_id)) {
            $this->plan_id = $plan_id;
            $this->Plan = new Plan($plan_id);
        }
        if ($this->Plan->plan_id == '') {
            return false;       // invalid plan requested
        }
        $this->notified = 0;
        $this->status = MEMBERSHIP_STATUS_ACTIVE;
        $this->istrial = 0;
        if ($exp == '')  {
            $this->expires = $this->Plan->calcExpiration($this->expires);
        } else {
            $this->expires = $exp;
        }
        if ($joined != '') $this->joined = $joined;
        $this->paid = MEMBERSHIP_today();
        if ($this->Save()) {
            return $this->expires;
        } else {
            return false;
        }
    }


    /**
     * Get the current membership price for this member.
     * Considers whether it is a new or renewing membership.
     *
     * @return  float   Price to buy or renew membership
     */
    public function Price()
    {
        return $this->Plan->Price($this->isNew());
    }


    /**
     * Create a read-only display of membership information for a member.
     * Used in the member profile, and on the membership editing tab for
     * regular members.  If "$panel" is set, then this is being displayed
     * in the tab and will include the javascript-controlled div tags.
     *
     * @see     plugin_profileedit_membership()
     * @see     plugin_profilevariablesdisplay_membership()
     * @param   boolean $panel  True if showing in the panel, false if not.
     * @param   integer $uid    User ID being displayed, default = current user
     * @return  string      HTML for membership data display
     */
    public function showInfo($panel = false, $uid = 0)
    {
        global $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP, $_USER, $_TABLES, $_SYSTEM;

        if ($uid == 0) $uid = (int)$_USER['uid'];
        $positions = array();
        if ($this->isNew || $this->Plan == NULL) {
            if (!$panel) return '';
            $joined = $LANG_MEMBERSHIP['na'];
            $expires = $LANG_MEMBERSHIP['na'];
            $plan_name = $LANG_MEMBERSHIP['na'];
            $plan_id = $LANG_MEMBERSHIP['na'];
            $plan_dscp = $LANG_MEMBERSHIP['na'];
            $relatives = array();
        } else {
            $joined = $this->joined;
            // Get the expiration date from the callback in services.inc.php
            // to get the highlighting based on status.
            $expires = membership_profilefield_expires('', $this->expires,
                    array(), '', '');
            $plan_name = $this->Plan->name;
            $plan_dscp = $this->Plan->description;
            $plan_id = $this->Plan->plan_id;
            $relatives = $this->getLinks();
            //$relatives = Link::getRelatives($this->uid);
            $mem_number = $this->mem_number;
            $sql = "SELECT descr FROM {$_TABLES['membership_positions']}
                    WHERE uid = $uid";
            $res = DB_query($sql, 1);
            while ($A = DB_fetchArray($res, false)) {
                $positions[] = $A['descr'];
            }
        }
        $position = implode(', ', $positions);
        $app_link = '';     // no app link by default
        if (!$this->isNew && $_CONF_MEMBERSHIP['require_app'] > 0) {
            $app_link = 'true';
        }

        $LT = new \Template(MEMBERSHIP_PI_PATH . '/templates/');
        $LT->set_file(array(
            'block' => 'profileblock.thtml',
        ));
        $LT->set_var(array(
            'is_uikit'  => $_SYSTEM['framework'] == 'uikit' ? 'true' : '',
            'joined'    => $joined,
            'expires'   => $expires,
            'plan_name' => $plan_name,
            'plan_description' => $plan_dscp,
            'plan_id'   => $plan_id,
            //'app_link'  => self::hasApp($uid) ? 'true' : '',
            'app_link'  => $app_link,
            'my_uid'    => $uid,
            'panel'     => $panel ? 'true' : '',
            'nolinks'   => empty($relatives) ? 'true' : '',
            //'old_links' => $old_links,
            'position' => $position,
            'mem_number' => SEC_hasRights('membership.admin') ? $this->mem_number : '',
            'use_mem_number' => $_CONF_MEMBERSHIP['use_mem_number'] ? 'true' : '',
        ) );

        $LT->set_block('block', 'LinkBlock', 'lrow');
        foreach ($relatives as $key=>$name) {
            $LT->set_var(array(
                'link_uname' => $name,
                'link_uid'   => $key,
            ) );
            $LT->parse('lrow', 'LinkBlock', true);
        }

        $LT->parse('output', 'block');
        return $LT->get_var('output');
    }


    /**
     * Transfer a membership from one plan to another.
     * This can be done on a per-member basis, or as part of a plan deletion.
     *
     * @param   string  $old_plan   Original Plan ID
     * @param   string  $new_plan   New Plan ID
     * @return  boolean     True on success, False on error or invalid new_plan
     */
    public static function Transfer($old_plan, $new_plan)
    {
        global $_TABLES;

        // Verify that the new plan exists
        if (empty($old_plan) || empty($new_plan) ||
            DB_count($_TABLES['membership_plans'], 'plan_id', $new_plan) == 0) {
            return false;
        }

        $old_plan = DB_escapeString($old_plan);
        $new_plan = DB_escapeString($new_plan);
        DB_query("UPDATE {$_TABLES['membership_members']}
                SET mem_plan_id = '$new_plan'
                WHERE mem_plan_id = '$old_plan'", 1);
        if (!DB_error()) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Determine if an application exists in the forms plugin for this member.
     * If view_app is not allowed, returns false.
     *
     * @param   integer $uid    User ID
     * @return  boolean     True if an app is found, False if not
     */
    public static function hasApp($uid)
    {
        global $_CONF_MEMBERSHIP;

        $hasApp = false;
        // Get the application form to provide a link
        if ($_CONF_MEMBERSHIP['view_app'] && $_CONF_MEMBERSHIP['app_form'] != '') {
            $status = LGLIB_invokeService('forms', 'resultId',
                array('frm_id' => $_CONF_MEMBERSHIP['app_form'],
                    'uid' => $uid),
                $output, $msg);
            if ($status == PLG_RET_OK && $output > 0) {
                $hasApp = true;
            }
        }
        return $hasApp;
    }


    /**
     * Get the number of days until this membership expires.
     * If the membership is already expired, return a negative number.
     *
     * @uses    COM_dateDiff() but reverses the abs() used there
     * @param   string  $exp    Expiration date (YYYY-MM-DD)
     * @return  integer     Days expired, negative if already expired.
     */
    public static function DaysToExpire($exp)
    {
        global $_CONF_MEMBERSHIP;

        $days = COM_dateDiff('d', $exp, MEMBERSHIP_today());
        if ($exp < MEMBERSHIP_today()) $days *= -1;
        return $days;
    }


    /**
     * Get the number of days that this membership has expired.
     * If the membership is not expired, return a negative number
     *
     * @param   string  $exp    Expiration date (YYYY-MM-DD)
     * @return  integer     Days expired, negative if not expired yet.
     */
    public static function DaysExpired($exp)
    {
        global $_CONF_MEMBERSHIP;

        $days = COM_dateDiff('d', $exp, MEMBERSHIP_today());
        // Undo absolute value conversion done in COM_dateDiff()
        if ($exp > MEMBERSHIP_today()) $days *= -1;
        return $days;
    }


    /**
     * Determine if the current user can purchase a membership.
     * Checks if the user is anonymous, or if not within the early_renewal
     *
     * @return  boolean     True if purchase is OK, False if not.
     */
    public function CanPurchase()
    {
        global $_CONF_MEMBERSHIP;

        if (COM_isAnonUser()) {
            $canPurchase = MEMBERSHIP_NOPURCHASE;
        } else {
            if ($this->expires > self::dtBeginRenew()) {
                $canPurchase = MEMBERSHIP_NO_RENEWAL;
            } else {
                $canPurchase = MEMBERSHIP_CANPURCHASE;
            }
        }
        return $canPurchase;
    }


    /**
     * Renew a membership.
     * Calls the plan's CalcExpiration() function to get the correct
     * expiration date.
     *
     * Argument array includes:
     * - exp         => New expiration date, calculated if omitted
     * - mem_pmttype => Payment type, no payment transaction if omitted
     * - mem_pmtamt  => Payment amount
     * - mem_pmtdate => Payment date
     * - mem_pmtdesc => Paymetn description
     *
     * @uses    Plan::calcExpiration()
     * @param   array   $args   Array of arguments
     * @return  boolean     True on success, False on failure
     */
    public function Renew($args = array())
    {
        if (!$this->istrial && $this->Plan !== NULL && !$this->isNew) {
            $this->expires = isset($args['exp']) ? $args['exp'] :
                    $this->Plan->calcExpiration($this->expires);
            // Set the plan ID so this isn't seen as a cancellation by Save()
            if (!isset($args['mem_plan_id']))
                $args['mem_plan_id'] = $this->plan_id;
            $args['mem_expires'] = $this->expires;
            $this->Save($args);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Delete a membership record.
     * Only the specified user is deleted; linked accounts are not affected.
     * The specified user is also removed from the linked accounts.
     *
     * @param   integer $uid    Member's user ID
     */
    public static function Delete($uid)
    {
        global $_CONF_MEMBERSHIP, $_TABLES;

        // Remove this user from the family
        //Link::Emancipate($uid);
        self::remLink($uid);

        // Remove this user from the membership group
        USER_delGroup($_CONF_MEMBERSHIP['member_group'], $uid);

        // Delete this membership record
        DB_delete($_TABLES['membership_members'], 'mem_uid', $uid);
        Cache::clear('members');     // Make sure members and links are cleared
        self::_disableAccount($uid);
    }


    /**
     * Add a transaction record to the membership_trans table.
     *
     * @param   string  $gateway    Gateway name or payment type
     * @param   float   $amt        Amount paid
     * @param   string  $txn_id     Optional transaction ID or comment
     * @param   string  $dt         Optional date, now() used if empty
     * @param   integer $by         Optional user ID, -1 for system gateway
     */
    public function AddTrans($gateway, $amt, $txn_id='', $dt = '', $by = -1)
    {
        global $_TABLES, $_USER, $_CONF_MEMBERSHIP;

        $gateway = DB_escapeString($gateway);
        $amt = (float)$amt;
        $txn_id = DB_escapeString($txn_id);
        $now = empty($dt) ? $_CONF_MEMBERSHIP['now']->toMySQL(true) : DB_escapeString($dt);
        $by = $by == -1 ? (int)$_USER['uid'] : (int)$by;
        $sql = "INSERT INTO {$_TABLES['membership_trans']} SET
            tx_date = '{$now}',
            tx_by = '{$by}',
            tx_uid = '{$this->uid}',
            tx_planid = '{$this->Plan->plan_id}',
            tx_gw = '{$gateway}',
            tx_amt = '{$amt}',
            tx_exp = '{$this->expires}',
            tx_txn_id = '$txn_id'";
        DB_query($sql);
    }


    /**
     * Update other plugins based on a changed membership status.
     *
     * @param   integer $uid            User ID
     * @param   integer $old_status     Original member status
     * @param   integer $new_status     New member status
     * @return  integer     Service status code (PLG_RET_OK, etc.);
     */
    public static function updatePlugins($uid, $old_status, $new_status)
    {
        global $_CONF_MEMBERSHIP;

        PLG_itemSaved($uid, $_CONF_MEMBERSHIP['pi_name']);
        return;

        global $_TABLES, $_CONF_MEMBERSHIP, $_PLUGINS;

        // No change in status just return OK
        if ($old_status == $new_status) {
            return PLG_RET_OK;
        }

        // Gets statuses from plugin config into an array
        //  my_status_name => mailchimp_value_name
        $statuses = MEMBERSHIP_memberstatuses();

        $retval = PLG_RET_OK;
        $uid = (int)$uid;
        $new_status = isset($statuses[$new_status]) ?
                $statuses[$new_status] : null;
        if ($new_status === null) {
            // unrecognized status received
            return PLG_RET_ERROR;
        }

        // 1. Update the Mailchimp plugin
        if ($_CONF_MEMBERSHIP['update_maillist']) {
            $status = LGLIB_invokeService('mailchimp', 'updateuser',
                array(
                    'uid' => $uid,
                    'params' => array(
                        'merge_vars' => array(
                            'MEMSTATUS'=> $new_status,
                        ),
                    ),
                ),
                $output, $svc_msg
            );
            if ($status != PLG_RET_OK) {
                COM_errorLog('Membership: Error updating mailling list. ' .
                "User: $uid, Segment $new_status");
                $retval = $status;
            }
        }

        // 2. Update the image quota in mediagallery.
        // Mediagallery doesn't have a service function, have to update the
        // database directly. Don't update users with unlimited quotas.
        if ($_CONF_MEMBERSHIP['manage_mg_quota']  &&
                in_array('mediagallery', $_PLUGINS)) {

            $quota = DB_getItem($_TABLES['mg_userprefs'], 'quota', "uid=$uid");
            if ($quota > 0) {

                $max = (int)$_CONF_MEMBERSHIP['mg_quota_member'];
                $min = (int)$_CONF_MEMBERSHIP['mg_quota_nonmember'];
                // sanity checking. Min must be positive to have an effect,
                // zero is unlimited. Max can be zero but otherwise must be > min
                if ($min < 1) $min = 1;
                if ($max == 0 || $min < $max) {
                    switch ($mem_status) {
                    case MEMBERSHIP_STATUS_ACTIVE:
                    case MEMBERSHIP_STATUS_ARREARS:
                        $size = $max * 1048576;
                        break;
                    default:
                        $size = $min * 1048576;
                        break;
                    }
                    // Update the MG uerpref table with the new quota.
                    // Ignore errors, nothing to be done about them here.
                    $sql = "INSERT INTO {$_TABLES['mg_userprefs']}
                                (`uid`, `quota`)
                            VALUES
                                ($uid, $size)
                            ON DUPLICATE KEY UPDATE
                                quota = '$size'";
                    DB_query($sql, 1);
                }
            }
         }

       return $retval;
    }


    /**
     * Create a membership numbe.r
     * Calls CUSTOM_createMemberNumber() if defined, otherwise
     * uses sprintf() and the member's uid to create the ID.
     *
     * @param   integer $uid    User ID or other numeric key
     * @return  string          Membership number
     */
    public static function createMemberNumber($uid)
    {
        global $_CONF_MEMBERSHIP;

        if (function_exists('CUSTOM_createMemberNumber')) {
            $retval = CUSTOM_createMemberNumber($uid);
        } else {
            $fmt = $_CONF_MEMBERSHIP['mem_num_fmt'];
            if (empty($fmt)) {
                $fmt = '%04d';
            }
            $retval = sprintf($fmt, (int)$uid);
        }
        return $retval;
    }


    /**
     * For pricing purposes trial memberships are considered "new".
     *
     * @return  string  String indicating 'new' or 'renewal' for pricing
     */
    public function isNew()
    {
        if ($this->istrial || $this->isNew) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get a short description for display in messages.
     *
     * @return  string  Description
     */
    public function planDescription()
    {
        global $LANG_MEMBERSHIP;

        $retval = $this->plan_id;
        if ($this->istrial) {
            $retval .= ', ' . $LANG_MEMBERSHIP['trial'];
        }
        return $retval;
    }


    /**
     * Disable a specific user's site account.
     *
     * @param   integer $uid    User ID to disable
     */
    private static function _disableAccount($uid)
    {
        global $_TABLES, $_CONF_MEMBERSHIP;

        if ($_CONF_MEMBERSHIP['disable_expired']) {
            // Disable the user account at expiration, if so configured
            DB_query("UPDATE {$_TABLES['users']}
                    SET status = " . USER_ACCOUNT_DISABLED .
                    " WHERE uid = $uid", 1);
        }
    }


    /**
     * Shortcut to get the current date object.
     *
     * @return  object  Date object for current timestamp
     */
    public static function Now()
    {
        global $_CONF;
        static $now = NULL;
        if ($now === NULL) {
            $now = new \Date('now', $_CONF['timezone']);
        }
        return $now;
    }


    /**
     * Shortcut function to get the SQL-formatted date.
     *
     * @return  string  Today's date as "YYYY-MM-DD"
     */
    public static function Today()
    {
        return self::Now()->format('Y-m-d', true);
    }


    /**
     * Get the latest expiration date that allows renewals.
     * This works with the early_renewal configuration to allow renewals
     * within X days of expiration.
     *
     * @return  object  Date object
     */
    public static function dtBeginRenew()
    {
        global $_CONF_MEMBERSHIP;
        static $dt = NULL;
        if ($dt === NULL) {
            $dt = self::Now()->add(new \DateInterval("P{$_CONF_MEMBERSHIP['early_renewal']}D"));
        }
        return $dt;
    }

    /**
     * Calculate and return the expiration date where the grace has ended.
     * This is the date after which memberships have truly expired.
     *
     * @return  object      Expiration date where grace period has ended.
     */
    public static function dtEndGrace()
    {
        global $_CONF_MEMBERSHIP;
        static $dt = NULL;
        if ($dt === NULL) {
            $dt = self::Now()->sub(new \DateInterval("P{$_CONF_MEMBERSHIP['grace_days']}D"));
        }
        return $dt;
    }


    /**
     * Return membership information for the getItemInfo function in functions.inc.
     *
     * @param   string  $what   Array of field names, already exploded
     * @param   array   $options    Additional options
     * @return  array       Array of fieldname=>value
     */
    public function getItemInfo($what, $options = array())
    {
        $retval = array();
        $U = User::getInstance($this->uid);
        foreach ($what as $fld) {
            switch ($fld) {
            case 'id':
                $retval[$fld] = $this->uid;
                break;
            case 'list_segment':
                $retval[$fld] = MEMBERSHIP_memberstatuses()[$this->status];
                break;
            case 'uid':
            case 'plan_id':
            case 'joined':
            case 'expires':
            case 'status':
            case 'mem_number':
            case 'istrial':
                // Membership fields
                $retval[$fld] = $this->$fld;
                break;
            default:
                // User fields
                $retval[$fld] = $U->$fld;
                if ($retval[$fld] === NULL) {
                    $retval[$fld] = '';
                }
                break;
            }
        }
        return $retval;
    }


    /**
     * Create a unique identifier for a membership record.
     * The same GUID is applied to all linked members.
     *
     * @param   string  $seed   Some seed value
     * @return  string      Unique identifier
     */
    private static function _makeGuid($seed)
    {
        return md5((string)$seed . rand());
    }


    /**
     * Link a membership to another membership.
     * If the membership being linked ($uid2) already exists, then it is updated
     * with information from the target account.
     * If updating, The date joined and member number are not changed.
     *
     * @todo    Decide if link should succeed even if plan is not a family plan
     * @param   integer $uid1   Target (master) account
     * @param   integer $uid2   Account being linked into the family
     * @return  boolean     True on success, False on error
     */
    public static function addLink($uid1, $uid2)
    {
        global $_TABLES, $_CONF_MEMBERSHIP;

        $Mem1 = self::getInstance($uid1);
        if ($Mem1->isNew) {
            COM_errorLog("Cannot link user $uid2 to nonexistant membership for $uid1");
            return false;
        }
        // TODO: Check here for $Mem1->Plan to see if it uses linked accounts?
        $Mem2 = self::getInstance($uid2);
        if ($Mem2->isNew) {
            if ($_CONF_MEMBERSHIP['use_mem_number']) {
                $mem_number = self::createMemberNumber($uid2);
            } else {
                $mem_number = '';
            }
        } else {
            $mem_number = $Mem2->mem_number;
        }
        $mem_number = DB_escapeString($mem_number);

        $sql = "INSERT INTO {$_TABLES['membership_members']} (
                mem_uid, mem_plan_id, mem_joined, mem_expires, mem_status, mem_guid,
                mem_number, mem_notified, mem_istrial
            ) SELECT
                $uid2, mem_plan_id, mem_joined, mem_expires, mem_status, mem_guid,
                '$mem_number', mem_notified, mem_istrial
                FROM {$_TABLES['membership_members']}
                WHERE mem_uid = $uid1
            ON DUPLICATE KEY UPDATE
                mem_plan_id = '" . DB_escapeString($Mem1->plan_id) . "',
                mem_expires = '" . DB_escapeString($Mem1->expires) . "',
                mem_status = '{$Mem1->status}',
                mem_guid = '{$Mem1->guid}',
                mem_notified = '{$Mem1->notified}',
                mem_istrial = '{$Mem1->istrial}'";
        //echo $sql;die;
        DB_query($sql);
        if (DB_error()) {
            COM_errorLog(__CLASS__ . "/addLink() error: $sql");
            return false;
        } else {
            Cache::clear('members');
            return true;
        }
    }


    /**
     * Remove a linked membership from the family.
     * The GUID is changed so it is now a standalone membership, nothing else
     * is changed.
     *
     * @param   integer $uid    Account ID being unlinked
     * @return  boolean     True on success, False on error
     */
    public static function remLink($uid)
    {
        global $_TABLES;

        $uid = (int)$uid;
        if ($uid < 2) return false;

        $sql = "UPDATE {$_TABLES['membership_members']}
            SET mem_guid = '" . self::_makeGuid($uid) . "'
            WHERE mem_uid = $uid";
        DB_query($sql);
        if (DB_error()) {
            COM_errorLog(__CLASS__ . "::remLink() error: $sql");
            return false;
        } else {
            Cache::clear('members');
            return true;
        }
    }


    /**
     * Get all accounts related to the specified account.
     *
     * @param   mixed   $uid    User ID
     * @return  array       Array of relatives (uid => username)
     */
    public function getLinks()
    {
        global $_TABLES, $_USER;

        // If uid is empty, use the curent id
        if ($this->uid < 1) return array();   // invalid user ID requested

        $cache_key = 'links_' . $this->uid;
        $relatives = Cache::get($cache_key);
        if ($relatives === NULL) {
            $relatives = array();
            $sql = "SELECT m.mem_uid, u.fullname, u.username
                    FROM {$_TABLES['membership_members']} m
                    LEFT JOIN {$_TABLES['users']} u
                    ON m.mem_uid = u.uid
                    WHERE m.mem_guid = '" . DB_escapeString($this->guid) . "'
                    AND m.mem_uid <> {$this->uid}";
            //echo $sql;die;
            $res = DB_query($sql, 1);
            while ($A = DB_fetchArray($res, false)) {
                $relatives[$A['mem_uid']] = empty($A['fullname']) ?
                    $A['username'] : $A['fullname'];
            }
            Cache::set($cache_key, $relatives, 'members');
        }
        return $relatives;
    }

}

?>
