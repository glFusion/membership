<?php
/**
 * Class to handle membership records.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2020 Lee Garner <lee@leegarner.com>
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
    const STATUS_ACTIVE = 0;
    const STATUS_ENABLED = 1;
    const STATUS_ARREARS = 2;
    const STATUS_EXPIRED = 4;
    const STATUS_DROPPED = 128;

    /** Plan ID.
     * @var string */
    private $plan_id = '';

    /** Flag indicating the member has been notified of impending renewal.
     * @var boolean */
    private $notified = 0;

    /** Flag indicating that this is a trial membership.
     * @var boolean */
    private $istrial = 0;

    /** Member user ID.
     * @var integer */
    private $uid = 0;

    /** Membership status.
     * @var integer */
    private $status = self::STATUS_ACTIVE;

    /** Date joined.
     * @var string */
    private $joined = '';

    /** Expiration date.
     * @var string */
    private $expires = '';

    /** Membership number.
     * @var string */
    private $mem_number = '';

    /** Membership GUID, for tracking families.
     * @var string */
    private $guid = '';

    /** Flag to indicate that this is a new record.
     * @var boolean */
    private $isNew = 1;

    /** Membership plan related to this membership.
     * @var object */
    private $Plan = NULL;


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

        if ($uid == 0) {
            $uid = (int)$_USER['uid'];
        }
        $this->uid = $uid;
        if ($this->uid > 1 && $this->Read($this->uid)) {
            $this->isNew = false;
        } else {
            $this->expires = Dates::plusOneYear();
            $this->joined = Dates::Today();
            $this->notified = (int)$_CONF_MEMBERSHIP['notifycount'];
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
                $this->setVars($A);
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
     * @return  object  $this
     */
    public function setVars($A)
    {
        global $_CONF_MEMBERSHIP;

        if (!is_array($A)) {
            return $this;
        }

        if (isset($A['mem_uid'])) {
            // Will be set via DB read, probably not via form
            $this->uid = (int)$A['mem_uid'];
        }
//        if (isset($A['mem_paid'])) $this->paid = $A['mem_paid'];
        if (isset($A['mem_joined'])) $this->joined = $A['mem_joined'];
        if (isset($A['mem_expires'])) $this->expires = $A['mem_expires'];
        if (isset($A['mem_plan_id'])) $this->plan_id = $A['mem_plan_id'];
        if (isset($A['mem_status'])) $this->status = $A['mem_status'] ? 1 : 0;
        $this->notified = MEMB_getVar($A, 'mem_notified', 'integer', $_CONF_MEMBERSHIP['notifycount']);
        if (isset($A['mem_number'])) $this->mem_number = $A['mem_number'];
        $this->istrial = MEMB_getVar($A, 'mem_istrial', 'integer', 0);
        // This will never come from a form:
        if (isset($A['mem_guid'])) $this->guid = $A['mem_guid'];
        return $this;
    }


    /**
     * Get the Plan ID.
     *
     * @return  string      Plan ID
     */
    public function getPlanID()
    {
        return $this->plan_id;
    }


    /**
     * Get the Plan object.
     *
     * @return  object      Plan object.
     */
    public function getPlan()
    {
        if ($this->Plan === NULL) {
            $this->Plan = new Plan($this->plan_id);
        }
        return $this->Plan;
    }


    /**
     * Set a new Plan.
     *
     * @param   string  $id     Plan ID
     * @return  object  $this
     */
    public function setPlan($id)
    {
        $this->plan_id = $id;
        $this->Plan = new Plan($this->plan_id);
        return $this;
    }


    /**
     * Get the expiration date for the membership.
     *
     * @return  string      Expiration date YYYY-MM-DD
     */
    public function getExpires()
    {
        return $this->expires;
    }


    /**
     * Get the join date for the membership.
     *
     * @return  string      Date joined as YYYY-MM-DD
     */
    public function getJoined()
    {
        return $this->joined;
    }


    /**
     * Get the membership number.
     *
     * @return  string      Membership number
     */
    public function getMemNumber()
    {
        return $this->mem_number;
    }


    /**
     * Get the unique ID for the membership. Used to link accounts.
     *
     * @return  string      Globally unique ID
     */
    public function getGuid()
    {
        return $this->guid;
    }


    /**
     * Check if this is a trial membership.
     *
     * @return  integer     1 if trial, 0 if regular
     */
    public function isTrial()
    {
        return $this->istrial ? 1 : 0;
    }


    /**
     * Check if the member has already been notified of impending expiration.
     *
     * @return  integer     1 if notified, 0 if not
     */
    public function expToSend()
    {
        return (int)$this->notified;
    }


    /**
     * Get the member status (active, expired, etc.)
     *
     * @return  integer     Value to indicate status
     */
    public function getStatus()
    {
        return (int)$this->status;
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

        $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
        $T->set_file(array(
            'editmember' => 'editmember.thtml',
            'tips' => 'tooltipster.thtml',
            'js' => 'editmember_js.thtml',
        ) );
        $T->set_var(array(
            'my_uid'    => $this->uid,
            'joined'    => $this->joined,
            'expires'   => $this->expires,
            'hlp_member_edit' => $LANG_MEMBERSHIP['hlp_member_edit'],
            'doc_url'       => MEMBERSHIP_getDocURL('edit_member.html',
                                            $_CONF['language']),
            'viewApp'   => App::getInstance($this->uid)->Exists(),
            'notified_orig' => $this->notified == 1 ? 1 : 0,
            'plan_id_orig' => $this->plan_id,
            'is_member' => $this->isNew ? '' : 'true',
            'pmt_date'  => Dates::Today(),
            'mem_number' => $this->mem_number,
            'use_mem_number' => $_CONF_MEMBERSHIP['use_mem_number'] ? 'true' : '',
            'mem_istrial' => $this->istrial,
            'mem_istrial_chk' => $this->istrial ? 'checked="checked"' : '',
            'is_family' => $this->Plan ? $this->Plan->isFamily() : 0,
        ) );
        $T->set_block('editmember', 'expToSend', 'expTS');
        for ($i = 0; $i <= $_CONF_MEMBERSHIP['notifycount']; $i++) {
            $T->set_var(array(
                'notify_val' => $i,
                'sel' => $i == $this->notified ? 'selected="selected"' : '',
            ) );
            $T->parse('expTS', 'expToSend', true);
        }

        if ($this->Plan) {
            $T->set_var('family_display', $this->Plan->isFamily() ? 'block' : 'none');
        } else {
            $T->set_var('family_display', 'none');
        }
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
            if ($this->plan_id == $P->getPlanID()) {
                $sel = 'selected="selected"';
                if ($P->isFamily()) {
                    $T->set_var('upd_link_text', $LANG_MEMBERSHIP['does_upd_links']);
                } else {
                    $T->set_var('upd_link_text', $LANG_MEMBERSHIP['no_upd_links']);
                }
            } else {
                $sel = '';
            }
            $T->set_var(array(
                'plan_sel'  => $sel,
                'plan_id'   => $P->getPlanID(),
                'plan_name' => $P->getName(),
            ) );
            $T->parse('planrow', 'PlanBlock', true);
            if ($P->isFamily()== 1) {
                $family_ids[] = '"'. $P->getPlanID() . '"';
            }
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
        $T->parse('editmember_js', 'js');
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
            $this->setVars($A);
        }

        // Cannot save a membership for Anonymous
        if ($this->uid < 2) {
            return false;
        }

        // Check for a valid membership plan
        $this->Plan = Plan::getInstance($this->plan_id);
        if ($this->Plan->getPlanID() == '') {
            return false;
        }

        // Get the payment and renewal values, if any.
        $pmt_type = MEMB_getVar($A, 'mem_pmttype');
        $pmt_amt = MEMB_getVar($A, 'mem_pmtamt', 'float', 0);
        $quickrenew = MEMB_getVar($A, 'mem_quickrenew', 'integer', 0);
        if ($quickrenew) {
            $this->istrial = 0;
            $this->expires = $this->Plan->calcExpiration($this->expires);
        }

        // The first thing is to check to see if we're removing this account
        // from the family so we don't update other members incorrectly
        if ($this->Plan->isFamily()) {
            if (isset($_POST['emancipate']) && $_POST['emancipate'] == 1) {
                self::remLink($this->uid);
            } else {
                $orig_links = MEMB_getVar($A, 'mem_orig_links', 'array');
                $new_links = MEMB_getVar($A, 'mem_links', 'array');
                $arr = array_diff($orig_links, $new_links);
                foreach ($arr as $link_id) {
                    self::remLink($link_id);
                }
                $arr = array_diff($new_links, $orig_links);
                foreach ($arr as $link_id) {
                    self::addLink($this->uid, $link_id);
                }
            }
        }

        // After the links have been updated, check for the "Cancel" checkbox.
        // If set, cancel this member's membership along with all the new links
        // and return.
        if (isset($A['mem_cancel'])) {
            self::Cancel($this->uid);
            return true;
        }

        // Date has been updated with a later date. If updated to an earlier
        // date then the expiration/arrears will be handled by
        // runScheduledTask
        if ($this->expires > Dates::Today()) {
            $this->status = MEMBERSHIP_STATUS_ACTIVE;
        }

        // If this plan updates linked accounts, get all the accounts.
        // Already updated any link changes above.
        if ($this->Plan->isFamily()) {
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
            Logger::debug("Adding user $key to group {$_CONF_MEMBERSHIP['member_group']}");
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
                        mem_joined = '" . DB_escapeString($this->joined) ."',
                        mem_expires = '" . DB_escapeString($this->expires) . "',
                        mem_status = {$this->status},
                        mem_guid = '{$this->guid}',
                        mem_number = '" . DB_EscapeString($this->mem_number) . "',
                        mem_notified = {$this->notified},
                        mem_istrial = {$this->istrial}";
            //echo $sql;die;
            //Logger::System($sql);
            DB_query($sql, 1);
            if (DB_error()) {
                Logger::System(__CLASS__ . '::Save() sql error: ' . $sql);
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
        // This only logs transactions for profile updates; Shop
        // transactions are logged by the handlePurchase service function.
        if (!empty($pmt_type) || $pmt_amt > 0 || $quickrenew == 1) {
            $this->AddTrans(
                $A['mem_pmttype'],
                $A['mem_pmtamt'],
                $A['mem_pmtdesc']
            );
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
        switch ($new_status) {
        case MEMBERSHIP_STATUS_EXPIRED:
            if (!empty($_CONF_MEMBERSHIP['member_group'])) {
                $groups[] = $_CONF_MEMBERSHIP['member_group'];
            }
            break;
        }
        // Set membership status
        $sql = "UPDATE {$_TABLES['membership_members']} SET
                mem_status = $new_status
                WHERE mem_uid = $uid";
        //echo $sql;die;
        DB_query($sql, 1);

        // Remove this member from the membership groups
        foreach ($groups as $group) {
            USER_delGroup($group, $uid);
        }

        // Now do the same thing for all the relatives.
        if ($inc_relatives) {
            $relatives = $Mem->getLinks();
            foreach ($relatives as $key => $name) {
                foreach ($groups as $group) {
                    USER_delGroup($group, $key);
                }
                DB_query("UPDATE {$_TABLES['membership_members']} SET
                        mem_status = $new_status
                        WHERE mem_uid = $key", 1);
                self::updatePlugins('membership:' . $key, $old_status, $new_status);
            }
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
        foreach (Position::getByMember($uid) as $P) {
            $P->setMember(0);
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
     * Used by Shop processing to automatically add or update a membership.
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
        if ($this->Plan->getPlanID() == '') {
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
        $this->paid = Dates::Today();
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
        $mem_number = '';
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
            $plan_name = $this->Plan->getName();
            $plan_dscp = $this->Plan->getDscp();
            $plan_id = $this->Plan->getPlanID();
            $relatives = $this->getLinks();
            //$relatives = Link::getRelatives($this->uid);
            if ($_CONF_MEMBERSHIP['use_mem_number'] && SEC_hasRights('membership.admin')) {
                $mem_number = $this->mem_number;
            }
            $sql = "SELECT descr FROM {$_TABLES['membership_positions']}
                    WHERE uid = $uid";
            $res = DB_query($sql, 1);
            while ($A = DB_fetchArray($res, false)) {
                $positions[] = $A['descr'];
            }
        }
        $position = implode(', ', $positions);
        if (!$this->isNew &&
            App::isRequired() > MEMBERSHIP_APP_DISABLED &&
            App::getInstance($uid)->Exists()
        ) {
            $app_link = true;
        } else {
            $app_link = false;
        }
        $LT = new \Template(MEMBERSHIP_PI_PATH . '/templates/');
        $LT->set_file(array(
            'block' => 'profileblock.thtml',
        ));
        $LT->set_var(array(
            'joined'    => $joined,
            'expires'   => $expires,
            'plan_name' => $plan_name,
            'plan_description' => $plan_dscp,
            'plan_id'   => $plan_id,
            'app_link'  => $app_link,
            'my_uid'    => $uid,
            'panel'     => $panel ? 'true' : '',
            'nolinks'   => empty($relatives) ? 'true' : '',
            //'old_links' => $old_links,
            'position' => $position,
            'mem_number' => $mem_number,
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

        $days = COM_dateDiff('d', $exp, Dates::Today());
        if ($exp < Dates::Today()) $days *= -1;
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

        $days = COM_dateDiff('d', $exp, Dates::Today());
        // Undo absolute value conversion done in COM_dateDiff()
        if ($exp > Dates::Today()) $days *= -1;
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
            if ($this->isNew()) {
                $canPurchase = MEMBERSHIP_CANPURCHASE;
            } elseif ($this->expires > Dates::plusRenewal()) {
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
            if (!isset($args['mem_plan_id'])) {
                $args['mem_plan_id'] = $this->plan_id;
            }
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
        USES_lib_user();

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
        global $_TABLES, $_USER, $_CONF;

        $gateway = DB_escapeString($gateway);
        $amt = (float)$amt;
        $txn_id = DB_escapeString($txn_id);
        $now = empty($dt) ? $_CONF['_now']->toMySQL(true) : DB_escapeString($dt);
        $by = $by == -1 ? (int)$_USER['uid'] : (int)$by;
        $sql = "INSERT INTO {$_TABLES['membership_trans']} SET
            tx_date = '{$now}',
            tx_by = '{$by}',
            tx_uid = '{$this->uid}',
            tx_planid = '{$this->Plan->getPlanID()}',
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
     */
    public static function updatePlugins($uid, $old_status, $new_status)
    {
        global $_TABLES, $_CONF_MEMBERSHIP, $_PLUGINS;

        // No change in status just return OK
        if ($old_status == $new_status) {
            return;
        }

        PLG_itemSaved($uid, $_CONF_MEMBERSHIP['pi_name']);

        /*
        // Gets statuses from plugin config into an array
        //  my_status_name => mailchimp_value_name
        $statuses = MEMBERSHIP_memberstatuses();

        $retval = PLG_RET_OK;
        $uid = (int)$uid;
        $new_status = isset($statuses[$new_status]) ?
                $statuses[$new_status] : null;
        if ($new_status === null) {
            // unrecognized status received
            return;
        }

        // 1. Update the Mailchimp plugin
        if ($_CONF_MEMBERSHIP['update_maillist']) {
            $status = PLGinvokeService('mailchimp', 'updateuser',
                array(
                    'uid' => $uid,
                    'params' => array(
                        'merge_vars' => array(
                            'MEMSTATUS'=> $new_status,
                        ),
                    ),
                ),
                $output,
                $svc_msg
            );
            if ($status != PLG_RET_OK) {
                Logger::System('Membership: Error updating mailling list. ' .
                "User: $uid, Segment $new_status");
                $retval = $status;
            }
        }
        */

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
                    if ($size != $quota) {
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
        }

        return;
    }


    /**
     * Create a membership number.
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
     * Determine if this is a new membership or a renewal.
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
     * Return information for the getItemInfo function in functions.inc.
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
        //var_dump($retval);die;
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
     * If updating, the date joined and member number are not changed.
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
        if ($Mem1->isNew()) {
            Logger::System("Cannot link user $uid2 to nonexistant membership for $uid1");
            return false;
        } elseif (!$Mem1->Plan->isFamily()) {
            Logger::System("Cannot link $uid2 to a non-family plan");
            return false;
        }

        // TODO: Check here for $Mem1->Plan to see if it uses linked accounts?
        $Mem2 = self::getInstance($uid2);
        if ($Mem2->isNew()) {
            if ($_CONF_MEMBERSHIP['use_mem_number']) {
                $mem_number = self::createMemberNumber($uid2);
            } else {
                $mem_number = '';
            }
        } else {
            $mem_number = $Mem2->getMemNumber();
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
                mem_plan_id = '" . DB_escapeString($Mem1->getPlanID()) . "',
                mem_expires = '" . DB_escapeString($Mem1->getExpires()) . "',
                mem_status = '{$Mem1->getStatus()}',
                mem_guid = '{$Mem1->getGuid()}',
                mem_notified = '{$Mem1->expToSend()}',
                mem_istrial = '{$Mem1->isTrial()}'";
        //echo $sql;die;
        DB_query($sql);
        if (DB_error()) {
            Logger::System(__CLASS__ . "/addLink() error: $sql");
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
            Logger::System(__CLASS__ . "::remLink() error: $sql");
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


    /**
     * Display a summary of memberships by plan.
     *
     * @return  string  HTML output for the page
     */
    public static function summaryStats()
    {
        global $_CONF_MEMBERSHIP, $_TABLES;

        // The brute-force way to get summary stats.  There must be a better way.
        $sql = "SELECT DISTINCT(mem_guid), mem_plan_id, mem_expires
            FROM {$_TABLES['membership_members']}
            WHERE mem_expires > '" . Dates::expGraceEnded() . "'";
        $rAll = DB_query($sql);
        $stats = array();
        $template = array('current' => 0, 'arrears' => 0);
        while ($A = DB_fetchArray($rAll, false)) {
            if (!isset($stats[$A['mem_plan_id']])) {
                $stats[$A['mem_plan_id']] = $template;
            }
            if ($A['mem_expires'] >= Dates::Today()) {
                $stats[$A['mem_plan_id']]['current']++;
            } else {
                $stats[$A['mem_plan_id']]['arrears']++;
            }
        }

        $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
        $T->set_file('stats', 'admin_stats.thtml');
        $T->set_block('stats', 'statrow', 'srow');
        $linetotal = 0;
        $tot_current = 0;
        $tot_arrears = 0;
        $gtotal = 0;
        foreach ($stats as $plan_id=>$data) {
            $linetotal = $data['current'] + $data['arrears'];
            $tot_current += $data['current'];
            $tot_arrears += $data['arrears'];
            $gtotal += $linetotal;
            $T->set_var(array(
                'plan'          => $plan_id,
                'num_current'   => $data['current'],
                'num_arrears'   => $data['arrears'],
                'line_total'    => $linetotal,
            ) );
            $T->parse('srow', 'statrow', true);
        }
        $T->set_var(array(
            'tot_current'   => $tot_current,
            'tot_arrears'   => $tot_arrears,
            'grand_total'   => $gtotal,
        ) );
        $T->parse('output', 'stats');
        return $T->get_var('output');
    }


    /**
     * Uses lib-admin to list the members.
     *
     * @return  string  HTML for the list
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_MEMBERSHIP,
            $_CONF_MEMBERSHIP;

        $retval = '';

        $header_arr = array(
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort' => false,
                'align'=>'center',
            ),
        );
        if ($_CONF_MEMBERSHIP['require_app']) {
            $header_arr[] = array(
                'text' => $LANG_MEMBERSHIP['application'],
                'field' => 'app_link',
                'sort' => false,
                'align' => 'center',
            );
        }
        if ($_CONF_MEMBERSHIP['use_mem_number'] > 0) {
            $header_arr[] = array(
                'text' => $LANG_MEMBERSHIP['mem_number'],
                'field' => 'mem_number',
                'sort' => true,
            );
        }
        $header_arr[] = array(
            'text' => $LANG_MEMBERSHIP['member_name'],
            'field' => 'fullname',
            'sort' => true,
        );
        $header_arr[] = array(
            'text' => $LANG_MEMBERSHIP['linked_accounts'],
            'field' => 'links',
            'sort' => false,
        );
        $header_arr[] = array(
            'text' => $LANG_MEMBERSHIP['plan'],
            'field' => 'plan',
            'sort' => false,
        );
        $header_arr[] = array(
            'text' => $LANG_MEMBERSHIP['expires'],
            'field' => 'mem_expires',
            'sort' => true,
        );

        $defsort_arr = array('field' => 'm.mem_expires', 'direction' => 'asc');
        if (isset($_REQUEST['showexp'])) {
            $frmchk = 'checked="checked"';
            $exp_query = '';
        } else {
            $frmchk = '';
            $exp_query = sprintf(
                "AND m.mem_status IN(%d, %d, %d) AND mem_expires >= '%s'",
                MEMBERSHIP_STATUS_ACTIVE,
                MEMBERSHIP_STATUS_ENABLED,
                MEMBERSHIP_STATUS_ARREARS,
                Dates::expGraceEnded()
            );
        }
        $query_arr = array(
            'table' => 'membership_members',
            'sql' => "SELECT m.*, u.username, u.fullname, p.name as plan
                FROM {$_TABLES['membership_members']} m
                LEFT JOIN {$_TABLES['users']} u
                    ON u.uid = m.mem_uid
                LEFT JOIN {$_TABLES['membership_plans']} p
                    ON p.plan_id = m.mem_plan_id
                WHERE 1=1 $exp_query",
            'query_fields' => array('u.fullname', 'u.email'),
            'default_filter' => '',
        );
        $text_arr = array(
            'has_extras' => true,
            'form_url'  => MEMBERSHIP_ADMIN_URL . '/index.php?listmembers',
        );
        $filter = '<input type="checkbox" name="showexp" ' . $frmchk .  '>&nbsp;' .
            $LANG_MEMBERSHIP['show_expired'] . '&nbsp;&nbsp;';

        $del_action = '<button class="uk-button uk-button-mini uk-button-danger" name="deletebutton" ' .
            'onclick="return confirm(\'' . $LANG_MEMBERSHIP['confirm_regen'] . '\');">' .
            '<i class="uk-icon uk-icon-remove"></i> ' . $LANG_ADMIN['delete']. '</button>';
        $renew_action = '<button class="uk-button uk-button-mini" name="renewbutton" ' .
            'onclick="return confirm(\'' . $LANG_MEMBERSHIP['confirm_renew'] . '\');">' .
            '<i class="uk-icon uk-icon-refresh"></i> ' . $LANG_MEMBERSHIP['renew'] . '</button>';
        $options = array(
            'chkdelete' => 'true',
            'chkfield' => 'mem_uid',
            'chkactions' => $del_action . '&nbsp;&nbsp;' . $renew_action . '&nbsp;&nbsp;',
        );

        if ($_CONF_MEMBERSHIP['use_mem_number'] == 2) {
            $options['chkactions'] .= '<button class="uk-button uk-button-mini" name="regenbutton" ' .
                'onclick="return confirm(\'' . $LANG_MEMBERSHIP['confirm_regen'] . '\');">' .
                '<i class="uk-icon uk-icon-cogs"></i> ' . $LANG_MEMBERSHIP['regen_mem_numbers'] . '</button>';
        }
        $form_arr = array();
        $retval .= ADMIN_list(
            'membership_memberlist',
            array(__CLASS__, 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr, $filter, '',
            $options, $form_arr
        );
        return $retval;
    }


    /**
     * Determine what to display in the admin list for each form.
     *
     * @param  string  $fieldname  Name of the field, from database
     * @param  mixed   $fieldvalue Value of the current field
     * @param  array   $A          Array of all name/field pairs
     * @param  array   $icon_arr   Array of system icons
     * @return string              HTML for the field cell
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ACCESS, $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP, $_TABLES,
            $LANG_ADMIN;

        $retval = '';
        $pi_admin_url = MEMBERSHIP_ADMIN_URL;

        switch($fieldname) {
        case 'edit':
            $showexp = isset($_POST['showexp']) ? '&amp;showexp' : '';
            $retval = COM_createLink(
                $_CONF_MEMBERSHIP['icons']['edit'],
                MEMBERSHIP_ADMIN_URL . '/index.php?editmember=' . $A['mem_uid'] . $showexp
            );
            break;

        case 'app_link':
            $url = MEMBERSHIP_PI_URL . '/app.php?prt&uid=' . $A['mem_uid'];
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-eye"></i>',
                '#!',
                array(
                    'onclick' => "popupWindow('{$url}', 'Help', 640, 480, 1)",
                    'title' => $LANG_MEMBERSHIP['view_app'],
                    'class' => 'tooltip',
                )
            );
            break;

        case 'tx_fullname':
            $retval = COM_createLink($fieldvalue,
                MEMBERSHIP_ADMIN_URL . '/index.php?listtrans&amp;uid=' . $A['tx_uid']);
            break;

        case 'fullname':
            $retval = self::createNameLink($A['mem_uid'], $A['fullname']);
            break;

        case 'links':
            $links = self::getInstance($A['mem_uid'])->getLinks();
            $L = array();
            foreach ($links as $uid=>$fullname) {
                $L[] = self::createNameLink($uid);
            }
            if (!empty($L)) {
                $retval = implode('; ', $L);
            }
            break;

        case 'id':
            return $A['id'];
            break;

        case 'mem_expires':
            if ($fieldvalue >= Dates::Today()) {
                $status = 'current';
            } elseif ($fieldvalue >= Dates::expGraceEnded()) {
                $status = 'arrears';
            } else {
                $status = 'expired';
            }
            $retval = "<span class=\"member_$status\">{$fieldvalue}</span>";
            break;

        case 'email':
            $retval = empty($fieldvalue) ? '' :
                "<a href=\"mailto:$fieldvalue\">$fieldvalue</a>";
            break;

        case 'tx_by':
            if ($fieldvalue == 0) {
                $retval = $LANG_MEMBERSHIP['system_task'];
            } else {
                $retval = COM_getDisplayName($fieldvalue);
            }
            break;

        case 'tx_txn_id':
            $non_gw = array('', 'cc', 'check', 'cash');
            $retval = $fieldvalue;
            if (!empty($fieldvalue) && !in_array($A['tx_gw'], $non_gw)) {
                $status = LGLIB_invokeService(
                    'shop', 'getUrl',
                    array(
                        'id'    => $fieldvalue,
                        'type'  => 'order',
                    ),
                    $output, $svc_msg
                );
                if ($status == PLG_RET_OK) {
                    $retval = COM_createLink($fieldvalue, $output);
                }
            }
            break;

        default:
            $retval = $fieldvalue;

        }
        return $retval;
    }


    /**
     * Display the member's full name in the "Last, First" format with a link.
     * Also sets class and javascript to highlight the same user's name elsewhere
     * on the page.
     * Uses a static variable to hold links by user ID for repeated lookups.
     *
     * @param   integer $uid    User ID, used to get the full name if not supplied.
     * @param   string  $fullname   Optional Full override
     * @return  string      HTML for the styled user name.
     */
    public static function createNameLink($uid, $fullname='')
    {
        global $_CONF;

        static $retval = array();

        if (!isset($retval[$uid])) {
            if ($fullname == '') {
                $fullname = COM_getDisplayName($uid);
            }
            $parsed = PLG_callFunctionForOnePlugin(
                'plugin_parseName_lglib',
                array(
                    1 => $fullname,
                    2 => 'LCF',
                )
            );
            if ($parsed === false ) {
                $parsed = $fullname;
            }
            $retval[$uid] = '<span class="member_normal" rel="rel_' . $uid .
                '" onmouseover="MEM_highlight(' . $uid .
                ',1);" onmouseout="MEM_highlight(' . $uid . ',0);">' .
                COM_createLink(
                    $parsed,
                    $_CONF['site_url'] . '/users.php?mode=profile&uid=' . $uid
                )
                . '</span>';
        }
        return $retval[$uid];
    }


    /**
     * List transactions.
     *
     * @return  string  HTML output for the page
     */
    public static function listTrans()
    {
        global $_TABLES, $LANG_MEMBERSHIP, $_CONF;

        $tx_from = MEMB_getVar($_POST, 'tx_from');
        if (!empty($tx_from)) {
            $from_sql = "AND tx_date >= '" . DB_escapeString($tx_from . ' 00:00:00') . "'";
        } else {
            $tx_from = '';
            $from_sql = '';
        }
        $tx_to = MEMB_getVar($_POST, 'tx_to');
        if (!empty($tx_to)) {
            $to_sql = "AND tx_date <= '" . DB_escapeString($tx_to . ' 23:59:59') . "'";
        } else {
            $tx_to = '';
            $to_sql = '';
        }
        $uid = MEMB_getVar($_GET, 'uid', 'integer');
        if ($uid > 0) {
            $user_sql = 'AND tx_uid = ' . (int)$_GET['uid'];
        } else {
            $user_sql = '';
        }

        $query_arr = array(
            'table' => 'membership_trans',
            'sql' => "SELECT tx.*, u.fullname as tx_fullname
                FROM {$_TABLES['membership_trans']} tx
                LEFT JOIN {$_TABLES['users']} u
                    ON u.uid = tx.tx_uid
                WHERE 1=1 $from_sql $to_sql $user_sql",
            'query_fields' => array('u.fullname'),
            'default_filter' => '',
        );
        $defsort_arr = array(
            'field' => 'tx_date',
            'direction' => 'DESC',
        );
        $text_arr = array(
            'has_extras' => true,
            'form_url'  => MEMBERSHIP_ADMIN_URL . '/index.php?listtrans',
        );
        $tx_from = MEMB_getVar($_POST, 'tx_from');
        $tx_to = MEMB_getVar($_POST, 'tx_to');
        $filter = $LANG_MEMBERSHIP['from'] .
            ': <input id="f_tx_from" type="text" size="10" name="tx_from" data-uk-datepicker value="' . $tx_from . '" />&nbsp;' .
            $LANG_MEMBERSHIP['to'] .
            ': <input id="f_tx_to" type="text" size="10" name="tx_to" data-uk-datepicker value="' . $tx_to . '" />';
        $header_arr = array(
            array(
                'text' => $LANG_MEMBERSHIP['date'],
                'field' => 'tx_date',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['entered_by'],
                'field' => 'tx_by',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['member_name'],
                'field' => 'tx_fullname',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['plan'],
                'field' => 'tx_planid',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['expires'],
                'field' => 'tx_exp',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['pmt_method'],
                'field' => 'tx_gw',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['txn_id'],
                'field' => 'tx_txn_id',
                'sort' => true,
            ),
        );
        $form_arr = array();
        return ADMIN_list(
            'membership_listtrans',
            array(__CLASS__, 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr, $filter, '',
            '', $form_arr
        );
    }


    /**
     * Notify users that have memberships soon to expire.
     * This is in functions.inc so it can be called from runscheduledTask.
     */
    public static function notifyExpiration()
    {
        global $_TABLES, $_CONF, $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP, $_USER;

        $interval = (int)$_CONF_MEMBERSHIP['notifydays'];

        // Return if we're not configured to notify users.
        if (
            $interval < 0 ||
            $_CONF_MEMBERSHIP['notifymethod'] == MEMBERSHIP_NOTIFY_NONE
        ) {
            return;
        }
        $stat = MEMBERSHIP_STATUS_ACTIVE . ',' . MEMBERSHIP_STATUS_ENABLED;
        $sql = "SELECT m.mem_uid, m.mem_notified, m.mem_expires, m.mem_plan_id,
                u.email, u.username, u.fullname
            FROM {$_TABLES['membership_members']} m
            LEFT JOIN {$_TABLES['users']} u
                ON u.uid = m.mem_uid
            WHERE m.mem_notified > 0
            AND m.mem_expires < DATE_ADD(now(), INTERVAL (m.mem_notified * $interval) DAY)
            AND m.mem_status IN ($stat)";
        //echo $sql;die;
        //Logger::System($sql);
        $r = DB_query($sql);
        if (!$r || DB_numRows($r) == 0) {
            return;
        }

        $notified_ids = array();    // holds memberhsip IDs that get notified
        $template_base = MEMBERSHIP_PI_PATH . '/templates/notify';

        while ($row = DB_fetchArray($r, false)) {
            if ($_CONF_MEMBERSHIP['notifymethod'] & MEMBERSHIP_NOTIFY_EMAIL) {
                // Create a notification email message.
                $username = COM_getDisplayName($row['mem_uid']);
                $P = Plan::getInstance($row['mem_plan_id']);
                if ($P->isNew() || !$P->notificationsEnabled()) {
                    // Do not send notifications for this plan
                    continue;
                }

                $args = array(
                    'custom'    => array('uid'   => $row['mem_uid']),
                    'amount' => $P->Price(false),
                    'item_number' => $_CONF_MEMBERSHIP['pi_name'] . ':' . $P->getPlanID() .
                        ':renewal',
                    'item_name' => $P->getName(),
                    'btn_type' => 'buy_now',
                );
                $status = LGLIB_invokeService(
                    'shop',
                    'genButton',
                    $args,
                    $output,
                    $msg
                );
                $nameparts = PLG_callFunctionForOnePlugin(
                    'plugin_parseName_lglib',
                    array(
                        1 => $row['fullname'],
                    )
                );
                if ($nameparts !== false) {
                    $fname = $nameparts['fname'];
                    $lname = $nameparts['lname'];
                } else {
                    $fname = '';
                    $lname = '';
                }

                $button = ($status == PLG_RET_OK) ? $output[0] : '';
                $dt = new \Date($A['mem_expires'], $_CONF['timezone']);

                $T = new \Template($template_base);
                $T->set_file(array(
                    'outer' => 'exp_outer.thtml',
                    'message' => 'exp_message.thtml',
                ) );
                $T->set_var(array(
                    'site_name'     => $_CONF['site_name'],
                    'username'      => $username,
                    'pi_name'       => $_CONF_MEMBERSHIP['pi_name'],
                    'plan_name'     => $row['name'],
                    'plan_id'       => $row['plan_id'],
                    'description'   => $row['description'],
                    'detail_url'    => MEMBERSHIP_PI_URL .
                        '/index.php?detail=x&amp;plan_id=' .
                        urlencode($row['plan_id']
                    ),
                    'buy_button'    => $button,
                    'exp_my'        => $dt->format('F, Y', true),
                    'exp_date'      => $dt->format($_CONF['dateonly'], true),
                    'firstname'     => $fname,
                    'lastname'      => $lname,
                    'fullname'      => $row['fullname'],
                ) );

                $T->parse('exp_msg', 'message');
                $T->parse('output', 'outer');
                $message = $T->finish($T->get_var('output'));
                COM_mail(
                    array($row['email'], $username),
                    "{$LANG_MEMBERSHIP['exp_notice']}",
                    $message,
                    "{$_CONF['site_name']} <{$_CONF['site_mail']}>",
                    true
                );
            }

            if ($_CONF_MEMBERSHIP['notifymethod'] & MEMBERSHIP_NOTIFY_MESSAGE) {
                // Save a message for the next time they log in.
                $msg = sprintf(
                    $LANG_MEMBERSHIP['you_expire'],
                    $row['plan_id'],
                    $row['mem_expires']
                ) . ' ' . $LANG_MEMBERSHIP['renew_link'];
                $expire_msg = date(
                    'Y-m-d',
                    strtotime(
                        '-' . $_CONF_MEMBERSHIP['grace_days'] . ' day',
                        strtotime($row['mem_expires'])
                    )
                );
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
                SET mem_notified = mem_notified - 1
                WHERE mem_uid IN ($ids)";
            DB_query($sql, 1);
            if (DB_error()) {
                Logger::System("membership: error executing $sql");
            }
        }
    }

}

?>
