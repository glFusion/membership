<?php
/**
 * Class to handle membership records.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2012-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;
use Membership\Notifiers\Popup;
use Membership\Models\Transaction;
use Membership\Models\MemberNumber;
use Membership\Models\DataArray;
use Membership\Models\Request;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class for a membership record.
 * @package membership
 */
class Membership
{
    /** Membership action types */
    const NEW = 1;
    const RENEW = 2;

    // define values for whether a user can purchase memberships
    const CANPURCHASE = 0;
    const NOPURCHASE = 1;
    const NEED_APP = 2;
    const NO_RENEWAL = 3;

    // Define values for expiration notification method
    const NOTIFY_NONE = 0;
    const NOTIFY_EMAIL = 1;
    const NOTIFY_MESSAGE = 2;
    const NOTIFY_BOTH = 3;

    const MSG_EXPIRING_CODE = 'memb_msg_expiring';
    const MSG_EXPIRED_CODE = 'memb_msg_expired';

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
    private $status = Status::DROPPED;

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
    public function __construct(?int $uid=NULL)
    {
        global $_USER;

        if ($uid === NULL) {
            $uid = (int)$_USER['uid'];
        }
        $this->uid = $uid;
        if ($this->uid > 1 && $this->Read($this->uid)) {
            $this->isNew = false;
        } else {
            $this->joined = Dates::Today();
            $this->notified = (int)Config::get('notifycount');
        }
    }


