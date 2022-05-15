<?php
/**
 * Class to handle board and committee possitions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2014-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;
use glFusion\Database\Database;
use glfusion\Log\Log;


/**
 * Class to handle board and committee positions.
 * @package membership
 */
class Position
{
    /** DB record ID of the position.
     * @var integer */
    private $id = 0;

    /** ID of user currently occupying the posision.
     * @var integer */
    private $uid = 0;

    /** Sequence for ordering in the lists.
     * @var integer */
    private $orderby = 9999;

    /** ID of group where the position's occupant is added.
     * @var integer */
    private $grp_id = 0;

    /** Flag to indicate that this position is enabled.
     * @var integer */
    private $enabled = 1;

    /** Show this position in user-facing lists even if unoccupied?
     * @var integer */
    private $show_vacant = 1;

    /** Description of the position.
     * @var string */
    private $dscp = '';

    /** Contact info for the position's occupant.
     * @var string */
    private $contact = '';

    /** Original group ID, used when editing to detect changes.
     * @var integer */
    private $old_grp_id = 0;

    /** Original user ID, used when editing to detect changes.
     * @var integer */
    private $old_uid = 0;

    /** Include this position in the custom profile's member listing?
     * Only affects whether the position name is shown, the member
     * will still be included.
     * @var boolean */
    private $in_lists = 0;

    /** Group record ID.
     * @var integer */
    private $pg_id = 0;

    /** Group short name, e.g. `Officer`.
     * @var string */
    private $pg_tag = '';

    /** Group full title, e.g. `Club Officers`.
     * @var string */
    private $pg_title = '';


    /**
     * Set variables and read a record if an ID is provided.
     *
     * @param   integer $id     Optional ID of existing position record
     */
    public function __construct($id=0)
    {
        if (is_array($id)) {
            $this->setVars($id, false);
        } elseif ($id > 0) {
            if (!$this->Read($id)) {
                $this->id = 0;
            }
        }
    }


    /**
     * Get the record ID of the position.
     * Used also to see if the position is new or existing.
     *
     * @return  integer     Record ID, zero indicates a new object
     */
    public function getID()
    {
        return (int)$this->id;
    }


    /**
     * Get the group ID associated with this position.
     *
     * @return  integer     Group ID
     */
    public function getGroupID()
    {
        return (int)$this->grp_id;
    }


    /**
     * Read a position from the database.
     *
     * @param   integer $id     Optional ID, current ID if empty
     * @return  boolean     True on success, False on failure
     */
    public function Read(?int $id = NULL) : bool
    {
        global $_TABLES;

        if (empty($id)) $id = $this->id;
        if ($id == 0) return false;     // need a valid ID

        $qb = Database::getInstance()->conn->createQueryBuilder();
        try {
            $data = $qb->select('p.*, pg.pg_id', 'pg.pg_tag', 'pg.pg_title')
               ->from($_TABLES['membership_positions'], 'p')
               ->leftJoin('p', $_TABLES['membership_posgroups'], 'pg', 'p.pg_id=pg.pg_id')
               ->where('p.id = ?')
               ->setParameter(0, $id, Database::INTEGER)
               ->execute()
               ->fetch(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            $data = array();
        }
        if (is_array($data) && !empty($data)) {
            $this->setVars($data);
            return true;
        }
        return false;
    }


    /**
     * Set the values from an array into the object members.
     *
     * @param   array   $A          Array of values
     * @param   boolean $fromDB     True if reading from the DB
     * @return  boolean     True on success, False on failure
     */
    public function setVars($A, $fromDB=true)
    {
        if (!is_array($A)) return false;

        if (isset($A['id'])) $this->id = (int)$A['id'];
        $this->uid      = (int)$A['uid'];
        $this->dscp = $A['descr'];
        $this->orderby  = (int)$A['orderby'];
        $this->contact  = $A['contact'];
        $this->grp_id   = (int)$A['grp_id'];
        $this->old_uid  = isset($A['old_uid']) ? (int)$A['old_uid'] : $this->uid;
        $this->old_grp_id  = isset($A['old_grp_id']) ? (int)$A['old_grp_id'] : $this->grp_id;
        $this->enabled  = isset($A['enabled']) ? (int)$A['enabled'] : 0;
        $this->show_vacant = isset($A['show_vacant']) ? (int)$A['show_vacant'] : 0;
        $this->in_lists = isset($A['in_lists']) ? (int)$A['in_lists'] : 0;
        $this->pg_id = (int)$A['pg_id'];

        if ($fromDB) {
            $this->pg_tag = $A['pg_tag'];
        } else {
            //$this->pg_id = (int)$A['pg_id'];
            /*if (isset($A['position_type']) && !empty($A['position_type'])) {
                $this->type = $A['position_type'];
            } else {
                $this->type = $A['position_type_sel'];
            }*/
        }
        return true;
   }


    /**
     * Save the current values to the database.
     *
     * @param   array   $A  Optional array, current values used if empty
     * @return  boolean     True on success, False on failure
     */
    public function Save($A = array())
    {
        global $_TABLES;

        if (is_array($A) && !empty($A)) {
            $this->setVars($A, false);
        }

        // Get the position group, creating a new one if needed.
        if (isset($A['position_type']) && !empty($A['position_type'])) {
            $PG = PosGroup::getByTag($A['position_type']);
            if ($PG->isNew()) {
                $PG->setTag($A['position_type'])->Save();
            }
            $this->pg_id = $PG->getID();
        }

        $qb = Database::getInstance()->conn->createQueryBuilder();
        if ($this->id == 0) {
            $qb->insert($_TABLES['membership_positions'])
               ->values(array(
                   'pg_id' => ':pg_id',
                   'uid' => ':uid',
                   'descr' => ':dscp',
                   'contact' => ':contact',
                   'show_vacant' => ':show_vacant',
                   'orderby' => ':orderby',
                   'enabled' => ':enabled',
                   'in_lists' => ':in_lists',
                   'grp_id' => ':grp_id',
               ));
        } else {
            $qb->update($_TABLES['membership_positions'])
               ->set('pg_id', ':pg_id')
               ->set('uid', ':uid')
               ->set('descr', ':dscp')
               ->set('contact', ':contact')
               ->set('show_vacant', ':show_vacant')
               ->set('orderby', ':orderby')
               ->set('enabled', ':enabled')
               ->set('in_lists', ':in_lists')
               ->set('grp_id', ':grp_id')
               ->where('id = :id')
               ->setParameter('id', $this->id, Database::INTEGER);
        }
        try {
            $qb->setParameter('pg_id', $this->pg_id, Database::INTEGER)
               ->setParameter('uid', $this->uid, Database::INTEGER)
               ->setParameter('dscp', $this->dscp, Database::STRING)
               ->setParameter('contact', $this->contact, Database::STRING)
               ->setParameter('show_vacant', $this->show_vacant, Database::INTEGER)
               ->setParameter('orderby', $this->orderby, Database::INTEGER)
               ->setParameter('enabled', $this->enabled, Database::INTEGER)
               ->setParameter('in_lists', $this->in_lists, Database::INTEGER)
               ->setParameter('grp_id', $this->grp_id, Database::INTEGER)
               ->execute();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            return false;
        }
        self::reOrder($this->pg_id);
        // Check and change group memberships if necessary
        $this->_updateGroups();
        return true;
    }


    /**
     * Set the given member as the occupant of a position.
     *
     * @param   integer $uid    User ID to hold the position
     * @return  boolean     Results from Save()
     */
    public function setMember($uid)
    {
        $this->uid = (int)$uid;
        return $this->Save();
    }


    /**
     * Get the positions held by a member.
     *
     * @param   integer $uid    Member's User ID
     * @return  array       Array of position IDs
     */
    public static function getByMember(int $uid) : array
    {
        global $_TABLES;

        $retval = array();
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['membership_positions']}
                WHERE uid = ?",
                array($uid),
                array(Database::INTEGER)
            )->fetchAll(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $retval[] = new self($A);
            }
        }
        return $retval;
    }


    /**
     * Sets a boolean field to the opposite of the supplied value.
     *
     * @param   integer $oldvalue   Old (current) value
     * @param   string  $field      Name of DB field to set
     * @param   integer $id         ID number of element to modify
     * @return         New value, or old value upon failure
     */
    public static function toggle(int $oldvalue, string $field, int $id) : int
    {
        global $_TABLES;

        // If it's still an invalid ID, return the old value
        if ($id == '')
            return $oldvalue;

        switch ($field) {       // sanitize
        case 'enabled':
        case 'show_vacant':
            break;
        default:
            return $oldvalue;
        }

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $db = Database::getInstance();
        try {
            $db->conn->executeUpdate(
                "UPDATE {$_TABLES['membership_positions']}
                SET $field = ?
                WHERE id = ?",
                array($newvalue, $id),
                array(Database::INTEGER, Database::INTEGER)
            );
            return $newvalue;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            return $oldvalue;
        }
    }


    /**
     * Remove the current position.
     */
    public function Delete()
    {
        global $_TABLES;

        // First remove the member->group assignment, if any
        $this->uid = 0;
        $this->grp_id = 0;
        $this->_updateGroups();

        // Then delete the position record
        $db = Database::getInstance();
        $db->conn->delete(
            $_TABLES['membership_positions'],
            array('id'),
            array($this->id),
            array(Database::INTEGER)
        );
    }


    /**
     * Creates the edit form.
     *
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES;

        $T = new \Template(Config::get('pi_path') . 'templates');
        $T->set_file('editform', 'position_form.thtml');
        $T->set_var(array(
            'action_url'    => Config::get('admin_url'),
            'id'            => $this->id,
            'description'   => $this->dscp,
            'option_user_select' => COM_optionList(
                $_TABLES['users'],
                'uid,fullname',
                $this->uid, 1
            ),
            'orderby_sel'   => self::getOrderbyOptions($this->pg_id, $this->orderby),
            'show_vacant_chk'   => $this->show_vacant ? 'checked="checked"' : '',
            'in_lists_chk'  => $this->in_lists ? 'checked="checked"' : '',
            'ena_chk'       => $this->enabled ? 'checked="checked"' : '',
            'position_type_select' => COM_optionList(
                $_TABLES['membership_posgroups'],
                'pg_id,pg_tag,pg_orderby',
                $this->pg_id,
                2
            ),
            'contact'       => $this->contact,
            'grp_select'    => COM_optionList($_TABLES['groups'],
                            'grp_id,grp_name', $this->grp_id, 1),
            'old_grp_id'    => $this->old_grp_id,
            'old_uid'       => $this->old_uid,
            'doc_url'       => MEMBERSHIP_getDocURL('position.html', 'membership'),
            'pg_id'         => $this->pg_id,
            'orderby'       => $this->orderby,
         ) );
        return $T->parse('output', 'editform');
    }   // function Edit()


    /**
     * Reorder the positions for admin lists and information pages.
     *
     * @param   integer $pg_id  Position Group record ID
     */
    public static function reOrder($pg_id)
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT id, orderby FROM {$_TABLES['membership_positions']}
                WHERE pg_id = ?
                ORDER BY orderby ASC",
                array($pg_id),
                array(Database::INTEGER)
            )->fetchAll(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            $data = array();
        }

        $order = 10;
        $stepNumber = 10;
        $retval = '';
        if (is_array($data)) {
            foreach ($data as $A) {
                if ($A['orderby'] != $order) {  // only update incorrect ones
                    try {
                        $db->conn->executeUpdate(
                            "UPDATE {$_TABLES['membership_positions']}
                            SET orderby = '$order'
                            WHERE id = '{$A['id']}'",
                            array($order, $A['id']),
                            array(Database::INTEGER, Database::INTEGER)
                        );
                    } catch (\Throwable $e) {
                        Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
                        $retval = '5';
                    }
                }
            }
            $order += $stepNumber;
        }
        return $retval;
    }


    /**
     * Move a position up or down in the list.
     *
     * @param   integer $id     Record ID to move
     * @param   integer $pg_id      Position Group ID
     * @param   string  $where  Direction to move ('up' or 'down')
     */
    public static function Move(int $id, int $pg_id, string $where) : string
    {
        global $_CONF, $_TABLES, $LANG21;

        $retval = '';
        $id = (int)$id;
        $pg_id = (int)$pg_id;

        switch ($where) {
        case 'up':
            $sign = '-';
            break;

        case 'down':
            $sign = '+';
            break;

        default:
            return '';
            break;
        }
        $db = Database::getInstance();
        try {
            $db->conn->executeUpdate(
                "UPDATE {$_TABLES['membership_positions']}
                SET orderby = orderby $sign 11
                WHERE id = ?",
                array($id),
                array(Database::INTEGER)
            );
            self::reOrder($pg_id);
            $msg = '';
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            $msg = '5';
        }
        return $msg;
    }


    /**
     * Update group membership based on changes in position.
     * If the group or user ID have changed, remove the old user
     * from the old group and add the new user to the new group
     */
    private function _updateGroups()
    {
        USES_lib_user();

        if (
            $this->old_grp_id != $this->grp_id ||
            $this->old_uid != $this->uid
        ) {
            if (
                $this->old_grp_id != 0 &&
                $this->old_uid != 0
            ) {
                $Positions = self::getByMember($this->old_uid);
                $del_from_group = true;
                foreach ($Positions as $Position) {
                    // Check if the member belongs to any position except this
                    // one that has the same group ID assignment. If so, do
                    // not remove the user from the group.
                    if (
                        $Position->getID() != $this->id &&
                        $Position->getGroupID() == $this->old_grp_id
                    ) {
                        $del_from_group = false;
                        break;
                    }
                }
                if ($del_from_group) {
                    // used to be a member in this position, now maybe not
                    USER_delGroup($this->old_grp_id, $this->old_uid);
                }
            }
            if ($this->grp_id != 0 && $this->uid != 0) {
                // There is a user in this position, add to the group
                USER_addGroup($this->grp_id, $this->uid);
            }
        }
        return $this;
    }


    /**
     * Displays the list of committee and board positions.
     *
     * @return  string  HTML string containing the contents of the ipnlog
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $LANG_MEMBERSHIP, $_USER, $LANG_ADMIN;

        USES_lib_admin();

        $sql = "SELECT p.*,u.fullname, pg.pg_tag
            FROM {$_TABLES['membership_positions']} p
            LEFT JOIN {$_TABLES['membership_posgroups']} pg
            ON pg.pg_id = p.pg_id
            LEFT JOIN {$_TABLES['users']} u
            ON u.uid = p.uid";
//            WHERE 1=1 ";

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['edit'],
                'field' => 'editpos',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_MEMBERSHIP['move'],
                'field' => 'move',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_MEMBERSHIP['enabled'],
                'field' => 'enabled',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_MEMBERSHIP['position_type'],
                'field' => 'pg_tag',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['description'],
                'field' => 'descr',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['current_user'],
                'field' => 'fullname',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['order'],
                'field' => 'orderby',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['show_vacant'],
                'field' => 'show_vacant',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'deletepos',
                'sort' => 'false',
                'align' => 'center'
            ),
        );

        $query_arr = array(
            'table' => 'membership_positions',
            'sql' => $sql,
            'query_fields' => array('u.fullname', 'p.descr'),
            'default_filter' => '',
        );
        $defsort_arr = array(
            'field' => 'pg.pg_orderby,p.orderby',
            'direction' => 'ASC'
        );
        $filter = '';
        $text_arr = array(
            'form_url' => Config::get('admin_url') . '/index.php?positions',
        );

        $options = array(
            'chkdelete' => true,
            'chkfield' => 'id',
        );
        if (!isset($_REQUEST['query_limit'])) {
            $_GET['query_limit'] = 20;
        }

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_MEMBERSHIP['new_position'],
            Config::get('admin_url') . '/index.php?editpos=0',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );
        $display .= ADMIN_list(
            'membership_positions',
            array(__CLASS__, 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Determine what to display in the admin list for each position.
     *
     * @param   string  $fieldname  Name of the field, from database
     * @param   mixed   $fieldvalue Value of the current field
     * @param   array   $A          Array of all name/field pairs
     * @param   array   $icon_arr   Array of system icons
     * @return  string              HTML for the field cell
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ACCESS, $LANG_MEMBERSHIP;

        $retval = '';

        $pi_admin_url = Config::get('admin_url');
        switch($fieldname) {
        case 'editpos':
            $retval = FieldList::edit(array(
                'url' => Config::get('admin_url') . '/index.php?editpos=' . $A['id'],
                'attr' => array(
                    'class' => 'tooltip',
                    'title' => $LANG_MEMBERSHIP['edit'],
                ),
            ) );
            break;

        case 'move':
            $base_url = Config::get('admin_url') .
                '/index.php?type=' . urlencode($A['pg_id']) .
                '&id=' . $A['id'] . '&reorderpos=';
            $retval .= FieldList::up(array(
                'url' => $base_url . 'up',
            ) );
            $retval .= '&nbsp;' . FieldList::down(array(
                'url' => $base_url . 'down',
            ) );
            break;

        case 'deletepos':
            $retval = FieldList::delete(array(
                'delete_url' => Config::get('admin_url') . '/index.php?deletepos=' . $A['id'],
                'attr' => array(
                    'onclick' => "return confirm('{$LANG_MEMBERSHIP['q_del_item']}');",
                    'class' => 'tooltip',
                    'title' => $LANG_MEMBERSHIP['hlp_delete'],
                ),
            ) );
            break;

        case 'pg_tag':
            $retval = COM_createLink(
                $fieldvalue,
                Config::get('url') . '/group.php?type=' . $fieldvalue
            );
            break;

        case 'fullname':
            if ($A['uid'] == 0) {
                $retval = '<i>' . $LANG_MEMBERSHIP['vacant'] . '</i>';
            } else {
                $retval = COM_createLink(
                    $fieldvalue,
                    $_CONF['site_url'] . '/users.php?mode=profile&uid=' . $A['uid']
                );
            }
            break;

        case 'enabled':
        case 'show_vacant':
            $retval = FieldList::checkbox(array(
                'id' => $fieldname . '_' . $A['id'],
                'title' => $LANG_MEMBERSHIP['hlp_' . $fieldname],
                'checked' => $fieldvalue == 1,
                'class' => 'tooltip',
                'onclick' => "MEMB_toggle(this, '{$A['id']}', 'position', '$fieldname', '$pi_admin_url');",
            ) );
            break;

        default:
            $retval = $fieldvalue;
            break;
        }

        return $retval;
    }


    /**
     * Get the HTML for the option values in the "position after" field.
     *
     * @param   integer $pg_id      Position Group ID
     * @param   integer $orderby    Current orderby value.
     * @return  string      HTML for option values.
     */
    public static function getOrderbyOptions($pg_id, $orderby)
    {
        global $_TABLES, $LANG_MEMBERSHIP;

        $pg_id = (int)$pg_id;
        $orderby = (int)$orderby;
        if ($orderby > 0) {
            $orderby_sel = $orderby - 10;
        } else {
            $orderby_sel = 1;
        }
        $retval = '<option value="1">-- ' . $LANG_MEMBERSHIP['first'] . '--</option>' . LB;
        $retval .= COM_optionList(
            $_TABLES['membership_positions'],
            'orderby,descr',
            $orderby_sel,
            1,
            "pg_id = $pg_id AND orderby <> {$orderby}"
        );
        return $retval;
    }
}