    /**
     * Get an instance of a specific membership.
     *
     * @param   integer $uid    User ID to retrieve, default=current user
     * @return  object      Membership object
     */
    public static function getInstance(?int $uid = NULL) : self
    {
        global $_USER;

        if (!empty($uid)) {
            $uid = $_USER['uid'];
        }
        if ($uid > 1) {
            $cache_key = 'member_' . $uid;
            $retval = Cache::get($cache_key);
            if ($retval === NULL) {
                $retval = new self($uid);
                if (!$retval->isNew()) {
                    Cache::set($cache_key, $retval, 'members');
                }
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
     * @return  boolean     True on success, False on error
     */
    public function Read(?int $uid = NULL) : bool
    {
        global $_TABLES;

        if (!empty($uid)) {
            $this->uid = $uid;
        }

        try {
            $data = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['membership_members']} WHERE mem_uid = ?",
                array($this->uid),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data) && !empty($data)) {
            $this->setVars(new DataArray($data));
            $this->Plan = new Plan($this->plan_id);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Set all the object variables from an array, either $_POST or DB record.
     *
     * @param   DataArray   $A  Array of values
     * @return  object  $this
     */
    public function setVars(DataArray $A) : self
    {
        if (isset($A['mem_uid'])) {
            // Will be set via DB read, probably not via form
            $this->uid = $A->getInt('mem_uid');
        }
        if (isset($A['mem_joined'])) $this->joined = $A['mem_joined'];
        if (isset($A['mem_expires'])) $this->expires = $A['mem_expires'];
        if (isset($A['mem_plan_id'])) $this->plan_id = $A['mem_plan_id'];
        if (isset($A['mem_status'])) $this->status = (int)$A['mem_status'];
        if (isset($A['mem_notified'])) $this->notified = (int)$A['mem_notified'];
        if (isset($A['mem_number'])) $this->mem_number = $A['mem_number'];
        $this->istrial = $A->getInt('mem_istrial');
        // This will never come from a form:
        if (isset($A['mem_guid'])) $this->guid = $A['mem_guid'];
        return $this;
    }


    /**
     * Get the glFusion user ID.
     *
     * @return  integer     User ID
     */
    public function getUid() : int
    {
        return (int)$this->uid;
    }


    /**
     * Get the Plan ID.
     *
     * @return  string      Plan ID
     */
    public function getPlanID() : string
    {
        return $this->plan_id;
    }


    /**
     * Get the Plan object.
     *
     * @return  object      Plan object.
     */
    public function getPlan() : Plan
    {
        if ($this->Plan === NULL) {
            $this->Plan = Plan::getInstance($this->plan_id);
        }
        return $this->Plan;
    }


    /**
     * Set a new Plan.
     *
     * @param   string  $id     Plan ID
     * @return  object  $this
     */
    public function setPlan(string $id) : self
    {
        $this->plan_id = $id;
        $this->Plan = new Plan($this->plan_id);
        return $this;
    }


    /**
     * Set the expiration date manually.
     *
     * @param   string  $exp    Expiration date YYYY-MM-DD
     * @return  object  $this
     */
    public function setExpires(string $exp) : self
    {
        $this->expires = $exp;
        return $this;
    }


    /**
     * Get the expiration date for the membership.
     *
     * @return  string      Expiration date YYYY-MM-DD
     */
    public function getExpires() : string
    {
        return $this->expires;
    }


    /**
     * Get the join date for the membership.
     *
     * @return  string      Date joined as YYYY-MM-DD
     */
    public function getJoined() : string
    {
        return $this->joined;
    }


    /**
     * Set the membership number value.
     *
     * @param   string  $mem_num    Membership number
     * @return  object  $this
     */
    public function setMemNumber(string $mem_num) : self
    {
        $this->mem_number = $mem_num;
        return $this;
    }


    /**
     * Get the membership number.
     *
     * @return  string      Membership number
     */
    public function getMemNumber() : string
    {
        return $this->mem_number;
    }


    public function setTrial(bool $flag) : self
    {
        $this->istrial = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Get the unique ID for the membership. Used to link accounts.
     *
     * @return  string      Globally unique ID
     */
    public function getGuid() : string
    {
        return $this->guid;
    }


    /**
     * Check if this is a trial membership.
     *
     * @return  integer     1 if trial, 0 if regular
     */
    public function isTrial() : int
    {
        return $this->istrial ? 1 : 0;
    }


    /**
     * Check if the member has already been notified of impending expiration.
     *
     * @return  integer     1 if notified, 0 if not
     */
    public function expToSend() : int
    {
        return (int)$this->notified;
    }


    /**
     * Get the member status (active, expired, etc.)
     *
     * @return  integer     Value to indicate status
     */
    public function getStatus() : int
    {
        return (int)$this->status;
    }


    /**
     * Check if this membership is expired.
     * Compares the expiration date to the date when the grace period ends.
     *
     * @return  boolean     True if expired, False if current or in arrears.
     */
    public function isExpired() : bool
    {
        return Status::fromExpiration($this->expires) == Status::EXPIRED;
    }


    /**
     * Check if this membership is in arrears.
     * Checks that today is between the expiration date and the end of the
     * grace period.
     *
     * @return  boolean     True if in arrears, False if current or expired.
     */
    public function isArrears() : bool
    {
        return Status::fromExpiration($this->expires) == Status::ARREARS;
    }


    /**
     * Check if the membership is current.
     * Just checks that the expiration falls after today.
     *
     * @return  boolean     True if current, False if in arrears or expired
     */
    public function isCurrent() : bool
    {
        return ($this->getExpires() > Dates::Today());
    }


    /**
     * Create the edit member for all the members variables.
     * Checks the type of edit being done to select the right template.
     *
     * @param   string  $action_url Form action url, empty if within profile editing
     * @return  string          HTML for edit member
     */
    public function EditForm(string $action_url = '') : string
    {
        global $_CONF, $_TABLES, $LANG_MEMBERSHIP, $LANG_MEMBERSHIP_PMTTYPES;

        if ($this->uid == 0) {
            $this->expires = Dates::calcExpiration();
            $tpl = 'createmember.thtml';
        } else {
            $tpl = 'editmember.thtml';
        }

        $T = new \Template(Config::get('pi_path') . 'templates');
        $T->set_file(array(
            'editmember' => $tpl,
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
            'use_mem_number' => Config::get('use_mem_number') ? 'true' : '',
            'mem_istrial' => $this->istrial,
            'mem_istrial_chk' => $this->istrial ? 'checked="checked"' : '',
            'is_family' => $this->Plan ? $this->Plan->isFamily() : 0,
            'lang_x_interval' => sprintf($LANG_MEMBERSHIP['at_x_interval'], Config::get('notifydays')),
        ) );
        if ($this->uid == 0) {
            try {
                $db = Database::getInstance();
                $data = $db->conn->executeQuery(
                    "SELECT u.uid,u.fullname FROM {$_TABLES['users']} u
                    LEFT JOIN {$_TABLES['membership_members']} m
                    ON u.uid = m.mem_uid WHERE m.mem_uid IS NULL"
                )->fetchAllAssociative();
            } catch (\Exception $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $data = false;
            }
            if (!empty($data)) {
                $opts = '';
                foreach ($data as $A) {
                    $opts .= '<option value="' . $A['uid'] . '">' . $A['fullname'] . '</option>' . LB;
                }
                $T->set_var('member_select', $opts);
            }
        }
        $T->set_block('editmember', 'expToSend', 'expTS');
        for ($i = 0; $i <= Config::get('notifycount'); $i++) {
            $T->set_var(array(
                'notify_val' => $i,
                'sel' => $i == $this->notified ? 'selected="selected"' : '',
            ) );
            $T->parse('expTS', 'expToSend', true);
        }

        $T->set_block('editmember', 'pmttype_block', 'pt_blk');
        foreach ($LANG_MEMBERSHIP_PMTTYPES as $key=>$val) {
            $T->set_var(array(
                'pmt_key' => $key,
                'pmt_name' => $val,
            ) );
            $T->parse('pt_blk', 'pmttype_block', true);
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
                'renew_url'  => Config::get('admin_url') . '/index.php?quickrenew',
            ) );
        }

        $family_plans = array();
        $T->set_block('editmember', 'PlanBlock', 'planrow');
        $Plans = Plan::getPlans('', true);
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

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        try {
            $qb->select('uid', 'username', 'fullname')
               ->from($_TABLES['users'])
               ->where('uid > 1')
               ->andWhere('uid <> :uid')
               ->setParameter('uid', $this->uid, Database::INTEGER)
               ->orderBy('fullname', 'ASC');
            if (!empty($link_ids)) {
                $qb->andWhere('uid NOT IN (:link_ids)')
                   ->setParameter('link_ids', $link_ids, Database::PARAM_INT_ARRAY);
            }
            $data = $qb->execute()->fetchAllAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }

        $T->set_block('editmember', 'linkSelect', 'linksel');
        if (is_array($data)) {
            foreach ($data as $A) {
                $T->set_var(array(
                    'link_id'   => $A['uid'],
                    'link_name' => empty($A['fullname']) ? $A['username'] : $A['fullname'],
                ) );
                $T->parse('linksel', 'linkSelect', true);
            }
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
    public function Save(?DataArray $A=NULL) : bool
    {
        global $_TABLES, $_USER;

        $Request = Request::getInstance();

        // Save the old membership object to check later if there are changes.
        $OldMembership = clone $this;

        $db = Database::getInstance();
        $old_status = $this->status;  // track original status
        if (!$A) {
            $A = new DataArray();
        } else {
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
        // Now see if we're changing from a Family plan to Non-Family.
        // For this we need to reset the guid to effectively emancipate
        // this member while leaving all other family members alone.
        if ($OldMembership->getPlan()->isFamily() && !$this->Plan->isFamily()) {
            $this->guid = '';
        }

        $this->status = Status::fromExpiration($this->expires);

        // Set a flag to see if the membership status, group, etc. needs
        // to be updated based on form input. Quick renewal forces an update.
        $need_membership_update = !$this->Matches($OldMembership);

        // The first thing is to check to see if we're removing this account
        // from the family so we don't update other members incorrectlya
        if ($this->Plan->isFamily()) {
            if ($Request->getInt('emancipate') == 1) {
                self::remLink($this->uid);
                $need_membership_update = true;
            } else {
                $orig_links = $A->getArray('mem_orig_links');
                $new_links = $A->getArray('mem_links');
                $arr = array_diff($orig_links, $new_links);
                if (!empty($arr)) {
                    $need_membership_update = true;
                    foreach ($arr as $uid2) {
                        self::remLink($uid2);
                    }
                }
                $arr = array_diff($new_links, $orig_links);
                if (!empty($arr)) {
                    $need_membership_update = true;
                    foreach ($arr as $uid2) {
                        $this->addLink($uid2);
                    }
                }
            }
        }

        // After the links have been updated, check for the "Cancel" checkbox.
        // If set, cancel this member's membership along with all the new links
        // and return.
        if (isset($A['mem_cancel'])) {
            $this->Cancel();
            return true;
        }

        // If this plan updates linked accounts, get all the accounts.
        // Already updated any link changes above.
        if ($need_membership_update && $this->Plan->isFamily()) {
            $accounts = $this->getLinks();
            $accounts[$this->uid] = '';
            Cache::clear('members');
        } else {
            // Don't bother updating others if no key fields changed
            $accounts = array($this->uid => '');
        }

        // Create a guid (just an md5()) for the membership.
        // Only for memberships that don't already have one, e.g. new.
        if ($this->guid == '') {
            $this->guid = self::_makeGuid($this->uid);
            $need_membership_update = true;
        }

        if (!$need_membership_update) {
            // No database updated needed.
            return true;
        }

        Cache::clear('members');
        USES_lib_user();
        foreach ($accounts as $key => $name) {

            // Create membership number if not already defined for the account
            // Include trailing comma, be sure to place it appropriately in
            // the sql statement that follows
            if (
                Config::get('use_mem_number') == MemberNumber::AUTOGEN &&
                $this->isNew &&
                $this->mem_number == ''
            ) {
                $this->mem_number = MemberNumber::create($key);
            }

            $values = array(
                'mem_plan_id' => $this->plan_id,
                'mem_joined' => $this->joined,
                'mem_expires' => $this->expires,
                'mem_status' => $this->status,
                'mem_guid' => $this->guid,
                'mem_number' => $this->mem_number,
                'mem_notified' => $this->notified,
                'mem_istrial' => $this->istrial,
            );
            $types = array(
                Database::STRING,
                Database::STRING,
                Database::STRING,
                Database::STRING,
                Database::STRING,
                Database::STRING,
                Database::INTEGER,
                Database::INTEGER,
                Database::INTEGER,  // extra for mem_uid in values or where
            );
            try {
                $values['mem_uid'] = $this->uid;
                $db->conn->insert($_TABLES['membership_members'], $values, $types);
                Log::write(Config::PI_NAME, Log::INFO,
                    "Member {$key} " . COM_getDisplayName($key) . " created by {$_USER['username']}."
                );
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
                try {
                    $where = array('mem_uid' => $this->uid);
                    $db->conn->update($_TABLES['membership_members'], $values, $where, $types);
                    Log::write(Config::PI_NAME, Log::INFO,
                        "Member {$key} " . COM_getDisplayName($key) . " updated by {$_USER['username']}."
                    );
                } catch (\Throwable $e) {
                    Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
                } 
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
            }

            // Add the member to the groups if the status has changed,
            // and the status is active. If the expiration was set to a past
            // date then the status and group changes will be handled by
            // runScheduledTask
            if ($need_membership_update) {
                if ($this->status == Status::ACTIVE) {
                    Log::write('system', Log::DEBUG, "membership:: Adding user $key to group " . Config::get('member_group'));
                    USER_addGroup(Config::get('member_group'), $key);
                }
                self::updatePlugins(array($key), $old_status, $this->status);
            }
        }

        return true;
    }


    /**
     * Update a membership status. Currently only cancels or deletes members.
     *
     * @param   integer $uid            User ID of member being removed
     * @param   boolean $inc_relatives  True to include relatives
     * @param   integer $old_status     Original status being changed, for logging
     * @param   integer $new_status     New status value to set
     * @return  boolean     True on success, False on error
     */
    private function _UpdateStatus(int $uid, bool $inc_relatives, int $old_status, int $new_status) : bool
    {
        global $_TABLES;

        $uid = (int)$uid;
        if ($uid < 2) return false;
        $new_status = (int)$new_status;
        USES_lib_user();

        // Remove the member from the membership group
        $groups = array();
        switch ($new_status) {
        case Status::EXPIRED:
            if (!empty(Config::get('member_group'))) {
                $groups[] = Config::get('member_group');
            }
            break;
        }
        // Set membership status
        $update_keys = array($uid);

        // Remove this member from the membership groups
        foreach ($groups as $group) {
            USER_delGroup($group, $uid);
        }

        // Now do the same thing for all the relatives.
        if ($inc_relatives) {
            $relatives = $this->getLinks();
            foreach ($relatives as $key => $name) {
                foreach ($groups as $group) {
                    USER_delGroup($group, $key);
                }
                $update_keys[] = $key;
            }
        }
        if (!empty($update_keys)) {
            $db = Database::getInstance();
            try {
                $db->conn->executeQuery(
                    "UPDATE {$_TABLES['membership_members']} SET
                    mem_status = ?
                    WHERE mem_uid IN (?)",
                    array($new_status, $update_keys),
                    array(Database::INTEGER, Database::PARAM_INT_ARRAY)
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
            }
            self::updatePlugins($update_keys, $old_status, $new_status);
        }
        return true;
    }


    /**
     * Cancel a membership.
     * Called when the administrator removes a membership plan from a
     * member's profile. Updates the expiration date to the current date
     * and then calls self::Expire() to perform normal expiration actions.
     *
     * @param   integer $uid    User ID to cancel
     * @param   boolean $cancel_relatives   True to cancel linked accounts
     */
    public function Cancel(?bool $cancel_relatives=NULL) : void
    {
        global $_TABLES, $_CONF;

        if ($cancel_relatives === NULL) {
            $cancel_relatives = true;
        }

        $db = Database::getInstance();
        try {
            $values = array(
                'mem_expires' => Dates::Today(),
                'mem_notified' => 0,
            );
            $types = array(
                Database::STRING,
                Database::INTEGER,
            );
            if ($cancel_relatives) {
                $where = array('mem_guid' => $this->getGuid());
                $types[] = Database::STRING;
            } else {
                $where = array('mem_uid' => $this->getUid());
                $types[] = Database::INTEGER;
            }
            $db->conn->update($_TABLES['membership_members'], $values, $where, $types);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
        }
        $this->Expire($cancel_relatives, false);
    }


    /**
     * Expire a membership.
     * Called from PurgeOld when the membership has expired, and from
     * self::Cancel for memberships that are cancelled manually.
     * Assume the current status is "Active" to force status-change operations.
     *
     * @param   boolean $cancel_relatives   True to cancel linked accounts
     * @param   boolean $notify True to send normal notification
     * @return  object  $this
     */
    public function Expire(bool $cancel_relatives=true, bool $notify=true) : self
    {
        // Remove this member from any club positions held
        foreach (Position::getByMember($this->uid) as $P) {
            $P->setMember(0);
        }
        // Disable the account if so configured
        $this->_disableAccount();

        // Send a final notification, if notifications are used
        if ($notify) {
            $N = new Notifiers\Expiration;
            $N->withUids(array($this->uid))
              ->Notify();
        }

        Cache::clear('members');
        $this->_UpdateStatus(
            $this->uid,
            $cancel_relatives,
            Status::ARREARS,
            Status::EXPIRED
        );
        return $this;
    }


    /**
     * Set a member to "in arrears"
     * Called from plugin_runScheduledTask when the membership is overdue.
     * Assume the current status is "Active" to force status-change operations.
     *
     * @param   boolean $cancel_relatives   True to cancel linked accounts
     * @param   boolean $notify True to send normal notification
     * @return  object  $this
     */
    public function Arrears(bool $cancel_relatives=true, bool $notify=true) : self
    {
        // Send a final notification, if notifications are used
        if ($notify) {
            $N = new Notifiers\Expiration;
            $N->withUids(array($this->uid))
              ->Notify();
        }

        $this->_UpdateStatus(
            $this->uid,
            $cancel_relatives,
            Status::ACTIVE,
            Status::ARREARS
        );
        return $this;
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
    public function Add(Transaction $Txn, bool $calc_exp=true) : bool
    {
        if ($this->getPlan()->getPlanID() == '') {
            return false;       // invalid plan requested
        }

        $this->notified = 0;
        $this->status = Status::ACTIVE;
        if ($this->expires == '')  {
            $this->expires = Dates::Today();
        }
        if (!$this->istrial && $calc_exp) {
            $this->expires = Dates::calcExpiration($this->expires);
        }

        if ($this->joined == '') {
            $this->joined = Dates::Today();
        }
        if ($this->Save()) {
            $Txn->withExpiration($this->expires)
                ->withUid($this->uid)
                ->save();
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
    public function Price() : float
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
     * @see     plugin_profileblocksdisplay_membership()
     * @param   boolean $panel  True if showing in the panel, false if not.
     * @param   integer $uid    User ID being displayed, default = current user
     * @return  string      HTML for membership data display
     */
    public function showInfo(bool $panel = false, int $uid = 0) : string
    {
        global $LANG_MEMBERSHIP, $_USER, $_TABLES, $_SYSTEM;

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
            if (Config::get('use_mem_number') && SEC_hasRights('membership.admin')) {
                $mem_number = $this->mem_number;
            }
            $db = Database::getInstance();
            try {
                $data = $db->conn->executeQuery(
                    "SELECT descr FROM {$_TABLES['membership_positions']}
                    WHERE uid = ?",
                    array($uid),
                        array(Database::INTEGER)
                )->fetchAllAssociative();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
                $data = false;
            }
            if (is_array($data)) {
                foreach ($data as $A) {
                    $positions[] = $A['descr'];
                }
            }
        }
        $position = implode(', ', $positions);
        if (!$this->isNew &&
            App::isRequired() > App::DISABLED &&
            App::getInstance($uid)->Exists()
        ) {
            $app_link = true;
        } else {
            $app_link = false;
        }
        $LT = new \Template(Config::get('pi_path') . 'templates/');
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
            'use_mem_number' => Config::get('use_mem_number') ? 'true' : '',
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
    public static function Transfer(string $old_plan, string $new_plan) : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        // Verify that the new plan exists
        if (empty($old_plan) || empty($new_plan) ||
            $db->getCount(
                $_TABLES['membership_plans'],
                array('plan_id'),
                array($new_plan),
                array(Database::STRING)
            ) == 0) {
            return false;
        }

        try {
            $db->conn->executeStatement(
                "UPDATE {$_TABLES['membership_members']}
                SET mem_plan_id = ?
                WHERE mem_plan_id = ?",
                array($new_plan, $old_plan),
                array(Database::STRING, Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * Get the number of days until this membership expires.
     * If the membership is already expired, return a negative number.
     *
     * @uses    COM_dateDiff() but reverses the abs() used there
     * @param   string  $exp    Expiration date (YYYY-MM-DD)
     * @return  integer     Days expired, negative if already expired.
     */
    public static function DaysToExpire(string $exp) : int
    {
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
    public static function DaysExpired(string $exp) : int
    {
        $days = COM_dateDiff('d', $exp, Dates::Today());
        // Undo absolute value conversion done in COM_dateDiff()
        if ($exp > Dates::Today()) $days *= -1;
        return $days;
    }


    /**
     * Determine if the current user can purchase a membership.
     * Checks if the user is anonymous, or if not within the early_renewal
     *
     * @return  integer     Flag indicating purchase eligibility
     */
    public function canPurchase() : int
    {
        if (COM_isAnonUser()) {
            $canPurchase = self::NOPURCHASE;
        } else {
            if ($this->isNew()) {
                $canPurchase = self::CANPURCHASE;
            } elseif ($this->expires > Dates::plusRenewal()) {
                $canPurchase = self::NO_RENEWAL;
            } else {
                $canPurchase = self::CANPURCHASE;
            }
        }
        return $canPurchase;
    }


    /**
     * Renew a membership.
     * Calls Dates::calcExpiration() function to get the correct
     * expiration date, then creates an array of args to simulate a POST.
     *
     * Argument array includes:
     * - exp         => New expiration date, calculated if omitted
     * - mem_pmttype => Payment type, no payment transaction if omitted
     * - mem_pmtamt  => Payment amount
     * - mem_pmtdate => Payment date
     * - mem_pmtdesc => Payment description
     *
     * @uses    Dates::calcExpiration()
     * @param   array   $args   Array of arguments
     * @return  boolean     True on success, False on failure
     */
    public function Renew(Transaction $Txn) : bool
    {
        $new_planid = $Txn->getPlanId();
        if (empty($new_planid)) {
            $Txn->withPlanId($this->plan_id);
        } elseif ($new_planid != $this->plan_id) {
            // If a different plan ID is supplied, validate it.
            $Plan = Plan::getInstance($new_planid);
            if (!$Plan->isNew()) {
                $this->plan_id = $new_planid;
            } else {
                // Invalid plan ID supplied, reset to the original.
                $Txn->withPlanId($this->plan_id);
            }
        }
        $this->setTrial(false);     // Renewals can't be trial
        $this->expires = Dates::calcExpiration($this->expires);
        $this->notified = (int)Config::get('notifycount');
        $Txn->withExpiration($this->expires)
            ->save();
        // Remove the renewal popup message
        Popup::deleteOne($this->uid, self::MSG_EXPIRING_CODE);
        return $this->Save();
    }


    /**
     * Delete a membership record.
     * Only the specified user is deleted; linked accounts are not affected.
     * The specified user is also removed from the linked accounts.
     */
    public function Delete() : void
    {
        global $_TABLES;
        USES_lib_user();

        if ($this->isNew() || $this->uid < 2) {
            return;
        }

        // Remove this user from the family
        self::remLink($this->uid);

        // Remove this user from the membership group
        USER_delGroup(Config::get('member_group'), $this->uid);

        // Delete this membership record
        $db = Database::getInstance();
        try {
            $db->conn->delete(
                $_TABLES['membership_members'],
                array('mem_uid' => $this->uid),
                array(Database::INTEGER)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
        }
        Cache::clear('members');     // Make sure members and links are cleared
        $this->_disableAccount();
    }


    /**
     * Update other plugins based on a changed membership status.
     *
     * @param   integer $uid            User ID
     * @param   integer $old_status     Original member status
     * @param   integer $new_status     New member status
     */
    public static function updatePlugins(array $uids, int $old_status, int $new_status) : void
    {
        global $_TABLES, $_PLUGINS;

        // No change in status just return OK
        if ($old_status == $new_status) {
            return;
        }

        foreach ($uids as $uid) {
            PLG_itemSaved('membership:' . $uid, Config::PI_NAME);
        }

        // Update the image quota in mediagallery.
        // Mediagallery doesn't have a service function, have to update the
        // database directly. Don't update users with unlimited quotas.
        if (
            Config::get('manage_mg_quota')  &&
            in_array('mediagallery', $_PLUGINS)
        ) {
            $db = Database::getInstance();
            $quota = $db->getItem(
                $_TABLES['mg_userprefs'],
                'quota',
                array('uid' => $uid)
            );
            if ($quota > 0) {
                $max = (int)Config::get('mg_quota_member');
                $min = (int)Config::get('mg_quota_nonmember');
                // sanity checking. Min must be positive to have an effect,
                // zero is unlimited. Max can be zero but otherwise must be > min
                if ($min < 1) $min = 1;
                if ($max == 0 || $min < $max) {
                    switch ($mem_status) {
                    case Status::ACTIVE:
                    case Status::ARREARS:
                        $size = $max * 1048576;
                        break;
                    default:
                        $size = $min * 1048576;
                        break;
                    }
                    if ($size != $quota) {
                        // Update the MG uerpref table with the new quota.
                        // Ignore errors, nothing to be done about them here.
                        try {
                            $db->conn->insert(
                                $_TABLES['mg_userprefs'],
                                array(`uid` => $uid, 'quota' => $size),
                                array(Database::INTEGER, Database::INTEGER)
                            );
                        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
                            try {
                                $db->conn->update(
                                    $_TABLES['mg_userprefs'],
                                    array('quota' => $size),
                                    array('uid' => $uid),
                                    array(Database::INTEGER, Database::INTEGER)
                                );
                            } catch (\Exception $e) {
                                Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
                            }
                        } catch (\Exception $e) {
                            Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }


    /**
     * Determine if this is a new membership or a renewal.
     * For pricing purposes trial memberships are considered "new".
     *
     * @return  boolean     True for a new membership, False for existing
     */
    public function isNew() : bool
    {
        return $this->isNew;
    }


    /**
     * Get a short description for display in messages.
     *
     * @return  string  Description
     */
    public function planDescription() : string
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
     */
    private function _disableAccount() : void
    {
        global $_TABLES;

        if (Config::get('disable_expired')) {
            // Disable the user account at expiration, if so configured
            $db = Database::getInstance();
            try {
                $db->conn->update(
                    $_TABLES['users'],
                    array('status' => USER_ACCOUNT_DISABLED, 'uid' => $uid),
                    array(Database::INTEGER, Database::INTEGER)
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
            }
        }
    }


    /**
     * Return information for the getItemInfo function in functions.inc.
     *
     * @param   array   $what   Array of field names, already exploded
     * @param   array   $options    Additional options
     * @return  array       Array of fieldname=>value
     */
    public function getItemInfo(array $what, array $options = array()) : array
    {
        $retval = array();
        $U = User::getInstance($this->uid);

        foreach ($what as $fld) {
            switch ($fld) {
            case 'id':
                $retval[$fld] = $this->uid;
                break;
            case 'merge_fields':
                $retval[$fld] = Status::getMergeFields($this->status);
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
            case 'list_segment':
                $retval[$fld] = Status::getSegment($this->status);
                break;
            default:
                // User fields
                if (isset($U->$fld)) {
                    $retval[$fld] = $U->$fld;
                } else {
                    $retval[$fld] = '';
                }
                if ($retval[$fld] === NULL) {
                    // set from the object, but null
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
    private static function _makeGuid(string $seed) : string
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
    public function addLink(int $uid2) : bool
    {
        global $_TABLES;

        if ($this->isNew()) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): Cannot link user $uid2 to nonexistant membership for $uid1");
            return false;
        } elseif (!$this->getPlan()->isFamily()) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): Cannot link $uid2 to a non-family plan");
            return false;
        }

        $Mem2 = self::getInstance($uid2);
        if ($Mem2->isNew()) {
            if (Config::get('use_mem_number') == MemberNumber::AUTOGEN) {
                $mem_number = MemberNumber::create($uid2);
            } else {
                $mem_number = '';
            }
        } else {
            $mem_number = $Mem2->getMemNumber();
        }

        $db = Database::getInstance();
        try {
            $sql = "INSERT INTO {$_TABLES['membership_members']} (
                mem_uid, mem_plan_id, mem_joined, mem_expires, mem_status, mem_guid,
                mem_number, mem_notified, mem_istrial
                ) SELECT
                :uid2, mem_plan_id, mem_joined, mem_expires, mem_status, mem_guid,
                :mem_number, mem_notified, mem_istrial
                FROM {$_TABLES['membership_members']}
                WHERE mem_uid = :uid1
                ON DUPLICATE KEY UPDATE
                mem_plan_id = :plan_id,
                mem_expires = :expires,
                mem_status = :status,
                mem_guid = :guid,
                mem_notified = :notified,
                mem_number = :mem_number,
                mem_istrial = :istrial";
            $stmt = $db->conn->prepare($sql);
            $stmt->bindValue('uid1', $this->uid, Database::INTEGER);
            $stmt->bindValue('uid2', $uid2, Database::INTEGER);
            $stmt->bindValue('plan_id', $this->getPlanID(), Database::STRING);
            $stmt->bindValue('expires', $this->getExpires(), Database::STRING);
            $stmt->bindValue('status', $this->getStatus(), Database::INTEGER);
            $stmt->bindValue('guid', $this->getGuid(), Database::STRING);
            $stmt->bindValue('notified', $this->expToSend(), Database::INTEGER);
            $stmt->bindValue('istrial', $this->isTrial(), Database::INTEGER);
            $stmt->bindValue('mem_number', $mem_number, Database::INTEGER);
            $stmt->executeStatement();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        Log::write(Config::PI_NAME, Log::INFO , "Member $uid2 linked to member {$this->uid}");
        Cache::clear('members');
        return true;
    }


    /**
     * Remove a linked membership from the family.
     * The GUID is changed so it is now a standalone membership, nothing else
     * is changed.
     *
     * @param   integer $uid    Account ID being unlinked
     * @return  boolean     True on success, False on error
     */
    public static function remLink(int $uid) : bool
    {
        global $_TABLES;

        $uid = (int)$uid;
        if ($uid < 2) return false;

        $db = Database::getInstance();
        try {
            $db->conn->update(
                $_TABLES['membership_members'],
                array('mem_guid' => self::_makeGuid($uid)),
                array('mem_uid' => $uid),
                array(Database::STRING, Database::INTEGER)
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        Log::write('system', Log::INFO, "Member $uid links removed");
        Cache::clear('members');
        return true;
    }


    /**
     * Get all accounts related to the specified account.
     *
     * @param   mixed   $uid    User ID
     * @return  array       Array of relatives (uid => username)
     */
    public function getLinks() : array
    {
        global $_TABLES;

        // If uid is empty, use the curent id
        if ($this->uid < 1) return array();   // invalid user ID requested

        $cache_key = 'links_' . $this->uid;
        $relatives = Cache::get($cache_key);
        if ($relatives === NULL) {
            $relatives = array();
            $db = Database::getInstance();
            try {
                $data = $db->conn->executeQuery(
                    "SELECT m.mem_uid, u.fullname, u.username
                    FROM {$_TABLES['membership_members']} m
                    LEFT JOIN {$_TABLES['users']} u
                    ON m.mem_uid = u.uid
                    WHERE m.mem_guid = ?
                    AND m.mem_uid <> ?",
                    array($this->guid, $this->uid),
                    array(Database::STRING, Database::INTEGER)
                )->fetchAllAssociative();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $data = false;
            }
            if (is_array($data)) {
                foreach ($data as $A) {
                    $relatives[$A['mem_uid']] = empty($A['fullname']) ?
                        $A['username'] : $A['fullname'];
                }
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
    public static function summaryStats() : string
    {
        global $_TABLES;

        // The brute-force way to get summary stats.  There must be a better way.
        $sql = "SELECT mem_plan_id,
            sum(case when mem_status = ? then 1 else 0 end) as active,
            sum(case when mem_status = ? then 1 else 0 end) as arrears
            FROM {$_TABLES['membership_members']}
            WHERE mem_expires > ? 
            GROUP BY mem_plan_id";
        $db = Database::getInstance();
        try {
            $rAll = $db->conn->executeQuery(
                $sql,
                array(Status::ACTIVE, Status::ARREARS, Dates::expGraceEnded()),
                array(Database::INTEGER, Database::INTEGER, Database::STRING)
            )->fetchAllAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $rAll = false;
        }

        $T = new \Template(Config::get('pi_path') . 'templates');
        $T->set_file('stats', 'admin_stats.thtml');
        $linetotal = 0;
        $tot_current = 0;
        $tot_arrears = 0;
        $gtotal = 0;
        if (is_array($rAll)) {
            foreach ($rAll as $A) {
                $T->set_block('stats', 'statrow', 'srow');
                $linetotal = $A['active'] + $A['arrears'];
                $tot_current += $A['active'];
                $tot_arrears += $A['arrears'];
                $gtotal += $linetotal;
                $T->set_var(array(
                    'plan'          => $A['mem_plan_id'],
                    'plan_url'      => Config::get('admin_url') .
                        '/index.php?listmembers&plan=' . $A['mem_plan_id'],
                    'num_current'   => $A['active'],
                    'num_arrears'   => $A['arrears'],
                    'line_total'    => $linetotal,
                ) );
                $T->parse('srow', 'statrow', true);
            }
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
     * Check if this object matches the provided object for key values.
     *
     * @param   object  $B      Object "B" to test
     * @return  bool    True if the objects match, False if not
     */
    public function Matches(Membership $B) : bool
    {
        if (
            $this->plan_id != $B->getPlanID() ||
            $this->joined != $B->getJoined() ||
            $this->expires != $B->getExpires() ||
            $this->istrial != $B->isTrial() ||
            $this->status != $B->getStatus() ||
            $this->notified != $B->expToSend()
        ) {
            return false;
        }
        return true;
    }


    /**
     * Uses lib-admin to list the members.
     *
     * @return  string  HTML for the list
     */
    public static function adminList() : string
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_MEMBERSHIP;

        $retval = '';
        $Request = Request::getInstance();

        $header_arr = array(
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort' => false,
                'align'=>'center',
            ),
        );
        if (Config::get('require_app')) {
            $header_arr[] = array(
                'text' => $LANG_MEMBERSHIP['application'],
                'field' => 'app_link',
                'sort' => false,
                'align' => 'center',
            );
        }
        if (Config::get('use_mem_number') > 0) {
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
            'text' => $LANG_MEMBERSHIP['joined'],
            'field' => 'mem_joined',
            'sort' => true,
            'nowrap' => true,
        );
        $header_arr[] = array(
            'text' => $LANG_MEMBERSHIP['expires'],
            'field' => 'mem_expires',
            'sort' => true,
            'nowrap' => true,
        );

        $defsort_arr = array('field' => 'm.mem_expires', 'direction' => 'asc');
        if (isset($Request['showexp'])) {
            $frmchk = 'checked="checked"';
            $showexp_chk = true;
            $exp_query = '';
        } else {
            $frmchk = '';
            $showexp_chk = false;
            $exp_query = sprintf(
                "AND m.mem_status IN(%d, %d) AND mem_expires >= '%s'",
                Status::ACTIVE,
                Status::ARREARS,
                Dates::expGraceEnded()
            );
        }
        if (isset($Request['plan']) && !empty($Request['plan'])) {
            $sel_plan = DB_escapeString($Request['plan']);
            $exp_query .= " AND plan_id = '$sel_plan'";
        } else {
            $sel_plan = '';
        }

        $fullname = "IF (u.fullname = '' OR u.fullname IS NULL,
            u.fullname,
            CONCAT(
                SUBSTRING_INDEX(u.fullname,' ',-1), ', ',
                SUBSTRING_INDEX(u.fullname,' ',1)
                )
            ) AS fullname,
            SUBSTRING_INDEX(u.fullname,'',-1) AS lname,
            SUBSTRING_INDEX(u.fullname,'',1) AS fname";
        $query_arr = array(
            'table' => 'membership_members',
            'sql' => "SELECT m.*, u.username, $fullname, p.name as plan
                FROM {$_TABLES['membership_members']} m
                LEFT JOIN {$_TABLES['users']} u
                    ON u.uid = m.mem_uid
                LEFT JOIN {$_TABLES['membership_plans']} p
                    ON p.plan_id = m.mem_plan_id
                WHERE 1=1 $exp_query",
            'query_fields' => array('u.fullname', 'u.email'),
            'default_filter' => '',
        );
//            echo $query_arr['sql'];die;

        $text_arr = array(
            'has_extras' => true,
            'form_url'  => Config::get('admin_url') . '/index.php?listmembers',
        );

        $T = new \Template(Config::get('pi_path') . 'templates/admin/');
        $T->set_file('filter', 'memb_filter.thtml');
        $T->set_var(array(
            'exp_chk' => $frmchk,
            'plan_opts' => COM_optionList(
                $_TABLES['membership_plans'],
                'plan_id,plan_id',
                $sel_plan
            ),
        ) );
        $T->parse('output', 'filter');
        $filter = $T->finish($T->get_var('output'));

        $del_action = FieldList::deleteButton(array(
            'name' => 'deletebutton',
            'text' => $LANG_ADMIN['delete'],
            'attr' => array(
                'onclick' => "return confirm('{$LANG_MEMBERSHIP['confirm_regen']}');",
            ),
        ) );
        $renew_action = FieldList::renewButton(array(
            'name' => 'renewbutton',
            'text' => $LANG_MEMBERSHIP['renew'],
            'attr' => array(
                'onclick' => "return confirm('{$LANG_MEMBERSHIP['confirm_renew']}');",
            ),
        ) );
        $notify_action = FieldList::notifyButton(array(
            'name' => 'notify',
            'text' => $LANG_MEMBERSHIP['notify'],
            'attr' => array(
                'onclick' => "return confirm('{$LANG_MEMBERSHIP['confirm_notify']}');",
            ),
        ) );

        $options = array(
            'chkdelete' => 'true',
            'chkfield' => 'mem_uid',
            'chkactions' => $del_action . '&nbsp;&nbsp;' . $renew_action . '&nbsp;&nbsp;' .
                '&nbsp;&nbsp;' . $notify_action,
        );

        if (Config::get('use_mem_number') == MemberNumber::AUTOGEN) {
            $options['chkactions'] .= FieldList::regenButton(array(
                'name' => 'regenbutton',
                'text' => $LANG_MEMBERSHIP['regen_mem_numbers'],
                'attr' => array(
                    'onclick' => "return confirm('{$LANG_MEMBERSHIP['confirm_regen']}');",
                ),
            ) );
        }
        $extra = array(
            'showexp' => $Request->getInt('show_exp'),
        );
        $form_arr = array();
        $retval .= ADMIN_list(
            'membership_memberlist',
            array(__CLASS__, 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr, $filter, $extra,
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
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr, $extra = array()) : string
    {
        global $_CONF, $LANG_ACCESS, $LANG_MEMBERSHIP, $_TABLES, $LANG_ADMIN;

        $retval = '';
        $pi_admin_url = Config::get('admin_url');

        switch($fieldname) {
        case 'edit':
            $showexp = $extra['showexp'] ? '&amp;showexp' : '';
            $retval = FieldList::edit(array(
                'url' => Config::get('admin_url') . '/index.php?editmember=' . $A['mem_uid'] . $showexp,
            ) );
            break;

        case 'app_link':
            $url = Config::get('url') . '/app.php?prt&uid=' . $A['mem_uid'];
            $retval = FieldList::view(array(
                'attr' => array(
                    'onclick' => "popupWindow('{$url}', 'Help', 640, 480, 1)",
                    'title' => $LANG_MEMBERSHIP['view_app'],
                    'class' => 'tooltip',
                )
            ) );
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
    public static function createNameLink(int $uid, string $fullname='') : string
    {
        global $_CONF;

        static $retval = array();

        if (!isset($retval[$uid])) {
            if ($fullname == '') {
                $fullname = COM_getDisplayName($uid);
            }
            $parsed = User::parseName($fullname, 'LCF');
            if (empty($parsed)) {
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
     * Expire memberships that have not been renewed within the grace period.
     *
     * @return  integer     Number of memberships affected
     */
    public static function batchExpire() : int
    {
        global $_TABLES, $LANG_MEMBERSHIP;

        $stat = Status::ACTIVE . ',' . Status::ARREARS;

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        try {
            $data = $qb->select('m.mem_uid', 'm.mem_expires', 'u.fullname')
               ->from($_TABLES['membership_members'], 'm')
               ->leftJoin('m', $_TABLES['users'], 'u', 'u.uid=m.mem_uid')
               ->where('m.mem_status in (:status)')
               ->andWhere('m.mem_expires < :endgrace')
               ->setParameter('status', array(Status::ACTIVE, Status::ARREARS), Database::PARAM_INT_ARRAY)
               ->setParameter('endgrace', Dates::expGraceEnded(), Database::STRING)
               ->execute()
               ->fetchAllAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                self::getInstance($A['mem_uid'])->Expire(true, false);
                Log::write(
                    Config::PI_NAME,
                    Log::INFO,
                    sprintf(
                        $LANG_MEMBERSHIP['log_expired'],
                        $A['mem_uid'],
                        $A['fullname']
                    )
                );
            }
            return count($data);
        } else {
            return 0;
        }
    }


    /**
     * Set overdue memberships to "in arrears".
     * Runs nearly the same query as expirePostGrace() above since expired
     * members now have their statuses changed to "expired"
     *
     * @return  integer     Number of memberships affected
     */
    public static function batchArrears() : int
    {
        global $_TABLES, $LANG_MEMBERSHIP;

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        try {
            $data = $qb->select('m.mem_uid', 'm.mem_expires', 'u.fullname')
               ->from($_TABLES['membership_members'], 'm')
               ->leftJoin('m', $_TABLES['users'], 'u', 'u.uid=m.mem_uid')
               ->where('m.mem_status in (:status)')
               ->andWhere('m.mem_expires < :now')
               ->setParameter('status', array(Status::ACTIVE), Database::PARAM_INT_ARRAY)
               ->setParameter('now', Dates::Today(), Database::STRING)
               ->execute()
               ->fetchAllAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                self::getInstance($A['mem_uid'])->Arrears(true, false);
                Log::write(
                    Config::PI_NAME,
                    Log::INFO,
                    sprintf(
                        $LANG_MEMBERSHIP['log_arrears'],
                        $A['mem_uid'],
                        $A['fullname']
                    )
                );
            }
            return count($data);
        } else {
            return 0;
        }
    }


    /**
     * Purge old membership records that have been expired for some time.
     *
     * @return  integer     Number of memberships affected
     */
    public static function batchPurge() : int
    {
        global $_TABLES, $LANG_MEMBERSHIP;

        $days = (int)Config::get('drop_days');
        if ($days < 0) {
            return 0;
        }

        $db = Database::getInstance();
        try {
            $rows = $db->conn->executeStatement(
                "UPDATE {$_TABLES['membership_members']}
                SET mem_status = ?
                WHERE ? > (mem_expires + interval ? DAY)",
                array(Status::DROPPED, Dates::Today(), $days),
                array(Database::INTEGER, Database::STRING, Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $rows = 0;
        }
        Log::write(
            Config::PI_NAME,
            Log::INFO,
            sprintf($LANG_MEMBERSHIP['log_purged'], $rows, $days)
        );
        return $rows;
    }

}
